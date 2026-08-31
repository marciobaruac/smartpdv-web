<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoListaPreco extends Model
{
	protected $fillable = [
		'lista_id', 'produto_id', 'percentual_lucro', 'valor','quantidade_minima','referencia','ordem'
	];

	public function produto(){
		return $this->belongsTo(Produto::class, 'produto_id')->where('ativo',true);
	}

	public function lista(){
		return $this->belongsTo(ListaPreco::class, 'lista_id');
	}
}
