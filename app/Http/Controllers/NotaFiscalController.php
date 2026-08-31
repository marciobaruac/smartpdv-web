<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfigNota;
use App\Services\NFService;
use App\Services\NFCeService;
use App\Services\NuvemFiscalNfceService;

use App\Models\Venda;
use App\Models\ContaReceber;
use App\Models\Certificado;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\DA\NFe\Daevento;
use App\Models\NotaComplementar;
use App\Models\Cidade;
use App\Models\Cliente;
use App\Models\Tributacao;
use App\Models\NfceInutilizacao;
use App\Models\NaturezaOperacao;

use App\Models\Transportadora;
use App\Models\Produto;
use Mail;

use Illuminate\Support\Facades\DB;

use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use NFePHP\POS\DanfcePos;
use NFePHP\NFe\Factories\Contingency;

class NotaFiscalController extends Controller
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

	public function gerarNf(Request $request){

		$vendaId = $request->vendaId;
		$venda = Venda::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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

			   DB::beginTransaction();
			   $config = ConfigNota::first();
			    //$venda->NfNumero = $config->ultimo_numero_nfe;
             //  $venda->save();

            if ($venda->NfNumero == 0) {

				$venda->NfNumero = $config->ultimo_numero_nfe+1;
				$venda->save();
				$config->ultimo_numero_nfe =  $config->ultimo_numero_nfe+1;
				$config->save();





			}

			$contingency = new Contingency();



			$nfe = $nfe_service->gerarNFe($vendaId,$venda->NfNumero);



			$venda->chave = $nfe['chave'];
			$venda->path_xml = $nfe['chave'] . '.xml';
		    $venda->save();

			$acronym = 'MT';
			$motive = 'SEFAZ fora do AR';
			$type = 'SVCAN';

			$status = $contingency->activate($acronym, $motive, $type);

			if(!isset($nfe['erros_xml'])){
			  //   file_put_contents('xml_nfe/teste2.xml', $nfe['xml']);
			  //   return response()->json($nfe, 200);
			  //   die();
				$signed = $nfe_service->sign($nfe['xml']);
				$resultado = $nfe_service->transmitir($signed, $nfe['chave']);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->estado = 'APROVADO';
					$venda->save();


				}else{

					$venda->estado = 'REJEITADO';
					$venda->save();
				}
				echo json_encode($resultado);
			}else{
				return response()->json($nfe['erros_xml'], 401);
			}

		}else{
			echo json_encode("Apro");
		}
		DB::commit();

	}

	public function testeGerar( $id){

		$vendaId = $id;
		$venda = Venda::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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

			   DB::beginTransaction();
			   $config = ConfigNota::first();
			    //$venda->NfNumero = $config->ultimo_numero_nfe;
             //  $venda->save();

            if ($venda->NfNumero == 0) {

				$venda->NfNumero = $config->ultimo_numero_nfe+1;
				$venda->save();
				$config->ultimo_numero_nfe =  $config->ultimo_numero_nfe+1;
				$config->save();





			}





			$nfe = $nfe_service->gerarNFe($vendaId,$venda->NfNumero);



			$venda->chave = $nfe['chave'];
			$venda->path_xml = $nfe['chave'] . '.xml';
		    $venda->save();



			//print_r($status);
			if(!isset($nfe['erros_xml'])){
			  //   file_put_contents('xml_nfe/teste2.xml', $nfe['xml']);
			  //   return response()->json($nfe, 200);
			  //   die();
				$signed = $nfe_service->sign($nfe['xml']);
				$resultado = $nfe_service->transmitir($signed, $nfe['chave']);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->estado = 'APROVADO';
					$venda->save();


				}else{

					$venda->estado = 'REJEITADO';
					$venda->save();
				}
				echo json_encode($resultado);
			}else{
				return response()->json($nfe['erros_xml'], 401);
			}

		}else{
			echo json_encode("Apro");
		}
		DB::commit();

	}


	public function xmlTemp($id){

		$vendaId = $id;
		$venda = Venda::
		where('id', $vendaId)
		->first();

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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


		$nfe = $nfe_service->gerarNFe($vendaId,1);
		if(!isset($nfe['erros_xml'])){
			// file_put_contents('xml/teste2.xml', $nfe['xml']);
			// return response()->json($nfe, 200);

			return response($nfe['xml'])
			->header('Content-Type', 'application/xml');
		}else{
			return response()->json($nfe['erros_xml'], 401);
		}



	}

	public function inutilizar(Request $request){

		try{
			$config = ConfigNota::first();

			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);

			$nfe_service = new NFService([
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


			$result = $nfe_service->inutilizar($config, $request->nInicio, $request->nFinal,
				$request->justificativa, $request->serie);
			// return response()->json($result, 200);
			echo json_encode($result);
		}catch(\Exception $e){
			return response()->json($e->getMessage(), 401);

		}
	}

    public function inutilizarnfce(Request $request){

		try{
			$config = ConfigNota::first();
			if($config == null){
				return response()->json('Configuração fiscal não encontrada.', 401);
			}

			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);

			if($this->getNfceProvider() === 'NUVEM_FISCAL'){
				$nuvem = new NuvemFiscalNfceService();
				$result = $nuvem->inutilizarNumeracao(
					(int) $config->ambiente,
					(int) ($request->serie ?: $config->numero_serie_nfce),
					(int) $request->nInicio,
					(int) $request->nFinal,
					(string) $request->justificativa,
					$cnpj,
					(int) ($request->ano ?: date('Y'))
				);

				$cStat = !empty($result['inutilizada']) ? '102' : (string) ($result['codigo_status'] ?? '0');
				$xMotivo = (string) ($result['motivo_status'] ?? $result['message'] ?? 'Retorno da Nuvem Fiscal');

				if ($cStat === '102') {
					NfceInutilizacao::create([
						'serie'         => (int) ($request->serie ?: $config->numero_serie_nfce),
						'numero_inicio' => (int) $request->nInicio,
						'numero_final'  => (int) $request->nFinal,
						'ano'           => (int) ($request->ano ?: date('Y')),
						'justificativa' => (string) $request->justificativa,
						'protocolo'     => $result['protocolo'] ?? null,
						'status'        => 'inutilizado',
					]);
				}

				return response()->json([
					'infInut' => [
						'cStat' => $cStat,
						'xMotivo' => $xMotivo,
						'nProt' => $result['protocolo'] ?? null,
					],
					'nuvem_fiscal' => $result,
				], empty($result['ok']) ? 401 : 200);
			}

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


			$result = $nfe_service->inutilizar($config, $request->nInicio, $request->nFinal,
				$request->justificativa);
			// return response()->json($result, 200);
			echo json_encode($result);
		}catch(\Exception $e){
			return response()->json($e->getMessage(), 401);

		}
	}

	private function getNfceProvider(): string
	{
		$provider = '';

		if (function_exists('env')) {
			$value = env('NFCE_PROVIDER');
			if ($value !== null && $value !== false) {
				$provider = (string) $value;
			}
		}

		if ($provider === '') {
			$value = getenv('NFCE_PROVIDER');
			if ($value !== false && $value !== null) {
				$provider = (string) $value;
			}
		}

		if ($provider === '' && isset($_ENV['NFCE_PROVIDER'])) {
			$provider = (string) $_ENV['NFCE_PROVIDER'];
		}

		if ($provider === '' && isset($_SERVER['NFCE_PROVIDER'])) {
			$provider = (string) $_SERVER['NFCE_PROVIDER'];
		}

		return strtoupper(trim($provider)) ?: 'SEFAZ';
	}


	public function consultaCadastro(Request $request){

		$config = ConfigNota::first();
		$certificado = Certificado::first();

		if($config == null || $certificado == null){
			return response()->json("Configure o emitente para buscar", 403);
		}

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		try{
			$nfe_service = new NFService([
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
			$cnpj = $request->cnpj;
			$uf = $request->uf;
			$nfe_service->consultaCadastro($cnpj, $uf);
		}catch(\Exception $e){
			return response()->json($e->getMessage(), 401);
		}
	}

	public function imprimir($id){
		$venda = Venda::
		where('id', $id)
		->first();

		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		if(file_exists($public.'xml_nfe/'.$venda->chave.'.xml')){
			$xml = file_get_contents($public.'xml_nfe/'.$venda->chave.'.xml');
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));

			try {
				$danfe = new Danfe($xml);

				$pdf = $danfe->render($logo);
			// header('Content-Type: application/pdf');
			// echo $pdf;
				return response($pdf)
				->header('Content-Type', 'application/pdf');
			} catch (InvalidArgumentException $e) {
				echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
			}
		}else{
			echo "Arquivo XML não encontrado!!";
		}
	}

	public function renderizarXmlcomplementar($id){
		$venda = Venda::
		where('id', $id)
		->first();





		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		if(file_exists($public.'xml_nfe/'.$venda->chave.'.xml')){
			$xml = file_get_contents($public.'xml_nfe/'.$venda->chave.'.xml');
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));

			$arquivo =$xml;
			//$xml = simplexml_load_file($xml);
		    $xml = simplexml_load_file($public.'xml_nfe/'.$venda->chave.'.xml');
			if($this->validaChave($xml->NFe->infNFe->attributes()->Id)){


				$cidade = Cidade::getCidadeCod($xml->NFe->infNFe->emit->enderEmit->cMun);
				$dadosEmitente = [
					'cpf' => $xml->NFe->infNFe->emit->CPF,
					'cnpj' => $xml->NFe->infNFe->emit->CNPJ,
					'razaoSocial' => $xml->NFe->infNFe->emit->xNome,
					'nomeFantasia' => $xml->NFe->infNFe->emit->xFant,
					'logradouro' => $xml->NFe->infNFe->emit->enderEmit->xLgr,
					'numero' => $xml->NFe->infNFe->emit->enderEmit->nro,
					'bairro' => $xml->NFe->infNFe->emit->enderEmit->xBairro,
					'cep' => $xml->NFe->infNFe->emit->enderEmit->CEP,
					'fone' => $xml->NFe->infNFe->emit->enderEmit->fone,
					'ie' => $xml->NFe->infNFe->emit->IE,
					'cidade_id' => $cidade->id
				];

				$vFrete = number_format((double) $xml->NFe->infNFe->total->ICMSTot->vFrete,
					2, ",", ".");
				$vDesc = number_format((double) $xml->NFe->infNFe->total->ICMSTot->vDesc, 2, ",", ".");

				$idCliente = 0;
				$clienteEncontrado = $this->verificaCliente($xml->NFe->infNFe->dest->CNPJ);
				$dadosAtualizados = [];
				if($clienteEncontrado){
					$idCliente = $clienteEncontrado->id;
					//$dadosAtualizados = $this->verificaAtualizacao($fornecedorEncontrado, $dadosEmitente);
				}else{

					array_push($dadosAtualizados, "Fornecedor cadastrado com sucesso");
					$idFornecedor = $this->cadastrarFornecedor($dadosEmitente);
				}


				$seq = 0;
				$itens = [];
				$contSemRegistro = 0;
				$config = ConfigNota::first();
				$transportadoras = Transportadora::all();

				$tributacao = Tributacao::first();

				foreach($xml->NFe->infNFe->det as $item) {

				     //echo "<pre>";
			        // print_r($item->prod->cProd);
			        // echo "</pre>";
			         //die();
					 $produto = Produto::verificaCadastrado($item->prod->cProd,
					 $item->prod->xProd, $item->prod->cProd,$config->consultaprodutoentrada);
			       // echo "<pre>";
			         //print_r($item->prod->NCM);
					// print_r($ncm);

			        // echo "</pre>";
			         //die();
						$trib = NotaComplementar::getTrib($item->imposto);

					if ($produto->CST_CSOSN == '500'){

						$cst_csosn = $produto->CST_CSOSN;
					}
					else{

						$cst_csosn = '101';

					}

					$item = [
						'idproduto' =>$produto->id,
						'codigo' => $item->prod->cProd,

						'xProd' => $item->prod->xProd,

						'NCM' => $produto->NCM,
					//	'NCM' => $item->prod->NCM,

						'CFOP' => $produto->CFOP_saida_estadual,

					    'uCom' => $item->prod->uCom,
						'vUnCom' => $item->prod->vUnCom,
						'qCom' => $item->prod->qCom,

						'codBarras' => $item->prod->cEAN,

						'cst_csosn' => $produto->CST_CSOSN,
						'cst_pis' => $produto->CST_PIS,
						'cst_cofins' => $produto->CST_COFINS,
						'cst_ipi' => $produto->CST_IPI,
						'perc_icms' => $produto->perc_icms,
						'perc_pis' =>  $produto->perc_pis ,
						'perc_cofins' => $produto->perc_cofins ,
						'perc_ipi' => $produto->perc_ipi,
						'pRedBC' => $produto->pRedBC
					];

					array_push($itens, $item);

				}

				//echo "<pre>";
			  //  print_r($itens);


			   //  echo "</pre>";
			  //  die();

				$chave = substr($xml->NFe->infNFe->attributes()->Id, 3, 44);
				$dadosNf = [
					'chave' => $chave,
					'vProd' => $xml->NFe->infNFe->total->ICMSTot->vProd,
					'indPag' => $xml->NFe->infNFe->ide->indPag,
					'nNf' => $xml->NFe->infNFe->ide->nNF,
					'vFrete' => $vFrete,
					'vDesc' => $vDesc,
				];


			//Pagamento
				$fatura = [];
				if (!empty($xml->NFe->infNFe->cobr->dup))
				{
					foreach($xml->NFe->infNFe->cobr->dup as $dup) {
						$titulo = $dup->nDup;
						$vencimento = $dup->dVenc;
						$vencimento = explode('-', $vencimento);
						$vencimento = $vencimento[2]."/".$vencimento[1]."/".$vencimento[0];
						$vlr_parcela = number_format((double) $dup->vDup, 2, ",", ".");

						$parcela = [
							'numero' => $titulo,
							'vencimento' => $vencimento,
							'valor_parcela' => $vlr_parcela
						];
						array_push($fatura, $parcela);
					}
				}

			//upload
			//	$file = $request->file;

			   // $file =  file_get_contents($public.'xml_nfe/'.$venda->chave.'.xml');
			   // $nameArchive = $chave . ".xml" ;

				//$pathXml = $file->move(public_path('xml_devolucao_entrada'), $nameArchive);

            //fim upload

				$naturezas = NaturezaOperacao::all();

				return view('notacomplementar/visualizaNota')
				->with('title', 'Nota Complementar de Impostos')
				->with('itens', $itens)
				->with('fatura', $fatura)
				->with('notacomplementarJs', true)
			//	->with('pathXml', $nameArchive)
				->with('idFornecedor', $idCliente )
				->with('dadosNf', $dadosNf)
				->with('naturezas', $naturezas)
				->with('config', $config)
				->with('dadosEmitente', $dadosEmitente)
				->with('dadosAtualizados', $dadosAtualizados)
				->with('transportadoras', $transportadoras);

			}else{

				session()->flash('mensagem_erro', 'Este XML, já foi realizado nota complementar!');
				return redirect("/notacomplementar/nova");
			}
		//	try {
			//	$danfe = new Danfe($xml);

			//	$pdf = $danfe->render($logo);
			// header('Content-Type: application/pdf');
			// echo $pdf;
		//		return response($pdf)
			//	->header('Content-Type', 'application/pdf');
		//	} catch (InvalidArgumentException $e) {
			//	echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		//	}
		}else{
			echo "Arquivo XML não encontrado!!";
		}

	}

	private function validaChave($chave){
		$chave = substr($chave, 3, 44);
		$cp = NotaComplementar::
		where('chave_nf_origem', $chave)
		->first();
		return $cp == null ? true : false;
	}


	public function escpos($id){
		$venda = Venda::
		where('id', $id)
		->first();

		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';

		$xml = file_get_contents($public.'xml_nfe/'.$venda->chave.'.xml');
		$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));
		// $docxml = FilesFolders::readFile($xml);
		$connector = new NetworkPrintConnector('127.0.0.1', 9100);
		$danfcepos = new DanfcePos($connector);

	}

	public function imprimirCce($id){
		$venda = Venda::
		where('id', $id)
		->first();

		if($venda->sequencia_cce > 0){

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			if(file_exists($public.'xml_nfe_correcao/'.$venda->chave.'.xml')){
				$xml = file_get_contents($public.'xml_nfe_correcao/'.$venda->chave.'.xml');
				$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));

				$dadosEmitente = $this->getEmitente();

				try {
					$daevento = new Daevento($xml, $dadosEmitente);
					$daevento->debugMode(true);
					$pdf = $daevento->render($logo);
				// header('Content-Type: application/pdf');
				// echo $pdf;
					return response($pdf)
					->header('Content-Type', 'application/pdf');
				} catch (InvalidArgumentException $e) {
					echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
				}
			}else{
				echo "Arquivo XML não encontrado!!";
			}
		}else{
			echo "<center><h1>Este documento não possui evento de correção!<h1></center>";
		}
	}

	public function imprimirCancela($id){
		$venda = Venda::
		where('id', $id)
		->first();

		if($venda->estado == 'CANCELADO'){
			try {
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				if(file_exists($public.'xml_nfe_cancelada/'.$venda->chave.'.xml')){
					$xml = file_get_contents($public.'xml_nfe_cancelada/'.$venda->chave.'.xml');

					$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));

					$dadosEmitente = $this->getEmitente();

					$daevento = new Daevento($xml, $dadosEmitente);
					$daevento->debugMode(true);
					$pdf = $daevento->render($logo);
				// header('Content-Type: application/pdf');
				// echo $pdf;
					return response($pdf)
					->header('Content-Type', 'application/pdf');
				}else{
					echo "Arquivo XML não encontrado!!";
				}
			} catch (InvalidArgumentException $e) {
				echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
			}
		}else{
			echo "<center><h1>Este documento não possui evento de cancelamento!<h1></center>";
		}
	}

	private function getEmitente(){
		$config = ConfigNota::first();
		return [
			'razao' => $config->razao_social,
			'logradouro' => $config->logradouro,
			'numero' => $config->numero,
			'complemento' => '',
			'bairro' => $config->bairro,
			'CEP' => $config->cep,
			'municipio' => $config->municipio,
			'UF' => $config->UF,
			'telefone' => $config->telefone,
			'email' => ''
		];
	}

	public function cancelar(Request $request){

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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


		$nfe = $nfe_service->cancelar($request->id, $request->justificativa);



		if(!isset($nfe['erro'])){

			$venda = Venda::
			where('id', $request->id)
			->first();
			$venda->estado = 'CANCELADO';
			$venda->save();

			$this->removerDuplicadas($venda);
			return response()->json($nfe, 200);

		}else{
			return response()->json($nfe['data'], $nfe['status']);
		}

	}

	public function cancelarxml($id){

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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


		$id = 198;

		$nfe = $nfe_service->cancelar($id, 'DIGITADO O PRECO DE VENDA ERRADO');

	     echo "<pre>";
	    print_r($nfe);
		 echo "</pre>";

		 die();

		if(!isset($nfe['erro'])){

			$venda = Venda::
			where('id', $request->id)
			->first();
			$venda->estado = 'CANCELADO';
			$venda->save();

			$this->removerDuplicadas($venda);
			return response()->json($nfe, 200);

		}else{
			return response()->json($nfe['data'], $nfe['status']);
		}

	}

	private function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	private function removerDuplicadas($venda){
		foreach($venda->duplicatas as $dp){
			$c = ContaReceber::
			where('id', $dp->id)
			->delete();
		}
	}

	public function cartaCorrecao(Request $request){

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
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


		$nfe = $nfe_service->cartaCorrecao($request->id, $request->correcao);
		echo json_encode($nfe);
	}


	public function consultar(Request $request){
		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);
		$nfe_service = new NFService([
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
		$c = $nfe_service->consultar($request->id);
		echo json_encode($c);
	}

	public function consultar_cliente($id){
		$venda = Venda::
		where('id', $id)
		->first();
		echo json_encode($venda->cliente);
	}

	public function enviarXml(Request $request){
		$email = $request->email;
		$id = $request->id;
		$venda = Venda::
		where('id', $id)
		->first();
		$this->criarPdfParaEnvio($venda);
		$value = session('user_logged');
		Mail::send('mail.xml_send', ['emissao' => $venda->data_registro, 'nf' => $venda->NfNumero,
			'valor' => $venda->valor_total, 'usuario' => $value['nome']], function($m) use ($venda, $email){

				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$nomeEmpresa = getenv('MAIL_NAME');
				$nomeEmpresa = str_replace("_", " ",  $nomeEmpresa);
				$nomeEmpresa = str_replace("_", " ",  $nomeEmpresa);
				$emailEnvio = getenv('MAIL_USERNAME');

				$m->from($emailEnvio, $nomeEmpresa);
				$m->subject('Envio de XML NF ' . $venda->NfNumero);

				$m->attach($public.'xml_nfe/'.$venda->chave.'.xml');
				$m->attach($public.'pdf/DANFE.pdf');
				$m->to($email);
			});
		return "ok";
	}

	private function criarPdfParaEnvio($venda){
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$xml = file_get_contents($public.'xml_nfe/'.$venda->chave.'.xml');
		$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));
		// $docxml = FilesFolders::readFile($xml);

		try {
			$danfe = new Danfe($xml);
			// $id = $danfe->monta($logo);
			$pdf = $danfe->render($logo);
			header('Content-Type: application/pdf');
			file_put_contents($public.'pdf/DANFE.pdf',$pdf);
		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}
	}

	private function verificaCliente($cnpj){
		$forn = Cliente::verificaCadastrado($this->formataCnpj($cnpj));
		return $forn;
	}

	private function formataCnpj($cnpj){
		$temp = substr($cnpj, 0, 2);
		$temp .= ".".substr($cnpj, 2, 3);
		$temp .= ".".substr($cnpj, 5, 3);
		$temp .= "/".substr($cnpj, 8, 4);
		$temp .= "-".substr($cnpj, 12, 2);
		return $temp;
	}

	public function atualizarnfe(Request $request){







			$venda = Venda::
			where('id', $request->id)
			->first();
			$venda->observacao = $request->infnfe;
			$venda->save();

			return response()->json($venda, 200);


	}


}
