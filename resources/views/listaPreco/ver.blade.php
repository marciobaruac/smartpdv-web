@extends('default.layout')
@section('content')

<div class="card card-custom gutter-b">
	<div class="card-body">
		<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
		   <h4>Lista de Preço <strong class="text-primary">{{$lista->nome}}</strong></h4>
			<h4>Percentual de alteração: <strong class="text-danger">{{$lista->percentual_alteracao}}%</strong></h4>
			<h5>Total de produtos cadastrados no sistema: <strong class="text-danger">{{sizeof($produtos)}}</strong></h5>

			<a style="margin-left: 5px; margin-top: 5px;" href="/listaDePrecos/newprodutotabela/{{$lista->id}}" class="btn btn-lg btn-success">
			   <i class="fa fa-plus"></i>Insere Tabela
			</a>

			@if(sizeof($lista->itens) > 0)

			<!-- Campo de pesquisa -->
			<div class="form-group mt-5">
				<input type="text" id="searchInput" class="form-control" placeholder="🔍 Pesquisar produto...">
			</div>

			<div class="row">
				<div class="col-sm-12 col-lg-12 col-md-12 col-xl-12">
					<div class="row">
						<div class="col-xl-12">

							<div id="kt_datatable" class="datatable datatable-bordered datatable-head-custom datatable-default datatable-primary datatable-loaded">

								<table class="datatable-table" style="max-width: 100%; overflow: scroll">
									<thead class="datatable-head">
										<tr class="datatable-row">
											<th class="datatable-cell"><span style="width: 300px;">Produto</span></th>
											<th class="datatable-cell"><span style="width: 100px;">Valor venda padrão</span></th>
											<th class="datatable-cell"><span style="width: 100px;">Valor de compra</span></th>
											<th class="datatable-cell"><span style="width: 100px;">Valor venda da lista</span></th>
											<th class="datatable-cell"><span style="width: 100px;">Margem sobre Custo</span></th>
											<th class="datatable-cell"><span style="width: 100px;">Quantidade Minima</span></th>
											<th class="datatable-cell"><span style="width: 120px;">Ações</span></th>
										</tr>
									</thead>

									<tbody id="body" class="datatable-body">
										@foreach($lista->itens as $i)
											@if(isset($i->produto))
												<tr class="datatable-row">
													<td class="datatable-cell">
														<span style="width: 300px;">{{$i->produto->nome}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 100px;">{{number_format($i->produto->valor_venda, 2)}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 100px;">{{number_format($i->produto->valor_compra, 2)}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 100px;">{{number_format($i->valor, 2)}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 100px;">{{number_format($i->percentual_lucro, 2)}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 100px;">{{number_format($i->quantidade_minima, 2)}}</span>
													</td>
													<td class="datatable-cell">
														<span style="width: 120px;">
															<a class="btn btn-light-primary" href="/listaDePrecos/editValor/{{ $i->id }}">
																<i class="la la-edit"></i>
															</a>
														</span>
													</td>
												</tr>
											@endif
										@endforeach
									</tbody>
								</table>

							</div>
						</div>
					</div>
				</div>
			</div>

			@else
			<h5 class="center-align text-danger">
				Esta lista ainda não tem produtos cadastrados
				<a class="btn btn-light-success" href="/listaDePrecos/gerar/{{$lista->id}}">Gerar Lista de Produtos</a>
			</h5>
			@endif
		</div>
	</div>
</div>

<!-- Script para pesquisa na tabela -->
<script>
	document.getElementById('searchInput').addEventListener('keyup', function() {
		var input = this.value.toLowerCase();
		var rows = document.querySelectorAll('#body tr');

		rows.forEach(function(row) {
			var produto = row.querySelector('td span').textContent.toLowerCase();

			if(produto.includes(input)) {
				row.style.display = '';
			} else {
				row.style.display = 'none';
			}
		});
	});
</script>

@endsection
