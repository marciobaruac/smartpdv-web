<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\StockMove;
use App\Models\Estoque;
use App\Models\Produto;
use App\Models\Apontamento;
use App\Models\AlteracaoEstoque;
use App\Models\EstoqueApontamentoManual;
use App\Models\Estoque_mov;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;



class StockController extends Controller
{
	public function __construct(){
        $this->middleware(function ($request, $next) {
            $value = session('user_logged');
            if(!$value){
                return redirect("/login");
            }else{
                if($value['acesso_estoque'] == 0){
                    return redirect("/sempermissao");
                }
            }
            return $next($request);
        });
    }

    public function index(){
        $estoque = Estoque::
        join('produtos','produtos.id', '=', 'estoques.produto_id')
        ->where('produtos.ativo', true)
        ->orderBy('estoques.updated_at', 'desc')
        ->paginate(100);
        return view('stock/list')
        ->with('estoque', $estoque)
        ->with('links', true)
        ->with('title', 'Estoque');
    }



    public function apontamento(){
        $apontamentos = Apontamento::limit(5)
        ->orderBy('id', 'desc')
        ->get();

        $produtos = Produto::where('composto', 1)->get();
        return view('stock/apontamento')
        ->with('apontamentos', $apontamentos)
        ->with('produtos', $produtos)
        ->with('produtoJs', true)
        ->with('title', 'Apontamento');
    }

    public function apontamentoManual(){
        $produtos = Produto::
		 where('ativo', true)
		->orderBy('nome','asc')
		->get();



        return view('stock/apontaManual')
        ->with('produtoJs', false)
        ->with('produtos', $produtos)
        ->with('title', 'Apontamento Manual');
    }

    public function todosApontamentos(){
        $periodo = $this->resolvePeriodoFiltroApontamentos();

        $apontamentos = Apontamento::
        whereBetween('data_apontamento', [$periodo['inicio_data'], $periodo['fim_data']])
        ->orderBy('id', 'desc')
        ->paginate(10);
        return view("stock/todosApontamentos")
        ->with('apontamentos', $apontamentos)
        ->with('dataInicial', $periodo['data_inicial'])
        ->with('dataFinal', $periodo['data_final'])
        ->with('links', true)
        ->with('title', 'Todos os apontamentos');
    }

    public function filtroApontamentos(Request $request){
        $periodo = $this->resolvePeriodoFiltroApontamentos($request->dataInicial, $request->dataFinal);

        $apontamentos = Apontamento::
        whereBetween('data_apontamento', [$periodo['inicio_data'], $periodo['fim_data']])
        ->orderBy('data_registro', 'desc')
        ->paginate(10);
        return view("stock/todosApontamentos")
        ->with('apontamentos', $apontamentos)
        ->with('dataInicial', $periodo['data_inicial'])
        ->with('dataFinal', $periodo['data_final'])
        ->with('links', true)
        ->with('title', 'Todos os apontamentos');
    }

    private function parseDate($date){
        return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
    }

    public function saveApontamento(Request $request){

        DB::beginTransaction();

        $this->_validateApontamento($request);

        $produto = $request->input('produto');
        $produto = explode("-", $produto);
        $produto = $produto[0];

        $result = Apontamento::create([
            'data_apontamento' => $this->parseDate($request->dataproducao),
            'quantidade' => str_replace(",", ".", $request->quantidade),
            'usuario_id' => get_id_user(),
            'produto_id' => $produto
        ]);

        $prod = Produto::
        where('id', $produto)
        ->first();

        $stockMove = new StockMove();

        $stockMove->pluStock((int) $result->produto_id,$result->quantidade,$prod->valor_compra,'Apontamento', 'ID: '.$result->id,$result->id );



      //  $stockMove->pluStock((int) $produto,
        //    str_replace(",", ".", $request->quantidade),
          //  str_replace(",", ".", $prod->valor_venda));

       // $this->downEstoquePorReceita($produto, str_replace(",", ".", $request->quantidade));

       $stockMovesaida = new StockMove();

       foreach($prod->receita->itens as $i){
        $stockMovesaida ->downStock($i->produto->id, ($i->quantidade * $result->quantidade),$i->produto->valor_compra,'Apontamento','ID AP: '. $result->id. ' Qtd Pro.: '.$result->quantidade ,$result->id  );

      }

        if($result && $stockMove &&  $stockMovesaida ){
            DB::commit();
            session()->flash("mensagem_sucesso", "Apontamento cadastrado com sucesso!");
        }else{
            DB::rollback();
            session()->flash('mensagem_erro', 'Erro ao cadastrar apontamento!');
        }

        return redirect("/estoque/apontamentoProducao");
    }


