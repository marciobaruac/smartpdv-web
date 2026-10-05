<?php
/**
 * migrar_vendas_dia.php
 * -----------------------------------------------------------------------------
 * Migra as VENDAS DE PDV (venda_caixas) de um dia específico de um banco de
 * origem para outro banco de destino que JÁ EXISTE, gerando NOVOS IDs no destino
 * e re-mapeando todas as tabelas filhas. Nunca reaproveita o id da origem.
 *
 * Cadeia migrada:
 *   venda_caixas            (mestre)          -> novo id
 *   item_venda_caixas       (venda_caixa_id)  -> re-mapeado
 *   conta_recebers          (venda_caixa_id)  -> re-mapeado   [--financeiro]
 *   estoque_movs            (mov. de estoque) -> novo id      [--estoque]
 *   estoquemovpdvs          (estoquemov_id + estoquepdv_id) re-mapeados [--estoque]
 *
 * Características:
 *   - Mapeia por NOME de coluna (interseção origem ∩ destino). Os esquemas dos
 *     bancos pantanal* divergem (43 vs 59 colunas), então SELECT * não serve.
 *   - Preserva created_at/updated_at/data_registro (mantém a data 15/09).
 *   - DRY-RUN por padrão: só grava de verdade com --confirm.
 *   - Roda tudo dentro de UMA transação (rollback automático em erro ou dry-run).
 *
 * Uso:
 *   php scripts/migrar_vendas_dia.php --target=pantanal4
 *   php scripts/migrar_vendas_dia.php --target=pantanal4 --confirm
 *   php scripts/migrar_vendas_dia.php --target=pantanal2 --date=2026-09-15 --confirm
 *   php scripts/migrar_vendas_dia.php --target=pantanal3 --no-estoque --no-financeiro --confirm
 *
 * Flags:
 *   --target=NOME     (obrigatório) banco de destino
 *   --date=YYYY-MM-DD (padrão 2026-09-15) dia das vendas a migrar
 *   --source=NOME     (padrão: DB_DATABASE do .env) banco de origem
 *   --estoque / --no-estoque         (padrão: liga) migra o movimento de estoque
 *   --financeiro / --no-financeiro   (padrão: liga) migra contas a receber
 *   --confirm         grava de verdade (sem isso é simulação/rollback)
 * -----------------------------------------------------------------------------
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ---------------------------------------------------------------------------
// 1) Argumentos
// ---------------------------------------------------------------------------
$opts = [
    'target'     => null,
    'source'     => null,
    'date'       => '2026-09-15',
    'estoque'    => true,
    'financeiro' => true,
    'confirm'    => false,
];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--confirm')            { $opts['confirm'] = true; continue; }
    if ($arg === '--estoque')            { $opts['estoque'] = true; continue; }
    if ($arg === '--no-estoque')         { $opts['estoque'] = false; continue; }
    if ($arg === '--financeiro')         { $opts['financeiro'] = true; continue; }
    if ($arg === '--no-financeiro')      { $opts['financeiro'] = false; continue; }
    if (preg_match('/^--target=(.+)$/', $arg, $m)) { $opts['target'] = $m[1]; continue; }
    if (preg_match('/^--source=(.+)$/', $arg, $m)) { $opts['source'] = $m[1]; continue; }
    if (preg_match('/^--date=(.+)$/',   $arg, $m)) { $opts['date']   = $m[1]; continue; }
    fwrite(STDERR, "Argumento desconhecido: $arg\n");
    exit(1);
}

if (!$opts['target']) {
    fwrite(STDERR, "ERRO: informe --target=NOME_DO_BANCO\n");
    fwrite(STDERR, "Ex.: php scripts/migrar_vendas_dia.php --target=pantanal4 --confirm\n");
    exit(1);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opts['date'])) {
    fwrite(STDERR, "ERRO: --date deve ser YYYY-MM-DD\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 2) Credenciais do .env
// ---------------------------------------------------------------------------
function loadEnv(string $path): array {
    $env = [];
    if (!is_file($path)) return $env;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // remove aspas simples/duplas envolventes
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v)-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $env[$k] = $v;
    }
    return $env;
}

$root = dirname(__DIR__);
$env  = loadEnv($root . '/.env');

$host = $env['DB_HOST']     ?? '127.0.0.1';
$port = $env['DB_PORT']     ?? '3306';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$srcDb = $opts['source'] ?: ($env['DB_DATABASE'] ?? '');
$dstDb = $opts['target'];

if ($srcDb === '') {
    fwrite(STDERR, "ERRO: não achei DB_DATABASE no .env; use --source=NOME\n");
    exit(1);
}
if ($srcDb === $dstDb) {
    fwrite(STDERR, "ERRO: origem e destino são o mesmo banco ($srcDb).\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 3) Conexão (uma só; qualificamos os bancos pelo nome nas queries)
// ---------------------------------------------------------------------------
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Dados legados contêm datas '0000-00-00'; relaxa o modo estrito para
    // permitir inserir esses valores no destino (igual ao app antigo).
    $pdo->exec("SET SESSION sql_mode=''");
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO ao conectar no MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

function q(PDO $pdo, string $sql, array $params = []): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function scalar(PDO $pdo, string $sql, array $params = []) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}
function dbExists(PDO $pdo, string $db): bool {
    return (bool) scalar($pdo,
        "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?", [$db]);
}
function tableColumns(PDO $pdo, string $db, string $table): array {
    $rows = q($pdo,
        "SELECT column_name AS c FROM information_schema.columns
         WHERE table_schema=? AND table_name=? ORDER BY ordinal_position", [$db, $table]);
    return array_column($rows, 'c');
}
function qid(string $name): string { return '`' . str_replace('`', '', $name) . '`'; }

// valida bancos
foreach ([$srcDb, $dstDb] as $db) {
    if (!dbExists($pdo, $db)) {
        fwrite(STDERR, "ERRO: banco '$db' não existe.\n");
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 4) Helper genérico de migração com re-mapeamento de FKs
//    Retorna map [old_id => new_id]. Colunas usadas = interseção origem∩destino
//    (menos 'id'). Colunas em $fkRemap têm o valor traduzido pelo mapa dado.
// ---------------------------------------------------------------------------
function migrateTable(
    PDO $pdo, string $srcDb, string $dstDb, string $table,
    string $whereSql, array $whereParams,
    array $fkRemap /* colName => &map (old=>new) */,
    array &$warnings
): array {
    $srcCols = tableColumns($pdo, $srcDb, $table);
    $dstCols = tableColumns($pdo, $dstDb, $table);
    $common  = array_values(array_intersect($srcCols, $dstCols));
    $common  = array_values(array_diff($common, ['id'])); // id é auto-increment no destino

    if (!in_array('id', $srcCols, true)) {
        throw new RuntimeException("Tabela $table não tem coluna id na origem.");
    }
    if (empty($common)) {
        throw new RuntimeException("Sem colunas em comum para $table entre $srcDb e $dstDb.");
    }

    $rows = q($pdo, "SELECT * FROM " . qid($srcDb) . "." . qid($table) . " WHERE $whereSql ORDER BY id", $whereParams);

    $colList     = implode(',', array_map('qid', $common));
    $placeholders = implode(',', array_fill(0, count($common), '?'));
    $insertSql   = "INSERT INTO " . qid($dstDb) . "." . qid($table) . " ($colList) VALUES ($placeholders)";
    $ins = $pdo->prepare($insertSql);

    $map = [];
    foreach ($rows as $row) {
        $oldId = $row['id'];
        $vals  = [];
        foreach ($common as $col) {
            $val = $row[$col] ?? null;
            if (isset($fkRemap[$col]) && $val !== null) {
                $fkMap = $fkRemap[$col];
                if (array_key_exists($val, $fkMap)) {
                    $val = $fkMap[$val];
                } else {
                    // FK que não foi migrada nesta rodada: mantém valor original e avisa
                    $warnings[] = "$table#$oldId: FK $col=$val não re-mapeada (mantido valor original).";
                }
            }
            $vals[] = $val;
        }
        $ins->execute($vals);
        $map[$oldId] = (int) $pdo->lastInsertId();
    }
    return $map;
}

