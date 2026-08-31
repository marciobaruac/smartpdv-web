<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estoque_mov extends Model
{
 
    
    protected $fillable = [
		'produto_id','tipomov','origem','descricao','quantidade','valor', 'usuario_id'
	];

	public function produto(){
		return $this->belongsTo(Produto::class, 'produto_id');
	}

	public function usuario(){
		return $this->belongsTo(Usuario::class, 'usuario_id');
	}


}
