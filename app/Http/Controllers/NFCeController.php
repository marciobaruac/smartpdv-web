<?php

namespace App\Http\Controllers;

use NFePHP\NFe\Common\Standardize;
use Illuminate\Http\Request;
use App\Models\VendaCaixa;
use App\Models\Enums\EstadoVenda;
use App\Models\Venda;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\Legacy\FilesFolders;
use App\Models\ConfigNota;
use App\Helpers\StockMove;
use App\Services\NFCeService;
use App\Services\CupomFiscalPdf;
use App\Services\CupomNaoFiscalPdf;
use App\Services\NuvemFiscalNfceService;
use Illuminate\Support\Facades\Log;
class NFCeController extends Controller
{

	public function __construct(){
		$this->middleware(function ($request, $next) {
			$value = session('user_logged');
			if(!$value){
				return redirect("/login");
			}
			return $next($request);
		});
	}

	public function gerar(Request $request){
		$traceId = uniqid('nfce_', true);
		$startedAt = microtime(true);

		try {
			if (session()->isStarted()) {
				session()->save();
			}
			if (function_exists('session_write_close')) {
				@session_write_close();
			}
		} catch (\Throwable $e) {
			// não interrompe o fluxo
		}

		Log::channel('nfce')->info('NFC-e gerar.start', [
			'trace_id' => $traceId,
			'venda_id' => $request->vendaId,
			'url' => $request->fullUrl(),
			'ip' => $request->ip()
		]);

		try {
			$vendaId = $request->vendaId;
			$venda = VendaCaixa::
			where('id', $vendaId)
			->first();
			Log::channel('nfce')->info('NFC-e gerar.venda_ok', [
				'trace_id' => $traceId,
				'venda_id' => $vendaId
			]);

			if (!$venda) {
				Log::channel('nfce')->warning('NFC-e gerar.venda_nao_encontrada', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId
				]);
				return response()->json(['message' => 'Venda não encontrada.'], 404);
			}

			$config = ConfigNota::first();
			Log::channel('nfce')->info('NFC-e gerar.config_ok', [
				'trace_id' => $traceId,
				'ambiente' => $config ? $config->ambiente : null
			]);
			if (!$config) {
				Log::channel('nfce')->error('NFC-e gerar.config_nao_encontrada', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId
				]);
				return response()->json(['message' => 'Configuração fiscal não encontrada.'], 500);
			}

			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);

			$nfe_service = new NFCeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => (int)$config->ambiente,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			]);
			Log::channel('nfce')->info('NFC-e gerar.service_ok', [
				'trace_id' => $traceId
			]);

			if($venda->estado == 'REJEITADO' || $venda->estado == 'PENDENTE'){
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$retornoConsulta = $this->consultarSefazAntesDeReenviar($nfe_service, $venda, $public, $traceId);
				if ($retornoConsulta !== null) {
					return response()->json($retornoConsulta);
				}
			}

			if($venda->estado == 'REJEITADO' || $venda->estado == 'DISPONIVEL'){
				Log::channel('nfce')->info('NFC-e gerar.reservar_numero.start', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId,
					'nfce_atual' => $venda->NFcNumero,
					'estado' => $venda->estado
				]);
				$this->reservarNumeroNfceParaVendaCaixa($venda);
				Log::channel('nfce')->info('NFC-e gerar.reservar_numero.finish', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId,
					'nfce_reservada' => $venda->NFcNumero
				]);



				$nfce = $nfe_service->gerarNFCe($vendaId);
				Log::channel('nfce')->info('NFC-e gerar.xml_montado', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId,
					'tem_erros_xml' => isset($nfce['erros_xml'])
				]);

				if(!isset($nfce['erros_xml'])){
					$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
					$xmlGerado = $this->normalizarPagamentoPixNfceXml($nfce['xml'], $venda, $traceId);
					$signed = $nfe_service->sign($xmlGerado);
					$this->validarPagamentoPixNfceAssinado($signed, $venda, $traceId);

					$this->persistirXmlNfce($public, (int) $venda->id, (string) $nfce['chave'], $signed);

					$resultado = $this->enviarNfceComProvider($nfe_service, $config, $signed, $xmlGerado, $nfce['chave'], 'venda-caixa-' . $venda->id);

					if(substr($resultado, 0, 4) != 'Erro'){
						$venda->chave = $nfce['chave'];
						$venda->path_xml = $nfce['chave'] . '.xml';
						if(strpos($resultado, 'NuvemPendente:') === 0){
							$venda->estado = 'PENDENTE';
						}else{
							$venda->estado = 'APROVADO';
						}

						$venda->NFcNumero = $nfce['nNf'];
						$venda->save();
					}else{
						$venda->estado = 'REJEITADO';
						$venda->save();
					}

					Log::channel('nfce')->info('NFC-e gerar.finish', [
						'trace_id' => $traceId,
						'venda_id' => $vendaId,
						'resultado' => $resultado,
						'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
					]);
					return response()->json($resultado);
				}else{
					Log::channel('nfce')->warning('NFC-e gerar.erros_xml', [
						'trace_id' => $traceId,
						'venda_id' => $vendaId,
						'erros_xml' => $nfce['erros_xml'],
						'exception' => $nfce['exception'] ?? null,
						'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
					]);
					return response()->json($nfce['erros_xml'], 401);
				}

			}else{
				Log::channel('nfce')->info('NFC-e gerar.ja_aprovada', [
					'trace_id' => $traceId,
					'venda_id' => $vendaId,
					'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
				]);
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$retornoNuvem = $this->validarStatusRealNuvemParaVendaCaixa($venda, $config, $public);
				return response()->json($retornoNuvem);
			}

		} catch (\Throwable $e) {
			Log::channel('nfce')->error('NFC-e gerar.exception', [
				'trace_id' => $traceId,
				'venda_id' => $request->vendaId,
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
			]);
			return response()->json(['message' => 'Falha ao gerar NFC-e. Consulte o log NFC-e.'], 500);
		}
	}

	public function gerarid($id){

		$vendaId = $id;
		$venda = VendaCaixa::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		if($venda->estado == 'REJEITADO' || $venda->estado == 'DISPONIVEL'){
			header('Content-type: text/html; charset=UTF-8');
			$this->reservarNumeroNfceParaVendaCaixa($venda);



			$nfce = $nfe_service->gerarNFCe($vendaId);

			if(!isset($nfce['erros_xml'])){
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$xmlGerado = $this->normalizarPagamentoPixNfceXml($nfce['xml'], $venda, 'gerarid_' . $venda->id);
				$signed = $nfe_service->sign($xmlGerado);
				$this->validarPagamentoPixNfceAssinado($signed, $venda, 'gerarid_' . $venda->id);
			    $this->persistirXmlNfce($public, (int) $venda->id, (string) $nfce['chave'], $signed);



				$resultado = $this->enviarNfceComProvider($nfe_service, $config, $signed, $xmlGerado, $nfce['chave'], 'venda-caixa-' . $venda->id);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->chave = $nfce['chave'];
					$venda->path_xml = $nfce['chave'] . '.xml';
					if(strpos($resultado, 'NuvemPendente:') === 0){
						$venda->estado = 'PENDENTE';
					}else{
						$venda->estado = 'APROVADO';
					}

				    $venda->NFcNumero = $nfce['nNf'];
				    $venda->save();
				}else{
					$venda->estado = 'REJEITADO';
					$venda->save();
				}
				echo json_encode($resultado);
			}else{
				return response()->json($nfce['erros_xml'], 401);
			}

		}else{
			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			echo json_encode($this->validarStatusRealNuvemParaVendaCaixa($venda, $config, $public));
		}

	}


	public function gerarvenda(Request $request){

		$vendaId = $request->vendaId;
		$venda = Venda::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		if($venda->estado == 'REJEITADO' || $venda->estado == 'DISPONIVEL'){
			header('Content-type: text/html; charset=UTF-8');

			$nfce = $nfe_service->gerarNFCeVenda($vendaId);
			if(!isset($nfce['erros_xml'])){
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$xmlGerado = $this->normalizarPagamentoPixNfceXml($nfce['xml'], $venda, 'gerarvenda_' . $venda->id);
				$signed = $nfe_service->sign($xmlGerado);
				$this->validarPagamentoPixNfceAssinado($signed, $venda, 'gerarvenda_' . $venda->id);
			    $this->persistirXmlNfce($public, (int) $venda->id, (string) $nfce['chave'], $signed);
				$resultado = $this->enviarNfceComProvider($nfe_service, $config, $signed, $xmlGerado, $nfce['chave'], 'venda-credito-' . $venda->id);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->chave = $nfce['chave'];
					$venda->path_xml = $nfce['chave'] . '.xml';
					if(strpos($resultado, 'NuvemPendente:') === 0){
						$venda->estado = 'PENDENTE';
					}else{
						$venda->estado = 'APROVADO';
					}

					$venda->NFcNumero = $nfce['nNf'];
					$venda->save();
				}else{
					$venda->estado = 'REJEITADO';
					$venda->save();
				}
				echo json_encode($resultado);
			}else{
				return response()->json($nfce['erros_xml'], 401);
			}

		}else{
			echo json_encode("Apro");
		}

	}

	public function xmlTemp($id){

		$venda = VendaCaixa::
		where('id', $id)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		$nfce = $nfe_service->gerarNFCe($id);
		if(!isset($nfce['erros_xml'])){
			$xml = $nfce['xml'];
			return response($xml)
			->header('Content-Type', 'application/xml');

		}else{
			return response()->json($nfce['erros_xml'], 401);
		}


	}


	public function imprimir($id){
		$venda = VendaCaixa::
		where('id', $id)
		->first();
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$config = ConfigNota::first();
		$bloqueioMsg = $this->sincronizarStatusNuvemAntesImpressao($venda, $config, $public);
		if($bloqueioMsg !== null){
			return response($bloqueioMsg, 409);
		}
		$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, (string) $venda->chave);
		if($xmlPath !== null){
			try {
				$xml = file_get_contents($xmlPath);
				$pdf = $this->renderCupomFiscal($xml, $public.'imgs/logo.jpg');

			// header('Content-Type: application/pdf');
			// echo $pdf;

				//$pdf = $pdf + $pdf1 ;
				return response($pdf)
				->header('Content-Type', 'application/pdf');

			//	return response($pdf1)
			//	->header('Content-Type', 'application/pdf');


			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		}else{
			echo "Arquivo XML não encontrado!!";
		}
	}
	public function imprimirvenda($id){
		$venda = Venda::
		where('id', $id)
		->first();
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, (string) $venda->chave);
		if($xmlPath !== null){
			try {
				$xml = file_get_contents($xmlPath);
				$pdf = $this->renderCupomFiscal($xml, $public.'imgs/logo.jpg');

			// header('Content-Type: application/pdf');
			// echo $pdf;
				return response($pdf)
				->header('Content-Type', 'application/pdf');

			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		}else{
			echo "Arquivo XML não encontrado!!";
		}
	}


	public function baixarXml($id){
		$venda = VendaCaixa::
		where('id', $id)
		->first();
		try {

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, (string) $venda->chave);
			if($xmlPath === null){
				return response("Arquivo XML não encontrado!!", 404);
			}

			return response()->download($xmlPath);
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function imprimirNaoFiscal($id){
		$venda = VendaCaixa::
		where('id', $id)
		->first();
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$pathLogo = $public.'imgs/logo.jpg';

		$pdf = $this->renderCupomNaoFiscal($venda, $pathLogo);

		// header('Content-Type: application/pdf');
		// echo $pdf;
		return response($pdf)
		->header('Content-Type', 'application/pdf');
	}

	public function gerarArquivo(Request $request){
		$id = $request->id;
		$tipo = $request->tipo;
		$venda = VendaCaixa::
		where('id', $id)
		->first();
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';

		if($tipo == 'nao_fiscal'){
			$pathLogo = $public.'imgs/logo.jpg';

			$pdf = $this->renderCupomNaoFiscal($venda, $pathLogo);
		}else{
			$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, (string) $venda->chave);
			if($xmlPath === null){
				return response()->json("Arquivo XML não encontrado!!", 404);
			}
			$xml = file_get_contents($xmlPath);
			$pdf = $this->renderCupomFiscal($xml, $public.'imgs/logo.jpg');
		}

		file_put_contents(public_path('impressao_pdv/'.$id.'.pdf'), $pdf);

		return response()->json("ok", 200);

	}

	public function imprimirNaoFiscalCredito($id){
		$venda = Venda::
		where('id', $id)
		->first();
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$pathLogo = $public.'imgs/logo.jpg';

		$pdf = $this->renderCupomNaoFiscal($venda, $pathLogo);

		// header('Content-Type: application/pdf');
		// echo $pdf;
		return response($pdf)
		->header('Content-Type', 'application/pdf');
	}

	private function renderCupomNaoFiscal($venda, $pathLogo){
		if (class_exists(\NFePHP\DA\NFe\Cupom::class)) {
			$cupomClass = \NFePHP\DA\NFe\Cupom::class;
			$cupom = new $cupomClass($venda, $pathLogo);
			$cupom->monta();
			return $cupom->render();
		}

		return (new CupomNaoFiscalPdf($venda, $pathLogo))->render();
	}

	private function renderCupomFiscal($xml, $pathLogo){
		return (new CupomFiscalPdf($xml, $pathLogo))->render();
	}

	public function cancelar(Request $request){
		if ($this->getNfceProvider() === 'NUVEM_FISCAL') {
			return $this->cancelarViaNuvemFiscalVendaCaixa($request);
		}

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);


		$nfce = $nfe_service->cancelarNFCe($request->id, $request->justificativa);

		if(!isset($nfce['cStat'])){
			return response()->json($nfce, 404);
		}
		if(in_array((int)$nfce['retEvento']['infEvento']['cStat'], [101, 135, 155, 573])){
			$venda = VendaCaixa::
			where('id', $request->id)
			->first();
			$venda->estado = 'CANCELADO';
			$venda->save();
			// if($venda){
			// 	$stockMove = new StockMove();

			// 	foreach($venda->itens as $i){
			// 		$stockMove->pluStock($i->produto_id,
			// 			$i->quantidade, -50); // -50 na altera valor compra
			// 	}
			// }
			return response()->json($nfce, 200);

		}else{
			if($nfce['retEvento']['infEvento']['cStat'] == 501){

			}
			return response()->json($nfce, 401);
		}


	}

	public function cancelarvenda(Request $request){
		if ($this->getNfceProvider() === 'NUVEM_FISCAL') {
			return $this->cancelarViaNuvemFiscalVendaCredito($request);
		}

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);


		$nfce = $nfe_service->cancelarNFCevenda($request->id, $request->justificativa);

		if(!isset($nfce['cStat'])){
			return response()->json($nfce, 404);
		}
		if(in_array((int)$nfce['retEvento']['infEvento']['cStat'], [101, 135, 155, 573])){
			$venda = Venda::
			where('id', $request->id)
			->first();
			$venda->estado = 'CANCELADO';
			$venda->save();
			// if($venda){
			// 	$stockMove = new StockMove();

			// 	foreach($venda->itens as $i){
			// 		$stockMove->pluStock($i->produto_id,
			// 			$i->quantidade, -50); // -50 na altera valor compra
			// 	}
			// }
			return response()->json($nfce, 200);

		}else{
			if($nfce['retEvento']['infEvento']['cStat'] == 501){

			}
			return response()->json($nfce, 401);
		}


	}

	public function cancelarSubstituicao(Request $request){
		if ($this->getNfceProvider() === 'NUVEM_FISCAL') {
			return $this->cancelarViaNuvemFiscalSubstituicao($request);
		}

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);

		$nfce = $nfe_service->cancelarSubstituicaoNFCe($request->id, $request->justificativa,
			$request->chaveRef);

		if(!isset($nfce['cStat'])){
			return response()->json($nfce, 404);
		}
		if(in_array((int)$nfce['retEvento']['infEvento']['cStat'], [101, 135, 155, 573])){
			$venda = VendaCaixa::
			where('id', $request->id)
			->first();
			$venda->estado = 'CANCELADO';
			$venda->save();
			// if($venda){
			// 	$stockMove = new StockMove();

			// 	foreach($venda->itens as $i){
			// 		$stockMove->pluStock($i->produto_id,
			// 			$i->quantidade, -50); // -50 na altera valor compra
			// 	}
			// }
			return response()->json($nfce, 200);

		}else{
			if($nfce['retEvento']['infEvento']['cStat'] == 501){

			}
			return response()->json($nfce, 401);
		}


	}

	public function deleteVenda($id){
		$result = VendaCaixa::where('id', $id)
		->delete();
		echo json_encode($result);
	}

	private function parseDate($date, $plusDay = false){
        if($plusDay == false)
            return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
        else
            return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
    }

	private function cancelarViaNuvemFiscalVendaCaixa(Request $request)
	{
		$venda = VendaCaixa::where('id', $request->id)->first();
		if (!$venda) {
			return response()->json(['message' => 'Venda não encontrada.'], 404);
		}

		$ret = $this->cancelarDocumentoNuvem((string) $venda->chave, (string) $request->justificativa);
		if (empty($ret['ok'])) {
			return response()->json($ret, 422);
		}

		if (!empty($ret['cancelada'])) {
			$venda->estado = 'CANCELADO';
			$venda->save();
		}

		if (!empty($ret['pendente']) && (string) $venda->estado !== 'CANCELADO') {
			$venda->estado = 'PENDENTE';
			$venda->save();
		}

		return $this->respostaCancelamentoNuvemCompativel($ret);
	}

	private function cancelarViaNuvemFiscalVendaCredito(Request $request)
	{
		$venda = Venda::where('id', $request->id)->first();
		if (!$venda) {
			return response()->json(['message' => 'Venda não encontrada.'], 404);
		}

		$ret = $this->cancelarDocumentoNuvem((string) $venda->chave, (string) $request->justificativa);
		if (empty($ret['ok'])) {
			return response()->json($ret, 422);
		}

		if (!empty($ret['cancelada'])) {
			$venda->estado = 'CANCELADO';
			$venda->save();
		}

		if (!empty($ret['pendente']) && (string) $venda->estado !== 'CANCELADO') {
			$venda->estado = 'PENDENTE';
			$venda->save();
		}

		return $this->respostaCancelamentoNuvemCompativel($ret);
	}

	private function cancelarViaNuvemFiscalSubstituicao(Request $request)
	{
		$venda = VendaCaixa::where('id', $request->id)->first();
		if (!$venda) {
			return response()->json(['message' => 'Venda não encontrada.'], 404);
		}

		$chave = trim((string) $request->chaveRef);
		if ($chave === '') {
			$chave = (string) $venda->chave;
		}

		$ret = $this->cancelarDocumentoNuvem($chave, (string) $request->justificativa);
		if (empty($ret['ok'])) {
			return response()->json($ret, 422);
		}

		if (!empty($ret['cancelada'])) {
			$venda->estado = 'CANCELADO';
			$venda->save();
		}

		if (!empty($ret['pendente']) && (string) $venda->estado !== 'CANCELADO') {
			$venda->estado = 'PENDENTE';
			$venda->save();
		}

		return $this->respostaCancelamentoNuvemCompativel($ret);
	}

	private function cancelarDocumentoNuvem(string $chave, string $justificativa): array
	{
		$chave = trim($chave);
		if ($chave === '') {
			return [
				'ok' => false,
				'message' => 'NFC-e sem chave para cancelamento na Nuvem Fiscal.',
			];
		}

		$config = ConfigNota::first();
		if (!$config) {
			return [
				'ok' => false,
				'message' => 'Configuração fiscal não encontrada.',
			];
		}

		$cnpj = preg_replace('/\D+/', '', (string) $config->cnpj);
		$service = new NuvemFiscalNfceService();

		return $service->cancelarPorChave(
			$chave,
			(int) $config->ambiente,
			$justificativa,
			$cnpj
		);
	}

	private function respostaCancelamentoNuvemCompativel(array $ret)
	{
		$xMotivo = (string) ($ret['motivo_status'] ?? $ret['mensagem'] ?? $ret['message'] ?? '');
		if ($xMotivo === '') {
			$xMotivo = !empty($ret['cancelada'])
				? 'Evento de cancelamento homologado.'
				: 'Pedido de cancelamento enviado.';
		}

		$cStatEvento = 136;
		$http = 200;
		if (!empty($ret['cancelada'])) {
			$cStatEvento = 135;
			$http = 200;
		} elseif (!empty($ret['pendente'])) {
			$cStatEvento = 136;
			$http = 200;
		} elseif (!empty($ret['ok'])) {
			$cStatEvento = (int) ($ret['codigo_status'] ?? 999);
			$http = 401;
		}

		$payload = array_merge($ret, [
			'cStat' => 128,
			'retEvento' => [
				'infEvento' => [
					'cStat' => $cStatEvento,
					'xMotivo' => $xMotivo,
				],
			],
		]);

		return response()->json($payload, $http);
	}
    private function diasDatas($data_inicial,$data_final) {
        $diferenca = strtotime($data_final) - strtotime($data_inicial);
        $dias = floor($diferenca / (60 * 60 * 24));
        return $dias;
    }

    public function reprocessamento(Request $request){
        try{
            $dataInicial = $request->dataIni;
            $dataFinal = $request->dataFim;
            $statusFiltro = (string) $request->statusFiltro;

            if (($dataInicial == null) || ($dataFinal== null)){
                return response()->json("Preencha a Data Inicial e Final para reprocessar!", 401);
            }elseif ($this->parseDate($dataInicial) > $this->parseDate($dataFinal, true)){
                return response()->json("Data Final Maior que Data Inicial!", 401);
            }elseif($this->diasDatas(
                $this->parseDate($dataInicial) , $this->parseDate($dataFinal, true)
                )  > 60){
                return response()->json("O intervalo Data Inicial e Final grande (Limite: 60 dias)!", 401);
            }

            $provider = $this->getNfceProvider();
            $config = ConfigNota::first();
            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            $statusPermitidos = $this->obterEstadosReprocessamento($statusFiltro);

            if ($provider === 'NUVEM_FISCAL') {
                $vendas = VendaCaixa::
                        where('ativo',true)
                        ->whereIn('estado', $statusPermitidos)
                        ->orderBy('id', 'desc')
                        ->whereBetween('created_at', [$this->parseDate($dataInicial),
                                                        $this->parseDate($dataFinal, true)
                                                        ])
                        ->get();

                $totais = [
                    'aprovadas' => 0,
                    'pendentes' => 0,
                    'rejeitadas' => 0,
                    'erros' => 0,
                ];

                foreach($vendas as $v){
                    $resultado = $this->reprocessarNuvemFiscalStatusVenda($v, $config, $public);
                    if ($resultado === 'aprovada') {
                        $totais['aprovadas']++;
                    } elseif ($resultado === 'pendente') {
                        $totais['pendentes']++;
                    } elseif ($resultado === 'rejeitada') {
                        $totais['rejeitadas']++;
                    } else {
                        $totais['erros']++;
                    }
                }

                return response()->json(
                    "Reprocessamento Nuvem Fiscal concluído. Aprovadas: {$totais['aprovadas']}, Pendentes: {$totais['pendentes']}, Rejeitadas: {$totais['rejeitadas']}, Erros: {$totais['erros']}",
                    200
                );
            }

            $vendas = VendaCaixa::
                        where('ativo',true)
                        ->whereIn('estado', $statusPermitidos)
                        ->orderBy('id', 'desc')
                        ->whereBetween('created_at', [$this->parseDate($dataInicial),
                                                        $this->parseDate($dataFinal, true)
                                                        ])
                        ->get();

            foreach($vendas as $v){
                Log::channel('nfce')->info('NFC-e reprocessamento SEFAZ consulta protocolo', [
                    'venda_id' => $v->id,
                    'estado' => $v->estado,
                    'chave' => $v->chave,
                ]);
                $retAux = $this->gerarXMLJaAutorizado($v);
                if ($retAux == "Sucesso"){
                    $v->estado = EstadoVenda::APROVADO;
                    $v->save();
                }elseif ($retAux == "Erro"){

                }
            }
            echo json_encode("Apro");
        }catch(\Exception $r){
            return response()->json("Erro no reprocessamento!", 401);
        }
    }

    private function obterEstadosReprocessamento(string $filtro): array
    {
        $filtro = strtolower(trim($filtro));
        if ($filtro === 'todos') {
            return ['DISPONIVEL', 'PENDENTE', 'APROVADO', 'REJEITADO'];
        }
        if ($filtro === 'pendente') {
            return ['PENDENTE'];
        }
        if ($filtro === 'aprovado') {
            return ['APROVADO'];
        }
        if ($filtro === 'rejeitado') {
            return ['REJEITADO'];
        }

        // padrão da tela
        return ['REJEITADO', 'PENDENTE'];
    }

    private function reprocessarNuvemFiscalStatusVenda(VendaCaixa $venda, ?ConfigNota $config, string $public): string
    {
        if (trim((string) $venda->chave) === '') {
            // Tenta recuperar a chave pelo XML local quando existir.
            $xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, '');
            if ($xmlPath !== null) {
                $xmlLocal = @simplexml_load_file($xmlPath);
                if ($xmlLocal !== false && isset($xmlLocal->infNFe)) {
                    try {
                        $chaveXml = substr((string) $xmlLocal->infNFe->attributes()['Id'], 3);
                        if ($chaveXml !== '') {
                            $venda->chave = $chaveXml;
                            if (empty($venda->path_xml)) {
                                $venda->path_xml = $chaveXml . '.xml';
                            }
                            $venda->save();
                        }
                    } catch (\Throwable $e) {
                        // continua e tratará como erro sem chave
                    }
                }
            }
        }

        if (trim((string) $venda->chave) === '') {
            return 'erro';
        }

        $tpAmb = (int) optional($config)->ambiente;
        $cnpj = preg_replace('/\D+/', '', (string) optional($config)->cnpj);
        $service = new NuvemFiscalNfceService();
        $ret = $service->consultarPorChave((string) $venda->chave, $tpAmb, $cnpj);

        if (empty($ret['ok'])) {
            return 'erro';
        }

        if (empty($ret['found'])) {
            $venda->estado = 'PENDENTE';
            $venda->save();
            return 'pendente';
        }

        if (!empty($ret['autorizada'])) {
            $venda->estado = 'APROVADO';
            if (empty($venda->path_xml) && !empty($venda->chave)) {
                $venda->path_xml = $venda->chave . '.xml';
            }
            $venda->save();

            $xmlProcessado = trim((string) ($ret['xml'] ?? ''));
            if ($xmlProcessado !== '') {
                $this->persistirXmlNfce($public, (int) $venda->id, (string) $venda->chave, $xmlProcessado);
            }

            return 'aprovada';
        }

        $status = strtolower((string) ($ret['status'] ?? 'pendente'));
        if (in_array($status, ['rejeitado', 'erro', 'denegado'], true)) {
            $venda->estado = 'REJEITADO';
            $venda->save();
            return 'rejeitada';
        }

        $venda->estado = 'PENDENTE';
        $venda->save();
        return 'pendente';
    }

    public function gerarXMLJaAutorizado($venda){

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		try{
			$nfe_service = new NFCeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => (int)$config->ambiente,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			]);

            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			if (file_exists($public.'xml_nfce/'.$venda->id.'.xml')){
                $xmlLocal = simplexml_load_file($public.'xml_nfce/'.$venda->id.'.xml');
                $bChaveAcessoNula = false;

                if ($venda->chave == null || empty($venda->chave) ){
                    try{
                        $venda->chave = substr(((string)  $xmlLocal->infNFe->attributes()["Id"]),3);
                        $bChaveAcessoNula= true;
                    }catch(\Exception $r){
                        return "Erro";
                    }
                }

                Log::channel('nfce')->info('NFC-e buscar XML autorizado por consulta SEFAZ', [
                    'venda_id' => $venda->id,
                    'chave' => $venda->chave,
                ]);

                $c = $nfe_service->consultarNFCeRetXML($venda);
                $st = new Standardize();
    			$std = $st->toStd($c);

    			if ($std->cStat != 100 ) {
                    Log::channel('nfce')->warning('NFC-e consulta SEFAZ nao autorizada no reprocessamento', [
                        'venda_id' => $venda->id,
                        'chave' => $venda->chave,
                        'cStat' => $std->cStat ?? null,
                        'xMotivo' => $std->xMotivo ?? null,
                    ]);
                    return "Erro";
                }

                if ($bChaveAcessoNula == true){
                    if (($venda->path_xml == null) || empty($venda->path_xml) ){
                        $venda->path_xml = $venda->chave . '.xml';
                    }
                    $venda->save();
                }
                $nfe_service->retornaXMLAssinado($xmlLocal->asXML(),$c, $venda->chave);
                Log::channel('nfce')->info('NFC-e protocolo recuperado no reprocessamento SEFAZ', [
                    'venda_id' => $venda->id,
                    'chave' => $venda->chave,
                    'nProt' => $std->protNFe->infProt->nProt ?? null,
                ]);
                return "Sucesso";
            }else{
                return "Erro";
            }

		}catch(\Exception $r){
			return "Erro";
		}
	}

	public function consultar($id){
		$venda = VendaCaixa::find($id);

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		try{
			$nfe_service = new NFCeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => (int)$config->ambiente,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			]);

			$c = $nfe_service->consultarNFCe($venda);

			return response()->json($c, 200);
		}catch(\Throwable $r){
			Log::channel('nfce')->error('NFC-e consultar.controller_exception', [
				'venda_id' => $id,
				'message' => $r->getMessage(),
				'file' => $r->getFile(),
				'line' => $r->getLine(),
			]);
			return response()->json($r->getMessage(), 401);

		}
	}

    public function gerarXml($id){


		$venda = VendaCaixa::
		where('id', $id)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFCeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);


		header('Content-type: text/html; charset=UTF-8');

		$nfce = $nfe_service->gerarNFCe($id);
		if(!isset($nfce['erros_xml'])){
			$xml = $nfce['xml'];

			return response($xml)
			->header('Content-Type', 'application/xml');
		}else{
			return response()->json($nfce['erros_xml'], 401);
		}

	}

	private function reservarNumeroNfceParaVendaCaixa(VendaCaixa $venda){
		// Uma vez reservado, mantém o mesmo número da NFC-e nos reenvios.
		if ((int) $venda->NFcNumero > 0) {
			return;
		}

		$estadoPermiteReserva = in_array((string) $venda->estado, ['DISPONIVEL', 'REJEITADO'], true);
		if(!$estadoPermiteReserva){
			return;
		}

		$config = ConfigNota::first();
		if($config == null){
			throw new \Exception('Configuração fiscal não encontrada para definir numeração da NFC-e.');
		}

		$ultimoConfigurado = (int) $config->ultimo_numero_nfce;
		$proximoNumero = $ultimoConfigurado + 1;
		if ($proximoNumero <= 1) {
			$proximoNumero = 1;
		}

		$venda->NFcNumero = $proximoNumero;
		$venda->save();

		$config->ultimo_numero_nfce = $proximoNumero;
		$config->save();
	}

	private function persistirXmlNfce(string $public, int $vendaId, string $chave, string $xml): void
	{
		$dir = $public . 'xml_nfce/';
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}

		// Mantém compatibilidade com rotinas antigas que buscam por id.
		@file_put_contents($dir . $vendaId . '.xml', $xml);

		$chave = trim($chave);
		if ($chave !== '') {
			// Padrão oficial do sistema: nome do arquivo pela chave de acesso.
			@file_put_contents($dir . $chave . '.xml', $xml);
		}
	}

	private function normalizarPagamentoPixNfceXml(string $xml, $venda, string $traceId): string
	{
		$tipoPagamento = (string) ($venda->tipo_pagamento ?? '');
		if ($tipoPagamento !== '04') {
			return $xml;
		}

		try {
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = false;
			if (!$dom->loadXML($xml)) {
				return $xml;
			}

			$xpath = new \DOMXPath($dom);
			$pagNodes = $xpath->query('//*[local-name()="infNFe"]/*[local-name()="pag"]');
			if (!$pagNodes || $pagNodes->length === 0) {
				return $xml;
			}

			$pag = $pagNodes->item(0);
			$ns = $pag->namespaceURI ?: 'http://www.portalfiscal.inf.br/nfe';

			$detNodes = [];
			foreach ($pag->childNodes as $child) {
				if ($child instanceof \DOMElement && $child->localName === 'detPag') {
					$detNodes[] = $child;
				}
			}
			foreach ($detNodes as $detNode) {
				$pag->removeChild($detNode);
			}

			$vPag = number_format((float) ($venda->valor_total - ($venda->desconto ?? 0)), 2, '.', '');
			$detPag = $dom->createElementNS($ns, 'detPag');
			$detPag->appendChild($dom->createElementNS($ns, 'tPag', '17'));
			$detPag->appendChild($dom->createElementNS($ns, 'vPag', $vPag));

			$vTroco = null;
			foreach ($pag->childNodes as $child) {
				if ($child instanceof \DOMElement && $child->localName === 'vTroco') {
					$vTroco = $child;
					break;
				}
			}
			if ($vTroco) {
				$pag->insertBefore($detPag, $vTroco);
			} else {
				$pag->appendChild($detPag);
			}

			$xmlNormalizado = $dom->saveXML();
			$pagSnippet = '';
			$pagAtual = $xpath->query('//*[local-name()="infNFe"]/*[local-name()="pag"]')->item(0);
			if ($pagAtual instanceof \DOMElement) {
				$pagSnippet = $dom->saveXML($pagAtual);
			}

			Log::channel('nfce')->info('NFC-e XML pagamento PIX normalizado antes da assinatura', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id ?? null,
				'tipo_pagamento_sistema' => $tipoPagamento,
				'pag_xml' => $pagSnippet,
			]);

			return $xmlNormalizado ?: $xml;
		} catch (\Throwable $e) {
			Log::channel('nfce')->error('NFC-e falha ao normalizar pagamento PIX no XML', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id ?? null,
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);
			return $xml;
		}
	}

	private function validarPagamentoPixNfceAssinado(string $xml, $venda, string $traceId): void
	{
		$tipoPagamento = (string) ($venda->tipo_pagamento ?? '');
		if ($tipoPagamento !== '04') {
			return;
		}

		try {
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;
			if (!$dom->loadXML($xml)) {
				throw new \Exception('XML assinado invalido para validar pagamento Pix.');
			}

			$xpath = new \DOMXPath($dom);
			$detPagNodes = $xpath->query('//*[local-name()="infNFe"]/*[local-name()="pag"]/*[local-name()="detPag"]');
			$detalhes = [];
			$temPix = false;
			$temCartao = false;

			foreach ($detPagNodes as $detPag) {
				if (!($detPag instanceof \DOMElement)) {
					continue;
				}

				$tPagNode = $xpath->query('./*[local-name()="tPag"]', $detPag)->item(0);
				$vPagNode = $xpath->query('./*[local-name()="vPag"]', $detPag)->item(0);
				$cardNode = $xpath->query('./*[local-name()="card"]', $detPag)->item(0);
				$tPag = $tPagNode ? trim($tPagNode->textContent) : '';

				if ($tPag === '17') {
					$temPix = true;
				}
				if (in_array($tPag, ['03', '04'], true)) {
					$temCartao = true;
				}

				$detalhes[] = [
					'tPag' => $tPag,
					'vPag' => $vPagNode ? trim($vPagNode->textContent) : '',
					'tem_card' => $cardNode instanceof \DOMElement,
					'xml' => $dom->saveXML($detPag),
				];
			}

			Log::channel('nfce')->info('NFC-e XML assinado pagamento validado', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id ?? null,
				'tipo_pagamento_sistema' => $tipoPagamento,
				'detPag' => $detalhes,
			]);

			if (!$temPix || $temCartao) {
				throw new \Exception('Pagamento Pix/TEF Pix inconsistente no XML assinado. Verifique log NFC-e XML assinado pagamento validado.');
			}
		} catch (\Throwable $e) {
			Log::channel('nfce')->error('NFC-e XML assinado pagamento invalido', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id ?? null,
				'message' => $e->getMessage(),
			]);
			throw $e;
		}
	}

	private function consultarSefazAntesDeReenviar(NFCeService $nfeService, VendaCaixa $venda, string $public, string $traceId): ?string
	{
		if ($this->getNfceProvider() === 'NUVEM_FISCAL') {
			return null;
		}

		$chave = trim((string) $venda->chave);
		$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, $chave);
		$xmlLocal = null;

		if ($xmlPath !== null) {
			$xmlLocal = @simplexml_load_file($xmlPath);
			if ($chave === '' && $xmlLocal) {
				try {
					$chave = substr((string) $xmlLocal->infNFe->attributes()['Id'], 3);
					$venda->chave = $chave;
					$venda->save();
				} catch (\Throwable $e) {
					Log::channel('nfce')->warning('NFC-e pre-reenvio sem chave no XML local', [
						'trace_id' => $traceId,
						'venda_id' => $venda->id,
						'xml_path' => $xmlPath,
						'message' => $e->getMessage(),
					]);
				}
			}
		}

		if ($chave === '') {
			Log::channel('nfce')->info('NFC-e pre-reenvio sem chave para consulta; segue emissao normal', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id,
				'estado' => $venda->estado,
				'nfce_numero' => $venda->NFcNumero,
			]);
			return null;
		}

		Log::channel('nfce')->info('NFC-e pre-reenvio consultando chave antes de enviar', [
			'trace_id' => $traceId,
			'venda_id' => $venda->id,
			'estado' => $venda->estado,
			'nfce_numero' => $venda->NFcNumero,
			'chave' => $chave,
		]);

		$consultaXml = $nfeService->consultarNFCeRetXML($venda);
		if (trim((string) $consultaXml) === '') {
			Log::channel('nfce')->warning('NFC-e pre-reenvio consulta vazia; segue emissao normal', [
				'trace_id' => $traceId,
				'venda_id' => $venda->id,
				'chave' => $chave,
			]);
			return null;
		}

		$st = new Standardize();
		$std = $st->toStd($consultaXml);
		$cStat = (int) ($std->cStat ?? 0);

		Log::channel('nfce')->info('NFC-e pre-reenvio retorno consulta chave', [
			'trace_id' => $traceId,
			'venda_id' => $venda->id,
			'chave' => $chave,
			'cStat' => $cStat,
			'xMotivo' => $std->xMotivo ?? null,
			'nProt' => $std->protNFe->infProt->nProt ?? null,
		]);

		if ($cStat !== 100) {
			return null;
		}

		$xmlAssinado = $xmlLocal ? $xmlLocal->asXML() : '';
		if (trim((string) $xmlAssinado) === '') {
			$xmlPath = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, $chave);
			$xmlAssinado = $xmlPath !== null ? @file_get_contents($xmlPath) : '';
		}

		if (trim((string) $xmlAssinado) !== '') {
			$nfeService->retornaXMLAssinado($xmlAssinado, $consultaXml, $chave);
		}

		$venda->chave = $chave;
		$venda->path_xml = $chave . '.xml';
		$venda->estado = 'APROVADO';
		$venda->save();

		$protocolo = (string) ($std->protNFe->infProt->nProt ?? 'autorizada');
		Log::channel('nfce')->info('NFC-e pre-reenvio autorizacao recuperada sem novo envio', [
			'trace_id' => $traceId,
			'venda_id' => $venda->id,
			'chave' => $chave,
			'protocolo' => $protocolo,
		]);

		return $protocolo;
	}

	private function resolverCaminhoXmlNfce(string $public, int $vendaId, string $chave): ?string
	{
		$dir = $public . 'xml_nfce/';
		$chave = trim($chave);
		if ($chave !== '') {
			$pathChave = $dir . $chave . '.xml';
			if (file_exists($pathChave)) {
				return $pathChave;
			}
		}

		$pathId = $dir . $vendaId . '.xml';
		if (file_exists($pathId)) {
			return $pathId;
		}

		return null;
	}

	private function validarStatusRealNuvemParaVendaCaixa(VendaCaixa $venda, ?ConfigNota $config, string $public): string
	{
		if ($this->getNfceProvider() !== 'NUVEM_FISCAL') {
			return ((string) $venda->estado === 'APROVADO') ? 'Apro' : ('Erro: NFC-e não está aprovada. Estado atual: ' . $venda->estado);
		}

		if (trim((string) $venda->chave) === '') {
			return 'Erro: NFC-e sem chave para consulta na Nuvem Fiscal.';
		}

		$tpAmb = (int) optional($config)->ambiente;
		$cnpj = preg_replace('/\D+/', '', (string) optional($config)->cnpj);

		$service = new NuvemFiscalNfceService();
		$ret = $service->consultarPorChave((string) $venda->chave, $tpAmb, $cnpj);
		if (empty($ret['ok'])) {
			return 'Erro: ' . (string) ($ret['message'] ?? 'Falha ao consultar status da NFC-e na Nuvem Fiscal.');
		}

		if (empty($ret['found'])) {
			$venda->estado = 'PENDENTE';
			$venda->save();
			return 'NuvemPendente:' . (string) ($ret['id'] ?? '');
		}

		if (empty($ret['autorizada'])) {
			$status = strtolower((string) ($ret['status'] ?? 'pendente'));
			if (in_array($status, ['rejeitado', 'erro', 'denegado'], true)) {
				$venda->estado = 'REJEITADO';
				$venda->save();
				$raw = $ret['raw'] ?? [];
				$detalhes = $this->detalhesRejeicaoNuvemFiscal($raw, $status);
				return 'Erro: NFC-e rejeitada na Nuvem Fiscal - ' . $detalhes;
			}

			$venda->estado = 'PENDENTE';
			$venda->save();
			return 'NuvemPendente:' . (string) ($ret['id'] ?? '');
		}

		$venda->estado = 'APROVADO';
		if (empty($venda->path_xml) && !empty($venda->chave)) {
			$venda->path_xml = $venda->chave . '.xml';
		}
		$venda->save();

		$xmlProcessado = trim((string) ($ret['xml'] ?? ''));
		if ($xmlProcessado !== '') {
			$this->persistirXmlNfce($public, (int) $venda->id, (string) $venda->chave, $xmlProcessado);
		}

		$protocolo = (string) ($ret['protocolo'] ?? $ret['id'] ?? 'autorizada');
		return 'NuvemAprovado:' . $protocolo;
	}

	private function sincronizarStatusNuvemAntesImpressao(VendaCaixa $venda, ?ConfigNota $config, string $public): ?string
	{
		if ($this->getNfceProvider() !== 'NUVEM_FISCAL') {
			return null;
		}

		if (trim((string) $venda->chave) === '') {
			return 'NFC-e sem chave de acesso. Gere/reenvie a nota antes de imprimir.';
		}

		$xmlPathAtual = $this->resolverCaminhoXmlNfce($public, (int) $venda->id, (string) $venda->chave);
		if ((string) $venda->estado === 'APROVADO' && $this->xmlPossuiProtocolo($xmlPathAtual)) {
			// Já está autorizado e o XML local já contém protocolo.
			return null;
		}

		$tpAmb = (int) optional($config)->ambiente;
		$cnpj = preg_replace('/\D+/', '', (string) optional($config)->cnpj);

		$service = new NuvemFiscalNfceService();
		$ret = $service->consultarPorChave((string) $venda->chave, $tpAmb, $cnpj);
		if (empty($ret['ok'])) {
			return (string) ($ret['message'] ?? 'Falha ao consultar status da NFC-e na Nuvem Fiscal.');
		}

		if (empty($ret['found'])) {
			return 'NFC-e ainda não localizada na base autorizadora. Aguarde alguns segundos e tente novamente.';
		}

		if (empty($ret['autorizada'])) {
			$status = (string) ($ret['status'] ?? 'pendente');
			$statusNorm = strtolower($status);
			if (in_array($statusNorm, ['rejeitado', 'erro', 'denegado'], true) && (string) $venda->estado !== 'REJEITADO') {
				$venda->estado = 'REJEITADO';
				$venda->save();
			}
			return 'NFC-e ainda não autorizada (status: ' . $status . '). Não é possível imprimir DANFCE fiscal agora.';
		}

		$venda->estado = 'APROVADO';
		if (empty($venda->path_xml) && !empty($venda->chave)) {
			$venda->path_xml = $venda->chave . '.xml';
		}
		$venda->save();

		$xmlProcessado = trim((string) ($ret['xml'] ?? ''));
		if ($xmlProcessado !== '') {
			$this->persistirXmlNfce($public, (int) $venda->id, (string) $venda->chave, $xmlProcessado);
		}

		return null;
	}

	private function xmlPossuiProtocolo(?string $xmlPath): bool
	{
		if ($xmlPath === null || !file_exists($xmlPath)) {
			return false;
		}

		$xml = @file_get_contents($xmlPath);
		if ($xml === false || $xml === '') {
			return false;
		}

		return (stripos($xml, '<protNFe') !== false) || (stripos($xml, '<nfeProc') !== false);
	}

	private function getNfceProvider(): string
	{
		$provider = strtoupper(trim((string) $this->readEnvValue('NFCE_PROVIDER')));
		if($provider === ''){
			$provider = 'SEFAZ';
		}
		return $provider;
	}

	private function readEnvValue(string $key): string
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

		if ($value !== '') {
			return trim($value);
		}

		$envPath = base_path('.env');
		if (!is_file($envPath)) {
			return '';
		}

		$lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!is_array($lines)) {
			return '';
		}

		$prefix = $key . '=';
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}
			if (strpos($line, $prefix) !== 0) {
				continue;
			}
			$raw = trim(substr($line, strlen($prefix)));
			return trim($raw, "\"'");
		}

		return '';
	}

	private function enviarNfceComProvider(
		NFCeService $sefazService,
		ConfigNota $config,
		string $signedXml,
		string $xmlGerado,
		string $chaveGerada,
		string $referencia
	): string
	{
		$provider = $this->getNfceProvider();
		if($provider !== 'NUVEM_FISCAL'){
			return $sefazService->transmitirNfce($signedXml, $chaveGerada);
		}

		$nuvem = new NuvemFiscalNfceService();
		$res = $nuvem->emitirPorXml($xmlGerado, (int)$config->ambiente, $referencia);

		if(empty($res['ok'])){
			return 'Erro: ' . ($res['message'] ?? 'Falha ao enviar NFC-e para Nuvem Fiscal');
		}

		$status = strtolower((string)($res['status'] ?? ''));
		$raw = $res['raw'] ?? [];
		if(!empty($res['autorizada'])){
			$protocolo = (string)($res['protocolo'] ?? $res['id'] ?? 'autorizada');
			return 'NuvemAprovado:' . $protocolo;
		}

		if (in_array($status, ['rejeitado', 'erro', 'denegado'], true)) {
			$detalhes = $this->detalhesRejeicaoNuvemFiscal($raw, $status);
			return 'Erro: NFC-e rejeitada na Nuvem Fiscal - ' . $detalhes;
		}

		$id = (string)($res['id'] ?? '');
		return 'NuvemPendente:' . $id;
	}

	private function detalhesRejeicaoNuvemFiscal(array $raw, string $status): string
	{
		$codigoStatus = $this->valorNuvemFiscal($raw, [
			'autorizacao.codigo_status',
			'autorizacao.cStat',
			'autorizacao.codigo',
			'codigo_status',
			'cStat',
			'codigo',
			'erro.codigo_status',
			'erro.codigo',
		]);
		$motivoStatus = $this->valorNuvemFiscal($raw, [
			'autorizacao.motivo_status',
			'autorizacao.xMotivo',
			'autorizacao.motivo',
			'motivo_status',
			'xMotivo',
			'motivo',
			'erro.motivo_status',
			'erro.motivo',
		]);
		$mensagem = $this->valorNuvemFiscal($raw, [
			'autorizacao.mensagem',
			'mensagem',
			'message',
			'erro.mensagem',
			'erro.message',
			'erro',
		]);

		$detalhes = trim($codigoStatus . ' ' . $motivoStatus);
		if ($detalhes === '') {
			$detalhes = trim($mensagem);
		}
		if ($detalhes === '') {
			$detalhes = 'status: ' . $status;
		}

		return $detalhes;
	}

	private function valorNuvemFiscal(array $raw, array $paths): string
	{
		foreach ($paths as $path) {
			$value = $raw;
			foreach (explode('.', $path) as $part) {
				if (!is_array($value) || !array_key_exists($part, $value)) {
					$value = null;
					break;
				}
				$value = $value[$part];
			}

			if (is_scalar($value)) {
				$value = trim((string) $value);
				if ($value !== '') {
					return $value;
				}
			}
		}

		return '';
	}


}
