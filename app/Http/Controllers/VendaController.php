<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venda;
use App\Models\NaturezaOperacao;
use App\Models\ItemVenda;
use App\Models\Produto;

use App\Models\Pedido;
use App\Models\Categoria;
use App\Models\Tributacao;
use App\Models\ConfigNota;
use App\Models\Certificado;
use App\Models\CreditoVenda;
use App\Models\ContaReceber;
use App\Models\Transportadora;
use App\Models\Frete;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\ListaPreco;
use App\Helpers\StockMove;
use App\Services\NFService;
use NFePHP\DA\NFe\Danfe;
use Dompdf\Dompdf;
use App\Models\ComissaoVenda;
use App\Models\ConfigCartao;
use phpDocumentor\Reflection\PseudoTypes\False_;
use Illuminate\Support\Facades\DB;

class VendaController extends Controller
{
	public function __construct(){
		$this->middleware(function ($request, $next) {
			$value = session('user_logged');
			if(!$value){
				return redirect("/login");
			}
			return $next($request);
		});
	}

	public function lista(){
		$menos30 = $this->menos30Dias();
		$date = date('d/m/Y');

		$vendas = Venda::
	    where('ativo',true)
	    ->whereBetween('data_registro', [$this->parseDate($date) ,
	    $this->parseDate($date,true) ])
		->orderBy('data_registro', 'DESC')

		->paginate(20);



	//	$vendas = Venda::filtroData(
	//		$this->parseDate($date),
	//		$this->parseDate($date, true),
	//		'TODOS');

		$certificado = Certificado::first();

		return view("vendas/list")
		->with('vendas', $vendas)
		->with('nf', true)
		->with('links', true)
		->with('dataInicial', $date)
		->with('dataFinal', $date)
		->with('certificado', $certificado)
		->with('title', "Lista de Vendas");

	}

	public function nova(){
		$config = ConfigNota::first();
		if($config == null){
			session()->flash("mensagem_erro", "Configure o emitente!");
			return redirect('configNF');
		}
		$lastNF = Venda::lastNF();

		$naturezas = NaturezaOperacao::where('simplesremessa',false)
        ->get();
		$config = ConfigNota::first();
		$configcartao = ConfigCartao::first();
		$categorias = Categoria::all();
		$produtos = Produto::
		where('ativo', true)
		->orderBy('nome','asc')

		->get();
		$tributacao = Tributacao::first();
		$clientes = Cliente::all();
		$tiposPagamento = Venda::tiposPagamento();

		if(count($naturezas) == 0 || count($produtos) == 0 || $config == null || count($categorias) == 0 || $tributacao == null || count($clientes) == 0){

			return view("vendas/alerta")
			->with('produtos', count($produtos))
			->with('categorias', count($categorias))
			->with('clientes', count($clientes))
			->with('naturezas', $naturezas)
			->with('config', $config)
			->with('tributacao', $tributacao)
			->with('configcartao', $configcartao)

			->with('title', "Validação para Emitir");
		}else{

			$transportadoras = Transportadora::all();

			foreach($clientes as $c){
				$c->cidade;
			}
			foreach($produtos as $p){
				$p->listaPreco;
				$p->estoque;
			}

			return view("vendas/register")
			->with('naturezas', $naturezas)
			->with('vendaJs', true)
			->with('config', $config)
			->with('clientes', $clientes)
			->with('produtos', $produtos)
			->with('transportadoras', $transportadoras)
			->with('tiposPagamento', $tiposPagamento)
			->with('lastNF', $lastNF)
			->with('listaPreco', ListaPreco::all())
			->with('title', "Nova Venda");
		}
	}


	public function novasimplesremessa(){
		$config = ConfigNota::first();
		if($config == null){
			session()->flash("mensagem_erro", "Configure o emitente!");
			return redirect('configNF');
		}
		$lastNF = Venda::lastNF();

		$naturezas = NaturezaOperacao::where('simplesremessa',true)
        ->get();


		$config = ConfigNota::first();
		$categorias = Categoria::all();
		$produtos = Produto::
		where('valor_venda', '>', 0)
		->where('ativo', true)
		->orderBy('nome','asc')

		->get();
		$tributacao = Tributacao::first();
		$clientes = Cliente::all();
		$tiposPagamento = Venda::tiposPagamento();

		if(count($naturezas) == 0 || count($produtos) == 0 || $config == null || count($categorias) == 0 || $tributacao == null || count($clientes) == 0){

			return view("vendas/alerta")
			->with('produtos', count($produtos))
			->with('categorias', count($categorias))
			->with('clientes', count($clientes))
			->with('naturezas', $naturezas)
			->with('config', $config)
			->with('tributacao', $tributacao)
			->with('title', "Validação para Emitir");
		}else{

			$transportadoras = Transportadora::all();

			foreach($clientes as $c){
				$c->cidade;
			}
			foreach($produtos as $p){
				$p->listaPreco;
				$p->estoque;
			}

			return view("vendas/registersimples")
			->with('naturezas', $naturezas)
			->with('simplesJs', true)
			->with('config', $config)
			->with('clientes', $clientes)
			->with('produtos', $produtos)
			->with('transportadoras', $transportadoras)
			->with('tiposPagamento', $tiposPagamento)
			->with('lastNF', $lastNF)
			->with('listaPreco', ListaPreco::all())
			->with('title', "Nova Simples Remessa");
		}
	}
	public function detalhar($id){
		$venda = Venda::
		where('id', $id)
		->first();

		$menos30 = $this->menos30Dias();
		$date = date('d/m/Y');

		return view("vendas/detalhe")
		->with('venda', $venda)
		->with('title', "Detalhe de Venda $id");
	}

