<?php

namespace App\Services;

use App\Models\ConfigNota;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
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

	// =====================================================================
	// Emissao / eventos de NF-e (modelo 55) via IntegraNotas
	// A base ($this->baseUrl) ja inclui o sufixo /v1, portanto os endpoints
	// abaixo comecam em /nfe, /dfe, etc.
	// =====================================================================

	/** True quando ha token configurado. */
	public function temToken(): bool
	{
		return trim((string) $this->token) !== '';
	}

	/** Origem do token (config_notas.token_nfe | env | nao_configurado). */
	public function origemToken(): string
	{
		return $this->tokenOrigem;
	}

	/** Pending request HTTP com Authorization + timeout padrao. */
	private function http()
	{
		return Http::withHeaders([
				'Authorization' => $this->token, // sem "Bearer"
				'Accept'        => 'application/json',
			])
			->timeout($this->timeout);
	}

	/** Normaliza a resposta da IntegraNotas num formato unico. */
	private function normalize(array $json, int $http = 200): array
	{
		return [
			'ok'       => (bool) Arr::get($json, 'sucesso', Arr::get($json, 'ok', false)),
			'codigo'   => Arr::get($json, 'codigo', Arr::get($json, 'dados.codigo_status', $http)),
			'mensagem' => Arr::get($json, 'mensagem', Arr::get($json, 'dados.motivo_status', '')),
			'raw'      => $json,
			'http'     => $http,
		];
	}

	private function error(string $msg, string $code = 'HTTP_ERROR', int $http = 500, array $extra = []): array
	{
		return [
			'ok'       => false,
			'codigo'   => $code,
			'mensagem' => $msg,
			'raw'      => $extra,
			'http'     => $http,
		];
	}

	private function exStatus(RequestException $e)
	{
		return $e->response ? $e->response->status() : null;
	}

	private function exJson(RequestException $e)
	{
		if (!$e->response) {
			return null;
		}
		try {
			$j = $e->response->json();
			return $j === null ? (string) $e->response->body() : $j;
		} catch (\Throwable $t) {
			return (string) $e->response->body();
		}
	}

	/** Tenta uma lista de endpoints ate um responder com sucesso. */
	private function requestJsonCandidates(string $method, array $candidates, array $payload = []): array
	{
		$last = null;
		$method = strtoupper($method);

		foreach ($candidates as $path) {
			$url = $this->baseUrl . $path;
			try {
				$req = $this->http()->asJson();
				switch ($method) {
					case 'GET':    $res = $req->get($url); break;
					case 'POST':   $res = $req->post($url, $payload); break;
					case 'PUT':    $res = $req->put($url, $payload); break;
					case 'PATCH':  $res = $req->patch($url, $payload); break;
					case 'DELETE': $res = $req->delete($url, $payload); break;
					default:
						return $this->error("Metodo HTTP nao suportado: {$method}", 'HTTP_METHOD_NOT_SUPPORTED', 500);
				}

				$res->throw();
				$json = $res->json() ?? [];

				Log::info('[IntegraNotas] request candidate OK', [
					'method' => $method,
					'url'    => $url,
					'http'   => $res->status(),
				]);

				return $this->normalize($json, $res->status());
			} catch (RequestException $e) {
				$status = $this->exStatus($e);
				$body = $this->exJson($e);
				$last = ['method' => $method, 'url' => $url, 'status' => $status, 'body' => $body];

				Log::warning('[IntegraNotas] request candidate falhou', [
					'method' => $method,
					'url'    => $url,
					'status' => $status,
					'body'   => $body,
				]);
			} catch (ConnectionException $e) {
				return $this->error('Falha de conexao na IntegraNotas', 'CONNECTION_ERROR', 0, [
					'exception' => $e->getMessage(),
					'url'       => $url,
				]);
			}
		}

		$detalhe = '';
		if (is_array($last)) {
			$detalhe = ' Ultima tentativa: ' . ($last['method'] ?? '') . ' ' . ($last['url'] ?? '') . ' -> HTTP ' . ($last['status'] ?? '') . '.';
		}

		return $this->error(
			'Nenhum endpoint candidato respondeu com sucesso.' . $detalhe,
			'ENDPOINT_NOT_FOUND',
			is_array($last) ? ($last['status'] ?? 404) : 404,
			$last ?? []
		);
	}

	/** GET /nfe/status */
	public function statusNFe(): array
	{
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		try {
			$res = $this->http()->get($this->baseUrl . '/nfe/status')->throw();
			return $this->normalize($res->json() ?? [], $res->status());
		} catch (RequestException $e) {
			$status = $this->exStatus($e);
			return $this->error('Falha ao consultar status NFe', 'HTTP_ERROR', $status ?? 500, ['body' => $this->exJson($e)]);
		} catch (ConnectionException $e) {
			return $this->error('Falha de conexao ao consultar status NFe', 'CONNECTION_ERROR', 0);
		}
	}

	/** GET /nfe/{chave} - consulta situacao da NF-e por chave (44 digitos). */
	public function consultaChaveNFe(string $chave): array
	{
		$chave = preg_replace('/\D+/', '', $chave);
		if (strlen($chave) !== 44) {
			return $this->error('Chave invalida (esperado 44 digitos).', 'INVALID_CHAVE', 422);
		}
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$url = $this->baseUrl . '/nfe/' . $chave;
		try {
			$res = $this->http()->get($url)->throw();
			return $this->normalize($res->json() ?? [], $res->status());
		} catch (RequestException $e) {
			$status = $this->exStatus($e);
			return $this->error('Falha ao consultar NFe (HTTP ' . $status . ')', 'HTTP_ERROR', $status ?? 500, ['body' => $this->exJson($e)]);
		} catch (ConnectionException $e) {
			return $this->error('Falha de conexao ao consultar NFe', 'CONNECTION_ERROR', 0);
		}
	}

	/** POST /nfe - emite (envia) a NF-e. */
	public function enviarNFe(array $payload): array
	{
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		try {
			$res = $this->http()->asJson()->post($this->baseUrl . '/nfe', $payload)->throw();
			$json = $res->json() ?? [];

			Log::info('[IntegraNotas] enviar NFe OK', [
				'http'        => $res->status(),
				'resp_codigo' => $json['codigo'] ?? null,
			]);

			return $this->normalize($json, $res->status());
		} catch (RequestException $e) {
			$status = $this->exStatus($e);
			$body = $this->exJson($e);
			Log::warning('[IntegraNotas] enviar NFe falhou', ['status' => $status, 'body' => $body]);
			return $this->error('Falha ao enviar NFe', 'HTTP_ERROR', $status ?? 500, is_array($body) ? $body : ['raw' => $body]);
		} catch (ConnectionException $e) {
			return $this->error('Falha de conexao ao enviar NFe', 'CONNECTION_ERROR', 0);
		}
	}

	/** POST /nfe/preview - gera DANFE previo (PDF) sem autorizar. */
	public function previewNFe(array $payload): array
	{
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$tries = [
			'/nfe/preview',
			'/nfe/preview/pdf',
			'/nfe/preview/impressao',
			'/nfe/preview/imprimir',
		];

		foreach ($tries as $path) {
			$url = $this->baseUrl . $path;
			try {
				$res = $this->http()->asJson()->post($url, $payload)->throw();

				$contentType = (string) ($res->header('Content-Type') ?? '');
				$bodyRaw = $res->body();

				if (stripos($contentType, 'application/pdf') !== false && $bodyRaw !== '') {
					return ['ok' => true, 'http' => $res->status(), 'pdf' => $bodyRaw, 'url' => $url];
				}
				if ($bodyRaw !== '' && substr($bodyRaw, 0, 4) === '%PDF') {
					return ['ok' => true, 'http' => $res->status(), 'pdf' => $bodyRaw, 'url' => $url];
				}

				$json = $res->json() ?? [];
				if (is_array($json) && array_key_exists('sucesso', $json) && $json['sucesso'] === false) {
					return $this->error((string) ($json['mensagem'] ?? 'Falha no preview da NFe.'), (string) ($json['codigo'] ?? 'PREVIEW_INVALID'), $res->status(), $json);
				}
				if (is_array($json) && !empty($json['pdf'])) {
					$b64 = (string) $json['pdf'];
					if (strpos($b64, ',') !== false) {
						$b64 = explode(',', $b64, 2)[1];
					}
					$pdf = base64_decode($b64, true);
					if ($pdf === false || $pdf === '' || substr($pdf, 0, 4) !== '%PDF') {
						continue;
					}
					return ['ok' => true, 'http' => $res->status(), 'pdf' => $pdf, 'xml' => isset($json['xml']) ? (string) $json['xml'] : null, 'url' => $url, 'raw' => $json];
				}
			} catch (RequestException $e) {
				$status = $this->exStatus($e);
				$body = $this->exJson($e);
				if ($status !== 404) {
					return $this->error(
						is_array($body) ? (string) ($body['mensagem'] ?? 'Falha no preview da NFe.') : 'Falha no preview da NFe.',
						is_array($body) ? (string) ($body['codigo'] ?? 'PREVIEW_HTTP_ERROR') : 'PREVIEW_HTTP_ERROR',
						$status ?? 500,
						is_array($body) ? $body : ['raw' => $body]
					);
				}
			} catch (ConnectionException $e) {
				return $this->error('Falha de conexao ao gerar preview da NFe: ' . $e->getMessage(), 'CONNECTION_ERROR', 0);
			}
		}

		return $this->error('Nao foi possivel gerar o preview do DANFE na IntegraNotas.', 'PREVIEW_NOT_FOUND', 404);
	}

	/** Cancela a NF-e por chave (justificativa minima de 15 caracteres). */
	public function cancelarNFe(string $chave, string $justificativa): array
	{
		$chave = preg_replace('/\D+/', '', $chave);
		if (strlen($chave) !== 44) {
			return $this->error('Chave invalida (esperado 44 digitos).', 'INVALID_CHAVE', 422);
		}
		$justificativa = trim($justificativa);
		if (mb_strlen($justificativa) < 15) {
			return $this->error('Justificativa deve ter no minimo 15 caracteres.', 'INVALID_JUSTIFICATIVA', 422);
		}
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$payloadBase = ['chave' => $chave, 'justificativa' => $justificativa];
		$candidates = [
			['POST', '/nfe/cancela', $payloadBase],
			['POST', "/nfe/cancela/{$chave}", ['justificativa' => $justificativa]],
			['PATCH', '/nfe/cancelar', $payloadBase],
			['PUT', '/nfe/cancelar', $payloadBase],
			['POST', '/nfe/cancelar', $payloadBase],
			['POST', '/nfe/eventos/cancelamento', $payloadBase],
		];

		$last = null;
		foreach ($candidates as $c) {
			[$method, $path, $payload] = $c;
			$r = $this->requestJsonCandidates($method, [$path], $payload);
			if (($r['ok'] ?? false) || (($r['http'] ?? 0) >= 200 && ($r['http'] ?? 0) < 300)) {
				return $r;
			}
			$last = $r;
		}

		return $last ?: $this->error('Falha ao cancelar NFe.', 'CANCEL_ERROR', 500);
	}

	/** Registra Carta de Correcao (CC-e). Correcao minima de 15 caracteres. */
	public function cartaCorrecaoNFe(string $chave, string $correcao, int $sequencia = 1): array
	{
		$chave = preg_replace('/\D+/', '', $chave);
		if (strlen($chave) !== 44) {
			return $this->error('Chave invalida (esperado 44 digitos).', 'INVALID_CHAVE', 422);
		}
		$correcao = trim($correcao);
		if (mb_strlen($correcao) < 15) {
			return $this->error('Correcao deve ter no minimo 15 caracteres.', 'INVALID_CORRECAO', 422);
		}
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$seq = max(1, $sequencia);
		$candidates = [
			['POST', '/nfe/correcao', ['chave' => $chave, 'justificativa' => $correcao, 'sequencia' => $seq]],
			['POST', "/nfe/correcao/{$chave}", ['justificativa' => $correcao, 'sequencia' => $seq]],
			['POST', '/nfe/carta-correcao', ['chave' => $chave, 'correcao' => $correcao, 'sequencia' => $seq]],
			['POST', '/nfe/eventos/carta-correcao', ['chave' => $chave, 'correcao' => $correcao, 'sequencia' => $seq]],
		];

		$last = null;
		foreach ($candidates as $c) {
			[$method, $path, $payload] = $c;
			$r = $this->requestJsonCandidates($method, [$path], $payload);
			if (($r['ok'] ?? false) || (($r['http'] ?? 0) >= 200 && ($r['http'] ?? 0) < 300)) {
				return $r;
			}
			$last = $r;
		}

		return $last ?: $this->error('Falha ao registrar carta de correcao.', 'CCE_ERROR', 500);
	}

	/** Recupera o XML autorizado por chave. */
	public function xmlNFe(string $chave): array
	{
		$chave = preg_replace('/\D+/', '', $chave);
		if (strlen($chave) !== 44) {
			return $this->error('Chave invalida (esperado 44 digitos).', 'INVALID_CHAVE', 422);
		}
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$paths = [
			'/nfe/xml/' . $chave,
			'/nfe/' . $chave . '/xml',
			'/nfe/xml?chave=' . $chave,
		];

		foreach ($paths as $path) {
			$url = $this->baseUrl . $path;
			try {
				$res = $this->http()->withHeaders(['Accept' => 'application/xml, text/xml, application/json'])->get($url)->throw();
				$body = (string) $res->body();

				if ($body !== '' && (strpos($body, '<NFe') !== false || strpos($body, '<nfeProc') !== false)) {
					return ['ok' => true, 'http' => $res->status(), 'xml' => $body, 'url' => $url];
				}

				$json = $res->json() ?? [];
				$xml = Arr::get($json, 'xml')
					?? Arr::get($json, 'dados.xml')
					?? Arr::get($json, 'nfe.xml')
					?? Arr::get($json, 'xml_autorizado')
					?? Arr::get($json, 'xmlProc');

				if (is_string($xml) && $xml !== '') {
					if (strpos($xml, '<') === false && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $xml)) {
						$decoded = base64_decode($xml, true);
						if ($decoded !== false) {
							$xml = $decoded;
						}
					}
					if (strpos($xml, '<NFe') !== false || strpos($xml, '<nfeProc') !== false) {
						return ['ok' => true, 'http' => $res->status(), 'xml' => $xml, 'url' => $url, 'raw' => $json];
					}
				}
			} catch (RequestException $e) {
				Log::warning('[IntegraNotas] xmlNFe falhou', ['url' => $url, 'status' => $this->exStatus($e)]);
			} catch (ConnectionException $e) {
				return $this->error('Falha de conexao ao obter XML', 'CONNECTION_ERROR', 0);
			}
		}

		return $this->error('Nao foi possivel obter XML pela chave.', 'XML_NOT_FOUND', 404);
	}

	/** Recupera o PDF/DANFE por chave (retorna binario em 'pdf'). */
	public function pdfNFe(string $chave): array
	{
		$chave = preg_replace('/\D+/', '', $chave);
		if (strlen($chave) !== 44) {
			return $this->error('Chave invalida (esperado 44 digitos).', 'INVALID_CHAVE', 422);
		}
		if (!$this->temToken()) {
			return $this->error('Configure o token_nfe na configuracao fiscal.', 'TOKEN_NAO_CONFIGURADO', 422);
		}

		$paths = [
			'/nfe/pdf/' . $chave,
			'/nfe/danfe?chave=' . $chave,
		];

		foreach ($paths as $path) {
			$url = $this->baseUrl . $path;
			try {
				$res = $this->http()->withHeaders(['Accept' => 'application/json'])->get($url)->throw();

				$contentType = (string) ($res->header('Content-Type') ?? '');
				$bodyRaw = $res->body();

				if (stripos($contentType, 'application/pdf') !== false && $bodyRaw) {
					return ['ok' => true, 'http' => $res->status(), 'pdf' => $bodyRaw, 'url' => $url];
				}

				$json = $res->json();
				if (is_array($json) && !empty($json['pdf'])) {
					$b64 = (string) $json['pdf'];
					if (strpos($b64, ',') !== false) {
						$b64 = explode(',', $b64, 2)[1];
					}
					$pdf = base64_decode($b64, true);
					if ($pdf === false || $pdf === '' || substr($pdf, 0, 4) !== '%PDF') {
						continue;
					}
					return ['ok' => true, 'http' => $res->status(), 'pdf' => $pdf, 'url' => $url, 'raw' => $json];
				}
			} catch (RequestException $e) {
				Log::warning('[IntegraNotas] pdfNFe falhou', ['url' => $url, 'status' => $this->exStatus($e)]);
			} catch (ConnectionException $e) {
				return $this->error('Falha de conexao ao obter PDF/DANFE', 'CONNECTION_ERROR', 0);
			}
		}

		return $this->error('Nao foi possivel obter o PDF/DANFE pela chave.', 'PDF_NOT_FOUND', 404);
	}
}