    public function saveApontamentoManual(Request $request){


        DB::beginTransaction();

       // $this->_validateApontamento($request);

        $produto = $request->input('produto');
        $prod = Produto::
        where('id', $produto)
        ->first();

        $quantidadeInformada = (float) str_replace(",", ".", $request->quantidade);

        // Evita duplicidade por reenvio/duplo clique em janela curta.
        $apontamentoDuplicado = EstoqueApontamentoManual::where('produto_id', $produto)
        ->where('usuario_id', get_id_user())
        ->where('tipo', $request->tipo)
        ->where('quantidade', $quantidadeInformada)
        ->where('created_at', '>=', Carbon::now()->subSeconds(15))
        ->exists();

        if($apontamentoDuplicado){
            DB::rollBack();
            session()->flash('mensagem_erro', 'Apontamento duplicado detectado. Aguarde alguns segundos antes de reenviar.');
            return redirect('/estoque');
        }

        $dataApontamento = [
            'produto_id' => $produto,
            'usuario_id' => get_id_user(),
            'quantidade' => $quantidadeInformada,
            'tipo' => $request->tipo,
        ];

        $resultapont = EstoqueApontamentoManual::create($dataApontamento);

        $stockMove = new StockMove();
        $result = null;



        $quantidade =  $quantidadeInformada;
        if ($request->tipo == 'saldo'){
            $stock = $stockMove->existStock($produto);
            if($stock){
                $saldoatual =  $stock->quantidade;
            }else
            {
                $saldoatual = 0;
            }



            $quantidade =  $quantidadeInformada -  $saldoatual  ;

            if ($quantidade >= 0 ){



                $stockMove->pluStock(
                    $prod->id,
                    (float) str_replace(",", ".", $prod->valor_compra),
                    'Manual',
                    'Obs. ' . $request->observacao . ' - Mov: ' . $resultapont->id,
                    $resultapont->id,
                    $prod->valor_compra
                );


            }
            else  {


                $result = $stockMove->downStock(
                    (int) $produto,
                    (float) $quantidade,
                    (float) str_replace(",", ".", $prod->valor_compra),
                    'Manual',
                    'Obs. ' . $request->observacao . ' - Mov: ' . $resultapont->id,
                    $resultapont->id
                );


            }




        }


        else if($request->tipo == 'incremento'){

            $result = $stockMove->pluStock((int) $produto,
            $quantidade,
            str_replace(",", ".", $prod->valor_compra),'Contagem','Obs. '. $request->observacao.'-'.  'Mov: '.$resultapont->id,$resultapont->id);

        }else{

            $result = $stockMove->downStock((int)$produto,$quantidade,$prod->valor_compra,'Contagem','Obs. '. $request->observacao.'-'.  'Mov: '.$resultapont->id,$resultapont->id);

        }



        if ($result && $resultapont){
            DB::commit();
            session()->flash("mensagem_sucesso", "Apontamento Manual cadastrado com sucesso!");
        }else{
            DB::rollback();
            session()->flash('mensagem_erro', 'Erro ao cadastrar apontamento manual!');
        }

        return redirect("/estoque");
    }

    private function downEstoquePorReceita($idProduto, $quantidade){
        $produto = Produto::
        where('id', $idProduto)
        ->first();
        $stockMove = new StockMove();
        foreach($produto->receita->itens as $i){
            $stockMove->downStock($i->produto->id, $i->quantidade * $quantidade);
        }

    }

// public function deleteApontamento($id){
//     $ap = Apontamento::
//     where('id', $id)
//     ->first();

//     $stockMove = new StockMove();
//     foreach($ap->produto->receita->itens as $i){
//         echo $i->quantidade;
//         $stockMove->downStock($i->produto->id, $i->quantidade * $quantidade);
//     }
// }

    private function _validateApontamento(Request $request){
        $rules = [
            'produto' => 'required',
            'quantidade' => 'required',
            'dataproducao' => 'required',

        ];

        $messages = [
            'produto.required' => 'O campo produto é obrigatório.',
            'produto.min' => 'Clique sobre o produto desejado.',
            'quantidade.required' => 'O campo quantidade é obrigatório.',
            'quantidade.min' => 'Informe o valor do campo em casas decimais, ex: 1,000.'
        ];

        $this->validate($request, $rules, $messages);

    }

