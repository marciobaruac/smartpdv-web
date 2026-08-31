<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DreConta extends Model
{
    use HasFactory;

    protected $fillable = [
		'nome', 'tipo'
	];

  public static function cTipo(){
		return [

			'F' => 'FIXO',
			'V' => 'VARIÁVEL',
	
    ]	;
    }

}
