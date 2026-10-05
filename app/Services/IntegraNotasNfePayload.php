<?php

namespace App\Services;

use App\Models\ConfigNota;
use App\Models\Tributacao;
use App\Models\Venda;

/**
 * Monta o payload JSON de NF-e (modelo 55) esperado pela IntegraNotas
 * (POST /v1/nfe) a partir da Venda do pantanal.
 *
 * Portado/adaptado de smartfinantech-erp (IntegranotasController::montarPayloadNFe),
 * simplificado para a modelagem do pantanal (sem Asaas/override por item).
 */
class IntegraNotasNfePayload
{
    /** CST/CSOSN que exigem base/valor de ICMS no payload. */
    private const ICMS_EXIGE_BASE = ['00', '10', '20', '70', '90', '900'];

    public function montar(Venda $v, ConfigNota $cfg, Tributacao $trib, int $serie, int $numero): array
    {
        $agora = now()->toIso8601String();
        $dataEmissaoRef = substr($agora, 0, 10);

        $cli = $v->cliente;
        $dest = $this->montarDestinatario($cli, $cfg);

        $isInterestadual = strtoupper((string) $cfg->UF) !== strtoupper((string) ($cli->cidade->uf ?? $cfg->UF));

        // ===== Itens =====
        $itens = [];
        $nItem = 0;
        $valorTotal = 0.0;

        foreach ($v->itens as $i) {
            $produto = $i->produto;
            if ($produto === null) {
                throw new \RuntimeException("A venda #{$v->id} possui um item (ID {$i->id}) sem produto vinculado. Corrija antes de emitir a NF-e.");
            }

            $nItem++;
            $qtd = (float) $i->quantidade;
            $vUnit = (float) $i->valor;
            $vBrut = round($qtd * $vUnit, 2);
            $valorTotal += $vBrut;

            $itens[] = $this->montarItem($nItem, $i, $produto, $v, $cfg, $trib, $isInterestadual, $qtd, $vUnit, $vBrut);
        }

        if (empty($itens)) {
            throw new \RuntimeException("A venda #{$v->id} nao possui itens para emitir a NF-e.");
        }

        // ===== Rateio de desconto e frete (centavos, fecha exato) =====
        $descontoTotal = (float) ($v->desconto ?? 0);
        $this->ratear($itens, $descontoTotal, $valorTotal, 'valor_desconto');

        $valorFrete = 0.0;
        if (!empty($v->frete)) {
            $valorFrete = (float) ($v->frete->valor ?? $v->frete->valor_frete ?? 0);
        }
        $this->ratear($itens, $valorFrete, $valorTotal, 'valor_frete');

        // Recalcula bases de ICMS/PIS/COFINS apos desconto+frete
        foreach ($itens as &$it) {
            $this->recalcularBasesItem($it);
        }
        unset($it);

        // ===== Pagamento =====
        $valorLiquido = max(0, round($valorTotal + $valorFrete - $descontoTotal, 2));
        $tpag = $this->mapMeioPagamento((string) $v->tipo_pagamento);
        $aPrazo = in_array((string) $v->tipo_pagamento, ['05', '06'], true);

        if ($tpag === '90') {
            $pagamento = ['formas_pagamento' => [['meio_pagamento' => '90', 'valor' => '0.00']]];
        } else {
            $pagamento = [
                'formas_pagamento' => [[
                    'meio_pagamento' => $tpag,
                    'valor' => number_format($valorLiquido, 2, '.', ''),
                    'tipo_integracao' => '2',
                    'indicador_pagamento' => $aPrazo ? '1' : '0',
                ]],
            ];
        }

        // ===== Frete =====
        $frete = [
            'modalidade_frete' => (string) (($v->frete->tipo ?? null) ?: (($cfg->frete_padrao ?? null) !== null ? (string) $cfg->frete_padrao : '9')),
            'valor_frete' => number_format($valorFrete, 2, '.', ''),
        ];
        $this->anexarTransportadora($frete, $v);

        // ===== Observacoes =====
        $obs = trim((string) ($v->observacao ?? ''));
        $obs = preg_replace('/\s+/u', ' ', $obs);
        $obs = trim((string) mb_substr($obs, 0, 1200, 'UTF-8'));

        $payload = [
            'natureza_operacao' => $this->txt((string) ($v->natureza->natureza ?? 'VENDA')),
            'serie' => (string) $serie,
            'numero' => (string) $numero,
            'data_emissao' => $agora,
            'data_entrada_saida' => $agora,
            'tipo_operacao' => '1',       // 1 = saida
            'finalidade_emissao' => '1',  // 1 = normal
            'consumidor_final' => (string) ((int) ($cli->consumidor_final ?? 0)),
            'presenca_comprador' => '1',
            'destinatario' => $dest,
            'itens' => $itens,
            'frete' => $frete,
            'indicador_pagamento' => $aPrazo ? '1' : '0',
            'pagamento' => $pagamento,
            'informacoes_adicionais_contribuinte' => $obs,
            'informacoes_complementares' => $obs,
            'informacoes_adicionais' => $obs,
            'pessoas_autorizadas' => array_values(array_filter([
                !empty($cfg->cnpj) ? ['cnpj' => preg_replace('/\D+/', '', (string) $cfg->cnpj)] : null,
            ])),
        ];

        // ===== IBS/CBS (reforma tributaria) =====
        $this->garantirIbsCbs($payload);

        return $payload;
    }

