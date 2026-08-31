<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\CreditoVenda;
use App\Models\Venda;
use App\Models\VendaCaixa;
use Dompdf\Dompdf;

class FluxoCaixaController extends Controller
{

	public function __construct(){
        $this->middleware(function ($request, $next) {
            $value = session('user_logged');
            if(!$value){
				return redirect("/login");
			}else{
				if($value['adm'] == 0){
					return redirect("/sempermissao");
				}
			}
            return $next($request);
        });
    }

	private function parseDate($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
		else
			return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
	}

	private function parseViewData($date){

		return date('d/m/Y', strtotime(str_replace("/", "-", $date)));
	}


	public function index(){
		$datas = $this->returnDateMesAtual();

		$saldoant = $this->retornasaldoant($datas['start']);

		$fluxo = $this->criarArrayDeDatas($datas['start'], $datas['end']);
	//	echo "<pre>";
		//print_r($saldoant);
	//	echo "</pre>";

	//	die();
		return view('fluxoCaixa/list')
		->with('fluxo', $fluxo)
		->with('saldoant',$saldoant)
		->with('title', 'Fluxo de Caixa');
	}

	public function filtro(Request $request){

		$saldoant = $this->retornasaldoant($this->parseDate($request->data_inicial));
		$fluxo = $this->criarArrayDeDatas($this->parseDate($request->data_inicial),
			$this->parseDate($request->data_final));
		return view('fluxoCaixa/list')
		->with('fluxo', $fluxo)
		->with('saldoant',$saldoant)

		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)

