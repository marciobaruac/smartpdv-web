<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venda;
use App\Models\ItemVenda;
use App\Models\ItemPedido;
use App\Models\VendaCaixa;
use App\Models\ItemVendaCaixa;
use App\Models\Compra;
use App\Models\Estoque;
use App\Models\Produto;
use App\Models\Usuario;
use App\Models\ContaReceber;
use App\Models\ContaPagar;
use App\Models\ConfigNota;
use App\Models\Cliente;
use App\Models\Categoria;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PHPUnit\Framework\Constraint\IsEmpty;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{

	public function __construct(){
		$this->middleware(function ($request, $next) {
			$value = session('user_logged');
			if(!$value){
				return redirect("/login");
			}else{
				if($value['acesso_financeiro'] == 0){
					return redirect("/sempermissao");
				}
			}
			return $next($request);
		});
	}

	public function index(){
		$usuarios = Usuario::all();
		$clientes = Cliente::all();
		$config   = ConfigNota::first();
        $categoria = Categoria::all();

		return view('relatorios/index')
		->with('relatorioJS', true)
		->with('usuarios', $usuarios)
		->with('clientes', $clientes)
		->with('config', $config)
        ->with('categoria',$categoria)


		->with('title', 'Relatórios');
	}

	public function filtroVendas(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$total_resultados = $request->total_resultados;
		$ordem = $request->ordem;

		if($data_inicial && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}

		$config = ConfigNota::first();

		$vendas = Venda
		::select(\DB::raw('DATE_FORMAT(vendas.data_registro, "%d-%m-%Y") as data, sum(vendas.valor_total) as total'))
		->orWhere(function($q) use ($data_inicial, $data_final){
			if($data_inicial && $data_final){
				return $q->whereBetween('vendas.data_registro', [$data_inicial,
					$data_final]);
			}
		})
		->where('ativo',true)
		->groupBy('data')
		->orderBy($ordem == 'data' ? 'data' : 'total', $ordem == 'data' ? 'desc' : $ordem)

		->limit($total_resultados ?? 1000000)
		->get();


		$vendasCaixa = VendaCaixa
		::select(\DB::raw('DATE_FORMAT(venda_caixas.data_registro, "%d-%m-%Y") as data, sum(venda_caixas.valor_total) as total'))

		->orWhere(function($q) use ($data_inicial, $data_final){
			if($data_inicial && $data_final){
				return $q->whereBetween('venda_caixas.data_registro', [$data_inicial,
					$data_final]);
			}
		})
		->where('ativo',true)
		->groupBy('data')
		->orderBy($ordem == 'data' ? 'data' : 'total', $ordem == 'data' ? 'desc' : $ordem)
		->limit($total_resultados ?? 1000000)
		->get();


		// echo $vendasCaixa;
		// die();


		$arr = $this->uneArrayVendas($vendas, $vendasCaixa);



		if($total_resultados){
			$arr = array_slice($arr, 0, $total_resultados);
		}
		usort($arr, function($a, $b) use ($ordem){
			if($ordem == 'asc') return $a['total'] > $b['total'];
			else if($ordem == 'desc') return $a['total'] < $b['total'];
			else return $a['data'] < $b['data'];
		});





		if(sizeof($arr) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}

		$p = view('relatorios/relatorio_venda')
		->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')

		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('vendas', $arr)
		->with('fantasia', $config->nome_fantasia);


		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio_venda.pdf");
	}

	public function filtroDespesas(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
	//	$total_resultados = $request->total_resultados;
		//$ordem = $request->ordem;

		if($data_inicial && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}


	    $c = ContaPagar::
		select('conta_pagars.*')

		->orderBy('data_pagamento', 'asc')
		->whereBetween('data_pagamento', [$data_inicial,
		   $data_final])
		->get();

		// echo $vendasCaixa;
		// die();

	//	$arr = $this->uneArrayVendas($vendas, $vendasCaixa);

		$this->list($c);



		if(sizeof($c) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}

	//	$p = view('relatorios/relatorio_venda')
	//	->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')

	//	->with('data_inicial', $request->data_inicial)
	//	->with('data_final', $request->data_final)
	//	->with('vendas', $arr);

		// return $p;

	//	$domPdf = new Dompdf(["enable_remote" => true]);
	//	$domPdf->loadHtml($p);
//
//		$pdf = ob_get_clean();

//		$domPdf->setPaper("A4");
//		$domPdf->render();
//		$domPdf->stream("relatorio_venda.pdf");
	}


	public function filtroReceitas(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
	//	$total_resultados = $request->total_resultados;
		//$ordem = $request->ordem;

		if($data_inicial && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}


	    $c = ContaReceber::
		select('conta_recebers.*')


		-> where('ativo',true)
		->orderBy('data_recebimento', 'asc')
		->whereBetween('data_recebimento', [$data_inicial,
		   $data_final])
		->get();

		// echo $vendasCaixa;
		// die();

	//	$arr = $this->uneArrayVendas($vendas, $vendasCaixa);

		$this->listreceber($c);



		if(sizeof($c) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}

	//	$p = view('relatorios/relatorio_venda')
	//	->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')

	//	->with('data_inicial', $request->data_inicial)
	//	->with('data_final', $request->data_final)
	//	->with('vendas', $arr);

		// return $p;

	//	$domPdf = new Dompdf(["enable_remote" => true]);
	//	$domPdf->loadHtml($p);
//
//		$pdf = ob_get_clean();

//		$domPdf->setPaper("A4");
//		$domPdf->render();
//		$domPdf->stream("relatorio_venda.pdf");
	}



	public function filtroCompras(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$total_resultados = $request->total_resultados;
		$ordem = $request->ordem;

		if($data_final && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}
        $config = ConfigNota::first();
		$compras = Compra
        ::select('compras.id',
                 'fornecedors.razao_social',
                 \DB::raw('sum(compras.valor) as total'),
                 \DB::raw('count(compras.id) as compras_diarias'),
                 \DB::raw('DATE_FORMAT(compras.created_at, "%d-%m-%Y") as data'))
        ->join('fornecedors', 'compras.fornecedor_id', '=', 'fornecedors.id')
        ->where(function($q) use ($data_inicial, $data_final){
            if($data_inicial && $data_final){
                $q->whereBetween('compras.created_at', [$data_inicial, $data_final]);
            }
            $q->where('compras.status', 0); // Fixed status of 0
        })
        ->groupBy('compras.id', 'fornecedors.razao_social')
        ->orderBy('compras.created_at') // Order by data
        ->get();


		if(sizeof($compras) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}

		$p = view('relatorios/relatorio_compra')
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('compras', $compras)
        ->with('fantasia', $config->nome_fantasia);



		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de compras.pdf");
	}

public function filtroVendaProdutos(Request $request) {
    $data_inicial = $request->data_inicial;
    $data_final = $request->data_final;
    $total_resultados = $request->total_resultados;
    $ordem = $request->ordem;
    $cliente = $request->cliente;
    $categoriaproduto = $request->categoriaproduto;

    if ($data_final && $data_final) {
        $data_inicial = $this->parseDate($data_inicial);
        $data_final = $this->parseDate($data_final, true);
    }

    $itensVenda = ItemVenda::select(\DB::raw('produtos.id as id, produtos.nome as nome, produtos.valor_venda as valor_venda, sum(item_vendas.quantidade) as total, sum(item_vendas.quantidade * item_vendas.valor) as total_dinheiro'))
        ->join('produtos', 'produtos.id', '=', 'item_vendas.produto_id')
        ->join('vendas', 'vendas.id', '=', 'item_vendas.venda_id')
        ->orWhere(function($q) use ($data_inicial, $data_final) {
            if ($data_final && $data_final) {
                return $q->whereBetween('item_vendas.created_at', [$data_inicial, $data_final]);
            }
        })
        ->groupBy('produtos.nome')
        ->orderBy($ordem == 'nome' ? 'produtos.nome' : 'total', $ordem == 'nome' ? 'asc' : $ordem);

    if ($cliente != 'todos') {
        $itensVenda->where('cliente_id', $cliente);
    }

    if ($categoriaproduto != 'todos') {
        $itensVenda->where('categoria_id', $categoriaproduto);
    }

    $itensVenda = $itensVenda->get();

    $itensVendaCaixa = ItemVendaCaixa::select([
            'produtos.id as id',
            'produtos.nome as nome',
            'item_venda_caixas.valor as valor_venda',
            \DB::raw('SUM(item_venda_caixas.quantidade) as total'),
            \DB::raw('SUM(item_venda_caixas.quantidade * item_venda_caixas.valor) as total_dinheiro')
        ])
        ->join('produtos', 'produtos.id', '=', 'item_venda_caixas.produto_id')
        ->join('venda_caixas', 'venda_caixas.id', '=', 'item_venda_caixas.venda_caixa_id')
        ->where('venda_caixas.ativo', 1) // Adiciona o filtro para o campo 'ativo'
        ->Where(function($q) use ($data_inicial, $data_final) {
            if ($data_final && $data_final) {
                return $q->whereBetween('item_venda_caixas.created_at', [$data_inicial, $data_final]);
            }
        })
        ->groupBy('produtos.nome')
        ->orderBy($ordem == 'nome' ? 'produtos.nome' : 'total', $ordem == 'nome' ? 'asc' : $ordem);

    if ($cliente != 'todos') {
        $itensVendaCaixa->where('cliente_id', $cliente);
    }

    if ($categoriaproduto != 'todos') {
        $itensVendaCaixa->where('categoria_id', $categoriaproduto);
    }

    $itensVendaCaixa = $itensVendaCaixa->get();

    $arr = $this->uneArrayProdutos($itensVenda, $itensVendaCaixa);

    if (sizeof($arr) == 0) {
        session()->flash("mensagem_erro", "Relatório sem registro!");
        return redirect('/relatorios');
    }

    if ($total_resultados) {
        $arr = array_slice($arr, 0, $total_resultados);
    }

    usort($arr, function($a, $b) use ($ordem) {
        if ($ordem == 'asc') {
            return $a['total'] - $b['total'];
        } elseif ($ordem == 'desc') {
            return $b['total'] - $a['total'];
        } else {
            return strcmp($a['nome'], $b['nome']);
        }
    });

    $p = view('relatorios/relatorio_venda_produtos')
        ->with('ordemt', $ordem == 'asc' ? 'Menos' : 'Mais')
        ->with('ordem', $ordem)
        ->with('data_inicial', $request->data_inicial)
        ->with('data_final', $request->data_final)
        ->with('itens', $arr);

    $domPdf = new Dompdf(["enable_remote" => true]);
    $domPdf->loadHtml($p);

    $pdf = ob_get_clean();

    $domPdf->setPaper("A4");
    $domPdf->render();
    $domPdf->stream("relatorio de produtos.pdf");
}



	public function filtroVendaClientes(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$total_resultados = $request->total_resultados;
		$ordem = $request->ordem;

		if($data_final && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}

		$vendas = Venda
		::select(\DB::raw('clientes.id as id, clientes.nome_fantasia as nome, count(*) as total, sum(valor_total) as total_dinheiro'))
		->join('clientes', 'clientes.id', '=', 'vendas.cliente_id')
		->orWhere(function($q) use ($data_inicial, $data_final){
			if($data_final && $data_final){
				return $q->whereBetween('vendas.data_registro', [$data_inicial,
					$data_final]);
			}
		})
		->where('ativo',true)
		->groupBy('clientes.id')
		->orderBy('total_dinheiro', $ordem)

		->limit($total_resultados ?? 1000000)
		->get();

		$vendasCaixa = VendaCaixa
		::select(\DB::raw('clientes.id as id, clientes.nome_fantasia as nome, count(*) as total, sum(valor_total) as total_dinheiro'))
		->join('clientes', 'clientes.id', '=', 'venda_caixas.cliente_id')
		->orWhere(function($q) use ($data_inicial, $data_final){
			if($data_final && $data_final){
				return $q->whereBetween('venda_caixas.data_registro', [$data_inicial,
					$data_final]);
			}
		})
		->where('ativo',true)
		->groupBy('clientes.id')
		->orderBy('total_dinheiro', $ordem)

		->limit($total_resultados ?? 1000000)
		->get();


		$arr = $this->uneArrayVendasCliente($vendas, $vendasCaixa);

		//echo "<pre>";
	  //  print_r($arr);
	   // echo "</pre>";
	   // die();

	   if($total_resultados){
		$arr = array_slice($arr, 0, $total_resultados);
	  }
	   usort($arr, function($a, $b) use ($ordem){
		if($ordem == 'asc') return $a['valor'] > $b['valor'];
		else if($ordem == 'desc') return $a['valor'] < $b['valor'];
		else return $a['data'] < $b['data'];
	   });

	    //echo "<pre>";
	    //print_r($arr);
	    //echo "</pre>";
	    //die();

		if(sizeof($arr) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}


		$p = view('relatorios/relatorio_clientes')
		->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('vendas', $arr);

		// return $p;



		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de compras.pdf");
	}

	public function filtroEstoqueMinimo(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$total_resultados = $request->total_resultados;
		$ordem = $request->ordem;

		if($data_final && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}

		$produtos = Produto::all();
		$arrDesfalque = [];
		foreach($produtos as $p){
			if($p->estoque_minimo > 0){
				$estoque = Estoque::where('produto_id', $p->id)->first();
				$temp = null;
				if($estoque == null){
					$temp = [
						'id' => $p->id,
						'nome' => $p->nome,
						'estoque_minimo' => $p->estoque_minimo,
						'estoque_atual' => 0,
						'total_comprar' => $p->estoque_minimo,
						'valor_compra' => 0
					];
				}else{
					$temp = [
						'id' => $p->id,
						'nome' => $p->nome,
						'estoque_minimo' => $p->estoque_minimo,
						'estoque_atual' => $estoque->quantidade,
						'total_comprar' => $p->estoque_minimo - $estoque->quantidade,
						'valor_compra' => $estoque->valor_compra
					];
				}

				array_push($arrDesfalque, $temp);

			}
		}

		if($total_resultados){
			$arrDesfalque = array_slice($arrDesfalque, 0, $total_resultados);
		}

		// print_r($arrDesfalque);

		$p = view('relatorios/relatorio_estoque_minimo')
		->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('itens', $arrDesfalque);

		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de estoque minimo.pdf");
	}

	public function filtroVendaDiaria(Request $request){
		$data = $request->data_inicial;
		$total_resultados = $request->total_resultados;
		$ordem = $request->ordem;

		$data_inicial = null;
		$data_final = null;

		if(strlen($data) == 0){
			session()->flash("mensagem_erro", "Informe o dia para gerar o relatório!");
			return redirect('/relatorios');
		}else{
			$data_inicial = $this->parseDateDay($data);
			$data_final = $this->parseDateDay($data, true);
		}

		$vendas = Venda
		::select(\DB::raw('vendas.id, DATE_FORMAT(vendas.data_registro, "%d-%m-%Y %H:%i") as data, valor_total, desconto'))

		->where('ativo',true)
		->join('item_vendas', 'item_vendas.venda_id', '=', 'vendas.id')
		->Where(function($q) use ($data_inicial, $data_final){
			if($data_final && $data_final){
				return $q->whereBetween('vendas.created_at', [$data_inicial,
					$data_final]);
			}
		})
		->groupBy('vendas.id')

		->limit($total_resultados ?? 1000000)
		->get();

		$vendasCaixa = VendaCaixa
		::select(\DB::raw('venda_caixas.id, DATE_FORMAT(venda_caixas.data_registro, "%d-%m-%Y %H:%i") as data, valor_total, desconto'))
		->where('ativo',true)
		->join('item_venda_caixas', 'item_venda_caixas.venda_caixa_id', '=', 'venda_caixas.id')

		->Where(function($q) use ($data_inicial, $data_final){
			if($data_final && $data_final){
				return $q->whereBetween('venda_caixas.created_at', [$data_inicial,
					$data_final]);
			}
		})
		->groupBy('venda_caixas.id')
		->limit($total_resultados ?? 1000000)
		->get();


		$arr = $this->uneArrayVendasDay($vendas, $vendasCaixa);
		if($total_resultados){
			$arr = array_slice($arr, 0, $total_resultados);
		}

		// usort($arr, function($a, $b) use ($ordem){
		// 	if($ordem == 'asc') return $a['total'] > $b['total'];
		// 	else if($ordem == 'desc') return $a['total'] < $b['total'];
		// 	else return $a['data'] < $b['data'];
		// });

		if(sizeof($arr) == 0){

			session()->flash("mensagem_erro", "Relatório sem registro!");
			return redirect('/relatorios');
		}

		$p = view('relatorios/relatorio_diario')
		->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')

		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('vendas', $arr);

		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de vendas.pdf");
	}

	private function uneArrayVendas($vendas, $vendasCaixa){
		$adicionados = [];
		$arr = [];

		foreach($vendas as $v){

			$temp = [
				'data' => $v->data,
				'total' => $v->total,
				// 'itens' => $v->itens
			];
			array_push($adicionados, $v->data);
			array_push($arr, $temp);

		}

		foreach($vendasCaixa as $v){


			if(!in_array($v->data, $adicionados)){


				$temp = [
					'data' => $v->data,
					'total' => $v->total,
					// 'itens' => $v->itens
				];
				array_push($adicionados, $v->data);
				array_push($arr, $temp);
			}else{
				for($aux = 0; $aux < count($arr); $aux++){
					if($arr[$aux]['data'] == $v->data){
						$arr[$aux]['total'] += $v->total;
						// $arr[$aux]['itens'] += $i->itens;
					}
				}
			}

		}
		return $arr;
	}

	private function uneArrayVendasDay($vendas, $vendasCaixa){
		$adicionados = [];
		$arr = [];

		foreach($vendas as $v){

			$temp = [
				'id' => $v->id,
				'data' => $v->data,
				'total' => $v->valor_total,
				'itens' => $v->itens,
				'desconto' => $v->desconto
			];
			array_push($adicionados, $v->data);
			array_push($arr, $temp);

		}

		foreach($vendasCaixa as $v){

			$temp = [
				'id' => $v->id,
				'data' => $v->data,
				'total' => $v->valor_total,
				'itens' => $v->itens,
				'desconto' => $v->desconto
			];

			array_push($adicionados, $v->data);
			array_push($arr, $temp);

		}
		return $arr;
	}

	private function uneArrayVendasCliente($vendas, $vendasCaixa){
		$adicionados = [];
		$arr = [];

		foreach($vendas as $v){

			$temp = [
				'id' => $v->id,
				'nome' => $v->nome,
				'total' => $v->total,
				'valor' => $v->total_dinheiro
			];
		//	array_push($adicionados, $v->data);
			array_push($arr, $temp);

		}

		foreach($vendasCaixa as $v){


			$temp = [
				'id' => $v->id,
				'nome' => $v->nome,
				'total' => $v->total,
				'valor' => $v->total_dinheiro
			];



			array_push($arr, $temp);

		}
		return $arr;
	}

	private function uneArrayProdutos($itemVenda, $itemVendasCaixa){
		$adicionados = [];
		$arr = [];

		foreach($itemVenda as $i){

			$temp = [
				'id' => $i->id,
				'nome' => $i->nome,
				'valor_venda' => $i->valor_venda,
				'total' => $i->total,
				'total_dinheiro' => $i->total_dinheiro,
			];
			array_push($adicionados, $i->id);
			array_push($arr, $temp);

		}

		foreach($itemVendasCaixa as $i){
			if(!in_array($i->id, $adicionados)){
				$temp = [
					'id' => $i->id,
					'nome' => $i->nome,
					'valor_venda' => $i->valor_venda,
					'total' => $i->total,
					'total_dinheiro' => $i->total_dinheiro,
				];
				array_push($adicionados, $i->id);
				array_push($arr, $temp);
			}else{
				for($aux = 0; $aux < count($arr); $aux++){
					if($arr[$aux]['id'] == $i->id){
						$arr[$aux]['total'] += $i->total;
						$arr[$aux]['total_dinheiro'] += $i->total;
					}
				}
			}
		}

		return $arr;
	}

	private static function parseDate($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
		else
			return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
	}

	private static function parseDateDay($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date))) . " 00:00";
		else
			return date('Y-m-d', strtotime(str_replace("/", "-", $date))) . " 23:59";

	}



	public function filtroLucro(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$tipo = $request->tipo;

		if($tipo == 'detalhado'){
			if(!$data_inicial){
				session()->flash("mensagem_erro", "Informe a data para gerar o relatório!");
				return redirect('/relatorios');
			}

			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_inicial, true);

			$vendas = Venda
			::whereBetween('vendas.created_at', [$data_inicial,
				$data_final])
			->where('ativo',true)
			->groupBy('created_at')
			->get();

			$vendasCaixa = VendaCaixa
			::whereBetween('venda_caixas.created_at', [$data_inicial,
				$data_final])
			->where('ativo',true)
			->groupBy('created_at')
			->get();


			$arr = [];
			foreach($vendas as $v){
				$total = $v->valor_total;
				$somaValorCompra = 0;
				foreach($v->itens as $i){
				//pega valor de compra
					$vCompra = 0;
					$vCompra = $i->produto->valor_compra;
					if(!$vCompra == 0){
						$estoque = Estoque::ultimoValorCompra($i->produto_id);

						if($estoque != null){
							$vCompra = $estoque->valor_compra;
						}
					}

					$somaValorCompra = $i->quantidade * $vCompra;
				}

				$lucro = $total - $somaValorCompra;
				if($somaValorCompra == 0){
					$somaValorCompra = 1;
				}

				$temp = [
					'valor_venda' => $total,
					'valor_compra' => $somaValorCompra,
					'lucro' => $lucro,
					'lucro_percentual' =>
					number_format((($somaValorCompra - $total)/$somaValorCompra*100)*-1, 2),
					'local' => 'NF-e',
					'cliente' => $v->cliente->razao_social,
					'horario' => \Carbon\Carbon::parse($v->created_at)->format('H:i')
				];
				array_push($arr, $temp);
			}

			foreach($vendasCaixa as $v){
				$total = $v->valor_total;
				$somaValorCompra = 0;
				foreach($v->itens as $i){
				//pega valor de compra
					$vCompra = 0;
					$vCompra = $i->produto->valor_compra;
					if(!$vCompra == 0){
						$estoque = Estoque::ultimoValorCompra($i->produto_id);

						if($estoque != null){
							$vCompra = $estoque->valor_compra;
						}
					}

					$somaValorCompra = $i->quantidade * $vCompra;
				}

				$lucro = $total - $somaValorCompra;

				if($somaValorCompra == 0){
					$somaValorCompra = 1;
				}

				$temp = [
					'valor_venda' => $total,
					'valor_compra' => $somaValorCompra,
					'lucro' => $lucro,
					'lucro_percentual' =>
					number_format((($somaValorCompra - $total)/$somaValorCompra*100)*-1, 2),
					'local' => 'PDV',
					'cliente' => $v->cliente ? $v->cliente->razao_social : 'Cliente padrão',
					'horario' => \Carbon\Carbon::parse($v->created_at)->format('H:i')
				];
				array_push($arr, $temp);

			}

			if(sizeof($arr) == 0){

				session()->flash("mensagem_erro", "Relatório sem registro!");
				return redirect('/relatorios');
			}


			$p = view('relatorios/lucro_detalhado')
			->with('data_inicial', $request->data_inicial)
			->with('lucros', $arr);

			// return $p;

			$domPdf = new Dompdf(["enable_remote" => true]);
			$domPdf->loadHtml($p);

			$pdf = ob_get_clean();

			$domPdf->setPaper("A4");
			$domPdf->set_paper('letter', 'landscape');
			$domPdf->render();
			$domPdf->stream("relatorio de lucro detalhado.pdf");



		}else{

			if($data_final && $data_final){
				$data_inicial = $this->parseDate($data_inicial);
				$data_final = $this->parseDate($data_final, true);
			}
			if(!$data_inicial || !$data_final){
				session()->flash("mensagem_erro", "Informe o periodo corretamente para gerar o relatório!");
				return redirect('/relatorios');

				$vendas = Venda
				::whereBetween('vendas.created_at', [$data_inicial,
					$data_final])
				->groupBy('created_at')
				->get();
			}

			$vendas = Venda
			::whereBetween('vendas.created_at', [$data_inicial,
				$data_final])
			->groupBy('created_at')
			->get();

			$vendasCaixa = VendaCaixa
			::whereBetween('venda_caixas.created_at', [$data_inicial,
				$data_final])
			->groupBy('created_at')
			->get();


			$tempVenda = [];
			foreach($vendas as $v){
				$total = $v->valor_total;
				$somaValorCompra = 0;
				foreach($v->itens as $i){
				//pega valor de compra
					$vCompra = 0;
					$vCompra = $i->produto->valor_compra;
					if(!$vCompra == 0){
						$estoque = Estoque::ultimoValorCompra($i->produto_id);

						if($estoque != null){
							$vCompra = $estoque->valor_compra;
						}
					}

					$somaValorCompra = $i->quantidade * $vCompra;
				}

				$lucro = $total - $somaValorCompra;

				if(!isset($tempVenda[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')])){
					$tempVenda[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')] = $lucro;
				}else{
					$tempVenda[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')] += $lucro;
				}

			}

			$tempCaixa = [];
			foreach($vendasCaixa as $v){
				$total = $v->valor_total;
				$somaValorCompra = 0;
				foreach($v->itens as $i){
				//pega valor de compra
					$vCompra = 0;
					$vCompra = $i->produto->valor_compra;
					if(!$vCompra == 0){
						$estoque = Estoque::ultimoValorCompra($i->produto_id);

						if($estoque != null){
							$vCompra = $estoque->valor_compra;
						}
					}

					$somaValorCompra = $i->quantidade * $vCompra;
				}

				$lucro = $total - $somaValorCompra;

				if(!isset($tempCaixa[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')])){
					$tempCaixa[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')] = $lucro;
				}else{
					$tempCaixa[\Carbon\Carbon::parse($v->created_at)->format('d/m/Y')] += $lucro;
				}

			}

			// print_r($tempVenda);
			// print_r($tempCaixa);

			$arr = $this->criarArrayDeDatas($data_inicial, $data_final, $tempVenda, $tempCaixa);


			$p = view('relatorios/lucro')
			->with('data_inicial', $request->data_inicial)
			->with('data_final', $request->data_final)
			->with('lucros', $arr);

			// return $p;

			$domPdf = new Dompdf(["enable_remote" => true]);
			$domPdf->loadHtml($p);

			$pdf = ob_get_clean();

			$domPdf->setPaper("A4");
			$domPdf->render();
			$domPdf->stream("relatorio de lucro.pdf");
		}
	}

	private function gerarLucroDetalhado(){

	}

	private function criarArrayDeDatas($inicio, $fim, $tempVenda, $tempCaixa){
		$diferenca = strtotime($fim) - strtotime($inicio);
		$dias = floor($diferenca / (60 * 60 * 24));
		$global = [];
		$dataAtual = $inicio;
		for($aux = 0; $aux < $dias+1; $aux++){
			// echo \Carbon\Carbon::parse($dataAtual)->format('d/m/Y');


			$rs['data'] = $this->parseViewData($dataAtual);
			if(isset($tempCaixa[\Carbon\Carbon::parse($dataAtual)->format('d/m/Y')])){
				$rs['valor_caixa'] = $tempCaixa[\Carbon\Carbon::parse($dataAtual)->format('d/m/Y')];
			}else{
				$rs['valor_caixa'] = 0;
			}
			if(isset($tempVenda[\Carbon\Carbon::parse($dataAtual)->format('d/m/Y')])){
				$rs['valor'] = $tempVenda[\Carbon\Carbon::parse($dataAtual)->format('d/m/Y')];
			}else{
				$rs['valor'] = 0;
			}

			array_push($global, $rs);


			$dataAtual = date('Y-m-d', strtotime($dataAtual. '+1day'));
		}


		return $global;
	}

	private function parseViewData($date){
		return date('d/m/Y', strtotime(str_replace("/", "-", $date)));
	}


	public function cobrancaPendente(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$usuario = $request->usuario;

		if($data_final && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}

		$contas = ContaReceber::
		whereBetween('data_vencimento', [$data_inicial,
			$data_final])
		->where('status', false);

		if($usuario != 'todos'){
			$contas->where('usuario_id', $usuario);
		}

		$contas = $contas->get();

		// print_r($arrDesfalque);
		if($usuario != 'todos'){
			$usuario = Usuario::find($usuario)->first()->nome;
		}

		$p = view('relatorios/relatorio_cobranca_pedente')
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('usuario', $usuario)
		->with('contas', $contas);

		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de estoque minimo.pdf");
	}

	public function estoqueProduto(Request $request){
		$ordem = $request->ordem;
		$total_resultados = $request->total_resultados;

		$produtos = Produto
		::select(\DB::raw('produtos.id, produtos.nome, produtos.unidade_venda, estoques.quantidade'))
		->join('estoques', 'produtos.id', '=', 'estoques.produto_id')
		->limit($total_resultados ?? 1000000)
		->orderBy('produtos.nome')
		->where('ativo', true);
		if($ordem == 'qtd'){
		// ->orderBy('total', $ordem)
			$produtos = $produtos->orderBy('estoques.quantidade', 'desc');
		}

		$produtos = $produtos->get();

		// echo $produtos;
		// die();


		$p = view('relatorios/relatorio_estoque')
		->with('ordem', $ordem == 'asc' ? 'Menos' : 'Mais')
		->with('produtos', $produtos);

		// return $p;

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de estoque.pdf");
	}

	public function relatorioAtendimento(Request $request){
		$data_inicial = $request->data_inicial;
		$data_final = $request->data_final;
		$usuario = $request->usuario;

		if($data_final && $data_final){
			$data_inicial = $this->parseDate($data_inicial);
			$data_final = $this->parseDate($data_final, true);
		}

		$itens = ItemPedido::
		select('item_pedidos.*');

		if($data_final && $data_final){
			$itens->whereBetween('item_pedidos.created_at', [$data_inicial,
			$data_final]);
		}


		if($usuario != 'todos'){
			$itens->where('usuario_id', $usuario);
		}

		$itens = $itens->get();

		if($usuario != 'todos'){
			$usuario = Usuario::find($usuario)->first()->nome;
		}

		$p = view('relatorios/relatorio_atendimento')
		->with('data_inicial', $request->data_inicial)
		->with('data_final', $request->data_final)
		->with('usuario', $usuario)
		->with('title', 'Relatório de atendimento')
		->with('itens', $itens);

		return $p;
	}

	public function list($customers)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    //    $sheet->setTitle(__('platform.report.type.customers'));
     //   $sheet->getStyle('A1:F1')->getBorders()->getOutline()->setBorderStyle(true);
        $sheet->setCellValue('A1', __('FORNECEDOR'));
        $sheet->setCellValue('B1', __('CONTA'));
        $sheet->setCellValue('C1', __('EMISSÃO'));
		$sheet->setCellValue('D1', __('VENCIMENTO'));
        $sheet->setCellValue('E1', __('DATA PAGAMENTO'));
        $sheet->setCellValue('F1', __('STATUS'));
        $sheet->setCellValue('G1', __('VALOR DOC'));
		$sheet->setCellValue('H1', __('VALOR PAGO'));
        $line = 2;
        foreach ($customers as $item) {
            $sheet->setCellValueByColumnAndRow(1, $line,  $item->fornecedor->razao_social );
            $sheet->setCellValueByColumnAndRow(2, $line,  $item->dreconta->nome);
            $sheet->setCellValueByColumnAndRow(3, $line, $this->parseDateexcel($item->data_emissao));
            $sheet->setCellValueByColumnAndRow(4, $line, $this->parseDateexcel($item->data_vencimento));

			$sheet->setCellValueByColumnAndRow(5, $line, $this->parseDateexcel($item->data_pagamento));
            if ($item->status == true){
				$sheet->setCellValueByColumnAndRow(6, $line,'Pago');
			}
			else {

				$sheet->setCellValueByColumnAndRow(6, $line,'Pendente');
			}

			$sheet->setCellValueByColumnAndRow(7, $line, $item->valor_integral);
            $sheet->setCellValueByColumnAndRow(8, $line, $item->valor_pago);
            $line++;
        }
        $writer = new Xls($spreadsheet);
        $filename = "report-" . time() . ".xls";

        // echo "<pre>";
		// print_r(storage_path($filename));
		// echo "</pre>";

		 //die();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($filename).'"');
		  $writer->save('php://output');
       // $writer->save(storage_path($filename));

        //$writer->save(storage_path('app/public/report/customer/' . $filename));


    }

	public function listreceber($customers)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    //    $sheet->setTitle(__('platform.report.type.customers'));
     //   $sheet->getStyle('A1:F1')->getBorders()->getOutline()->setBorderStyle(true);
        $sheet->setCellValue('A1', __('CLIENTE'));
        $sheet->setCellValue('B1', __('EMISSÃO'));
		$sheet->setCellValue('C1', __('VENCIMENTO'));
        $sheet->setCellValue('D1', __('STATUS'));
		$sheet->setCellValue('E1', __('DATA RECEBIMENTO'));
        $sheet->setCellValue('F1', __('VALOR DOC'));
		$sheet->setCellValue('G1', __('VALOR RECEBIDO'));
        $line = 2;
        foreach ($customers as $item) {

			if (isset($item->venda->cliente->razao_social)){

				$cliente =  $item->venda->cliente->razao_social;


			}else{

				$cliente =  'CONSUMIDOR';


			}


			$sheet->setCellValueByColumnAndRow(1, $line, $cliente );
            $sheet->setCellValueByColumnAndRow(2, $line, $this->parseDateexcel($item->date_register));
            $sheet->setCellValueByColumnAndRow(3, $line, $this->parseDateexcel($item->data_vencimento));

		     if ($item->status == true){
				$sheet->setCellValueByColumnAndRow(4, $line,'Recebido');
				$sheet->setCellValueByColumnAndRow(5, $line, $this->parseDateexcel($item->data_recebimento));

			}
			else {

				$sheet->setCellValueByColumnAndRow(4, $line,'Pendente');
				$sheet->setCellValueByColumnAndRow(5, $line, '');

			}

			$sheet->setCellValueByColumnAndRow(6, $line, $item->valor_integral);


			if ($item->status == true){

				$sheet->setCellValueByColumnAndRow(7, $line, $item->valor_recebido);

			}
			else {

				$sheet->setCellValueByColumnAndRow(7, $line, 0);

			}

            $line++;
        }
        $writer = new Xls($spreadsheet);
        $filename = "report-" . time() . ".xls";

        // echo "<pre>";
		// print_r(storage_path($filename));
		// echo "</pre>";

		 //die();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($filename).'"');
		  $writer->save('php://output');
       // $writer->save(storage_path($filename));

        //$writer->save(storage_path('app/public/report/customer/' . $filename));


    }



	private function parseDateexcel($date, $plusDay = false){
        if($plusDay == false)
            return date('d-m-Y', strtotime(str_replace("/", "-", $date)));
        else
            return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
    }
    public function filtroApontamento(Request $request)
    {
        $startDate = $request->input('data_inicial');
        $endDate = $request->input('data_final');

        if ($startDate && $endDate) {
            $startDate = $this->parseDate($startDate);
            $endDate = $this->parseDate($endDate, true);
        }

        $config = ConfigNota::first();

        $result = DB::table('produtos as p')
            ->select(
                'p.id',
                'p.nome as produto_nome',
                DB::raw("
                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        JOIN estoquemovpdvs epdv ON epdv.estoquemov_id = m.id
                        WHERE m.produto_id = p.id AND m.tipomov = 'saida' AND m.origem = 'pdv' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) as QtdVenda,

                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        WHERE m.produto_id = p.id AND m.tipomov = 'Entrada' AND m.origem <> 'Extorno de Venda' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) as QtdCompra,

                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        WHERE m.produto_id = p.id AND m.origem = 'Extorno de Venda' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) as QtdExtornoVenda,

                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        JOIN estoquemovvendas ev ON ev.estoquemov_id = m.id
                        WHERE m.produto_id = p.id AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) as QtdPerda,

                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        WHERE m.produto_id = p.id AND m.origem = 'Contagem' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) as QtdAjuste,

                    (COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        JOIN estoquemovcompras ec ON ec.estoquemov_id = m.id
                        WHERE m.produto_id = p.id AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) +
                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        WHERE m.produto_id = p.id AND m.origem = 'Extorno de Venda' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) -
                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        JOIN estoquemovpdvs epdv ON epdv.estoquemov_id = m.id
                        WHERE m.produto_id = p.id AND m.tipomov = 'saida' AND m.origem = 'pdv' AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0) -
                    COALESCE((SELECT SUM(m.quantidade)
                        FROM estoque_movs m
                        JOIN estoquemovvendas ev ON ev.estoquemov_id = m.id
                        WHERE m.produto_id = p.id AND DATE(m.created_at) BETWEEN '$startDate' AND '$endDate'), 0)) as saldo
                ")
            )
            ->join('estoque_movs as m', 'm.produto_id', '=', 'p.id')
            ->whereBetween(DB::raw('DATE(m.created_at)'), [$startDate, $endDate])
            ->where('p.ativo', '=', 1)
            ->groupBy('p.id', 'p.nome')
            ->get();

        if (sizeof($result) == 0) {
            session()->flash("mensagem_erro", "Relatório sem registro!");
            return redirect('/relatorios');
        }

        $p = view('relatorios/relatorio_apontamento')
            ->with('data_inicial', $request->data_inicial)
            ->with('data_final', $request->data_final)
            ->with('result', $result)
            ->with('fantasia', $config->nome_fantasia);

        $domPdf = new Dompdf(["enable_remote" => true]);
        $domPdf->loadHtml($p);

        $domPdf->setPaper("A4", "landscape");
        $domPdf->render();
        return $domPdf->stream("relatorio_de_apontamento.pdf");
    }






}

