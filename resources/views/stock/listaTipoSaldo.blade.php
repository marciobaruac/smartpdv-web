

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
									<h4>Apontamento de Estoque por Tipo Saldo</h4>

									<table class="datatable-table" style="max-width: 100%; overflow: scroll">
										<thead class="datatable-head">
											<tr class="datatable-row" style="left: 0px;">
												
												<th data-field="OrderID" class="datatable-cell datatable-cell-sort"><span style="width: 150px;">Produto</span></th>
												<th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 120px;">Saldo</span></th>
												<th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 120px;">Quanidade Informada</span></th>
												<th data-field="Country" class="datatable-cell datatable-cell-sort"><span style="width: 120px;">Divergência</span></th>
												<th data-field="ShipDate" class="datatable-cell datatable-cell-sort"><span style="width: 80px;">Data</span></th>

											

											</tr>
										</thead>
										<tbody class="datatable-body">
											
											@foreach($apontamentos as $a)

											<tr class="datatable-row" style="left: 0px;">
												<td class="datatable-cell"><span class="codigo" style="width: 150px;">
													{{$a->nome}} 
												</span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 120px;">
												    {{number_format($a->saldoant, 2, ',', '.') }}
												</span></td>

												<td class="datatable-cell"><span class="codigo" style="width: 120px;">
												    {{number_format($a->qtdinf, 2, ',', '.') }}
												
											    </span></td>
												<td class="datatable-cell"><span class="codigo" style="width: 120px;">
												    {{number_format($a->qtddif, 2, ',', '.') }}
											
												</span></td>
											
												<td class="datatable-cell"><span class="codigo" style="width: 80px;">
													{{ \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i:s')}}
												</span></td>

	

												

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