    public function listApontamentos($id){
        $periodo = $this->resolvePeriodoFiltroApontamentos(request('dataInicial'), request('dataFinal'));

        $apontamentos = Estoque_mov::orderBy('id', 'desc')
        ->where('produto_id', $id)
        ->whereBetween('created_at', [$periodo['inicio_data_hora'], $periodo['fim_data_hora']])
        ->get();

        $origensCompra = [];
        if($apontamentos->count() > 0){
            $origensCompra = DB::table('estoquemovcompras as ec')
            ->join('item_compras as ic', 'ic.id', '=', 'ec.estoquecompra_id')
            ->whereIn('ec.estoquemov_id', $apontamentos->pluck('id')->toArray())
            ->pluck('ic.compra_id', 'ec.estoquemov_id')
            ->toArray();
        }

        // Fallback para registros antigos sem v�nculo em estoquemovcompras.
        foreach($apontamentos as $mov){
            if(isset($origensCompra[$mov->id])) continue;

            $descricao = (string) $mov->descricao;
            $compraId = null;

            // Registros com origem correta.
            if($mov->origem == 'Compra'){
                if(preg_match('/Mov:\s*(\d+)/i', $descricao, $matchMov)){
                    $compraId = (int) $matchMov[1];
                }

                if(!$compraId && preg_match('/NFE:\s*([0-9]+)/i', $descricao, $matchNf)){
                    $nf = $matchNf[1];
                    $compraId = DB::table('compras as c')
                    ->join('item_compras as ic', 'ic.compra_id', '=', 'c.id')
                    ->where('c.nf', $nf)
                    ->where('ic.produto_id', $mov->produto_id)
                    ->orderBy('c.id', 'desc')
                    ->value('c.id');
                }
            }

            // Registros antigos com par�metros invertidos:
            // origem = custo (2.17), descricao = 'Compra', valor = item_compra_id.
            if(!$compraId && strtolower(trim($descricao)) == 'compra' && is_numeric($mov->origem) && is_numeric($mov->valor)){
                $itemCompraId = (int) $mov->valor;
                $compraId = DB::table('item_compras')->where('id', $itemCompraId)->value('compra_id');
            }

            // Extorno de entrada normalmente traz compra em "Mov: {id}".
            if(!$compraId && strtolower((string)$mov->origem) == 'extorno da entrada' && preg_match('/Mov:\s*(\d+)/i', $descricao, $matchExt)){
                $compraId = (int) $matchExt[1];
            }

            if($compraId){
                $origensCompra[$mov->id] = $compraId;
            }
        }

        $origensDfe = [];
        $origensNf = [];
        if(count($origensCompra) > 0){
            $compras = DB::table('compras')
            ->whereIn('id', array_values($origensCompra))
            ->get(['id', 'dfe_id', 'nf']);

            $comprasPorId = [];
            foreach($compras as $c){
                $comprasPorId[$c->id] = [
                    'dfe_id' => $c->dfe_id,
                    'nf' => $c->nf
                ];
            }

            foreach($origensCompra as $movId => $compraId){
                $origensDfe[$movId] = isset($comprasPorId[$compraId]) ? $comprasPorId[$compraId]['dfe_id'] : null;
                $origensNf[$movId] = isset($comprasPorId[$compraId]) ? $comprasPorId[$compraId]['nf'] : null;
            }
        }

        $totaisPorOrigem = [];
        $totaisGerais = [
            'entrada' => 0.0,
            'saida' => 0.0,
            'saldo' => 0.0
        ];

        foreach($apontamentos as $mov){
            $origemResumo = $this->normalizarOrigemMovimento($mov);
            $tipo = strtolower((string)$mov->tipomov);
            $quantidade = (float)$mov->quantidade;

            if(!isset($totaisPorOrigem[$origemResumo])){
                $totaisPorOrigem[$origemResumo] = [
                    'entrada' => 0.0,
                    'saida' => 0.0,
                    'saldo' => 0.0
                ];
            }

            if($tipo == 'entrada'){
                $totaisPorOrigem[$origemResumo]['entrada'] += $quantidade;
                $totaisGerais['entrada'] += $quantidade;
            }else{
                $totaisPorOrigem[$origemResumo]['saida'] += $quantidade;
                $totaisGerais['saida'] += $quantidade;
            }
        }

        foreach($totaisPorOrigem as $origem => $totais){
            $totaisPorOrigem[$origem]['saldo'] = $totais['entrada'] - $totais['saida'];
        }

        $totaisGerais['saldo'] = $totaisGerais['entrada'] - $totaisGerais['saida'];



        return view('stock/listaAlteracao')
        ->with('title', 'Histórico de Movimentação')
        ->with('produto_id', $id)
        ->with('dataInicial', $periodo['data_inicial'])
        ->with('dataFinal', $periodo['data_final'])
        ->with('origensCompra', $origensCompra)
        ->with('origensDfe', $origensDfe)
        ->with('origensNf', $origensNf)
        ->with('totaisPorOrigem', $totaisPorOrigem)
        ->with('totaisGerais', $totaisGerais)
        ->with('apontamentos', $apontamentos);
    }

