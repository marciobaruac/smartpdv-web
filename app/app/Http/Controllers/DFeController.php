<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DFeService;
use App\Models\ConfigNota;
use App\Models\ManifestaDfe;
use App\Models\ProdutoListaPreco;
use NFePHP\NFe\Common\Standardize;
use App\Models\Cidade;
use App\Models\Produto;
use App\Models\Categoria;
use App\Models\Certificado;
use App\Models\Fornecedor;
use App\Models\Compra;
use App\Models\ContaPagar;
use NFePHP\DA\NFe\Danfe;
use App\Models\ItemDfe;
use App\Helpers\StockMove;

use App\Models\ItemCompra;
use Illuminate\Support\Facades\DB;

class DFeController extends Controller
{
	public function __construct()
	{
		$this->middleware(function ($request, $next) {
			$value = session('user_logged');
			if (!$value) {
				return redirect("/login");
			} else {
				if ($value['acesso_cliente'] == 0) {
					return redirect("/sempermissao");
				}
			}
			return $next($request);
		});
	}

	public function index()
	{
		$redirect = $this->validarConfigBasica();
		if ($redirect) {
			return $redirect;
		}

		$periodo = $this->getPeriodoPadraoDfe();
		$docs = $this->buscarDocsFiltrados($periodo['data_inicial'], $periodo['data_final'], '--');

		return view('dfe/index')
			->with('docs', $docs)
			->with('bi', $this->montarDadosBI($docs, $periodo['data_inicial'], $periodo['data_final']))
			->with('dfeJS', $docs)
			->with('data_final', $periodo['data_final'])
			->with('data_inicial', $periodo['data_inicial'])
			->with('tipo_selecionado', '--')
			->with('title', 'DF-e');
	}

	public function atualizaentrada()
	{
		return redirect('/dfe');
	}

	public function filtro(Request $request)
	{
		$tipo = $request->tipo;
		$dataInicial = $request->data_inicial;
		$dataFinal = $request->data_final;

		$redirect = $this->validarConfigBasica();
		if ($redirect) {
			return $redirect;
		}

		$docs = $this->buscarDocsFiltrados($dataInicial, $dataFinal, $tipo);

		return view('dfe/index')
			->with('docs', $docs)
			->with('bi', $this->montarDadosBI($docs, $dataInicial, $dataFinal))
			->with('dfeJS', $docs)
			->with('data_final', $dataFinal)
			->with('data_inicial', $dataInicial)
			->with('tipo_selecionado', $tipo)
			->with('title', 'DF-e');
	}

	public function analise()
	{
		$redirect = $this->validarConfigBasica();
		if ($redirect) {
			return $redirect;
		}

		$periodo = $this->getPeriodoPadraoDfe();
		$docs = $this->buscarDocsFiltrados($periodo['data_inicial'], $periodo['data_final'], '--');

		return view('dfe/analise')
			->with('docs', $docs)
			->with('bi', $this->montarDadosBI($docs, $periodo['data_inicial'], $periodo['data_final']))
			->with('data_final', $periodo['data_final'])
			->with('data_inicial', $periodo['data_inicial'])
			->with('tipo_selecionado', '--')
			->with('title', 'An�lise de Entradas DF-e');
	}

	public function analiseFiltro(Request $request)
	{
		$tipo = $request->tipo;
		$dataInicial = $request->data_inicial;
		$dataFinal = $request->data_final;

		$redirect = $this->validarConfigBasica();
		if ($redirect) {
			return $redirect;
		}

		$docs = $this->buscarDocsFiltrados($dataInicial, $dataFinal, $tipo);

		return view('dfe/analise')
			->with('docs', $docs)
			->with('bi', $this->montarDadosBI($docs, $dataInicial, $dataFinal))
			->with('data_final', $dataFinal)
			->with('data_inicial', $dataInicial)
			->with('tipo_selecionado', $tipo)
			->with('title', 'An�lise de Entradas DF-e');
	}

	private function validarConfigBasica()
	{
		$config = ConfigNota::first();
		if ($config == null) {
			session()->flash('mensagem_sucesso', 'Configure o Emitente');
			return redirect('configNF');
		}

		$certificado = Certificado::first();
		if ($certificado == null) {
			session()->flash('mensagem_erro', 'Configure o Certificado');
			return redirect('configNF');
		}

		return null;
	}

	private function getPeriodoPadraoDfe()
	{
		return [
			'data_inicial' => \Carbon\Carbon::now()->subDays(90)->format('d/m/Y'),
			'data_final' => \Carbon\Carbon::now()->format('d/m/Y'),
		];
	}

	private function buscarDocsFiltrados($dataInicial, $dataFinal, $tipo = '--')
	{
		$dIni = \Carbon\Carbon::createFromFormat('d/m/Y', $dataInicial)->startOfDay()->format('Y-m-d H:i:s');
		$dFim = \Carbon\Carbon::createFromFormat('d/m/Y', $dataFinal)->endOfDay()->format('Y-m-d H:i:s');

		$query = ManifestaDfe::where('ativo', 1)
			->whereBetween('data_emissao', [$dIni, $dFim]);

		if ($tipo != '--') {
			$query->where('tipo', $tipo);
		}

		return $query->orderBy('id', 'desc')->get();
	}

