<?php

namespace App\Services;

use App\Models\ConfigNota;

class CupomNaoFiscalPdf
{
    protected const PAGE_WIDTH = 164.4;
    protected const PAGE_HEIGHT = 650;
    protected const LEFT = 8;
    protected const RIGHT = 154;

    private $venda;
    private $pathLogo;

    public function __construct($venda, ?string $pathLogo = null)
    {
        $this->venda = $venda;
        $this->pathLogo = $pathLogo;
    }

    public function render(): string
    {
        $dados = $this->dados();
        $logo = $this->logoJpeg();
        $stream = '';

        if ($logo) {
            $stream .= "q\n42 0 0 42 8 592 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco($dados['empresa'], $logo ? 54 : static::LEFT, 628, 5.2, 7);
        $stream .= $this->linha(static::LEFT, 578, static::RIGHT, 578);
        $stream .= $this->textoCentro('CUPOM NAO FISCAL', 568, 7, true);
        $stream .= $this->linha(static::LEFT, 558, static::RIGHT, 558);

        $y = 543;
        $stream .= $this->texto('DESCRICAO', static::LEFT, $y, 6, true);
        $stream .= $this->textoDireita('TOTAL', static::RIGHT, $y, 6, true);
        $y -= 12;

        foreach ($dados['itens'] as $item) {
            foreach ($this->quebrarLinha($item['nome'], 24) as $idx => $linha) {
                $stream .= $this->texto($linha, static::LEFT, $y, 6, $idx === 0);
                $y -= 9;
            }

            $stream .= $this->texto($item['detalhe'], static::LEFT, $y, 5.7);
            $stream .= $this->textoDireita($item['total'], static::RIGHT, $y, 5.7, true);
            $y -= 12;
        }

        $stream .= $this->linha(static::LEFT, $y + 4, static::RIGHT, $y + 4);
        $stream .= $this->texto('Qtd. Total de Itens', static::LEFT, $y - 8, 6, true);
        $stream .= $this->textoDireita($dados['qtd_itens'], static::RIGHT, $y - 8, 6, true);
        $stream .= $this->texto('Total de Produtos', static::LEFT, $y - 18, 6, true);
        $stream .= $this->textoDireita($dados['total_produtos'], static::RIGHT, $y - 18, 6, true);
        $stream .= $this->texto('TOTAL', static::LEFT, $y - 30, 7, true);
        $stream .= $this->textoDireita($dados['total'], static::RIGHT, $y - 30, 7, true);
        $stream .= $this->linha(static::LEFT, $y - 38, static::RIGHT, $y - 38);
        $stream .= $this->texto('Pagamento', static::LEFT, $y - 51, 5.8, true);
        $stream .= $this->textoDireita($dados['pagamento'], static::RIGHT, $y - 51, 5.8);
        $stream .= $this->texto('Data', static::LEFT, $y - 61, 5.8, true);
        $stream .= $this->textoDireita($dados['data'], static::RIGHT, $y - 61, 5.8);

        return $this->pdf($stream, $logo);
    }

    private function dados(): array
    {
        $config = ConfigNota::first();
        $itens = $this->venda->itens()->with('produto')->get();
        $totalProdutos = 0;
        $qtdItens = 0;
        $linhasEmpresa = [];

        if ($config) {
            $linhasEmpresa = array_filter([
                $this->textoLimpo($config->razao_social),
                'CNPJ: ' . $this->textoLimpo($config->cnpj),
                'IE: ' . $this->textoLimpo($config->ie),
                $this->textoLimpo(trim(($config->logradouro ?? '') . ', ' . ($config->numero ?? ''))),
                $this->textoLimpo(trim(($config->bairro ?? '') . ' ' . ($config->municipio ?? '') . '-' . ($config->UF ?? ''))),
                $config->cep ? 'CEP: ' . $this->textoLimpo($config->cep) : null,
            ]);
        }

        $linhasItens = [];
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

            $linhasItens[] = [
                'nome' => $this->textoLimpo($nome),
                'detalhe' => number_format($quantidade, 2, ',', '.') . ' x R$ ' . number_format($valor, 2, ',', '.'),
                'total' => 'R$ ' . number_format($total, 2, ',', '.'),
            ];
        }

        return [
            'empresa' => $linhasEmpresa ?: ['CUPOM NAO FISCAL'],
            'itens' => $linhasItens,
            'qtd_itens' => number_format($qtdItens, 0, ',', '.'),
            'total_produtos' => 'R$ ' . number_format($totalProdutos, 2, ',', '.'),
            'total' => 'R$ ' . number_format((float) $this->venda->valor_total, 2, ',', '.'),
            'pagamento' => $this->textoLimpo($this->descricaoPagamento()),
            'data' => $this->formatarData($this->venda->created_at ?? $this->venda->data_registro ?? now()),
        ];
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

    protected function formatarData($data): string
    {
        try {
            return \Carbon\Carbon::parse($data)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return date('d/m/Y H:i:s');
        }
    }

    protected function textoBloco(array $linhas, float $x, float $y, float $fonte, float $altura): string
    {
        $stream = '';
        foreach ($linhas as $linha) {
            $stream .= $this->texto($linha, $x, $y, $fonte, true);
            $y -= $altura;
        }

        return $stream;
    }

    protected function texto(string $texto, float $x, float $y, float $fonte = 7, bool $bold = false): string
    {
        $font = $bold ? 'F2' : 'F1';
        return "BT\n/{$font} {$fonte} Tf\n{$x} {$y} Td\n(" . $this->pdfText($texto, 60) . ") Tj\nET\n";
    }

    protected function textoCentro(string $texto, float $y, float $fonte = 7, bool $bold = false): string
    {
        $largura = $this->larguraTexto($texto, $fonte);
        return $this->texto($texto, max(static::LEFT, (static::PAGE_WIDTH - $largura) / 2), $y, $fonte, $bold);
    }

    protected function textoDireita(string $texto, float $xDireita, float $y, float $fonte = 7, bool $bold = false): string
    {
        return $this->texto($texto, max(static::LEFT, $xDireita - $this->larguraTexto($texto, $fonte)), $y, $fonte, $bold);
    }

    protected function larguraTexto(string $texto, float $fonte): float
    {
        return strlen($this->textoLimpo($texto)) * $fonte * 0.44;
    }

    protected function linha(float $x1, float $y1, float $x2, float $y2): string
    {
        return "0.4 w\n{$x1} {$y1} m\n{$x2} {$y2} l\nS\n";
    }

    protected function quebrarLinha(string $texto, int $limite): array
    {
        $palavras = explode(' ', $texto);
        $linhas = [];
        $linha = '';

        foreach ($palavras as $palavra) {
            if (strlen(trim($linha . ' ' . $palavra)) > $limite && $linha !== '') {
                $linhas[] = $linha;
                $linha = $palavra;
            } else {
                $linha = trim($linha . ' ' . $palavra);
            }
        }

        if ($linha !== '') {
            $linhas[] = $linha;
        }

        return $linhas ?: [''];
    }

    protected function textoLimpo($texto): string
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
            'Ã¡' => 'a', 'Ã£' => 'a', 'Ã©' => 'e', 'Ãª' => 'e', 'Ã­' => 'i',
            'Ã³' => 'o', 'Ãµ' => 'o', 'Ãº' => 'u', 'Ã§' => 'c',
        ];
        $texto = strtr($texto, $mapa);

        return preg_replace('/[^\x20-\x7E]/', '', $texto);
    }

    protected function pdfText(string $texto, int $limite): string
    {
        $texto = substr($this->textoLimpo($texto), 0, $limite);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    protected function logoJpeg(): ?array
    {
        if (!$this->pathLogo || preg_match('/^[a-z]+:\/\//i', $this->pathLogo) || !is_file($this->pathLogo)) {
            return null;
        }

        $info = @getimagesize($this->pathLogo);
        if (!$info || ($info[2] ?? null) !== IMAGETYPE_JPEG) {
            return null;
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'data' => file_get_contents($this->pathLogo),
        ];
    }

    protected function pdf(string $stream, ?array $logo): string
    {
        $objects = [];
        $xObject = $logo ? ' /XObject << /Im1 6 0 R >>' : '';

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . static::PAGE_WIDTH . " " . static::PAGE_HEIGHT . "] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>{$xObject} >> /Contents " . ($logo ? '7' : '6') . " 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        if ($logo) {
            $objects[] = "<< /Type /XObject /Subtype /Image /Width {$logo['width']} /Height {$logo['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo['data']) . " >>\nstream\n" . $logo['data'] . "\nendstream";
        }

        $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

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
