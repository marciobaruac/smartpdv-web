<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigCartao extends Model
{
    protected $fillable = [
        'cartao', 'bandeira', 'taxa', 'prazodias'
      
    ];
    use HasFactory;
}