    // ===================================================================
    // Destinatario
    // ===================================================================

    private function montarDestinatario($cli, ConfigNota $cfg): array
    {
        $cnpjCpf = preg_replace('/\D+/', '', (string) ($cli->cpf_cnpj ?? ''));
        $isPF = (strlen($cnpjCpf) === 11);

        $dest = [
            ($isPF ? 'cpf' : 'cnpj') => $cnpjCpf,
            'nome' => $this->txt((string) ($cli->razao_social ?: $cli->nome_fantasia ?: 'CONSUMIDOR')),
        ];

        $ieRaw = strtoupper(trim((string) ($cli->ie_rg ?? '')));
        $isIsento = ($ieRaw === 'ISENTO');
        $ieDigits = preg_replace('/\D+/', '', $ieRaw);
        $ieValida = ($ieDigits !== '' && strlen($ieDigits) >= 2 && strlen($ieDigits) <= 14 && !preg_match('/^0+$/', $ieDigits));

        $uf = strtoupper((string) ($cli->cidade->uf ?? $cfg->UF ?? ''));
        $ufsNaoAceitamIsento = ['AL','AM','BA','CE','DF','ES','GO','MG','MS','MT','PA','PB','PE','RJ','RN','RS','SE','SP'];
        $ufNaoAceitaIsento = in_array($uf, $ufsNaoAceitamIsento, true);

        if (!$isIsento && $ieValida) {
            $dest['indicador_inscricao_estadual'] = '1';
            $dest['inscricao_estadual'] = $ieDigits;
        } elseif ($isIsento && !$ufNaoAceitaIsento && !$isPF) {
            $dest['indicador_inscricao_estadual'] = '2';
        } else {
            $dest['indicador_inscricao_estadual'] = '9';
        }

        $dest['endereco'] = [
            'logradouro' => $this->txt((string) ($cli->rua ?? '')),
            'numero' => (string) ($cli->numero ?? ''),
            'bairro' => $this->txt((string) ($cli->bairro ?? '')),
            'codigo_municipio' => (string) ($cli->cidade->codigo ?? $cfg->codMun),
            'nome_municipio' => $this->txt((string) ($cli->cidade->nome ?? $cfg->municipio)),
            'uf' => (string) ($cli->cidade->uf ?? $cfg->UF),
            'cep' => preg_replace('/\D+/', '', (string) ($cli->cep ?? '')),
            'codigo_pais' => '1058',
            'nome_pais' => 'BRASIL',
        ];

        return $dest;
    }

    // ===================================================================
    // Item
    // ===================================================================