// ---------------------------------------------------------------------------
// 5) Execução
// ---------------------------------------------------------------------------
$date = $opts['date'];
$warnings = [];

echo str_repeat('=', 74) . "\n";
echo "MIGRAÇÃO DE VENDAS DE PDV\n";
echo str_repeat('=', 74) . "\n";
echo "Origem   : $srcDb\n";
echo "Destino  : $dstDb\n";
echo "Dia      : $date\n";
echo "Estoque  : " . ($opts['estoque'] ? 'SIM' : 'não') . "\n";
echo "Financeiro (contas a receber): " . ($opts['financeiro'] ? 'SIM' : 'não') . "\n";
echo "Modo     : " . ($opts['confirm'] ? '>>> GRAVAÇÃO REAL (--confirm) <<<' : 'DRY-RUN (simulação, rollback no final)') . "\n";
echo str_repeat('-', 74) . "\n";

// IDs de venda_caixas do dia (origem)
$vendaIds = array_map('intval', array_column(
    q($pdo, "SELECT id FROM " . qid($srcDb) . ".venda_caixas WHERE DATE(data_registro)=? ORDER BY id", [$date]),
    'id'
));

if (empty($vendaIds)) {
    echo "Nenhuma venda_caixa encontrada em $srcDb no dia $date. Nada a fazer.\n";
    exit(0);
}
$inList = implode(',', $vendaIds);
echo "Vendas na origem: " . count($vendaIds) . " (ids " . min($vendaIds) . "–" . max($vendaIds) . ")\n";

