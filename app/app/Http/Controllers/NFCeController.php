<?php

namespace App\Http\Controllers;

use NFePHP\NFe\Common\Standardize;
use Illuminate\Http\Request;
use App\Models\VendaCaixa;
use App\Models\Enums\EstadoVenda;
use App\Models\Venda;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\NFe\Cupom;
use NFePHP\DA\Legacy\FilesFolders;
use App\Models\ConfigNota;
use App\Helpers\StockMove;
use App\Services\NFCeService;
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

		$vendaId = $request->vendaId;
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

             if ($venda->NFcNumero == 0){

				$vendaLast = VendaCaixa::lastNFCe();
          		$lastNumero = $vendaLast;

			     $venda->NFcNumero = (int)$lastNumero+1;

			     $venda->save();

			 }
			 else if ($venda->estado == 'DISPONIVEL'){

				$vendaLast = VendaCaixa::lastNFCe();
          		$lastNumero = $vendaLast;

			     $venda->NFcNumero = (int)$lastNumero+1;

			     $venda->save();

			 }



			$nfce = $nfe_service->gerarNFCe($vendaId);

			if(!isset($nfce['erros_xml'])){
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$signed = $nfe_service->sign($nfce['xml']);

				file_put_contents($public.'xml_nfce/'.$venda->id.'.xml',$signed);

				$resultado = $nfe_service->transmitirNfce($signed, $nfce['chave']);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->chave = $nfce['chave'];
					$venda->path_xml = $nfce['chave'] . '.xml';
					$venda->estado = 'APROVADO';

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

             if ($venda->NFcNumero == 0){

				$vendaLast = VendaCaixa::lastNFCe();
          		$lastNumero = $vendaLast;

			     $venda->NFcNumero = (int)$lastNumero+1;

			     $venda->save();

			 }
			 else if ($venda->estado == 'DISPONIVEL'){

				$vendaLast = VendaCaixa::lastNFCe();
          		$lastNumero = $vendaLast;

			     $venda->NFcNumero = (int)$lastNumero+1;

			     $venda->save();

			 }



			$nfce = $nfe_service->gerarNFCe($vendaId);

			if(!isset($nfce['erros_xml'])){
				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				$signed = $nfe_service->sign($nfce['xml']);
			    file_put_contents($public.'xml_nfce/'.$venda->id.'.xml',$signed);



				$resultado = $nfe_service->transmitirNfce($signed, $nfce['chave']);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->chave = $nfce['chave'];
					$venda->path_xml = $nfce['chave'] . '.xml';
					$venda->estado = 'APROVADO';

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
				$signed = $nfe_service->sign($nfce['xml']);
			    file_put_contents($public.'xml_nfce/'.$venda->id.'.xml',$signed);
				$resultado = $nfe_service->transmitirNfce($signed, $nfce['chave']);

				if(substr($resultado, 0, 4) != 'Erro'){
					$venda->chave = $nfce['chave'];
					$venda->path_xml = $nfce['chave'] . '.xml';
					$venda->estado = 'APROVADO';

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
		if(file_exists($public.'xml_nfce/'.$venda->chave.'.xml')){
			try {
				$xml = file_get_contents($public.'xml_nfce/'.$venda->chave.'.xml');
				$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));


				$danfce = new Danfce($xml);
				// $danfce->monta($logo);
				$pdf = $danfce->render($logo);

			// header('Content-Type: application/pdf');
			// echo $pdf;

		     	$cupom = new Cupom($venda,$logo);
			    $cupom->monta();
			    $pdf1 = $cupom->render();

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
		if(file_exists($public.'xml_nfce/'.$venda->chave.'.xml')){
			try {
				$xml = file_get_contents($public.'xml_nfce/'.$venda->chave.'.xml');
				$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));


				$danfce = new Danfce($xml);
				// $danfce->monta($logo);
				$pdf = $danfce->render($logo);

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

			return response()->download($public.'xml_nfce/'.$venda->chave.'.xml');
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

		$cupom = new Cupom($venda, $pathLogo);
		$cupom->monta();
		$pdf = $cupom->render();

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

			$cupom = new Cupom($venda, $pathLogo);
			$cupom->monta();
			$pdf = $cupom->render();
		}else{
			$xml = file_get_contents($public.'xml_nfce/'.$venda->chave.'.xml');
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));


			$danfce = new Danfce($xml);
			// $danfce->monta($logo);
			$pdf = $danfce->render($logo);
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

		$cupom = new Cupom($venda, $pathLogo);
		$cupom->monta();
		$pdf = $cupom->render();

		// header('Content-Type: application/pdf');
		// echo $pdf;
		return response($pdf)
		->header('Content-Type', 'application/pdf');
	}

	public function cancelar(Request $request){

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
		if($nfce['retEvento']['infEvento']['cStat'] == 135){
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
		if($nfce['retEvento']['infEvento']['cStat'] == 135){
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
		if($nfce['retEvento']['infEvento']['cStat'] == 135){
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
    private function diasDatas($data_inicial,$data_final) {
        $diferenca = strtotime($data_final) - strtotime($data_inicial);
        $dias = floor($diferenca / (60 * 60 * 24));
        return $dias;
    }

    public function reprocessamento(Request $request){
        try{
            $dataInicial = $request->dataIni;
            $dataFinal = $request->dataFim;

            if (($dataInicial == null) || ($dataFinal== null)){
                return response()->json("Preencha a Data Inicial e Final para reprocessar!", 401);
            }elseif ($this->parseDate($dataInicial) > $this->parseDate($dataFinal, true)){
                return response()->json("Data Final Maior que Data Inicial!", 401);
            }elseif($this->diasDatas(
                $this->parseDate($dataInicial) , $this->parseDate($dataFinal, true)
                )  > 60){
                return response()->json("O intervalo Data Inicial e Final grande (Limite: 60 dias)!", 401);
            }

            $vendas = VendaCaixa::
                        where('ativo',true)
                        ->where('estado','REJEITADO')
                        ->orderBy('id', 'desc')
                        ->whereBetween('created_at', [$this->parseDate($dataInicial),
                                                        $this->parseDate($dataFinal, true)
                                                        ])
                        ->get();

            foreach($vendas as $v){
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

                $c = $nfe_service->consultarNFCeRetXML($venda);
                $st = new Standardize();
    			$std = $st->toStd($c);

    			if ($std->cStat != 100 ) {
                    return "Erro";
                }

                if ($bChaveAcessoNula == true){
                    if (($venda->path_xml == null) || empty($venda->path_xml) ){
                        $venda->path_xml = $venda->chave . '.xml';
                    }
                    $venda->save();
                }
                $nfe_service->retornaXMLAssinado($xmlLocal->asXML(),$c, $venda->chave);
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
		}catch(\Exception $r){
			return response()->json($e->getMessage(), 401);

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


}
