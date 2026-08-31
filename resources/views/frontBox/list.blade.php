@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">


	<div class="card-body">

		<div class="" id="kt_user_profile_aside" style="margin-left: 10px; margin-right: 10px;">

			<input type="hidden" id="_token" value="{{ csrf_token() }}">
			<form method="get" action="/frenteCaixa/filtro">
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

					<div class="form-group col-lg-3 col-md-4 col-sm-6">
						<label class="col-form-label">Status NFC-e</label>
						<select name="estado_nfce" class="form-control">
							<option value="todos" {{ (isset($estadoNfce) ? $estadoNfce : 'todos') == 'todos' ? 'selected' : '' }}>Todos</option>
							<option value="rejeitado_pendente" {{ (isset($estadoNfce) ? $estadoNfce : '') == 'rejeitado_pendente' ? 'selected' : '' }}>Rejeitado + Pendente</option>
							<option value="rejeitado" {{ (isset($estadoNfce) ? $estadoNfce : '') == 'rejeitado' ? 'selected' : '' }}>Rejeitado</option>
							<option value="pendente" {{ (isset($estadoNfce) ? $estadoNfce : '') == 'pendente' ? 'selected' : '' }}>Pendente</option>
							<option value="aprovado" {{ (isset($estadoNfce) ? $estadoNfce : '') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
							<option value="disponivel" {{ (isset($estadoNfce) ? $estadoNfce : '') == 'disponivel' ? 'selected' : '' }}>Disponivel</option>
						</select>
					</div>


					<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
						<button style="margin-top: 15px;" class="btn btn-light-primary px-6 font-weight-bold">Pesquisa</button>
					</div>
				</div>
			</form>
			<br>
			<h4>Lista de Vendas de Frente de Caixa</h4>

			<div class="row">
				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%" href="/frenteCaixa" class="btn btn-light-primary">
						<i class="la la-box"></i>
						FRENTE DE CAIXA
					</a>
				</div>
				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%" href="#!" onclick="modalWhatsApp()" class="btn btn-light-success">
						<i class="la la-whatsapp"></i>
						ENVIAR WHATSAPP
					</a>
				</div>
				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%" href="/relatorios/filtroVendaDiaria?data_inicial={{date('d-m-Y')}}&total_resultados=" class="btn btn-light-danger">
						<i class="la la-file"></i>
						BAIXAR RELATÓRIO
					</a>
				</div>

				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%" data-toggle="modal" data-target="#modal-somas" class="btn btn-light-info">
						<i class="las la-calendar-plus"></i>
						SOMA DETALHADA

					</a>
				</div>


				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%; margin-top: 10px;" href="/frenteCaixa/fechamentos" class="btn btn-light-warning">
						<i class="las la-file"></i>
						CAIXAS FECHADOS

					</a>
				</div>
                <div class="col-lg-3 col-xl-3">
                    <a style="width: 100%; margin-top: 10px; background-color: #E6E6FA; color: #333;" href="/frenteCaixa/relatorioFiltro/{{$data1}}/{{$data2}}" class="btn">
                        <i class="las la-calculator"></i>
                        POR FORMA DE PAGAMENTO
                    </a>
                </div>
				<div class="col-lg-3 col-xl-3">
					<a style="width: 100%; margin-top: 10px;" href="#!" data-toggle="modal" data-target="#modal-inutilizar-nfce-pdv" class="btn btn-light-danger">
						<i class="las la-ban"></i>
						INUTILIZAR NFC-E
					</a>
				</div>


			</div>

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
								<!--end::Wizard Step 1 Nav-->
								<!--begin::Wizard Step 2 Nav-->
								<div class="wizard-step" data-wizard-type="step" data-wizard-state="current">
									<div class="wizard-label" id="grade">
										<h3 class="wizard-title">
											<span>
												<i style="font-size: 40px" class="la la-tablet"></i>
												Grade
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
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Cliente</span></th>
																<th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Data</span></th>
																<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Tipo de pagamento</span></th>

																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Estado</span></th>

																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">NFCe</span></th>
																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Usuário</span></th>
																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 100px;">Valor</span></th>

																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 220px;">Ações</span></th>
															</tr>
														</thead>

														<tbody class="datatable-body">

															@foreach($vendas as $v)

															<tr class="datatable-row" style="left: 0px; @if($v->estado == 'REJEITADO') background: #ffcdd2; @elseif($v->estado == 'APROVADO') background: #a7ffeb; @endif">
																<td class="datatable-cell"><span class="codigo" style="width: 70px;">{{$v->id}}</span>
																</td>
																<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $v->cliente->razao_social ?? 'NAO IDENTIFCADO' }}</span>
																</td>
																<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ \Carbon\Carbon::parse($v->created_at)->format('d/m/Y H:i:s')}}</span>
																</td>
																<td class="datatable-cell"><span class="codigo" style="width: 100px;">

																	@if($v->tipo_pagamento == '99')

																	<a href="#!" onclick='swal("", "{{$v->multiplo()}}", "info")' class="btn btn-light-info">
																		Ver
																	</a>
																	@else
																	{{$v->getTipoPagamento($v->tipo_pagamento)}}
																	@endif

																</span>
															</td>
															<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $v->estado }}</span>
															</td>
															<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $v->NFcNumero > 0 ? $v->NFcNumero : '--' }}</span>
															</td>
															<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ $v->usuario->nome }}</span>
															</td>
															<td class="datatable-cell"><span class="codigo" style="width: 100px;">{{ number_format($v->valor_total, 2, ',', '.') }}</span>
															</td>
															<td class="datatable-cell">
																<span class="codigo" style="width: 220px;">

																	@if($v->NFcNumero && $v->estado == 'APROVADO')

																	<a title="CUPOM FISCAL" target="_blank" href="/nfce/imprimir/{{$v->id}}" class="btn btn-success">
																		<i class="la la-print"></i>
																	</a>

																	<a id="btn_consulta_{{$v->id}}" title="CONSULTAR NFCE" onclick="consultarNFCe('{{$v->id}}')" href="#!" class="btn btn-warning spinner-white spinner-right">
																		<i class="la la-check"></i>
																	</a>

																	<a title="BAIXAR XML" target="_blank" href="/nfce/baixarXml/{{$v->id}}" class="btn btn-danger">
																		<i class="la la-download"></i>
																	</a>

																	@endif

																	<a title="CUPOM NÃO FISCAL" target="_blank" href="/nfce/imprimirNaoFiscal/{{$v->id}}" class="btn btn-primary">
																		<i class="la la-print"></i>
																	</a>

																	@if($v->estado != 'APROVADO')
																	<a title="GERAR NFCE" id="btn_envia_{{$v->id}}" class="btn btn-warning spinner-white spinner-right" onclick='swal("Atenção!", "Deseja enviar esta venda para Sefaz?", "warning").then((sim) => {if(sim){ emitirNFCe({{$v->id}}) }else{return false} })' href="#!">
																		<i class="las la-file-invoice"></i>
																	</a>
																	@endif

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

							<!--end: Wizard Step 1-->
							<!--begin: Wizard Step 2-->
							<div class="pb-5" data-wizard-type="step-content">

								<!-- Inicio do card -->

								<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
									<div class="row">

										@foreach($vendas as $v)
										<div class="col-sm-6 col-lg-6 col-md-6 col-xl-6">

											<div class="card card-custom gutter-b example example-compact">
												<div class="card-header">
													<div class="card-title">
														<h3 style="width: 230px; font-size: 15px; height: 10px;" class="card-title">
															<strong class="text-success">R$ {{ number_format($v->valor_total, 2, ',', '.') }} </strong> - {{ \Carbon\Carbon::parse($v->created_at)->format('d/m/Y H:i:s')}}

														</h3>

													</div>
												</div>

												<div class="card-body">

													<div class="kt-widget__info">
														<span class="kt-widget__label">ID:</span>
														<a target="_blank" class="kt-widget__data text-success">
															{{ $v->id }}
														</a>
													</div>

													<div class="kt-widget__info">
														<span class="kt-widget__label">Cliente:</span>
														<a target="_blank" class="kt-widget__data text-success">
															{{ $v->cliente->razao_social ?? 'NAO IDENTIFCADO' }}
														</a>
													</div>

													<div class="kt-widget__info">
														<span class="kt-widget__label">NFCe:</span>
														<a target="_blank" class="kt-widget__data text-success">
															{{ $v->NFcNumero > 0 ? $v->NFcNumero : '--' }}
														</a>
													</div>

													<div class="kt-widget__info">
														<span class="kt-widget__label">Estado:</span>
														<a target="_blank" class="kt-widget__data text-success">
															{{ $v->estado }}
														</a>
													</div>

													<div class="kt-widget__info">
														<span class="kt-widget__label">Usuário:</span>
														<a target="_blank" class="kt-widget__data text-success">
															{{ $v->usuario->nome }}
														</a>
													</div>

													<div class="kt-widget__info">
														<span class="kt-widget__label">Tipo de pagamento:</span>
														<span class="codigo" style="width: 100px;">

															@if($v->tipo_pagamento == '99')

															<a href="#!" onclick='swal("", "{{$v->multiplo()}}", "info")' class="btn btn-light-info">
																Ver
															</a>
															@else
															<span class="label label-xl label-inline label-light-success">
																{{$v->getTipoPagamento($v->tipo_pagamento)}}
															</span>
															@endif

														</span>
													</div>

													<hr>

													<div class="row">

														<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">

															<a style="width: 100%; margin-top: 5px;" target="_blank" href="/nfce/imprimirNaoFiscal/{{$v->id}}" class="btn btn-primary">
																<i class="la la-print"></i>
																Imprimir não fiscal
															</a>
														</div>


														@if($v->NFcNumero && $v->estado == 'APROVADO')

														<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">
															<a style="width: 100%; margin-top: 5px;" target="_blank" href="/nfce/imprimir/{{$v->id}}" class="btn btn-success">
																<i class="la la-print"></i>
																Imprimir fiscal
															</a>
														</div>

														<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">
															<a id="btn_consulta_grid_{{$v->id}}" style="width: 100%; margin-top: 5px;" href="#!" onclick="consultarNFCe('{{$v->id}}')" class="btn btn-warning spinner-white spinner-right">
																<i class="la la-check"></i>
																Consultar NFCe
															</a>
														</div>

														<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">
															<a style="width: 100%; margin-top: 5px;" target="_blank" href="/nfce/baixarXml/{{$v->id}}" class="btn btn-danger">
																<i class="la la-download"></i>
																Baixar XML
															</a>
														</div>



														@endif

														@if(!$v->NFcNumero)

														<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">
															<a style="width: 100%; margin-top: 5px;" id="btn_envia_grid_{{$v->id}}" onclick='swal("Atenção!", "Deseja enviar esta venda para Sefaz?", "warning").then((sim) => {if(sim){ emitirNFCe({{$v->id}}) }else{return false} })' href="#!" class="btn btn-warning spinner-white spinner-right">
																<i class="la la-file-invoice"></i>
																Transmitir NFCe
															</a>
														</div>
														@endif


														@if($v->estado == 'APROVADO')

															<div class="col-sm-12 col-lg-12 col-md-12 col-xl-6">
															<a style="width: 100%; margin-top: 5px;" id="btn_envia_grid_{{$v->id}}" onclick='swal("Atenção!", "Deseja enviar esta venda para Sefaz?", "warning").then((sim) => {if(sim){ emitirNFCe({{$v->id}}) }else{return false} })' href="#!" class="btn btn-warning spinner-white spinner-right">
															<i class="la la-file-invoice"></i>
																Transmitir NFCe
															</a>
															</div>
														@endif


													</div>
												</div>
											</div>



										</div>
										@endforeach

									</div>
								</div>
							</div>
							<!--end: Wizard Step 2-->



						</form>

					</div>
				</div>
			</div>
		</div>

	</div>