	private function montarDadosBI($docs, $dataInicial = null, $dataFinal = null)
	{
		$total = (int) $docs->count();
		$valorTotal = (float) $docs->sum('valor');
		$entradaRealizada = (int) $docs->where('tipo', 5)->count();
		$prontasParaEntrada = (int) $docs->whereIn('tipo', [1, 2])->count();
		$pendenteManifesto = (int) $docs->filter(function ($doc) {
			return is_null($doc->tipo) || (int) $doc->tipo === 0;
		})->count();
		$recusadas = (int) $docs->whereIn('tipo', [3, 4])->count();

		$taxaEntrada = $total > 0 ? round(($entradaRealizada / $total) * 100, 1) : 0;
		$ticketMedio = $total > 0 ? ($valorTotal / $total) : 0;

		$status = collect([
			['label' => 'Entrada realizada', 'quantidade' => $entradaRealizada, 'cor' => '#0f766e'],
			['label' => 'Prontas para entrada', 'quantidade' => $prontasParaEntrada, 'cor' => '#0ea5e9'],
			['label' => 'Aguardando manifestação', 'quantidade' => $pendenteManifesto, 'cor' => '#f59e0b'],
			['label' => 'Recusadas/Não realizadas', 'quantidade' => $recusadas, 'cor' => '#dc2626'],
		])->map(function ($item) use ($total) {
			$item['percentual'] = $total > 0 ? round(($item['quantidade'] / $total) * 100, 1) : 0;
			return $item;
		});

		$topFornecedores = $docs->groupBy('nome')
			->map(function ($itens, $nome) {
				return [
					'nome' => $nome,
					'quantidade' => $itens->count(),
					'valor' => (float) $itens->sum('valor')
				];
			})
			->sortByDesc('quantidade')
			->take(5)
			->values();

		$docIds = $docs->pluck('id')->filter()->values()->all();
		$entradaPorProduto = collect();
		$saidaPorProduto = collect();
		$mapProdutos = [];
		$topClientes = collect();

		if (count($docIds) > 0) {
			$entradaPorProduto = DB::table('item_compras as ic')
				->join('compras as c', 'c.id', '=', 'ic.compra_id')
				->leftJoin('produtos as p', 'p.id', '=', 'ic.produto_id')
				->whereIn('c.dfe_id', $docIds)
				->select(
					'ic.produto_id',
					DB::raw("COALESCE(p.nome, CONCAT('Produto #', ic.produto_id)) as nome_produto"),
					DB::raw('SUM(ic.quantidade) as quantidade_entrada'),
					DB::raw('SUM(ic.quantidade * ic.valor_unitario) as valor_entrada_total')
				)
				->groupBy('ic.produto_id', 'p.nome')
				->get();
		}

		try {
			if ($dataInicial && $dataFinal) {
				$dIniVenda = \Carbon\Carbon::createFromFormat('d/m/Y', $dataInicial)->startOfDay()->format('Y-m-d H:i:s');
				$dFimVenda = \Carbon\Carbon::createFromFormat('d/m/Y', $dataFinal)->endOfDay()->format('Y-m-d H:i:s');
			} else {
				$dIniVenda = \Carbon\Carbon::now()->subDays(90)->startOfDay()->format('Y-m-d H:i:s');
				$dFimVenda = \Carbon\Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
			}

			$saidaNfPorProduto = DB::table('item_vendas as iv')
				->join('vendas as v', 'v.id', '=', 'iv.venda_id')
				->leftJoin('produtos as p', 'p.id', '=', 'iv.produto_id')
				->whereBetween('iv.created_at', [$dIniVenda, $dFimVenda])
				->where('v.estado', '!=', 'CANCELADO')
				->select(
					'iv.produto_id',
					DB::raw("COALESCE(p.nome, CONCAT('Produto #', iv.produto_id)) as nome_produto"),
					DB::raw('SUM(iv.quantidade) as quantidade_saida'),
					DB::raw('SUM(iv.quantidade * iv.valor) as valor_venda_total')
				)
				->groupBy('iv.produto_id', 'p.nome')
				->get();

			$saidaCaixaPorProduto = DB::table('item_venda_caixas as ivc')
				->join('venda_caixas as vc', 'vc.id', '=', 'ivc.venda_caixa_id')
				->leftJoin('produtos as p', 'p.id', '=', 'ivc.produto_id')
				->whereBetween('ivc.created_at', [$dIniVenda, $dFimVenda])
				->where('vc.estado', '!=', 'CANCELADO')
				->select(
					'ivc.produto_id',
					DB::raw("COALESCE(p.nome, CONCAT('Produto #', ivc.produto_id)) as nome_produto"),
					DB::raw('SUM(ivc.quantidade) as quantidade_saida'),
					DB::raw('SUM(ivc.quantidade * ivc.valor) as valor_venda_total')
				)
				->groupBy('ivc.produto_id', 'p.nome')
				->get();

			$clientesNf = DB::table('vendas as v')
				->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
				->whereBetween('v.created_at', [$dIniVenda, $dFimVenda])
				->where('v.estado', '!=', 'CANCELADO')
				->select(
					DB::raw("COALESCE(NULLIF(c.razao_social, ''), NULLIF(c.nome_fantasia, ''), 'Consumidor Final') as nome_cliente"),
					DB::raw('COUNT(*) as quantidade_docs'),
					DB::raw('SUM(v.valor_total) as valor_total')
				)
				->groupBy('nome_cliente')
				->get();

			$clientesCaixa = DB::table('venda_caixas as vc')
				->leftJoin('clientes as c', 'c.id', '=', 'vc.cliente_id')
				->whereBetween('vc.created_at', [$dIniVenda, $dFimVenda])
				->where('vc.estado', '!=', 'CANCELADO')
				->select(
					DB::raw("COALESCE(NULLIF(c.razao_social, ''), NULLIF(c.nome_fantasia, ''), NULLIF(vc.nome, ''), 'Consumidor Final') as nome_cliente"),
					DB::raw('COUNT(*) as quantidade_docs'),
					DB::raw('SUM(vc.valor_total) as valor_total')
				)
				->groupBy('nome_cliente')
				->get();

			$saidaPorProduto = $saidaNfPorProduto
				->concat($saidaCaixaPorProduto)
				->groupBy(function ($item) {
					return ($item->produto_id ?: 0) . '|' . $item->nome_produto;
				})
				->map(function ($itens) {
					$first = $itens->first();
					return (object) [
						'produto_id' => $first->produto_id,
						'nome_produto' => $first->nome_produto,
						'quantidade_saida' => $itens->sum('quantidade_saida'),
						'valor_venda_total' => $itens->sum('valor_venda_total'),
					];
				})
				->values();

			$topClientes = $clientesNf
				->concat($clientesCaixa)
				->groupBy('nome_cliente')
				->map(function ($itens, $nomeCliente) {
					return [
						'nome' => $nomeCliente,
						'quantidade' => (int) $itens->sum('quantidade_docs'),
						'valor' => (float) $itens->sum('valor_total')
					];
				})
				->sortByDesc('valor')
				->take(5)
				->values();
		} catch (\Exception $e) {
			$saidaPorProduto = collect();
			$topClientes = collect();
		}

		foreach ($entradaPorProduto as $item) {
			$chave = $item->produto_id ? 'p_'.$item->produto_id : 'n_'.md5($item->nome_produto);
			$mapProdutos[$chave] = [
				'produto_id' => $item->produto_id,
				'nome' => $item->nome_produto,
				'quantidade_entrada' => (float) $item->quantidade_entrada,
				'valor_entrada_total' => (float) $item->valor_entrada_total,
				'quantidade_saida' => 0.0,
				'valor_venda_total' => 0.0
			];
		}

		foreach ($saidaPorProduto as $item) {
			$chave = $item->produto_id ? 'p_'.$item->produto_id : 'n_'.md5($item->nome_produto);
			if (!isset($mapProdutos[$chave])) {
				$mapProdutos[$chave] = [
					'produto_id' => $item->produto_id,
					'nome' => $item->nome_produto,
					'quantidade_entrada' => 0.0,
					'valor_entrada_total' => 0.0,
					'quantidade_saida' => 0.0,
					'valor_venda_total' => 0.0
				];
			}
			$mapProdutos[$chave]['quantidade_saida'] += (float) $item->quantidade_saida;
			$mapProdutos[$chave]['valor_venda_total'] += (float) $item->valor_venda_total;
		}

		$entradaPorProduto = collect($mapProdutos)->map(function ($item) {
			$quantidadeEntrada = (float) $item['quantidade_entrada'];
			$valorEntradaTotal = (float) $item['valor_entrada_total'];
			$quantidadeSaida = (float) $item['quantidade_saida'];
			$valorVendaTotal = (float) $item['valor_venda_total'];

			$custoMedioEntrada = $quantidadeEntrada > 0 ? ($valorEntradaTotal / $quantidadeEntrada) : 0.0;
			$valorVendaMedio = $quantidadeSaida > 0 ? ($valorVendaTotal / $quantidadeSaida) : 0.0;
			$custoEstimadoSaida = $quantidadeSaida * $custoMedioEntrada;
			$lucroTotal = $valorVendaTotal - $custoEstimadoSaida;
			$margemLucroPercentual = $valorVendaTotal > 0 ? (($lucroTotal / $valorVendaTotal) * 100) : 0.0;

			return [
				'produto_id' => $item['produto_id'],
				'nome' => $item['nome'],
				'quantidade_entrada' => $quantidadeEntrada,
				'valor_entrada_total' => $valorEntradaTotal,
				'quantidade_saida' => $quantidadeSaida,
				'valor_venda_total' => $valorVendaTotal,
				'valor_venda_medio' => $valorVendaMedio,
				'lucro_total' => $lucroTotal,
				'margem_lucro_percentual' => $margemLucroPercentual
			];
		})
		->sortByDesc('quantidade_entrada')
		->values();

		$valorEntradaTotal = (float) $entradaPorProduto->sum('valor_entrada_total');
		$quantidadeSaidaTotal = (float) $entradaPorProduto->sum('quantidade_saida');
		$valorSaidaTotal = (float) $entradaPorProduto->sum('valor_venda_total');
		$lucroTotalSaida = (float) $entradaPorProduto->sum('lucro_total');
		$ticketMedioSaida = $quantidadeSaidaTotal > 0 ? ($valorSaidaTotal / $quantidadeSaidaTotal) : 0;
		$margemMediaSaida = $valorSaidaTotal > 0 ? (($lucroTotalSaida / $valorSaidaTotal) * 100) : 0;
		$produtosComSaida = (int) $entradaPorProduto->filter(function ($item) {
			return (float) $item['quantidade_saida'] > 0;
		})->count();

		return [
			'kpis' => [
				'valor_entrada_total' => $valorEntradaTotal,
				'quantidade_saida_total' => $quantidadeSaidaTotal,
				'valor_saida_total' => $valorSaidaTotal,
				'lucro_total_saida' => $lucroTotalSaida,
				'margem_media_saida' => $margemMediaSaida,
				'ticket_medio_saida' => $ticketMedioSaida,
				'produtos_com_saida' => $produtosComSaida
			],
			'status' => $status,
			'top_fornecedores' => $topFornecedores,
			'top_clientes' => $topClientes,
			'entrada_por_produto' => $entradaPorProduto
		];
	}


