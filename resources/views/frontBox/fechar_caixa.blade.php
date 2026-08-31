@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">
	<div class="card-body">
		<div id="kt_user_profile_aside" style="margin-left: 10px; margin-right: 10px;">
			<div class="col-lg-12" id="content">

				<h2 class="card-title">
					Total de vendas: <strong class="text-info">{{sizeof($vendas)}}</strong>
				</h2>
				<h3>
					Inicio do caixa: <strong class="text-success">{{ \Carbon\Carbon::parse($abertura->created_at)->format('d/m/Y H:i:s')}}</strong>
				</h3>

				@if($usuario->adm == 1)

				<div class="row">
					<div class="col-xl-12">
						<h3 class="text-info">Total por tipo de pagamento:</h3>
						<div class="kt-section kt-section--first">
							<div class="kt-section__body">
								<div class="row">

									@foreach($somaTiposPagamento as $key => $tp)
										@if($tp > 0)
										<div class="col-sm-4 col-lg-4 col-md-6">
											<div class="card card-custom gutter-b">
												<div class="card-header">
													<h3 class="card-title">
														{{App\Models\VendaCaixa::getTipoPagamento($key)}}
													</h3>
												</div>
												<div class="card-body">
													<h4 class="text-success">R$ {{number_format($tp, 2, ',', '.')}}</h4>
												</div>
											</div>
										</div>
										@endif
									@endforeach

								</div>
							</div>
						</div>
					</div>
				</div>

				@endif

				@if($usuario->adm == 1)

				<div class="row">
					<div class="col-xl-12">
						<div id="kt_datatable" class="datatable datatable-bordered datatable-head-custom datatable-default datatable-primary datatable-loaded">

							<table class="datatable-table" style="max-width: 100%; overflow: scroll">
								<thead class="datatable-head">
									<tr class="datatable-row" style="left: 0px;">
										<th><span style="width: 70px;">#</span></th>
										<th><span style="width: 100px;">Cliente</span></th>
										<th><span style="width: 100px;">Data</span></th>
										<th><span style="width: 100px;">Tipo de pagamento</span></th>
										<th><span style="width: 100px;">Estado</span></th>
										<th><span style="width: 100px;">NFCe</span></th>
										<th><span style="width: 100px;">Valor</span></th>
									</tr>
								</thead>

								<tbody class="datatable-body">
									@foreach($vendas as $v)
									<tr class="datatable-row">
										<td><span class="codigo" style="width: 70px;">{{$v->id}}</span></td>
										<td><span class="codigo" style="width: 100px;">{{ $v->cliente->razao_social ?? 'NÃO IDENTIFICADO' }}</span></td>
										<td><span class="codigo" style="width: 100px;">{{ \Carbon\Carbon::parse($v->created_at)->format('d/m/Y H:i:s')}}</span></td>
										<td>
											<span class="codigo" style="width: 100px;">
												@if($v->tipo_pagamento == '99')
													<a href="#!" onclick='swal("", "{{$v->multiplo()}}", "info")' class="btn btn-light-info">Ver</a>
												@else
													{{$v->getTipoPagamento($v->tipo_pagamento)}}
												@endif
											</span>
										</td>
										<td><span class="codigo" style="width: 100px;">{{ $v->estado }}</span></td>
										<td><span class="codigo" style="width: 100px;">{{ $v->NFcNumero > 0 ? $v->NFcNumero : '--' }}</span></td>
										<td><span class="codigo" style="width: 100px;">{{ number_format($v->valor_total, 2, ',', '.') }}</span></td>
									</tr>
									@endforeach
								</tbody>
							</table>

						</div>
					</div>
				</div>
				<br>
				@endif

				@if(sizeof($vendas) == 0)
					<p class="text-danger">NÃO É POSSÍVEL FECHAR SEM NENHUMA VENDA</p>
				@endif

				<div class="row">
					<form method="post" action="/frenteCaixa/fechar" class="col-md-6">
						@csrf
						<input type="hidden" name="abertura_id" value="{{$abertura->id}}">

						<div class="form-group">
							<label for="saldo_informado_fechamento">Informar o Total do Caixa</label>
							<input
								type="number"
								step="0.01"
								min="0"
								class="form-control"
								name="saldo_informado_fechamento"
								id="saldo_informado_fechamento"
								required
								placeholder="Informe o valor de fechamento"
							/>
						</div>

						<button
							@if(sizeof($vendas) == 0) disabled @endif
							class="btn btn-lg btn-danger">
							<i class="la la-times"></i>
							Fechar Caixa
						</button>
					</form>
				</div>

			</div>
		</div>
	</div>
</div>

<script>
    window.onload = function() {
        document.getElementById("saldo_informado_fechamento").focus();
    }
</script>

@endsection
