<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NuvemFiscalNfceService
{
    private $baseUrl;
    private $token;
    private $timeout;
    private $clientId;
    private $clientSecret;
    private $tokenUrl;
    private $envFileCache = null;
    private $lastAuthError = '';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) ($this->readEnv('NUVEMFISCAL_BASE_URL') ?: $this->readEnv('NUVEM_FISCAL_BASE_URL')), '/');
        if ($this->baseUrl === '') {
            $this->baseUrl = 'https://api.nuvemfiscal.com.br';
        }

        $this->token = trim((string) ($this->readEnv('NUVEMFISCAL_TOKEN') ?: $this->readEnv('NUVEM_FISCAL_TOKEN')));
        $this->clientId = trim((string) ($this->readEnv('NUVEM_FISCAL_CLIENT_ID') ?: $this->readEnv('NUVEMFISCAL_CLIENT_ID')));
        $this->clientSecret = trim((string) ($this->readEnv('NUVEM_FISCAL_CLIENT_SECRET') ?: $this->readEnv('NUVEMFISCAL_CLIENT_SECRET')));
        $this->tokenUrl = trim((string) ($this->readEnv('NUVEM_FISCAL_TOKEN_URL') ?: $this->readEnv('NUVEMFISCAL_TOKEN_URL')));
        if ($this->tokenUrl === '') {
            $this->tokenUrl = 'https://auth.nuvemfiscal.com.br/oauth/token';
        }

        $this->timeout = (int) (($this->readEnv('NUVEMFISCAL_TIMEOUT') ?: $this->readEnv('NUVEM_FISCAL_TIMEOUT')) ?: 45);
        if ($this->timeout < 10) {
            $this->timeout = 10;
        }

        Log::channel('nfce')->info('[NuvemFiscal] Serviço iniciado', [
            'base_url'   => $this->baseUrl,
            'token_url'  => $this->tokenUrl,
            'client_id'  => $this->clientId !== '' ? substr($this->clientId, 0, 6) . '***' : '(VAZIO)',
            'secret_ok'  => $this->clientSecret !== '' ? 'sim' : '(VAZIO)',
            'token_fixo' => $this->token !== '' ? 'sim' : 'nao',
        ]);
    }

    private function readEnv(string $key): string
    {
        $value = '';

        if (function_exists('env')) {
            $v = env($key);
            if ($v !== null && $v !== false) {
                $value = (string) $v;
            }
        }

        if ($value === '') {
            $v = getenv($key);
            if ($v !== false && $v !== null) {
                $value = (string) $v;
            }
        }

        if ($value === '' && isset($_ENV[$key])) {
            $value = (string) $_ENV[$key];
        }

        if ($value === '' && isset($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
        }

        if ($value === '') {
            $fileVars = $this->readDotEnvFile();
            if (isset($fileVars[$key])) {
                $value = (string) $fileVars[$key];
            }
        }

        return trim($value);
    }

    private function readDotEnvFile(): array
    {
        if (is_array($this->envFileCache)) {
            return $this->envFileCache;
        }

        $this->envFileCache = [];
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return $this->envFileCache;
        }

        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $this->envFileCache;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            $v = trim($v, "\"'");
            if ($k !== '') {
                $this->envFileCache[$k] = $v;
            }
        }

        return $this->envFileCache;
    }

  public function emitirPorXml(string $xml, int $tpAmb, string $referencia = ''): array
{
    Log::channel('nfce')->info('[NuvemFiscal] Iniciando emissão NFC-e', [
        'tpAmb'      => $tpAmb,
        'ambiente'   => $tpAmb === 2 ? 'homologacao' : 'producao',
        'referencia' => $referencia,
    ]);

    $token = $this->resolveAccessToken();

    if ($token === '') {
        $erro = $this->lastAuthError !== ''
            ? $this->lastAuthError
            : 'Configure NUVEMFISCAL_TOKEN ou NUVEM_FISCAL_CLIENT_ID/NUVEM_FISCAL_CLIENT_SECRET no .env';

        Log::channel('nfce')->error('[NuvemFiscal] Falha ao obter token OAuth', [
            'erro' => $erro,
        ]);

        return [
            'ok'      => false,
            'message' => $erro,
        ];
    }

    Log::channel('nfce')->info('[NuvemFiscal] Token OAuth obtido com sucesso');

    $infNFe = $this->extractInfNFe($xml);

    if (empty($infNFe)) {
        Log::channel('nfce')->error('[NuvemFiscal] Falha ao extrair infNFe do XML');

        return [
            'ok'      => false,
            'message' => 'Nao foi possivel extrair infNFe do XML para envio a Nuvem Fiscal.',
        ];
    }

    /*
     * Correção obrigatória para Nuvem Fiscal:
     * A tag det precisa ser sempre ARRAY.
     * Quando existe apenas 1 item, a conversão do XML pode gerar objeto simples.
     */
    if (isset($infNFe['det']) && is_array($infNFe['det'])) {
        if (isset($infNFe['det']['prod']) || isset($infNFe['det']['imposto'])) {
            $infNFe['det'] = [$infNFe['det']];
        }
    }

    /*
     * Também garante que detPag seja array, caso a API exija lista.
     */
    if (
        isset($infNFe['pag']['detPag']) &&
        is_array($infNFe['pag']['detPag']) &&
        (isset($infNFe['pag']['detPag']['tPag']) || isset($infNFe['pag']['detPag']['vPag']))
    ) {
        $infNFe['pag']['detPag'] = [$infNFe['pag']['detPag']];
    }

    $payload = [
        'infNFe'   => $infNFe,
        'ambiente' => ((int) $tpAmb === 2) ? 'homologacao' : 'producao',
    ];

    if ($referencia !== '') {
        $payload['referencia'] = substr($referencia, 0, 50);
    }

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    Log::channel('nfce')->info('[NuvemFiscal] Enviando NFC-e', [
        'url'          => $this->baseUrl . '/nfce',
        'ambiente'     => $payload['ambiente'],
        'json_preview' => substr($jsonPayload, 0, 2000),
    ]);

    $response = Http::withToken($token)
        ->acceptJson()
        ->timeout($this->timeout)
        ->post($this->baseUrl . '/nfce', $payload);

    Log::channel('nfce')->info('[NuvemFiscal] Resposta emissão', [
        'status' => $response->status(),
        'body'   => substr($response->body(), 0, 1000),
    ]);

    if (!$response->ok()) {
        return [
            'ok'      => false,
            'message' => 'Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
        ];
    }

    $data = $response->json();

    if (!is_array($data)) {
        return [
            'ok'      => false,
            'message' => 'Resposta invalida da Nuvem Fiscal.',
        ];
    }

    $status = strtolower((string) ($data['status'] ?? ''));
    $codigoStatus = (int) ($data['autorizacao']['codigo_status'] ?? 0);

    $autorizada = in_array($status, ['autorizado', 'aprovado', 'concluido'], true)
        || $codigoStatus === 100;

    Log::channel('nfce')->info('[NuvemFiscal] NFC-e processada', [
        'autorizada' => $autorizada,
        'status'     => $data['status'] ?? null,
        'cod_status' => $codigoStatus,
        'chave'      => $data['chave'] ?? null,
        'protocolo'  => $data['autorizacao']['numero_protocolo'] ?? null,
    ]);

    return [
        'ok'         => true,
        'autorizada' => $autorizada,
        'id'         => $data['id'] ?? null,
        'chave'      => $data['chave'] ?? null,
        'protocolo'  => $data['autorizacao']['numero_protocolo'] ?? null,
        'status'     => $data['status'] ?? null,
        'raw'        => $data,
    ];
}

    public function consultarPorChave(string $chave, int $tpAmb, string $cpfCnpj = ''): array
    {
        $token = $this->resolveAccessToken();
        if ($token === '') {
            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        $chave = trim($chave);
        if ($chave === '') {
            return [
                'ok' => false,
                'message' => 'Chave da NFC-e nao informada para consulta.',
            ];
        }

        $cpfCnpj = preg_replace('/\D+/', '', (string) $cpfCnpj);
        if ($cpfCnpj === '') {
            $cpfCnpj = preg_replace('/\D+/', '', (string) $this->readEnv('NUVEM_FISCAL_CNPJ'));
        }
        if ($cpfCnpj === '') {
            return [
                'ok' => false,
                'message' => 'Configure NUVEM_FISCAL_CNPJ no .env para consultar status na Nuvem Fiscal.',
            ];
        }

        $ambiente = ((int) $tpAmb === 2) ? 'homologacao' : 'producao';
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($this->timeout)
            ->get($this->baseUrl . '/nfce', [
                '$top' => 1,
                'cpf_cnpj' => $cpfCnpj,
                'ambiente' => $ambiente,
                'chave' => $chave,
            ]);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Consulta Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        $data = $response->json();
        $item = (is_array($data) && isset($data['data'][0]) && is_array($data['data'][0])) ? $data['data'][0] : null;
        if ($item === null) {
            return [
                'ok' => true,
                'found' => false,
                'status' => 'nao_encontrada',
                'message' => 'NFC-e ainda nao encontrada na Nuvem Fiscal/SEFAZ.',
            ];
        }

        $status = strtolower((string) ($item['status'] ?? ''));
        $codigoStatus = (int) ($item['autorizacao']['codigo_status'] ?? 0);
        $autorizada = in_array($status, ['autorizado', 'aprovado', 'concluido'], true) || $codigoStatus === 100;
        $id = (string) ($item['id'] ?? '');

        $xmlProcessado = '';
        if ($autorizada && $id !== '') {
            $xmlResponse = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/nfce/' . $id . '/xml');

            if ($xmlResponse->ok()) {
                $xmlProcessado = (string) $xmlResponse->body();
            }
        }

        return [
            'ok' => true,
            'found' => true,
            'autorizada' => $autorizada,
            'status' => $item['status'] ?? null,
            'id' => $id !== '' ? $id : null,
            'chave' => $item['chave'] ?? $chave,
            'protocolo' => $item['autorizacao']['numero_protocolo'] ?? null,
            'xml' => $xmlProcessado,
            'raw' => $item,
        ];
    }

    public function cancelarPorChave(string $chave, int $tpAmb, string $justificativa, string $cpfCnpj = ''): array
    {
        $consulta = $this->consultarPorChave($chave, $tpAmb, $cpfCnpj);
        if (empty($consulta['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($consulta['message'] ?? 'Falha ao consultar NFC-e para cancelamento.'),
                'raw' => $consulta,
            ];
        }

        if (empty($consulta['found'])) {
            return [
                'ok' => false,
                'message' => 'NFC-e nao encontrada na Nuvem Fiscal para cancelamento.',
                'raw' => $consulta,
            ];
        }

        $statusAtual = strtolower((string) ($consulta['status'] ?? ''));
        if (in_array($statusAtual, ['cancelado', 'cancelada'], true)) {
            return [
                'ok' => true,
                'cancelada' => true,
                'pendente' => false,
                'status' => 'cancelado',
                'codigo_status' => 135,
                'motivo_status' => 'Evento de cancelamento homologado.',
                'raw' => $consulta['raw'] ?? [],
            ];
        }

        $token = $this->resolveAccessToken();
        if ($token === '') {
            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        $id = (string) ($consulta['id'] ?? '');
        if ($id === '') {
            return [
                'ok' => false,
                'message' => 'ID da NFC-e nao localizado para cancelamento.',
                'raw' => $consulta,
            ];
        }

        $payload = [
            'justificativa' => trim($justificativa) !== '' ? trim($justificativa) : 'Cancelamento solicitado pelo emitente.',
        ];

        Log::channel('nfce')->info('[NuvemFiscal] Solicitando cancelamento NFC-e', [
            'id' => $id,
            'chave' => $chave,
            'ambiente' => ((int) $tpAmb === 2) ? 'homologacao' : 'producao',
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/nfce/' . $id . '/cancelamento', $payload);

        Log::channel('nfce')->info('[NuvemFiscal] Resposta cancelamento NFC-e', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Cancelamento Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        $data = $response->json();
        if (!is_array($data)) {
            $data = [];
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $codigoStatus = (int) ($data['codigo_status'] ?? $data['autorizacao']['codigo_status'] ?? 0);
        $motivoStatus = (string) ($data['motivo_status'] ?? $data['autorizacao']['motivo_status'] ?? '');
        $mensagem = (string) ($data['mensagem'] ?? $data['autorizacao']['mensagem'] ?? '');
        $protocolo = (string) ($data['numero_protocolo'] ?? $data['autorizacao']['numero_protocolo'] ?? '');

        $cancelada = in_array($status, ['cancelado', 'cancelada', 'concluido', 'autorizado'], true)
            || in_array($codigoStatus, [135, 136, 155], true);
        $pendente = !$cancelada && in_array($status, ['pendente', 'processando', 'registrado'], true);

        return [
            'ok' => true,
            'cancelada' => $cancelada,
            'pendente' => $pendente,
            'status' => $status !== '' ? $status : null,
            'codigo_status' => $codigoStatus > 0 ? $codigoStatus : null,
            'motivo_status' => $motivoStatus !== '' ? $motivoStatus : null,
            'mensagem' => $mensagem !== '' ? $mensagem : null,
            'protocolo' => $protocolo !== '' ? $protocolo : null,
            'id' => $id,
            'raw' => $data,
        ];
    }

    public function inutilizarNumeracao(int $tpAmb, int $serie, int $numeroInicial, int $numeroFinal, string $justificativa, string $cpfCnpj = '', ?int $ano = null): array
    {
        $token = $this->resolveAccessToken();
        if ($token === '') {
            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        $justificativa = trim($justificativa);
        if (strlen($justificativa) < 15) {
            return [
                'ok' => false,
                'message' => 'Justificativa deve ter pelo menos 15 caracteres.',
            ];
        }

        $cpfCnpj = preg_replace('/\D+/', '', (string) $cpfCnpj);
        if ($cpfCnpj === '') {
            $cpfCnpj = preg_replace('/\D+/', '', (string) $this->readEnv('NUVEM_FISCAL_CNPJ'));
        }

        if ($cpfCnpj === '') {
            return [
                'ok' => false,
                'message' => 'CNPJ do emitente não encontrado para inutilização NFC-e.',
            ];
        }

        $payload = [
            'ambiente' => ((int) $tpAmb === 2) ? 'homologacao' : 'producao',
            'cnpj' => $cpfCnpj,
            'ano' => $ano ?: (int) date('Y'),
            'serie' => $serie,
            'numero_inicial' => $numeroInicial,
            'numero_final' => $numeroFinal,
            'justificativa' => $justificativa,
        ];

        $configuredEndpoint = trim((string) $this->readEnv('NUVEM_FISCAL_NFCE_INUTILIZACAO_ENDPOINT'));
        $endpoints = $configuredEndpoint !== ''
            ? [$configuredEndpoint]
            : ['/nfce/inutilizacoes', '/nfce/inutilizacao', '/nfce/inutilizar'];

        $response = null;
        $endpointUsed = '';
        foreach ($endpoints as $endpoint) {
            if (strpos($endpoint, '/') !== 0) {
                $endpoint = '/' . $endpoint;
            }

            Log::channel('nfce')->info('[NuvemFiscal] Solicitando inutilizacao NFC-e', [
                'url' => $this->baseUrl . $endpoint,
                'payload' => $payload,
            ]);

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl . $endpoint, $payload);

            $endpointUsed = $endpoint;

            Log::channel('nfce')->info('[NuvemFiscal] Resposta inutilizacao NFC-e', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000),
            ]);

            if (!in_array($response->status(), [404, 405], true) || $configuredEndpoint !== '') {
                break;
            }
        }

        if ($response === null || !$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Inutilizacao Nuvem Fiscal HTTP '
                    . ($response ? $response->status() : 'sem resposta')
                    . ' em ' . $endpointUsed . ' - '
                    . ($response ? $response->body() : ''),
            ];
        }

        $data = $response->json();
        if (!is_array($data)) {
            $data = [];
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $codigoStatus = (int) (
            $data['codigo_status']
            ?? $data['autorizacao']['codigo_status']
            ?? $data['inutilizacao']['codigo_status']
            ?? $data['retorno']['codigo_status']
            ?? 0
        );
        $motivoStatus = (string) (
            $data['motivo_status']
            ?? $data['autorizacao']['motivo_status']
            ?? $data['inutilizacao']['motivo_status']
            ?? $data['retorno']['motivo_status']
            ?? $data['mensagem']
            ?? ''
        );
        $protocolo = (string) (
            $data['numero_protocolo']
            ?? $data['autorizacao']['numero_protocolo']
            ?? $data['inutilizacao']['numero_protocolo']
            ?? $data['retorno']['numero_protocolo']
            ?? ''
        );

        $inutilizada = in_array($status, ['autorizado', 'aprovado', 'concluido', 'inutilizado', 'inutilizada'], true)
            || $codigoStatus === 102;

        return [
            'ok' => true,
            'inutilizada' => $inutilizada,
            'status' => $status !== '' ? $status : null,
            'codigo_status' => $codigoStatus > 0 ? $codigoStatus : null,
            'motivo_status' => $motivoStatus !== '' ? $motivoStatus : null,
            'protocolo' => $protocolo !== '' ? $protocolo : null,
            'raw' => $data,
        ];
    }

    private function resolveAccessToken(): string
    {
        $this->lastAuthError = '';

        if ($this->token !== '') {
            Log::channel('nfce')->info('[NuvemFiscal] Usando token fixo do .env');
            return $this->token;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            $this->lastAuthError = 'Credenciais OAuth ausentes no .env (NUVEM_FISCAL_CLIENT_ID / NUVEM_FISCAL_CLIENT_SECRET).';
            Log::channel('nfce')->error('[NuvemFiscal] ' . $this->lastAuthError);
            return '';
        }

        Log::channel('nfce')->info('[NuvemFiscal] Solicitando token OAuth', [
            'url'       => $this->tokenUrl,
            'client_id' => substr($this->clientId, 0, 6) . '***',
            'scope'     => 'nfce',
            'metodo'    => 'body (client_credentials)',
        ]);

        // Tentativa 1: client_id/client_secret no body com scope (padrão Nuvem Fiscal)
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->tokenUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'nfce',
                ]);

            Log::channel('nfce')->info('[NuvemFiscal] Resposta OAuth tentativa 1', [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);
        } catch (\Throwable $e) {
            $this->lastAuthError = 'Falha de conexão no OAuth da Nuvem Fiscal: ' . $e->getMessage();
            Log::channel('nfce')->error('[NuvemFiscal] Exceção OAuth tentativa 1', ['erro' => $e->getMessage()]);
            return '';
        }

        if ($response->ok()) {
            $data = $response->json();
            if (is_array($data)) {
                $token = trim((string) ($data['access_token'] ?? ''));
                if ($token !== '') {
                    Log::channel('nfce')->info('[NuvemFiscal] Token obtido na tentativa 1');
                    return $token;
                }
            }
        }

        // Tentativa 2: HTTP Basic Auth com scope
        Log::channel('nfce')->info('[NuvemFiscal] Tentativa 2 OAuth (Basic Auth)');

        try {
            $response2 = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'nfce',
                ]);

            Log::channel('nfce')->info('[NuvemFiscal] Resposta OAuth tentativa 2', [
                'http_status' => $response2->status(),
                'body'        => $response2->body(),
            ]);
        } catch (\Throwable $e) {
            $this->lastAuthError = 'Falha de conexão no OAuth da Nuvem Fiscal: ' . $e->getMessage();
            Log::channel('nfce')->error('[NuvemFiscal] Exceção OAuth tentativa 2', ['erro' => $e->getMessage()]);
            return '';
        }

        if ($response2->ok()) {
            $data2 = $response2->json();
            if (is_array($data2)) {
                $token2 = trim((string) ($data2['access_token'] ?? ''));
                if ($token2 !== '') {
                    Log::channel('nfce')->info('[NuvemFiscal] Token obtido na tentativa 2');
                    return $token2;
                }
            }
        }

        $this->lastAuthError = 'OAuth Nuvem Fiscal falhou. HTTP '
            . $response2->status() . ' - ' . $response2->body();

        Log::channel('nfce')->error('[NuvemFiscal] OAuth falhou em todas as tentativas', [
            'client_id_usado' => $this->clientId,
            'token_url'       => $this->tokenUrl,
            'ultimo_status'   => $response2->status(),
            'ultimo_body'     => $response2->body(),
            'SOLUCAO'         => 'Acesse app.nuvemfiscal.com.br > Configuracoes > API e atualize NUVEM_FISCAL_CLIENT_ID e NUVEM_FISCAL_CLIENT_SECRET no .env',
        ]);

        return '';
    }

    private function extractInfNFe(string $xml): array
    {
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$sx) {
            return [];
        }

        $infNodes = $sx->xpath('//*[local-name()="infNFe"]');
        if (!$infNodes || !isset($infNodes[0])) {
            return [];
        }

        return $this->xmlToArray($infNodes[0]);
    }

    // Campos inteiros conforme schema oficial Nuvem Fiscal (campos mostrados como número inteiro no JSON)
    // ATENÇÃO: cNF, cMunFG, CFOP, CST, CSOSN, tPag são STRINGS no schema — não incluir aqui
    private const INT_FIELDS = [
        // ide
        'cUF', 'mod', 'serie', 'nNF', 'tpNF', 'idDest',
        'tpImp', 'tpEmis', 'cDV', 'tpAmb', 'finNFe',
        'indFinal', 'indPres', 'indIntermed', 'procEmi',
        // ide.gCompraGov
        'tpEnteGov', 'tpOperGov',
        // ide.NFref.refECF
        'nECF', 'nCOO',
        // emit
        'CRT',
        // dest
        'indIEDest',
        // det
        'nItem', 'indTot', 'indBemMovelUsado', 'nItemPed',
        // det.imposto.ICMS
        'orig', 'modBC', 'modBCST',
        'motDesICMS', 'indDeduzDeson', 'motDesICMSST', 'motRedAdRem',
        // det.imposto.IPI
        'qSelo',
        // det.imposto.ISSQN
        'indISS', 'indIncentivo',
        // det.imposto.PISST / COFINSST
        'indSomaPISST', 'indSomaCOFINSST',
        // det.imposto.IBSCBS
        'indDoacao', 'tpCredPresIBSZFM',
        // det.prod.DI
        'tpViaTransp', 'tpIntermedio', 'nAdicao', 'nSeqAdic',
        // det.prod.veicProd
        'tpOp', 'anoMod', 'anoFab', 'tpVeic', 'espVeic', 'condVeic', 'lota', 'tpRest',
        // det.prod.arma
        'tpArma',
        // det.prod.comb
        'cProdANP', 'nBico', 'nBomba', 'nTanque', 'indImport', 'cUFOrig',
        // transp
        'modFrete', 'qVol',
        // pag
        'indPag', 'tpIntegra',
        // total
        'cRegTrib',
        // infAdic
        'indProc',
        // cana
        'dia',
        // infRespTec
        'idCSRT',
        // agropecuario
        'tpGuia',
    ];

    // Campos float explícitos que não seguem o padrão v*/p*/q* abaixo
    private const FLOAT_EXTRA = [
        'adRemICMS', 'adRemICMSReten', 'adRemICMSDif', 'adRemICMSRet',
        'adRemCBS', 'adRemCBSReten', 'adRemCBSRet',
        'adRemIBS', 'adRemIBSReten', 'adRemIBSRet',
        'pesoL', 'pesoB',
    ];

    private function castByName(string $name, string $value)
    {
        if ($value === '') {
            return $value;
        }

        // Inteiros por lista explícita (verificado ANTES dos padrões)
        if (in_array($name, self::INT_FIELDS, true)) {
            return (int) $value;
        }

        // Padrão: campos v*, p*, q* com segunda letra maiúscula = decimal (monetário/percentual/quantidade)
        // Exemplos: vProd, vBC, pICMS, pRedBC, qCom, qTrib, qBCProd
        if (
            strlen($name) > 1
            && ctype_upper($name[1])
            && ($name[0] === 'v' || $name[0] === 'p' || $name[0] === 'q')
            && is_numeric($value)
        ) {
            return (float) $value;
        }

        // Float por lista explícita para casos que não seguem o padrão acima
        if (in_array($name, self::FLOAT_EXTRA, true) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function xmlToArray(\SimpleXMLElement $node)
    {
        $result = [];

        // Atributos XML: aplica tipagem para campos conhecidos (ex: det@nItem inteiro)
        foreach ($node->attributes() as $attrName => $attrValue) {
            $attrName = (string) $attrName;
            $result[$attrName] = $this->castByName($attrName, trim((string) $attrValue));
        }

        $children = $node->children();
        if (count($children) === 0) {
            $text  = trim((string) $node);
            $name  = $node->getName();
            $typed = $this->castByName($name, $text);
            if (!empty($result)) {
                if ($text !== '') {
                    $result['value'] = $typed;
                }
                return $result;
            }
            return $typed;
        }

        foreach ($children as $child) {
            $name  = $child->getName();
            $value = $this->xmlToArray($child);

            if (array_key_exists($name, $result)) {
                if (!is_array($result[$name]) || !array_key_exists(0, $result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
