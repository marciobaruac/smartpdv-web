<?php

namespace App\Http\Controllers;

use App\Models\ConfigNota;
use App\Models\ManifestaDfe;
use App\Services\IntegraNotasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IntegraNotasController extends Controller
{
	public function notasEntrada(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'cnpj' => 'nullable|string|min:14|max:18',
			'data_inicio' => 'nullable|date',
			'data_fim' => 'nullable|date|after_or_equal:data_inicio',
			'status' => 'nullable|string|max:60',
			'chave' => 'nullable|string|min:44|max:44',
		]);

		if ($validator->fails()) {
			return response()->json([
				'ok' => false,
				'message' => 'Parametros invalidos.',
				'errors' => $validator->errors(),
			], 422);
		}

		$config = ConfigNota::first();
		$cnpj = preg_replace('/\D+/', '', (string) ($request->cnpj ?: ($config->cnpj ?? '')));

		if ($cnpj === '' || strlen($cnpj) !== 14) {
			return response()->json([
				'ok' => false,
				'message' => 'Informe um CNPJ valido ou configure o CNPJ do emitente.',
			], 422);
		}

		$service = new IntegraNotasService();
		$resultado = $service->buscarNotasEntrada(
			(int) ManifestaDfe::max('nsu'),
			$cnpj,
			$request->data_inicio,
			$request->data_fim
		);

		if (empty($resultado['ok'])) {
			return response()->json([
				'ok' => false,
				'message' => $this->normalizarMensagem((string) ($resultado['message'] ?? 'Falha ao buscar notas de entrada.')),
			], 422);
		}

		$notas = collect($resultado['documentos'] ?? [])
			->filter(function ($documento) {
				return is_array($documento);
			})
			->map(function ($documento) {
				return $this->mapearNotaEntrada($documento);
			})
			->filter(function ($nota) use ($request) {
				return $this->filtrarNota($nota, $request);
			})
			->values();

		return response()->json([
			'ok' => true,
			'total' => $notas->count(),
			'data' => $notas,
		], 200);
	}

	public function importarNotasEntradaWeb(Request $request)
	{
		$value = session('user_logged');
		if (!$value || (isset($value['acesso_cliente']) && $value['acesso_cliente'] == 0)) {
			return response()->json([
				'ok' => false,
				'message' => 'Sessao expirada ou sem permissao.',
			], 401);
		}

		$service = new IntegraNotasService();
		$config = ConfigNota::first();
		$cnpj = preg_replace('/\D+/', '', (string) ($config->cnpj ?? ''));
		$periodo = trim((string) ($request->periodo ?: $request->periodo_integra_notas));
		if ($periodo !== '' && preg_match('/^\d{4}-\d{2}$/', $periodo)) {
			$dataPeriodo = Carbon::createFromFormat('Y-m', $periodo);
			$dataInicio = $dataPeriodo->copy()->startOfMonth()->format('Y-m-d');
			$dataFim = $dataPeriodo->copy()->endOfMonth()->format('Y-m-d');
		} else {
			$dataInicio = $this->normalizarDataFiltro($request->data_inicial ?: $request->data_inicio);
			$dataFim = $this->normalizarDataFiltro($request->data_final ?: $request->data_fim);
		}
		$resultado = $service->buscarNotasEntrada((int) ManifestaDfe::max('nsu'), $cnpj, $dataInicio, $dataFim);

		if (empty($resultado['ok'])) {
			return response()->json([
				'ok' => false,
				'message' => (string) ($resultado['message'] ?? 'Falha ao buscar notas na IntegraNotas.'),
			], 422);
		}

		$importadas = 0;
		$jaExistiam = 0;

		foreach (($resultado['documentos'] ?? []) as $documento) {
			if (!is_array($documento)) {
				continue;
			}

			$nota = $this->mapearNotaEntrada($documento);
			if ($nota['chave'] === '') {
				continue;
			}

			if (ManifestaDfe::where('chave', $nota['chave'])->exists()) {
				$jaExistiam++;
				continue;
			}

			ManifestaDfe::create([
				'chave' => $nota['chave'],
				'nome' => $nota['nome_emitente'],
				'documento' => $nota['cnpj_emitente'],
				'valor' => $nota['valor'],
				'num_prot' => $this->getValor($documento, ['numero_protocolo', 'protocolo', 'num_prot']),
				'data_emissao' => $nota['data_emissao'] ?: Carbon::now()->format('Y-m-d H:i:s'),
				'sequencia_evento' => 0,
				'fatura_salva' => false,
				'tipo' => 0,
				'nsu' => $nota['nsu'],
			]);

			$this->salvarXmlSeDisponivel($nota['chave'], $documento);
			$importadas++;
		}

		return response()->json([
			'ok' => true,
			'message' => 'IntegraNotas consultada. Novas notas: ' . $importadas . '. Ja existiam: ' . $jaExistiam . '.',
			'importadas' => $importadas,
			'ja_existiam' => $jaExistiam,
		], 200);
	}

	private function mapearNotaEntrada(array $documento)
	{
		$emitente = isset($documento['emitente']) && is_array($documento['emitente']) ? $documento['emitente'] : [];
		$chave = $this->getValor($documento, ['chave_acesso', 'chave']);
		$manifesto = $chave !== '' ? ManifestaDfe::where('chave', $chave)->first() : null;

		return [
			'id' => $this->getValor($documento, ['id']),
			'chave' => $chave,
			'cnpj_emitente' => $this->getValor($documento, ['cnpj', 'documento_emitente', 'cpf_cnpj_emitente', 'cnpj_emitente']) ?: $this->getValor($emitente, ['cpf_cnpj', 'cnpj', 'CNPJ']),
			'nome_emitente' => $this->getValor($documento, ['razao', 'nome_emitente', 'emitente_nome', 'razao_social_emitente']) ?: $this->getValor($emitente, ['nome', 'razao_social', 'xNome']),
			'valor' => (float) str_replace(',', '.', $this->getValor($documento, ['valor_total', 'valor', 'vNF'])),
			'data_emissao' => $this->normalizarData($this->getValor($documento, ['data', 'data_emissao', 'dh_emissao', 'dhEmi', 'created_at'])),
			'status' => $this->getStatus($documento, $manifesto),
			'tipo_manifesto' => $manifesto ? (int) $manifesto->tipo : null,
			'nsu' => (int) $this->getValor($documento, ['nsu', 'numero_nsu', 'dist_nsu']),
			'raw' => $documento,
		];
	}

	private function filtrarNota(array $nota, Request $request)
	{
		$chave = preg_replace('/\D+/', '', (string) $request->chave);
		if ($chave !== '' && $nota['chave'] !== $chave) {
			return false;
		}

		if ($request->status !== null && $request->status !== '') {
			$status = strtolower((string) $request->status);
			if (strtolower((string) $nota['status']) !== $status && (string) $nota['tipo_manifesto'] !== (string) $request->status) {
				return false;
			}
		}

		if ($request->data_inicio || $request->data_fim) {
			try {
				$dataEmissao = Carbon::parse($nota['data_emissao']);
			} catch (\Exception $e) {
				return false;
			}

			if ($request->data_inicio && $dataEmissao->lt(Carbon::parse($request->data_inicio)->startOfDay())) {
				return false;
			}

			if ($request->data_fim && $dataEmissao->gt(Carbon::parse($request->data_fim)->endOfDay())) {
				return false;
			}
		}

		return true;
	}

	private function getStatus(array $documento, $manifesto)
	{
		$status = $this->getValor($documento, ['status', 'situacao', 'manifestacao']);
		if ($status !== '') {
			return $status;
		}

		if ($manifesto) {
			return (string) $manifesto->estado();
		}

		return '--';
	}

	private function getValor(array $documento, array $chaves)
	{
		foreach ($chaves as $chave) {
			if (isset($documento[$chave]) && $documento[$chave] !== null && $documento[$chave] !== '') {
				return (string) $documento[$chave];
			}
		}

		return '';
	}

	private function normalizarData($data)
	{
		try {
			if ($data) {
				return Carbon::parse($data)->format('Y-m-d H:i:s');
			}
		} catch (\Exception $e) {
		}

		return null;
	}

	private function normalizarMensagem($mensagem)
	{
		return $mensagem;
	}

	private function normalizarDataFiltro($data)
	{
		if (!$data) {
			return null;
		}

		try {
			if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
				return Carbon::createFromFormat('d/m/Y', $data)->format('Y-m-d');
			}

			return Carbon::parse($data)->format('Y-m-d');
		} catch (\Exception $e) {
			return null;
		}
	}

	private function salvarXmlSeDisponivel($chave, array $documento)
	{
		$xml = $this->getValor($documento, ['xml']);
		if ($xml === '') {
			return;
		}

		if (base64_encode(base64_decode($xml, true)) === $xml) {
			$xml = base64_decode($xml);
		}

		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$diretorio = $public . 'xml_dfe';
		if (!is_dir($diretorio)) {
			@mkdir($diretorio, 0775, true);
		}

		file_put_contents($diretorio . '/' . $chave . '.xml', $xml);
	}
}