		->with('dataInicial', $this->parseDate($request->data_inicial))
		->with('dataFinal', $this->parseDate($request->data_final))
		->with('title', 'Fluxo de Caixa');
	}

	private function returnDateMesAtual(){
		$hoje = date('Y-m-d');
		$primeiroDia = substr($hoje, 0, 7) . "-01";

		return ['start' => $primeiroDia, 'end' => $hoje];
	}

	private function getContasReceber($data){
		$contas = ContaReceber::
		selectRaw('data_recebimento as data, sum(valor_recebido) as valor')
		->where('data_recebimento', $data)
		->whereRaw('forma_recebimento not in  ("01","02","03","04")')
		->whereRaw('status', true)
		->where('ativo',true)
		->groupBy('data_recebimento')
		->first();
		return $contas;
	}

	private function getCreditoVenda($data){
		$creditos = CreditoVenda::
		selectRaw('DATE_FORMAT(vendas.data_registro, "%Y-%m-%d") as data, sum(vendas.valor_total) as valor')
		->join('vendas', 'vendas.id' , '=', 'credito_vendas.venda_id')
		->whereRaw("DATE_FORMAT(credito_vendas.updated_at, '%Y-%m-%d') = '$data'")
		->where('credito_vendas.status', true)
		->groupBy('data')
		->first();

		return $creditos;
	}

	private function getContasPagar($data){
		$contas = ContaPagar::
		selectRaw('data_pagamento as data, sum(valor_pago) as valor')
		->where('data_pagamento', $data)
		->whereRaw('status', true)
		->groupBy('data_pagamento')
		->first();
		return $contas;
	}

	private function getVendas($data){
		$venda = Venda::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(valor_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' AND forma_pagamento = 'a_vista'")
		->groupBy('data')
		->first();

		return $venda;
	}

	private function getDinheiro($data){
		$dinheiro = ContaReceber::
		selectRaw('DATE_FORMAT(data_recebimento, "%Y-%m-%d") as data, sum(valor_recebido) as valor')
		->whereRaw("DATE_FORMAT(data_recebimento, '%Y-%m-%d') = '$data' AND forma_recebimento = '01'")
		->groupBy('data')
		->where('ativo',true)
		->first();

		return $dinheiro;
	}

	private function getDebito($data){
		$debito = ContaReceber::
		selectRaw('DATE_FORMAT(data_recebimento, "%Y-%m-%d") as data, sum(valor_recebido) as valor')
		->whereRaw("DATE_FORMAT(data_recebimento, '%Y-%m-%d') = '$data' AND forma_recebimento = '02'")
		->whereRaw('ativo',true)
		->groupBy('data')

		->first();

		return $debito;
	}

	private function getCredito($data){
		$debito = ContaReceber::
		selectRaw('DATE_FORMAT(data_recebimento, "%Y-%m-%d") as data, sum(valor_recebido) as valor')
		->whereRaw("DATE_FORMAT(data_recebimento, '%Y-%m-%d') = '$data' AND forma_recebimento = '03'")
		->whereRaw('ativo',true)
		->groupBy('data')
		->first();

		return $debito;
	}

	private function getPix($data){
		$dinheiro = ContaReceber::
		selectRaw('DATE_FORMAT(data_recebimento, "%Y-%m-%d") as data, sum(valor_recebido) as valor')
		->whereRaw("DATE_FORMAT(data_recebimento, '%Y-%m-%d') = '$data' AND forma_recebimento = '04'")
		->whereRaw('ativo',true)
		->groupBy('data')
		->first();

		return $dinheiro;
	}


	private function getVendaCaixa($data){
		$venda = VendaCaixa::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(valor_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data'")
		->groupBy('data')
		->first();

		return $venda;
	}


	private function retornasaldoant($inicio){


		$receitas = ContaReceber::
		selectRaw('sum(valor_recebido) as valor')
		->whereRaw("data_recebimento < '$inicio'")
		->whereRaw('status', true)
		->where('ativo',true)
		->first();




		$despesas = ContaPagar::
		selectRaw('sum(valor_pago) as valor')
		->whereRaw("data_pagamento < '$inicio'")
		->whereRaw('status', true)
		->first();


		$saldo = $receitas->valor - $despesas->valor;

		return $saldo;
	}




	private function criarArrayDeDatas($inicio, $fim){
		$diferenca = strtotime($fim) - strtotime($inicio);
		$dias = floor($diferenca / (60 * 60 * 24));
		$global = [];
		$dataAtual = $inicio;
		for($aux = 0; $aux < $dias+1; $aux++){

		    $contaReceber = $this->getContasReceber($dataAtual);

			$contaPagar = $this->getContasPagar($dataAtual);

			$credito = $this->getCreditoVenda($dataAtual);

			$venda = $this->getVendas($dataAtual);

			$dinheiro = $this->getDinheiro($dataAtual);
			$debito = $this->getDebito($dataAtual);
			$credito = $this->getCredito($dataAtual);
			$pix = $this->getPix($dataAtual);





			$tst = [
				'data' => $this->parseViewData($dataAtual),
				'conta_receber' => $contaReceber->valor ?? 0,
				'conta_pagar' => $contaPagar->valor ?? 0,
				'credito_venda' => $credito->valor ?? 0,
				'venda' => $venda->valor ?? 0,
				'dinheiro' => $dinheiro->valor ?? 0,
				'debito' => $debito->valor ?? 0,
				'credito' => $credito->valor ??0,
				'pix' => $pix->valor ??0,


				'venda_caixa' => $vendaCaixa->valor ?? 0,
			];

			array_push($global, $tst);

			$temp = [];

			$dataAtual = date('Y-m-d', strtotime($dataAtual. '+1day'));
		}


		return $global;
	}

	public function relatorioIndex(){

		$domPdf = new Dompdf();

		// ob_start();
		$datas = $this->returnDateMesAtual();

		$fluxo = $this->criarArrayDeDatas($datas['start'], $datas['end']);
		$p = view('fluxoCaixa/relatorio')
		->with('fluxo', $fluxo);
		$domPdf->loadHtml($p);

		// $pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("file.pdf");
	}

	public function relatorioFiltro($data_inicial, $data_final){

		$domPdf = new Dompdf();

		// ob_start();

		$fluxo = $this->criarArrayDeDatas($this->parseDate($data_inicial),
			$this->parseDate($data_final));
		$p = view('fluxoCaixa/relatorio')
		->with('fluxo', $fluxo);
		$domPdf->loadHtml($p);

		// $pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("file.pdf");
	}

}