    public function listTipoSaldo($id){
     //   $apontamentos = Estoque_mov::orderBy('id', 'desc')
       // ->where('produto_id', $id)
        //->get();


        $apontamentos = EstoqueApontamentoManual
		::select(\DB::raw(' produtos.nome, estoque_apontamento_manuals.quantidade - estoque_movs.quantidade as saldoant, estoque_apontamento_manuals.quantidade as qtdinf, estoque_movs.quantidade as qtddif, estoque_apontamento_manuals.created_at '))
		->join('estoquemovmanuals', 'estoquemanual_id', '=', 'estoque_apontamento_manuals.id')
        ->join('estoque_movs', 'estoque_movs.id', '=', 'estoquemovmanuals.estoquemov_id')
     	->join('produtos','produtos.id', '=', 'estoque_apontamento_manuals.produto_id')
        ->orderBy('estoque_apontamento_manuals.created_at','desc')
	    ->where('estoque_apontamento_manuals.produto_id', $id)
        ->where('estoque_apontamento_manuals.tipo', 'saldo')
        ->where('estoque_apontamento_manuals.quantidade', '<>', 0)
        ->whereRaw('(estoque_apontamento_manuals.quantidade - estoque_movs.quantidade) <> 0');



        $apontamentos=   $apontamentos->get();

        return view('stock/listaTipoSaldo')
        ->with('title', 'Analise Apontamento Tipo Saldo')
        ->with('apontamentos', $apontamentos);
    }

    private function normalizarOrigemMovimento($mov){
        $origem = trim((string)$mov->origem);
        $descricao = strtolower(trim((string)$mov->descricao));
        $origemLower = strtolower($origem);

        if($origem == 'Compra' || strpos($descricao, 'compra') !== false) return 'Compra';
        if($origemLower == 'extorno da entrada') return 'Extorno da Entrada';
        if($origemLower == 'pdv') return 'PDV';
        if($origemLower == 'manual') return 'Manual';
        if($origemLower == 'apontamento') return 'Apontamento';

        // Registros legados: origem gravada com custo (ex.: 2.17) devem agrupar como Compra.
        $origemNumerica = str_replace(',', '.', $origem);
        if(is_numeric($origemNumerica)) return 'Compra';

        return $origem != '' ? $origem : 'Nao informado';
    }

    private function resolvePeriodoFiltroApontamentos($dataInicial = null, $dataFinal = null){
        $inicio = null;
        $fim = null;

        if(!empty($dataInicial) && !empty($dataFinal)){
            try{
                $inicio = Carbon::createFromFormat('d/m/Y', $dataInicial)->startOfDay();
                $fim = Carbon::createFromFormat('d/m/Y', $dataFinal)->endOfDay();
            }catch(\Exception $e){
                $inicio = null;
                $fim = null;
            }
        }

        if(!$inicio || !$fim){
            $fim = Carbon::today()->endOfDay();
            $inicio = Carbon::today()->subDays(14)->startOfDay();
        }

        if($inicio->gt($fim)){
            $aux = $inicio->copy();
            $inicio = $fim->copy()->startOfDay();
            $fim = $aux->copy()->endOfDay();
        }

        return [
            'data_inicial' => $inicio->format('d/m/Y'),
            'data_final' => $fim->format('d/m/Y'),
            'inicio_data' => $inicio->toDateString(),
            'fim_data' => $fim->toDateString(),
            'inicio_data_hora' => $inicio->toDateTimeString(),
            'fim_data_hora' => $fim->toDateTimeString(),
        ];
    }

    public function listApontamentosDelte($id){
        $alteracao = AlteracaoEstoque::find($id);

        $stockMove = new StockMove();

        if($alteracao->tipo != 'incremento'){
            $result = $stockMove->pluStock($alteracao->produto_id, $alteracao->quantidade);
        }else{
            $result = $stockMove->downStock($alteracao->produto_id, $alteracao->quantidade);
        }

        $alteracao->delete();

        session()->flash('mensagem_sucesso', 'Registro removido!');
        return redirect("/estoque/listApontamentos/" . $alteracao->produto_id);

    }

}






