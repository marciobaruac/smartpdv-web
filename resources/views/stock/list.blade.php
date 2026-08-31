@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">

    <div class="card-body">

        <div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
            <div class="card card-custom gutter-b example example-compact">
                <div class="card-header">

                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-xl-12">

                                <div id="kt_datatable" class="datatable datatable-bordered datatable-head-custom datatable-default datatable-primary datatable-loaded">
                                    <br>

                                    <h4>Estoque</h4>

                                    {{-- CAMPO DE BUSCA NA PRÓPRIA TABELA --}}
                                    <div class="form-group row">
                                        <div class="col-md-4">
                                            <input type="text"
                                                   id="filtroEstoque"
                                                   class="form-control"
                                                   placeholder="Filtrar por produto, categoria, unidade...">
                                        </div>
                                    </div>

                                    <table id="tabela-estoque" class="datatable-table" style="max-width: 100%; overflow: scroll">
                                        <thead class="datatable-head">
                                            <tr class="datatable-row" style="left: 0px;">

                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 150px;">Produto</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Categoria</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Quantidade</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Un. Compra</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Un. Venda</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Valor de Compra</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 80px;">Subtotal</span>
                                                </th>
                                                <th class="datatable-cell datatable-cell-sort">
                                                    <span style="width: 160px;">Ações</span>
                                                </th>

                                            </tr>
                                        </thead>
                                        <tbody class="datatable-body">
                                            @php $subtotal = 0; @endphp

                                            @foreach($estoque as $e)
                                                @php
                                                    $total = $e->quantidade * $e->valor_compra;
                                                    $subtotal += $total;
                                                @endphp

                                                <tr class="datatable-row" style="left: 0px;">
                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 150px;">
                                                            {{ $e->produto->nome }} | {{ $e->produto->cor }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ $e->produto->categoria->nome ?? 'Valor Padrão' }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ number_format($e->quantidade, 2, ',', '.') }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ $e->produto->unidade_compra }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ $e->produto->unidade_venda }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ number_format($e->valor_compra, 2, ',', '.') }}
                                                            {{ $e->produto->unidade_compra }}
                                                        </span>
                                                    </td>

                                                    <td class="datatable-cell">
                                                        <span class="codigo" style="width: 80px;">
                                                            {{ number_format($total, 2, ',', '.') }}
                                                        </span>
                                                    </td>

                                                    {{-- AÇÕES --}}
                                                    <td class="datatable-cell">
                                                        <span class="codigo"
                                                              style="width: 160px; display: inline-flex; gap: 4px; flex-wrap: wrap;">

                                                            {{-- HISTÓRICO --}}
                                                            <a target="_blank"
                                                               href="/estoque/listApontamentos/{{ $e->produto->id }}"
                                                               class="btn btn-icon btn-xs btn-light-danger"
                                                               title="Histórico de Apontamentos">
                                                                <i class="la la-history"></i>
                                                            </a>

                                                            {{-- APONTAR ESTOQUE (MODAL) --}}
                                                            <button type="button"
                                                                    class="btn btn-icon btn-xs btn-success btn-apontar-estoque"
                                                                    title="Apontar Estoque"
                                                                    data-produto-id="{{ $e->produto->id }}"
                                                                    data-produto-nome="{{ $e->produto->nome }}@if($e->produto->cor) - {{ $e->produto->cor }}@endif">
                                                                <i class="la la-balance-scale"></i>
                                                            </button>

                                                        </span>
                                                    </td>

                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex flex-wrap py-2 mr-3">
                                @if(isset($links))
                                    {{ $estoque->links() }}
                                @endif
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
                                    <div class="card card-custom gutter-b example example-compact">
                                        <div class="card-header">
                                            <div class="card-body">
                                                <h3 class="card-title">
                                                    Total em Estoque: R$
                                                    <strong class="red-text">{{ number_format($subtotal, 2, ',', '.') }}</strong>
                                                </h3>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- MODAL APONTAMENTO DE ESTOQUE --}}
<div class="modal fade" id="modalApontamentoEstoque" tabindex="-1" role="dialog"
     aria-labelledby="tituloModalApontamento" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <form method="POST" action="/estoque/saveApontamentoManual" id="formApontamentoModal">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalApontamento">
                        Apontar Estoque
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="produto" id="produto_id_modal">

                    <div class="form-group">
                        <label>Produto</label>
                        <input type="text" id="produto_nome_modal" class="form-control" readonly>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Quantidade</label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   class="form-control"
                                   name="quantidade"
                                   id="quantidade_modal"
                                   required>
                        </div>

                        <div class="form-group col-md-5">
                            <label>Tipo</label>
                            <select class="form-control" name="tipo" id="tipo_modal" required>
                                <option value="saldo">Contagem (Saldo final)</option>
                                <option value="incremento">Incremento (+)</option>
                                <option value="decremento">Decremento (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observação</label>
                        <input type="text"
                               class="form-control"
                               name="observacao"
                               id="observacao_modal"
                               placeholder="Ex: Contagem geral, ajuste inventário...">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light-danger" data-dismiss="modal">
                        <i class="la la-close"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success" id="btnSalvarApontamentoModal">
                        <i class="la la-check"></i> Salvar Apontamento
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

{{-- SCRIPTS: BUSCA + MODAL --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // FILTRO DA TABELA
        const inputFiltro = document.getElementById('filtroEstoque');
        const tabela = document.getElementById('tabela-estoque');

        if (inputFiltro && tabela) {
            inputFiltro.addEventListener('keyup', function () {
                const filtro = this.value.toLowerCase();
                const linhas = tabela.querySelectorAll('tbody tr');

                linhas.forEach(function (linha) {
                    const textoLinha = linha.innerText.toLowerCase();
                    linha.style.display = textoLinha.indexOf(filtro) > -1 ? '' : 'none';
                });
            });
        }

        // BOTÃO "APONTAR ESTOQUE" -> ABRE MODAL
        $(document).on('click', '.btn-apontar-estoque', function () {
            const produtoId   = $(this).data('produto-id');
            const produtoNome = $(this).data('produto-nome');

            $('#produto_id_modal').val(produtoId);
            $('#produto_nome_modal').val(produtoNome);
            $('#quantidade_modal').val('');
            $('#tipo_modal').val('saldo'); // padrão
            $('#observacao_modal').val('');

            $('#modalApontamentoEstoque').modal('show');
        });

        $('#modalApontamentoEstoque').on('shown.bs.modal', function () {
            const btnSalvar = document.getElementById('btnSalvarApontamentoModal');
            if (btnSalvar) {
                setTimeout(function () {
                    btnSalvar.focus();
                }, 50);
            }
        });


        // Evita duplo envio do apontamento manual.
        const formApontamentoModal = document.getElementById('formApontamentoModal');
        if (formApontamentoModal) {
            formApontamentoModal.addEventListener('submit', function () {
                const botaoSalvar = formApontamentoModal.querySelector('button[type="submit"]');
                if (botaoSalvar) {
                    botaoSalvar.disabled = true;
                    botaoSalvar.innerHTML = '<i class="la la-spinner spinner"></i> Salvando...';
                }
            });
        }
    });
</script>

@endsection

