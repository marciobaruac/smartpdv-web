<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NuvemFiscalDistribuicaoNfeService
{
    private $baseUrl;
    private $tokenUrl;
    private $token;
    private $clientId;
    private $clientSecret;
    private $timeout;
    private $envFileCache = null;
    private $lastAuthError = '';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (
            $this->readEnv('NUVEM_FISCAL_API_URL')
            ?: $this->readEnv('NUVEMFISCAL_BASE_URL')
            ?: $this->readEnv('NUVEM_FISCAL_BASE_URL')
        ), '/');

        if ($this->baseUrl === '') {
            $this->baseUrl = 'https://api.nuvemfiscal.com.br';
        }

        $authUrl = rtrim((string) (
            $this->readEnv('NUVEM_FISCAL_AUTH_URL')
            ?: $this->readEnv('NUVEM_FISCAL_TOKEN_URL')
            ?: $this->readEnv('NUVEMFISCAL_TOKEN_URL')
        ), '/');

        if ($authUrl === '') {
            $authUrl = 'https://auth.nuvemfiscal.com.br';
        }

        $this->tokenUrl = substr($authUrl, -12) === '/oauth/token'
            ? $authUrl
            : $authUrl . '/oauth/token';

        $this->token = trim((string) (
            $this->readEnv('NUVEM_FISCAL_TOKEN')
            ?: $this->readEnv('NUVEMFISCAL_TOKEN')
        ));

        $this->clientId = trim((string) (
            $this->readEnv('NUVEM_FISCAL_CLIENT_ID')
            ?: $this->readEnv('NUVEMFISCAL_CLIENT_ID')
        ));

        $this->clientSecret = trim((string) (
            $this->readEnv('NUVEM_FISCAL_CLIENT_SECRET')
            ?: $this->readEnv('NUVEMFISCAL_CLIENT_SECRET')
        ));

        $this->timeout = (int) (
            $this->readEnv('NUVEMFISCAL_TIMEOUT')
            ?: $this->readEnv('NUVEM_FISCAL_TIMEOUT')
            ?: 45
        );

        if ($this->timeout < 10) {
            $this->timeout = 10;
        }
    }

    public function distribuir(string $cpfCnpj, string $ambiente, int $ultimoNsu = 0): array
    {
        $this->logInfo('Iniciando distribuicao', [
            'cpf_cnpj' => $this->maskCpfCnpj($cpfCnpj),
            'ambiente' => $this->normalizarAmbiente($ambiente),
            'dist_nsu' => $ultimoNsu,
        ]);

        $token = $this->resolveAccessToken();
        if ($token === '') {
            $this->logError('Falha token distribuicao', [
                'erro' => $this->lastAuthError,
            ]);

            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        $payload = [
            'cpf_cnpj' => preg_replace('/\D+/', '', $cpfCnpj),
            'ambiente' => $this->normalizarAmbiente($ambiente),
            'tipo_consulta' => 'dist-nsu',
            'dist_nsu' => $ultimoNsu,
            'ignorar_tempo_espera' => false,
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/distribuicao/nfe', $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Falha de conexao com a Nuvem Fiscal: ' . $e->getMessage(),
            ];
        }

        Log::info('[NuvemFiscal Distribuicao NF-e] Distribuir documentos', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);
        $this->logInfo('Resposta distribuicao', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 2000),
        ]);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        $data = $response->json();
        return [
            'ok' => true,
            'raw' => is_array($data) ? $data : [],
        ];
    }

    public function configurarEmpresa(string $cpfCnpj, string $ambiente): array
    {
        $this->logInfo('Iniciando configuracao distnfe', [
            'cpf_cnpj' => $this->maskCpfCnpj($cpfCnpj),
            'ambiente' => $this->normalizarAmbiente($ambiente),
        ]);

        $token = $this->resolveAccessToken();
        if ($token === '') {
            $this->logError('Falha token configuracao distnfe', [
                'erro' => $this->lastAuthError,
            ]);

            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        $cpfCnpj = preg_replace('/\D+/', '', $cpfCnpj);
        $payload = [
            'distribuicao_automatica' => false,
            'distribuicao_intervalo_horas' => 24,
            'ciencia_automatica' => false,
            'ambiente' => $this->normalizarAmbiente($ambiente),
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->put($this->baseUrl . '/empresas/' . $cpfCnpj . '/distnfe', $payload);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Falha de conexao com a Nuvem Fiscal: ' . $e->getMessage(),
            ];
        }

        Log::info('[NuvemFiscal Distribuicao NF-e] Configurar empresa', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);
        $this->logInfo('Resposta configuracao distnfe', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 2000),
        ]);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        return [
            'ok' => true,
            'raw' => is_array($response->json()) ? $response->json() : [],
        ];
    }

    public function listarDocumentos(string $cpfCnpj, string $ambiente, int $top = 100): array
    {
        $this->logInfo('Iniciando listagem documentos', [
            'cpf_cnpj' => $this->maskCpfCnpj($cpfCnpj),
            'ambiente' => $this->normalizarAmbiente($ambiente),
            'top' => $top,
        ]);

        $token = $this->resolveAccessToken();
        if ($token === '') {
            $this->logError('Falha token listagem documentos', [
                'erro' => $this->lastAuthError,
            ]);

            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/distribuicao/nfe/documentos', [
                    '$top' => min(max($top, 1), 100),
                    '$skip' => 0,
                    'cpf_cnpj' => preg_replace('/\D+/', '', $cpfCnpj),
                    'ambiente' => $this->normalizarAmbiente($ambiente),
                    'tipo_documento' => 'nota',
                ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Falha de conexao com a Nuvem Fiscal: ' . $e->getMessage(),
            ];
        }

        Log::info('[NuvemFiscal Distribuicao NF-e] Listar documentos', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);
        $this->logInfo('Resposta listagem documentos', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 2000),
        ]);

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        $data = $response->json();
        $documentos = is_array($data) && isset($data['data']) && is_array($data['data'])
            ? $data['data']
            : [];

        return [
            'ok' => true,
            'documentos' => $documentos,
            'raw' => is_array($data) ? $data : [],
        ];
    }

    public function baixarXmlDocumento(string $id): array
    {
        $this->logInfo('Iniciando download XML documento', [
            'documento_id' => $id,
        ]);

        $token = $this->resolveAccessToken();
        if ($token === '') {
            $this->logError('Falha token download XML documento', [
                'erro' => $this->lastAuthError,
            ]);

            return [
                'ok' => false,
                'message' => $this->lastAuthError !== '' ? $this->lastAuthError : 'Falha ao obter token da Nuvem Fiscal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/distribuicao/nfe/documentos/' . $id . '/xml');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Falha de conexao com a Nuvem Fiscal: ' . $e->getMessage(),
            ];
        }

        if (!$response->ok()) {
            $this->logInfo('Resposta download XML documento com erro', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);

            return [
                'ok' => false,
                'message' => 'Nuvem Fiscal HTTP ' . $response->status() . ' - ' . $response->body(),
            ];
        }

        $this->logInfo('Resposta download XML documento OK', [
            'status' => $response->status(),
            'bytes' => strlen((string) $response->body()),
        ]);

        return [
            'ok' => true,
            'xml' => (string) $response->body(),
        ];
    }

    private function normalizarAmbiente(string $ambiente): string
    {
        $ambiente = strtolower(trim($ambiente));
        return $ambiente === 'homologacao' || $ambiente === 'homologação' ? 'homologacao' : 'producao';
    }

    private function resolveAccessToken(): string
    {
        $this->lastAuthError = '';

        if ($this->token !== '') {
            $this->logInfo('Usando token fixo', [
                'token_fixo' => true,
            ]);
            return $this->token;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            $this->lastAuthError = 'Credenciais OAuth ausentes no .env (NUVEM_FISCAL_CLIENT_ID / NUVEM_FISCAL_CLIENT_SECRET).';
            $this->logError('Credenciais OAuth ausentes', [
                'client_id_ok' => $this->clientId !== '',
                'client_secret_ok' => $this->clientSecret !== '',
            ]);
            return '';
        }

        $this->logInfo('Solicitando OAuth Basic', [
            'token_url' => $this->tokenUrl,
            'client_id' => substr($this->clientId, 0, 6) . '***',
            'scope' => 'distribuicao-nfe',
        ]);

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'distribuicao-nfe',
                ]);
        } catch (\Throwable $e) {
            $this->lastAuthError = 'Falha de conexao no OAuth da Nuvem Fiscal: ' . $e->getMessage();
            $this->logError('Excecao OAuth Basic', [
                'erro' => $e->getMessage(),
            ]);
            return '';
        }

        $this->logInfo('Resposta OAuth Basic', [
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);

        if ($response->ok()) {
            $data = $response->json();
            if (is_array($data)) {
                $token = trim((string) ($data['access_token'] ?? ''));
                if ($token !== '') {
                    return $token;
                }
            }
        }

        $this->logInfo('Solicitando OAuth body', [
            'token_url' => $this->tokenUrl,
            'client_id' => substr($this->clientId, 0, 6) . '***',
            'scope' => 'distribuicao-nfe',
        ]);

        try {
            $response2 = Http::asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'distribuicao-nfe',
                ]);
        } catch (\Throwable $e) {
            $this->lastAuthError = 'Falha de conexao no OAuth da Nuvem Fiscal: ' . $e->getMessage();
            $this->logError('Excecao OAuth body', [
                'erro' => $e->getMessage(),
            ]);
            return '';
        }

        $this->logInfo('Resposta OAuth body', [
            'status' => $response2->status(),
            'body' => substr($response2->body(), 0, 1000),
        ]);

        if ($response2->ok()) {
            $data2 = $response2->json();
            if (is_array($data2)) {
                $token2 = trim((string) ($data2['access_token'] ?? ''));
                if ($token2 !== '') {
                    return $token2;
                }
            }
        }

        $this->lastAuthError = 'OAuth Nuvem Fiscal falhou. HTTP ' . $response2->status() . ' - ' . $response2->body();
        $this->logError('OAuth falhou', [
            'erro' => $this->lastAuthError,
        ]);
        return '';
    }

    private function logInfo(string $message, array $context = []): void
    {
        Log::info('[NuvemFiscal Distribuicao NF-e] ' . $message, $context);
    }

    private function logError(string $message, array $context = []): void
    {
        Log::error('[NuvemFiscal Distribuicao NF-e] ' . $message, $context);
    }

    private function maskCpfCnpj(string $cpfCnpj): string
    {
        $value = preg_replace('/\D+/', '', $cpfCnpj);
        if (strlen($value) <= 4) {
            return $value;
        }

        return substr($value, 0, 4) . str_repeat('*', max(strlen($value) - 8, 0)) . substr($value, -4);
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

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");
            if ($key !== '') {
                $this->envFileCache[$key] = $value;
            }
        }

        return $this->envFileCache;
    }
}