    private function montarItem(int $nItem, $i, $produto, Venda $v, ConfigNota $cfg, Tributacao $trib, bool $isInterestadual, float $qtd, float $vUnit, float $vBrut): array
    {
        // NCM
        $ncm = preg_replace('/\D+/', '', (string) ($produto->NCM ?? ''));
        if ($ncm !== '' && strlen($ncm) < 8) {
            $ncm = str_pad($ncm, 8, '0', STR_PAD_LEFT);
        }

        // CFOP
        $simplesRemessa = (!empty($v->natureza) && (int) ($v->natureza->simplesremessa ?? 0) === 1)
            || (int) ($v->simplesremessa ?? 0) === 1;
        if ($simplesRemessa) {
            $cfop = $isInterestadual
                ? (string) ($v->natureza->CFOP_saida_inter_estadual ?? '')
                : (string) ($v->natureza->CFOP_saida_estadual ?? '');
        } else {
            $cfop = $isInterestadual
                ? (string) ($produto->CFOP_saida_inter_estadual ?: ($v->natureza->CFOP_saida_inter_estadual ?? ''))
                : (string) ($produto->CFOP_saida_estadual ?: ($v->natureza->CFOP_saida_estadual ?? ''));
        }

        $icms = $this->montarIcms($produto, $cfg, $trib, $vBrut);

        // PIS
        $pisPerc = max(0, min(100, (float) str_replace(',', '.', (string) ($produto->perc_pis ?? 0))));
        $pis = [
            'situacao_tributaria' => $this->valorOuPadrao($produto->CST_PIS ?? $cfg->CST_PIS_padrao, '01'),
            'valor_base_calculo' => number_format($vBrut, 2, '.', ''),
            'aliquota' => number_format($pisPerc, 2, '.', ''),
            'valor' => number_format($vBrut * $pisPerc / 100, 2, '.', ''),
        ];

        // COFINS
        $cofPerc = max(0, min(100, (float) str_replace(',', '.', (string) ($produto->perc_cofins ?? 0))));
        $cofins = [
            'situacao_tributaria' => $this->valorOuPadrao($produto->CST_COFINS ?? $cfg->CST_COFINS_padrao, '01'),
            'valor_base_calculo' => number_format($vBrut, 2, '.', ''),
            'aliquota' => number_format($cofPerc, 2, '.', ''),
            'valor' => number_format($vBrut * $cofPerc / 100, 2, '.', ''),
        ];

        return [
            'numero_item' => (string) $nItem,
            'codigo_produto' => (string) ($produto->id ?? $nItem),
            'descricao' => $this->txt((string) $produto->nome),
            'codigo_ncm' => $ncm !== '' ? substr($ncm, 0, 8) : '00000000',
            'cfop' => $cfop ?: '5102',
            'unidade_comercial' => (string) ($produto->unidade_venda ?: 'UN'),
            'quantidade_comercial' => number_format($qtd, 4, '.', ''),
            'valor_unitario_comercial' => number_format($vUnit, 10, '.', ''),
            'valor_bruto' => number_format($vBrut, 2, '.', ''),
            'unidade_tributavel' => (string) ($produto->unidade_venda ?: 'UN'),
            'quantidade_tributavel' => number_format($qtd, 4, '.', ''),
            'valor_unitario_tributavel' => number_format($vUnit, 10, '.', ''),
            'origem' => (string) ($produto->origem ?? '0'),
            'inclui_no_total' => '1',
            'imposto' => [
                'icms' => $icms,
                'pis' => $pis,
                'cofins' => $cofins,
            ],
            'valor_desconto' => '0.00',
            'valor_frete' => '0.00',
            'valor_seguro' => '0.00',
            'valor_outras_despesas' => '0.00',
        ];
    }

    private function montarIcms($produto, ConfigNota $cfg, Tributacao $trib, float $vBrut): array
    {
        $sit = $this->situacaoIcmsParaRegime($produto->CST_CSOSN ?? null, $cfg, $trib);

        $aliq = (float) str_replace(',', '.', (string) ($produto->perc_icms ?? 0));
        $csosnList = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];
        if (in_array($sit, $csosnList, true) && $sit !== '900') {
            $aliq = 0.0;
        }
        $aliq = max(0, min(100, $aliq));

        $icms = [
            'situacao_tributaria' => $sit,
            'aliquota' => number_format($aliq, 2, '.', ''),
            'valor' => '0.00',
        ];

        if (in_array($sit, self::ICMS_EXIGE_BASE, true)) {
            $base = max(0, round($vBrut, 2));
            $icms['modalidade_base_calculo'] = (string) ($produto->modbc ?? '3');
            $icms['valor_base_calculo'] = number_format($base, 2, '.', '');
            $icms['valor'] = number_format($base * $aliq / 100, 2, '.', '');
        }

