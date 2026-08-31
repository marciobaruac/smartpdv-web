<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompraManual;
use App\Models\ItemCompra;
use App\Models\Compra;
use App\Models\Produto;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Helpers\StockMove;
use App\Http\Controllers\ProductController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CompraManualController extends Controller
{
	public function __construct(){
		$this->middleware(function ($request, $next) {
			$value = session('user_logged');
			if(!$value){
				return redirect("/login");
			}else{
				if($value['acesso_compra'] == 0){
					return redirect("/sempermissao");
				}
			}
			return $next($request);
		});
	}

	public function index(){
		$fornecedores = Fornecedor::orderBy('razao_social')->get();
		$produtos = Produto::orderBy('nome')->get();
		return view('compraManual/register')
		->with('compraManual', true)
		->with('fornecedores', $fornecedores)
		->with('produtos', $produtos)
		->with('title', 'Compra Manual');
	}

	public function salvar(Request $request){
		DB::beginTransaction();
		$compra = $request->compra;
		$result = Compra::create([
			'fornecedor_id' => $compra['fornecedor'],
			'usuario_id' => get_id_user(),
			'nf' => '0',
			'observacao' => $compra['observacao'] != null ? $compra['observacao'] : '',
			'valor' => str_replace(",", ".", $compra['total']),
			'desconto' => $compra['desconto'] != null ?
			str_replace(",", ".", $compra['desconto']) : 0,
			'xml_path' => '',
			'estado' => 'NOVO',
			'chave' => '',
			'numero_emissao' => 0
		]);

		$this->salvarItens($result->id, $compra['itens']);

	    $this->salvarParcela($result->id, $compra['fatura'], $compra['fornecedor']);

	    DB::commit();
		echo json_encode($result);
	}

	private function salvarItens($id, $itens){

		$stockMove = new StockMove();
		$produto   = new ProductController;




		foreach($itens as $i){
			$prod = Produto::where('id', (int) $i['codigo'])->first();
			$result = ItemCompra::create([
				'compra_id' => $id,
				'produto_id' => (int) $i['codigo'],
				'quantidade' =>  str_replace(",", ".", $i['quantidade']),
				'valor_unitario' => str_replace(",", ".", $i['valor']),
				'unidade_compra' => $prod['unidade_compra'],
			]);


			$stockMove->pluStock(
                (int) $i['codigo'],
                str_replace(",", ".", $i['quantidade'] * $prod->conversao_unitaria),
                'Compra',
                'Obs. ' . 'Compra' . '-' . 'Mov: ' . $result->compra_id,
                $result->id
            );


			//$produto ->updatecustoproduto($i['codigo'], str_replace(",", ".", $i['valor']));
			$produto ->updatecustoproduto($i['codigo'], $result->valor_unitario/$prod->conversao_unitaria);
		}

		return true;
	}

	public function salvarParcela($id, $fatura,$fornecedor){
		$cont = 0;
		$valor = 0;
		foreach($fatura as $parcela){
			$cont = $cont+1;
			$valorParcela = str_replace(".", "", $parcela['valor']);
			$valorParcela = str_replace(",", ".", $valorParcela);

			$result = ContaPagar::create([
				'compra_id' => $id,
				'data_vencimento' => $this->parseDate($parcela['data']),
				'data_pagamento' => $this->parseDate($parcela['data']),
				'valor_integral' => $valorParcela,
				'valor_pago' => 0,
				'status' => false,
				'referencia' => "Parcela $cont da Compra código $id",
				'categoria_id' => 1,
				'fornecedor_id' => $fornecedor,
				'data_emissao'  =>  date('Y/m/d'),
			]);
		}
		return true;
	}

	private function parseDate($date){
		return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
	}

	public function ultimaCompra($produtoId){
		$item = ItemCompra::
		where('produto_id', $produtoId)
		->orderBy('id', 'desc')
		->get();

		if(count($item) > 0){
			$last = $item[0];
			$r = [
				'fornecedor' => $last->compra->fornecedor->razao_social,
				'valor' => $last->valor_unitario,
				'quantidade' => $last->quantidade,
				'data' => Carbon::parse($last->compra->created_at)->format('d/m/Y H:i:s')
			];
			echo json_encode($r);
		}else{
			echo json_encode(null);
		}
	}

}
