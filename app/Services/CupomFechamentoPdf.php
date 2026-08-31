<?php

namespace App\Services;

use App\Models\VendaCaixa;

class CupomFechamentoPdf extends CupomNaoFiscalPdf
{
    private const PAGE_WIDTH = 164.4;
    private const PAGE_HEIGHT = 650;
    private const LEFT = 8;
    private const RIGHT = 154;

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
            $stream .= "q\n38 0 0 38 8 592 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco(['FECHAMENTO DE CAIXA'], $logo ? 54 : self::LEFT, 625, 7, 8);
        $stream .= $this->linha(self::LEFT, 578, self::RIGHT, 578);

        $y = 565;
        foreach ($dados['cabecalho'] as $linha) {
            $stream .= $this->texto($linha, self::LEFT, $y, 5.8, true);
            $y -= 9;
        }

        $stream .= $this->linha(self::LEFT, $y, self::RIGHT, $y);
        $y -= 12;
        $stream .= $this->texto('FORMA DE PAGAMENTO', self::LEFT, $y, 5.8, true);
        $stream .= $this->textoDireita('VALOR', self::RIGHT, $y, 5.8, true);
        $y -= 10;

        foreach ($dados['pagamentos'] as $pagamento) {
            $stream .= $this->texto($pagamento['tipo'], self::LEFT, $y, 5.6);
            $stream .= $this->textoDireita($pagamento['valor'], self::RIGHT, $y, 5.6, true);
            $y -= 9;
        }

        $stream .= $this->linha(self::LEFT, $y + 2, self::RIGHT, $y + 2);
        $stream .= $this->texto('TOTAL RECEBIDO', self::LEFT, $y - 9, 6.5, true);
        $stream .= $this->textoDireita($dados['total_pagamentos'], self::RIGHT, $y - 9, 6.5, true);
        $y -= 24;

        if (count($dados['sangrias']) > 0) {
            $stream .= $this->texto('SANGRIAS', self::LEFT, $y, 5.8, true);
            $stream .= $this->textoDireita('VALOR', self::RIGHT, $y, 5.8, true);
            $y -= 10;

            foreach ($dados['sangrias'] as $sangria) {
                foreach ($this->quebrarLinha($sangria['descricao'], 24) as $linha) {
                    $stream .= $this->texto($linha, self::LEFT, $y, 5.4);
                    $y -= 8;
                }
                $stream .= $this->textoDireita($sangria['valor'], self::RIGHT, $y + 8, 5.4, true);
            }

            $stream .= $this->linha(self::LEFT, $y + 2, self::RIGHT, $y + 2);
            $stream .= $this->texto('TOTAL SANGRIAS', self::LEFT, $y - 9, 6, true);
            $stream .= $this->textoDireita($dados['total_sangrias'], self::RIGHT, $y - 9, 6, true);
            $y -= 24;
        }

        $stream .= $this->linha(self::LEFT, $y + 2, self::RIGHT, $y + 2);
        $stream .= $this->texto('SALDO ABERTURA', self::LEFT, $y - 9, 5.8, true);
        $stream .= $this->textoDireita($dados['saldo_abertura'], self::RIGHT, $y - 9, 5.8, true);
        $stream .= $this->texto('DINHEIRO VENDAS', self::LEFT, $y - 20, 5.8, true);
        $stream .= $this->textoDireita($dados['dinheiro_vendas'], self::RIGHT, $y - 20, 5.8, true);
        $stream .= $this->texto('DINHEIRO ESPERADO', self::LEFT, $y - 31, 6.2, true);
        $stream .= $this->textoDireita($dados['dinheiro_esperado'], self::RIGHT, $y - 31, 6.2, true);
        $y -= 45;

