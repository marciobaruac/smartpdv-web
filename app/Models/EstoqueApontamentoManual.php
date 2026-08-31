<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstoqueApontamentoManual extends Model
{
    protected $fillable = [
	'tipo','quantidade','produto_id','usuario_id'
	];

	public function produto(){
		return $this->belongsTo(Produto::class, 'produto_id');
	}

	public function usuario(){
		return $this->belongsTo(Usuario::class, 'usuario_id');
	}
}
