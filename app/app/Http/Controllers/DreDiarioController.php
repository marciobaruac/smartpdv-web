<?php

namespace App\Http\Controllers;

use App\Models\Venda;;
use App\Models\VendaCaixa;
use App\Models\ContaPagar;

use Illuminate\Http\Request;

class DreDiarioController extends Controller
{
    
    public function __construct(){
        $this->middleware(function ($request, $next) {
            $value = session('user_logged');
            if(!$value){
                return redirect("/login");
            }else{
                if($value['acesso_cliente'] == 0){
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

	
	public function weekOfMonth($qDate) {
		$dt = strtotime($qDate);
		$day  = date('j',$dt);
		$month = date('m',$dt);
		$year = date('Y',$dt);
		$totalDays = date('t',$dt);
		$weekCnt = 1;
		$retWeek = 0;
		
		return $month;
	}

	public function weekOfYear($qDate) {
		$dt = strtotime($qDate);
		$day  = date('j',$dt);
		$month = date('m',$dt);
		$year = date('Y',$dt);
		$totalDays = date('t',$dt);
		$weekCnt = 1;
		$retWeek = 0;
		
		return $year;
	}
	
	
	private function parseViewData($date){
		
		return date('d/m/Y', strtotime(str_replace("/", "-", $date)));
	}

    public function index(){
		$datas = $this->returnDateMesAtual();

		$fluxo = $this->criarArrayDeDatas($datas['start'], $datas['end']);
		// echo "<pre>";
		// print_r($fluxo);
		// echo "</pre>";

		// die();
		return view('dreDiario/list')
		->with('fluxo', $fluxo)
		->with('title', 'DRE Diário');
	}

    private function returnDateMesAtual(){
		$hoje = date('Y-m-d');
		$primeiroDia = substr($hoje, 0, 7) . "-01";

		return ['start' => $primeiroDia, 'end' => $hoje];
	
	}

    private function criarArrayDeDatas($inicio, $fim){
		$diferenca = strtotime($fim) - strtotime($inicio);
		$dias = floor($diferenca / (60 * 60 * 24));
		$global = [];
		$dataAtual = $fim;
		for($aux = 0; $aux < $dias+1; $aux++){

		
			$venda =    $this->getVendas($dataAtual);
			$desconto = $this->getDescontos($dataAtual);
            $vendaPDV = $this->getPDV($dataAtual);
			$custoproduto = $this->getCustoProduto($dataAtual);
		
			$despesasfixas      = $this->getContasPagar($inicio,$fim);
			
            $despesasfixasv = $despesasfixas;
		    $despesasfixasv = ($despesasfixasv/26);
            
         
			

			$despesasvariaveis  =  $this->getDespesasVariaveis($dataAtual);
            $vvendav              = $venda->valor ?? 0;
			$vvendaPDV            = $vendaPDV->valor ?? 0;
			$vdesconto            = $desconto->desconto ?? 0;
			$vvenda              =  $vvendav + $vvendaPDV ;
			$vcustoproduto       = $custoproduto ?? 0;
			$vdespesasvariaveis  = $despesasvariaveis->valor ?? 0;
            
			$lucrobruto   =   $vvenda -  ($vdesconto+$vcustoproduto);
            $lucroliquido =  ( $vvenda - ($vdesconto+$vcustoproduto+$despesasfixasv+$despesasvariaveis) ) ;
			
			if ($vvenda != 0 ){
				$perlucroliquido = ($lucroliquido /  $vvenda) * 100 ;
				$perlucrobruto   = ($lucrobruto/$vvenda) * 100;
			}
			
		
			$tst = [
				'data' => $this->parseViewData($dataAtual),
				'venda' => $vvenda ?? 0,
				'desconto' => $vdesconto ??0,
                'custoproduto' => $custoproduto  ?? 0,
				'despesasfixas' => $despesasfixasv ?? 0,
				'despesasvariaveis'  => $despesasvariaveis ?? 0,
				'lucrobruto'         => $lucrobruto ??0,
				'perlucrobruto'    => $perlucrobruto ??0,
				'lucroliquido'      =>  $lucroliquido, 
				'perlucroliquido'  => $perlucroliquido  ?? 0
			];

			array_push($global, $tst);

			$temp = [];

		//	$dataAtual = date('Y-m-d', strtotime($dataAtual. '+1day'));
		    $dataAtual = date('Y-m-d', strtotime($dataAtual. '-1day'));
		}


		return $global;
	}

	private function getVendas($data){
		$venda = Venda::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(valor_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ativo',true)
		->where('simplesremessa',false)
		->groupBy('data')
		->first();

		return $venda;
	}


	private function getDescontos($data){
		$venda = Venda::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(desconto) as desconto')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ativo',true)
		->where('simplesremessa',false)
		->groupBy('data')
		->first();

		return $venda;
	}

	private function getPDV($data){
		$venda =  VendaCaixa::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(valor_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ESTADO', '<>','CANCELADO')
		->whereRaw('ativo',true)
		->groupBy('data')
		->first();

		return $venda;
	}

    private function getCustoProduto($data){
		$venda = Venda::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(custo_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ativo',true)
		->where('simplesremessa',false)
		->groupBy('data')
		->first();

		$vendacaixa = VendaCaixa::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(custo_total) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ESTADO', '<>','CANCELADO')
		->whereRaw('ativo',true)
		->groupBy('data')
		->first();
        
		$custototal = ($venda->valor ?? 0) + ($vendacaixa->valor ?? 0);
      
		return $custototal;
	}

	private function getContasPagar($data,$datafim){
		
		
		
		$mes  = $this->weekOfMonth($data);
        $ano  = $this->weekOfYear($data);
		$contas = ContaPagar::
		
		selectRaw('sum(valor_integral) as valor')
		->whereMonth('data_emissao', $mes)
		->whereYear('data_emissao', $ano)
		
		->join('dre_contas', 'dre_contas.id' , '=', 'dreconta_id')
		->where('dre_contas.tipo', '=', "F")
		->first();
	
		



		return $contas->valor;
	}

	private function getDespesasVariaveis($data){
		
		$venda = Venda::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(txcartao+txantcartao) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ativo',true)
		->groupBy('data')
		->first();

		$vendacaixa = VendaCaixa::
		selectRaw('DATE_FORMAT(data_registro, "%Y-%m-%d") as data, sum(txcartao+txantcartao) as valor')
		->whereRaw("DATE_FORMAT(data_registro, '%Y-%m-%d') = '$data' ")
		->where('ESTADO', '<>','CANCELADO')
		->whereRaw('ativo',true)
		->groupBy('data')
		->first();

		$custovariaveis = ($venda->valor ?? 0) + ($vendacaixa->valor ?? 0);

		return $custovariaveis;
	
	}

	public function filtro(Request $request){

		$fluxo = $this->criarArrayDeDatas($this->parseDate($request->data_inicial), 
			$this->parseDate($request->data_final));
		return view('dreDiario/list')
		->with('fluxo', $fluxo)
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)

		->with('dataInicial', $this->parseDate($request->data_inicial))
		->with('dataFinal', $this->parseDate($request->data_final))
		->with('title', 'DRE Diário');
	}


	

}