        $stream .= $this->texto('SALDO INFORMADO', self::LEFT, $y, 6, true);
        $stream .= $this->textoDireita($dados['saldo_informado'], self::RIGHT, $y, 6, true);
        $y -= 12;
        $stream .= $this->texto($dados['resultado_label'], self::LEFT, $y, 6.5, true);
        $stream .= $this->textoDireita($dados['resultado_valor'], self::RIGHT, $y, 6.5, true);
        $y -= 14;
        $stream .= $this->texto('DATA IMPRESSAO', self::LEFT, $y, 5.4);
        $stream .= $this->textoDireita(date('d/m/Y H:i:s'), self::RIGHT, $y, 5.4);

        return $this->pdf($stream, $logo);
    }

    private function dadosFechamento(): array
    {
        $pagamentos = [];
        $totalPagamentos = 0;
        $dinheiroVendas = 0;

        foreach ($this->somaTiposPagamento as $item) {
            $valor = (float) ($item->total ?? 0);
            $tipo = $item->tipo_pagamento ?? '';
            $totalPagamentos += $valor;
            if ($tipo === '01') {
                $dinheiroVendas += $valor;
            }
            $pagamentos[] = [
                'tipo' => $this->textoLimpo(VendaCaixa::getTipoPagamento($tipo)),
                'valor' => $this->dinheiro($valor),
            ];
        }

        foreach ($this->somaMultFormas as $item) {
            for ($i = 1; $i <= 3; $i++) {
                $valorCampo = 'valor_pagamento_' . $i;
                $tipoCampo = 'tipo_pagamento_' . $i;
                $valor = (float) ($item->{$valorCampo} ?? 0);
                if ($valor <= 0) {
                    continue;
                }

                $totalPagamentos += $valor;
                if ($this->ehDinheiro($item->{$tipoCampo} ?? '')) {
                    $dinheiroVendas += $valor;
                }
                $pagamentos[] = [
                    'tipo' => $this->textoLimpo((string) ($item->{$tipoCampo} ?? 'Pagamento')),
                    'valor' => $this->dinheiro($valor),
                ];
            }
        }

        $sangrias = [];
        $totalSangrias = 0;
        foreach ($this->sangrias as $sangria) {
            $valor = (float) ($sangria->valor ?? 0);
            $totalSangrias += $valor;
            $sangrias[] = [
                'descricao' => $this->textoLimpo((string) ($sangria->observacao ?? $sangria->descricao ?? 'Sangria')),
                'valor' => $this->dinheiro($valor),
            ];
        }

        $saldoAbertura = (float) ($this->dadosFechamento->valor ?? 0);
        $saldoInformado = (float) ($this->dadosFechamento->saldo_informado_fechamento ?? 0);
        $dinheiroEsperado = $saldoAbertura + $dinheiroVendas - $totalSangrias;
        $diferenca = $saldoInformado - $dinheiroEsperado;
        $resultadoLabel = 'SEM DIFERENCA';
        if ($diferenca > 0) {
            $resultadoLabel = 'SOBRA';
        } elseif ($diferenca < 0) {
            $resultadoLabel = 'FALTA';
        }

        return [
            'cabecalho' => array_filter([
                'Abertura: ' . $this->formatarData($this->dadosFechamento->data_registro ?? $this->dadosFechamento->created_at ?? null),
                'Fechamento: ' . $this->formatarData($this->dadosFechamento->updated_at ?? null),
                $this->dataInicial && $this->dataFinal ? 'Periodo: ' . $this->dataInicial . ' ate ' . $this->dataFinal : null,
            ]),
            'pagamentos' => $pagamentos ?: [['tipo' => 'Sem vendas', 'valor' => $this->dinheiro(0)]],
            'total_pagamentos' => $this->dinheiro($totalPagamentos),
            'sangrias' => $sangrias,
            'total_sangrias' => $this->dinheiro($totalSangrias),
            'saldo_abertura' => $this->dinheiro($saldoAbertura),
            'dinheiro_vendas' => $this->dinheiro($dinheiroVendas),
            'dinheiro_esperado' => $this->dinheiro($dinheiroEsperado),
            'saldo_informado' => $this->dinheiro($saldoInformado),
            'resultado_label' => $resultadoLabel,
            'resultado_valor' => $this->dinheiro(abs($diferenca)),
        ];
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
