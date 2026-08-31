<?php

namespace App\Services;

use App\Models\ConfigNota;
use Dompdf\Dompdf;
use Dompdf\Options;

class CupomNaoFiscalPdf
{
    private $venda;
    private $pathLogo;

    public function __construct($venda, ?string $pathLogo = null)
    {
        $this->venda = $venda;
        $this->pathLogo = $pathLogo;
    }

    public function render(): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html(), 'UTF-8');
        $dompdf->setPaper([0, 0, 226.77, 650], 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function html(): string
    {
        $config = ConfigNota::first();
        $itens = $this->venda->itens()->with('produto')->get();
        $qtdItens = $itens->sum(function ($item) {
            return (float) $item->quantidade;
        });
        $totalProdutos = $itens->sum(function ($item) {
            return (float) $item->quantidade * (float) $item->valor;
        });

        $logo = $this->logoBase64();
        $empresa = $this->empresaHtml($config);
        $linhas = $itens->map(function ($item) {
            $quantidade = (float) $item->quantidade;
            $valor = (float) $item->valor;
            $total = $quantidade * $valor;
            $nome = $item->produto->nome ?? 'Produto';

            if (!empty($item->observacao)) {
                $nome .= ' obs: ' . $item->observacao;
            }

            return '<tr><td colspan="3">'
                . e(number_format($quantidade, 2, ',', '.'))
                . ' x '
                . e($nome)
                . ' | R$ '
                . e(number_format($valor, 2, ',', '.'))
                . ' | R$ '
                . e(number_format($total, 2, ',', '.'))
                . '</td></tr>';
        })->implode('');

        $pagamento = $this->descricaoPagamento();
        $data = $this->formatarData($this->venda->created_at ?? $this->venda->data_registro ?? now());

        return '<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #000; margin: 0; }
        .cupom { width: 100%; padding: 8px 10px; }
        .topo { display: table; width: 100%; margin-bottom: 8px; }
        .logo { display: table-cell; width: 58px; vertical-align: top; }
        .logo img { max-width: 52px; max-height: 52px; }
        .empresa { display: table-cell; vertical-align: top; font-size: 9px; line-height: 1.05; }
        h1 { font-size: 10px; text-align: center; margin: 9px 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; padding-bottom: 6px; }
        td { padding: 2px 0; font-size: 9px; line-height: 1.25; }
        .totais { margin-top: 14px; }
        .totais td:first-child { font-weight: bold; }
        .totais td:last-child { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="cupom">
        <div class="topo">
            <div class="logo">' . ($logo ? '<img src="' . $logo . '">' : '') . '</div>
            <div class="empresa">' . $empresa . '</div>
        </div>
        <h1>CUPOM NAO FISCAL</h1>
        <table>
            <thead><tr><th>DESCRICAO</th><th>QT</th><th>TOT</th></tr></thead>
            <tbody>' . $linhas . '</tbody>
        </table>
        <table class="totais">
            <tr><td>Qtd. Total de Itens</td><td>' . e(number_format($qtdItens, 0, ',', '.')) . '</td></tr>
            <tr><td>Total de Produtos</td><td>' . e(number_format($totalProdutos, 2, ',', '.')) . '</td></tr>
            <tr><td>Total</td><td>R$ ' . e(number_format((float) $this->venda->valor_total, 2, ',', '.')) . '</td></tr>
            <tr><td>' . e($pagamento) . '</td><td></td></tr>
            <tr><td>Data</td><td>' . e($data) . '</td></tr>
        </table>
    </div>
</body>
</html>';
    }

    private function empresaHtml(?ConfigNota $config): string
    {
        if (!$config) {
            return 'CUPOM NAO FISCAL';
        }

        $endereco = trim(($config->logradouro ?? '') . ', ' . ($config->numero ?? ''));
        $bairro = trim(($config->bairro ?? '') . ' ' . ($config->municipio ?? '') . '-' . ($config->UF ?? ''));
        $linhas = [
            $config->razao_social,
            'CNPJ:' . $config->cnpj,
            'IE:' . $config->ie,
            $endereco,
            $bairro . ' CEP:' . ($config->cep ?? ''),
            $config->fone ? 'FONE:' . $config->fone : null,
        ];

        return collect($linhas)
            ->filter()
            ->map(function ($linha) {
                return e($linha);
            })
            ->implode('<br>');
    }

    private function descricaoPagamento(): string
    {
        if (($this->venda->tipo_pagamento ?? null) === '99' && method_exists($this->venda, 'multiplo')) {
            return $this->venda->multiplo();
        }

        if (method_exists(get_class($this->venda), 'getTipoPagamento')) {
            return get_class($this->venda)::getTipoPagamento($this->venda->tipo_pagamento);
        }

        return $this->venda->forma_pagamento ?: 'Pagamento';
    }

    private function formatarData($data): string
    {
        try {
            return \Carbon\Carbon::parse($data)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return date('d/m/Y H:i:s');
        }
    }

    private function logoBase64(): ?string
    {
        if (!$this->pathLogo || !file_exists($this->pathLogo)) {
            return null;
        }

        $conteudo = file_get_contents($this->pathLogo);
        $mime = function_exists('mime_content_type') ? mime_content_type($this->pathLogo) : 'image/jpeg';

        return 'data:' . ($mime ?: 'image/jpeg') . ';base64,' . base64_encode($conteudo);
    }
}
