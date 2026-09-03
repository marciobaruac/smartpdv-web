<?php

namespace App\Services;

use App\Models\VendaCaixa;
use App\Models\ConfigNota;

class CupomFechamentoPdf extends CupomNaoFiscalPdf
{
    protected const PAGE_WIDTH = 164.4;
    protected const PAGE_HEIGHT = 650;
    protected const LEFT = 18;
    protected const RIGHT = 138;
    protected const LABEL_WIDTH = 72;
    protected const VALUE_WIDTH = 48;
    protected const ROW_HEIGHT = 13;

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
            $stream .= "q\n42 0 0 42 8 592 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco($dados['empresa'], $logo ? 54 : self::LEFT, 628, 5.2, 7);

        $periodoInformado = $this->temPeriodoInformado();

        $y = 548;

        if ($periodoInformado) {
            $stream .= $this->linhaLarga('Vendas por Forma de Pagamento', $y);
            $y -= 17;
            $stream .= $this->linhaLarga('Periodo informado: ' . $this->periodoInformadoTexto(), $y);
            $y -= 28;
        } else {
            $stream .= $this->linhaTabela('Caixa no Periodo', '', $y);
            $y -= 17;
            $stream .= $this->linhaTabela('Operador:', $dados['operador'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela('Abertura:', $dados['abertura'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela('Fechamento:', $dados['fechamento'], $y);
            $y -= 28;
        }

        foreach ($dados['pagamentos'] as $pagamento) {
            $stream .= $this->linhaTabela($pagamento['tipo'], $pagamento['valor'], $y);
            $y -= 17;
        }

        $stream .= $this->linhaTabela('TOTAL', $dados['total_pagamentos'], $y);

        if (!$periodoInformado) {
            $y -= 17;
            $stream .= $this->linhaTabela('(-) Sangria', $dados['total_sangria'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela('TOTAL GERAL', $dados['total_geral'], $y);

            $y -= 31;
            $stream .= $this->textoCentro('========================', $y, 6, true);
            $y -= 17;
            $stream .= $this->textoCentro('APURACAO SALDO CAIXA', $y, 6, true);
            $y -= 17;
            $stream .= $this->textoCentro('========================', $y, 6, true);

            $y -= 24;
            $stream .= $this->linhaTabela('Saldo Inicial Troco', $dados['saldo_inicial'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela('Troco + Dinheiro - Sangria', $dados['saldo_apurado'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela('Saldo Informado', $dados['saldo_informado'], $y);
            $y -= 17;
            $stream .= $this->linhaTabela($dados['descricao_diferenca'], $dados['diferenca'], $y);
        }

        $y -= 31;
        $stream .= $this->textoCentro('========================', $y, 6, true);

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

        $totalSangria = (float) ($this->sangrias ? $this->sangrias->sum('valor') : 0);
        $saldoInicial = (float) ($this->dadosFechamento->valor ?? 0);
        $saldoApurado = $saldoInicial + $dinheiroVendas - $totalSangria;
        $saldoInformado = (float) ($this->dadosFechamento->saldo_informado_fechamento ?? 0);
        $diferenca = $saldoInformado - $saldoApurado;

        if ($diferenca < 0) {
            $descricaoDiferenca = 'Falta no Caixa';
        } elseif ($diferenca > 0) {
            $descricaoDiferenca = 'Sobra no Caixa';
        } else {
            $descricaoDiferenca = 'Conferencia OK';
        }

        $inicio = $this->dadosFechamento->data_registro ?? $this->dadosFechamento->created_at ?? null;
        $fim = $this->dadosFechamento->updated_at ?? null;

        return [
            'empresa' => $this->empresa(),
            'operador' => $this->textoLimpo($this->dadosFechamento->usuario->nome ?? ''),
            'abertura' => $this->formatarData($inicio),
            'fechamento' => $this->formatarData($fim),
            'pagamentos' => $pagamentos ?: [['tipo' => 'Sem recebimentos', 'valor' => $this->dinheiro(0)]],
            'total_pagamentos' => $this->dinheiro($totalPagamentos),
            'total_sangria' => $this->dinheiro($totalSangria),
            'total_geral' => $this->dinheiro($totalPagamentos),
            'saldo_inicial' => $this->dinheiro($saldoInicial),
            'saldo_apurado' => $this->dinheiro($saldoApurado),
            'saldo_informado' => $this->dinheiro($saldoInformado),
            'descricao_diferenca' => $descricaoDiferenca,
            'diferenca' => $this->dinheiro(abs($diferenca)),
        ];
    }

    private function temPeriodoInformado(): bool
    {
        return !empty($this->dataInicial) || !empty($this->dataFinal);
    }

    private function periodoInformadoTexto(): string
    {
        $inicio = $this->dataInicial ? $this->formatarDataCurta($this->dataInicial) : '';
        $fim = $this->dataFinal ? $this->formatarDataCurta($this->dataFinal) : '';

        if ($inicio !== '' && $fim !== '') {
            return $inicio . ' a ' . $fim;
        }

        return $inicio !== '' ? $inicio : $fim;
    }

    private function linhaLarga(string $texto, float $y): string
    {
        $stream = $this->retangulo(self::LEFT, $y - 4, self::LABEL_WIDTH + self::VALUE_WIDTH, self::ROW_HEIGHT);
        $stream .= $this->texto($this->textoLimpo($texto), self::LEFT + 2, $y, 5.0, true);

        return $stream;
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
        $fonteLabel = strlen($this->textoLimpo($label)) > 22 ? 4.8 : 5.3;
        $fonteValor = strlen($this->textoLimpo($valor)) > 15 ? 4.8 : 5.3;

        $stream = $this->retangulo(self::LEFT, $y - 4, self::LABEL_WIDTH, self::ROW_HEIGHT);

        if ($valor !== '') {
            $stream .= $this->retangulo(self::LEFT + self::LABEL_WIDTH, $y - 4, self::VALUE_WIDTH, self::ROW_HEIGHT);
        }

        $stream .= $this->texto($this->limitarTexto($label, 26), self::LEFT + 2, $y, $fonteLabel, true);

        if ($valor !== '') {
            $stream .= $this->textoDireita($this->limitarTexto($valor, 16), self::RIGHT - 4, $y, $fonteValor, true);
        }

        return $stream;
    }

    private function retangulo(float $x, float $y, float $w, float $h): string
    {
        return "0.4 w\n{$x} {$y} {$w} {$h} re\nS\n";
    }

    protected function textoLimpo($texto): string
    {
        $texto = (string) $texto;
        $texto = $this->normalizarEncoding($texto);

        $mapa = [
            'Ã¡' => 'a', 'Ã ' => 'a', 'Ã£' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
            'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
            'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã³' => 'o', 'Ã²' => 'o', 'Ãµ' => 'o', 'Ã´' => 'o', 'Ã¶' => 'o',
            'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
            'Ã§' => 'c', 'Ã' => 'A', 'Ã€' => 'A', 'Ãƒ' => 'A', 'Ã‚' => 'A',
            'Ã‰' => 'E', 'ÃŠ' => 'E', 'Ã' => 'I', 'Ã“' => 'O', 'Ã•' => 'O',
            'Ã”' => 'O', 'Ãš' => 'U', 'Ã‡' => 'C',
            'ÃƒÂ¡' => 'a', 'ÃƒÂ£' => 'a', 'ÃƒÂ©' => 'e', 'ÃƒÂª' => 'e', 'ÃƒÂ­' => 'i',
            'ÃƒÂ³' => 'o', 'ÃƒÂµ' => 'o', 'ÃƒÂº' => 'u', 'ÃƒÂ§' => 'c',
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];

        $texto = strtr($texto, $mapa);

        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            if ($convertido !== false) {
                $texto = $convertido;
            }
        }

        $texto = str_replace(
            ['Fl??via', 'Cart??o', 'D??bito', 'Cr??dito', 'Confer??ncia', 'Per??odo'],
            ['Flavia', 'Cartao', 'Debito', 'Credito', 'Conferencia', 'Periodo'],
            $texto
        );

        return preg_replace('/[^\x20-\x7E]/', '', $texto);
    }

    private function normalizarEncoding(string $texto): string
    {
        $corrigido = @iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
        if ($corrigido !== false && $this->contarCaracteresInvalidos($corrigido) < $this->contarCaracteresInvalidos($texto)) {
            return $corrigido;
        }

        if (function_exists('mb_convert_encoding')) {
            $corrigido = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
            if (is_string($corrigido) && $this->contarCaracteresInvalidos($corrigido) < $this->contarCaracteresInvalidos($texto)) {
                return $corrigido;
            }
        }

        return $texto;
    }

    private function contarCaracteresInvalidos(string $texto): int
    {
        return substr_count($texto, '?') + substr_count($texto, '�');
    }

    private function limitarTexto(string $texto, int $limite): string
    {
        $texto = $this->textoLimpo($texto);
        if (strlen($texto) <= $limite) {
            return $texto;
        }

        return substr($texto, 0, $limite);
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

    private function dinheiro(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}
