<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TEFParametros;
use App\Models\TransacaoTEF;
use App\Services\CurlTEF;
use Illuminate\Support\Facades\Log;
use DB;

class TEFController extends Controller
{
	public function __construct(){

	}

	public function postTransacaoTEF(Request $request){
		$parametrosTEF 				= TEFParametros::get();

		// ID auto increment da tabela transacao_tef
		$referencia 				= 0;

        if(count($parametrosTEF) > 0){

            $dataTest                               = $parametrosTEF[0];

            $curlUpdate                             = new CurlTEF($dataTest['url'] . '/Venda/Vender/?key='.trim($dataTest['chave_integracao']));

            $terminalID                             = $request->TERMINAL_ID ?: getenv('TEF_TERMINAL_PADRAO') ?: 24221;

            $arr['formaPagamentoId']                = $request->FORMA_PAGAMENTO_ID;
            $arr['terminalId']                      = $terminalID;
            $arr['referencia']                      = $referencia;
            $arr['aguardarTefIniciarTransacao']     = true;
            $arr['parcelamentoAdmin']               = false;
            // se for débito, passar parcelas = 1

            $qtdParcelas = 1;
            if($request->FORMA_PAGAMENTO_ID == 21 && $request->PARCELAS != 0){
                $qtdParcelas = $request->PARCELAS;
            }
            $arr['quantidadeParcelas']              = $qtdParcelas;

            if($request->FORMA_PAGAMENTO_ID == getenv('TEF_FORMA_PAGAMENTO_PIX') && getenv('TEF_ADQUIRENTE_PIX')){
                $arr['adquirente']                  = getenv('TEF_ADQUIRENTE_PIX');
            }



            $valorTotal = $request->VALOR_TOTAL;

            // if(strpos($dataTest['url'], 'sandbox') !== false){
                // $valorTotal = $request->VALOR_TOTAL;
                $valorTotal               = number_format($request->VALOR_TOTAL, 2, ',', '');
                // $valorTotal               = number_format($valorTotal, 2, ',', '');
            // }

            // dd($valorTotal);

            $arr['valorTotalVendido']               = $valorTotal;

            Log::info('TEF Venda/Vender request', [
                'url' => $dataTest['url'] . '/Venda/Vender/',
                'payload' => $arr
            ]);

            $execute                                = $curlUpdate->executeCurl($arr);
            Log::info('TEF Venda/Vender response', [
                'response' => $execute
            ]);
            $response                               = json_decode($execute);

            if(!isset($response->message)){

                // Inserir transação no banco de dados na transacao_tef

				$formaPagamento = 0;

                $valorTotal     = 0;
                $parcelas       = 0;
				$clienteID      = 0;
				$clienteNome	= '';
				$terminalID		= $request->TERMINAL_ID ?: getenv('TEF_TERMINAL_PADRAO') ?: 24221;

                if(isset($request->FORMA_PAGAMENTO_ID)){
                    $formaPagamento = $request->FORMA_PAGAMENTO_ID;
                }

                if(isset($request->VALOR_TOTAL)){
                    $valorTotal = $request->VALOR_TOTAL;
                }

                if(isset($request->PARCELAS)){
                    $parcelas = $qtdParcelas;
                }

				$createResposta = TransacaoTEF::create([
					'id_company' 			=> 0,
					'intencao_venda_id' 	=> $response->intencaoVenda->id,
					'forma_pagamento_tef' 	=> $formaPagamento,
					'terminal_id' 			=> $terminalID,
					'cliente_id' 			=> $clienteID,
					'data_transacao' 		=> date('Y-m-d', strtotime('now')),
					'nome' 					=> $clienteNome,
					'valor' 				=> $valorTotal,
					'parcelas' 				=> $parcelas,
					'situacao' 				=> 0,
					'status' 				=> 0
				]);

                // log_info('TEF - INTENÇÃO DE VENDA ' . $response->intencaoVenda->id . ' CRIADA - VR. ' . $param->VALOR_TOTAL . ' PARCELAS: ' . $param->PARCELAS . ' REF: ' . $referencia);
            }else{
                // Erro ao criar transação TEF
                // mensagem do erro $response->message
            }

            echo json_encode($response);
            exit;

        }else{
            // Parâmetros do TEF não encontrados;
        }

	}