	public function delete($id){
		DB::beginTransaction();

		$venda = Venda::
		where('id', $id)
		->first();

	if 	(!$venda->simplesremessa){
		$stockMove = new StockMove();
		foreach($venda->itens as $i){
			// baixa de estoque



				$stockMove->pluStock($i->produto->id,$i->quantidade, $i->produto->valor_compra, 'Extorno de Venda','Obs. '. 'N: '.'-'.  'Mov: '. $venda->id,  $i->id );


			}
		}
		foreach($venda->duplicatas as $c){

			$c->ativo = false;
			$c->save();
		}


		//$this->removerDuplicadas($venda);
		$venda->ativo = false;


		if($venda->save()){
            session()->flash('mensagem_sucesso', 'Venda Cancelada!');
            DB::commit();
        }else{
            session()->flash('mensagem_erro', 'Erro!');
        }




		return redirect('/vendas/lista');
	}

	private function removerDuplicadas($venda){
		foreach($venda->duplicatas as $dp){
			$c = ContaReceber::
			where('id', $dp->id);

			$c->ativo = false;

			$c->save();


		}
	}

	function sanitizeString($str){
		return preg_replace('{\W}', ' ', preg_replace('{ +}', ' ', strtr(
			utf8_decode(html_entity_decode($str)),
			utf8_decode('ÀÁÃÂÉÊÍÓÕÔÚÜÇÑàáãâéêíóõôúüçñ'),
			'AAAAEEIOOOUUCNaaaaeeiooouucn')));
	}

