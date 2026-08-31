@extends('default.layout')
@section('content')
<div class="card card-custom gutter-b">

	<div class="card-body">
		<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
		     <h3 class="card-title">{{isset($produto) ? 'Atualizar Preço' : ''}} Inserir na Lista</h3>
					
		     
			<h5>Produto: <strong class="text-danger">{{{ isset($produto) ? $produto->nome : old('nome') }}}</strong></h5>
            
			
			
			

			<form method="post" action="{{{ isset($produto) ? '/listaDePrecos/salvarPreco': '/listaDePrecos/saveprodutotabela' }}}" enctype="multipart/form-data">
			@if(isset($listaid))
			   <input type="hidden" id = "listaid"  name="listaid" value="{{$listaid}}">
			   <select class="form-control select2" id="kt_select2_1" name="produto">
				   <option>Selecione um produto</option>
				   @foreach($produtos as $p)
			       <option value="{{$p->id}}">{{$p->id}} - {{$p->nome}}</option>
				   @endforeach
			    </select>
			@endif    
			  
				<input type="hidden" name="id" value="{{{ isset($produto) ? $produto->id : old('id') }}}">
				@csrf
				<div class="row">
					<div class="form-group validated col-sm-3 col-lg-3">
						<label class="col-form-label">Valor</label>
						<div class="">
							<input type="text" id="novo_valor" class="form-control @if($errors->has('novo_valor')) is-invalid @endif money" name="novo_valor" value="{{{ isset($produto->valor) ? $produto->valor : old('novo_valor') }}}">
							@if($errors->has('novo_valor'))
							<div class="invalid-feedback">
								{{ $errors->first('novo_valor') }}
							</div>
							@endif
						
						</div>
					</div>
					<div class="form-group validated col-sm-3 col-lg-3">
						<label class="col-form-label">Quantidade Mínima</label>
						<div class="">
							<input type="text" id="quantidade_minima" class="form-control @if($errors->has('quantidade_minima')) is-invalid @endif money" name="quantidade_minima" value="{{{ isset($produto->quantidade_minima) ? $produto->quantidade_minima : old('quantidade_minima') }}}">
							@if($errors->has('quantidade_minima'))
							<div class="invalid-feedback">
								{{ $errors->first('quantidade_minima') }}
							</div>
							@endif
						
						</div>
					</div>

					<div class="form-group validated col-sm-3 col-lg-3">
						<label class="col-form-label">Referência</label>
						<div class="">
							<input type="text" id="referencia" class="form-control @if($errors->has('referencia')) is-invalid @endif " name="referencia" value="{{{ isset($produto->referencia) ? $produto->referencia : old('referencia') }}}">
							@if($errors->has('referencia'))
							<div class="invalid-feedback">
								{{ $errors->first('referencia') }}
							</div>
							@endif
						
						</div>
					</div>
				</div>

				<div class="row">
					<a class="btn btn-light-danger" href="/listaDePrecos">Cancelar</a>
					<input style="margin-left: 5px;" type="submit" value="Salvar" class="btn btn-light-success">
				</div>
			</form>
		</div>
	</div>
</div>

@endsection