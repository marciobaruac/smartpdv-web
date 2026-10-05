<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estoquemovvenda extends Model
{
    protected $fillable = [
		'estoquemov_id','estoquevenda_id'
	];
}
