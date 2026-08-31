<?php

namespace App\Services;

use App\Models\ConfigNota;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegraNotasService
{
	private $baseUrl;
	private $token;
	private $timeout;
	private $cnpj;
	private $tokenOrigem;

	public function __construct()
	{
		$config = ConfigNota::first();
		$tokenTabela = '';
		$this->cnpj = '';
		$this->tokenOrigem = 'nao_configurado';

		if ($config) {
			$tokenTabela = trim((string) ($config->token_nfe ?? $config->tokennfe ?? ''));
			$this->cnpj = preg_replace('/\D+/', '', (string) ($config->cnpj ?? ''));
		}

		$this->baseUrl = rtrim((string) (env('INTEGRA_NOTAS_BASE_URL') ?: env('INTEGRANOTAS_BASE_URL') ?: env('CLOUD_DFE_BASE_URL') ?: 'https://api.integranotas.com.br/v1'), '/');
		if ($tokenTabela !== '') {
			$this->token = $tokenTabela;
			$this->tokenOrigem = 'config_notas.token_nfe';
		} else {
			$this->token = trim((string) (env('INTEGRA_NOTAS_TOKEN') ?: env('INTEGRANOTAS_TOKEN') ?: env('CLOUD_DFE_TOKEN')));
			$this->tokenOrigem = $this->token !== '' ? 'env' : 'nao_configurado';
		}
		$this->timeout = (int) (env('INTEGRA_NOTAS_TIMEOUT') ?: env('INTEGRANOTAS_TIMEOUT') ?: 60);

		if ($this->timeout < 10) {
			$this->timeout = 60;
		}
	}

	public function buscarNotasEntrada($ultimoNsu = 0, $cnpj = null, $dataInicio = null, $dataFim = null): array
	{
		$cnpj = preg_replace('/\D+/', '', (string) ($cnpj ?: $this->cnpj));
		$periodos = $this->montarPeriodos($dataInicio, $dataFim);

		Log::info('[IntegraNotas] Buscando notas de entrada', [
			'base_url' => $this->baseUrl,
			'cnpj' => $this->mascararCnpj($cnpj),
			'token_origem' => $this->tokenOrigem,
			'token' => $this->mascararToken($this->token),
			'token_acesso' => $this->token,
			'ultimo_nsu' => (int) $ultimoNsu,
			'periodos' => $periodos,
		]);

		if ($this->token === '') {
			Log::warning('[IntegraNotas] Token nao configurado');

			return [
				'ok' => false,
				'message' => 'Configure o token_nfe na configuracao fiscal.',
			];
		}

		$documentos = [];
		$raw = [];
		$ultimoNsuRetorno = (string) $ultimoNsu;

		foreach ($periodos as $periodo) {
			$query = [
				'periodo' => $periodo,
			];

			try {
				$response = Http::withHeaders([
						'Authorization' => $this->token,
					])
					->acceptJson()
					->timeout($this->timeout)
					->post($this->baseUrl . '/dfe/nfe', $query);
			} catch (\Throwable $e) {
				Log::error('[IntegraNotas] Falha de conexao', [
					'cnpj' => $this->mascararCnpj($cnpj),
					'periodo' => $periodo,
					'erro' => $e->getMessage(),
				]);

				return [
					'ok' => false,
					'message' => 'Falha de conexao com a IntegraNotas: ' . $e->getMessage(),
				];
			}

			$data = $response->json();
			$raw[$periodo] = is_array($data) ? $data : ['body' => $response->body()];

			Log::info('[IntegraNotas] Resposta da API', [
				'status' => $response->status(),
				'cnpj' => $this->mascararCnpj($cnpj),
				'periodo' => $periodo,
				'body' => substr((string) $response->body(), 0, 1000),
			]);

			if (!$response->ok()) {
				return [
					'ok' => false,
					'message' => $this->extrairMensagem($data, 'IntegraNotas HTTP ' . $response->status()),
					'raw' => $raw,
				];
			}

			if (is_array($data) && array_key_exists('sucesso', $data) && empty($data['sucesso'])) {
				return [
					'ok' => false,
					'message' => $this->extrairMensagem($data, 'Falha ao buscar notas na IntegraNotas.'),
					'raw' => $raw,
				];
			}

			$documentos = array_merge($documentos, $this->extrairDocumentos(is_array($data) ? $data : []));
			$ultimoNsuRetorno = (string) ($data['ultimo_nsu'] ?? $data['ultNSU'] ?? $ultimoNsuRetorno);
		}

		return [
			'ok' => true,
			'documentos' => $documentos,
			'ultimo_nsu' => $ultimoNsuRetorno,
			'raw' => $raw,
		];
	}

	private function mascararCnpj($cnpj): string
	{
		$cnpj = preg_replace('/\D+/', '', (string) $cnpj);
		if (strlen($cnpj) < 8) {
			return $cnpj;
		}

		return substr($cnpj, 0, 4) . str_repeat('*', max(strlen($cnpj) - 8, 0)) . substr($cnpj, -4);
	}

	private function mascararToken($token): string
	{
		$token = (string) $token;
		if ($token === '') {
			return '';
		}

		if (strlen($token) <= 12) {
			return substr($token, 0, 3) . '***';
		}

		return substr($token, 0, 6) . '***' . substr($token, -4);
	}

	private function extrairDocumentos(array $data): array
	{
		foreach (['docs', 'documentos', 'notas', 'data', 'lista'] as $campo) {
			if (isset($data[$campo]) && is_array($data[$campo])) {
				return $data[$campo];
			}
		}

		return [];
	}

	private function extrairMensagem($data, $padrao): string
	{
		if (is_array($data)) {
			foreach (['mensagem', 'message', 'erro', 'motivo'] as $campo) {
				if (!empty($data[$campo])) {
					return (string) $data[$campo];
				}
			}
		}

		return $padrao;
	}

	private function montarPeriodos($dataInicio = null, $dataFim = null): array
	{
		try {
			$inicio = $dataInicio ? \Carbon\Carbon::parse($dataInicio)->startOfMonth() : \Carbon\Carbon::now()->startOfMonth();
			$fim = $dataFim ? \Carbon\Carbon::parse($dataFim)->startOfMonth() : $inicio->copy();
		} catch (\Exception $e) {
			$inicio = \Carbon\Carbon::now()->startOfMonth();
			$fim = $inicio->copy();
		}

		if ($fim->lt($inicio)) {
			$fim = $inicio->copy();
		}

		$periodos = [];
		while ($inicio->lte($fim)) {
			$periodos[] = $inicio->format('Y-m');
			$inicio->addMonth();
		}

		return $periodos;
	}
}
