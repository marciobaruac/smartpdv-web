<?php

namespace App\Services;

use App\Models\ConfigNota;

class CupomNaoFiscalPdf
{
    private $venda;

    public function __construct($venda, ?string $pathLogo = null)
    {
        $this->venda = $venda;
    }

    public function render(): string
    {
        $linhas = $this->linhas();
        $stream = "BT\n/F1 7 Tf\n12 630 Td\n10 TL\n";

        foreach ($linhas as $linha) {
            $stream .= '(' . $this->pdfText($linha) . ") Tj\nT*\n";
        }

        $stream .= "ET";

        return $this->pdf([$this->page($stream)]);
    }

    private function linhas(): array
    {
        $config = ConfigNota::first();
        $itens = $this->venda->itens()->with('produto')->get();
        $totalProdutos = 0;
        $qtdItens = 0;

        $linhas = [];

        if ($config) {
            $linhas[] = $this->texto($config->razao_social);
            $linhas[] = 'CNPJ:' . $this->texto($config->cnpj);
            $linhas[] = 'IE:' . $this->texto($config->ie);
            $linhas[] = $this->texto(trim(($config->logradouro ?? '') . ', ' . ($config->numero ?? '')));
            $linhas[] = $this->texto(trim(($config->bairro ?? '') . ' ' . ($config->municipio ?? '') . '-' . ($config->UF ?? '')));
            if ($config->cep) {
                $linhas[] = 'CEP:' . $this->texto($config->cep);
            }
        }

        $linhas[] = '';
        $linhas[] = 'CUPOM NAO FISCAL';
        $linhas[] = '';
        $linhas[] = 'DESCRICAO';

        foreach ($itens as $item) {
            $quantidade = (float) $item->quantidade;
            $valor = (float) $item->valor;
            $total = $quantidade * $valor;
            $nome = $item->produto->nome ?? 'Produto';

            if (!empty($item->observacao)) {
                $nome .= ' obs: ' . $item->observacao;
            }

            $qtdItens += $quantidade;
            $totalProdutos += $total;

            $linhas[] = $this->texto(number_format($quantidade, 2, ',', '.') . ' x ' . $nome);
            $linhas[] = 'R$ ' . number_format($valor, 2, ',', '.') . ' | R$ ' . number_format($total, 2, ',', '.');
        }

        $linhas[] = '';
        $linhas[] = 'Qtd. Total de Itens: ' . number_format($qtdItens, 0, ',', '.');
        $linhas[] = 'Total de Produtos: R$ ' . number_format($totalProdutos, 2, ',', '.');
        $linhas[] = 'Total: R$ ' . number_format((float) $this->venda->valor_total, 2, ',', '.');
        $linhas[] = $this->texto($this->descricaoPagamento());
        $linhas[] = 'Data: ' . $this->formatarData($this->venda->created_at ?? $this->venda->data_registro ?? now());

        return $linhas;
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

    private function texto($texto): string
    {
        $texto = (string) $texto;
        $mapa = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A',
            'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Õ' => 'O',
            'Ô' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ];

        return strtr($texto, $mapa);
    }

    private function pdfText(string $texto): string
    {
        $texto = substr($this->texto($texto), 0, 42);
        $texto = preg_replace('/[^\x20-\x7E]/', '', $texto);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function page(string $stream): string
    {
        return "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    private function pdf(array $streams): string
    {
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 226.77 650] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = $streams[0];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }
}
