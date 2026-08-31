<?php

namespace App\Services;

use App\Models\VendaCaixa;
use App\Models\ConfigNota;

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
            $stream .= "q\n45 0 0 45 8 592 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco($dados['empresa'], $logo ? 62 : self::LEFT, 635, 5.2, 6.4);

        $y = 540;
        $stream .= $this->linhaTabela('Caixa no Periodo', '', $y);
        $y -= 13;
        $stream .= $this->linhaTabela('Operador:', $dados['operador'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('Abertura:', $dados['abertura'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('Fechamento:', $dados['fechamento'], $y);
        $y -= 20;

        foreach ($dados['pagamentos'] as $pagamento) {
            $stream .= $this->linhaTabela($pagamento['tipo'], $pagamento['valor'], $y);
            $y -= 13;
        }

        $stream .= $this->linhaTabela('TOTAL', $dados['total_pagamentos'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('(-) Sangria', $dados['total_sangrias'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('TOTAL GERAL', $dados['total_geral'], $y);
        $y -= 28;

        if (count($dados['sangrias']) > 0) {
            foreach ($dados['sangrias'] as $sangria) {
                $stream .= $this->linhaTabela($sangria['descricao'], $sangria['valor'], $y);
                $y -= 13;
            }
            $y -= 10;
        }

        $stream .= $this->textoCentro('==========================', $y, 5.8, true);
        $y -= 16;
        $stream .= $this->textoCentro('APURACAO SALDO CAIXA DINHEIRO', $y, 5.9, true);
        $y -= 16;
        $stream .= $this->textoCentro('==========================', $y, 5.8, true);
        $y -= 15;

        $stream .= $this->linhaTabela('Saldo Inicial Troco', $dados['saldo_abertura'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('(Troco + Dinheiro) - Sangria', $dados['dinheiro_esperado'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela('Saldo Informado', $dados['saldo_informado'], $y);
        $y -= 13;
        $stream .= $this->linhaTabela($dados['resultado_label'], $dados['resultado_valor'], $y);
        $y -= 28;
        $stream .= $this->textoCentro('==========================', $y, 5.8, true);

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
        $resultadoLabel = 'Sem diferenca';
        if ($diferenca > 0) {
            $resultadoLabel = 'Sobra no Caixa';
        } elseif ($diferenca < 0) {
            $resultadoLabel = 'Falta no Caixa';
        }

        return [
            'empresa' => $this->empresa(),
            'operador' => $this->textoLimpo($this->dadosFechamento->usuario->nome ?? ''),
            'abertura' => $this->formatarData($this->dadosFechamento->data_registro ?? $this->dadosFechamento->created_at ?? null),
            'fechamento' => $this->formatarData($this->dadosFechamento->updated_at ?? null),
            'pagamentos' => $pagamentos ?: [['tipo' => 'Sem vendas', 'valor' => $this->dinheiro(0)]],
            'total_pagamentos' => $this->dinheiro($totalPagamentos),
            'total_geral' => $this->dinheiro($totalPagamentos - $totalSangrias),
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

    private function linhaTabela(string $label, string $valor, float $y): string
    {
        $stream = $this->retangulo(8, $y - 8, 74, 12);
        $stream .= $this->retangulo(82, $y - 8, 62, 12);
        $fonteLabel = strlen($this->textoLimpo($label)) > 22 ? 4.7 : 5.5;
        $stream .= $this->texto($this->limitarTexto($label, 28), 10, $y - 4, $fonteLabel, true);
        $stream .= $this->textoDireita($this->limitarTexto($valor, 16), 140, $y - 4, 5.3, true);
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
