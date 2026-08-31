<!DOCTYPE html>
<html>
<head>
	<title></title>
	<link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css" integrity="sha384-oAOxQR6DkCoMliIh8yFnu25d7Eq/PHS21PClpwjOTeU2jRSq11vu66rf90/cZr47" crossorigin="anonymous">
	<link rel="stylesheet" href="/css/materialize.min.css">

</head>
<body>

	<div class="row">
		<div class="col s12">

			@if ($ordem != 'nome')

			   <h3 class="center-align">Relatorio de Produtos {{$ordemt}} Vendidos</h3>


			@else

			   <h3 class="center-align">Relatorio de Venda de Produtos por Ordem de Nome</h3>

			@endif



			@if($data_inicial && $data_final)
			<h4>Periodo: {{$data_inicial}} - {{$data_final}}</h4>
			@endif
		</div>


		<table class="pure-table">
			<thead>
				<tr>
					<th width="30">COD PRODUTO</th>
					<th width="150">PRODUTO</th>
					<th width="50">PRECO UN</th>
					<th width="50">TOTAL QTD</th>
					<th width="50">TOTAL R$</th>
				</tr>
			</thead>



			<tbody>
				@php
					$totalQuantidade = 0;
					$totalValor = 0;
				@endphp
				@foreach($itens as $key => $i)
				@php
					$totalQuantidade += $i['total'];
					$totalValor += $i['total_dinheiro'];
				@endphp
				<tr class="@if($key%2 == 0) pure-table-odd @endif">
					<td>{{$i['id']}}</td>
					<td>{{$i['nome']}}</td>
					<td>{{number_format($i['valor_venda'], 2)}}</td>
					<td>{{number_format($i['total'], 2)}}</td>
					<td>{{number_format($i['total_dinheiro'], 2)}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>

		<div>
			<h4>Total de Quantidade: {{ number_format($totalQuantidade, 2) }}</h4>
			<h4>Total de Valor: {{ number_format($totalValor, 2) }}</h4>
		</div>

	</div>
	<div class="row">
		<canvas id="grafico-vendas" style="width: 100%; margin-left: 100px; margin-top: 20px;"></canvas>
	</div>

</body>
</html>
