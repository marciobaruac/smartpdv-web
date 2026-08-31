<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VendaCaixa;
use App\Helpers\StockMove;
use App\Models\ConfigNota;
use App\Models\NaturezaOperacao;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\Cliente;
use App\Models\Tributacao;
use App\Models\Usuario;
use App\Models\Certificado;
use App\Models\ListaPreco;
use App\Models\AberturaCaixa;
use App\Models\ProdutoPizza;
use App\Models\Orcamento;
use App\Models\CreditoVenda;
use App\Models\ConfigCaixa;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDelete;
use App\Models\ItemPedido;
use App\Models\ComplementoDelivery;
use App\Models\ItemPedidoComplementoLocal;
use App\Models\ProdutoListaPreco;
use App\Models\SangriaCaixa;
//use App\Models\Cupom;
use NFePHP\DA\NFe\CupomFechamento;
use NFePHP\DA\NFe\CupomFechamentoPeriodo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\NfceInutilizacao;



class FrontBoxController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $value = session('user_logged');
            if (!$value) {
                return redirect("/login");
            } else {
                if ($value['acesso_caixa'] == 0) {
                    return redirect("/sempermissao");
                }
            }
            return $next($request);
        });
    }

    public function index($pedidoId = 0)
    {

        $config = ConfigNota::first();
        $naturezas = NaturezaOperacao::all();

        $categorias = Categoria::orderBy('nome', 'desc')
            ->get();

        //  $produtos = Produto::
        $produtosaux = Produto::where('valor_venda', '>', 0)
            ->where('ativo', true)

            ->get();
        $tributacao = Tributacao::first();
        $tiposPagamento = VendaCaixa::tiposPagamento();
        $config = ConfigNota::first();
        $certificado = Certificado::first();
        $usuario = Usuario::find(get_id_user());

        if (count($naturezas) == 0 || count($produtosaux) == 0 || $config == null || count($categorias) == 0 || $tributacao == null) {

            return view("frontBox/alerta")
                ->with('produtos', count($produtosaux))
                ->with('categorias', count($categorias))
                ->with('naturezas', $naturezas)
                ->with('config', $config)
                ->with('tributacao', $tributacao)
                ->with('title', "Validação para Emitir");
        } else {

            if ($config->nat_op_padrao == 0) {

                session()->flash('mensagem_erro', 'Informe a natureza de operação para o PDV!');
                return redirect('/configNF');
            } else {
                $tiposPagamentoMulti = VendaCaixa::tiposPagamentoMulti();
                $produtos = Produto::select('produtos.*', 'produto_lista_precos.referencia as referenciatabela', 'produto_lista_precos.id as idprecoproduto', 'produto_lista_precos.valor', 'produto_lista_precos.quantidade_minima', 'lista_precos.nome as nomelista', 'lista_precos.id as lista')
                    ->join('produto_lista_precos', 'produtos.id', 'produto_lista_precos.produto_id')
                    ->join('lista_precos', 'lista_id', 'lista_precos.id')
                    ->where('valor', '>', 0)
                    ->where('ativo', true)
                    ->orderBy('produto_lista_precos.ordem')->get();



                foreach ($produtos as $p) {
                    $p->listaPreco;
                }
                $categorias = Categoria::orderBy('nome')->get();
                $clientes = Cliente::orderBy('razao_social')->get();

                foreach ($clientes as $c) {
                    $c->totalEmAberto = 0;
                    $soma = $this->getTotalContaCredito($c);
                    if ($soma != null) {
                        $c->totalEmAberto = $soma->total;
                    }
                }

                $orcamentos = Orcamento::where('estado', 'NOVO')
                    ->get();

                $atalhos = ConfigCaixa::where('usuario_id', get_id_user())
                    ->first();

                $view = 'main';
                if ($atalhos != null && $atalhos->modelo_pdv == 1) {
                    $view = 'main2';
                }

                $mesas = Mesa::all();

                $adicionais = ComplementoDelivery::all();
                foreach ($adicionais as $a) {
                    $a->nome = $a->nome();
                }

                $pedido = null;

                if ($pedidoId > 0) {
                    $pedido = Pedido::find($pedidoId);
                }

                $pedidos = Pedido::where('desativado', false)
                    ->orderBy('comanda')
                    ->get();

                return view('frontBox/' . $view)
                    ->with('frenteCaixa', true)
                    ->with('tiposPagamento', $tiposPagamento)
                    ->with('config', $config)
                    ->with('pedidos', $pedidos)
                    ->with('atalhos', $atalhos)
                    ->with('adicionais', $adicionais)
                    ->with('mesas', $mesas)
                    ->with('pedido', $pedido)
                    ->with('orcamentos', $orcamentos)
                    ->with('certificado', $certificado)
                    ->with('listaPreco', ListaPreco::all())
                    ->with('disableFooter', true)
                    ->with('usuario', $usuario)
                    ->with('produtos', $produtos)
                    ->with('clientes', $clientes)
                    ->with('categorias', $categorias)
                    ->with('tiposPagamentoMulti', $tiposPagamentoMulti)
                    ->with('title', 'Frente de Caixa');
            }
        }
    }

    private function getTotalContaCredito($cliente)
    {
        return CreditoVenda::selectRaw('sum(vendas.valor_total) as total')
            ->join('vendas', 'vendas.id', '=', 'credito_vendas.venda_id')
            ->where('credito_vendas.cliente_id', $cliente->id)
            ->where('status', 0)
            ->first();
    }

    private function cancelarNFCe($venda)
    {
        $config = ConfigNota::first();

        $cnpj = str_replace(".", "", $config->cnpj);
        $cnpj = str_replace("/", "", $cnpj);
        $cnpj = str_replace("-", "", $cnpj);
        $cnpj = str_replace(" ", "", $cnpj);
        $nfe_service = new NFeService([
            "atualizacao" => date('Y-m-d h:i:s'),
            "tpAmb" => 2,
            "razaosocial" => $config->razao_social,
            "siglaUF" => $config->UF,
            "cnpj" => $cnpj,
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "AAAAAAA",
            "CSC" => "XTZOH6COASX5DYLKBUZXG5TABFG7ZFTQVSA2",
            "CSCid" => "000001"
        ], 65);

        $nfce = $nfe_service->cancelarNFCe($venda->id, "Troca de produtos requisitada pelo cliente");
        return is_array($nfce);
    }

    public function deleteVenda(Request $request)
    {
        DB::beginTransaction();

        $datacancelamento = date('Y/m/d H:i:s');


        $id = $request->id;

        $venda = VendaCaixa
            ::where('id', $id)
            ->first();


        $stockMove = new StockMove();

        foreach ($venda->itens as $i) {
            if ($i->produto->receita) {
                $receita = $i->produto->receita;
                foreach ($receita->itens as $rec) {

                    if (!empty($rec->produto->receita)) {


                        $receita2 = $rec->produto->receita;

                        foreach ($receita2->itens as $rec2) {

                            $produtor = Produto::where('id', $rec2->produto_id)
                                ->first();
                            $stockMove->pluStock(
                                $rec2->produto_id,
                                (float) str_replace(",", ".", $i['quantidade']) * ($rec2->quantidade / $receita2->rendimento),
                                'Extorno de Venda',
                                'Obs. ' . 'N: ' . '-' . 'Mov: ' . $venda->id,
                                $i->id,
                                $i->produto->valor_compra
                            );
                        }
                    } else {
                        $stockMove->pluStock(
                            $i->produto->id,
                            $i->quantidade,
                            'Extorno de Venda',
                            'Obs. ' . 'N: ' . '-' . 'Mov: ' . $venda->id,
                            $i->id,
                            $i->produto->valor_compra
                        );
                    }
                }
            } else {

                $stockMove->pluStock(
                    $i->produto->id,
                    $i->quantidade,
                    'Extorno de Venda',
                    'Obs. ' . 'N: ' . '-' . 'Mov: ' . $venda->id,
                    $i->id,
                    $i->produto->valor_compra
                );
            }
        }

        foreach ($venda->duplicatas as $c) {

            $c->ativo = false;
            $c->save();
        }

        $venda->ativo = false;
        $venda->data_cancelamento =  $datacancelamento;
        $venda->motivo_cancelamento = $request->justificativa;
        if ($venda->save()) {
            DB::commit();
            //   session()->flash("mensagem_sucesso", "Venda cancelada com sucesso!");
            //   return response()->json($venda, 200);

            //    echo json_encode($result);


        } else {
            //  session()->flash('mensagem_erro', 'Erro ao cancelar venda!');
            //     return response()->json($venda, 401);

        }

        echo json_encode($venda);
        //return redirect('/frenteCaixa/devolucao');

    }

    public function list()
    {
        // $vendas = VendaCaixa::
        // orderBy('id', 'desc')
        // ->get();

        $vendas = VendaCaixa::filtroData(
            $this->parseDate(date("Y-m-d")),
            $this->parseDate(date("Y-m-d"), true)
        );
        $dataInicial =  date('d/m/Y');
        $dataFinal =    date('d/m/Y');


        $somaTiposPagamento = $this->somaTiposPagamento($vendas);
        return view('frontBox/list')
            ->with('vendas', $vendas)
            ->with('frenteCaixa', true)
            ->with('somaTiposPagamento', $somaTiposPagamento)
            ->with('data1', $this->parseDate($dataInicial))
            ->with('data2', $this->parseDate($dataFinal))
            ->with('estadoNfce', 'todos')

            ->with('info', "Lista de vendas de Hoje: " . date("d/m/Y"))
            ->with('title', 'Lista de Vendas na Frente de Caixa');
    }

    private function somaTiposPagamento($vendas)
    {
        $tipos = $this->preparaTipos();

        foreach ($vendas as $v) {
            if (isset($tipos[$v->tipo_pagamento])) {
                $tipos[$v->tipo_pagamento] += $v->valor_total;
            }
        }
        return $tipos;
    }

    private function preparaTipos()
    {
        $temp = [];
        foreach (VendaCaixa::tiposPagamento() as $key => $tp) {
            $temp[$key] = 0;
        }
        return $temp;
    }

    public function devolucao()
    {
        $vendas = VendaCaixa::where('ativo', true)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();
        return view('frontBox/devolucao')
            ->with('vendas', $vendas)
            ->with('frenteCaixa', true)
            ->with('nome', '')
            ->with('nfce', '')
            ->with('valor', '')
            ->with('info', "Lista das ultimas 20 vendas")

            ->with('title', 'Devolução');
    }

    public function filtro(Request $request)
    {
        $dataInicial = $request->data_inicial;
        $dataFinal = $request->data_final;
        $estadoNfce = $this->normalizarFiltroEstadoNfce((string) $request->estado_nfce);

        $query = VendaCaixa::where('ativo', true)
            ->orderBy('id', 'desc')
            ->whereBetween('created_at', [
                $this->parseDate($dataInicial),
                $this->parseDate($dataFinal, true)
            ]);

        $this->aplicarFiltroEstadoNfce($query, $estadoNfce);
        $vendas = $query->get();

        $somaTiposPagamento = $this->somaTiposPagamento($vendas);
        $textoEstado = $this->descricaoFiltroEstadoNfce($estadoNfce);

        return view('frontBox/list')
            ->with('vendas', $vendas)
            ->with('dataInicial', $dataInicial)
            ->with('somaTiposPagamento', $somaTiposPagamento)
            ->with('info', "Lista de vendas período: $dataInicial até $dataFinal")
            ->with('dataFinal', $dataFinal)
            ->with('estadoNfce', $estadoNfce)

            ->with('data1', $this->parseDate($request->data_inicial))
            ->with('data2', $this->parseDate($request->data_final))

            ->with('frenteCaixa', true)
            ->with('info', "Lista de vendas periodo: $dataInicial ate $dataFinal - $textoEstado")
            ->with('title', 'Filtro de Vendas na Frente de Caixa');
    }

    private function normalizarFiltroEstadoNfce(string $estado): string
    {
        $estado = strtolower(trim($estado));
        $permitidos = ['todos', 'rejeitado', 'pendente', 'rejeitado_pendente', 'aprovado', 'disponivel'];
        return in_array($estado, $permitidos, true) ? $estado : 'todos';
    }

    private function aplicarFiltroEstadoNfce($query, string $estado): void
    {
        if ($estado === 'todos') {
            return;
        }

        if ($estado === 'rejeitado_pendente') {
            $query->whereIn('estado', ['REJEITADO', 'PENDENTE']);
            return;
        }

        $query->where('estado', strtoupper($estado));
    }

    private function descricaoFiltroEstadoNfce(string $estado): string
    {
        $descricoes = [
            'todos' => 'Todos os estados',
            'rejeitado' => 'Somente rejeitadas',
            'pendente' => 'Somente pendentes',
            'rejeitado_pendente' => 'Rejeitadas + pendentes',
            'aprovado' => 'Somente aprovadas',
            'disponivel' => 'Somente disponiveis',
        ];

        return $descricoes[$estado] ?? $descricoes['todos'];
    }

    private function parseDate($date, $plusDay = false)
    {
        if ($plusDay == false)
            return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
        else
            return date('Y-m-d', strtotime("+1 day", strtotime(str_replace("/", "-", $date))));
    }



    public function filtroCliente(Request $request)
    {

        $vendas = VendaCaixa::filtroCliente($request->nome);
        return view('frontBox/devolucao')
            ->with('vendas', $vendas)
            ->with('frenteCaixa', true)
            ->with('valor', '')
            ->with('nome', $request->nome)
            ->with('nfce', '')
            ->with('info', "Filtro cliente: $request->nome")

            ->with('title', 'Filtro por cliente');
    }


    public function filtroNFCe(Request $request)
    {

        $vendas = VendaCaixa::filtroNFCe($request->nfce);
        return view('frontBox/devolucao')
            ->with('vendas', $vendas)
            ->with('frenteCaixa', true)
            ->with('valor', '')
            ->with('nfce', $request->nfce)
            ->with('nome', '')
            ->with('info', "Filtro NFCE: $request->nfce")
            ->with('title', 'Filtro por NFCe');
    }

    public function filtroValor(Request $request)
    {

        $vendas = VendaCaixa::filtroValor($request->valor);
        return view('frontBox/devolucao')
            ->with('vendas', $vendas)
            ->with('frenteCaixa', true)
            ->with('nfce', '')
            ->with('valor', $request->valor)
            ->with('nome', '')
            ->with('info', "Filtro valor: $request->valor")

            ->with('title', 'Filtro por Valor');
    }

    public function fechar()
    {
        $abertura = AberturaCaixa::where('ultima_venda', 0)->orderBy('id', 'desc')->first();
        $ultimaFechada = AberturaCaixa::where('ultima_venda', '>', 0)->orderBy('id', 'desc')->first();
        $usuario = Usuario::find(get_id_user());


        $ultimaVendaCaixa = VendaCaixa::orderBy('id', 'desc')
            ->first();
        $vendas = [];
        $somaTiposPagamento = [];
        if ($ultimaVendaCaixa != null) {
            $ultimaVendaCaixa = $ultimaVendaCaixa->id;

            $vendas = VendaCaixa::where('ativo', true)
                ->whereBetween('id', [
                    ($ultimaFechada != null ? $ultimaFechada->ultima_venda + 1 : 0),
                    $ultimaVendaCaixa
                ])
                ->get();

            $somaTiposPagamento = $this->somaTiposPagamento($vendas);
        }
        if ($abertura == null) {
            return redirect('/frenteCaixa')->with('erro', 'O caixa esta fechado!!');
        } else {
            return view('frontBox/fechar_caixa')
                ->with('vendas', $vendas)
                ->with('abertura', $abertura)
                ->with('usuario', $usuario)

                ->with('somaTiposPagamento', $somaTiposPagamento)
                ->with('title', 'Fechar caixa');
        }
    }

  public function fecharPost(Request $request)
{
    \Log::info('Iniciando fechamento de caixa', [
        'request' => $request->all()
    ]);

    try {
        // Validação dos dados
        $request->validate([
            'abertura_id' => 'required|exists:abertura_caixas,id',
            'saldo_informado_fechamento' => 'required|numeric|min:0',
        ]);

        \Log::info('Validação OK', [
            'abertura_id' => $request->abertura_id,
            'saldo_informado_fechamento' => $request->saldo_informado_fechamento
        ]);

        // Busca a abertura
        $id = $request->abertura_id;
        $abertura = AberturaCaixa::find($id);

        if (!$abertura) {
            \Log::error('Abertura não encontrada', ['id' => $id]);
            throw new \Exception('Abertura não encontrada');
        }

        \Log::info('Abertura encontrada', [
            'abertura' => $abertura->toArray()
        ]);

        // Busca a última venda
        $ultimaVendaCaixa = VendaCaixa::orderBy('id', 'desc')->first();

        if ($ultimaVendaCaixa) {
            \Log::info('Última venda encontrada', [
                'id' => $ultimaVendaCaixa->id
            ]);
        } else {
            \Log::warning('Nenhuma venda encontrada');
        }

        // Atualiza os campos da abertura
        $abertura->ultima_venda = $ultimaVendaCaixa ? $ultimaVendaCaixa->id : null;
        $abertura->saldo_informado_fechamento = $request->saldo_informado_fechamento;

        $abertura->save();

        \Log::info('Abertura atualizada com sucesso', [
            'abertura_id' => $abertura->id,
            'ultima_venda' => $abertura->ultima_venda,
            'saldo_informado_fechamento' => $abertura->saldo_informado_fechamento
        ]);

        // Mensagem de sucesso
        session()->flash("mensagem_sucesso", "Caixa fechado com sucesso!");

        return redirect('frenteCaixa/list');

    } catch (\Exception $e) {
        \Log::error('Erro ao fechar o caixa', [
            'erro' => $e->getMessage(),
            'linha' => $e->getLine(),
            'arquivo' => $e->getFile()
        ]);

        session()->flash("mensagem_erro", "Erro ao fechar caixa: " . $e->getMessage());
        return redirect()->back();
    }
}



    public function fechamentos()
    {
        $aberturas = AberturaCaixa::where('ultima_venda', '>', 0)->get();
        $arr = [];

        for ($i = 0; $i < sizeof($aberturas); $i++) {
            $atual = $aberturas[$i]->ultima_venda;
            if ($i == 0) {
                $anterior = 0;
            } else {
                $anterior = $aberturas[$i - 1]->ultima_venda;
            }
            $vendas = VendaCaixa
                ::whereBetween('id', [
                    $anterior + 1,
                    $atual
                ])
                ->where('ativo', true)
                ->get();

            $total = 0;
            foreach ($vendas as $v) {
                $total += $v->valor_total;
            }

            $temp = [
                'inicio' => \Carbon\Carbon::parse($aberturas[$i]->created_at)->format('d/m/Y H:i:s'),
                'fim' => \Carbon\Carbon::parse($aberturas[$i]->updated_at)->format('d/m/Y H:i:s'),
                'total' => $total,
                'id' => $aberturas[$i]->id
            ];

            array_push($arr, $temp);
        }

        usort($arr, function ($a, $b) {
            return ($a['id'] < $b['id']) ? 1 : -1;
        });

        return view('frontBox/fechamentos')
            ->with('fechamentos', $arr)
            ->with('title', 'Lista de Caixas');
    }

    public function listaFechamento($id)
    {
        $aberturas = AberturaCaixa::all();
        $abertura = null;
        $inicio = 0;
        $fim = 0;

        for ($i = 0; $i < sizeof($aberturas); $i++) {
            if ($aberturas[$i]->id == $id) {
                $abertura = $aberturas[$i];
                if ($i > 0) {
                    $inicio = $aberturas[$i - 1]->ultima_venda + 1;
                }

                $fim = $aberturas[$i]->ultima_venda;
            }
        }

        $vendas = [];
        $somaTiposPagamento = [];


        $vendas = VendaCaixa
            ::whereBetween('id', [
                $inicio,
                $fim
            ])
            ->where('ativo', true)
            ->get();

        $somaTiposPagamento = $this->somaTiposPagamento($vendas);

        return view('frontBox/lista_fecha_caixa')
            ->with('vendas', $vendas)
            ->with('abertura', $abertura)
            ->with('somaTiposPagamento', $somaTiposPagamento)
            ->with('title', 'Detalhe fecha caixa');
    }


    public function imprimirFechamento($id)
{
    $aberturas = AberturaCaixa::all();
    $abertura = null;
    $inicio = 0;
    $fim = 0;

    for ($i = 0; $i < sizeof($aberturas); $i++) {
        if ($aberturas[$i]->id == $id) {
            $abertura = $aberturas[$i];
            if ($i > 0) {
                $inicio = $aberturas[$i - 1]->ultima_venda + 1;
            }
            $fim = $aberturas[$i]->ultima_venda;
        }
    }

    $dadosfechamento = AberturaCaixa::where('id', $id)->first();

    // 🔸 Soma dos pagamentos simples
    $somaTiposPagamento = VendaCaixa::selectRaw('sum(valor_total) as total, tipo_pagamento')
        ->whereBetween('id', [$inicio, $fim])
        ->where('tipo_pagamento', '<>', '99')
        ->where('ativo', true)
        ->groupBy('tipo_pagamento')
        ->get();

    // 🔸 Soma dos pagamentos múltiplos
    $somaMultFormas = VendaCaixa::select(
            'valor_pagamento_1',
            'valor_pagamento_2',
            'valor_pagamento_3',
            'tipo_pagamento_1',
            'tipo_pagamento_2',
            'tipo_pagamento_3'
        )
        ->whereBetween('id', [$inicio, $fim])
        ->where('tipo_pagamento', '=', '99')
        ->where('ativo', true)
        ->get();

    // 🔸 Consulta das sangrias dentro do período
    $sangrias = SangriaCaixa::whereBetween('data_registro', [
            $dadosfechamento->data_registro,
            $dadosfechamento->updated_at
        ])
        ->get();

    // 🔥 🔸 Criação do log se tiver sangria
    if ($sangrias->count() > 0) {
        $valorSangria = $sangrias->sum('valor');

        \Log::info('Fechamento de caixa com sangria detectada', [
            'abertura_id' => $id,
            'usuario' => auth()->user()->name ?? 'Desconhecido',
            'valor_sangria' => $valorSangria,
            'data_fechamento' => now()->format('Y-m-d H:i:s'),
            'mensagem' => 'Fechamento de caixa realizado com sangria.'
        ]);
    }

    // 🔸 Caminho da logo
    $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
    $pathLogo = $public . 'imgs/logo.jpg';

    // 🔸 Geração do cupom PDF
    $cupom = new CupomFechamento($somaTiposPagamento, $somaMultFormas, $dadosfechamento, $sangrias, $pathLogo);
    $cupom->monta();
    $pdf = $cupom->render();

    return response($pdf)
        ->header('Content-Type', 'application/pdf');
}





    public function config()
    {

        $config = ConfigCaixa::where('usuario_id', get_id_user())
            ->first();

        return view('frontBox/config')
            ->with('config', $config)
            ->with('title', 'Configuração Caixa');
    }

    public function configSave(Request $request)
    {
        // $usuario = Usuario::find(get_id_user());
        $config = ConfigCaixa::where('usuario_id', get_id_user())
            ->first();

        if ($config == null) {
            $data = [
                'finalizar' => $request->finalizar ?? '',
                'reiniciar' => $request->reiniciar ?? '',
                'editar_desconto' => $request->editar_desconto ?? '',
                'editar_acrescimo' => $request->editar_acrescimo ?? '',
                'editar_observacao' => $request->editar_observacao ?? '',
                'setar_valor_recebido' => $request->setar_valor_recebido ?? '',
                'forma_pagamento_dinheiro' => $request->forma_pagamento_dinheiro ?? '',
                'forma_pagamento_debito' => $request->forma_pagamento_debito ?? '',
                'forma_pagamento_credito' => $request->forma_pagamento_credito ?? '',
                'forma_pagamento_pix' => $request->forma_pagamento_pix ?? '',
                'setar_leitor' => $request->setar_leitor ?? '',
                'impressora_modelo' => $request->impressora_modelo ?? 80,
                'modelo_pdv' => $request->modelo_pdv,
                'valor_recebido_automatico' => $request->valor_recebido_automatico ? true : false,
                'usuario_id' => get_id_user()
            ];

            ConfigCaixa::create($data);
            session()->flash("mensagem_sucesso", "Configuração salva!");
        } else {
            $config->finalizar = $request->finalizar ?? '';
            $config->reiniciar = $request->reiniciar ?? '';
            $config->editar_desconto = $request->editar_desconto ?? '';
            $config->editar_acrescimo = $request->editar_acrescimo ?? '';
            $config->editar_observacao = $request->editar_observacao ?? '';
            $config->setar_valor_recebido = $request->setar_valor_recebido ?? '';
            $config->forma_pagamento_dinheiro = $request->forma_pagamento_dinheiro ?? '';
            $config->forma_pagamento_debito = $request->forma_pagamento_debito ?? '';
            $config->forma_pagamento_credito = $request->forma_pagamento_credito ?? '';
            $config->forma_pagamento_pix = $request->forma_pagamento_pix ?? '';
            $config->setar_leitor = $request->setar_leitor ?? '';
            $config->valor_recebido_automatico = $request->valor_recebido_automatico ? true : false;
            $config->impressora_modelo = $request->impressora_modelo ?? 80;
            $config->modelo_pdv = $request->modelo_pdv;

            $config->save();
            session()->flash("mensagem_sucesso", "Configuração editada!");
        }

        return redirect()->back();
    }

    public function saveItemPedido(Request $request)
    {
        $this->_validateItem($request);

        // print_r($request->all());

        $comanda = Pedido::where('comanda', $request->id)
            // ->where('desativado', false)
            ->first();

        if (empty($comanda)) {
            $res = Pedido::create([
                'comanda' => $request->id,
                'observacao' => $request->observacao ?? '',
                'status' => false,
                'nome' => '',
                'rua' => '',
                'numero' => '',
                'bairro_id' => null,
                'referencia' => '',
                'telefone' => '',
                'fechar_mesa' => false,
                'desativado' => false,
                'mesa_id' => $request->mesa_id != 'null' ? $request->mesa_id : null
            ]);
            if ($res) {
                $result = ItemPedido::create([
                    'pedido_id' => $res->id,
                    'produto_id' => $request->produto,
                    'quantidade' => str_replace(",", ".", $request->quantidade),
                    'status' => false,
                    'tamanho_pizza_id' => $request->tamanho_pizza_id ?? NULL,
                    'observacao' => $request->observacao ?? '',
                    'valor' => str_replace(",", ".", $request->valor),
                    'impresso' => false,
                    'usuario_id' => get_id_user()
                ]);

                if ($request->adicional > 0) {
                    $item = ItemPedidoComplementoLocal::create([
                        'item_pedido' => $result->id,
                        'complemento_id' => $request->adicional,
                        'quantidade' => str_replace(",", ".", $request->quantidade),
                    ]);
                }
                session()->flash('mensagem_sucesso', 'Comanda aberta com sucesso!');
                return redirect('/frenteCaixa/comPedido/' . $res->id);
            }
        } else {
            $result = ItemPedido::create([
                'pedido_id' => $comanda->id,
                'produto_id' => $request->produto,
                'quantidade' => str_replace(",", ".", $request->quantidade),
                'status' => false,
                'tamanho_pizza_id' => $request->tamanho_pizza_id ?? NULL,
                'observacao' => $request->observacao ?? '',
                'valor' => str_replace(",", ".", $request->valor),
                'impresso' => false,
                'usuario_id' => get_id_user()
            ]);

            if ($request->adicional > 0) {
                $item = ItemPedidoComplementoLocal::create([
                    'item_pedido' => $result->id,
                    'complemento_id' => $request->adicional,
                    'quantidade' => str_replace(",", ".", $request->quantidade),
                ]);
            }
            // session()->flash('mensagem_erro', 'Esta comanda encontra-se ativa!');
            // return redirect()->back();
            session()->flash('mensagem_sucesso', 'Item adicionado com sucesso!');
            return redirect('/frenteCaixa/comPedido/' . $comanda->id);
        }
    }

    private function _validateItem(Request $request)
    {
        $validaTamanho = false;

        $rules = [
            'id' => 'required',
            'produto' => 'required|numeric|min:1',
            'quantidade' => 'required',
            'valor' => 'required',
        ];

        $messages = [
            'id.required' => 'Campo obrigatório.',
            'produto.required' => 'O campo produto é obrigatório.',
            'produto.numeric' => 'O campo produto é obrigatório.',
            'produto.min' => 'O campo produto é obrigatório.',
            'quantidade.required' => 'O campo quantidade é obrigatório.',
            'valor.required' => 'O campo valor é obrigatório.',
        ];

        $this->validate($request, $rules, $messages);
    }

    public function deleteItemPedido($id)
    {
        $item = ItemPedido::where('id', $id)
            ->first();

        PedidoDelete::create(
            [
                'pedido_id' => $item->pedido_id,
                'produto' => $item->nomeDoProduto(),
                'quantidade' => $item->quantidade,
                'valor' => $item->valor,
                'data_insercao' => \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i:s')
            ]
        );

        if ($item->delete()) {
            session()->flash('mensagem_sucesso', 'Item removido!');
        } else {
            session()->flash('mensagem_erro', 'Erro');
        }
        return redirect()->back();
    }


    //  public function imprimirFechamento($id){
    //	$venda = VendaCaixa::
    //	where('id', $id)
    //	->first();
    //	$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
    //	$pathLogo = $public.'imgs/logo.jpg';

    //	$cupom = new Cupom($venda, $pathLogo);
    //$cupom->monta();
    //$pdf = $cupom->render();

    // header('Content-Type: application/pdf');
    // echo $pdf;
    //	return response($pdf)
    //	->header('Content-Type', 'application/pdf');
    public function relatorioFiltro($data_inicial, $data_final)
    {

        //  $domPdf = new Dompdf();

        // ob_start();

        //$fluxo = $this->criarArrayDeDatas($this->parseDate($data_inicial),
        //    $this->parseDate($data_final));
        // $p = view('fluxoCaixa/relatorio')
        // ->with('fluxo', $fluxo);
        //  $domPdf->loadHtml($p);

        // $pdf = ob_get_clean();

        //  $domPdf->setPaper("A4");
        // $domPdf->render();
        // $domPdf->stream("file.pdf");

        $aberturas = AberturaCaixa::all();
        $abertura = null;
        $inicio = 0;
        $fim = 0;

        //print_r($data_inicial);
        // echo "</pre>";

        //die();

        //for($i = 0; $i < sizeof($aberturas); $i++){
        //  if($aberturas[$i]->id == $id){
        //     $abertura = $aberturas[$i];
        //     if($i > 0){
        //        $inicio = $aberturas[$i-1]->ultima_venda +1;
        //   }

        //   $fim = $aberturas[$i]->ultima_venda;
        //  }
        //  }

        $vendas = [];
        $somaTiposPagamento = [];


        $dadosfechamento = AberturaCaixa::where('id', 1)
            ->first();

        $dataInicial = $data_inicial;
        $dataFinal = $data_final;

        // $vendas = VendaCaixa::filtroData(
        //     $this->parseDate($dataInicial),
        //    $this->parseDate($dataFinal, true)
        // );





        $somaTiposPagamento = VendaCaixa::selectRaw('sum(valor_total) as total, tipo_pagamento')
            ->whereBetween('data_registro', [
                $this->parseDate($dataInicial),
                $this->parseDate($dataFinal, true)
            ])
            ->where('tipo_pagamento', '<>', '99')
            ->where('ativo', true)
            ->groupBy('tipo_pagamento')
            ->get();

        $somaMultFormas = VendaCaixa::select('valor_pagamento_1', 'valor_pagamento_2', 'valor_pagamento_3', 'tipo_pagamento_1', 'tipo_pagamento_2', 'tipo_pagamento_3')
            ->whereBetween('data_registro', [
                $this->parseDate($dataInicial),
                $this->parseDate($dataFinal, true)
            ])
            ->where('tipo_pagamento', '=', '99')
            ->where('ativo', true)
            ->get();


        //	 print_r($somaTiposPagamento);
        ///	 echo "</pre>";

        ///	die();

        //  $ab = AberturaCaixa::where('ultima_venda', 0)->orderBy('id', 'desc')->first();

        //  date_default_timezone_set('America/Sao_Paulo');
        // $hoje = date("Y-m-d") . " 00:00:00";

        $amanha = date('Y-m-d', strtotime('+1 days')) . " 00:00:00";
        $sangrias = SangriaCaixa::whereBetween('data_registro', [
                $this->parseDate($dataInicial),
                $this->parseDate($dataFinal, true)
            ])
            ->get();



            //  $sangrias = SangriaCaixa::
            //  whereBetween('data_registro', [ $inicio,
            //  $fim])
        ;


        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        $pathLogo = $public . 'imgs/logo.jpg';

        $cupom = new CupomFechamentoPeriodo($somaTiposPagamento, $somaMultFormas, $dadosfechamento, $sangrias, $dataInicial, $dataFinal, $pathLogo);
        $cupom->monta();
        $pdf = $cupom->render();
        // file_put_contents($public.'pdf/CUPOM_PEDIDO.pdf',$pdf);
        // return redirect($public.'pdf/CUPOM_PEDIDO.pdf');

        return response($pdf)
            ->header('Content-Type', 'application/pdf');
    }

    public function importarNumerosInutilizacao(Request $request)
    {
        $fileData = $_FILES['arquivo'] ?? null;

        if (
            empty($fileData) ||
            !isset($fileData['error']) ||
            $fileData['error'] !== UPLOAD_ERR_OK ||
            empty($fileData['tmp_name']) ||
            !is_uploaded_file($fileData['tmp_name'])
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Arquivo não enviado.',
            ], 400);
        }

        $tmpPath  = $fileData['tmp_name'];
        $origName = $fileData['name'] ?? '';
        $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $content = @file_get_contents($tmpPath);
        if ($content === false || $content === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível ler o arquivo.',
            ], 400);
        }

        $text = $this->extrairTextoArquivoInutilizacao($content, $extension, $tmpPath);
        $parsed = $this->extrairNumerosInutilizacao($text);

        $jaInutilizados = $this->verificarJaInutilizados($parsed['numbers'], $parsed['series']);

        return response()->json([
            'ok' => true,
            'numbers' => $parsed['numbers'],
            'series' => $parsed['series'],
            'already_inutilized' => $jaInutilizados,
            'count' => count($parsed['numbers']),
            'message' => count($parsed['numbers']) > 0
                ? count($parsed['numbers']) . ' número(s) encontrado(s).'
                : 'Nenhum número encontrado no arquivo. Se for PDF escaneado, copie e cole os números no campo de texto.',
        ]);
    }

    public function gravarInutilizadosNfce(Request $request)
    {
        $serie        = (int) $request->serie;
        $ano          = (int) $request->ano;
        $justificativa = (string) $request->justificativa;
        $intervalos   = $request->intervalos ?? [];

        if (!$serie || !$ano || strlen($justificativa) < 15) {
            return response()->json(['ok' => false, 'message' => 'Informe serie, ano e justificativa (minimo 15 caracteres).'], 400);
        }

        if (empty($intervalos)) {
            return response()->json(['ok' => false, 'message' => 'Nenhum intervalo informado.'], 400);
        }

        $gravados = 0;
        foreach ($intervalos as $intervalo) {
            $inicio = (int) ($intervalo['inicio'] ?? 0);
            $fim    = (int) ($intervalo['fim'] ?? 0);
            if ($inicio < 1 || $fim < $inicio) {
                continue;
            }
            NfceInutilizacao::create([
                'serie'         => $serie,
                'numero_inicio' => $inicio,
                'numero_final'  => $fim,
                'ano'           => $ano,
                'justificativa' => $justificativa,
                'status'        => 'inutilizado',
            ]);
            $gravados++;
        }

        return response()->json([
            'ok'      => true,
            'message' => $gravados . ' intervalo(s) gravado(s) como inutilizado(s).',
        ]);
    }

    private function verificarJaInutilizados(array $numbers, array $series): array
    {
        if (empty($numbers)) {
            return [];
        }

        $min = min($numbers);
        $max = max($numbers);

        $query = NfceInutilizacao::where('numero_inicio', '<=', $max)
            ->where('numero_final', '>=', $min);

        if (!empty($series)) {
            $query->whereIn('serie', $series);
        }

        $ranges = $query->get(['numero_inicio', 'numero_final']);

        if ($ranges->isEmpty()) {
            return [];
        }

        $jaInutilizados = [];
        foreach ($numbers as $number) {
            foreach ($ranges as $range) {
                if ($number >= $range->numero_inicio && $number <= $range->numero_final) {
                    $jaInutilizados[] = $number;
                    break;
                }
            }
        }

        return $jaInutilizados;
    }

    private function extrairTextoArquivoInutilizacao(string $content, string $extension, string $path = ''): string
    {
        if ($extension !== 'pdf') {
            return $content;
        }

        $pdftotext = $this->extrairPdfComPdftotext($path);
        if ($pdftotext !== '') {
            return $pdftotext;
        }

        $text = $content;

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress(trim($stream));
                if ($decoded === false) {
                    $decoded = @gzdecode(trim($stream));
                }
                if ($decoded !== false) {
                    $text .= "\n" . $decoded;
                }
            }
        }

        $text = preg_replace('/\\\\([0-7]{3})/', ' ', $text);
        $text = preg_replace('/[^0-9A-Za-zÀ-ÿ\.\,\;\:\-\s\/]/u', ' ', $text);

        return (string) $text;
    }

    private function extrairPdfComPdftotext(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $candidates = [
            'C:\\laragon\\bin\\git\\mingw64\\bin\\pdftotext.exe',
            'pdftotext',
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'nfce_pdf_');
        if ($tmp === false) {
            return '';
        }

        foreach ($candidates as $binary) {
            @unlink($tmp);

            $command = '"' . $binary . '" -layout -enc UTF-8 '
                . escapeshellarg($path) . ' '
                . escapeshellarg((string) $tmp);

            $output = [];
            $exitCode = 1;
            @exec($command, $output, $exitCode);

            if ($exitCode === 0 && is_file($tmp)) {
                $text = @file_get_contents($tmp);
                if (is_string($text) && trim($text) !== '') {
                    @unlink($tmp);
                    return $text;
                }
            }
        }

        @unlink($tmp);

        return '';
    }

    private function extrairNumerosInutilizacao(string $text): array
    {
        $numbers = [];
        $series = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (preg_match('/^\s*(\d{1,9})\s+(\d{1,9})\s+(\d{1,4})\s+nfc/i', $line, $match)) {
                $start = (int) $match[1];
                $end = (int) $match[2];
                $serie = (int) $match[3];

                if ($start > 0 && $end >= $start && ($end - $start) <= 10000) {
                    for ($number = $start; $number <= $end; $number++) {
                        $numbers[$number] = $number;
                    }
                    $series[$serie] = $serie;
                }
            }
        }

        if (!empty($numbers)) {
            sort($numbers, SORT_NUMERIC);
            sort($series, SORT_NUMERIC);

            return [
                'numbers' => array_values($numbers),
                'series' => array_values($series),
            ];
        }

        if (stripos($text, '%PDF-') !== false) {
            return [
                'numbers' => [],
                'series' => [],
            ];
        }

        preg_match_all('/\b\d{1,9}\b/', $text, $matches);

        foreach ($matches[0] ?? [] as $number) {
            $value = (int) $number;
            if ($value < 1 || $value > 999999999) {
                continue;
            }

            $numbers[$value] = $value;
        }

        sort($numbers, SORT_NUMERIC);

        return [
            'numbers' => array_values($numbers),
            'series' => [],
        ];
    }
}
