<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Vendas</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        h3, h4 {
            text-align: center;
            margin: 10px 0;
        }

        table.pure-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .pure-table-bordered th,
        .pure-table-bordered td {
            border: 1px solid #ccc;
        }

        .total {
            margin-top: 20px;
            font-size: 18px;
        }

        .canvas-container {
            margin-top: 40px;
            margin-left: 20px;
            margin-right: 20px;
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.1/build/pure-min.css">
</head>
<body>

    <div class="header">
        <h3>{{ $fantasia }}</h3>
        <h3>Relatório de Vendas</h3>
        @if($data_inicial && $data_final)
            <h4>Período: {{ $data_inicial }} - {{ $data_final }}</h4>
        @endif
    </div>

    <table class="pure-table pure-table-bordered">
        <thead>
            <tr>
                <th width="150">DATA</th>
                <th width="100">TOTAL</th>
                <th width="100">QTD. VENDAS</th>
                <th width="120">TICKET MÉDIO</th>
            </tr>
        </thead>

        @php $somatotal = 0; $totalVendas = 0; @endphp

        <tbody>
            @foreach($vendas as $key => $v)
            <tr class="@if($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $v['data'] }}</td>
                <td>{{ number_format($v['total'], 2, ',', '.') }}</td>
                <td>{{ $v['total_vendas'] ?? '-' }}</td>
                <td>
                    @if(isset($v['total_vendas']) && $v['total_vendas'] > 0)
                        {{ number_format($v['total'] / $v['total_vendas'], 2, ',', '.') }}
                    @else
                        -
                    @endif
                </td>
                @php
                    $somatotal += $v['total'];
                    $totalVendas += $v['total_vendas'] ?? 0;
                @endphp
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        <p>Total de Vendas: <strong style="color: green">R$ {{ number_format($somatotal, 2, ',', '.') }}</strong></p>

        @if($totalVendas > 0)
            @php $ticket_medio_geral = $somatotal / $totalVendas; @endphp
            <p>Ticket Médio Geral: <strong style="color: blue">R$ {{ number_format($ticket_medio_geral, 2, ',', '.') }}</strong></p>
        @endif
    </div>

    <div class="canvas-container">
        <canvas id="grafico-vendas" style="width: 100%; height: 300px;"></canvas>
    </div>

</body>
</html>
