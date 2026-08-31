<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Apontamento Manual</title>
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css" integrity="sha384-oAOxQR6DkCoMliIh8yFnu25d7Eq/PHS21PClpwjOTeU2jRSq11vu66rf90/cZr47" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/materialize.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .center-align {
            text-align: center;
        }
        .right-align {
            text-align: right;
        }
        .small-column {
            width: 5%;
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        table.pure-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.pure-table th, table.pure-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }
        table.pure-table th {
            background-color: #f2f2f2;
            text-align: left;
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
            <h3 class="center-align">Relatório de Apontamento Manual de Estoque</h3>
            @if($data_inicial && $data_final)
            <h4 class="center-align">Período: {{$data_inicial}} - {{$data_final}}</h4>
            @endif
        </div>

        <div class="table-container">
            <table class="pure-table">
                <thead>
                    <tr>
                        <th class="small-column">ID</th>
                        <th width="15%">Produto</th>
                        <th width="10%">Qtd Compra</th>
                        <th width="10%">Qtd Extorno Venda</th>
                        <th width="10%">Qtd Venda</th>
                        <th width="10%">Qtd Perda</th>
                        <th width="10%">Qtd Ajuste</th>
                        <th width="10%">Saldo</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($result as $key => $r)
                    <tr class="@if($key % 2 == 0) pure-table-odd @endif">
                        <td class="small-column">{{$r->id}}</td>
                        <td>{{$r->produto_nome}}</td>
                        <td class="align-right">{{number_format($r->QtdCompra, 2)}}</td>
                        <td class="align-right">{{number_format($r->QtdExtornoVenda, 2)}}</td>
                        <td class="align-right">{{number_format($r->QtdVenda, 2)}}</td>
                        <td class="align-right">{{number_format($r->QtdPerda, 2)}}</td>
                        <td class="align-right">{{number_format($r->QtdAjuste, 2)}}</td>
                        <td class="align-right">{{number_format(($r->QtdCompra + $r->QtdExtornoVenda + $r->QtdAjuste) - ($r->QtdVenda + $r->QtdPerda), 2)}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
