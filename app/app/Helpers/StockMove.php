<?php

namespace App\Helpers;

use App\Models\Estoque;
use App\Models\Produto;
use App\Models\Estoque_mov;
use App\Models\Estoquemovmanual;
use App\Models\Estoquemovvenda;
use App\Models\Estoquemovcompra;
use App\Models\Estoquemovpdv;
use App\Models\Estoquemovapontamento;

class StockMove {
	public function existStock($productId){
		$p = Estoque
		::where('produto_id', $productId)
		->first();
		return $p != null ? $p : 0;
	}

	public function getStockProduct($productId){
		$stock = $this->existStock($productId);
		return $stock->quantity ?? 0;
	}

	public function pluStock($productId, $quantity, $value = -1, $origem,$descricao,$idorigem){

		
		$dataEstoquemov = [
            'produto_id' => $productId,
			'tipomov'    => 'Entrada',
            'usuario_id' => get_id_user(),
            'quantidade' => $quantity,
            'origem' => $origem,
			'descricao' => $descricao,
			'valor' => $value,
        ];

		$resultmov = Estoque_mov::create($dataEstoquemov);
     
		if ($origem == 'Manual'){

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'estoquemanual_id' => $idorigem,
				
			];
			Estoquemovmanual::create($dataEstoquemov);
	
		}else if ($origem == 'Compra'){

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'estoquecompra_id' => $idorigem,
				
			];
			Estoquemovcompra::create($dataEstoquemov);
	
		} else if ($origem == 'Apontamento'){

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'apontamento_id' => $idorigem,
				
			];
			Estoquemovapontamento::create($dataEstoquemov);
	

		}
	
       
		$quantity = (float)$quantity;
		$stock = $this->existStock($productId);
		if($stock){ // update
			$stock->quantidade += $quantity;
			$stock->valor_compra = $value > -1 ? $value : $stock->valor_compra;
		}else{
			$stock = new Estoque();
			$stock->valor_compra = $value;
			$stock->quantidade = $quantity;
			$stock->produto_id = $productId;
		}
		return $stock->save();
	}

	public function atualizasaldo($productId, $quantity, $value = -1){

		$produto = Produto::find($productId);
		$quantity = (float)$quantity;
		$stock = $this->existStock($productId);
		if($stock){ // update
	
			$stock->quantidade = $quantity;
			$stock->valor_compra = $value > -1 ? $value : $stock->valor_compra;
		}else{
			$stock = new Estoque();
			$stock->valor_compra = $value;
			$stock->quantidade = $quantity;
			$stock->produto_id = $productId;
		}
		return $stock->save();
	}

	public function downStock($productId, $quantity,$value,$origem,$descricao,$idorigem){
		
		$quantity = (float)$quantity;
		$stock = $this->existStock($productId);
		$dataEstoquemov = [
            'produto_id' => $productId,
			'tipomov'    => 'Saida',
            'usuario_id' => get_id_user(),
            'quantidade' => $quantity,
            'origem' => $origem,
			'descricao' => $descricao,
			'valor' => $value,
        ];

		$resultmov = Estoque_mov::create($dataEstoquemov);

		if ($origem == 'Manual'){

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'estoquemanual_id' => $idorigem,
				
			];
			Estoquemovmanual::create($dataEstoquemov);
	
		}else if ($origem == 'Venda'){

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'estoquevenda_id' => $idorigem,
				
			];
			Estoquemovvenda::create($dataEstoquemov);
	
		
		}else if ($origem == 'PDV'){
		
			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'estoquepdv_id' => $idorigem,
				
			];
			Estoquemovpdv::create($dataEstoquemov);
	
		} else if ($origem =='Apontamento') {

			$dataEstoquemov = [
				'estoquemov_id' => $resultmov->id,
				'apontamento_id' => $idorigem,
				
			];
			Estoquemovapontamento::create($dataEstoquemov);

		}
		
		

		if($stock){ // update
	
			$stock->quantidade -= abs($quantity);
			$stock->valor_compra = $value > -1 ? $value : $stock->valor_compra;
		}else{
			$stock = new Estoque();
			$stock->valor_compra = $value;
			$stock->quantidade -=  abs($quantity);
			$stock->produto_id = $productId;
		}
		return $stock->save();
		
	}
}