<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class estoquemovcompra extends Model
{
    protected $fillable = [
		'estoquemov_id','estoquecompra_id'
	];
}
