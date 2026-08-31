<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemNotaComplementar extends Model
{
	protected $fillable = [
		'cod', 'nome', 'ncm', 'cfop', 'valor_unit', 'quantidade', 'notacomplementar_id', 'codBarras', 'unidade_medida', 'cst_csosn', 'cst_pis', 'cst_cofins', 'cst_ipi',
		'perc_icms', 'perc_pis', 'perc_cofins', 'perc_ipi', 'pRedBC', 'baseicmsst', 'perc_icms_st','valor_icms','valor_icms_st','perc_mvast'
	];
}