</div>

<div class="modal fade" id="modal-inutilizar-nfce-pdv" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">INUTILIZAR NUMERO DE NFC-E</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					x
				</button>
			</div>

			<div class="modal-body">
				<div class="row">
					<div class="form-group validated col-sm-3 col-lg-3 col-12">
						<label class="col-form-label">Serie</label>
						<input type="text" id="pdv_serie_nfce" class="form-control" value="">
					</div>
					<div class="form-group validated col-sm-3 col-lg-3 col-12">
						<label class="col-form-label">Ano</label>
						<input type="text" id="pdv_ano_nfce" class="form-control" value="{{date('Y')}}">
					</div>
					<div id="pdv_col_nInicio_nfce" class="form-group validated col-sm-3 col-lg-3 col-12">
						<label class="col-form-label">Numero NFC-e Inicial</label>
						<input type="text" id="pdv_nInicio_nfce" class="form-control" value="">
					</div>
					<div id="pdv_col_nFinal_nfce" class="form-group validated col-sm-3 col-lg-3 col-12">
						<label class="col-form-label">Numero NFC-e Final</label>
						<input type="text" id="pdv_nFinal_nfce" class="form-control" value="">
					</div>
					<div class="form-group validated col-sm-12 col-lg-12 col-12">
						<label class="col-form-label">Justificativa</label>
						<input type="text" id="pdv_justificativa_inut_nfce" class="form-control" value="">
					</div>
					<div class="form-group validated col-sm-12 col-lg-12 col-12">
						<label class="col-form-label">Importar numeros para conferir</label>
						<input type="file" id="pdv_arquivo_inut_nfce" class="form-control" accept=".txt,.csv,.pdf">
						<small class="text-muted" id="pdv_info_arquivo_inut_nfce">Aceita TXT/CSV e tenta ler PDF simples. Se preferir, cole os numeros abaixo.</small>
					</div>
					<div class="form-group col-sm-12 col-lg-12 col-12">
						<button type="button" id="btn-processar-arquivo-inut-nfce" class="btn btn-light-success btn-sm spinner-white spinner-right" onclick="processarArquivoInutilizacaoPdv()">
							Processar arquivo e extrair numeracao
						</button>
					</div>
					<div class="form-group validated col-sm-12 col-lg-12 col-12">
						<label class="col-form-label">Numeros importados ou colados</label>
						<textarea id="pdv_texto_inut_nfce" class="form-control" rows="3" placeholder="Cole aqui os numeros das NFC-e, um por linha ou separados por virgula"></textarea>
					</div>
					<div class="form-group col-sm-12 col-lg-12 col-12">
						<button type="button" class="btn btn-light-primary btn-sm" onclick="carregarNumerosInutilizacaoPdv()">Carregar para Conferir</button>
						<button type="button" class="btn btn-light-info btn-sm" onclick="marcarTodosInutilizacaoPdv(true)">Selecionar todos</button>
						<button type="button" class="btn btn-light-warning btn-sm" onclick="marcarTodosInutilizacaoPdv(false)">Desmarcar todos</button>
					</div>
					<div class="form-group col-sm-12 col-lg-12 col-12" id="pdv_box_numeros_inut_nfce" style="display: none;">
						<label class="col-form-label">Confira e selecione os numeros que serao inutilizados</label>
						<div id="pdv_resumo_numeros_inut_nfce" class="text-primary" style="margin-bottom: 8px;"></div>
						<div style="max-height: 260px; overflow-y: auto; border: 1px solid #e4e6ef; border-radius: 4px;">
							<table class="table table-sm table-hover mb-0">
								<thead>
									<tr>
										<th style="width: 60px;">
											<label class="checkbox checkbox-outline checkbox-primary mb-0">
												<input type="checkbox" id="pdv_check_todos_inut_nfce" checked onchange="marcarTodosInutilizacaoPdv(this.checked)">
												<span></span>
											</label>
										</th>
										<th style="width: 90px;">#</th>
										<th>Numero NFC-e</th>
										<th style="width: 160px;">Status</th>
									</tr>
								</thead>
								<tbody id="pdv_lista_numeros_inut_nfce"></tbody>
							</table>
						</div>
					</div>
					<div class="form-group col-sm-12 col-lg-12 col-12" id="pdv_resultado_inut_nfce" style="display: none;">
						<label class="col-form-label">Resultado da inutilizacao</label>
						<div class="alert alert-light" id="pdv_resultado_inut_nfce_texto"></div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">Fechar</button>
				<button type="button" id="btn-gravar-inutilizados-nfce-pdv" onclick="gravarInutilizadosNfcePdv()" class="btn btn-light-warning font-weight-bold spinner-white spinner-right" title="Grava os numeros como ja inutilizados sem enviar ao SEFAZ">Gravar como Ja Inutilizado</button>
				<button type="button" id="btn-inutilizar-nfce-pdv" onclick="inutilizarNfcePdv()" class="btn btn-light-success font-weight-bold spinner-white spinner-right">Inutilizar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-whatsApp" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					x
				</button>
			</div>

			<div class="modal-body">
				<div class="row">
					<div class="form-group validated col-sm-6 col-lg-6 col-12">
						<label class="col-form-label" id="">WhatsApp</label>
						<input type="text" id="celular" name="celular" class="form-control" value="">
					</div>
				</div>
				<div class="row">
					<div class="form-group validated col-sm-12 col-lg-12 col-12">
						<label class="col-form-label" id="">Texto</label>
						<input type="text" id="texto" name="texto" class="form-control" value="">
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">Fechar</button>
				<button id="btn-cpf" type="button" class="btn btn-success font-weight-bold" onclick="enviarWhatsApp()">Enviar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-somas" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					x
				</button>
			</div>

			<div class="modal-body">
				@foreach($somaTiposPagamento as $key => $s)
				@if($s > 0)
				<h4 class="center-align">{{App\Models\VendaCaixa::getTipoPagamento($key)}} = <strong class="red-text">R$ {{number_format($s, 2)}}</strong></h4>
				@endif
				@endforeach
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light-danger font-weight-bold" data-dismiss="modal">Fechar</button>
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


    <div class="modal fade" id="sucessoModal" tabindex="-1" role="dialog" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered d-flex justify-content-center" role="document">
            <div class="modal-content">
                <div class="modal-header" style="padding: 0.5rem 1.75rem">
                    <h5 class="modal-title  w-100 text-center" >Informação</h5>
                </div>
                <div class="modal-body" style="padding: 0.75rem;">
                    <p>
                        <h6 class="modal-title">Reprocessamento concluído com Sucesso.</h6>
                    </p>
                </div>
                <div class="modal-footer" style="padding: 0.5rem;">
                    <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

	<div class="row" style="margin-top: 10px;">
		<div class="form-group col-lg-3 col-md-4 col-sm-6">
			<label class="col-form-label">Filtro Reprocessamento</label>
			<select id="status_reprocessamento" class="form-control">
				<option value="rejeitado_pendente">Rejeitado + Pendente</option>
				<option value="todos">Todos</option>
				<option value="pendente">Somente Pendente</option>
				<option value="aprovado">Somente Aprovado</option>
				<option value="rejeitado">Somente Rejeitado</option>
			</select>
		</div>

		<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
			<a style="margin-top: 15px;"
				onclick="reprocessamento()"
				class="btn btn-dark px-6 font-weight-bold">
				Reprocessar
			</a>
		</div>
	</div>