// --- Preflight: possível duplicidade no destino (mesmo dia) ---
$jaNoDestino = (int) scalar($pdo,
    "SELECT COUNT(*) FROM " . qid($dstDb) . ".venda_caixas WHERE DATE(data_registro)=?", [$date]);
if ($jaNoDestino > 0) {
    echo "\n *** ATENÇÃO: o destino '$dstDb' JÁ tem $jaNoDestino venda(s) em $date. ***\n";
    echo " *** Rodar com --confirm vai ADICIONAR de novo (duplicar). Confira antes. ***\n";
}

// --- Preflight: integridade referencial (só avisa) ---
$prodFaltando = (int) scalar($pdo,
    "SELECT COUNT(DISTINCT i.produto_id) FROM " . qid($srcDb) . ".item_venda_caixas i
     WHERE i.venda_caixa_id IN ($inList)
       AND i.produto_id NOT IN (SELECT id FROM " . qid($dstDb) . ".produtos)");
$cliFaltando = (int) scalar($pdo,
    "SELECT COUNT(DISTINCT v.cliente_id) FROM " . qid($srcDb) . ".venda_caixas v
     WHERE v.id IN ($inList) AND v.cliente_id IS NOT NULL
       AND v.cliente_id NOT IN (SELECT id FROM " . qid($dstDb) . ".clientes)");
$usrFaltando = (int) scalar($pdo,
    "SELECT COUNT(DISTINCT v.usuario_id) FROM " . qid($srcDb) . ".venda_caixas v
     WHERE v.id IN ($inList)
       AND v.usuario_id NOT IN (SELECT id FROM " . qid($dstDb) . ".usuarios)");
if ($prodFaltando || $cliFaltando || $usrFaltando) {
    echo "\n Integridade (referências que NÃO existem no destino, ficarão órfãs):\n";
    if ($prodFaltando) echo "   - produtos: $prodFaltando\n";
    if ($cliFaltando)  echo "   - clientes: $cliFaltando\n";
    if ($usrFaltando)  echo "   - usuários: $usrFaltando\n";
}
echo str_repeat('-', 74) . "\n";

// --- Transação ---
$pdo->beginTransaction();
try {
    // 1) venda_caixas
    $mapVenda = migrateTable($pdo, $srcDb, $dstDb, 'venda_caixas',
        "DATE(data_registro)=?", [$date], [], $warnings);
    echo "venda_caixas ......... " . count($mapVenda) . " inseridas\n";

    // 2) item_venda_caixas
    $mapItem = migrateTable($pdo, $srcDb, $dstDb, 'item_venda_caixas',
        "venda_caixa_id IN ($inList)", [],
        ['venda_caixa_id' => $mapVenda], $warnings);
    echo "item_venda_caixas .... " . count($mapItem) . " inseridos\n";

    // 3) conta_recebers (financeiro)
    if ($opts['financeiro']) {
        $mapConta = migrateTable($pdo, $srcDb, $dstDb, 'conta_recebers',
            "venda_caixa_id IN ($inList)", [],
            ['venda_caixa_id' => $mapVenda], $warnings);
        echo "conta_recebers ....... " . count($mapConta) . " inseridas\n";
    }

    // 4) estoque (estoque_movs -> estoquemovpdvs)
    if ($opts['estoque']) {
        $oldItemIds = array_keys($mapItem);
        if (!empty($oldItemIds)) {
            $itemIn = implode(',', array_map('intval', $oldItemIds));

            // ids de estoque_movs ligados aos itens migrados (via estoquemovpdvs.estoquepdv_id)
            $movIds = array_map('intval', array_column(
                q($pdo, "SELECT DISTINCT estoquemov_id
                          FROM " . qid($srcDb) . ".estoquemovpdvs
                          WHERE estoquepdv_id IN ($itemIn) AND estoquemov_id IS NOT NULL"),
                'estoquemov_id'
            ));

            $mapMov = [];
            if (!empty($movIds)) {
                $movIn  = implode(',', $movIds);
                $mapMov = migrateTable($pdo, $srcDb, $dstDb, 'estoque_movs',
                    "id IN ($movIn)", [], [], $warnings);
                echo "estoque_movs ......... " . count($mapMov) . " inseridos\n";
            } else {
                echo "estoque_movs ......... 0 (nenhum ligado)\n";
            }

            // estoquemovpdvs: re-mapeia estoquemov_id (via mapMov) e estoquepdv_id (via mapItem)
            $mapPdv = migrateTable($pdo, $srcDb, $dstDb, 'estoquemovpdvs',
                "estoquepdv_id IN ($itemIn)", [],
                ['estoquemov_id' => $mapMov, 'estoquepdv_id' => $mapItem], $warnings);
            echo "estoquemovpdvs ....... " . count($mapPdv) . " inseridos\n";
        }
        echo "  (OBS: o LEDGER de estoque foi replicado; o SALDO em `estoques` NÃO foi\n";
        echo "   alterado por este script — ajuste o saldo à parte, se necessário.)\n";
    }

    echo str_repeat('-', 74) . "\n";
    if (!empty($warnings)) {
        echo "AVISOS (" . count($warnings) . "):\n";
        foreach (array_slice($warnings, 0, 30) as $w) echo "   - $w\n";
        if (count($warnings) > 30) echo "   ... (+" . (count($warnings) - 30) . ")\n";
        echo str_repeat('-', 74) . "\n";
    }

    if ($opts['confirm']) {
        $pdo->commit();
        echo "OK: alterações GRAVADAS (commit) em $dstDb.\n";
    } else {
        $pdo->rollBack();
        echo "DRY-RUN: nada foi gravado (rollback). Rode de novo com --confirm para valer.\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "\nERRO durante a migração (rollback aplicado): " . $e->getMessage() . "\n");
    exit(1);
}
