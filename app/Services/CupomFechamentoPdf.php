<?php

namespace App\Services;

use App\Models\VendaCaixa;
use App\Models\ConfigNota;

class CupomFechamentoPdf extends CupomNaoFiscalPdf
{
    protected const PAGE_WIDTH = 250;
    protected const PAGE_HEIGHT = 650;
    protected const LEFT = 8;
    protected const RIGHT = 238;

    private $somaTiposPagamento;
    private $somaMultFormas;
    private $dadosFechamento;
    private $sangrias;
    private $dataInicial;
    private $dataFinal;

    public function __construct($somaTiposPagamento, $somaMultFormas, $dadosFechamento, $sangrias, ?string $pathLogo = null, ?string $dataInicial = null, ?string $dataFinal = null)
    {
        $this->somaTiposPagamento = $somaTiposPagamento;
        $this->somaMultFormas = $somaMultFormas;
        $this->dadosFechamento = $dadosFechamento;
        $this->sangrias = $sangrias;
        $this->dataInicial = $dataInicial;
        $this->dataFinal = $dataFinal;
        parent::__construct((object) ['itens' => collect(), 'valor_total' => 0], $pathLogo);
    }

    public function render(): string
    {
        $dados = $this->dadosFechamento();
        $logo = $this->logoJpeg();
        $stream = '';

        if ($logo) {
            $stream .= "q\n70 0 0 52 8 580 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco($dados['empresa'], $logo ? 88 : self::LEFT, 635, 6.2, 7.2);

        $y = 548;
        $stream .= $this->linhaTabela('Recebimentos por Forma de Pagamento', '', $y);
        $y -= 17;
        $stream .= $this->linhaTabela('Periodo:', $dados['periodo'], $y);
        $y -= 28;

        foreach ($dados['pagamentos'] as $pagamento) {
            $stream .= $this->linhaTabela($pagamento['tipo'], $pagamento['valor'], $y);
            $y -= 17;
        }

        $y -= 8;
        $stream .= $this->linha(self::LEFT, $y + 8, self::RIGHT, $y + 8);
        $stream .= $this->linhaTabela('TOTAL RECEBIDO', $dados['total_pagamentos'], $y);

        return $this->pdf($stream, $logo);
    }

    private function dadosFechamento(): array
    {
        $pagamentosPorTipo = [];
        $totalPagamentos = 0;
        $dinheiroVendas = 0;

        foreach ($this->somaTiposPagamento as $item) {
            $valor = (float) ($item->total ?? 0);
            $tipo = $item->tipo_pagamento ?? '';
            $this->somarPagamento($pagamentosPorTipo, $tipo, $valor, $totalPagamentos, $dinheiroVendas);
        }

        foreach ($this->somaMultFormas as $item) {
            for ($i = 1; $i <= 3; $i++) {
                $valorCampo = 'valor_pagamento_' . $i;
                $tipoCampo = 'tipo_pagamento_' . $i;
                $valor = (float) ($item->{$valorCampo} ?? 0);
                if ($valor <= 0) {
                    continue;
                }

                $this->somarPagamento($pagamentosPorTipo, $item->{$tipoCampo} ?? '', $valor, $totalPagamentos, $dinheiroVendas);
            }
        }

        $pagamentos = [];
        foreach (VendaCaixa::tiposPagamento() as $tipo => $descricao) {
            if (!isset($pagamentosPorTipo[$tipo])) {
                continue;
            }

            $pagamentos[] = [
                'tipo' => $this->textoLimpo($descricao),
                'valor' => $this->dinheiro($pagamentosPorTipo[$tipo]),
            ];
        }

        return [
            'empresa' => $this->empresa(),
            'periodo' => $this->periodoVerificado(),
            'pagamentos' => $pagamentos ?: [['tipo' => 'Sem recebimentos', 'valor' => $this->dinheiro(0)]],
            'total_pagamentos' => $this->dinheiro($totalPagamentos),
        ];
    }

    private function periodoVerificado(): string
    {
        if ($this->dataInicial || $this->dataFinal) {
            $inicio = $this->dataInicial ? $this->formatarDataCurta($this->dataInicial) : '';
            $fim = $this->dataFinal ? $this->formatarDataCurta($this->dataFinal) : '';
            return trim($inicio . ' ate ' . $fim);
        }

        $inicio = $this->formatarData($this->dadosFechamento->data_registro ?? $this->dadosFechamento->created_at ?? null);
        $fim = $this->formatarData($this->dadosFechamento->updated_at ?? null);
        return $inicio . ' ate ' . $fim;
    }

    private function formatarDataCurta($data): string
    {
        try {
            return \Carbon\Carbon::parse($data)->format('d/m/Y');
        } catch (\Throwable $e) {
            return date('d/m/Y');
        }
    }

    private function somarPagamento(array &$pagamentosPorTipo, $tipo, float $valor, float &$totalPagamentos, float &$dinheiroVendas): void
    {
        $tipo = trim((string) $tipo);
        if ($tipo === '' || $valor <= 0) {
            return;
        }

        $tipo = str_pad($tipo, 2, '0', STR_PAD_LEFT);

        if (!isset($pagamentosPorTipo[$tipo])) {
            $pagamentosPorTipo[$tipo] = 0;
        }

        $pagamentosPorTipo[$tipo] += $valor;
        $totalPagamentos += $valor;

        if ($tipo === '01') {
            $dinheiroVendas += $valor;
        }
    }

    private function linhaTabela(string $label, string $valor, float $y): string
    {
        $fonteLabel = strlen($this->textoLimpo($label)) > 30 ? 6.8 : 8.2;
        $fonteValor = strlen($this->textoLimpo($valor)) > 24 ? 6.4 : 8.2;
        $stream = $this->texto($this->limitarTexto($label, 44), self::LEFT, $y, $fonteLabel, true);
        $stream .= $this->textoDireita($this->limitarTexto($valor, 34), self::RIGHT, $y, $fonteValor, true);
        return $stream;
    }

    private function limitarTexto(string $texto, int $limite): string
    {
        $texto = $this->textoLimpo($texto);
        if (strlen($texto) <= $limite) {
            return $texto;
        }

        return substr($texto, 0, $limite);
    }

    private function retangulo(float $x, float $y, float $w, float $h): string
    {
        return "0.4 w\n{$x} {$y} {$w} {$h} re\nS\n";
    }

    private function empresa(): array
    {
        $config = ConfigNota::first();
        if (!$config) {
            return ['FECHAMENTO DE CAIXA'];
        }

        return array_filter([
            $this->textoLimpo($config->razao_social),
            'CNPJ:' . $this->textoLimpo($config->cnpj),
            'IE:' . $this->textoLimpo($config->ie),
            $this->textoLimpo(trim(($config->logradouro ?? '') . ' ' . ($config->numero ?? ''))),
            $this->textoLimpo(trim(($config->bairro ?? '') . '. CEP:' . ($config->cep ?? ''))),
            $this->textoLimpo(trim(($config->municipio ?? '') . '-' . ($config->UF ?? '') . ' ' . ($config->fone ?? ''))),
        ]);
    }

    private function ehDinheiro($tipo): bool
    {
        $tipo = strtoupper($this->textoLimpo((string) $tipo));
        return strpos($tipo, 'DINHEIRO') !== false;
    }

    private function dinheiro(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}