	public function salvar(Request $request){

		DB::beginTransaction();

		$venda = $request->venda;
		$valorFrete = str_replace(".", "", $venda['valorFrete'] ?? 0);
		$valorFrete = str_replace(",", ".", $valorFrete );
		$vol = $venda['volume'];

		if($vol['pesoL']){
			$pesoLiquido = str_replace(",", ".", $vol['pesoL']);
			// $pesoLiquido = str_replace(",", ".", $pesoLiquido);
		}else{
			$pesoLiquido = 0;
		}

		if($vol['pesoB']){
			$pesoBruto = str_replace(",", ".", $vol['pesoB']);
			// $pesoBruto = str_replace(",", ".", $pesoBruto);
		}else{
			$pesoBruto = 0;
		}

		if($vol['qtdVol']){
			$qtdVol = str_replace(",", ".", $vol['qtdVol']);
			// $qtdVol = str_replace(",", ".", $qtdVol);
		}else{
			$qtdVol = 0;
		}

		$frete = null;
		if($venda['frete'] != '9'){
			$frete = Frete::create([
				'placa' => $venda['placaVeiculo'] ?? '',
				'valor' => $valorFrete ?? 0,
				'tipo' => (int)$venda['frete'],
				'qtdVolumes' => $qtdVol?? 0,
				'uf' => $venda['ufPlaca'] ?? '',
				'numeracaoVolumes' => $vol['numeracaoVol'] ?? '0',
				'especie' => $vol['especie'] ?? '*',
				'peso_liquido' => $pesoLiquido ?? 0,
				'peso_bruto' => $pesoBruto ?? 0
			]);
		}


		$totalVenda = str_replace(",", ".", $venda['total']);
        $totalCusto = str_replace(",", ".", $venda['totalcusto']);
		$txcartao   = 0;
		$desconto = 0;
		$txantcartao =0;
		if($venda['desconto']){
			$desconto = str_replace(".", "", $venda['desconto']);
			$desconto = str_replace(",", ".", $desconto);
		}


		if  ($venda['formaPagamento'] == '02'){

			$infcartao =  ConfigCartao::where('cartao', 'Debito')
			->where('bandeira',$venda['bandeira_cartao'])
			->first();

			if ($infcartao){
				$taxa = $infcartao->taxa;
			  }
			  else{
				$taxa = 0;
			  }

			$txcartao = ($totalVenda * $taxa)/100;



		}

		if ($venda['formaPagamento'] == '03'){

			$total = count($venda['fatura']);

			$ptxcartao = 0;

			switch($total){

				case 1:
					$ptxcartao = 1.90;
					break;

				case  2:
					$ptxcartao = 1.80;
					break;

				case  3:
				    $ptxcartao = 1.80;
				break;

				case  4:
				    $ptxcartao = 1.80;
				break;

				case  5:
				    $ptxcartao = 1.80;
				break;

				case  6:
				    $ptxcartao = 1.80;
				break;

				case  7:
				    $ptxcartao = 2.50;
				break;

				case  8:
				    $ptxcartao = 2.50;
				break;

				case  9:
				    $ptxcartao = 2.50;
				break;

				case  10:
				    $ptxcartao = 2.50;
				break;


				case  11:
				    $ptxcartao = 2.50;
				break;

				case  12:
				    $ptxcartao = 2.50;
				break;









			}



			$txcartao = ($totalVenda * $ptxcartao)/100;

			$txantcartao = ($totalVenda * 1.80)/100;

		}





		$result = Venda::create([
			'cliente_id' => $venda['cliente'],
			'transportadora_id' => $venda['transportadora'],
			'forma_pagamento' => $venda['tipoPagamento'],
			'tipo_pagamento' => $venda['tipoPagamento'],
			'usuario_id' => get_id_user(),
			'valor_total' => $totalVenda,
			'custo_total' =>$totalCusto,
			'desconto' => $desconto,
			'frete_id' => $frete != null ? $frete->id : null,
			'NfNumero' => 0,
			'natureza_id' => $venda['naturezaOp'],
			'path_xml' => '',
			'chave' => '',
			'sequencia_cce' => 0,
			'observacao' => $this->sanitizeString($venda['observacao']) ?? '',
			'estado' => 'DISPONIVEL',
			'bandeira_cartao' => $venda['bandeira_cartao'],
			'cAut_cartao' => $venda['cAut_cartao'] ?? '',
			'cnpj_cartao' => $venda['cnpj_cartao'] ?? '',
			'descricao_pag_outros' => $venda['descricao_pag_outros'] ?? '',
			'txcartao' => $txcartao,
			'txantcartao' => $txantcartao

		]);

		if($venda['formaPagamento'] == 'conta_crediario'){
			$credito = CreditoVenda::create([
				'venda_id' => $result->id,
				'cliente_id' => $venda['cliente'],
				'status' => false,
			]);
		}

		$itens = $venda['itens'];
		$stockMove = new StockMove();
		$totalcusto = 0;
		foreach ($itens as $i) {


			$prod = Produto
			::where('id', $i['codigo'])
			->first();

			if ($prod->composto == 0){

			    $custoproduto = $prod->valor_compra;

			}
			else{

				$custoproduto = $prod->custoprodutofabricado($prod->id);
			}



			$resultvendaitem= ItemVenda::create([
				'venda_id' => $result->id,
				'produto_id' => (int) $i['codigo'],
				'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
				'valor' => (float) str_replace(",", ".", $i['valor']),
				'custo_total' => (float) str_replace(",", ".", $i['quantidade']) * $custoproduto ,

				'observacao' => $i['obs'] ?? ''
			]);

			$totalcusto +=  $custoproduto;

			//if(!empty($prod->receita)){
				//baixa por receita
			//	$receita = $prod->receita;
			//////	foreach($receita->itens as $rec){

				//	if(!empty($rec->produto->receita)){ // se item da receita for receita
				//		$receita2 = $rec->produto->receita;

				//		foreach($receita2->itens as $rec2){
					//		$stockMove->downStock(
					//			$rec2->produto_id,
					//			(float) str_replace(",", ".", $i['quantidade']) *
					//			($rec2->quantidade/$receita2->rendimento)
					//		);
				//		}
				//	}else{

					//	$stockMove->downStock(
					//		$rec->produto_id,
					//		(float) str_replace(",", ".", $i['quantidade']) *
					//		($rec->quantidade/$receita->rendimento)
					//	);
					//}
				//}
			//}else{
			//	$stockMove->downStock((int) $i['codigo'],str_replace(",", ".", $i['quantidade']),str_replace(",", ".", $i['custo']),'Venda','Obs. '. 'N: '.'-'.  'Mov: '. $result->id, $resultvendaitem->id);

		//}

	    $stockMove->downStock((int) $i['codigo'],str_replace(",", ".", $i['quantidade']),$custoproduto,'Venda','Obs. '. 'N: '.'-'.  'Mov: '. $result->id, $resultvendaitem->id);


	    }

		if(isset($venda['receberContas'])){
			$receberContas = $venda['receberContas'];
			foreach($receberContas as $r){
				$c = CreditoVenda::where('id', $r)
				->first();
				$c->status = true;
				$c->save();
			}
		}

		$fatura        = $venda['fatura'];

		foreach ($fatura as $f) {
		    $valorParcela = str_replace(",", ".", $f['valor']);
			$valorrecebido = str_replace(",", ".", $f['valor']);

			if( $venda['formaPagamento'] == '01' ||  $venda['formaPagamento']== '04'){

			   $vencimento  = $this->parseDate($f['data']);
               $recebimento = $this->parseDate($f['data']);
			}
			else
			{

			   $vencimento  = $this->verificadiautil($this->parseDate($f['data']));
			   $recebimento = $this->verificadiautil($this->parseDate($f['data']));

			}

			if( $venda['formaPagamento'] == '02') {

				$infcartao =  ConfigCartao::where('cartao', 'Debito')
                ->where('bandeira',$venda['bandeira_cartao'])
                ->first();


				if ($infcartao){
					$taxa = $infcartao->taxa;
				  }
				  else{
					$taxa = 0;
				}

				$txcartaodp = ($totalVenda * $taxa)/100;
				$valorrecebido   = $valorrecebido - $txcartaodp  ;
			}

			if( $venda['formaPagamento'] == '03') {


				$txcartaop = ($valorrecebido * $ptxcartao)/100;
				$txantcartaop = ($valorrecebido * 1.80)/100;

				$valorrecebido   = $valorrecebido -  ($txcartaop+$txantcartaop) ;
				$datafaturamento =  date('Y/m/d');
				$vencimento   = date('Y/m/d', strtotime('+ 2 days',strtotime($datafaturamento)));
                $vencimento   = $this->verificadiautil($vencimento);


				$vencimento  = $this->verificadiautil($this->parseDate($vencimento));
				$recebimento = $this->verificadiautil($this->parseDate($vencimento));


			 }


            if( $venda['formaPagamento'] == '05'  ||  $venda['formaPagamento'] == '06'){

				$status = false;

			}
			else {
				$status = true;

			}



			$resultFatura = ContaReceber::create([
					'venda_id' => $result->id,



					'data_vencimento'  => $vencimento ,
					'data_recebimento' => $recebimento ,

					'forma_recebimento'  =>  $venda['formaPagamento'],

					'valor_integral' => $valorParcela,
					'valor_recebido' => $valorrecebido,
					'status' => $status ,
					'referencia' => "Parcela, ".$f['numero'].", da Venda " . $result->id,
					'categoria_id' => 2,
					'usuario_id' => get_id_user()
				]);
			}

		//salvar Comissao
		$usuario = Usuario::find(get_id_user());
		if(isset($usuario->funcionario)){
			$percentual_comissao = $usuario->funcionario->percentual_comissao;
			$valorComissao = ($totalVenda * $percentual_comissao) / 100;
			ComissaoVenda::create(
				[
					'funcionario_id' => $usuario->funcionario->id,
					'venda_id' => $result->id,
					'tabela' => 'vendas',
					'valor' => $valorComissao,
					'status' => 0
				]
			);
		}

		$result->custo_total = $totalcusto;

		$result->save();

		DB::commit();


		echo json_encode($result);
	}

