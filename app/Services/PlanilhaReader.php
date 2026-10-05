<?php

namespace App\Services;

/**
 * Leitor nativo de planilhas (CSV e XLSX) sem dependencias externas.
 * Usa fgetcsv para CSV e ZipArchive + SimpleXML para XLSX.
 *
 * Retorna uma lista de linhas associativas, com as chaves normalizadas a
 * partir do cabecalho (primeira linha): minusculas, sem acento, espacos -> "_".
 */
class PlanilhaReader
{
    /**
     * @return array<int,array<string,string>>
     * @throws \RuntimeException
     */
    public function ler(string $path, string $extensao): array
    {
        $extensao = strtolower(trim($extensao));

        if (in_array($extensao, ['xlsx', 'xlsm'], true)) {
            $matriz = $this->lerXlsx($path);
        } elseif (in_array($extensao, ['csv', 'txt'], true)) {
            $matriz = $this->lerCsv($path);
        } else {
            throw new \RuntimeException('Formato nao suportado: ' . $extensao . '. Use CSV ou XLSX.');
        }

        return $this->combinarCabecalho($matriz);
    }

    /** Converte uma matriz (linhas x colunas) em linhas associativas pelo cabecalho. */
    private function combinarCabecalho(array $matriz): array
    {
        // Remove linhas totalmente vazias.
        $matriz = array_values(array_filter($matriz, function ($linha) {
            foreach ($linha as $c) {
                if (trim((string) $c) !== '') {
                    return true;
                }
            }
            return false;
        }));

        if (count($matriz) < 1) {
            return [];
        }

        $cabecalho = array_map([$this, 'normalizarChave'], array_shift($matriz));

        $linhas = [];
        foreach ($matriz as $linha) {
            $assoc = [];
            foreach ($cabecalho as $i => $chave) {
                if ($chave === '') {
                    continue;
                }
                $assoc[$chave] = isset($linha[$i]) ? trim((string) $linha[$i]) : '';
            }
            $linhas[] = $assoc;
        }

        return $linhas;
    }

    private function normalizarChave($chave): string
    {
        $chave = (string) $chave;
        // Remove BOM
        $chave = preg_replace('/^\xEF\xBB\xBF/', '', $chave);
        $chave = $this->retiraAcentos($chave);
        $chave = strtolower(trim($chave));
        $chave = preg_replace('/[^a-z0-9]+/', '_', $chave);
        return trim($chave, '_');
    }

    // ===================================================================
    // CSV
    // ===================================================================

    private function lerCsv(string $path): array
    {
        $conteudo = file_get_contents($path);
        if ($conteudo === false) {
            throw new \RuntimeException('Nao foi possivel ler o arquivo CSV.');
        }

        // Normaliza encoding para UTF-8.
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1, Windows-1252, UTF-8');
        }
        // Remove BOM
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        // Detecta delimitador na primeira linha (; ou , ou tab).
        $primeiraLinha = strtok($conteudo, "\r\n");
        $delimitador = ';';
        $contagens = [
            ';' => substr_count((string) $primeiraLinha, ';'),
            ',' => substr_count((string) $primeiraLinha, ','),
            "\t" => substr_count((string) $primeiraLinha, "\t"),
        ];
        arsort($contagens);
        $delimitador = (string) array_key_first($contagens);
        if ($contagens[$delimitador] === 0) {
            $delimitador = ';';
        }

        $matriz = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $conteudo);
        rewind($fh);
        while (($linha = fgetcsv($fh, 0, $delimitador)) !== false) {
            $matriz[] = $linha;
        }
        fclose($fh);

        return $matriz;
    }

    // ===================================================================
    // XLSX (ZipArchive + SimpleXML)
    // ===================================================================

    private function lerXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('Extensao ZipArchive indisponivel no servidor; use CSV.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo XLSX.');
        }

        // Shared strings.
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false && $ssXml !== '') {
            $xml = $this->carregarXml($ssXml);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $shared[] = $this->textoSi($si);
                }
            }
        }

        // Descobre a primeira planilha.
        $sheetPath = $this->primeiraPlanilha($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            throw new \RuntimeException('Planilha vazia ou ilegivel no XLSX.');
        }

        $xml = $this->carregarXml($sheetXml);
        if ($xml === false) {
            throw new \RuntimeException('Nao foi possivel interpretar o XLSX.');
        }

        $matriz = [];
        foreach ($xml->sheetData->row as $row) {
            $linha = [];
            $maxCol = -1;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = $this->colIndex($ref);
                $tipo = (string) $c['t'];

                if ($tipo === 's') {
                    $idx = (int) $c->v;
                    $valor = $shared[$idx] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = $this->textoSi($c->is);
                } else {
                    $valor = isset($c->v) ? (string) $c->v : '';
                }

                $linha[$col] = $valor;
                if ($col > $maxCol) {
                    $maxCol = $col;
                }
            }

            // Preenche colunas faltantes para manter alinhamento.
            $completa = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $completa[$i] = $linha[$i] ?? '';
            }
            $matriz[] = $completa;
        }

        return $matriz;
    }

    /**
     * Carrega um XML do XLSX neutralizando o namespace padrao, para que o
     * SimpleXML permita acessar os elementos por ->sheetData->row, ->si, etc.
     */
    private function carregarXml(string $conteudo)
    {
        // Remove declaracoes de namespace (default e com prefixo).
        $conteudo = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $conteudo);
        // Remove prefixos de namespace nos tags: <x:row> -> <row>, </x:row> -> </row>.
        $conteudo = preg_replace('/<(\/?)[A-Za-z0-9_]+:/', '<$1', $conteudo);
        return @simplexml_load_string($conteudo);
    }

    /** Extrai o texto de um no <si> (ou <is>) considerando runs <r><t>. */
    private function textoSi($si): string
    {
        if ($si === null) {
            return '';
        }
        if (isset($si->t) && count($si->t) > 0 && !isset($si->r)) {
            return (string) $si->t;
        }
        $texto = '';
        if (isset($si->r)) {
            foreach ($si->r as $r) {
                $texto .= (string) $r->t;
            }
        }
        if ($texto === '' && isset($si->t)) {
            $texto = (string) $si->t;
        }
        return $texto;
    }

    private function primeiraPlanilha(\ZipArchive $zip): string
    {
        // Tenta o padrao mais comum primeiro.
        if ($zip->getFromName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', (string) $nome)) {
                return $nome;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** Converte "AB12" -> indice de coluna 0-based (AB -> 27). */
    private function colIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return 0;
        }
        $letras = $m[1];
        $n = 0;
        $len = strlen($letras);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letras[$i]) - ord('A') + 1);
        }
        return $n - 1;
    }

    private function retiraAcentos(string $texto): string
    {
        $de = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ','Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
        $para = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N'];
        return str_replace($de, $para, $texto);
    }
}
