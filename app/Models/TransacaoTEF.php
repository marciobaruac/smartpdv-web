<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransacaoTEF extends Model
{
    protected $table = 'transacao_tef';
    protected $guarded = [];

    public static function filtroData($dataInicial, $dataFinal){
		return TransacaoTEF::leftJoin('venda_caixas', 'venda_caixas.intencao_venda_id', '=', 'transacao_tef.intencao_venda_id')
		->select('transacao_tef.*', 'venda_caixas.id as venda_caixa_id', 'venda_caixas.NFcNumero as venda_nfce_numero')
		->orderBy('transacao_tef.id', 'desc')
		->whereBetween('transacao_tef.created_at', [$dataInicial, 
			$dataFinal])
		->get();
	}

}