	public function salvarsimples(Request $request){

		DB::beginTransaction();
		$venda = $request->venda;
		$valorFrete = str_replace(".", "", $venda['valorFrete'] ?? 0);
		$valorFrete = str_replace(",", ".", $valorFrete );
		$vol = $venda['volume'];

		if($vol['pesoL']){
			$pesoLiquido = str_replace(",", ".", $vol['pesoL']);
			// $pesoLiquido = str_replace(",", ".", $pesoLiquido);
		}else{
			$pesoLiquido = 0;
		}

		if($vol['pesoB']){
			$pesoBruto = str_replace(",", ".", $vol['pesoB']);
			// $pesoBruto = str_replace(",", ".", $pesoBruto);
		}else{
			$pesoBruto = 0;
		}

		if($vol['qtdVol']){
			$qtdVol = str_replace(",", ".", $vol['qtdVol']);
			// $qtdVol = str_replace(",", ".", $qtdVol);
		}else{
			$qtdVol = 0;
		}

		$frete = null;
		if($venda['frete'] != '9'){
			$frete = Frete::create([
				'placa' => $venda['placaVeiculo'] ?? '',
				'valor' => $valorFrete ?? 0,
				'tipo' => (int)$venda['frete'],
				'qtdVolumes' => $qtdVol?? 0,
				'uf' => $venda['ufPlaca'] ?? '',
				'numeracaoVolumes' => $vol['numeracaoVol'] ?? '0',
				'especie' => $vol['especie'] ?? '*',
				'peso_liquido' => $pesoLiquido ?? 0,
				'peso_bruto' => $pesoBruto ?? 0
			]);
		}


		$totalVenda = str_replace(",", ".", $venda['total']);
        $totalCusto = str_replace(",", ".", $venda['totalcusto']);
		$txcartao   = 0;
		$desconto = 0;
		$txantcartao =0;
		if($venda['desconto']){
			$desconto = str_replace(".", "", $venda['desconto']);
			$desconto = str_replace(",", ".", $desconto);
		}










		$result = Venda::create([
			'cliente_id' => $venda['cliente'],
			'transportadora_id' => $venda['transportadora'],
			'forma_pagamento' => $venda['tipoPagamento'],
			'tipo_pagamento' => $venda['tipoPagamento'],
			'usuario_id' => get_id_user(),
			'valor_total' => $totalVenda,
			'custo_total' =>$totalCusto,
			'desconto' => $desconto,
			'frete_id' => $frete != null ? $frete->id : null,
			'NfNumero' => 0,
			'natureza_id' => $venda['naturezaOp'],
			'path_xml' => '',
			'chave' => '',
			'sequencia_cce' => 0,
			'observacao' => $this->sanitizeString($venda['observacao']) ?? '',
			'estado' => 'DISPONIVEL',
			'bandeira_cartao' => $venda['bandeira_cartao'],
			'cAut_cartao' => $venda['cAut_cartao'] ?? '',
			'cnpj_cartao' => $venda['cnpj_cartao'] ?? '',
			'descricao_pag_outros' => $venda['descricao_pag_outros'] ?? '',
			'txcartao' => $txcartao,
			'txantcartao' => $txantcartao,
			'simplesremessa'=>true
		]);



		$itens = $venda['itens'];

		foreach ($itens as $i) {
			$resultvendaitem = ItemVenda::create([
				'venda_id' => $result->id,
				'produto_id' => (int) $i['codigo'],
				'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
				'valor' => (float) str_replace(",", ".", $i['valor']),
				'custo_total' => (float) str_replace(",", ".", $i['quantidade']) * str_replace(",", ".", $i['custo']) ,

				'observacao' => $i['obs'] ?? ''
			]);



		}




		DB::commit();
		echo json_encode($result);
	}

