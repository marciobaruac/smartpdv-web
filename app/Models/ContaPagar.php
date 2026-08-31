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

	public static function filtroDataContaDre($dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "F"')
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal]);

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}
	public static function filtroDataContaVariavel($dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_pago,valor_integral)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "V"')
		->whereRaw(' conta_pagars.dreconta_id <> 0')
		//->whereRaw('dre_contas.id <> 0')
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal]);
		


		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}
	public static function filtroDataContaCompra($dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw(' sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->whereRaw(' conta_pagars.dreconta_id = 0')
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal]);

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->first();
		
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

	
	



	public static function filtroDataFornecedorCustoFixo($fornecedor, $dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "F"')	
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal])
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");
	

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}

	public static function filtroDataFornecedorCustoVariavel($fornecedor, $dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "V"')
		->whereRaw(' conta_pagars.dreconta_id <> 0')
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal])
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");
	
		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		return $c->get();
	}

	public static function filtroDataFornecedorCompra($fornecedor, $dataInicial, $dataFinal, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma')
			
		->whereBetween('data_pagamento', [$dataInicial, 
			$dataFinal])
		->whereRaw(' conta_pagars.dreconta_id = 0')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");

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

	public static function filtroFornecedorCustoFixo($fornecedor, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "F"')
		->whereRaw(' conta_pagars.dreconta_id <> 0')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	
	}

	public static function filtroFornecedorCustoVariavel($fornecedor, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "V"')
		->whereRaw(' conta_pagars.dreconta_id <> 0')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	}

	

	public static function filtroFornecedorCompra($fornecedor, $status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma')
		->whereRaw(' conta_pagars.dreconta_id = 0')
		->join('fornecedors', 'fornecedors.id' , '=', 'conta_pagars.fornecedor_id')
		->where('razao_social', 'LIKE', "%$fornecedor%");

		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->first();
	
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


	public static function filtroStatusCustoFixo($status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_pago,valor_integral)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "F"')
		->whereRaw(' conta_pagars.dreconta_id <> 0');
	
		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	}

	public static function filtroStatusCustoVariavel($status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma, dre_contas.nome')
		->join('dre_contas', 'dre_contas.id' , '=', 'conta_pagars.dreconta_id')
		->groupBy('dre_contas.nome')
		->orderBy('soma', 'desc')
		->whereRaw('tipo = "V"')
		->whereRaw(' conta_pagars.dreconta_id <> 0');
	
		
		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->get();
	}

	public static function filtroStatusCompra($status){
		$c = ContaPagar::
		selectRaw('  sum(IF(valor_pago = 0 ,valor_integral,valor_pago)) as soma')
		->whereRaw(' conta_pagars.dreconta_id = 0');
		
		if($status == 'pago'){
			$c->where('conta_pagars.status', true);
		} else if($status == 'pendente'){
			$c->where('conta_pagars.status', false);
		}
		
		return $c->first();
	}





}
