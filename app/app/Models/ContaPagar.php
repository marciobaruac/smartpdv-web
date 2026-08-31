<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaPagar extends Model
{
	protected $fillable = [
		'compra_id', 'data_vencimento', 'data_pagamento', 'valor_integral', 'valor_pago', 
		'referencia', 'categoria_id', 'status','data_emissao','forma_pagamento','dreconta_id','fornecedor_id'
	];

	public function compra(){
		return $this->belongsTo(Compra::class, 'compra_id');
	}

	public function categoria(){
		return $this->belongsTo(CategoriaConta::class, 'categoria_id');
	}

	public function dreconta(){
		return $this->belongsTo(DreConta::class, 'dreconta_id');
	}

	public function fornecedor(){
		return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
	}

	public static function filtroData($dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		select('conta_pagars.*')
	
		->orderBy('data_vencimento', 'asc')
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal]);

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}
	public static function filtroDataFornecedor($fornecedor, $dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		select('conta_pagars.*')
	
		->orderBy('conta_pagars.data_pagamento', 'asc')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->join('compras', 'compras.id' , '=', 'conta_pagars.compra_id','left outer')

		->where('fornecedors.razao_social', 'LIKE', "%$fornecedor%")
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal]);

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}

	public static function filtroFornecedor($fornecedor, $status){
		$c = ContaPagar::
		select('conta_pagars.*')
		->orderBy('conta_pagars.data_pagamento', 'asc')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->join('compras', 'compras.id' , '=', 'conta_pagars.compra_id','left outer')
		->where('razao_social', 'LIKE', "%$fornecedor%");

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	}

	public static function filtroStatus($status){
		$c = ContaPagar::
		select('conta_pagars.*')
	
		->orderBy('conta_pagars.data_pagamento', 'asc');

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	}

}