	public function atualizar(Request $request){
		$request = $request->venda;
		$venda_id = $request['venda_id'];
		$venda = $vendaAnterior = Venda::find($venda_id);

		$valorFrete = str_replace(".", "", $request['valorFrete'] ?? 0);
		$valorFrete = str_replace(",", ".", $valorFrete );

		$vol = $request['volume'];

		if($vol['pesoL']){
			$pesoLiquido = str_replace(".", "", $vol['pesoL']);
			$pesoLiquido = str_replace(",", ".", $pesoLiquido);
		}else{
			$pesoLiquido = 0;
		}

		if($vol['pesoB']){
			$pesoBruto = str_replace(".", "", $vol['pesoB']);
			$pesoBruto = str_replace(",", ".", $pesoBruto);
		}else{
			$pesoBruto = 0;
		}

		if($vol['qtdVol']){
			$qtdVol = str_replace(".", "", $vol['qtdVol']);
			$qtdVol = str_replace(",", ".", $qtdVol);
		}else{
			$qtdVol = 0;
		}

		$frete = null;
		if($request['frete'] != '9'){
			$frete = Frete::create([
				'placa' => $request['placaVeiculo'] ?? '',
				'valor' => $valorFrete ?? 0,
				'tipo' => (int)$request['frete'],
				'qtdVolumes' => $qtdVol ?? 0,
				'uf' => $request['ufPlaca'],
				'numeracaoVolumes' => $vol['numeracaoVol'] ?? '0',
				'especie' => $vol['especie'] ?? '*',
				'peso_liquido' => $pesoLiquido ?? 0,
				'peso_bruto' => $pesoBruto ?? 0
			]);
		}

		$totalVenda = str_replace(",", ".", $request['total']);

		$desconto = 0;
		if($request['desconto']){
			$desconto = str_replace(".", "", $request['desconto']);
			$desconto = str_replace(",", ".", $desconto);
		}

		$venda->transportadora_id = $request['transportadora'];
		$venda->forma_pagamento = $request['formaPagamento'];
		$venda->tipo_pagamento = $request['tipoPagamento'];
		$venda->usuario_id = get_id_user();
		$venda->valor_total = $totalVenda;
		$venda->desconto = $desconto;
		$venda->frete_id = $frete != null ? $frete->id : null;
		$venda->NfNumero = 0;
		$venda->natureza_id = $request['naturezaOp'];
		$venda->observacao = $this->sanitizeString($request['observacao']) ?? '';

		$venda->save();
		$itens = $request['itens'];
		$this->reverteEstoque($venda->itens);
		$this->deleteItens($venda);
		$stockMove = new StockMove();
		foreach ($itens as $i) {
			ItemVenda::create([
				'venda_id' => $venda->id,
				'produto_id' => (int) $i['codigo'],
				'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
				'valor' => (float) str_replace(",", ".", $i['valor'])
			]);

			$prod = Produto
			::where('id', $i['codigo'])
			->first();

			if(!empty($prod->receita)){
				//baixa por receita
				$receita = $prod->receita;
				foreach($receita->itens as $rec){

					if(!empty($rec->produto->receita)){ // se item da receita for receita
						$receita2 = $rec->produto->receita;

						foreach($receita2->itens as $rec2){
							$stockMove->downStock(
								$rec2->produto_id,
								(float) str_replace(",", ".", $i['quantidade']) *
								($rec2->quantidade/$receita2->rendimento)
							);
						}
					}else{

						$stockMove->downStock(
							$rec->produto_id,
							(float) str_replace(",", ".", $i['quantidade']) *
							($rec->quantidade/$receita->rendimento)
						);
					}
				}
			}else{
				$stockMove->downStock(
					(int) $i['codigo'], (float) str_replace(",", ".", $i['quantidade']));
			}
		}

		$this->deleteFatura($venda);
		$resultFatura = null;
		if($request['formaPagamento'] != 'a_vista' && $request['formaPagamento'] != 'conta_crediario'){
			$fatura = $request['fatura'];

			foreach ($fatura as $f) {
				$valorParcela = str_replace(",", ".", $f['valor']);

				$resultFatura = ContaReceber::create([
					'venda_id' => $venda->id,
					'data_vencimento' => $this->parseDate($f['data']),
					'data_recebimento' => $this->parseDate($f['data']),
					'valor_integral' => $valorParcela,
					'valor_recebido' => 0,
					'status' => false,
					'referencia' => "Parcela, ".$f['numero'].", da Venda " . $venda->id,
					'categoria_id' => 2,
				]);
			}
		}

		echo json_encode($resultFatura);

	}

	private function reverteEstoque($itens){
		$stockMove = new StockMove();
		foreach($itens as $i){
			if(!empty($i->produto->receita)){
				//baixa por receita
				$receita = $i->produto->receita;
				foreach($receita->itens as $rec){

					if(!empty($rec->produto->receita)){ // se item da receita for receita
						$receita2 = $rec->produto->receita;
						foreach($receita2->itens as $rec2){
							$stockMove->pluStock(
								$rec2->produto_id,
								(float) str_replace(",", ".", $i->quantidade) *
								($rec2->quantidade/$receita2->rendimento)
							);
						}
					}else{

						$stockMove->pluStock(
							$rec->produto_id,
							(float) str_replace(",", ".", $i->quantidade) *
							($rec->quantidade/$receita->rendimento)
						);
					}
				}
			}else{
				$stockMove->pluStock(
					$i->produto_id, (float) str_replace(",", ".", $i->quantidade));
			}
		}
	}

	private function deleteItens($venda){
		ItemVenda::where('venda_id', $venda->id)->delete();
	}

	private function deleteFatura($venda){
		ContaReceber::where('venda_id', $venda->id)->delete();
	}

	public function salvarCrediario(Request $request){
		$venda = $request->venda;
		$valorFrete = 0;

		$totalVenda = str_replace(",", ".", $venda['valor_total']);

		$desconto = 0;

		$result = Venda::create([
			'cliente_id' => $venda['cliente'],
			'transportadora_id' => null,
			'forma_pagamento' => 'conta_crediario',
			'tipo_pagamento' => '05',
			'usuario_id' => get_id_user(),
			'valor_total' => $totalVenda,
			'desconto' => $desconto,
			'frete_id' => null,
			'NfNumero' => 0,
			'natureza_id' => 1,
			'path_xml' => '',
			'chave' => '',
			'sequencia_cce' => 0,
			'observacao' => '',
			'estado' => 'DISPONIVEL'
		]);


		$credito = CreditoVenda::create([
			'venda_id' => $result->id,
			'cliente_id' => $venda['cliente'],
			'status' => false,
		]);

		if($venda['codigo_comanda'] > 0){
			$pedido = Pedido::
			where('comanda', $venda['codigo_comanda'])
			->where('status', 0)
			->where('desativado', 0)
			->first();

			$pedido->status = 1;
			$pedido->desativado = 1;
			$pedido->save();
		}


		$itens = $venda['itens'];
		$stockMove = new StockMove();
		foreach ($itens as $i) {
			ItemVenda::create([
				'venda_id' => $result->id,
				'produto_id' => (int) $i['id'],
				'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
				'valor' => (float) str_replace(",", ".", $i['valor'])
			]);
			$stockMove->downStock(
				(int) $i['id'], (float) str_replace(",", ".", $i['quantidade']));
		}

		echo json_encode($result);
	}

	private function menos30Dias(){
		return date('d/m/Y', strtotime("-30 days",strtotime(str_replace("/", "-",
			date('Y-m-d')))));
	}

	private function parseDate($date, $plusDay = false){
		if($plusDay == false)
			return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
		else
			return date('Y-m-d', strtotime("+1 day",strtotime(str_replace("/", "-", $date))));
	}