        if ($sit === '101') {
            $aliqCred = max(0, min(100, (float) str_replace(',', '.', (string) ($trib->icms ?? 0))));
            $icms['aliquota_credito_simples'] = number_format($aliqCred, 2, '.', '');
            $icms['valor_credito_simples'] = number_format($vBrut * $aliqCred / 100, 2, '.', '');
        }

        return $icms;
    }

    /** Recalcula base/valor de ICMS/PIS/COFINS apos aplicar desconto+frete no item. */
    private function recalcularBasesItem(array &$item): void
    {
        $vBruto = (float) $item['valor_bruto'];
        $desc = (float) $item['valor_desconto'];
        $frete = (float) $item['valor_frete'];
        $baseTrib = max(0, round($vBruto - $desc, 2));

        if (isset($item['imposto']['pis'])) {
            $aliq = (float) $item['imposto']['pis']['aliquota'];
            $item['imposto']['pis']['valor_base_calculo'] = number_format($baseTrib, 2, '.', '');
            $item['imposto']['pis']['valor'] = number_format($baseTrib * $aliq / 100, 2, '.', '');
        }
        if (isset($item['imposto']['cofins'])) {
            $aliq = (float) $item['imposto']['cofins']['aliquota'];
            $item['imposto']['cofins']['valor_base_calculo'] = number_format($baseTrib, 2, '.', '');
            $item['imposto']['cofins']['valor'] = number_format($baseTrib * $aliq / 100, 2, '.', '');
        }
        if (isset($item['imposto']['icms']) && in_array((string) $item['imposto']['icms']['situacao_tributaria'], self::ICMS_EXIGE_BASE, true)) {
            $aliq = (float) $item['imposto']['icms']['aliquota'];
            $baseIcms = max(0, round($baseTrib + $frete, 2));
            $item['imposto']['icms']['modalidade_base_calculo'] = (string) ($item['imposto']['icms']['modalidade_base_calculo'] ?? '3');
            $item['imposto']['icms']['valor_base_calculo'] = number_format($baseIcms, 2, '.', '');
            $item['imposto']['icms']['valor'] = number_format($baseIcms * $aliq / 100, 2, '.', '');
        }
    }

    // ===================================================================
    // Rateio em centavos (fecha a soma exatamente)
    // ===================================================================

    private function ratear(array &$itens, float $total, float $base, string $campo): void
    {
        if ($total <= 0 || $base <= 0 || count($itens) === 0) {
            return;
        }

        $totalCents = max(0, (int) round($total * 100));
        $baseCents = max(1, (int) round($base * 100));

        $bases = [];
        foreach ($itens as $k => $it) {
            $bases[$k] = max(0, (int) round(((float) $it['valor_bruto']) * 100));
        }

        $cents = array_fill(0, count($itens), 0);
        $acum = 0;
        foreach ($itens as $k => $dummy) {
            $p = (int) floor(($totalCents * $bases[$k]) / $baseCents);
            $cents[$k] = $p;
            $acum += $p;
        }

        $resto = $totalCents - $acum;
        arsort($bases);
        foreach ($bases as $k => $b) {
            if ($resto <= 0) {
                break;
            }
            $cents[$k] += 1;
            $resto -= 1;
        }

        foreach ($itens as $k => &$it) {
            $it[$campo] = number_format(max(0, $cents[$k] / 100), 2, '.', '');
        }
        unset($it);
    }

    // ===================================================================
    // IBS/CBS
    // ===================================================================

    private function garantirIbsCbs(array &$payload): void
    {
        if (empty($payload['itens']) || !is_array($payload['itens'])) {
            return;
        }
        foreach ($payload['itens'] as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if (empty($item['imposto']) || !is_array($item['imposto'])) {
                $item['imposto'] = [];
            }
            $item['imposto']['ibs_cbs'] = [
                'situacao_tributaria' => (string) env('INTEGRA_NFE_IBS_CBS_CST', '000'),
                'classificacao_tributaria' => (string) env('INTEGRA_NFE_IBS_CBS_CCLASS', '000001'),
                'grupo_ibs_cbs' => $this->grupoIbsCbs($item),
            ];
        }
        unset($item);
    }

    private function grupoIbsCbs(array $item): array
    {
        $base = max(0, round(
            (float) ($item['valor_bruto'] ?? 0)
            - (float) ($item['valor_desconto'] ?? 0)
            + (float) ($item['valor_frete'] ?? 0)
            + (float) ($item['valor_seguro'] ?? 0)
            + (float) ($item['valor_outras_despesas'] ?? 0),
            2
        ));

        $aliqIbsUf = max(0, (float) env('INTEGRA_NFE_IBS_UF_ALIQUOTA', 0.10));
        $aliqIbsMun = max(0, (float) env('INTEGRA_NFE_IBS_MUN_ALIQUOTA', 0.00));
        $aliqCbs = max(0, (float) env('INTEGRA_NFE_CBS_ALIQUOTA', 0.90));

        $valorIbsUf = round($base * $aliqIbsUf / 100, 2);
        $valorIbsMun = round($base * $aliqIbsMun / 100, 2);
        $valorCbs = round($base * $aliqCbs / 100, 2);

        return [
            'valor_base_calculo' => number_format($base, 2, '.', ''),
            'valor_total_ibs' => number_format($valorIbsUf + $valorIbsMun, 2, '.', ''),
            'ibs_estadual' => [
                'aliquota' => number_format($aliqIbsUf, 4, '.', ''),
                'valor' => number_format($valorIbsUf, 2, '.', ''),
            ],
            'ibs_municipal' => [
                'aliquota' => number_format($aliqIbsMun, 4, '.', ''),
                'valor' => number_format($valorIbsMun, 2, '.', ''),
            ],
            'cbs' => [
                'aliquota' => number_format($aliqCbs, 4, '.', ''),
                'valor' => number_format($valorCbs, 2, '.', ''),
            ],
        ];
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function anexarTransportadora(array &$frete, Venda $v): void
    {
        if (empty($v->transportadora)) {
            return;
        }
        $t = $v->transportadora;
        $doc = preg_replace('/\D+/', '', (string) ($t->cnpj_cpf ?? ''));
        $payload = ['nome' => $this->txt((string) ($t->razao_social ?? ''))];
        if (strlen($doc) === 14) {
            $payload['cnpj'] = $doc;
        } elseif (strlen($doc) === 11) {
            $payload['cpf'] = $doc;
        }
        $frete['transportador'] = array_filter($payload, function ($x) {
            return $x !== '' && $x !== null;
        });
    }

    private function situacaoIcmsParaRegime($valor, ConfigNota $cfg, Tributacao $trib): string
    {
        $valor = trim((string) $valor);
        $padrao = trim((string) ($cfg->CST_CSOSN_padrao ?? ''));
        $csosnValidos = ['101', '102', '103', '201', '202', '203', '300', '400', '500', '900'];

        if ((int) ($trib->regime ?? 0) === 0) { // Simples Nacional
            if (in_array($valor, $csosnValidos, true)) {
                return $valor;
            }
            if (in_array($padrao, $csosnValidos, true)) {
                return $padrao;
            }
            $map = ['00'=>'102','10'=>'202','20'=>'102','30'=>'400','40'=>'400','41'=>'400','50'=>'400','51'=>'900','60'=>'500','70'=>'202','90'=>'900'];
            return $map[$valor] ?? '102';
        }

        // Regime normal: usa CST (2 digitos)
        return $this->valorOuPadrao($valor ?: $padrao, '00');
    }

    private function valorOuPadrao($valor, string $padrao): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' ? $valor : $padrao;
    }

    private function mapMeioPagamento(string $tipoPagamento): string
    {
        // tipo_pagamento do pantanal -> tPag SEFAZ
        $map = [
            '01' => '01', // Dinheiro
            '02' => '04', // Cartao de Debito
            '03' => '03', // Cartao de Credito
            '04' => '17', // Pix
            '05' => '05', // Conta/Crediario -> Credito Loja
            '06' => '15', // Boleto Bancario
        ];
        $t = trim($tipoPagamento);
        return $map[$t] ?? '99';
    }

    /** Remove acentos/controle para campos de texto da NF-e. */
    private function txt($texto): string
    {
        $texto = (string) $texto;
        $texto = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $texto);
        return trim((string) $texto);
    }
}
