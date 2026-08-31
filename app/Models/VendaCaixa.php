<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ConfigNota;


class VendaCaixa extends Model
{
	protected $fillable = [
		'cliente_id', 'usuario_id', 'valor_total', 'NFcNumero',
		'natureza_id', 'data_registro', 'chave', 'path_xml', 'estado', 'tipo_pagamento', 'forma_pagamento', 
		'dinheiro_recebido','troco', 'nome', 'cpf', 'observacao', 'desconto', 'acrescimo', 
		'pedido_delivery_id', 'tipo_pagamento_1', 'valor_pagamento_1', 'tipo_pagamento_2', 
		'valor_pagamento_2', 'tipo_pagamento_3', 'valor_pagamento_3', 'bandeira_cartao', 
		'cnpj_cartao', 'cAut_cartao', 'descricao_pag_outros','txcartao','bandeira_cartao_1','bandeira_cartao_2','bandeira_cartao_3','data_cancelamento','justificativa', 'intencao_venda_id'
	];

	public function duplicatas(){
        return $this->hasMany('App\Models\ContaReceber', 'venda_caixa_id', 'id');
    }

	public function itens(){
		return $this->hasMany('App\Models\ItemVendaCaixa', 'venda_caixa_id', 'id');
	}

	public function cliente(){
		return $this->belongsTo(Cliente::class, 'cliente_id');
	}

	public function pedidoDelivery(){
		return $this->belongsTo(PedidoDelivery::class, 'pedido_delivery_id');
	}

	public function natureza(){
		return $this->belongsTo(NaturezaOperacao::class, 'natureza_id');
	}

	public function usuario(){
		return $this->belongsTo(Usuario::class, 'usuario_id');
	}

	public static function tiposPagamento(){
		return [
			'01' => 'Dinheiro',
			'02' => 'Cartão de Débito',
			'03' => 'Cartão de Crédito',
			'04' => 'Pix',
			'05' => 'Conta',
			'07' => 'TEF - Crédito',
			'08' => 'TEF - Débito',
			'09' => 'TEF - Pix',
	        //	'06' => 'Boleto',
			'99' => 'Multiplas Formas Pg'
			
		];
	}

	public static function bandeiras(){
		return [
			'01' => 'Visa',
            '02' => 'Mastercard',
            '03' => 'Elo',
            '04' => 'Amex',
            '05' => 'Hibercard',
		];
	}

	public static function getTipoPagamento($tipo){
		if(isset(VendaCaixa::tiposPagamento()[$tipo])){
			return VendaCaixa::tiposPagamento()[$tipo];
		}else{
			return "Não identificado";
		}
	}

	public static function lastNFCe(){
		$venda = VendaCaixa::
		where('NFcNumero', '!=', 0)
		->orderBy('NFcNumero', 'desc')
		->first();

		if($venda == null) {
			return ConfigNota::first()->ultimo_numero_nfce;
		}
		else{ 
			$configNum = ConfigNota::first()->ultimo_numero_nfce;
			return $configNum > $venda->NFcNumero ? $configNum : $venda->NFcNumero;
		}
	}

	public static function filtroData($dataInicial, $dataFinal){
		return VendaCaixa::
        where('ativo',true)
		->orderBy('id', 'desc')
		->whereBetween('created_at', [$dataInicial, 
			$dataFinal])
		->get();
	}

	public static function filtroCliente($cliente){
		return VendaCaixa::
		where('ativo',true)
		->join('clientes', 'clientes.id' , '=', 'venda_caixas.cliente_id')
		->where('clientes.razao_social', 'LIKE', "%$cliente%")
		->get();
	}

	public static function filtroNFCe($nfce){
		return VendaCaixa::
		where('NFcNumero', $nfce)
		->get();
	}

	public static function filtroValor($valor){
		return VendaCaixa::
		where('valor_total', 'LIKE', "%$valor%")
		->get();
	}

	public static function filtroEstado($estado){
		$c = VendaCaixa::
		where('estado', $estado)
		->where('forma_pagamento', '!=', 'conta_crediario');
		return $c->get();
	}

	public function multiplo(){
		$text = '';
		if($this->valor_pagamento_1 > 0){
			$text .= $this->tipo_pagamento_1 . ' - R$ ' . number_format($this->valor_pagamento_1, 2);
		}

		if($this->valor_pagamento_2 > 0){
			$text .= ' | '.$this->tipo_pagamento_2 . ' - R$ ' . number_format($this->valor_pagamento_2, 2);
		}

		if($this->valor_pagamento_3 > 0){
			$text .= ' | '.$this->tipo_pagamento_3 . ' - R$ ' . number_format($this->valor_pagamento_3, 2);
		}
		return $text;
	}

	public static function tiposPagamentoMulti(){
		return [
			'DINHEIRO',
			'CARTÃO DE DÉBITO',
			'CARTÃO DE CRÉDITO',
			'VALE REFEIÇÃO'
		];
	}



}
