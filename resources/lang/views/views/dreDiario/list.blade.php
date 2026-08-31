
@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">


	<div class="card-body">
		<div class="">
			<div class="col-sm-12 col-lg-4 col-md-6 col-xl-4">

				<h3>DRE Diário</h3>
			</div>
		</div>
		<br>


		<div class="" id="kt_user_profile_aside" style="margin-left: 10px; margin-right: 10px;">

			<form method="get" action="/drediario/filtro">
				<div class="row align-items-center">

					<div class="form-group col-lg-2 col-md-4 col-sm-6">
						<label class="col-form-label">Data Inicial</label>
						<div class="">
							<div class="input-group date">
								<input type="text" name="data_inicial" class="form-control" readonly value="{{{ isset($data_inicial) ? $data_inicial : '' }}}" id="kt_datepicker_3" />
								<div class="input-group-append">
									<span class="input-group-text">
										<i class="la la-calendar"></i>
									</span>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group col-lg-2 col-md-4 col-sm-6">
						<label class="col-form-label">Data Final</label>
						<div class="">
							<div class="input-group date">
								<input type="text" name="data_final" class="form-control" readonly value="{{{ isset($data_final) ? $data_final : '' }}}" id="kt_datepicker_3" />
								<div class="input-group-append">
									<span class="input-group-text">
										<i class="la la-calendar"></i>
									</span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-2 col-xl-2 mt-2 mt-lg-0">
						<button style="margin-top: 10px;" class="btn btn-light-primary px-6 font-weight-bold">Pesquisa</button>
					</div>
				</div>

			</form>
			<br>
			<h4>DRE Diário</h4>

			<label>Total de registros: {{count($fluxo)}}</label>
			<div class="row">

				<?php  
				$totalVenda = 0;
				$totalContaReceber = 0;
				$totalContaPagar = 0;
				$totalCredito = 0;
				$totalResultado = 0; 
				?>
				@foreach($fluxo as $f)


				<div class="col-sm-12 col-lg-6 col-md-6 col-xl-4">
					<div class="card card-custom gutter-b example example-compact">
						<div class="card-header">
							<div class="card-title">
								<h3 style="width: 230px; font-size: 20px; height: 10px;" class="card-title">
									{{$f['data']}}
								</h3>
							</div>

							<div class="card-toolbar">
								

							</div>

							<div class="card-body">

								<div class="kt-widget__info">
									<span class="kt-widget__label">Vendas:</span>
									<a target="_blank" class="kt-widget__data text-success">
										R$ {{number_format($f['venda'], 2, ',', '.')}}
									</a>
								</div>
								<div class="kt-widget__info">
									<span class="kt-widget__label">Custo Produto:</span>
									<a class="kt-widget__data text-success">
										R$ {{number_format($f['custoproduto'], 2, ',', '.')}}
									</a>
								</div>
								<div class="kt-widget__info">
									<span class="kt-widget__label text-info">Lucro Bruto:</span>
									<a class="kt-widget__data text-info">
										R$ {{number_format($f['venda']-$f['custoproduto'], 2, ',', '.')}}
									</a>
								</div>
								<div class="kt-widget__info">
									<span class="kt-widget__label">Despesas Fixas:</span>
									<a class="kt-widget__data text-success">
										R$ {{number_format($f['despesasfixas'], 2, ',', '.')}}
									</a>
								</div>
								<div class="kt-widget__info">
									<span class="kt-widget__label">Despesas Variáveis :</span>
									<a class="kt-widget__data text-success">
									    R$ {{number_format($f['despesasvariaveis'], 2, ',', '.')}}
									</a>
								</div>
								
								<div class="kt-widget__info">
									<span class="kt-widget__label text-info">Lucro Líquido:</span>
									<a class="kt-widget__data text-info">
									    R$ {{number_format($f['venda']-($f['custoproduto']+$f['despesasfixas']+($f['venda'] * 10/100)), 2, ',', '.')}}
									</a>
								</div>
							
								

							</div>

						</div>

					</div>

				</div>

				<?php  
				$totalVenda += $f['venda'];
			
				?>

				@endforeach

			</div>


			<div class="card-body">
				<div class="row">
					<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
						<div class="card card-custom gutter-b example example-compact">
							<div class="card-header">

								<div class="card-body">
									
									<div class="row">
										<div class="col-sm-3 col-md-3 col-lg-4">
											<h4>Total venda: <strong class="">R$ {{number_format($totalVenda, 2, ',', '.')}}</strong></h4>
										</div>
										
									</div>

									<div class="row">
										<div class="col-sm-6 col-md-6 col-lg-6">

											@if(isset($data_inicial) && isset($data_final))
											<a style="width: 100%;" href="/fluxoCaixa/relatorioFiltro/{{$dataInicial}}/{{$dataFinal}}">
												<span class="label label-xl label-inline label-light-success">Imprimir relatório</span>

											</a>
											@else
											<a style="width: 100%;" href="/fluxoCaixa/relatorioIndex">
												<span class="label label-xl label-inline label-light-success">Imprimir relatório</span>
											</a>
											@endif
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

@endsection

