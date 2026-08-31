@extends('default.layout')
@section('content')
<div class="card card-custom gutter-b">
	<div class="card-body">

		<div class="">
			<div class="col-sm-12 col-lg-4 col-md-6 col-xl-4">

				<a href="/contasPagar/new" class="btn btn-lg btn-success">
					<i class="fa fa-plus"></i>Nova Despesa
				</a>
			</div>
		</div>
		<br>
		<div class="" id="kt_user_profile_aside" style="margin-left: 10px; margin-right: 10px;">

			<input type="hidden" id="_token" value="{{ csrf_token() }}">

			<form method="get" action="/contasPagar/filtro">
				<div class="row align-items-center">

					<div class="form-group col-lg-4 col-xl-4">
						<div class="row align-items-center">

							<div class="col-md-12 my-2 my-md-0">
								<label class="col-form-label">Fornecedor</label>

								<div class="input-icon">
									<input type="text" name="fornecedor" value="{{{ isset($fornecedor) ? $fornecedor : '' }}}" class="form-control" placeholder="Fornecedor" id="kt_datatable_search_query">
									<span>
										<i class="fa fa-search"></i>
									</span>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group col-lg-2 col-md-4 col-sm-6">
						<label class="col-form-label">Data de Registro</label>
						<div class="">
							<div class="input-group date">
								<input type="text" name="data_inicial" class="form-control" readonly value="{{{ isset($dataInicial) ? $dataInicial : '' }}}" id="kt_datepicker_3" />
								<div class="input-group-append">
									<span class="input-group-text">
										<i class="la la-calendar"></i>
									</span>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group col-lg-2 col-md-4 col-sm-6">
						<label class="col-form-label">Data de Final</label>
						<div class="">
							<div class="input-group date">
								<input type="text" name="data_final" class="form-control" readonly value="{{{ isset($dataFinal) ? $dataFinal : '' }}}" id="kt_datepicker_3" />
								<div class="input-group-append">
									<span class="input-group-text">
										<i class="la la-calendar"></i>
									</span>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group col-lg-2 col-md-4 col-sm-6">
						<label class="col-form-label text-left col-lg-12 col-sm-12">Estado</label>

						<select class="custom-select form-control" id="status" name="status">
							<option @if(isset($stats) && $status=='todos' ) selected @endif value="todos">TODOS</option>
							<option @if(isset($stats) && $status=='pago' ) selected @endif value="pago">PAGO</option>
							<option @if(isset($stats) && $status=='pendente' ) selected @endif value="pendente">PENDENTE</option>
						</select>


					</div>

					<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
						<button style="margin-top: 10px;" class="btn btn-light-primary px-6 font-weight-bold">Pesquisa</button>
					</div>
				</div>

			</form>
			<br>

			<h4>Lista de Contas a Pagar</h4>
			<h6 style="color: red">*{{$infoDados}}</h6>
			<label>Total de registros: {{count($contas)}}</label>
			<div class="row">

				<?php
				$somaValor = 0;
				$somaPago = 0;
				$somaPendente = 0;
				?>

				<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
					<div class="wizard wizard-3" id="kt_wizard_v3" data-wizard-state="between" data-wizard-clickable="true">
						<div class="wizard-nav">

							<div class="wizard-steps px-8 py-8 px-lg-15 py-lg-3">
								<!--begin::Wizard Step 1 Nav-->
								<div class="wizard-step" data-wizard-type="step" data-wizard-state="done">
									<table class="table table-bordered yajra-datatable">

										<div class="wizard-label">
											<h3 class="wizard-title">
												<span>
													<i style="font-size: 40px" class="la la-table"></i>
													Tabela
												</span>
											</h3>
											<div class="wizard-bar"></div>
									</table>
								</div>
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
								<div class="wizard-step" data-wizard-type="step" data-wizard-state="current">
									<div class="wizard-label" id="grade">
										<h3 class="wizard-title">
											<span>
												<i style="font-size: 40px" class="la la-tablet"></i>
												Resumo
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
																<!-- <th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 60px;">#</span></th> -->
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 70px;">ID</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 150px;">Fornecedor</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Conta</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Emissão</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Vencimento</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Valor</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Status</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Dt Pagamento</span></th>
																<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 90px;">Vlr Pago</span></th>
																<th data-field="CompanyName" class="datatable-cell datatable-cell-sort"><span style="width: 160px;">Ações</span></th>
															</tr>

														</thead>
														<tbody id="body" class="datatable-body">
															@foreach($contas as $c)
															<tr class="datatable-row">



																<td class="datatable-cell"><span class="codigo" style="width: 70px;" id="id">{{$c->id}}</span>
																</td>



																<td class="datatable-cell"><span class="fornecedor" style="width: 150px;" id="fornecedor">{{ $c->fornecedor->razao_social }}</span>
																</td>


																<td class="datatable-cell"><span class="conta" style="width: 90px;" id="conta">{{$c->dreconta->nome}}</span>
																</td>

																<td class="datatable-cell"><span class="emissao" style="width: 90px;" id="emissao"> {{ \Carbon\Carbon::parse($c->data_emissao)->format('d/m/Y')}}</span>
																</td>

																<td class="datatable-cell"><span class="vencimento" style="width: 90px;" id="vencimento"> {{ \Carbon\Carbon::parse($c->data_vencimento)->format('d/m/Y')}}</span>
																</td>

																<td class="datatable-cell"><span class="valordoc" style="width: 90px;" id="valordoc"> R$ {{number_format($c->valor_integral, 2, ',', '.')}}</span>
																</td>
																<!--
																	  @if($c->status == true)
								
																	  		<td class="label label-xl label-inline label-light-success" class="datatable-cell"><span class="status" style="width: 90px;" id="satus">Pago</span>
																	  @else

																	  		<td class="label label-xl label-inline label-light-danger" class="datatable-cell"><span class="status" style="width: 90px;" id="satus">Pendente</span>

																	  @endif-->


																<td class="datatable-cell">
																	<span class="codigo" style="width: 100px;">
																		@if($c->status)
																		<span class="label label-xl label-inline label-light-success">PAGO</span>
																		@else
																		<span class="label label-xl label-inline label-light-danger">PENDENTE</span>
																		@endif
																	</span>
																</td>


																</td>
																@if($c->status)

																<td class="datatable-cell"><span class="datapagamento" style="width: 90px;" id="datapagamento"> {{ \Carbon\Carbon::parse($c->data_pagamento)->format('d/m/Y')}}</span>
																</td>


																@else

																<td class="datatable-cell"><span class="datapagamento" style="width: 90px;" id="datapagamento"> </span>
																</td>

																@endif


																<td class="datatable-cell"><span class="valordoc" style="width: 90px;" id="valordoc"> R$ {{number_format($c->valor_pago, 2, ',', '.')}}</span>
																</td>



																<td>
																	<div class="row">
																		<span style="width: 160px;">

																			@if($c->status == false)


																			<a class="btn btn-success" onclick="setaBaixa('{{ $c->id }}','{{ $c->valor_integral}}')" class="navi-link">

																				<i class="la la-bank"></i>
																			</a>
																			@endif

																			<a class="btn btn-info" href="/contasPagar/edit/{{$c->id}}" class="navi-link">
																				<i class="la la-file"></i>
																				<!-- <i class="la la-bank"></i> -->
																			</a>

																			<a class="btn btn-danger" onclick='swal("Atenção!", "Deseja cancelar essa venda?", "warning").then((sim) => {if(sim){ location.href="/contasPagar/delete/{{ $c->id }}" }else{return false} })' href="#!">
																				<i class="la la-trash"></i>
																			</a>

																		</span>

																	</div>



																</td>







															</tr>

															<?php
															$somaValor += $c->valor_integral;
															$somaPago += $c->valor_pago;

															if ($c->status == false)
																$somaPendente += $c->valor_integral;

															?>



															@endforeach

														</tbody>
													</table>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!--end: Wizard Step 1-->

								<!--begin: Wizard Step 2-->

								<!-- Inicio do card -->
								<div class="pb-5" data-wizard-type="step-content">

									<!-- Inicio do card -->
									<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">

										<div class="row">
											@foreach($contas as $c)

											<div class="col-sm-12 col-lg-6 col-md-6 col-xl-4">
												<div class="card card-custom gutter-b example example-compact">
													<div class="card-header">
														<div class="card-title">
															<h3 style="width: 230px; font-size: 20px; height: 10px;" class="card-title">
																R$ {{number_format($c->valor_integral, 2, ',', '.')}}
															</h3>
														</div>

														<div class="card-toolbar">
															<div class="dropdown dropdown-inline" data-toggle="tooltip" title="" data-placement="left" data-original-title="Ações">
																<a href="#" class="btn btn-hover-light-primary btn-sm btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
																	<i class="fa fa-ellipsis-h"></i>
																</a>
																<div class="dropdown-menu p-0 m-0 dropdown-menu-md dropdown-menu-right">
																	<!--begin::Navigation-->
																	<ul class="navi navi-hover">
																		<li class="navi-header font-weight-bold py-4">
																			<span class="font-size-lg">Ações:</span>
																		</li>


																		<li class="navi-separator mb-3 opacity-70"></li>
																		<li class="navi-item">
																			<a href="/contasPagar/edit/{{$c->id}}" class="navi-link">
																				<span class="navi-text">
																					<span class="label label-xl label-inline label-light-primary">Editar</span>
																				</span>
																			</a>
																		</li>
																		<li class="navi-item">
																			<a onclick='swal("Atenção!", "Deseja remover este registro?", "warning").then((sim) => {if(sim){ location.href="/contasPagar/delete/{{ $c->id }}" }else{return false} })' href="#!" class="navi-link">
																				<span class="navi-text">
																					<span class="label label-xl label-inline label-light-danger">Excluir</span>
																				</span>
																			</a>
																		</li>

																		@if($c->status == false)

																		<li class="navi-item">
																			<!-- <a href="/contasPagar/pagar/{{$c->id}}" class="navi-link"> -->


																			<a onclick="setaBaixa('{{ $c->id }}','{{ $c->valor_integral}}')" class="navi-link">

																				<span class="navi-text">


																					<span class="input-group-text btn-info btn">Pagar</span>
																					<!--  <span class="label label-xl label-inline label-light-success">Pagar</span> -->
																				</span>
																			</a>
																			<!-- 	</a>  -->
																		</li>

																		@endif


																	</ul>
																	<!--end::Navigation-->
																</div>
															</div>

														</div>

														<div class="card-body">

															<div class="kt-widget__info">
																<span class="kt-widget__label">Fornecedor:</span>
																<a target="_blank" class="kt-widget__data text-success">
																	<th>{{ $c->fornecedor->razao_social }}</th>
																</a>
															</div>
															<div class="kt-widget__info">
																<span class="kt-widget__label">Conta:</span>
																<a class="kt-widget__data text-success">
																	{{$c->dreconta->nome}}
																</a>
															</div>
															<div class="kt-widget__info">
																<span class="kt-widget__label">Data de Emissão:</span>
																<a class="kt-widget__data text-success">
																	{{ \Carbon\Carbon::parse($c->data_emissao)->format('d/m/Y')}}
																</a>
															</div>
															<div class="kt-widget__info">
																<span class="kt-widget__label">Data de vencimento:</span>
																<a class="kt-widget__data text-success">
																	{{ \Carbon\Carbon::parse($c->data_vencimento)->format('d/m/Y')}}
																</a>
															</div>



															<div class="kt-widget__info">
																<span class="kt-widget__label">Estado:</span>
																@if($c->status == true)
																<span class="label label-xl label-inline label-light-success">Pago</span>
																@else
																<span class="label label-xl label-inline label-light-danger">Pendente</span>
																@endif
															</div>

															@if($c->status == true)

															<div class="kt-widget__info">
																<span class="kt-widget__label">Data de Pagamento:</span>
																<a class="kt-widget__data text-success">
																	{{ \Carbon\Carbon::parse($c->data_pagamento)->format('d/m/Y')}}
																</a>
															</div>
															<div class="kt-widget__info">
																<span class="kt-widget__label">Valor pago:</span>
																<a class="kt-widget__data text-success">
																	{{ number_format($c->valor_pago, 2, ',', '.') }}
																</a>
															</div>

															@endif



														</div>

													</div>

												</div>

											</div>

											@endforeach
											<!--end::Navigation-->
										</div>
									</div>

								</div>
								<?php
								$totalconta = 0;
								$totalcontacompra = 0;
								$totalcontavariavel =0;
								
								?>
								<!-- Inicio do Resumo -->
								<!--begin: Wizard Step 3-->
								<div class="pb-5" data-wizard-type="step-content">
									<div class="card card-custom gutter-b">

										<div class="card-body">

											<div class="card card-custom gutter-b example example-compact">
												<div class="card-header bg-info">
													<h2 class="card-title text-white"> CUSTO FIXO

													</h2>
												</div>

												<div class="card-body">



													@foreach($contasfixasagrupadas as $c)
													<div class="row" style="height: 45px;">
														<div class="col-6">
															<h4 class="text-left">{{$c->nome}}</h4>
														</div>


														<div class="col-3">
															<h4 class="text-left">
																<strong>
																	R$ {{ number_format($c->soma, 2, ',', '.') }}
																</strong>
															</h4>
														</div>


													</div>
													<?php
													$totalconta += $c->soma
													?>
													@endforeach

												

											




												</div>
												<div class="card-header bg-info">
														<h2 class="card-title text-white"> TOTAL CUSTO FIXO

														</h2>

														<h2 class="card-title text-white"> R$ {{number_format($totalconta, 2, ',', '.') }}

														</h2>

												</div>


											</div>
											<div class="card card-custom gutter-b example example-compact">
												<div class="card-header bg-info">
													<h2 class="card-title text-white"> CUSTO VARIAVEL

													</h2>
												</div>

												<div class="card-body">



													@foreach($contasvariavelagrupadas as $c)
													<div class="row" style="height: 45px;">
														<div class="col-6">
															<h4 class="text-left">{{$c->nome}}</h4>
														</div>


														<div class="col-3">
															<h4 class="text-left">
																<strong>
																	R$ {{ number_format($c->soma, 2, ',', '.') }}
																</strong>
															</h4>
														</div>


													</div>
													<?php
													$totalcontavariavel += $c->soma
													?>
													@endforeach

												

											




												</div>
												<div class="card-header bg-info">
														<h2 class="card-title text-white"> TOTAL CUSTO VARIAVEL

														</h2>

														<h2 class="card-title text-white"> R$ {{number_format($totalcontavariavel, 2, ',', '.') }}

														</h2>

												</div>


											</div>
										
												<?php
													$totalcontacompra += $contascomprasagrupadas->soma
												?>
											
												

											<div class="card card-custom gutter-b example example-compact">
												<div class="card-header bg-info">
													<h2 class="card-title text-white"> TOTAL DE COMPRAS DE PRODUTOS

													</h2>
													<h2 class="card-title text-white">R$ {{number_format($totalcontacompra, 2, ',', '.') }}

													</h2>
													
												</div>
											</div>

										</div>
									</div>


								</div>


						</div>

					</div>



					</form>

					<div class="card-body">
						<div class="row">
							<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
								<div class="card card-custom gutter-b example example-compact">
									<div class="card-header">

										<div class="card-body">
											<h3 class="card-title">Valor a Pagar: <strong style="margin-left: 5px;"> R$ {{number_format($somaPendente, 2, ',', '.') }}</strong></h3>

										</div>

										<div class="card-body">
											<h3 class="card-title">Valor Pago: <strong style="margin-left: 5px;"> R$ {{number_format($somaPago, 2, ',', '.') }}</strong></h3>

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



<div class="modal fade" id="modal-baixa" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">CONFIRMAÇÃO DA BAIXA</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					x
				</button>
			</div>
			<div class="modal-body">
				<div class="row">
					<input id="idbaixa" type="hidden" class="form-control" name="idbaixa">
					<div class="form-group validated col-sm-6 col-lg-6">

						<label class="col-form-label" id="">Data de Pagamento</label>
						<input type="text" name="datapagamento" class="form-control data_pagamento" readonly id="kt_datepicker_3" />


					</div>

					<div class="form-group validated col-sm-6 col-lg-6">
						<label class="col-form-label" id="">Valor Pago</label>
						<input id="valorbaixa" type="text" class="form-control @if($errors->has('valorbaixa')) is-invalid @endif money" name="valorbaixa" value="">
					</div>



				</div>



			</div>


			<div class="modal-footer">
				<button style="width: 100%" type="button" onclick="salvarbaixa()" class="btn btn-success font-weight-bold">Confirma a Baixa</button>
			</div>
		</div>
	</div>
</div>














@endsection