@extends('default.layout')
@section('content')

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">

	<div class="container">
		<div class="card card-custom gutter-b example example-compact">
			<div class="col-lg-12">
				<!--begin::Portlet-->

				<form method="post" action="/estoque/saveApontamentoManual" enctype="multipart/form-data" id="formApontamentoManualPagina">

					<div class="card card-custom gutter-b example example-compact">
						<div class="card-header">

							<h3 class="card-title">Novo Apontamento</h3>
						</div>

					</div>
					@csrf

					<div class="row">
						<div class="col-xl-2"></div>
						<div class="col-xl-8">
							<div class="kt-section kt-section--first">
								<div class="kt-section__body">

									<div class="row">
										<div class="form-group validated col-lg-8 col-md-8 col-sm-10">
											<label class="col-form-label text-left col-lg-4 col-sm-12">Produto</label>
											<select class="form-control select2" id="kt_select2_1" name="produto">
												@foreach($produtos as $p)
												<option value="{{$p->id}}"> {{$p->nome}} - {{$p->id}}</option>
												@endforeach
											</select>
											@if($errors->has('produto'))
											<div class="invalid-feedback">
												{{ $errors->first('produto') }}
											</div>
											@endif
										</div>
									</div>

									<div class="row">
										<div class="form-group validated col-sm-6 col-lg-4">
											<label class="col-form-label">Quantidade</label>
											<div class="">
                                            <input type="number" id="quantidade" class="form-control @if($errors->has('quantidade')) is-invalid @endif" name="quantidade" value="{{ old('quantidade') }}" min="0" step="0.01">

                                            @if($errors->has('quantidade'))
												<div class="invalid-feedback">
													{{ $errors->first('quantidade') }}
												</div>
												@endif
											</div>
										</div>

										<div class="form-group validated col-sm-6 col-lg-4">
											<label class="col-form-label">Tipo</label>
											<div class="">
												<select class="custom-select form-control @if($errors->has('tipo')) is-invalid @endif" id="tipo" name="tipo" required>
                                                    <option value="">Selecione o tipo</option>
												    <option value="saldo">Contagem de Estoque</option>
												    
												</select>

												@if($errors->has('tipo'))
												<div class="invalid-feedback">
													{{ $errors->first('tipo') }}
												</div>
												@endif
											</div>
										</div>

									</div>

									<div class="row">
										<div class="form-group validated col-lg-8 col-md-8 col-sm-10">
											<label class="col-form-label text-left col-lg-4 col-sm-12">Observação</label>

											<input type="text" id="observacao" class="form-control @if($errors->has('observacao')) is-invalid @endif" name="observacao" value="{{ old('observacao') }}">
											@if($errors->has('observacao'))
											<div class="invalid-feedback">
												{{ $errors->first('observacao') }}
											</div>
											@endif
										</div>
									</div>



								</div>
							</div>
						</div>
					</div>
					<div class="card-footer">

						<div class="row">
							<div class="col-xl-2">

							</div>
							<div class="col-lg-3 col-sm-6 col-md-4">
								<a style="width: 100%" class="btn btn-danger" href="">
									<i class="la la-close"></i>
									<span class="">Cancelar</span>
								</a>
							</div>
							<div class="col-lg-3 col-sm-6 col-md-4">
								<button style="width: 100%" type="submit" class="btn btn-success" id="btnSalvarApontamentoManualPagina">
									<i class="la la-check"></i>
									<span class="">Salvar</span>
								</button>
							</div>

						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>


<script>
	document.addEventListener('DOMContentLoaded', function () {
		const form = document.getElementById('formApontamentoManualPagina');
		if (form) {
			form.addEventListener('submit', function () {
				const btn = document.getElementById('btnSalvarApontamentoManualPagina');
				if (btn) {
					btn.disabled = true;
					btn.innerHTML = '<i class="la la-spinner spinner"></i><span class=""> Salvando...</span>';
				}
			});
		}
	});
</script>
@endsection