	public function getDocumentos(Request $request)
	{
		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$data_inicial = str_replace("/", "-", $request->data_inicial);
		$data_final = str_replace("/", "-", $request->data_final);

		$dfe_service = new DFeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => 1,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		], 55);

		$docs = $this->validaDocsIncluidos($dfe_service->consulta(
			\Carbon\Carbon::parse($data_inicial)->format('Y-m-d'),
			\Carbon\Carbon::parse($data_final)->format('Y-m-d')
		));

		usort($docs, function ($a, $b) {
			return \Carbon\Carbon::parse($a['data_emissao'])->format('Y-m-d') <
				\Carbon\Carbon::parse($b['data_emissao'])->format('Y-m-d');
		});

		for ($aux = 0; $aux < count($docs); $aux++) {
			$docs[$aux]['data_emissao'] = \Carbon\Carbon::parse($docs[$aux]['data_emissao'])->format('d/m/Y H:i:s');
		}

		return response()->json($docs, 200);
	}



	private function validaDocsIncluidos($docs)
	{
		for ($aux = 0; $aux < count($docs); $aux++) {
			if ($docs[$aux]) {
				$manifesta = ManifestaDfe::where('chave', $docs[$aux]['chave'])->first();
				if ($manifesta != null) {
					$docs[$aux]['incluso'] = true;
					$docs[$aux]['tipo'] = $manifesta->tipo;
				}
			}
		}
		return $docs;
	}

	public function manifestar(Request $request)
	{

		$config = ConfigNota::first();

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$cnpj = str_replace(" ", "", $cnpj);

		$dfe_service = new DFeService([
			"atualizacao" => date('Y-m-d h:i:s'),
			"tpAmb" => 1,
			"razaosocial" => $config->razao_social,
			"siglaUF" => $config->UF,
			"cnpj" => $cnpj,
			"schemes" => "PL_009_V4",
			"versao" => "4.00",
			"tokenIBPT" => "AAAAAAA",
			"CSC" => $config->csc,
			"CSCid" => $config->csc_id
		], 55);
		$evento = $request->evento;

		$manifestaAnterior = $this->verificaAnterior($request->chave);

		if ($evento == 1) {
			$res = $dfe_service->manifesta(
				$request->chave,
				$manifestaAnterior != null ? ($manifestaAnterior->sequencia_evento + 1) : 1
			);
		} else if ($evento == 2) {
			$res = $dfe_service->confirmacao(
				$request->chave,
				$manifestaAnterior != null ? ($manifestaAnterior->sequencia_evento + 1) : 1
			);
		} else if ($evento == 3) {
			$res = $dfe_service->desconhecimento(
				$request->chave,
				$manifestaAnterior != null ? ($manifestaAnterior->sequencia_evento + 1) : 1,
				$request->justificativa
			);
		} else if ($evento == 4) {
			$res = $dfe_service->operacaoNaoRealizada(
				$request->chave,
				$manifestaAnterior != null ? ($manifestaAnterior->sequencia_evento + 1) : 1,
				$request->justificativa
			);
		}

		// echo $res['retEvento']['infEvento']['cStat'];
		if ($res['retEvento']['infEvento']['cStat'] == '135') { //sucesso
			// $manifesta = [
			// 	'chave' => $request->chave,
			// 	'nome' => $request->nome,
			// 	'documento' => $request->cnpj,
			// 	'valor' => $request->valor,
			// 	'num_prot' => $request->num_prot,
			// 	'data_emissao' => $request->data_emissao,
			// 	'sequencia_evento' => 1, 
			// 	'fatura_salva' => false,	 
			// 	'tipo' => $evento
			// ];

			$manifesto = ManifestaDfe::where('chave', $request->chave)
				->first();
			$manifesto->tipo = $evento;
			$manifesto->save();

			// ManifestaDfe::create($manifesta);
			session()->flash('mensagem_sucesso', 'XML ' . $request->chave . ' manifestado!');
			return redirect('/dfe');
		} else {

			// $manifesta = [
			// 	'chave' => $request->chave,
			// 	'nome' => $request->nome,
			// 	'documento' => $request->cnpj,
			// 	'valor' => $request->valor,
			// 	'num_prot' => $request->num_prot,
			// 	'data_emissao' => $request->data_emissao,
			// 	'sequencia_evento' => 1, 
			// 	'fatura_salva' => false,	
			// 	'tipo' => $evento 
			// ];

			$manifesto = ManifestaDfe::where('chave', $request->chave)
				->first();
			$manifesto->tipo = $evento;
			$manifesto->save();

			// ManifestaDfe::create($manifesta);

			session()->flash('mensagem_erro', 'Já esta manifestado a chave ' . $request->chave);
			return redirect('/dfe');
		}

	}

	private function verificaAnterior($chave)
	{
		return ManifestaDfe::where('chave', $chave)->first();
	}

	public function download($chave)
	{


		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$nfe = [];
		if (file_exists($public . 'xml_dfe/' . $chave . '.xml')) {
			$nfe = simplexml_load_file($public . 'xml_dfe/' . $chave . '.xml');
		}
		$config = ConfigNota::first();


		if (!$nfe) {


			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);

			$dfe_service = new DFeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => 1,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			], 55);
		}

		try {
			if (!$nfe) {
				$response = $dfe_service->download($chave);
				//print_r($response);
				// die();
				$stz = new Standardize($response);
				$std = $stz->toStd();
				if ($std->cStat != 138) {
					echo "Documento não retornado. [$std->cStat] $std->xMotivo" . ", aguarde alguns instantes e atualize a pagina!";
					die();
				}
				$zip = $std->loteDistDFeInt->docZip;
				$xml = gzdecode(base64_decode($zip));

				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				file_put_contents($public . 'xml_dfe/' . $chave . '.xml', $xml);

				$nfe = simplexml_load_string($xml);
			}
			//$nfe = simplexml_load_file($public.'xml_dfe/'.$chave.'.xml');
			if (!$nfe) {
				echo "Erro ao ler XML";
			} else {

				$fornecedor = $this->getFornecedorXML($nfe);

				$itens = $this->getItensDaNFe($nfe, $config);
				//echo "<pre>";
				// print_r($itens);
				////   echo "</pre>";
				//   die();
				$infos = $this->getInfosDaNFe($nfe);
				$fatura = $this->getFaturaDaNFe($nfe);


				//caregar view

				$categorias = Categoria::all();
				$unidadesDeMedida = Produto::unidadesMedida();

				$listaCSTCSOSN = Produto::listaCSTCSOSN();
				$listaCST_PIS_COFINS = Produto::listaCST_PIS_COFINS();
				$listaCST_IPI = Produto::listaCST_IPI();
				//$config = ConfigNota::first();

				$manifesto = ManifestaDfe::where('chave', $chave)->first();

				$compra = Compra::
					where('chave', $chave)
					->first();

				$dfe = ManifestaDfe::where('chave', $chave)->first();

				$compraAdicionada = Compra::
					where('dfe_id', $manifesto->id)
					->where('status', false)// verifica nota esta cancelada
					->exists();

				return view('dfe/view')
					->with('fornecedor', $fornecedor)
					->with('itens', $itens)
					->with('dfe', $dfe)
					->with('infos', $infos)
					->with('dfeJS', true)
					->with('compraAdicionada', $compraAdicionada)
					->with('compraFiscal', $compra != null ? true : false)
					->with('fatura', $fatura)
					->with('listaCSTCSOSN', $listaCSTCSOSN)
					->with('listaCST_PIS_COFINS', $listaCST_PIS_COFINS)
					->with('listaCST_IPI', $listaCST_IPI)
					->with('categorias', $categorias)
					->with('config', $config)
					->with('fatura_salva', $manifesto == null ? false : $manifesto->fatura_salva)
					->with('unidadesDeMedida', $unidadesDeMedida)
					->with('title', 'Visualizando XML');
			}
		} catch (\Exception $e) {
			echo "Erro de soap:<br>";
			echo $e->getMessage();
		}

	}

	public function imprimirDanfe($chave)
	{


		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		$nfe = [];
		if (file_exists($public . 'xml_dfe/' . $chave . '.xml')) {
			$nfe = simplexml_load_file($public . 'xml_dfe/' . $chave . '.xml');
			$xml = file_get_contents($public . 'xml_dfe/' . $chave . '.xml');
		}

		if (!$nfe) {

			$config = ConfigNota::first();

			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);

			$dfe_service = new DFeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => 1,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			], 55);
		}

		// print_r($response);
		try {

			if (!$nfe) {
				$response = $dfe_service->download($chave);

				$stz = new Standardize($response);
				$std = $stz->toStd();
				if ($std->cStat != 138) {
					echo "Documento não retornado. [$std->cStat] $std->xMotivo" . ", aguarde alguns instantes e atualize a pagina!";
					die;
				}
				$zip = $std->loteDistDFeInt->docZip;
				$xml = gzdecode(base64_decode($zip));

				$public = getenv('SERVIDOR_WEB') ? 'public/' : '';

				file_put_contents($public . 'xml_dfe/' . $chave . '.xml', $xml);
			}

			$danfe = new Danfe($xml);
			//$danfe = new Danfe($nfe);

			// $id = $danfe->monta();

			$pdf = $danfe->render();
			header('Content-Type: application/pdf');
			echo $pdf;
		} catch (InvalidArgumentException $e) {
			echo "Ocorreu um erro durante o processamento :" . $e->getMessage();
		}


	}

	private function getFornecedorXML($xml)
	{

		$cidade = Cidade::getCidadeCod($xml->NFe->infNFe->emit->enderEmit->cMun);
		$fornecedor = [
			'cpf' => $xml->NFe->infNFe->emit->CPF,
			'cnpj' => $xml->NFe->infNFe->emit->CNPJ,
			'razaoSocial' => $xml->NFe->infNFe->emit->xNome,
			'nomeFantasia' => $xml->NFe->infNFe->emit->xFant ?? $xml->NFe->infNFe->emit->xNome,
			'logradouro' => $xml->NFe->infNFe->emit->enderEmit->xLgr,
			'numero' => $xml->NFe->infNFe->emit->enderEmit->nro,
			'bairro' => $xml->NFe->infNFe->emit->enderEmit->xBairro,
			'cep' => $xml->NFe->infNFe->emit->enderEmit->CEP,
			'fone' => $xml->NFe->infNFe->emit->enderEmit->fone,
			'ie' => $xml->NFe->infNFe->emit->IE,
			'cidade_id' => $cidade->id
		];

		$fornecedorEncontrado = $this->verificaFornecedor($xml->NFe->infNFe->emit->CNPJ);
		if ($fornecedorEncontrado) {
			$fornecedor['objeto'] = $fornecedorEncontrado;
			$fornecedor['novo_cadastrado'] = false;
		} else {
			$fornecedor['novo_cadastrado'] = true;
			$idFornecedor = $this->cadastrarFornecedor($fornecedor);
			$fornecedor['objeto'] = Fornecedor::find($idFornecedor);
		}

		return $fornecedor;
	}

	private function getItensDaNFe($xml, $config)
	{
		$itens = [];


		foreach ($xml->NFe->infNFe->det as $item) {
			// echo "<pre>";
			//print_r($item);
			//echo "</pre>";
			//die();

			$preco = [];
			$produto = Produto::verificaCadastrado(
				$item->prod->cEAN,
				$item->prod->xProd,
				$item->prod->cProd,
				$config->consultaprodutoentrada
			);

			$produtoNovo = !$produto ? true : false;

			$tp = null;
			if ($produto != null) {
				$tp = ItemDfe::
					where('produto_id', $produto->id)
					->where('numero_nfe', $xml->NFe->infNFe->ide->nNF)
					->first();

				$preco = ProdutoListaPreco::
					select('valor')
					->where('produto_id', $produto->id)
					->where('lista_id', '=', 0)
					->first();


			}

			$ipi = 0;
			$icmsst = 0;
			$vipiunit = 0;
			$vicmsstunit = 0;
			$valorvenda = 0;
			$margemcusto = 0;
			$cst = '';
			$cest = '';
			if (!empty($item->imposto->IPI->IPITrib->vIPI)) {
				$ipi = $item->imposto->IPI->IPITrib->vIPI;
			}
			;

			if (!empty($item->imposto->ICMS->ICMS10->vICMSST)) {
				$icmsst = $item->imposto->ICMS->ICMS10->vICMSST;
			}
			;

			if (!empty($item->imposto->ICMS->ICMS10->vICMSST)) {
				$cst = $item->imposto->ICMS->ICMS10->CST;
			}
			;


			if (!empty($item->imposto->ICMS->ICMS60->CST)) {
				$cst = $item->imposto->ICMS->ICMS60->CST;
			}
			;



			if (!empty($item->imposto->ICMS->ICMSSN500->CSOSN)) {
				$cst = $item->imposto->ICMS->ICMSSN500->CSOSN;
			}
			;

			if (!empty($item->imposto->ICMS->ICMSSN102->CSOSN)) {
				$cst = $item->imposto->ICMS->ICMSSN102->CSOSN;
			}
			;

			if (!empty($item->imposto->ICMS->ICMSSN101->CSOSN)) {
				$cst = $item->imposto->ICMS->ICMSSN101->CSOSN;
			}
			;

			if (!empty($item->imposto->ICMS->ICMS00->CST)) {
				$cst = $item->imposto->ICMS->ICMS00->CST;
			}
			;





			if (!empty($item->prod->CEST)) {
				$cest = $item->prod->CEST;
			}
			;

			$vipiunit = ($ipi / $item->prod->qTrib);
			$vicmsstunit = ($icmsst / $item->prod->qTrib);
			$custoproduto = ($item->prod->vUnTrib + $vipiunit + $vicmsstunit);
			$custoproduto = Round($custoproduto, 2);


			if ($preco) {

				$valorvenda = $preco->valor;
				$margemcusto = ($valorvenda - $custoproduto) / $custoproduto * 100;
				$margemcusto = Round($margemcusto);
			} else {

				$valorvenda = 0;
				$margemcusto = 0;
			}


			$item = [
				'codigo' => $item->prod->cProd,
				'xProd' => $item->prod->xProd,
				'NCM' => $item->prod->NCM,
				'CFOP' => $item->prod->CFOP,
				//	'uCom' => $item->prod->uCom,
				'uCom' => $item->prod->uTrib,

				'vUnCom' => $item->prod->vUnTrib,
				'qCom' => $item->prod->qTrib,
				'codBarras' => $item->prod->cEAN,
				'produtoNovo' => $produtoNovo,
				'produto_id' => $produtoNovo ? null : $produto->id,
				'produtoSetadoEstoque' => $tp != null ? true : false,
				'produtoId' => $produtoNovo ? '0' : $produto->id,
				'conversao_unitaria' => $produtoNovo ? '' : $produto->conversao_unitaria,
				'vipi' => $ipi,
				'vicmsst' => $icmsst,
				'vvalorvenda' => $valorvenda,
				'vmargemcusto' => $margemcusto,
				'cst' => $cst,
				'cest' => $cest

			];
			array_push($itens, $item);
		}

		return $itens;
	}

	private function getInfosDaNFe($xml)
	{
		$chave = substr($xml->NFe->infNFe->attributes()->Id, 3, 44);
		$vFrete = number_format(
			(double) $xml->NFe->infNFe->total->ICMSTot->vFrete,
			2,
			",",
			"."
		);
		$vDesc = number_format((double) $xml->NFe->infNFe->total->ICMSTot->vDesc, 2, ",", ".");
		return [
			'chave' => $chave,
			'vProd' => $xml->NFe->infNFe->total->ICMSTot->vProd,
			'indPag' => $xml->NFe->infNFe->ide->indPag,
			'nNf' => $xml->NFe->infNFe->ide->nNF,
			'vFrete' => $vFrete,
			'vDesc' => $vDesc
		];
	}

	private function getFaturaDaNFe($xml)
	{
		if (!empty($xml->NFe->infNFe->cobr->dup)) {
			$fatura = [];
			$cont = 1;
			foreach ($xml->NFe->infNFe->cobr->dup as $dup) {
				$titulo = $dup->nDup;
				$vencimento = $dup->dVenc;
				$vencimento = explode('-', $vencimento);
				$vencimento = $vencimento[2] . "/" . $vencimento[1] . "/" . $vencimento[0];
				$vlr_parcela = (double) $dup->vDup;

				$parcela = [
					'numero' => $titulo,
					'vencimento' => $vencimento,
					'valor_parcela' => $vlr_parcela,
					'referencia' => $xml->NFe->infNFe->ide->nNF . "/" . $cont
				];
				array_push($fatura, $parcela);
				$cont++;
			}
			return $fatura;
		}
		return [];
	}


	private function verificaFornecedor($cnpj)
	{
		$forn = Fornecedor::verificaCadastrado($this->formataCnpj($cnpj));
		return $forn;
	}

	private function cadastrarFornecedor($fornecedor)
	{

		$result = Fornecedor::create([
			'razao_social' => $fornecedor['razaoSocial'],
			'nome_fantasia' => $fornecedor['nomeFantasia'],
			'rua' => $fornecedor['logradouro'],
			'numero' => $fornecedor['numero'],
			'bairro' => $fornecedor['bairro'],
			'cep' => $this->formataCep($fornecedor['cep']),
			'cpf_cnpj' => $this->formataCnpj($fornecedor['cnpj']),
			'ie_rg' => $fornecedor['ie'],
			'celular' => '*',
			'telefone' => $this->formataTelefone($fornecedor['fone']),
			'email' => '*',
			'cidade_id' => $fornecedor['cidade_id']
		]);
		return $result->id;
	}

	private function formataCnpj($cnpj)
	{
		$temp = substr($cnpj, 0, 2);
		$temp .= "." . substr($cnpj, 2, 3);
		$temp .= "." . substr($cnpj, 5, 3);
		$temp .= "/" . substr($cnpj, 8, 4);
		$temp .= "-" . substr($cnpj, 12, 2);
		return $temp;
	}

	private function formataCep($cep)
	{
		$temp = substr($cep, 0, 5);
		$temp .= "-" . substr($cep, 5, 3);
		return $temp;
	}

	private function formataTelefone($fone)
	{
		$temp = substr($fone, 0, 2);
		$temp .= " " . substr($fone, 2, 4);
		$temp .= "-" . substr($fone, 4, 4);
		return $temp;
	}

	public function salvarFatura(Request $request)
	{
		$chave = $request->chave;

		$manifesto = ManifestaDfe::where('chave', $chave)->first();
		$manifesto->fatura_salva = true;
		$manifesto->save();

		$fatura = json_decode($request->fatura);
		foreach ($fatura as $fat) {

			$conta = [
				'compra_id' => NULL,
				'data_vencimento' => \Carbon\Carbon::parse(str_replace("/", "-", $fat->vencimento))->format('Y-m-d'),
				'data_pagamento' => \Carbon\Carbon::parse(str_replace("/", "-", $fat->vencimento))->format('Y-m-d'),
				'valor_integral' => str_replace(",", ".", $fat->valor_parcela),
				'valor_pago' => 0,
				'referencia' => $fat->referencia,
				'categoria_id' => 1,
				'status' => false
			];

			ContaPagar::create($conta);
		}


		session()->flash('mensagem_sucesso', 'Fatura salva!');
		return redirect('/dfe/download/' . $chave);
	}

	public function novaConsulta()
	{
		return view('dfe/nova_consulta')
			->with('dfeJS', true)
			->with('title', 'Nova Consulta');
	}

	public function getDocumentosNovos()
	{

		try {
			$config = ConfigNota::first();

			$cnpj = str_replace(".", "", $config->cnpj);
			$cnpj = str_replace("/", "", $cnpj);
			$cnpj = str_replace("-", "", $cnpj);
			$cnpj = str_replace(" ", "", $cnpj);


			$dfe_service = new DFeService([
				"atualizacao" => date('Y-m-d h:i:s'),
				"tpAmb" => 1,
				"razaosocial" => $config->razao_social,
				"siglaUF" => $config->UF,
				"cnpj" => $cnpj,
				"schemes" => "PL_009_V4",
				"versao" => "4.00",
				"tokenIBPT" => "AAAAAAA",
				"CSC" => $config->csc,
				"CSCid" => $config->csc_id
			], 55);


			$manifesto = ManifestaDfe::orderBy('nsu', 'desc')->first();
			if ($manifesto == null)
				$nsu = 0;
			else
				$nsu = $manifesto->nsu;
			$docs = $dfe_service->novaConsulta($nsu);

			//echo "<pre>";
			//	print_r($nsu);
			//echo "</pre>";
			//	die();


			$novos = [];
			foreach ($docs as $d) {
				if ($this->validaNaoInserido($d['chave'])) {
					if ($d['valor'] > 0 && $d['nome']) {
						ManifestaDfe::create($d);
						array_push($novos, $d);
					}
				}
			}
			return response()->json($novos, 200);

		} catch (Exception $e) {
			return response()->json($e->getMessage(), 403);
		}

	}

	private function validaNaoInserido($chave)
	{
		$m = ManifestaDfe::where('chave', $chave)->first();
		if ($m == null)
			return true;
		else
			return false;
	}

	public function downloadXml($chave)
	{
		$dfe = ManifestaDfe::where('chave', $chave)->first();
		$chave = $dfe->chave;
		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
		if (file_exists($public . 'xml_dfe/' . $chave . '.xml'))
			return response()->download($public . 'xml_dfe/' . $chave . '.xml');
		else
			echo "Erro ao baixar XML, arquivo não encontrado!";
	}

	public function salvarCompra(Request $request)
	{
		$fornecedor = $request->fornecedor;
		$itens = $request->itens;
		$fatura = $request->fatura;
		$dfe_id = $request->dfe_id;
		$nf = $request->nf;



		$produto = new ProductController;

		DB::beginTransaction();
		try {
			$dfe = ManifestaDfe::where('id', $dfe_id)->lockForUpdate()->first();
			if ($dfe == null) {
				DB::rollback();
				return response()->json("DF-e não encontrada para realizar a entrada.", 404);
			}

                $compraDuplicada = Compra::where(function ($q) use ($dfe) {
                    $q->where('dfe_id', $dfe->id);
                    if (!empty($dfe->chave)) {
                        $q->orWhere('chave', $dfe->chave);
                    }
                })
                    ->where(function ($q) {
                        $q->where('status', false)
                            ->orWhere('status', 0)
                            ->orWhere('status', '0')
                            ->orWhere('status', 'false')
                            ->orWhereNull('status');
                    })
                    ->first();

			if ($compraDuplicada != null || (int) $dfe->tipo === 5) {
				DB::rollback();
                    return response('Entrada j� realizada para esta DF-e.', 409);
			}


			$data = [
				'fornecedor_id' => $fornecedor['id'],
				'usuario_id' => get_id_user(),
				'nf' => $nf,
				'observacao' => '',
				'valor' => $dfe->valor,
				'desconto' => 0,
				'xml_path' => '',
				'estado' => 'NOVO',
				'numero_emissao' => 0,
				'chave' => $dfe->chave,
				'dfe_id' => $dfe->id
			];

			$resCompra = Compra::create($data);

			foreach ($itens as $i) {
				$dataItem = [
					'compra_id' => $resCompra->id,
					'produto_id' => $i['produtoId'],
					'quantidade' => str_replace(",", ".", $i['qCom'][0]),
					'valor_unitario' => str_replace(",", ".", $i['vUnCom'][0]),
					'unidade_compra' => $i['uCom'][0],
					'cfop_entrada' => $i['CFOP'][0],
					'codigo_siad' => '',
					'valor_ipi' => str_replace(",", ".", $i['vipi'][0])
				];

				$itemRes = ItemCompra::create($dataItem);

				$stockMove = new StockMove();


				// Apura Custo

				$valoripi = (str_replace(",", ".", $i['vipi'][0]) / str_replace(",", ".", $i['qCom'][0]));
				$valoricmsst = (str_replace(",", ".", $i['vicmsst'][0]) / str_replace(",", ".", $i['qCom'][0]));

				$custoproduto = $itemRes->valor_unitario + $valoripi + $valoricmsst;

				if ($i['conversao_unitaria'] == 1) {

					$stockMove->pluStock(
						(int) $itemRes->produto_id,
						(str_replace(",", ".", $i['qCom'][0])),
						$custoproduto,
						'Compra',
						'Obs. ' . 'NFE: ' . $resCompra->nf,
						$itemRes->id
					);

				} else {
					$custoproduto = $custoproduto / $i['conversao_unitaria'];
					$stockMove->pluStock(
						(int) $itemRes->produto_id,
						(str_replace(",", ".", $i['qCom'][0]) * $i['conversao_unitaria']),
						$custoproduto,
						'Compra',
						'Obs. ' . 'NFE: ' . $resCompra->nf,
						$itemRes->id
					);


				}







				$produto->updatecustoproduto($i['produtoId'], $custoproduto);


			}



			if ($fatura != null) {

				foreach ($fatura as $f) {
					$dataConta = [
						'compra_id' => NULL,
						'data_vencimento' => \Carbon\Carbon::
							parse(str_replace("/", "-", $f['vencimento']))->format('Y-m-d'),
						'data_pagamento' => \Carbon\Carbon::
							parse(str_replace("/", "-", $f['vencimento']))->format('Y-m-d'),
						'valor_integral' => str_replace(",", ".", $f['valor_parcela']),
						'valor_pago' => 0,
						'referencia' => $f['referencia'],
						'categoria_id' => 1,
						'status' => false,

						'data_emissao' => $dfe->data_emissao,
						'forma_pagamento' => '04',
						'dreconta_id' => 0,
						'fornecedor_id' => $fornecedor['id']

					];




					ContaPagar::create($dataConta);
				}




			}

			$dfe->tipo = 5;
			$dfe->save();
			DB::commit();

			return response()->json("ok", 200);
		} catch (\Exception $e) {
			DB::rollback();
			return response()->json("Erro1: " . $e->getMessage(), 401);
		}

	}

	public function get($id)
	{

		$dfe = ManifestaDfe::find($id);

		return redirect('/dfe/download/' . $dfe->chave);
	}

	public function updatemanifesto($id)
	{
		$manifesto = new ManifestaDfe();
		$up = $manifesto->where('id', $id)->first();

		$up->tipo = 6;

		$result = $up->save();


	}

}

