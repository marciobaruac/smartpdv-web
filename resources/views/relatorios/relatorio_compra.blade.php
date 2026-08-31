<!DOCTYPE html>
<html>
<head>
    <title></title>
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css" integrity="sha384-oAOxQR6DkCoMliIh8yFnu25d7Eq/PHS21PClpwjOTeU2jRSq11vu66rf90/cZr47" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/materialize.min.css">
    <style>
        .right-align {
            text-align: right;
        }
        .small-column {
            width: 10%;
        }
        .align-right {
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="row">
        <div class="col s12">
            <h3 class="center-align">{{$fantasia}}</h3>

            <h3 class="center-align">Relatório de Compras</h3>
            @if($data_inicial && $data_final)
            <h4>Período: {{$data_inicial}} - {{$data_final}}</h4>
            @endif
        </div>

        <table class="pure-table">
            <thead>
                <tr>
                    <th class="small-column">ID</th> <!-- Set smaller size for ID column -->
                    <th width="15%">Data</th>
                    <th width="40%">Fornecedor</th>
                    <th width="20%" class="align-right">Total R$</th>
                </tr>
            </thead>

            <tbody>
                @php
                    $total_compras = 0;
                @endphp
                @foreach($compras as $key => $c)
                @php
                    $total_compras += $c->total;
                @endphp
                <tr class="@if($key % 2 == 0) pure-table-odd @endif">
                    <td class="small-column">{{$c->id}}</td> <!-- Set smaller size for ID column -->
                    <td width="15%">{{$c->data}}</td> <!-- Purchase Date -->
                    <td width="40%">{{$c->razao_social}}</td> <!-- Supplier's name -->
                    <td width="20%" class="align-right">{{number_format($c->total, 2)}}</td> <!-- Align content to the right -->
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="col s12"> <!-- Add right-align class here -->
            <h5 class="right-align">Totalizador</h5>
            <p class="right-align"><strong>Total R$:</strong> {{ number_format($total_compras, 2) }}</p>
        </div>
    </div>

    <div class="row">
        <canvas id="grafico-vendas" style="width: 100%; margin-left: 100px; margin-top: 20px;"></canvas>
    </div>

</body>
</html>