	public function filtro(Request $request){

		$dataInicial = $request->data_inicial;
		$dataFinal = $request->data_final;
		$cliente = $request->cliente;
		$estado = $request->estado;
		$vendas = null;

		if(isset($cliente) && isset($dataInicial) && isset($dataFinal)){
			$vendas = Venda::filtroDataCliente(
				$cliente,
				$this->parseDate($dataInicial),
				$this->parseDate($dataFinal, true),
				$estado
			);
		}else if(isset($dataInicial) && isset($dataFinal)){
			$vendas = Venda::filtroData(
				$this->parseDate($dataInicial),
				$this->parseDate($dataFinal, true),
				$estado
			);
		}else if(isset($cliente)){
			$vendas = Venda::filtroCliente(
				$cliente,
				$estado
			);

		}else{
			$vendas = Venda::filtroEstado(
				$estado
			);
		}

		$certificado = Certificado::first();

		return view("vendas/list")
		->with('vendas', $vendas)
		->with('nf', true)
		->with('cliente', $cliente)
		->with('certificado', $certificado)
		->with('dataInicial', $dataInicial)
		->with('dataFinal', $dataFinal)
		->with('estado', $estado)

		->with('title', "Filtro de Vendas");
	}

	public function teste(){
		ItemVenda::create([
			'venda_id' => 2,
			'produto_id' => 1,
			'quantidade' => 1,
			'valor' => 2
		]);
	}

	public function rederizarDanfe($id){
		$venda = Venda::find($id);
		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);
		$nfe = $nfe_service->gerarNFe($id);
		if(!isset($nfe['erros_xml'])){
			$xml = $nfe['xml'];

			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			$logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($public.'imgs/logo.jpg'));

