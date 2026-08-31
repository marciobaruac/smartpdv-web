

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
									<h4>Movimentacao de Produtos</h4>

									<form method="get" action="/estoque/listApontamentos/{{$produto_id}}">
										<div class="row align-items-center">
											<div class="form-group col-lg-3 col-md-4 col-sm-6">
												<label class="col-form-label">Data Inicial</label>
												<div class="input-group date">
													<input type="text" name="dataInicial" class="form-control" readonly value="{{isset($dataInicial) ? $dataInicial : ''}}" id="kt_datepicker_3" />
													<div class="input-group-append">
														<span class="input-group-text">
															<i class="la la-calendar"></i>
														</span>
													</div>
												</div>
											</div>
											<div class="form-group col-lg-3 col-md-4 col-sm-6">
												<label class="col-form-label">Data Final</label>
												<div class="input-group date">
													<input type="text" name="dataFinal" class="form-control" readonly value="{{isset($dataFinal) ? $dataFinal : ''}}" id="kt_datepicker_3" />
													<div class="input-group-append">
														<span class="input-group-text">
															<i class="la la-calendar"></i>
														</span>
													</div>
												</div>
											</div>
											<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
												<button style="margin-top: 15px;" class="btn btn-light-primary px-6 font-weight-bold">Buscar</button>
											</div>
										</div>
									</form>
									<div class="text-muted mb-2">Carregando por padrao os ultimos 15 dias. Use o periodo para consultar datas maiores.</div>
									<label>Total de registros: {{count($apontamentos)}}</label>

                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <div class="card bg-light-success">
                                                    <div class="card-body py-3">
                                                        <div class="text-muted">Entradas</div>
                                                        <div class="h4 mb-0">{{number_format($totaisGerais['entrada'], 3, ',', '.')}} UNID</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light-danger">
                                                    <div class="card-body py-3">
                                                        <div class="text-muted">Saidas</div>
                                                        <div class="h4 mb-0">{{number_format($totaisGerais['saida'], 3, ',', '.')}} UNID</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-light-primary">
                                                    <div class="card-body py-3">
                                                        <div class="text-muted">Saldo Final</div>
                                                        <div class="h4 mb-0">{{number_format($totaisGerais['saldo'], 3, ',', '.')}} UNID</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h5 class="mb-3">Totalizador por Origem</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>Origem</th>
                                                                <th>Entrada</th>
                                                                <th>Saida</th>
                                                                <th>Saldo</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($totaisPorOrigem as $origem => $t)
                                                            <tr>
                                                                <td>{{$origem}}</td>
                                                                <td>{{number_format($t['entrada'], 3, ',', '.')}} UNID</td>
                                                                <td>{{number_format($t['saida'], 3, ',', '.')}} UNID</td>
                                                                <td><strong>{{number_format($t['saldo'], 3, ',', '.')}} UNID</strong></td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

									<table class="datatable-table" style="max-width: 100%; overflow: scroll">
										<thead class="datatable-head">
											<tr class="datatable-row" style="left: 0px;">
												
												<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 150px;">Produto</span></th>
												<th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 120px;">Quantidade</span></th>
												<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Tipo</span></th>
												<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Origem</span></th>
                                                <th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Descrição</span></th>

												<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Usuário</span></th>
												<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Data</span></th>
                                                                                                                <th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 130px;">Tela origem</span></th>

											

											</tr>
										</thead>
										<tbody class="datatable-body">
											
											@foreach($apontamentos as $a)

											<tr class="datatable-row" style="left: 0px;">
												<td class="datatable-cell"><span class="codigo" style="width: 150px;">
													{{$a->produto->nome}} 
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 120px;">
												    {{number_format($a->quantidade, 2, ',', '.') }} {{$a->produto->unidade_venda}}
												</span></td>

												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
													{{$a->tipomov}}
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
                                                        @php
                                                        $origemExibicao = $a->origem;
                                                        if(is_numeric((string)$a->origem) && strtolower(trim((string)$a->descricao)) == 'compra'){
                                                            $origemExibicao = 'Compra';
                                                        }
                                                        @endphp
                                                        {{$origemExibicao}}
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
													@php
                                                        $descricaoExibicao = $a->descricao;
                                                        $nfCompra = isset($origensNf[$a->id]) ? $origensNf[$a->id] : null;
                                                        $ehCompra = ($a->origem == 'Compra' || strtolower(trim((string) $a->descricao)) == 'compra' || strtolower((string) $a->origem) == 'extorno da entrada');
                                                        if($ehCompra && !empty($nfCompra) && stripos($descricaoExibicao, 'NF') === false && stripos($descricaoExibicao, 'NFE') === false){
                                                            $descricaoExibicao = trim($descricaoExibicao) . ' | NF: ' . $nfCompra;
                                                        }
                                                    @endphp
                                                    {{$descricaoExibicao}}
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
													{{$a->usuario->nome}}
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
													{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i:s')}}
												</span></td>
                                                                                                                <td class="datatable-cell"><span class="codigo" style="width: 130px;">
                                                                                                                    @php
                                                                                                                    $compraId = isset($origensCompra[$a->id]) ? $origensCompra[$a->id] : null;
                                                                                                                    $dfeId = isset($origensDfe[$a->id]) ? $origensDfe[$a->id] : null;
                                                                                                                    $nfCompra = isset($origensNf[$a->id]) ? $origensNf[$a->id] : null;
                                                                                                                    @endphp

                                                                                                                    @if(($a->origem == 'Compra' || strtolower(trim((string) $a->descricao)) == 'compra' || strtolower((string) $a->origem) == 'extorno da entrada') && $compraId)
                                                                                                                    <a target="_blank" href="/compras/detalhes/{{$compraId}}">Compra #{{$compraId}}</a> @if($dfeId) <a target="_blank" class="text-muted" href="/dfe/get/{{$dfeId}}">| DF-e #{{$dfeId}}</a> @endif
                                                                                                                    @elseif($a->origem == 'Manual')
                                                                                                                    <a target="_blank" href="/estoque/apontamentoManual">Apontamento Manual</a>
                                                                                                                    @elseif($a->origem == 'Apontamento')
	                                                                                                                    <a target="_blank" href="/estoque/apontamentoProducao">Apontamento Producao</a>
                                                                                                                    @else
                                                                                                                    -
                                                                                                                    @endif
                                                                                                                </span></td>

	

												

											</tr>
											@endforeach
										</tbody>
									</table>
								</div>

                                        </div>
                                    </div>
							</div>
						</div>
						<div class="d-flex justify-content-between align-items-center flex-wrap">
							<div class="d-flex flex-wrap py-2 mr-3">
								@if(isset($links))
								{{$estoque->links()}}
								@endif
							</div>
						</div>

				

					</div>
				</div>
			
			</div>
		</div>
	</div>
	<div class="col-lg-3 col-sm-6 col-md-4">
		<a style="width: 100%" class="btn btn-success" href="/estoque">
			<i class="la la-check"></i>
			<span class="">Voltar</span>
		</a>
	</div>	
</div>

@endsection









