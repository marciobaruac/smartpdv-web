@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">


    <div class="card-body">

        <div class="" id="kt_user_profile_aside" style="margin-left: 10px; margin-right: 10px;">

            <input type="hidden" id="_token" value="{{ csrf_token() }}">
            <form method="get" action="/tef/filtro">
                <div class="row align-items-center">

                    <div class="form-group col-lg-3 col-md-4 col-sm-6">
                        <label class="col-form-label">Data Inicial</label>
                        <div class="">
                            <div class="input-group date">
                                <input type="text" name="data_inicial" class="form-control" readonly value="{{{isset($dataInicial) ? $dataInicial : ''}}}" id="kt_datepicker_3" />
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group col-lg-3 col-md-4 col-sm-6">
                        <label class="col-form-label">Data Final</label>
                        <div class="">
                            <div class="input-group date">
                                <input type="text" name="data_final" class="form-control" readonly value="{{{isset($dataFinal) ? $dataFinal : ''}}}" id="kt_datepicker_3" />
                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
                        <button style="margin-top: 15px;" class="btn btn-light-primary px-6 font-weight-bold">Pesquisa</button>
                    </div>
                </div>
            </form>
            <br>
            <h4>Lista de TEFs de Frente de Caixa</h4>

            <div class="row">
                <div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">

                    <div class="wizard wizard-3" id="kt_wizard_v3" data-wizard-state="between" data-wizard-clickable="true">
                        <!--begin: Wizard Nav-->

                        <div class="wizard-nav">
                            <p class="text-danger" style="margin-top: 10px;">{{$info}}</p>

                            <div class="wizard-steps px-8 py-8 px-lg-15 py-lg-3">
                                <!--begin::Wizard Step 1 Nav-->
                                <div class="wizard-step" data-wizard-type="step" data-wizard-state="done">
                                    <div class="wizard-label">
                                        <h3 class="wizard-title">
                                            <span>
                                                <i style="font-size: 40px" class="la la-table"></i>
                                                Tabela
                                            </span>
                                        </h3>
                                        <div class="wizard-bar"></div>
                                    </div>
                                </div>

                            </div>
                        </div>


                        <div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">

                            <!--begin: Wizard Form-->
                            <form class="form fv-plugins-bootstrap fv-plugins-framework" id="kt_form">
                                <!--begin: Wizard Step 1-->
                                <div class="pb-5" data-wizard-type="step-content">

                                    <!-- Inicio da tabela -->

                                    <div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
                                        <div class="row">
                                            <div class="col-xl-12">

                                                <div id="kt_datatable" class="datatable datatable-bordered datatable-head-custom datatable-default datatable-primary datatable-loaded">

                                                    <table class="datatable-table" style="max-width: 100%; overflow: scroll">
                                                        <thead class="datatable-head">
                                                            <tr class="datatable-row" style="left: 0px;">
                                                                <th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 70px;">#</span></th>
                                                                <th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Data</span></th>
                                                                <th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Intenção Venda</span></th>
                                                                <th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Venda</span></th>
                                                                <th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Terminal</span></th>
                                                                <th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Tipo de pagamento</span></th>
                                                                <th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Valor</span></th>
                                                                <th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Situação</span></th>
                                                                <th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 220px;">Ações</span></th>
                                                            </tr>
                                                        </thead>

                                                        <tbody class="datatable-body">

                                                            @foreach($tefs as $tef)

                                                            <tr class="datatable-row">
                                                                <td class="datatable-cell"><span class="codigo" style="width: 70px;">{{$tef->id}}</span></td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ \Carbon\Carbon::parse($tef->created_at)->format('d/m/Y H:i:s')}}</span></td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $tef->intencao_venda_id }}</span></td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $tef->venda_caixa_id ?: '-' }}</span></td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $tef->terminal_id }}</span></td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">

                                                                        @if($tef->forma_pagamento_tef == 21)
                                                                        <span class="codigo" style="width: 100px;">CRÉDITO</span>
                                                                        @elseif($tef->forma_pagamento_tef == 22)
                                                                        <span class="codigo" style="width: 100px;">DÉBITO</span>
                                                                        @else
                                                                        <span class="codigo" style="width: 100px;">PIX</span>
                                                                        @endif

                                                                    </span>
                                                                </td>
                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ number_format($tef->valor, 2, ',', '.') }}</span></td>

                                                                <td class="datatable-cell"><span class="codigo" style="width: 100px;">

                                                                        @if($tef->situacao == 0)
                                                                        <span class="codigo" style="width: 100px;">PENDENTE</span>
                                                                        @elseif($tef->situacao == 1)
                                                                        <span class="codigo" style="width: 100px;">APROVADA</span>
                                                                        @elseif($tef->situacao == 2)
                                                                        <span class="codigo" style="width: 100px;">EXPIRADA</span>
                                                                        @elseif($tef->situacao == 3)
                                                                        <span class="codigo" style="width: 100px;">EM PROCESSO DE CANCELAMENTO</span>
                                                                        @elseif($tef->situacao == 4)
                                                                        <span class="codigo" style="width: 100px;">SOLICITAÇÂO DE CANCELAMENTO</span>
                                                                        @elseif($tef->situacao == 5)
                                                                        <span class="codigo" style="width: 100px;">CANCELADA</span>
                                                                        @elseif($tef->situacao == 6)
                                                                        <span class="codigo" style="width: 100px;">NÃO APROVADA</span>
                                                                        @endif

                                                                    </span>
                                                                </td>

                                                                <td class="datatable-cell">
                                                                    <span class="codigo" style="width: 220px;">

                                                                        <a id="btn_consulta_imprimir_{{$tef->intencao_venda_id}}" title="IMPRIMIR SEGUNDA VIA" onclick="imprimirTEF('{{$tef->intencao_venda_id}}')" href="#!" class="btn btn-warning spinner-white spinner-right">
                                                                            <i class="fa fa-print"></i>
                                                                        </a>

                                                                        <a id="btn_consulta_{{$tef->intencao_venda_id}}" title="CANCELAR TEF" onclick="cancelarTEF('{{$tef->intencao_venda_id}}')" href="#!" class="btn btn-danger spinner-white spinner-right">
                                                                            <i class="fa fa-ban"></i>
                                                                        </a>

                                                                    </span>

                                                                </td>
                                                            </tr>

                                                            @endforeach

                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Fim da tabela -->
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered d-flex justify-content-center" role="document">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>

    <script>
        function imprimirTEF(id) {
            $('#btn_consulta_imprimir_' + id).addClass('spinner')

            let token = $('#_token').val();

            $.ajax({
                url: path + 'tef/postImpressaoSegundaVia',
                type: 'POST',
                async: false,
                data: {
                    INTENCAO_VENDA_ID: id,
                    _token: token
                },
                success: function(json) {

                    var data = $.parseJSON(json);

                    $('#btn_consulta_imprimir_' + id).removeClass('spinner');

                    var stringParcelas = '';

                    if (data.forma_pagamento_tef == 21) {
                        stringParcelas = '<span><strong>Parcelas:</strong> ' + data.parcelas + ' x</span><br>';
                    }

                    var html = '<div style="font-family: Arial, sans-serif; font-size: 12px; max-width: 300px; margin: 0 auto; border: 1px solid #000; padding: 10px; text-align: center;">' +
                            '<div style="font-weight: bold; margin-bottom: 10px;">COMPROVANTE DE TRANSAÇÃO TEF</div>' +

                            '<div style="border-bottom: 1px dashed #000; margin: 10px 0;"></div>' +

                            '<div style="margin-bottom: 10px;">' +
                                '<span><strong>Operadora:</strong> ' + data.adquirente + '</span><br>' +
                                '<span><strong>Bandeira:</strong> ' + data.bandeira + '</span><br>' +
                                '<span><strong>Titular:</strong> ' + data.nome + '</span><br>' +
                                '<span><strong>Autorização:</strong> ' + data.codigo_autorizacao + '</span><br>' +
                                '<span><strong>NSU:</strong> ' + data.nsu + '</span>' +
                            '</div>' +

                            '<div style="border-bottom: 1px dashed #000; margin: 10px 0;"></div>' +

                            '<div style="margin-bottom: 10px;">' +
                                '<span><strong>Valor da Compra:</strong> R$ ' + data.valor + '</span>' +
                                stringParcelas +
                            '</div>' +

                            '<div style="border-bottom: 1px dashed #000; margin: 10px 0;"></div>' +

                            '<div style="font-size: 10px; margin-top: 20px;">' +
                                'Agradecemos a sua preferência!<br>' +
                            '</div>' +
                        '</div>';


                    printJS({
                        printable: html,
                        type: 'raw-html'
                    });
                }
            });
        }

        function cancelarTEF(id) {
            $('#btn_consulta_' + id).addClass('spinner')
            $('#btn_consulta_grid_' + id).addClass('spinner');

            let token = $('#_token').val();

            $.ajax({
                url: path + 'tef/postCancelarVenda',
                type: 'POST',
                async: false,
                data: {
                    INTENCAO_VENDA_ID: id,
                    _token: token
                },
                success: function(json) {

                    swal('Cancelamento de TEF solicitado');

                    checkCancelamentoTEF(id, 10, 0);
                }
            });
        }

        function checkCancelamentoTEF(intencaoVendaId, inicio, flag) {

            if (flag < 20) {

                setTimeout(function() {

                    let token = $('#_token').val();

                    $.ajax({
                        url: path + 'tef/getIntencaoVendaTEFCancelamento',
                        type: 'POST',
                        async: false,
                        data: {
                            INTENCAO_VENDA_ID: intencaoVendaId,
                            _token: token
                        },
                        success: function(json) {
                            var data = $.parseJSON(json);

                            if (typeof data.intencoesVendas != 'undefined') {
                                var detIntencaoVenda = data.intencoesVendas[0];

                                if (detIntencaoVenda.intencaoVendaStatus.id == 10) {
                                    checkCancelamentoTEF(detIntencaoVenda.id, 3, (flag + 1));
                                } else if (detIntencaoVenda.intencaoVendaStatus.id == 18) {
                                    checkCancelamentoTEF(detIntencaoVenda.id, 3, (flag + 1));
                                } else if (detIntencaoVenda.intencaoVendaStatus.id == 19) {
                                    checkCancelamentoTEF(detIntencaoVenda.id, 3, (flag + 1));
                                } else if (detIntencaoVenda.intencaoVendaStatus.id == 20) {

                                    swal({
                                        title: "Sucesso",
                                        text: "Cancelamento concluído para essa transação",
                                        icon: "success", // "type" foi alterado para "icon"
                                        buttons: {
                                            confirm: {
                                                text: "Ok", // Alterado para "Ok" para corresponder ao contexto de sucesso
                                                value: true,
                                                visible: true,
                                                className: "btn-primary",
                                                closeModal: true
                                            }
                                        }
                                    }).then((willConfirm) => {
                                        if (willConfirm) {
                                            location.reload();
                                        }
                                    });
                                }

                            } else {
                                checkCancelamentoTEF(detIntencaoVenda.id, 3, (flag + 1));
                            }
                        }
                    });
                }, 1000 * inicio);

            }
        }
    </script>



    @endsection