			try {
				$danfe = new Danfe($xml);
				// $danfe->monta();
				$pdf = $danfe->render($logo);
				header('Content-Type: application/pdf');
				return response($pdf)
				->header('Content-Type', 'application/pdf');
			} catch (InvalidArgumentException $e) {
				echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
			}
		} else{
			foreach($nfe['erros_xml'] as $e) {
				echo $e;
			}
		}

	}

	public function imprimirPedido($id){
		$venda = Venda::find($id);
		$config = ConfigNota::first();

		$p = view('vendas/print')
		->with('config', $config)
		->with('venda', $venda);

		$domPdf = new Dompdf(["enable_remote" => true]);
		$domPdf->loadHtml($p);

		$pdf = ob_get_clean();

		$domPdf->setPaper("A4");
		$domPdf->render();
		$domPdf->stream("relatorio de venda $venda->id.pdf");

	}

	public function baixarXml($id){
		$venda = Venda::find($id);
		if($venda){
			$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			if(file_exists($public.'xml_nfe/'.$venda->chave.'.xml')){

				return response()->download($public.'xml_nfe/'.$venda->chave.'.xml');
			}else{
				echo "Arquivo XML não encontrado!!";
			}
		}else{
			echo "Selecione uma venda!!";

		}

	}

	public function edit($id){
		$venda = Venda::find($id);


		$config = ConfigNota::first();
		if($config == null){
			return redirect('configNF');
		}
		$lastNF = Venda::lastNF();

		$naturezas = NaturezaOperacao::all();

		$config = ConfigNota::first();
		$categorias = Categoria::all();
		$produtos = Produto::all();
		$tributacao = Tributacao::first();
		$clientes = Cliente::all();
		$tiposPagamento = Venda::tiposPagamento();

		foreach($venda->itens as $i){
			$i->produto;
		}
		$venda->duplicatas;
		$venda->natureza;
		$venda->cliente;
		$venda->frete;

		$transportadoras = Transportadora::all();

		$produtos = Produto::orderBy('nome')->get();

		foreach($produtos as $p){
			$p->listaPreco;
			$p->estoque;
		}

		return view("vendas/edit")
		->with('naturezas', $naturezas)
		->with('vendaJs', true)
		->with('config', $config)
		->with('transportadoras', $transportadoras)
		->with('produtos', $produtos)
		->with('venda', $venda)
		->with('tiposPagamento', $tiposPagamento)
		->with('lastNF', $lastNF)
		->with('listaPreco', ListaPreco::all())
		->with('title', "Editar Venda");

	}

	public function clone($id){
		$lastNF = Venda::lastNF();
		$venda = Venda::find($id);
		$config = ConfigNota::first();
		$clientes = Cliente::all();

		return view("vendas/clone")
		->with('vendaJs', true)
		->with('config', $config)
		->with('clientes', $clientes)
		->with('venda', $venda)
		->with('lastNF', $lastNF)
		->with('title', "Clonar Venda");
	}

	public function salvarClone(Request $request){
		$cliente = $request->cliente;
		$vendaId = $request->venda_id;

		$clienteId = (int)explode("-", $cliente)[0];
		if(!$clienteId){
			session()->flash("mensagem_erro", "Informe o cliente!");
			return redirect()->back();
		}
		$venda = Venda::find($vendaId);

		$freteId = null;
		if($venda->frete_id != NULL){
			$frete = Frete::create([
				'placa' => $venda->frete->placa,
				'valor' => $venda->frete->valor,
				'tipo' => $venda->frete->tipo,
				'qtdVolumes' => $venda->frete->qtdVolumes,
				'uf' => $venda->frete->uf,
				'numeracaoVolumes' => $venda->frete->numeracaoVolumes,
				'especie' => $venda->frete->especie,
				'peso_liquido' => $venda->frete->peso_liquido,
				'peso_bruto' => $venda->frete->peso_bruto
			]);
			$freteId = $frete->id;
		}

		$novaVenda = [
			'cliente_id' => $clienteId,
			'usuario_id' => get_id_user(),
			'frete_id' => $freteId,
			'valor_total' => $venda->valor_total,
			'forma_pagamento' => $venda->forma_pagamento,
			'NfNumero' => 0,
			'natureza_id' => $venda->natureza_id,
			'chave' => '',
			'path_xml' => '',
			'estado' => 'DISPONIVEL',
			'observacao' => $venda->observacao,
			'desconto' => $venda->desconto,
			'transportadora_id' => $venda->transportadora_id,
			'sequencia_cce' => 0,
			'tipo_pagamento' => $venda->tipo_pagamento,
			'bandeira_cartao' => $venda->bandeira_cartao,
			'cAut_cartao' => $venda->cAut_cartao,
			'cnpj_cartao' => $venda->cnpj_cartao,
			'descricao_pag_outros' => $venda->descricao_pag_outros,
		];

		$result = Venda::create($novaVenda);

		$itens = $venda->itens;
		$stockMove = new StockMove();
		foreach ($itens as $i) {
			ItemVenda::create([
				'venda_id' => $result->id,
				'produto_id' => $i->produto_id,
				'quantidade' => $i->quantidade,
				'valor' => $i->valor
			]);

			$prod = Produto
			::where('id', $i->produto_id)
			->first();

			if(!empty($prod->receita)){
				//baixa por receita
				$receita = $prod->receita;
				foreach($receita->itens as $rec){

					if(!empty($rec->produto->receita)){ // se item da receita for receita
						$receita2 = $rec->produto->receita;

						foreach($receita2->itens as $rec2){
							$stockMove->downStock(
								$rec2->produto_id,
								$i->quantidade *
								($rec2->quantidade/$receita2->rendimento)
							);
						}
					}else{

						$stockMove->downStock(
							$rec->produto_id,
							$i->quantidade*
							($rec->quantidade/$receita->rendimento)
						);
					}
				}
			}else{
				$stockMove->downStock(
					$i->produto_id, $i->quantidade);
			}
		}

		if($venda->forma_pagamento != 'a_vista' && $venda->forma_pagamento != 'conta_crediario'){
			$fatura = $venda->duplicatas;

			foreach ($fatura as $key => $f) {
				$valorParcela = str_replace(",", ".", $f['valor']);

				$resultFatura = ContaReceber::create([
					'venda_id' => $result->id,
					'data_vencimento' => $f->data_vencimento,
					'data_recebimento' => $f->data_recebimento,
					'valor_integral' => $f->valor_integral,
					'valor_recebido' => 0,
					'status' => false,
					'referencia' => "Parcela, ".($key+1).", da Venda " . $result->id,
					'categoria_id' => 2,
				]);
			}
		}


		session()->flash("mensagem_sucesso", "Venda duplicada com sucesso!");

		return redirect('/vendas/lista');

	}

	public function gerarXml($id){
		$venda = Venda::find($id);
		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$nfe_service = new NFService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => (int)$config->ambiente,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		]);
		$nfe = $nfe_service->gerarNFe($id,1);
		if(!isset($nfe['erros_xml'])){
			$xml = $nfe['xml'];

			return response($xml)
			->header('Content-Type', 'application/xml');

		} else{
			foreach($nfe['erros_xml'] as $e) {
				echo $e;
			}
		}
	}

	public function calculaFrete(Request $request){

		$stringUrl = "&sCepOrigem=$request->sCepOrigem&sCepDestino=$request->sCepDestino&nVlPeso=$request->nVlPeso";

		$stringUrl .= "&nVlComprimento=$request->nVlComprimento&nVlAltura=$request->nVlAltura&nVlLargura=$request->nVlLargura&nCdServico=04014";

		$url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=&sDsSenha=&sCdAvisoRecebimento=n&sCdMaoPropria=n&nVlValorDeclarado=0&nVlDiametro=0&StrRetorno=xml&nIndicaCalculo=3&nCdFormato=1" . $stringUrl;

		$unparsedResult = file_get_contents($url);
		$parsedResult = simplexml_load_string($unparsedResult);

		$stringUrl = "&sCepOrigem=$request->sCepOrigem&sCepDestino=$request->sCepDestino&nVlPeso=$request->nVlPeso";

		$stringUrl .= "&nVlComprimento=$request->nVlComprimento&nVlAltura=$request->nVlAltura&nVlLargura=$request->nVlLargura&nCdServico=04510";

		$url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=&sDsSenha=&sCdAvisoRecebimento=n&sCdMaoPropria=n&nVlValorDeclarado=0&nVlDiametro=0&StrRetorno=xml&nIndicaCalculo=3&nCdFormato=1" . $stringUrl;

		$unparsedResultSedex = file_get_contents($url);
		$parsedResultSedex = simplexml_load_string($unparsedResultSedex);

		$retorno = array(
			'preco_sedex' => strval($parsedResult->cServico->Valor),
			'prazo_sedex' => strval($parsedResult->cServico->PrazoEntrega),

			'preco' => strval($parsedResultSedex->cServico->Valor),
			'prazo' => strval($parsedResultSedex->cServico->PrazoEntrega)
		);

		return response()->json($retorno, 200);
	}

	public function verificadiautil($data){


		$diaSemana = getdate(strtotime($data));

		$diaSemana  = $diaSemana["wday"];

		if ($diaSemana==6){

		$data = date('Y/m/d', strtotime('+ 2 days',strtotime($data)));

		}else if( $diaSemana==0){
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));

		}

		// verificar se é feriado bancário

		//$search_array = array('2021-09-07','2021-10-12','2021-11-02','2021-11-15','2021-12-25');

		$search_array = array('2022-06-16','2022-09-07','2022-10-12','2022-11-2','2022-11-15','2022-12-15','2023-01-01','2023-02-20','2023-02-21','2023-04-07','2023-04-23','2023-05-01','2023-05-01','2023-06-08','2023-09-07','2023-10-12','2023-11-02','2023-11-15','2023-12-23');



		if (in_array($data, $search_array)) {
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));
		 }
		 if (in_array($data, $search_array)) {
			$data = date('Y/m/d', strtotime('+ 1 days',strtotime($data)));
		 }

		 return $data;


	}

    public function novatransferencia(){
		$config = ConfigNota::first();
		if($config == null){
			session()->flash("mensagem_erro", "Configure o emitente!");
			return redirect('configNF');
		}
		$lastNF = Venda::lastNF();

		$naturezas = NaturezaOperacao::where('transferencia',true)
        ->get();


		$config = ConfigNota::first();
		$categorias = Categoria::all();
		$produtos = Produto::
		where('valor_venda', '>', 0)
		->where('ativo', true)
		->orderBy('nome','asc')

		->get();
		$tributacao = Tributacao::first();
		$clientes = Cliente::all();
		$tiposPagamento = Venda::tiposPagamento();

		if(count($naturezas) == 0 || count($produtos) == 0 || $config == null || count($categorias) == 0 || $tributacao == null || count($clientes) == 0){

			return view("vendas/alerta")
			->with('produtos', count($produtos))
			->with('categorias', count($categorias))
			->with('clientes', count($clientes))
			->with('naturezas', $naturezas)
			->with('config', $config)
			->with('tributacao', $tributacao)
			->with('title', "Validação para Emitir");
		}else{

			$transportadoras = Transportadora::all();

			foreach($clientes as $c){
				$c->cidade;
			}
			foreach($produtos as $p){
				$p->listaPreco;
				$p->estoque;
			}

			return view("vendas/registertransferencia")
			->with('naturezas', $naturezas)
			->with('transferenciaJs', true)
			->with('config', $config)
			->with('clientes', $clientes)
			->with('produtos', $produtos)
			->with('transportadoras', $transportadoras)
			->with('tiposPagamento', $tiposPagamento)
			->with('lastNF', $lastNF)
			->with('listaPreco', ListaPreco::all())
			->with('title', "Nova Transferencia de Mercadoria");
		}
	}

    public function salvartransferencia(Request $request){

		DB::beginTransaction();
		$venda = $request->venda;
		$valorFrete = str_replace(".", "", $venda['valorFrete'] ?? 0);
		$valorFrete = str_replace(",", ".", $valorFrete );
		$vol = $venda['volume'];

		if($vol['pesoL']){
			$pesoLiquido = str_replace(",", ".", $vol['pesoL']);
			// $pesoLiquido = str_replace(",", ".", $pesoLiquido);
		}else{
			$pesoLiquido = 0;
		}

		if($vol['pesoB']){
			$pesoBruto = str_replace(",", ".", $vol['pesoB']);
			// $pesoBruto = str_replace(",", ".", $pesoBruto);
		}else{
			$pesoBruto = 0;
		}

		if($vol['qtdVol']){
			$qtdVol = str_replace(",", ".", $vol['qtdVol']);
			// $qtdVol = str_replace(",", ".", $qtdVol);
		}else{
			$qtdVol = 0;
		}

		$frete = null;
		if($venda['frete'] != '9'){
			$frete = Frete::create([
				'placa' => $venda['placaVeiculo'] ?? '',
				'valor' => $valorFrete ?? 0,
				'tipo' => (int)$venda['frete'],
				'qtdVolumes' => $qtdVol?? 0,
				'uf' => $venda['ufPlaca'] ?? '',
				'numeracaoVolumes' => $vol['numeracaoVol'] ?? '0',
				'especie' => $vol['especie'] ?? '*',
				'peso_liquido' => $pesoLiquido ?? 0,
				'peso_bruto' => $pesoBruto ?? 0
			]);
		}


		$totalVenda = str_replace(",", ".", $venda['total']);
        $totalCusto = str_replace(",", ".", $venda['totalcusto']);
		$txcartao   = 0;
		$desconto = 0;
		$txantcartao =0;
		if($venda['desconto']){
			$desconto = str_replace(".", "", $venda['desconto']);
			$desconto = str_replace(",", ".", $desconto);
		}










		$result = Venda::create([
			'cliente_id' => $venda['cliente'],
			'transportadora_id' => $venda['transportadora'],
			'forma_pagamento' => $venda['tipoPagamento'],
			'tipo_pagamento' => $venda['tipoPagamento'],
			'usuario_id' => get_id_user(),
			'valor_total' => $totalVenda,
			'custo_total' =>$totalCusto,
			'desconto' => $desconto,
			'frete_id' => $frete != null ? $frete->id : null,
			'NfNumero' => 0,
			'natureza_id' => $venda['naturezaOp'],
			'path_xml' => '',
			'chave' => '',
			'sequencia_cce' => 0,
			'observacao' => $this->sanitizeString($venda['observacao']) ?? '',
			'estado' => 'DISPONIVEL',
			'bandeira_cartao' => $venda['bandeira_cartao'],
			'cAut_cartao' => $venda['cAut_cartao'] ?? '',
			'cnpj_cartao' => $venda['cnpj_cartao'] ?? '',
			'descricao_pag_outros' => $venda['descricao_pag_outros'] ?? '',
			'txcartao' => $txcartao,
			'txantcartao' => $txantcartao,
			'transferencia'=>true
		]);



		$itens = $venda['itens'];
		$stockMove = new StockMove();

		foreach ($itens as $i) {
			$prod = Produto
			::where('id', $i['codigo'])
			->first();

			if ($prod->composto == 0){

			    $custoproduto = $prod->valor_compra;

			}
			else{

				$custoproduto = $prod->custoprodutofabricado($prod->id);
			}


			$resultvendaitem = ItemVenda::create([
				'venda_id' => $result->id,
				'produto_id' => (int) $i['codigo'],
				'quantidade' => (float) str_replace(",", ".", $i['quantidade']),
				'valor' => (float) str_replace(",", ".", $i['valor']),
				'custo_total' => (float) str_replace(",", ".", $i['quantidade']) * str_replace(",", ".", $i['custo']) ,

				'observacao' => $i['obs'] ?? ''
			]);


		$stockMove->downStock((int) $i['codigo'],str_replace(",", ".", $i['quantidade']),$custoproduto,'Venda','Obs. '. 'N: '.'-'.  'Mov: '. $result->id, $resultvendaitem->id);

		}




		DB::commit();
		echo json_encode($result);
	}


}
