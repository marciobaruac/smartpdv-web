<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NfceInutilizacao extends Model
{
    protected $table = 'nfce_inutilizacoes';

    protected $fillable = [
        'serie',
        'numero_inicio',
        'numero_final',
        'ano',
        'justificativa',
        'protocolo',
        'status',
    ];
}