<script>
	$('#modal-inutilizar-nfce-pdv').on('show.bs.modal', function () {
		$('#pdv_col_nInicio_nfce').show();
		$('#pdv_col_nFinal_nfce').show();
		$('#pdv_box_numeros_inut_nfce').hide();
		$('#pdv_lista_numeros_inut_nfce').html('');
		$('#pdv_resultado_inut_nfce').hide();
	});

	$('#pdv_arquivo_inut_nfce').on('change', function () {
		let file = this.files && this.files[0] ? this.files[0] : null;
		if (!file) return;

		$('#pdv_info_arquivo_inut_nfce').text('Arquivo selecionado: ' + file.name + '. Clique em Processar arquivo para extrair a numeracao.');
		$('#pdv_box_numeros_inut_nfce').hide();
		$('#pdv_lista_numeros_inut_nfce').html('');
		$('#pdv_col_nInicio_nfce').show();
		$('#pdv_col_nFinal_nfce').show();
	});

	function processarArquivoInutilizacaoPdv() {
		let input = document.getElementById('pdv_arquivo_inut_nfce');
		let file = input && input.files && input.files[0] ? input.files[0] : null;

		if (!file) {
			swal("Atenção", "Escolha um arquivo antes de processar.", "warning");
			return;
		}

		$('#btn-processar-arquivo-inut-nfce').addClass('spinner');
		$('#pdv_info_arquivo_inut_nfce').text('Processando arquivo: ' + file.name + '...');

		let formData = new FormData();
		formData.append('arquivo', file, file.name);
		formData.append('_token', $('#_token').val());

		$.ajax({
			type: 'POST',
			url: path + 'frenteCaixa/importarNumerosInutilizacao',
			data: formData,
			processData: false,
			contentType: false,
			dataType: 'json',
			success: function (res) {
				$('#btn-processar-arquivo-inut-nfce').removeClass('spinner');
				if (res && res.numbers && res.numbers.length > 0) {
					$('#pdv_info_arquivo_inut_nfce').text('Arquivo carregado: ' + file.name + '. ' + res.count + ' numero(s) encontrado(s).');
					if (res.series && res.series.length === 1) {
						$('#pdv_serie_nfce').val(res.series[0]);
					}
					renderizarTabelaNumerosInutilizacaoPdv(res.numbers, res.already_inutilized || []);
					return;
				}

				$('#pdv_info_arquivo_inut_nfce').text((res && res.message) ? res.message : 'Nenhum numero encontrado no arquivo.');
				$('#pdv_texto_inut_nfce').val('');
				$('#pdv_box_numeros_inut_nfce').hide();
				$('#pdv_lista_numeros_inut_nfce').html('');
			},
			error: function (e) {
				$('#btn-processar-arquivo-inut-nfce').removeClass('spinner');
				let msg = e.responseJSON && e.responseJSON.message ? e.responseJSON.message : 'Não foi possível ler o arquivo no servidor.';
				$('#pdv_info_arquivo_inut_nfce').text(msg);
				$('#pdv_texto_inut_nfce').val('');
				$('#pdv_box_numeros_inut_nfce').hide();
				$('#pdv_lista_numeros_inut_nfce').html('');
			}
		});
	}

	function lerArquivoInutilizacaoNoNavegador(file) {
		if ((file.name || '').toLowerCase().match(/\.pdf$/)) {
			$('#pdv_info_arquivo_inut_nfce').text('PDF precisa ser processado pelo servidor para evitar numeros internos do arquivo.');
			return;
		}

		let reader = new FileReader();
		reader.onload = function (event) {
			$('#pdv_texto_inut_nfce').val(event.target.result || '');
			carregarNumerosInutilizacaoPdv();
		};
		reader.readAsText(file);
	}

	function extrairNumerosInutilizacaoPdv(texto) {
		let encontrados = (texto || '').match(/\b\d{1,9}\b/g) || [];
		let unicos = {};
		let numeros = [];

		encontrados.forEach(function (numero) {
			let valor = parseInt(numero, 10);
			if (!valor || valor < 1) return;
			if (valor > 999999999) return;
			if (unicos[valor]) return;

			unicos[valor] = true;
			numeros.push(valor);
		});

		numeros.sort(function (a, b) {
			return a - b;
		});

		return numeros;
	}

	function carregarNumerosInutilizacaoPdv() {
		let numeros = extrairNumerosInutilizacaoPdv($('#pdv_texto_inut_nfce').val());
		renderizarTabelaNumerosInutilizacaoPdv(numeros);
	}

	function renderizarTabelaNumerosInutilizacaoPdv(numeros, jaInutilizados) {
		let lista = $('#pdv_lista_numeros_inut_nfce');
		lista.html('');
		$('#pdv_resultado_inut_nfce').hide();
		let setJa = new Set(jaInutilizados || []);

		if (numeros.length === 0) {
			$('#pdv_box_numeros_inut_nfce').hide();
			$('#pdv_col_nInicio_nfce').show();
			$('#pdv_col_nFinal_nfce').show();
			swal("Atenção", "Nenhum numero foi encontrado para conferir.", "warning");
			return;
		}

		numeros.forEach(function (numero) {
			let ja = setJa.has(numero);
			lista.append(
				'<tr data-numero="' + numero + '"' + (ja ? ' style="opacity:0.5;"' : '') + '>' +
					'<td>' +
					'<label class="checkbox checkbox-outline checkbox-primary mb-0">' +
						'<input type="checkbox" class="pdv-numero-inut-nfce" value="' + numero + '"' +
							(ja ? ' disabled' : ' checked') + '>' +
						'<span></span>' +
					'</label>' +
					'</td>' +
					'<td>' + (lista.children().length + 1) + '</td>' +
					'<td><strong>' + numero + '</strong></td>' +
					'<td class="pdv-status-inut-nfce">' +
						(ja
							? '<span class="badge badge-light-danger">Ja Inutilizado</span>'
							: '<span class="badge badge-light-primary">Selecionado</span>') +
					'</td>' +
				'</tr>'
			);
		});

		let pendentes = numeros.filter(function(n) { return !setJa.has(n); }).length;
		$('#pdv_check_todos_inut_nfce').prop('checked', pendentes > 0);
		$('#pdv_resumo_numeros_inut_nfce').text(
			numeros.length + ' numero(s) encontrado(s) — ' +
			setJa.size + ' ja inutilizado(s), ' +
			pendentes + ' pendente(s).'
		);
		$('#pdv_box_numeros_inut_nfce').show();
		$('#pdv_col_nInicio_nfce').hide();
		$('#pdv_col_nFinal_nfce').hide();
	}

	function marcarTodosInutilizacaoPdv(marcado) {
		$('.pdv-numero-inut-nfce:not(:disabled)').prop('checked', marcado);
		$('.pdv-numero-inut-nfce:not(:disabled)').each(function () {
			$(this).closest('tr').find('.pdv-status-inut-nfce').html(marcado
				? '<span class="badge badge-light-primary">Selecionado</span>'
				: '<span class="badge badge-light">Ignorado</span>'
			);
		});
	}

	$(document).on('change', '.pdv-numero-inut-nfce', function () {
		let status = $(this).closest('tr').find('.pdv-status-inut-nfce');
		status.html(this.checked
			? '<span class="badge badge-light-primary">Selecionado</span>'
			: '<span class="badge badge-light">Ignorado</span>'
		);

		let total = $('.pdv-numero-inut-nfce').length;
		let marcados = $('.pdv-numero-inut-nfce:checked').length;
		$('#pdv_check_todos_inut_nfce').prop('checked', total > 0 && total === marcados);
	});

	function numerosSelecionadosInutilizacaoPdv() {
		let numeros = [];
		$('.pdv-numero-inut-nfce:checked').each(function () {
			numeros.push(parseInt($(this).val(), 10));
		});
		numeros.sort(function (a, b) {
			return a - b;
		});
		return numeros;
	}

	function montarIntervalosInutilizacaoPdv(numeros) {
		let intervalos = [];
		if (numeros.length === 0) return intervalos;

		let inicio = numeros[0];
		let fim = numeros[0];
		for (let i = 1; i < numeros.length; i++) {
			if (numeros[i] === fim + 1) {
				fim = numeros[i];
				continue;
			}

			intervalos.push({inicio: inicio, fim: fim});
			inicio = numeros[i];
			fim = numeros[i];
		}
		intervalos.push({inicio: inicio, fim: fim});

		return intervalos;
	}

	function inutilizarNfcePdv() {
		let justificativa = $('#pdv_justificativa_inut_nfce').val();
		let serie = $('#pdv_serie_nfce').val();
		let ano = $('#pdv_ano_nfce').val();
		let nInicio = $('#pdv_nInicio_nfce').val();
		let nFinal = $('#pdv_nFinal_nfce').val();
		let token = $('#_token').val();

		let tabelaVisivel = $('#pdv_box_numeros_inut_nfce').is(':visible');

		if (!serie || !ano || !justificativa) {
			swal("Atenção", "Informe serie, ano e justificativa.", "warning");
			return;
		}
		if (!tabelaVisivel && (!nInicio || !nFinal)) {
			swal("Atenção", "Informe o numero inicial e final ou importe um arquivo com os numeros.", "warning");
			return;
		}

		if (justificativa.length < 15) {
			swal("Atenção", "A justificativa deve ter pelo menos 15 caracteres.", "warning");
			return;
		}

		let selecionados = numerosSelecionadosInutilizacaoPdv();
		if (tabelaVisivel && selecionados.length === 0) {
			swal("Atenção", "Selecione ao menos um numero importado ou feche a lista para usar o intervalo manual.", "warning");
			return;
		}
		if (selecionados.length > 0) {
			inutilizarNumerosSelecionadosNfcePdv(selecionados, serie, ano, justificativa, token);
			return;
		}

		$('#btn-inutilizar-nfce-pdv').addClass('spinner');

		$.ajax({
			type: 'POST',
			data: {
				justificativa: justificativa,
				serie: serie,
				ano: ano,
				nInicio: nInicio,
				nFinal: nFinal,
				_token: token
			},
			url: path + 'nf/inutilizarnfce',
			dataType: 'json',
			success: function (e) {
				$('#btn-inutilizar-nfce-pdv').removeClass('spinner');

				if (e.infInut && e.infInut.cStat == '102') {
					swal("Sucesso", "[" + e.infInut.cStat + "] " + e.infInut.xMotivo, "success")
						.then(() => {
							location.reload();
						});
					return;
				}

				if (e.infInut) {
					swal("Erro", "[" + e.infInut.cStat + "] " + e.infInut.xMotivo, "error");
					return;
				}

				swal("Erro", JSON.stringify(e), "error");
			},
			error: function (e) {
				$('#btn-inutilizar-nfce-pdv').removeClass('spinner');
				let msg = e.responseJSON || "Erro de comunicação contate o desenvolvedor!";
				if (typeof msg === 'object') {
					msg = msg.infInut ? "[" + msg.infInut.cStat + "] " + msg.infInut.xMotivo : JSON.stringify(msg);
				}
				swal("Erro", msg, "error");
			}
		});
	}

	function inutilizarNumerosSelecionadosNfcePdv(numeros, serie, ano, justificativa, token) {
		let intervalos = montarIntervalosInutilizacaoPdv(numeros);
		let resultados = [];
		let atual = 0;

		if (intervalos.length === 0) {
			swal("Atenção", "Selecione ao menos um numero para inutilizar.", "warning");
			return;
		}

		$('#btn-inutilizar-nfce-pdv').addClass('spinner');
		$('#pdv_resultado_inut_nfce').show();
		$('#pdv_resultado_inut_nfce_texto').html('Iniciando inutilizacao de ' + numeros.length + ' numero(s)...');

		function enviarProximo() {
			if (atual >= intervalos.length) {
				$('#btn-inutilizar-nfce-pdv').removeClass('spinner');
				let sucesso = resultados.filter(function (r) { return r.ok; }).map(function (r) { return r.label; });
				let erro = resultados.filter(function (r) { return !r.ok; }).map(function (r) { return r.label + ': ' + r.msg; });
				let html = '';

				html += '<strong>Inutilizados:</strong> ' + (sucesso.length ? sucesso.join(', ') : 'nenhum') + '<br>';
				if (erro.length) {
					html += '<strong>Com erro:</strong> ' + erro.join(' | ');
				}

				$('#pdv_resultado_inut_nfce_texto').html(html);
				swal("Concluido", "Confira o resultado no modal.", erro.length ? "warning" : "success");
				return;
			}

			let intervalo = intervalos[atual];
			let label = intervalo.inicio === intervalo.fim ? String(intervalo.inicio) : intervalo.inicio + '-' + intervalo.fim;
			$('#pdv_resultado_inut_nfce_texto').html('Enviando intervalo ' + label + '...');

			$.ajax({
				type: 'POST',
				data: {
					justificativa: justificativa,
					serie: serie,
					ano: ano,
					nInicio: intervalo.inicio,
					nFinal: intervalo.fim,
					_token: token
				},
				url: path + 'nf/inutilizarnfce',
				dataType: 'json',
				success: function (e) {
					if (e.infInut && e.infInut.cStat == '102') {
						resultados.push({ok: true, label: label, msg: e.infInut.xMotivo});
						marcarStatusIntervaloInutilizacaoPdv(intervalo.inicio, intervalo.fim, true, e.infInut.xMotivo);
					} else if (e.infInut) {
						resultados.push({ok: false, label: label, msg: '[' + e.infInut.cStat + '] ' + e.infInut.xMotivo});
						marcarStatusIntervaloInutilizacaoPdv(intervalo.inicio, intervalo.fim, false, '[' + e.infInut.cStat + '] ' + e.infInut.xMotivo);
					} else {
						resultados.push({ok: false, label: label, msg: JSON.stringify(e)});
						marcarStatusIntervaloInutilizacaoPdv(intervalo.inicio, intervalo.fim, false, JSON.stringify(e));
					}
					atual++;
					enviarProximo();
				},
				error: function (e) {
					let msg = e.responseJSON || "Erro de comunicacao";
					if (typeof msg === 'object') {
						msg = msg.infInut ? "[" + msg.infInut.cStat + "] " + msg.infInut.xMotivo : JSON.stringify(msg);
					}
					resultados.push({ok: false, label: label, msg: msg});
					marcarStatusIntervaloInutilizacaoPdv(intervalo.inicio, intervalo.fim, false, msg);
					atual++;
					enviarProximo();
				}
			});
		}

		enviarProximo();
	}

	function gravarInutilizadosNfcePdv() {
		let justificativa = $('#pdv_justificativa_inut_nfce').val();
		let serie = $('#pdv_serie_nfce').val();
		let ano = $('#pdv_ano_nfce').val();
		let token = $('#_token').val();

		if (!serie || !ano || !justificativa) {
			swal("Atenção", "Informe serie, ano e justificativa.", "warning");
			return;
		}
		if (justificativa.length < 15) {
			swal("Atenção", "A justificativa deve ter pelo menos 15 caracteres.", "warning");
			return;
		}

		let selecionados = numerosSelecionadosInutilizacaoPdv();
		if (selecionados.length === 0) {
			swal("Atenção", "Selecione ao menos um numero para gravar.", "warning");
			return;
		}

		let intervalos = montarIntervalosInutilizacaoPdv(selecionados);

		$('#btn-gravar-inutilizados-nfce-pdv').addClass('spinner');

		$.ajax({
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				_token: token,
				serie: serie,
				ano: ano,
				justificativa: justificativa,
				intervalos: intervalos,
			}),
			url: path + 'frenteCaixa/gravarInutilizados',
			dataType: 'json',
			success: function (res) {
				$('#btn-gravar-inutilizados-nfce-pdv').removeClass('spinner');
				if (res && res.ok) {
					swal("Sucesso", res.message, "success");
					selecionados.forEach(function (numero) {
						let row = $('#pdv_lista_numeros_inut_nfce tr[data-numero="' + numero + '"]');
						row.find('.pdv-status-inut-nfce').html('<span class="badge badge-light-success">Gravado</span>');
					});
				} else {
					swal("Erro", (res && res.message) ? res.message : 'Erro ao gravar.', "error");
				}
			},
			error: function (e) {
				$('#btn-gravar-inutilizados-nfce-pdv').removeClass('spinner');
				let msg = e.responseJSON && e.responseJSON.message ? e.responseJSON.message : 'Erro ao gravar.';
				swal("Erro", msg, "error");
			}
		});
	}

	function marcarStatusIntervaloInutilizacaoPdv(inicio, fim, ok, mensagem) {
		for (let numero = inicio; numero <= fim; numero++) {
			let row = $('#pdv_lista_numeros_inut_nfce tr[data-numero="' + numero + '"]');
			let badge = ok
				? '<span class="badge badge-light-success" title="' + mensagem + '">Inutilizado</span>'
				: '<span class="badge badge-light-danger" title="' + mensagem + '">Erro</span>';
			row.find('.pdv-status-inut-nfce').html(badge);
		}
	}
</script>

@endsection