    public function getIntencaoVendaTEF(Request $request){

        $parametrosTEF 							= TEFParametros::get();

        $dataTest                               = $parametrosTEF[0];

        $intencaoTEF                            = $request->INTENCAO_VENDA_ID;
        $intencaoVendaId                        = $request->INTENCAO_VENDA_ID;

        $curlUpdate                             = new CurlTEF($dataTest['url'] . '/IntencaoVenda/GetByFiltros?key='.trim($dataTest['chave_integracao']));

        $arr['intencaoVendaId']                 = $intencaoTEF;
        $arr['dataFim']                         = '';
        $arr['dataInicio']                      = true;
        $arr['formaPagamentoId']                = true;
        $arr['quantidadeRegistros']             = 20;
        $arr['status']                          = '';

        Log::info('TEF IntencaoVenda/GetByFiltros request', [
            'url' => $dataTest['url'] . '/IntencaoVenda/GetByFiltros',
            'payload' => $arr
        ]);

        $execute                                = $curlUpdate->executeCurl($arr);
        Log::info('TEF IntencaoVenda/GetByFiltros response', [
            'response' => $execute
        ]);
        $response                               = json_decode($execute);

        if(isset($response->intencoesVendas)){

            if(count($response->intencoesVendas) > 0){
                $detIntencaoVenda = $response->intencoesVendas[0];

                if(isset($detIntencaoVenda->pagamentosExternos) && count($detIntencaoVenda->pagamentosExternos) > 0){

                    $detPagamento = $detIntencaoVenda->pagamentosExternos[0];

                   // print_r($response);exit;

                    $situacao = 0;

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 10){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Aprovada e recebida com sucesso");
                        $situacao = 1;
                    }

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 15){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Expirada");
                        $situacao = 2;
                    }

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 18){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Em processo de cancelamento");
                        $situacao = 3;
                    }

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 19){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Sistema de pagamento recebeu a solicitação de cancelamento");
                        $situacao = 4;
                    }

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 20){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Cancelamento concluído");
                        $situacao = 5;
                    }

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 25){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Pagamento não aprovado pela adquirente ou banco emissor");
                        $situacao = 6;
                    }

                    if($situacao > 0){

                        $bandeiraSubstr     = substr($detPagamento->bandeira, 0, 4);
                        $codigoBandeira     = $this->getBandeiraByBandeiraTEF($bandeiraSubstr);
                        $cnpj_adquirente    = $this->getCNPJByAdquirente($detPagamento->adquirente);

                        $updated = TransacaoTEF::where('intencao_venda_id', $intencaoVendaId)->update([
                            'situacao' => $situacao,
                            'adquirente' => $detPagamento->adquirente,
                            'cnpj_adquirente' => $cnpj_adquirente,
                            'codigo_autorizacao' => $detPagamento->autorizacao,
                            'codigo_adquirente' => $detPagamento->codigoRespostaAdquirente,
                            'nsu' => $detPagamento->nsuTid,
                            'id_pagamento' => $detPagamento->idPagamento,
                            'nome' => $detPagamento->nomeTitularCartao,
                            'mensagem_adquirente' => $detPagamento->mensagemRespostaAdquirente,
                            'bandeira' => $detPagamento->bandeira,
                            'codigo_bandeira' => $codigoBandeira,
                            'endtoendid' => $detPagamento->nsuTid
                        ]);
                    }
                }
            }

        }

        echo json_encode($response);
        exit;
    }

    public function getIntencaoVendaTEFCancelamento(Request $request){

        $parametrosTEF 							= TEFParametros::get();

        $dataTest                               = $parametrosTEF[0];

        $intencaoTEF                            = $request->INTENCAO_VENDA_ID;
        $intencaoVendaId                        = $request->INTENCAO_VENDA_ID;

        $curlUpdate                             = new CurlTEF($dataTest['url'] . '/IntencaoVenda/GetByFiltros?key='.trim($dataTest['chave_integracao']));

        $arr['intencaoVendaId']                 = $intencaoTEF;
        $arr['dataFim']                         = '';
        $arr['dataInicio']                      = true;
        $arr['formaPagamentoId']                = true;
        $arr['quantidadeRegistros']             = 20;
        $arr['status']                          = '';

        $execute                                = $curlUpdate->executeCurl($arr);
        $response                               = json_decode($execute);

        if(isset($response->intencoesVendas)){

            if(count($response->intencoesVendas) > 0){
                $detIntencaoVenda = $response->intencoesVendas[0];

                if(isset($detIntencaoVenda->pagamentosExternos) && count($detIntencaoVenda->pagamentosExternos) > 0){

                    $detPagamento   = $detIntencaoVenda->pagamentosExternos[0];

                    $situacao       = 0;

                    if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 20){
                        // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Cancelamento concluído");
                        $situacao = 5;
                    }

                    if($situacao > 0){
                        $updated = TransacaoTEF::where('intencao_venda_id', $intencaoTEF)->update([
                            'situacao' => $situacao
                        ]);
                    }
                }
            }

        }

        echo json_encode($response);
        exit;
    }

    public function postCancelarVenda(Request $request){

        $parametrosTEF 							= TEFParametros::get();

        $dataTest                               = $parametrosTEF[0];

		$intencaoTEF                            = $request->INTENCAO_VENDA_ID;

        $curlUpdate                             = new CurlTEF($dataTest['url'] . '/Venda/CancelarVenda?key='.trim($dataTest['chave_integracao']));

        $arr['intencaoVendaId']                 = $intencaoTEF;
        $arr['terminalId']                      = $request->TERMINAL_ID ?: getenv('TEF_TERMINAL_PADRAO') ?: 24221;
        $arr['aguardarTefIniciarTransacao']     = true;
        $arr['senhaTecnica']                    = $dataTest['senha_tecnica'];

        $execute                                = $curlUpdate->executeCurl($arr);
        $response                               = json_decode($execute);

        $updated = TransacaoTEF::where('intencao_venda_id', $intencaoTEF)->update([
            'situacao' => 3
        ]);


        echo json_encode($response);
        exit;

    }


    public function webhook(Request $request){

        if(isset($request->intencaoVendaId)){
            $intencaoVendaId                        = $request->intencaoVendaId;
            $parametrosTEF 							= TEFParametros::get();

            $dataTest                               = $parametrosTEF[0];

            $curlUpdate                             = new CurlTEF($dataTest['url'] . '/IntencaoVenda/GetByFiltros?key='.trim($dataTest['chave_integracao']));

            $arr['intencaoVendaId']                 = $intencaoVendaId;
            $arr['dataFim']                         = '';
            $arr['dataInicio']                      = true;
            $arr['formaPagamentoId']                = true;
            $arr['quantidadeRegistros']             = 20;
            $arr['status']                          = '';

            $execute                                = $curlUpdate->executeCurl($arr);
            $response                               = json_decode($execute);

            if(isset($response->intencoesVendas)){

                if(count($response->intencoesVendas) > 0){
                    $detIntencaoVenda = $response->intencoesVendas[0];

                    if(isset($detIntencaoVenda->pagamentosExternos) && count($detIntencaoVenda->pagamentosExternos) > 0){

                        $detPagamento = $detIntencaoVenda->pagamentosExternos[0];

                       // print_r($response);exit;

                        $situacao = 0;

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 10){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Aprovada e recebida com sucesso");
                            $situacao = 1;
                        }

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 15){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Expirada");
                            $situacao = 2;
                        }

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 18){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Em processo de cancelamento");
                            $situacao = 3;
                        }

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 19){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Sistema de pagamento recebeu a solicitação de cancelamento");
                            $situacao = 4;
                        }

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 20){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Cancelamento concluído");
                            $situacao = 5;
                        }

                        if(trim($detIntencaoVenda->intencaoVendaStatus->id) == 25){
                            // log_info("Webhook - Intenção de venda " . $intencaoVenda . " : Pagamento não aprovado pela adquirente ou banco emissor");
                            $situacao = 6;
                        }

                        if($situacao > 0){

                            $bandeiraSubstr     = substr($detPagamento->bandeira, 0, 4);
                            $codigoBandeira     = $this->getBandeiraByBandeiraTEF($bandeiraSubstr);
                            $cnpj_adquirente    = $this->getCNPJByAdquirente($detPagamento->adquirente);

                            $updated = TransacaoTEF::where('intencao_venda_id', $intencaoVendaId)->update([
                                'situacao' => $situacao,
                                'adquirente' => $detPagamento->adquirente,
                                'cnpj_adquirente' => $cnpj_adquirente,
                                'codigo_autorizacao' => $detPagamento->autorizacao,
                                'codigo_adquirente' => $detPagamento->codigoRespostaAdquirente,
                                'nsu' => $detPagamento->nsuTid,
                                'id_pagamento' => $detPagamento->idPagamento,
                                'nome' => $detPagamento->nomeTitularCartao,
                                'mensagem_adquirente' => $detPagamento->mensagemRespostaAdquirente,
                                'bandeira' => $detPagamento->bandeira,
                                'codigo_bandeira' => $codigoBandeira,
                                'endtoendid' => $detPagamento->nsuTid
                            ]);


                        }
                    }
                }
            }

            exit;
        }

    }

    public function postImpressaoSegundaVia(Request $request){
        $dadosTEF = TransacaoTEF::where('intencao_venda_id', '=', $request->INTENCAO_VENDA_ID)->get();

        $response = $dadosTEF[0];

        echo json_encode($response);
        exit;

    }

    public function listagem(){

        $tefs = TransacaoTEF::filtroData(
            $this->parseDate(date("Y-m-d")),
            $this->parseDate(date("Y-m-d"), true)
        );
        $dataInicial =  date('d/m/Y');
        $dataFinal =    date('d/m/Y');

        return view('tef/list')
        ->with('tefs', $tefs)
        ->with('data1', $this->parseDate($dataInicial))
		->with('data2', $this->parseDate($dataFinal))

        ->with('info', "Lista de TEFs de Hoje: " . date("d/m/Y") )
        ->with('title', 'Lista de TEFs na Frente de Caixa');
    }

    public function filtro(Request $request){
        $dataInicial = $request->data_inicial;
        $dataFinal = $request->data_final;

        $tefs = TransacaoTEF::filtroData(
            $this->parseDate($dataInicial),
            $this->parseDate($dataFinal, true)
        );

        return view('tef/list')
        ->with('tefs', $tefs)
        ->with('data1', $this->parseDate($dataInicial))
		->with('data2', $this->parseDate($dataFinal))

        ->with('info', "Lista de TEFs " . $dataInicial . " - " . $dataFinal)
        ->with('title', 'Lista de TEFs na Frente de Caixa');
    }

    private function parseDate($date, $plusDay = false){
        if($plusDay == false)
            return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
        else
            return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
    }

    public function getCNPJByAdquirente($adquirente){
        $cnpj = getenv('TEF_CNPJ_ADQUIRENTE_PADRAO') ?: '01425787000104';

        if(strtoupper(trim($adquirente)) == 'REDE'){
            $cnpj = getenv('TEF_CNPJ_REDE') ?: '01425787000104';
        }else if(strtoupper(trim($adquirente)) == 'CIELO'){
            $cnpj = getenv('TEF_CNPJ_CIELO') ?: '01027058000191';
        }

        return $cnpj;
    }

    public function getBandeiraByBandeiraTEF($bandeira){
        $codigoBandeira = '1';

        $sql = "SELECT t.* from bandeira_cartao t
        where UPPER(t.descricao) like '" . strtoupper($bandeira) . "%'";

        $dados = DB::select($sql, []);

        if(count($dados) > 0) {
            $codigoBandeira     = $dados[0]->codigo;
		}

        return $codigoBandeira;
    }

}
