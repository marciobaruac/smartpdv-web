<?php

namespace App\Services;

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use App\Models\VendaCaixa;
use App\Models\Venda;
use App\Models\ConfigNota;
use App\Models\Certificado;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\Common\Soap\SoapCurl;
use App\Models\Tributacao;
use App\Models\PedidoDelivery;
use App\Models\IBPT;
use App\Models\TransacaoTEF;
use App\Helpers\LegacyCertificate;
use Illuminate\Support\Facades\Log;


error_reporting(E_ALL);
ini_set('display_errors', 'On');


class NFCeService
{
    private const NFE_NS = 'http://www.portalfiscal.inf.br/nfe';
    private const IBSCBS_CST = '000';
    private const IBSCBS_CCLASS_TRIB = '000001';
    private const IBS_UF_ALIQ = 0.10;
    private const IBS_MUN_ALIQ = 0.00;
    private const CBS_ALIQ = 0.90;

    // Atualize esta string a cada deploy relevante para conferir no log
    // (canal "nfce") se o arquivo em produção é a versão esperada.
    const VERSION = '2026-08-03.3-ibscbs-pl010-nativo';

    private $config;
    private $tools;
    private $certificate;

    public function __construct($config)
    {
        Log::channel('nfce')->info('NFC-e service.version', [
            'version'    => self::VERSION,
            'file'       => __FILE__,
            'modificado' => date('Y-m-d H:i:s', filemtime(__FILE__)),
        ]);

        $certificado = Certificado::first();
        $this->config = $config;

        if (!$certificado || empty($certificado->arquivo)) {
            throw new \Exception('Certificado digital A1 não configurado. Faça o upload do arquivo .pfx e senha.');
        }

        $senhaCertificado = trim((string)$certificado->senha);
        $arquivoCertificado = $certificado->arquivo;
        try {
            $certificate = Certificate::readPfx($arquivoCertificado, $senhaCertificado);
        } catch (\Throwable $e) {
            $convertido = LegacyCertificate::tryReencapsulateIfLegacy($arquivoCertificado, $senhaCertificado);
            if (empty($convertido)) {
                throw new \Exception('Falha ao ler o certificado A1 (.pfx). Verifique arquivo/senha. Detalhe: ' . $e->getMessage());
            }

            $arquivoCertificado = $convertido;
            $certificate = Certificate::readPfx($arquivoCertificado, $senhaCertificado);

            if ($certificado->arquivo !== $arquivoCertificado) {
                $certificado->arquivo = $arquivoCertificado;
                $certificado->save();
            }
        }
        $this->certificate = $certificate;

        $config['schemes'] = 'PL_010_V1.30';

        $this->tools = new Tools(json_encode($config), $certificate);
        $this->configureSoapSecurity($this->mustDisableSoapSecurityByEnv());
        $this->tools->model(65);
    }

    private function mustDisableSoapSecurityByEnv(): bool
    {
        $flag = strtolower(trim((string) getenv('NFCE_DISABLE_SSL_VERIFY')));
        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    private function configureSoapSecurity(bool $disableSecurity = false): void
    {
        $soap = new SoapCurl($this->certificate);
        $soapTimeout = (int) (getenv('NFCE_SOAP_TIMEOUT') ?: 12);
        if ($soapTimeout < 5) {
            $soapTimeout = 5;
        }
        $soap->timeout($soapTimeout);
        if ($disableSecurity) {
            $soap->disableSecurity(true);
            $soap->disableCertValidation(true);
        }
        $this->tools->loadSoapClass($soap);

        Log::channel('nfce')->info('NFC-e SOAP configurado', [
            'timeout' => $soapTimeout,
            'ssl_verify_disabled' => $disableSecurity,
            'env_disable_ssl' => $this->mustDisableSoapSecurityByEnv(),
        ]);
    }

    private function isSslCertificateChainError(\Throwable $e): bool
    {
        $msg = strtolower((string) $e->getMessage());
        return strpos($msg, 'ssl certificate') !== false
            || strpos($msg, 'self signed certificate') !== false
            || strpos($msg, 'certificate chain') !== false
            || strpos($msg, 'openssl verify') !== false;
    }

    private function responseHasSslCertificateChainError($response): bool
    {
        if (!is_string($response) || $response === '') {
            return false;
        }
        $msg = strtolower($response);
        return strpos($msg, 'ssl certificate') !== false
            || strpos($msg, 'self signed certificate') !== false
            || strpos($msg, 'certificate chain') !== false
            || strpos($msg, 'openssl verify') !== false
            || strpos($msg, 'an error occurred while trying to communication via soap') !== false;
    }

    private function runWithSslFallback(callable $callback)
    {
        try {
            if ($this->mustDisableSoapSecurityByEnv()) {
                Log::channel('nfce')->warning('NFC-e SOAP preparando chamada com SSL desabilitado por env');
                $this->configureSoapSecurity(true);
            }

            $response = $callback();
            if ($this->responseHasSslCertificateChainError($response)) {
                Log::channel('nfce')->warning('NFC-e SOAP detectou erro SSL na resposta; tentando fallback sem validacao SSL', [
                    'response_preview' => substr((string) $response, 0, 500),
                ]);
                $this->configureSoapSecurity(true);
                return $callback();
            }
            return $response;
        } catch (\Throwable $e) {
            if (!$this->isSslCertificateChainError($e)) {
                Log::channel('nfce')->error('NFC-e SOAP falhou sem relacao com SSL', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw $e;
            }
            Log::channel('nfce')->warning('NFC-e SOAP excecao SSL; tentando fallback sem validacao SSL', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->configureSoapSecurity(true);
            try {
                return $callback();
            } catch (\Throwable $retryException) {
                Log::channel('nfce')->error('NFC-e SOAP fallback tambem falhou', [
                    'first_message' => $e->getMessage(),
                    'retry_message' => $retryException->getMessage(),
                    'file' => $retryException->getFile(),
                    'line' => $retryException->getLine(),
                ]);
                throw $retryException;
            }
        }
    }

    private function getProtocolFromAuthorizedXml(?string $chave, ?string $pathXml = null, ?int $vendaId = null): ?string
    {
        $paths = [];

        $fileNames = [];
        if (!empty($pathXml)) {
            $fileNames[] = ltrim($pathXml, '/');
        }
        if (!empty($chave)) {
            $fileNames[] = $chave . '.xml';
        }
        if (!empty($vendaId)) {
            $fileNames[] = $vendaId . '.xml';
        }
        $fileNames = array_values(array_unique($fileNames));

        foreach ($fileNames as $name) {
            $paths[] = 'xml_nfce/' . $name;
            $paths[] = 'public/xml_nfce/' . $name;
            $paths[] = public_path('xml_nfce/' . $name);
            $paths[] = base_path('xml_nfce/' . $name);
            $paths[] = base_path('public/xml_nfce/' . $name);
        }
        $paths = array_values(array_unique($paths));

        foreach ($paths as $file) {
            if (!is_file($file)) {
                continue;
            }
            $xml = @file_get_contents($file);
            if ($xml === false || $xml === '') {
                continue;
            }
            if (preg_match('/<nProt>(\d+)<\/nProt>/i', $xml, $m)) {
                return $m[1];
            }
        }
        return null;
    }

   public function gerarNFCe($idVenda)
{
    $venda = VendaCaixa::where('id', $idVenda)->first();

    $config      = ConfigNota::first();
    $tributacao  = Tributacao::first();

    $nfe = new Make('PL_010_V1.30');
    $stdInNFe = new \stdClass();
    $stdInNFe->versao  = '4.00'; //versão do layout
    $stdInNFe->Id      = null;   //se o Id de 44 digitos não for passado será gerado automaticamente
    $stdInNFe->pk_nItem = '';    //deixe essa variavel sempre como NULL
    $nfe->taginfNFe($stdInNFe);

    // ========== IDE ==========
    $stdIde = new \stdClass();
    $stdIde->cUF     = $config->cUF;
    $stdIde->cNF     = rand(11111111, 99999999);
    $stdIde->natOp   = $config->natureza->natureza;
    $stdIde->mod     = 65;
    $stdIde->serie   = $config->numero_serie_nfce;
    $stdIde->nNF     = $venda->NFcNumero;
    $stdIde->dhEmi   = date("Y-m-d\TH:i:sP");
    $stdIde->dhSaiEnt= date("Y-m-d\TH:i:sP");
    $stdIde->tpNF    = 1;
    $stdIde->idDest  = 1;
    $stdIde->cMunFG  = $config->codMun;
    $stdIde->tpImp   = 4;
    $stdIde->tpEmis  = 1;
    $stdIde->cDV     = 0;
    $stdIde->tpAmb   = $config->ambiente;
    $stdIde->finNFe  = 1;
    $stdIde->indFinal= 1;
    $stdIde->indPres = 1;
    if ($config->ambiente == 2) $stdIde->indIntermed = 0;
    $stdIde->procEmi = '0';
    $stdIde->verProc = '3.10.31';
    $nfe->tagide($stdIde);

    // ========== EMITENTE ==========
    $stdEmit = new \stdClass();
    $stdEmit->xNome = $config->razao_social;
    $stdEmit->xFant = $config->nome_fantasia;
    $stdEmit->IE    = preg_replace('/\D/', '', $config->ie);
    $stdEmit->CRT   = $tributacao->regime == 0 ? 1 : 3; // 1=SN, 3=Regime Normal
    $stdEmit->CNPJ  = preg_replace('/\D/', '', $config->cnpj);
    $nfe->tagemit($stdEmit);

    // Endereço emitente
    $stdEnderEmit = new \stdClass();
    $stdEnderEmit->xLgr  = $config->logradouro;
    $stdEnderEmit->nro   = $config->numero;
    $stdEnderEmit->xCpl  = "";
    $stdEnderEmit->xBairro = $config->bairro;
    $stdEnderEmit->cMun  = $config->codMun;
    $stdEnderEmit->xMun  = $config->municipio;
    $stdEnderEmit->UF    = $config->UF;
    $stdEnderEmit->CEP   = preg_replace('/\D/', '', $config->cep);
    $stdEnderEmit->cPais = $config->codPais;
    $stdEnderEmit->xPais = $config->pais;
    $stdEnderEmit->fone  = preg_replace('/\D/', '', $config->fone);
    $nfe->tagenderEmit($stdEnderEmit);

    // ========== DESTINATÁRIO (quando houver) ==========
    if ($venda->cliente_id != null || $venda->cpf != null) {
        $stdDest = new \stdClass();

        if ($venda->cliente_id != null) {
            $stdDest->xNome     = $venda->cliente->razao_social;
            $stdDest->indIEDest = "1";
            $cnpj_cpf = preg_replace('/\D/', '', $venda->cliente->cpf_cnpj);
            if (strlen($cnpj_cpf) == 14) $stdDest->CNPJ = $cnpj_cpf; else $stdDest->CPF = $cnpj_cpf;
            $nfe->tagdest($stdDest);

            $stdEnderDest = new \stdClass();
            $stdEnderDest->xLgr   = $venda->cliente->rua;
            $stdEnderDest->nro    = $venda->cliente->numero;
            $stdEnderDest->xCpl   = "";
            $stdEnderDest->xBairro= $venda->cliente->bairro;
            $stdEnderDest->cMun   = $venda->cliente->cidade->codigo;
            $stdEnderDest->xMun   = strtoupper($venda->cliente->cidade->nome);
            $stdEnderDest->UF     = $venda->cliente->cidade->uf;
            $stdEnderDest->CEP    = preg_replace('/\D/', '', $venda->cliente->cep);
            $stdEnderDest->cPais  = "1058";
            $stdEnderDest->xPais  = "BRASIL";
            $nfe->tagenderDest($stdEnderDest);
        }

        if ($venda->cpf != null) {
            $cpf = preg_replace('/\D/', '', $venda->cpf);
            if ($venda->nome) $stdDest->xNome = $venda->nome;
            $stdDest->indIEDest = "9";
            if (strlen($cpf) == 14) $stdDest->CNPJ = $cpf; else $stdDest->CPF = $cpf;
            $nfe->tagdest($stdDest);
        }
    }

    // ========== ITENS ==========
    $somaProdutos = 0.0;
    $somaICMS = 0.0;
    $itemCont = 0;
    $somaDesconto = 0.0;
    $somaAcrescimo = 0.0;
    $VBC = 0.0;
    $somaFederal = 0.0;
    $somaEstadual = 0.0;
    $somaMunicipal = 0.0;

    foreach ($venda->itens as $i) {
        $itemCont++;

        $stdProd = new \stdClass();
        $stdProd->item   = $itemCont;
        $stdProd->cEAN   = $i->produto->codBarras;
        $stdProd->cEANTrib = $i->produto->codBarras;
        $stdProd->cProd  = $i->produto->id;
        $stdProd->xProd  = $this->retiraAcentos($i->produto->nome);
        if ($i->produto->cBenef) $stdProd->cBenef = $i->produto->cBenef;

        $ncm = preg_replace('/\D/', '', $i->produto->NCM);
        $stdProd->NCM = $ncm;

        $ibpt = IBPT::getIBPT($config->UF, $ncm);

        $stdProd->CFOP   = $i->produto->CFOP_saida_estadual;
        $cest = preg_replace('/\D/', '', $i->produto->CEST);
        $stdProd->CEST   = $cest;
        $stdProd->uCom   = $i->produto->unidade_venda;
        $stdProd->qCom   = $i->quantidade;
        $stdProd->vUnCom = $this->format($i->valor);
        $stdProd->vProd  = $this->format($i->quantidade * $i->valor);
        $stdProd->uTrib  = $i->produto->unidade_venda;
        $stdProd->qTrib  = $i->quantidade;
        $stdProd->vUnTrib= $this->format($i->valor);
        $stdProd->indTot = 1;

        // Acréscimo rateado (quando houver)
        if ($venda->acrescimo > 0) {
            if ($itemCont < sizeof($venda->itens)) {
                $totalVenda   = $venda->valor_total;
                $media        = (((($stdProd->vProd - $totalVenda) / $totalVenda)) * 100);
                $media        = 100 - ($media * -1);
                $tempAcr      = ($venda->acrescimo * $media) / 100;
                $somaAcrescimo += $tempAcr;
                $stdProd->vOutro = $this->format($tempAcr);
            } else {
                $stdProd->vOutro = $this->format($venda->acrescimo - $somaAcrescimo);
            }
        }

        // Delivery: rateio de diferença (quando existir)
        if ($venda->pedido_delivery_id > 0) {
            $pedido     = PedidoDelivery::find($venda->pedido_delivery_id);
            $somaItens  = $pedido->somaItensSemFrete();
            $totalVenda = $venda->valor_total;
            if ($somaItens < $totalVenda) {
                $vAcr = $totalVenda - $somaItens;
                if ($itemCont < sizeof($venda->itens)) {
                    $media = (((($stdProd->vProd - $totalVenda) / $totalVenda)) * 100);
                    $media = 100 - ($media * -1);
                    $tempAcr = ($vAcr * $media) / 100;
                    $somaAcrescimo += $tempAcr;
                    $stdProd->vOutro = $this->format($tempAcr);
                } else {
                    $stdProd->vOutro = $this->format($vAcr - $somaAcrescimo);
                }
            }
        }

        // Desconto rateado (quando houver)
        if ($venda->desconto > 0) {
            if ($itemCont < sizeof($venda->itens)) {
                $totalVenda = $venda->valor_total + $venda->desconto;
                $media      = ($i->quantidade * $i->valor) / $totalVenda;
                $tempDesc   = $venda->desconto * $media;
                $somaDesconto += $tempDesc;
                $stdProd->vDesc = $this->format($tempDesc);
            } else {
                $stdProd->vDesc = $this->format($venda->desconto - $somaDesconto);
            }
        }

        $somaProdutos += $i->quantidade * $i->valor;
        $nfe->tagprod($stdProd);

        // Impostos por item
        $stdImposto = new \stdClass();
        $stdImposto->item = $itemCont;

        if ($ibpt != null) {
            $vProd = $stdProd->vProd;
            $somaFederal  += ($vProd * ($ibpt->nacional_federal / 100));
            $somaEstadual += ($vProd * ($ibpt->estadual / 100));
            $somaMunicipal+= ($vProd * ($ibpt->municipal / 100));
            $stdImposto->vTotTrib = ($somaFederal + $somaEstadual + $somaMunicipal);
        }
        $nfe->tagimposto($stdImposto);

        if ($tributacao->regime == 1) { // regime normal
            $stdICMS = new \stdClass();
            $stdICMS->item = $itemCont;
            $stdICMS->orig = 0;
            $stdICMS->CST  = $i->produto->CST_CSOSN;
            $stdICMS->modBC= 0;
            $stdICMS->vBC  = $this->format($i->valor * $i->quantidade);
            $stdICMS->pICMS= $this->format($i->produto->perc_icms);
            $stdICMS->vICMS= $stdICMS->vBC * ($stdICMS->pICMS / 100);

            if ($i->produto->CST_CSOSN == '500' || $i->produto->CST_CSOSN == '60') {
                $stdICMS->pRedBCEfet = 0.00;
                $stdICMS->vBCEfet    = 0.00;
                $stdICMS->pICMSEfet  = 0.00;
                $stdICMS->vICMSEfet  = 0.00;
            } else {
                $VBC += $stdProd->vProd;
            }

            $somaICMS += $stdICMS->vICMS;
            $nfe->tagICMS($stdICMS);
        } else { // Simples
            $stdICMS = new \stdClass();
            $stdICMS->item    = $itemCont;
            $stdICMS->orig    = 0;
            $stdICMS->CSOSN   = $i->produto->CST_CSOSN;
            $stdICMS->pCredSN = $this->format($i->produto->perc_icms);
            $stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
            $nfe->tagICMSSN($stdICMS);
            $somaICMS = 0;
        }

        // PIS
        $stdPIS = new \stdClass();
        $stdPIS->item = $itemCont;
        $stdPIS->CST  = $i->produto->CST_PIS;
        $stdPIS->vBC  = ($this->format($i->produto->perc_pis) > 0) ? $stdProd->vProd : 0.00;
        $stdPIS->pPIS = $this->format($i->produto->perc_pis);
        $stdPIS->vPIS = $this->format(($stdProd->vProd) * ($i->produto->perc_pis / 100));
        $nfe->tagPIS($stdPIS);

        // COFINS
        $stdCOFINS = new \stdClass();
        $stdCOFINS->item = $itemCont;
        $stdCOFINS->CST  = $i->produto->CST_COFINS;
        $stdCOFINS->vBC  = ($this->format($i->produto->perc_cofins) > 0) ? $stdProd->vProd : 0.00;
        $stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
        $stdCOFINS->vCOFINS = $this->format(($stdProd->vProd) * ($i->produto->perc_cofins / 100));
        $nfe->tagCOFINS($stdCOFINS);

        $this->tagIbsCbsNativo($nfe, $itemCont, $stdProd);

        // Combustíveis (quando houver)
        if (strlen($i->produto->descricao_anp) > 5) {
            $stdComb = new \stdClass();
            $stdComb->item    = 1;
            $stdComb->cProdANP= $i->produto->codigo_anp;
            $stdComb->descANP = $i->produto->descricao_anp;
            $stdComb->UFCons  = $venda->cliente->cidade->uf;
            $nfe->tagcomb($stdComb);
        }

        // CEST (quando houver)
        if (strlen($cest) > 0) {
            $std = new \stdClass();
            $std->item = $itemCont;
            $std->CEST = $cest;
            $nfe->tagCEST($std);
        }
    }

    // ========== TOTAIS ==========
    $stdICMSTot = new \stdClass();
    $stdICMSTot->vBC       = $this->format($VBC);
    $stdICMSTot->vICMS     = $this->format($somaICMS);
    $stdICMSTot->vICMSDeson= 0.00;
    $stdICMSTot->vBCST     = 0.00;
    $stdICMSTot->vST       = 0.00;
    $stdICMSTot->vProd     = $this->format($somaProdutos);
    $stdICMSTot->vFrete    = 0.00;
    $stdICMSTot->vSeg      = 0.00;
    $stdICMSTot->vDesc     = $this->format($venda->desconto);
    $stdICMSTot->vII       = 0.00;
    $stdICMSTot->vIPI      = 0.00;
    $stdICMSTot->vPIS      = 0.00;
    $stdICMSTot->vCOFINS   = 0.00;
    $stdICMSTot->vOutro    = 0.00;
    $stdICMSTot->vNF       = $this->format($venda->valor_total);
    $stdICMSTot->vTotTrib  = 0.00;
    $nfe->tagICMSTot($stdICMSTot);

    // ========== TRANSPORTE ==========
    $stdTransp = new \stdClass();
    $stdTransp->modFrete = 9;
    $nfe->tagtransp($stdTransp);



    // ===============================
// CÁLCULO DE TROCO (OBRIGATÓRIO SE TOTAL PAGO > VALOR DA NOTA)
// ===============================

$valorNota = (float) $venda->valor_total;
$totalPago = 0.0;

// Pagamento único
if ($venda->tipo_pagamento != '99') {

    if ($venda->tipo_pagamento == '01') { // Dinheiro
        $totalPago = (float) $venda->dinheiro_recebido;
    } else {
        $totalPago = (float) $valorNota;
    }

} 
// Pagamentos múltiplos
else {
    $totalPago =
        (float) $venda->valor_pagamento_1 +
        (float) $venda->valor_pagamento_2 +
        (float) $venda->valor_pagamento_3;
}

$troco = 0.0;
if ($totalPago > $valorNota) {
    $troco = $totalPago - $valorNota;
}


// ========== PAGAMENTOS ==========
$stdPag = new \stdClass();

if ($troco > 0) {
    $stdPag->vTroco = $this->format($troco);
}

$nfe->tagpag($stdPag);


Log::info('NFC-e | Cálculo de pagamento', [
    'venda_id'        => $venda->id,
    'valor_nota'      => $valorNota,
    'total_pago'      => $totalPago,
    'troco_calculado' => $troco,
    'tipo_pagamento'  => $venda->tipo_pagamento,
    'dinheiro_recebido' => $venda->dinheiro_recebido ?? null,
    'pagamentos_multiplos' => [
        'p1' => $venda->valor_pagamento_1 ?? 0,
        'p2' => $venda->valor_pagamento_2 ?? 0,
        'p3' => $venda->valor_pagamento_3 ?? 0,
    ],
]);





    // Forma única
    if ($venda->tipo_pagamento != '99') {

        $det = new \stdClass();

        // POS sem TEF: 02 débito / 03 crédito
        if ($venda->tipo_pagamento == '02' || $venda->tipo_pagamento == '03') {
            $det->tPag      = ($venda->tipo_pagamento == '02') ? '04' : '03';
            $det->vPag      = $this->format($venda->valor_total);
            $det->tBand     = '99';
            $det->tpIntegra = 2;
            // $det->indPag  = '0';
        }
        // TEF integrado (cartão): 07 crédito / 08 débito
        elseif ($venda->tipo_pagamento == '07' || $venda->tipo_pagamento == '08') {
            $dadosTEF = TransacaoTEF::where('intencao_venda_id', $venda->intencao_venda_id)->first();
            if (!$dadosTEF) {
                Log::channel('nfce')->warning('NFC-e TEF nao encontrada', [
                    'venda_id' => $venda->id,
                    'intencao_venda_id' => $venda->intencao_venda_id,
                ]);
                throw new \Exception('Transação TEF não encontrada para esta venda.');
            }
            if ((int) $dadosTEF->situacao !== 1 || empty($dadosTEF->codigo_autorizacao)) {
                Log::channel('nfce')->warning('NFC-e TEF nao autorizada', [
                    'venda_id' => $venda->id,
                    'intencao_venda_id' => $venda->intencao_venda_id,
                    'transacao_tef_id' => $dadosTEF->id,
                    'situacao' => $dadosTEF->situacao,
                    'codigo_autorizacao' => $dadosTEF->codigo_autorizacao,
                    'cnpj_adquirente' => $dadosTEF->cnpj_adquirente,
                    'mensagem_adquirente' => $dadosTEF->mensagem_adquirente ?? null,
                ]);
                throw new \Exception('Transação TEF ainda não foi autorizada pela adquirente (código de autorização ausente). Aguarde a confirmação do pagamento antes de emitir a NFC-e.');
            }
            $det->tPag      = ($venda->tipo_pagamento == '07') ? '03' : '04';
            $det->vPag      = $this->format($venda->valor_total);
            $det->CNPJ      = preg_replace('/\D/', '', $dadosTEF->cnpj_adquirente);
            $det->tBand     = str_pad($dadosTEF->codigo_bandeira, 2, '0', STR_PAD_LEFT);
            $det->cAut      = $dadosTEF->codigo_autorizacao;
            $det->tpIntegra = 1;
            $det->indPag    = ($venda->tipo_pagamento == '07') ? '1' : '0';
        }
        // TEF Pix: 09
        elseif ($venda->tipo_pagamento == '09') {
            $dadosTEF = TransacaoTEF::where('intencao_venda_id', $venda->intencao_venda_id)->first();
            if (!$dadosTEF) {
                Log::channel('nfce')->warning('PIX | NFC-e Pix TEF nao encontrada', [
                    'venda_id' => $venda->id,
                    'intencao_venda_id' => $venda->intencao_venda_id,
                ]);
                throw new \Exception('Transação TEF não encontrada para esta venda.');
            }
            // Pix via TEF não retorna codigo_autorizacao (isso é normal, não é falha) —
            // a confirmação de pagamento é dada por "situacao" + CNPJ da adquirente.
            if ((int) $dadosTEF->situacao !== 1 || empty($dadosTEF->cnpj_adquirente)) {
                Log::channel('nfce')->warning('PIX | NFC-e Pix TEF nao autorizada', [
                    'venda_id' => $venda->id,
                    'intencao_venda_id' => $venda->intencao_venda_id,
                    'transacao_tef_id' => $dadosTEF->id,
                    'situacao' => $dadosTEF->situacao,
                    'codigo_autorizacao' => $dadosTEF->codigo_autorizacao,
                    'cnpj_adquirente' => $dadosTEF->cnpj_adquirente,
                    'mensagem_adquirente' => $dadosTEF->mensagem_adquirente ?? null,
                ]);
                throw new \Exception('Transação TEF (Pix) ainda não foi confirmada pela adquirente. Aguarde a confirmação do pagamento antes de emitir a NFC-e.');
            }
            // tPag continua 17 (Pix), mas a SEFAZ-MT exige os dados de integração do
            // TEF/POS (grupo "card") também para Pix feito via maquininha, não só cartão.
            $det->tPag      = '17';
            $det->vPag      = $this->format($venda->valor_total);
            $det->CNPJ      = preg_replace('/\D/', '', $dadosTEF->cnpj_adquirente);
            // cAut tem limite de 20 caracteres no schema da NFC-e; o "nsu" do Pix
            // costuma ser o EndToEndId (E2E), que tem 32+ caracteres, então trunca.
            $det->cAut      = !empty($dadosTEF->codigo_autorizacao)
                ? substr($dadosTEF->codigo_autorizacao, 0, 20)
                : substr((string) ($dadosTEF->nsu ?? ''), 0, 20);
            $det->tpIntegra = 1;
            $det->indPag    = '0';

            Log::channel('nfce')->info('PIX | NFC-e Pix TEF detPag montado', [
                'venda_id' => $venda->id,
                'tPag' => $det->tPag,
                'vPag' => $det->vPag,
                'CNPJ' => $det->CNPJ,
                'cAut' => $det->cAut,
                'tpIntegra' => $det->tpIntegra,
            ]);
        }
        // PIX sem TEF
        elseif ($venda->tipo_pagamento == '04') {
            $det->tPag = '17';
            $det->vPag = $this->format($venda->valor_total);
            // não enviar CNPJ/tBand/cAut/tpIntegra

            Log::channel('nfce')->info('PIX | NFC-e Pix sem TEF detPag montado', [
                'venda_id' => $venda->id,
                'tPag' => $det->tPag,
                'vPag' => $det->vPag,
            ]);
        }
        // Dinheiro/outros
        else {
            $det->tPag = '01';
            $det->vPag = ($venda->tipo_pagamento == '01')
                ? $this->format($venda->dinheiro_recebido)
                : $this->format($venda->valor_total);
        }

        Log::channel('nfce')->info('NFC-e detPag forma unica', [
            'venda_id' => $venda->id,
            'tipo_pagamento_sistema' => $venda->tipo_pagamento,
            'tPag' => $det->tPag ?? null,
            'vPag' => $det->vPag ?? null,
            'tpIntegra' => $det->tpIntegra ?? null,
            'tBand' => $det->tBand ?? null,
            'cAut' => $det->cAut ?? null,
        ]);

        $nfe->tagdetPag($det);

    } else {
        // Pagamentos múltiplos (99) – crie um detPag para cada parcela
        if ($venda->valor_pagamento_1 > 0) {
            $p1 = (object)[
                'tPag' => $this->mapTipoPagamentoNfce($venda->tipo_pagamento_1),
                'vPag' => $this->format($venda->valor_pagamento_1),
            ];
            if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_1, $p1->tPag)) { $p1->tBand='99'; $p1->tpIntegra=2; }
            $nfe->tagdetPag($p1);
        }

        if ($venda->tipo_pagamento_2 != null && $venda->valor_pagamento_2 > 0) {
            $p2 = (object)[
                'tPag' => $this->mapTipoPagamentoNfce($venda->tipo_pagamento_2),
                'vPag' => $this->format($venda->valor_pagamento_2),
            ];
            if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_2, $p2->tPag)) { $p2->tBand='99'; $p2->tpIntegra=2; }
            $nfe->tagdetPag($p2);
        }

        if ($venda->tipo_pagamento_3 != null && $venda->valor_pagamento_3 > 0) {
            $p3 = (object)[
                'tPag' => $this->mapTipoPagamentoNfce($venda->tipo_pagamento_3),
                'vPag' => $this->format($venda->valor_pagamento_3),
            ];
            if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_3, $p3->tPag)) { $p3->tBand='99'; $p3->tpIntegra=2; }
            $nfe->tagdetPag($p3);
        }
    }

    // ========== MONTAGEM ==========
    try {
        if (method_exists($nfe, 'montaNFe')) {
            $nfe->montaNFe();
        } elseif (method_exists($nfe, 'monta')) {
            $nfe->monta();
        } else {
            throw new \RuntimeException('Método de montagem do XML não encontrado no NFePHP.');
        }

        $xml = $this->applyIbsCbsCompatibility($nfe->getXML());

        return [
            'chave'  => $nfe->getChave(),
            'xml'    => $xml,
            'nNf'    => $stdIde->nNF,
            'modelo' => $nfe->getModelo()
        ];
    } catch (\Throwable $e) {
        // Retorna erros estruturais do NFePHP e evita quebra fatal do fluxo
        return [
            'erros_xml' => method_exists($nfe, 'getErrors') ? $nfe->getErrors() : [$e->getMessage()],
            'exception' => $e->getMessage()
        ];
    }
}



    public function gerarNFCeVenda($idVenda)
    {
        $venda = Venda::where('id', $idVenda)
            ->first();

        $config = ConfigNota::first();
        $tributacao = Tributacao::first();

        $nfe = new Make('PL_010_V1.30');
        $stdInNFe = new \stdClass();
        $stdInNFe->versao = '4.00'; //versão do layout
        $stdInNFe->Id = null; //se o Id de 44 digitos não for passado será gerado automaticamente
        $stdInNFe->pk_nItem = ''; //deixe essa variavel sempre como NULL

        $infNFe = $nfe->taginfNFe($stdInNFe);

        //IDE
        $stdIde = new \stdClass();
        $stdIde->cUF = $config->cUF;
        $stdIde->cNF = rand(11111111, 99999999);
        $stdIde->natOp = $config->natureza->natureza;

        // $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

        $vendaLast = Venda::lastNFCeVenda();
        $lastNumero = $vendaLast;

        $stdIde->mod = 65;
        $stdIde->serie = $config->numero_serie_nfce;
        $stdIde->nNF = (int)$lastNumero + 1;
        $stdIde->dhEmi = date("Y-m-d\TH:i:sP");
        $stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
        $stdIde->tpNF = 1;
        $stdIde->idDest = 1;
        $stdIde->cMunFG = $config->codMun;
        $stdIde->tpImp = 4;
        $stdIde->tpEmis = 1;
        $stdIde->cDV = 0;
        $stdIde->tpAmb = $config->ambiente;
        $stdIde->finNFe = 1;
        $stdIde->indFinal = 1;
        $stdIde->indPres = 1;
        if ($config->ambiente == 2)
            $stdIde->indIntermed = 0;
        $stdIde->procEmi = '0';
        $stdIde->verProc = '3.10.31';
        //
        $tagide = $nfe->tagide($stdIde);

        $stdEmit = new \stdClass();
        $stdEmit->xNome = $config->razao_social;
        $stdEmit->xFant = $config->nome_fantasia;

        $ie = str_replace(".", "", $config->ie);
        $ie = str_replace("/", "", $ie);
        $ie = str_replace("-", "", $ie);
        $stdEmit->IE = $ie;
        $stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

        $cnpj = str_replace(".", "", $config->cnpj);
        $cnpj = str_replace("/", "", $cnpj);
        $cnpj = str_replace("-", "", $cnpj);
        $stdEmit->CNPJ = $cnpj;

        $emit = $nfe->tagemit($stdEmit);

        // ENDERECO EMITENTE
        $stdEnderEmit = new \stdClass();
        $stdEnderEmit->xLgr = $config->logradouro;
        $stdEnderEmit->nro = $config->numero;
        $stdEnderEmit->xCpl = "";
        $stdEnderEmit->xBairro = $config->bairro;
        $stdEnderEmit->cMun = $config->codMun;
        $stdEnderEmit->xMun = $config->municipio;
        $stdEnderEmit->UF = $config->UF;

        $cep = str_replace("-", "", $config->cep);
        $stdEnderEmit->CEP = $cep;
        $stdEnderEmit->cPais = $config->codPais;
        $stdEnderEmit->xPais = $config->pais;

        $fone = str_replace(" ", "", $config->fone);
        $fone = str_replace("-", "", $fone);
        $stdEnderEmit->fone = $fone;

        $enderEmit = $nfe->tagenderEmit($stdEnderEmit);

        // DESTINATARIO

        if ($venda->cliente->cpf_cnpj != '000.000.000-00') {

            if ($venda->cliente_id != null || $venda->cpf != null) {
                $stdDest = new \stdClass();
                if ($venda->cliente_id != null) {
                    $stdDest->xNome = $venda->cliente->razao_social;
                    $stdDest->indIEDest = "1";

                    $cnpj_cpf = str_replace(".", "", $venda->cliente->cpf_cnpj);
                    $cnpj_cpf = str_replace("/", "", $cnpj_cpf);
                    $cnpj_cpf = str_replace("-", "", $cnpj_cpf);

                    if (strlen($cnpj_cpf) == 14) $stdDest->CNPJ = $cnpj_cpf;
                    else $stdDest->CPF = $cnpj_cpf;

                    $dest = $nfe->tagdest($stdDest);

                    $stdEnderDest = new \stdClass();
                    $stdEnderDest->xLgr = $venda->cliente->rua;
                    $stdEnderDest->nro = $venda->cliente->numero;
                    $stdEnderDest->xCpl = "";
                    $stdEnderDest->xBairro = $venda->cliente->bairro;
                    $stdEnderDest->cMun = $venda->cliente->cidade->codigo;
                    $stdEnderDest->xMun = strtoupper($venda->cliente->cidade->nome);
                    $stdEnderDest->UF = $venda->cliente->cidade->uf;

                    $cep = str_replace("-", "", $venda->cliente->cep);
                    $stdEnderDest->CEP = $cep;
                    $stdEnderDest->cPais = "1058";
                    $stdEnderDest->xPais = "BRASIL";
                    $enderDest = $nfe->tagenderDest($stdEnderDest);
                }
                if ($venda->cpf != null) {

                    $cpf = str_replace(".", "", $venda->cpf);
                    $cpf = str_replace("/", "", $cpf);
                    $cpf = str_replace("-", "", $cpf);
                    $cpf = str_replace(" ", "", $cpf);

                    if ($venda->nome) $stdDest->xNome = $venda->nome;
                    $stdDest->indIEDest = "9";
                    $stdDest->CPF = $cpf;
                    $dest = $nfe->tagdest($stdDest);
                }
            }
        }


        $somaProdutos = 0;
        $somaICMS = 0;
        //PRODUTOS
        $itemCont = 0;
        $somaDesconto = 0;
        $totalItens = count($venda->itens);
        $somaAcrescimo = 0;
        $VBC = 0;

        $somaFederal = 0;
        $somaEstadual = 0;
        $somaMunicipal = 0;
        foreach ($venda->itens as $i) {
            $itemCont++;

            $stdProd = new \stdClass();
            $stdProd->item = $itemCont;
            $stdProd->cEAN = $i->produto->codBarras;
            $stdProd->cEANTrib = $i->produto->codBarras;
            $stdProd->cProd = $i->produto->id;
            $stdProd->xProd = $i->produto->nome;
            // if($i->produto->CST_CSOSN == '500' || $i->produto->CST_CSOSN == '60'){
            // 	$stdProd->cBenef = 'SEM CBENEF';
            // }
            if ($i->produto->cBenef) {
                $stdProd->cBenef = $i->produto->cBenef;
            }

            $ncm = $i->produto->NCM;
            $ncm = str_replace(".", "", $ncm);
            $stdProd->NCM = $ncm;
            $ibpt = IBPT::getIBPT($config->UF, $ncm);

            $stdProd->CFOP = $i->produto->CFOP_saida_estadual;
            $cest = $i->produto->CEST;
            $cest = str_replace(".", "", $cest);
            $stdProd->CEST = $cest;
            $stdProd->uCom = $i->produto->unidade_venda;
            $stdProd->qCom = $i->quantidade;
            $stdProd->vUnCom = $this->format($i->valor);
            $stdProd->vProd = $this->format($i->quantidade * $i->valor);
            $stdProd->uTrib = $i->produto->unidade_venda;
            $stdProd->qTrib = $i->quantidade;
            $stdProd->vUnTrib = $this->format($i->valor);
            $stdProd->indTot = 1;


            //calculo media prod

            if ($venda->acrescimo > 0) {
                if ($itemCont < sizeof($venda->itens)) {
                    $totalVenda = $venda->valor_total;

                    $media = (((($stdProd->vProd - $totalVenda) / $totalVenda)) * 100);
                    $media = 100 - ($media * -1);

                    $tempAcrescimo = ($venda->acrescimo * $media) / 100;
                    $somaAcrescimo += $tempAcrescimo;

                    $stdProd->vOutro = $this->format($tempAcrescimo);
                } else {
                    $stdProd->vOutro = $this->format($venda->acrescimo - $somaAcrescimo);
                }
            }

            if ($venda->pedido_delivery_id > 0) {
                $pedido = PedidoDelivery::find($venda->pedido_delivery_id);
                $somaItens = $pedido->somaItensSemFrete();
                $totalVenda = $venda->valor_total;
                if ($somaItens < $totalVenda) {
                    $vAcr = $totalVenda - $somaItens;

                    if ($itemCont < sizeof($venda->itens)) {

                        $media = (((($stdProd->vProd - $totalVenda) / $totalVenda)) * 100);
                        $media = 100 - ($media * -1);

                        $tempAcrescimo = ($vAcr * $media) / 100;
                        $somaAcrescimo += $tempAcrescimo;

                        $stdProd->vOutro = $this->format($tempAcrescimo);
                    } else {
                        $stdProd->vOutro = $this->format($vAcr - $somaAcrescimo);
                    }
                }
            }
            // fim calculo


            // if($venda->desconto > 0){
            // 	$stdProd->vDesc = $this->format($venda->desconto/$totalItens);
            // }

            //	if($venda->desconto > 0){
            //	if($itemCont < sizeof($venda->itens)){
            //		$totalVenda = $venda->valor_total;

            //		$media = (((($stdProd->vProd - $totalVenda)/$totalVenda))*100);
            //		$media = 100 - ($media * -1);

            //		$tempDesc = ($venda->desconto*$media)/100;
            //		$somaDesconto += $tempDesc;

            //		$stdProd->vDesc = $this->format($tempDesc);
            //	}else{
            //	$stdProd->vDesc = $this->format($venda->desconto - $somaDesconto);
            //	}
            //}

            if ($venda->desconto > 0) {
                if ($itemCont < sizeof($venda->itens)) {
                    $totalVenda = $venda->valor_total;

                    $media    =  ($i->quantidade * $i->valor) / $totalVenda;

                    $tempDesc = $venda->desconto * $media;
                    $somaDesconto += $tempDesc;

                    $stdProd->vDesc = $this->format($tempDesc);
                } else {
                    $stdProd->vDesc = $this->format($venda->desconto - $somaDesconto);
                }
            }


            $somaProdutos += $i->quantidade * $i->valor;


            $prod = $nfe->tagprod($stdProd);

            $tributacao = Tributacao::first();

            $stdImposto = new \stdClass();
            $stdImposto->item = $itemCont;

            if ($ibpt != null) {
                $vProd = $stdProd->vProd;
                $somaFederal = ($vProd * ($ibpt->nacional_federal / 100));
                $somaEstadual += ($vProd * ($ibpt->estadual / 100));
                $somaMunicipal += ($vProd * ($ibpt->municipal / 100));
                $soma = $somaFederal + $somaEstadual + $somaMunicipal;
                $stdImposto->vTotTrib = $soma;
            }

            $imposto = $nfe->tagimposto($stdImposto);

            if ($tributacao->regime == 1) { // regime normal

                $stdICMS = new \stdClass();
                $stdICMS->item = $itemCont;
                $stdICMS->orig = 0;
                $stdICMS->CST = $i->produto->CST_CSOSN;
                $stdICMS->modBC = 0;
                $stdICMS->vBC = $this->format($i->valor * $i->quantidade);
                $stdICMS->pICMS = $this->format($i->produto->perc_icms);
                $stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS / 100);

                if ($i->produto->CST_CSOSN == '500' || $i->produto->CST_CSOSN == '60') {
                    $stdICMS->pRedBCEfet = 0.00;
                    $stdICMS->vBCEfet = 0.00;
                    $stdICMS->pICMSEfet = 0.00;
                    $stdICMS->vICMSEfet = 0.00;
                } else {
                    $VBC += $stdProd->vProd;
                }

                $somaICMS += $stdICMS->vICMS;
                $ICMS = $nfe->tagICMS($stdICMS);
            } else { // regime simples

                $stdICMS = new \stdClass();

                $stdICMS->item = $itemCont;
                $stdICMS->orig = 0;
                $stdICMS->CSOSN = $i->produto->CST_CSOSN;
                $stdICMS->pCredSN = $this->format($i->produto->perc_icms);
                $stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
                $ICMS = $nfe->tagICMSSN($stdICMS);

                $somaICMS = 0;
            }



            $stdPIS = new \stdClass();
            $stdPIS->item = $itemCont;
            $stdPIS->CST = $i->produto->CST_PIS;
            $stdPIS->vBC = $this->format($i->produto->perc_pis) > 0 ? $stdProd->vProd : 0.00;
            $stdPIS->pPIS = $this->format($i->produto->perc_pis);
            $stdPIS->vPIS = $this->format(($stdProd->vProd) * ($i->produto->perc_pis / 100));
            $PIS = $nfe->tagPIS($stdPIS);

            //COFINS
            $stdCOFINS = new \stdClass();
            $stdCOFINS->item = $itemCont;
            $stdCOFINS->CST = $i->produto->CST_COFINS;
            $stdCOFINS->vBC = $this->format($i->produto->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
            $stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
            $stdCOFINS->vCOFINS = $this->format(($stdProd->vProd) *
                ($i->produto->perc_cofins / 100));
            $COFINS = $nfe->tagCOFINS($stdCOFINS);

            $this->tagIbsCbsNativo($nfe, $itemCont, $stdProd);

            if (strlen($i->produto->descricao_anp) > 5) {
                $stdComb = new \stdClass();
                $stdComb->item = 1;
                $stdComb->cProdANP = $i->produto->codigo_anp;
                $stdComb->descANP = $i->produto->descricao_anp;
                $stdComb->UFCons = $venda->cliente->cidade->uf;

                $nfe->tagcomb($stdComb);
            }

            $cest = $i->produto->CEST;
            $cest = str_replace(".", "", $cest);
            $stdProd->CEST = $cest;
            if (strlen($cest) > 0) {
                $std = new \stdClass();
                $std->item = $itemCont;
                $std->CEST = $cest;
                $nfe->tagCEST($std);
            }
        }

        //ICMS TOTAL
        $stdICMSTot = new \stdClass();
        $stdICMSTot->vBC = $this->format($VBC);
        $stdICMSTot->vICMS = $this->format($somaICMS);
        $stdICMSTot->vICMSDeson = 0.00;
        $stdICMSTot->vBCST = 0.00;
        $stdICMSTot->vST = 0.00;
        $stdICMSTot->vProd = $this->format($somaProdutos);

        $stdICMSTot->vFrete = 0.00;

        $stdICMSTot->vSeg = 0.00;
        $stdICMSTot->vDesc = $this->format($venda->desconto);
        $stdICMSTot->vII = 0.00;
        $stdICMSTot->vIPI = 0.00;
        $stdICMSTot->vPIS = 0.00;
        $stdICMSTot->vCOFINS = 0.00;
        $stdICMSTot->vOutro = 0.00;
        $stdICMSTot->vNF = $this->format($venda->valor_total - $venda->desconto);
        $stdICMSTot->vTotTrib = 0.00;
        $ICMSTot = $nfe->tagICMSTot($stdICMSTot);

        //TRANSPORTADORA

        $stdTransp = new \stdClass();
        $stdTransp->modFrete = 9;

        $transp = $nfe->tagtransp($stdTransp);


    

        //if ($venda->tipo_pagamento != '99') {

        //	$stdPag->vTroco = $this->format($venda->troco);
        //if($venda->troco == 0 && ($venda->valor_total != $venda->dinheiro_recebido)){
        //	if($venda->tipo_pagamento != '03' && $venda->tipo_pagamento != '04'){
        //		$stdPag->vTroco = $this->format($venda->dinheiro_recebido - $venda->valor_total);
        //	}
        //	}
        //}

    

        //Resp Tecnico
        //	$stdResp = new \stdClass();
        //	$stdResp->CNPJ = getenv('RESP_CNPJ');
        //	$stdResp->xContato= getenv('RESP_NOME');
        //	$stdResp->email = getenv('RESP_EMAIL');
        //	$stdResp->fone = getenv('RESP_FONE');

        //	$nfe->taginfRespTec($stdResp);

        //DETALHE PAGAMENTO

        if ($venda->tipo_pagamento != '99') {

            $stdDetPag = new \stdClass();
            //$stdDetPag->indPag = 0;
            //$stdDetPag->vPag = $this->format($venda->dinheiro_recebido); //Obs: deve ser informado o valor pago pelo cliente

            if ($venda->tipo_pagamento == '02' || $venda->tipo_pagamento == '03') {

                if ($venda->tipo_pagamento == '02') {
                    $stdDetPag->tPag = '04';
                }

                if ($venda->tipo_pagamento == '03') {
                    $stdDetPag->tPag = '03';
                }

                //$stdDetPag->tBand = $venda->bandeira_cartao;
                //	$stdDetPag->tBand = '02';
                //	if($venda->cAut_cartao != ""){
                //		$stdDetPag->cAut = $venda->cAut_cartao;
                //	}
                //if($venda->cnpj_cartao != ""){
                //	$cnpj = str_replace(".", "", $venda->cnpj_cartao);
                //	$cnpj = str_replace("/", "", $cnpj);
                //	$cnpj = str_replace("-", "", $cnpj);
                //	$stdDetPag->CNPJ = $cnpj;
                //	}
                //	$stdDetPag->tpIntegra = 2;
                $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);
            } elseif ($venda->tipo_pagamento == '07' || $venda->tipo_pagamento == '08') {
                // TEF integrado (cartão): 07 crédito / 08 débito
                $dadosTEF = TransacaoTEF::where('intencao_venda_id', $venda->intencao_venda_id)->first();
                if (!$dadosTEF) {
                    Log::channel('nfce')->warning('NFC-e TEF nao encontrada', [
                        'venda_id' => $venda->id,
                        'intencao_venda_id' => $venda->intencao_venda_id,
                    ]);
                    throw new \Exception('Transacao TEF nao encontrada para esta venda.');
                }
                if ((int) $dadosTEF->situacao !== 1 || empty($dadosTEF->codigo_autorizacao)) {
                    Log::channel('nfce')->warning('NFC-e TEF nao autorizada', [
                        'venda_id' => $venda->id,
                        'intencao_venda_id' => $venda->intencao_venda_id,
                        'transacao_tef_id' => $dadosTEF->id,
                        'situacao' => $dadosTEF->situacao,
                        'codigo_autorizacao' => $dadosTEF->codigo_autorizacao,
                        'cnpj_adquirente' => $dadosTEF->cnpj_adquirente,
                        'mensagem_adquirente' => $dadosTEF->mensagem_adquirente ?? null,
                    ]);
                    throw new \Exception('Transação TEF ainda não foi autorizada pela adquirente (código de autorização ausente). Aguarde a confirmação do pagamento antes de emitir a NFC-e.');
                }
                $stdDetPag->tPag = ($venda->tipo_pagamento == '07') ? '03' : '04';
                $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);
                $stdDetPag->CNPJ = preg_replace('/\D/', '', $dadosTEF->cnpj_adquirente);
                $stdDetPag->tBand = str_pad($dadosTEF->codigo_bandeira, 2, '0', STR_PAD_LEFT);
                $stdDetPag->cAut = $dadosTEF->codigo_autorizacao;
                $stdDetPag->tpIntegra = 1;
                $stdDetPag->indPag = ($venda->tipo_pagamento == '07') ? '1' : '0';
            } elseif ($venda->tipo_pagamento == '09') {
                // TEF Pix
                $dadosTEF = TransacaoTEF::where('intencao_venda_id', $venda->intencao_venda_id)->first();
                if (!$dadosTEF) {
                    Log::channel('nfce')->warning('PIX | NFC-e Pix TEF nao encontrada', [
                        'venda_id' => $venda->id,
                        'intencao_venda_id' => $venda->intencao_venda_id,
                    ]);
                    throw new \Exception('Transacao TEF nao encontrada para esta venda.');
                }
                // Pix via TEF não retorna codigo_autorizacao (isso é normal, não é falha) —
                // a confirmação de pagamento é dada por "situacao" + CNPJ da adquirente.
                if ((int) $dadosTEF->situacao !== 1 || empty($dadosTEF->cnpj_adquirente)) {
                    Log::channel('nfce')->warning('PIX | NFC-e Pix TEF nao autorizada', [
                        'venda_id' => $venda->id,
                        'intencao_venda_id' => $venda->intencao_venda_id,
                        'transacao_tef_id' => $dadosTEF->id,
                        'situacao' => $dadosTEF->situacao,
                        'codigo_autorizacao' => $dadosTEF->codigo_autorizacao,
                        'cnpj_adquirente' => $dadosTEF->cnpj_adquirente,
                        'mensagem_adquirente' => $dadosTEF->mensagem_adquirente ?? null,
                    ]);
                    throw new \Exception('Transação TEF (Pix) ainda não foi confirmada pela adquirente. Aguarde a confirmação do pagamento antes de emitir a NFC-e.');
                }
                // tPag continua 17 (Pix), mas a SEFAZ-MT exige os dados de integração do
                // TEF/POS (grupo "card") também para Pix feito via maquininha, não só cartão.
                $stdDetPag->tPag = '17';
                $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);
                $stdDetPag->CNPJ = preg_replace('/\D/', '', $dadosTEF->cnpj_adquirente);
                // cAut tem limite de 20 caracteres no schema da NFC-e; o "nsu" do Pix
                // costuma ser o EndToEndId (E2E), que tem 32+ caracteres, então trunca.
                $stdDetPag->cAut = !empty($dadosTEF->codigo_autorizacao)
                    ? substr($dadosTEF->codigo_autorizacao, 0, 20)
                    : substr((string) ($dadosTEF->nsu ?? ''), 0, 20);
                $stdDetPag->tpIntegra = 1;
                $stdDetPag->indPag = '0';

                Log::channel('nfce')->info('PIX | NFC-e Pix TEF detPag montado', [
                    'venda_id' => $venda->id,
                    'tPag' => $stdDetPag->tPag,
                    'vPag' => $stdDetPag->vPag,
                    'CNPJ' => $stdDetPag->CNPJ,
                    'cAut' => $stdDetPag->cAut,
                    'tpIntegra' => $stdDetPag->tpIntegra,
                ]);
            } elseif ($venda->tipo_pagamento == '04') {
                $stdDetPag->tPag = '17';
                $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);

                Log::channel('nfce')->info('PIX | NFC-e Pix sem TEF detPag montado', [
                    'venda_id' => $venda->id,
                    'tPag' => $stdDetPag->tPag,
                    'vPag' => $stdDetPag->vPag,
                ]);
            } else {

                if ($venda->tipo_pagamento == '01') {

                    $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);
                } else {

                    $stdDetPag->vPag = $this->format($venda->valor_total - $venda->desconto);
                }

                $stdDetPag->tPag = '01';
            }



            // $std->tpIntegra = 1; //incluso na NT 2015/002
            // $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

            Log::channel('nfce')->info('NFC-e detPag venda normal', [
                'venda_id' => $venda->id,
                'tipo_pagamento_sistema' => $venda->tipo_pagamento,
                'tPag' => $stdDetPag->tPag ?? null,
                'vPag' => $stdDetPag->vPag ?? null,
                'tpIntegra' => $stdDetPag->tpIntegra ?? null,
                'tBand' => $stdDetPag->tBand ?? null,
                'cAut' => $stdDetPag->cAut ?? null,
            ]);

            $detPag = $nfe->tagdetPag($stdDetPag);
        } else {

            if ($venda->valor_pagamento_1 > 0) {

                $stdDetPag1 = new \stdClass();
                //$stdDetPag1->indPag = 0;

                $stdDetPag1->tPag = $this->mapTipoPagamentoNfce($venda->tipo_pagamento_1);
                $stdDetPag1->vPag = $this->format($venda->valor_pagamento_1); //Obs: deve ser informado o valor pago pelo cliente

                if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_1, $stdDetPag1->tPag)) {
                    // $stdDetPag1->CNPJ = '12345678901234';
                    // $stdDetPag3->CNPJ = null;

                    $stdDetPag1->tBand = '99';
                    // $stdDetPag1->cAut = '3333333';
                    $stdDetPag1->tpIntegra = 2;
                }

                // $std->tpIntegra = 1; //incluso na NT 2015/002
                // $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

                $detPag = $nfe->tagdetPag($stdDetPag1);
            } else {
                $stdDetPag = new \stdClass();
                $stdDetPag->tPag = $venda->tipo_pagamento;
                $stdDetPag->vPag = $this->format($venda->valor_total);
                $stdDetPag->xPag = $venda->descricao_pag_outros;
                $detPag = $nfe->tagdetPag($stdDetPag);
            }

            if ($venda->tipo_pagamento_2 != null && $venda->valor_pagamento_2 > 0) {

                $stdDetPag2 = new \stdClass();
                //$stdDetPag2->indPag = 0;

                $stdDetPag2->tPag = $this->mapTipoPagamentoNfce($venda->tipo_pagamento_2);
                $stdDetPag2->vPag = $this->format($venda->valor_pagamento_2); //Obs: deve ser informado o valor pago pelo cliente

                if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_2, $stdDetPag2->tPag)) {
                    // $stdDetPag2->CNPJ = '12345678901234';
                    // $stdDetPag3->CNPJ = null;

                    $stdDetPag2->tBand = '99';
                    // $stdDetPag2->cAut = '3333333';
                    $stdDetPag2->tpIntegra = 2;
                }

                // $std->tpIntegra = 1; //incluso na NT 2015/002
                // $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

                $detPag = $nfe->tagdetPag($stdDetPag2);
            }

            if ($venda->tipo_pagamento_3 != null && $venda->valor_pagamento_3 > 0) {

                $stdDetPag3 = new \stdClass();
                //$stdDetPag1->indPag = 0;

                $stdDetPag3->tPag = $this->mapTipoPagamentoNfce($venda->tipo_pagamento_3);
                $stdDetPag3->vPag = $this->format($venda->valor_pagamento_3); //Obs: deve ser informado o valor pago pelo cliente

                if ($this->isPagamentoCartaoNfce($venda->tipo_pagamento_3, $stdDetPag3->tPag)) {
                    // $stdDetPag3->CNPJ = null;
                    $stdDetPag3->tBand = '99';
                    // $stdDetPag3->cAut = '3333333';
                    $stdDetPag3->tpIntegra = 2;
                }

                // $std->tpIntegra = 1; //incluso na NT 2015/002
                // $std->indPag = '0'; //0= Pagamento à Vista 1= Pagamento à Prazo

                $detPag = $nfe->tagdetPag($stdDetPag3);
            }
        }
        // $stdDetPag = new \stdClass();
        // $stdDetPag->indPag = 0;

        // $stdDetPag->tPag = $venda->tipo_pagamento;
        // $stdDetPag->vPag = $this->format($stdICMSTot->vNF); //Obs: deve ser informado o valor pago pelo cliente

        // if($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04'){
        // 	$stdDetPag->CNPJ = '12345678901234';
        // 	$stdDetPag->tBand = '01';
        // 	$stdDetPag->cAut = '3333333';
        // 	$stdDetPag->tpIntegra = 1;
        // }


        // $detPag = $nfe->tagdetPag($stdDetPag);



        try {
            if (method_exists($nfe, 'montaNFe')) {
                $nfe->montaNFe();
            } elseif (method_exists($nfe, 'monta')) {
                $nfe->monta();
            } else {
                throw new \RuntimeException('Método de montagem do XML não encontrado no NFePHP.');
            }

            $xml = $this->applyIbsCbsCompatibility($nfe->getXML());

            $arr = [
                'chave' => $nfe->getChave(),
                'xml' => $xml,
                'nNf' => $stdIde->nNF,
                'modelo' => $nfe->getModelo()
            ];
            return $arr;
        } catch (\Throwable $e) {
            return [
                'erros_xml' => method_exists($nfe, 'getErrors') ? $nfe->getErrors() : [$e->getMessage()],
                'exception' => $e->getMessage()
            ];
        }
    }


    public function sign($xml)
    {
        return $this->tools->signNFe($xml);
    }

    private function tagIbsCbsNativo(Make $nfe, int $item, \stdClass $stdProd): void
    {
        $vBC = (float) ($stdProd->vProd ?? 0)
            - (float) ($stdProd->vDesc ?? 0)
            + (float) ($stdProd->vOutro ?? 0);

        $valoresItem = $this->calculateIbsCbsValues($vBC);

        $std = new \stdClass();
        $std->item = $item;
        $std->CST = self::IBSCBS_CST;
        $std->cClassTrib = self::IBSCBS_CCLASS_TRIB;
        $std->vBC = $this->format($vBC);
        $std->vIBS = $this->format($valoresItem['vIBS']);
        $std->gIBSUF_pIBSUF = $this->format(self::IBS_UF_ALIQ, 4);
        $std->gIBSUF_vIBSUF = $this->format($valoresItem['vIBSUF']);
        $std->gIBSMun_pIBSMun = $this->format(self::IBS_MUN_ALIQ, 4);
        $std->gIBSMun_vIBSMun = $this->format($valoresItem['vIBSMun']);
        $std->gCBS_pCBS = $this->format(self::CBS_ALIQ, 4);
        $std->gCBS_vCBS = $this->format($valoresItem['vCBS']);

        $nfe->tagIBSCBS($std);

        Log::channel('nfce')->info('NFC-e IBSCBS aplicado via NFePHP PL_010', [
            'item' => $item,
            'CST' => $std->CST,
            'cClassTrib' => $std->cClassTrib,
            'vBC' => $std->vBC,
            'pIBSUF' => $std->gIBSUF_pIBSUF,
            'pIBSMun' => $std->gIBSMun_pIBSMun,
            'pCBS' => $std->gCBS_pCBS,
            'vIBSUF' => $std->gIBSUF_vIBSUF,
            'vIBSMun' => $std->gIBSMun_vIBSMun,
            'vCBS' => $std->gCBS_vCBS,
        ]);
    }

    private function applyIbsCbsCompatibility($xml)
    {
        // IBSCBS agora e gerado pelos metodos nativos da NFePHP no schema PL_010.
        // Nao injeta tags por DOM para evitar estrutura fora do leiaute vigente.
        return $xml;

        if (!is_string($xml) || trim($xml) === '' || strpos($xml, '<IBSCBS>') !== false) {
            return $xml;
        }

        $oldUseErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($oldUseErrors);
            return $xml;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $totaisIbsCbs = [
            'vIBSUF' => 0.0,
            'vIBSMun' => 0.0,
            'vIBS' => 0.0,
            'vCBS' => 0.0,
        ];

        $detNodes = $xpath->query('//nfe:det');
        foreach ($detNodes as $det) {
            $nItem = (int) $det->getAttribute('nItem');
            $prod = $xpath->query('nfe:prod', $det)->item(0);
            $imposto = $xpath->query('nfe:imposto', $det)->item(0);

            if (!$prod || !$imposto || $xpath->query('nfe:IBSCBS', $imposto)->length > 0) {
                continue;
            }

            $vBC = $this->format((float) $this->nodeValue($xpath, 'nfe:vProd', $prod)
                - (float) $this->nodeValue($xpath, 'nfe:vDesc', $prod)
                + (float) $this->nodeValue($xpath, 'nfe:vOutro', $prod), 2);

            $valoresItem = $this->calculateIbsCbsValues((float) $vBC);
            foreach ($totaisIbsCbs as $campo => $valor) {
                $totaisIbsCbs[$campo] += $valoresItem[$campo];
            }

            $imposto->appendChild($this->makeIbsCbsNode($dom, $nItem, $vBC, $valoresItem));
        }

        $total = $xpath->query('//nfe:total')->item(0);
        if ($total && $xpath->query('nfe:IBSCBSTot', $total)->length === 0) {
            $total->appendChild($this->makeIbsCbsTotalNode($dom, $totaisIbsCbs));
        }

        $updatedXml = $dom->saveXML();
        libxml_clear_errors();
        libxml_use_internal_errors($oldUseErrors);

        return $updatedXml ?: $xml;
    }

    private function calculateIbsCbsValues(float $vBC): array
    {
        $vIBSUF = round($vBC * (self::IBS_UF_ALIQ / 100), 2);
        $vIBSMun = round($vBC * (self::IBS_MUN_ALIQ / 100), 2);
        $vCBS = round($vBC * (self::CBS_ALIQ / 100), 2);

        return [
            'vIBSUF' => $vIBSUF,
            'vIBSMun' => $vIBSMun,
            'vIBS' => $vIBSUF + $vIBSMun,
            'vCBS' => $vCBS,
        ];
    }

    private function makeIbsCbsNode(\DOMDocument $dom, $item, $vBC, array $valoresItem)
    {
        $node = $dom->createElementNS(self::NFE_NS, 'IBSCBS');
        $this->appendTextNode($dom, $node, 'CST', self::IBSCBS_CST);
        $this->appendTextNode($dom, $node, 'cClassTrib', self::IBSCBS_CCLASS_TRIB);
        $this->appendTextNode($dom, $node, 'vBC', $vBC);

        $gIBSUF = $dom->createElementNS(self::NFE_NS, 'gIBSUF');
        $this->appendTextNode($dom, $gIBSUF, 'pIBSUF', $this->format(self::IBS_UF_ALIQ, 4));
        $this->appendTextNode($dom, $gIBSUF, 'vIBSUF', $this->format($valoresItem['vIBSUF']));
        $node->appendChild($gIBSUF);

        $gIBSMun = $dom->createElementNS(self::NFE_NS, 'gIBSMun');
        $this->appendTextNode($dom, $gIBSMun, 'pIBSMun', $this->format(self::IBS_MUN_ALIQ, 4));
        $this->appendTextNode($dom, $gIBSMun, 'vIBSMun', $this->format($valoresItem['vIBSMun']));
        $node->appendChild($gIBSMun);

        $gCBS = $dom->createElementNS(self::NFE_NS, 'gCBS');
        $this->appendTextNode($dom, $gCBS, 'pCBS', $this->format(self::CBS_ALIQ, 4));
        $this->appendTextNode($dom, $gCBS, 'vCBS', $this->format($valoresItem['vCBS']));
        $node->appendChild($gCBS);

        Log::channel('nfce')->info('NFC-e IBSCBS compat aplicado no item', [
            'item' => $item,
            'CST' => self::IBSCBS_CST,
            'cClassTrib' => self::IBSCBS_CCLASS_TRIB,
            'vBC' => $vBC,
            'pIBSUF' => self::IBS_UF_ALIQ,
            'pIBSMun' => self::IBS_MUN_ALIQ,
            'pCBS' => self::CBS_ALIQ,
            'vIBSUF' => $this->format($valoresItem['vIBSUF']),
            'vIBSMun' => $this->format($valoresItem['vIBSMun']),
            'vCBS' => $this->format($valoresItem['vCBS']),
        ]);

        return $node;
    }

    private function makeIbsCbsTotalNode(\DOMDocument $dom, array $totaisIbsCbs)
    {
        $node = $dom->createElementNS(self::NFE_NS, 'IBSCBSTot');

        $gIBS = $dom->createElementNS(self::NFE_NS, 'gIBS');
        $gIBSUF = $dom->createElementNS(self::NFE_NS, 'gIBSUF');
        $this->appendTextNode($dom, $gIBSUF, 'vDif', '0.00');
        $this->appendTextNode($dom, $gIBSUF, 'vDevTrib', '0.00');
        $this->appendTextNode($dom, $gIBSUF, 'vIBSUF', $this->format($totaisIbsCbs['vIBSUF']));
        $gIBS->appendChild($gIBSUF);

        $gIBSMun = $dom->createElementNS(self::NFE_NS, 'gIBSMun');
        $this->appendTextNode($dom, $gIBSMun, 'vDif', '0.00');
        $this->appendTextNode($dom, $gIBSMun, 'vDevTrib', '0.00');
        $this->appendTextNode($dom, $gIBSMun, 'vIBSMun', $this->format($totaisIbsCbs['vIBSMun']));
        $gIBS->appendChild($gIBSMun);

        $this->appendTextNode($dom, $gIBS, 'vIBS', $this->format($totaisIbsCbs['vIBS']));
        $node->appendChild($gIBS);

        $gCBS = $dom->createElementNS(self::NFE_NS, 'gCBS');
        $this->appendTextNode($dom, $gCBS, 'vDif', '0.00');
        $this->appendTextNode($dom, $gCBS, 'vDevTrib', '0.00');
        $this->appendTextNode($dom, $gCBS, 'vCBS', $this->format($totaisIbsCbs['vCBS']));
        $node->appendChild($gCBS);

        return $node;
    }

    private function appendTextNode(\DOMDocument $dom, \DOMElement $parent, $name, $value)
    {
        $parent->appendChild($dom->createElementNS(self::NFE_NS, $name, (string) $value));
    }

    private function nodeValue(\DOMXPath $xpath, $query, \DOMNode $context)
    {
        $node = $xpath->query($query, $context)->item(0);
        return $node ? $node->nodeValue : 0;
    }

    //  public function transmitirNfce($signXml, $chave){
    //  	try{
    //  		$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
    //  		$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);
    //  		sleep(3);
    //  		$st = new Standardize();
    //  		$std = $st->toStd($resp);

    //  		if ($std->cStat != 103) {

    //  			return "[$std->cStat] - $std->xMotivo";
    //  		}
    //  		sleep(1);
    //  		$recibo = $std->infRec->nRec;
    //  		$protocolo = $this->tools->sefazConsultaRecibo($recibo);
    //  		sleep(1);
    // // return $protocolo;

    //  		$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
    //  		try {
    //  			$xml = Complements::toAuthorize($signXml, $protocolo);
    //  			header('Content-type: text/xml; charset=UTF-8');
    //  			file_put_contents($public.'xml_nfce/'.$chave.'.xml',$xml);
    //  			return $recibo;
    // 	// $this->printDanfe($xml);
    //  		} catch (\Exception $e) {
    //  			return "Erro: " . $st->toJson($protocolo);
    //  		}

    //  	} catch(\Exception $e){
    //  		return "Erro: ".$e->getMessage() ;
    //  	}

    //  }

    public function retornaXMLAssinado($signXml, $resp, $chave)
    {
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        try {
            $xml = Complements::toAuthorize($signXml, $resp);
            header('Content-type: text/xml; charset=UTF-8');
            file_put_contents($public . 'xml_nfce/' . $chave . '.xml', $xml);
            return 'Sucesso';
        } catch (\Exception $e) {
            return "Erro";
        }
    }

    private function autorizarXmlPorConsultaDaChave(string $signXml, string $chave, string $traceId = ''): ?string
    {
        try {
            $this->tools->model('65');
            $response = $this->runWithSslFallback(function () use ($chave) {
                return $this->tools->sefazConsultaChave($chave);
            });

            $st = new Standardize();
            $std = $st->toStd($response);
            $cStat = (int) ($std->cStat ?? 0);

            Log::channel('nfce')->info('NFC-e consulta chave apos duplicidade/envio', [
                'trace_id' => $traceId,
                'chave' => $chave,
                'cStat' => $cStat,
                'xMotivo' => $std->xMotivo ?? null,
                'nProt' => $std->protNFe->infProt->nProt ?? null,
            ]);

            if ($cStat !== 100) {
                return null;
            }

            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            $xml = Complements::toAuthorize($signXml, $response);
            file_put_contents($public . 'xml_nfce/' . $chave . '.xml', $xml);

            return (string) ($std->protNFe->infProt->nProt ?? 'autorizada');
        } catch (\Throwable $e) {
            Log::channel('nfce')->error('NFC-e falha ao buscar protocolo por consulta da chave', [
                'trace_id' => $traceId,
                'chave' => $chave,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    public function transmitirNfce($signXml, $chave)
    {
        @set_time_limit(120);
        $traceId = uniqid('tx_', true);
        $startedAt = microtime(true);
        Log::channel('nfce')->info('NFC-e transmitir.start', [
            'trace_id' => $traceId,
            'chave' => $chave,
            'provider' => 'SEFAZ_NFEPHP',
            'sincrono' => (int) getenv("NFCE_SINCRONO"),
            'tpAmb' => $this->config['tpAmb'] ?? null,
            'siglaUF' => $this->config['siglaUF'] ?? null,
            'cnpj_prefix' => isset($this->config['cnpj']) ? substr((string) $this->config['cnpj'], 0, 6) . '***' : null,
            'ssl_verify_disabled_env' => $this->mustDisableSoapSecurityByEnv(),
            'soap_timeout' => (int) (getenv('NFCE_SOAP_TIMEOUT') ?: 12),
        ]);
        try {
            $idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
            if (getenv("NFCE_SINCRONO") == 1) {
                $tEnvio = microtime(true);
                $resp = $this->runWithSslFallback(function () use ($signXml, $idLote) {
                    return $this->tools->sefazEnviaLote([$signXml], $idLote, 1);
                });
                Log::channel('nfce')->info('NFC-e transmitir.sefazEnviaLote.ok', [
                    'trace_id' => $traceId,
                    'elapsed_ms' => (int)((microtime(true) - $tEnvio) * 1000)
                ]);

                //	echo "<pre>";
                //  print_r($resp);
                // echo "</pre>";
                //die();

                //sleep(4);
                sleep(1);

                $st = new Standardize();
                $std = $st->toStd($resp);

                if ($std->cStat != 103 && $std->cStat != 104) {
                    Log::channel('nfce')->warning('NFC-e transmitir.cstat_invalido', [
                        'trace_id' => $traceId,
                        'cStat' => $std->cStat ?? null,
                        'xMotivo' => $std->xMotivo ?? null,
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);

                    if ((int) ($std->cStat ?? 0) === 204) {
                        $protocolo = $this->autorizarXmlPorConsultaDaChave($signXml, $chave, $traceId);
                        if (!empty($protocolo)) {
                            Log::channel('nfce')->info('NFC-e duplicidade resolvida por consulta de protocolo', [
                                'trace_id' => $traceId,
                                'chave' => $chave,
                                'protocolo' => $protocolo,
                            ]);
                            return $protocolo;
                        }
                    }

                    return "Erro: [$std->cStat] - $std->xMotivo";
                }
                //sleep(5);

                sleep(1);


                // $recibo = $std->infRec->nRec;
                // $protocolo = $this->tools->sefazConsultaRecibo($recibo);
                // sleep(3);

                // Lote processado (cStat 104) não garante que o documento em si foi
                // autorizado — precisa olhar o cStat do protNFe embutido. Duplicidade
                // (204) normalmente significa que a SEFAZ já processou essa chave antes
                // (ex.: timeout/SSL na tentativa anterior), então a nota pode já estar
                // autorizada — consulta a chave e devolve o protocolo real em vez de erro.
                $innerCStat = (int) ($std->protNFe->infProt->cStat ?? 0);
                if ($innerCStat === 204) {
                    Log::channel('nfce')->warning('NFC-e transmitir.duplicidade_detectada', [
                        'trace_id' => $traceId,
                        'chave' => $chave,
                        'xMotivo' => $std->protNFe->infProt->xMotivo ?? null,
                    ]);
                    $protocolo = $this->autorizarXmlPorConsultaDaChave($signXml, $chave, $traceId);
                    if (!empty($protocolo)) {
                        Log::channel('nfce')->info('NFC-e duplicidade resolvida por consulta de protocolo', [
                            'trace_id' => $traceId,
                            'chave' => $chave,
                            'protocolo' => $protocolo,
                        ]);
                        return $protocolo;
                    }
                }

                $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
                try {
                    $xml = Complements::toAuthorize($signXml, $resp);
                    header('Content-type: text/xml; charset=UTF-8');
                    file_put_contents($public . 'xml_nfce/' . $chave . '.xml', $xml);
                    Log::channel('nfce')->info('NFC-e transmitir.finish', [
                        'trace_id' => $traceId,
                        'chave' => $chave,
                        'cStat' => $std->cStat ?? null,
                        'nProt' => $std->protNFe->infProt->nProt ?? null,
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);
                    return $std->protNFe->infProt->nProt;
                    // $this->printDanfe($xml);
                } catch (\Exception $e) {
                    Log::channel('nfce')->error('NFC-e transmitir.toAuthorize_exception', [
                        'trace_id' => $traceId,
                        'message' => $e->getMessage(),
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);
                    return "Erro: " . $st->toJson($resp);
                }
            } else {
                $tEnvio = microtime(true);
                $resp = $this->runWithSslFallback(function () use ($signXml, $idLote) {
                    return $this->tools->sefazEnviaLote([$signXml], $idLote);
                });
                Log::channel('nfce')->info('NFC-e transmitir.sefazEnviaLote.ok', [
                    'trace_id' => $traceId,
                    'elapsed_ms' => (int)((microtime(true) - $tEnvio) * 1000)
                ]);
                sleep(2);
                $st = new Standardize();
                $std = $st->toStd($resp);

                if ($std->cStat != 103) {
                    Log::channel('nfce')->warning('NFC-e transmitir.cstat_invalido', [
                        'trace_id' => $traceId,
                        'cStat' => $std->cStat ?? null,
                        'xMotivo' => $std->xMotivo ?? null,
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);

                    if ((int) ($std->cStat ?? 0) === 204) {
                        $protocolo = $this->autorizarXmlPorConsultaDaChave($signXml, $chave, $traceId);
                        if (!empty($protocolo)) {
                            Log::channel('nfce')->info('NFC-e duplicidade resolvida por consulta de protocolo', [
                                'trace_id' => $traceId,
                                'chave' => $chave,
                                'protocolo' => $protocolo,
                            ]);
                            return $protocolo;
                        }
                    }

                    return "[$std->cStat] - $std->xMotivo";
                }
                sleep(2);
                $recibo = $std->infRec->nRec;
                $tRecibo = microtime(true);
                $protocolo = $this->runWithSslFallback(function () use ($recibo) {
                    return $this->tools->sefazConsultaRecibo($recibo);
                });
                Log::channel('nfce')->info('NFC-e transmitir.sefazConsultaRecibo.ok', [
                    'trace_id' => $traceId,
                    'recibo' => $recibo,
                    'elapsed_ms' => (int)((microtime(true) - $tRecibo) * 1000)
                ]);
                sleep(3);
                // return $protocolo;

                // Mesma checagem de duplicidade (204) do fluxo síncrono, aqui olhando
                // o retorno da consulta de recibo em vez da resposta direta do lote.
                $stdRecibo = $st->toStd($protocolo);
                $innerCStatAsync = (int) ($stdRecibo->protNFe->infProt->cStat ?? 0);
                if ($innerCStatAsync === 204) {
                    Log::channel('nfce')->warning('NFC-e transmitir.duplicidade_detectada', [
                        'trace_id' => $traceId,
                        'chave' => $chave,
                        'xMotivo' => $stdRecibo->protNFe->infProt->xMotivo ?? null,
                    ]);
                    $protocoloDuplicidade = $this->autorizarXmlPorConsultaDaChave($signXml, $chave, $traceId);
                    if (!empty($protocoloDuplicidade)) {
                        Log::channel('nfce')->info('NFC-e duplicidade resolvida por consulta de protocolo', [
                            'trace_id' => $traceId,
                            'chave' => $chave,
                            'protocolo' => $protocoloDuplicidade,
                        ]);
                        return $protocoloDuplicidade;
                    }
                }

                $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
                try {
                    $xml = Complements::toAuthorize($signXml, $protocolo);
                    header('Content-type: text/xml; charset=UTF-8');
                    file_put_contents($public . 'xml_nfce/' . $chave . '.xml', $xml);
                    Log::channel('nfce')->info('NFC-e transmitir.finish', [
                        'trace_id' => $traceId,
                        'chave' => $chave,
                        'recibo' => $recibo,
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);
                    return $recibo;
                    // $this->printDanfe($xml);
                } catch (\Exception $e) {
                    Log::channel('nfce')->error('NFC-e transmitir.toAuthorize_exception', [
                        'trace_id' => $traceId,
                        'message' => $e->getMessage(),
                        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
                    ]);
                    return "Erro: " . $st->toJson($protocolo);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('nfce')->error('NFC-e transmitir.exception', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => (int)((microtime(true) - $startedAt) * 1000)
            ]);
            return "Erro: " . $e->getMessage();
        }
    }

    public function cancelarNFCe($vendaId, $justificativa)
    {
        try {
            $venda = VendaCaixa::where('id', $vendaId)
                ->first();

            $chave = $venda->chave;
            $xJust = $justificativa;
            $nProt = $this->getProtocolFromAuthorizedXml($chave, $venda->path_xml ?? null, (int)$venda->id);
            if (empty($nProt)) {
                $response = $this->runWithSslFallback(function () use ($chave) {
                    return $this->tools->sefazConsultaChave($chave);
                });
                sleep(1);
                $stdCl = new Standardize($response);
                $arr = $stdCl->toArray();
                $nProt = $arr['protNFe']['infProt']['nProt'];
            }
            sleep(1);

            $response = $this->runWithSslFallback(function () use ($chave, $xJust, $nProt) {
                return $this->tools->sefazCancela($chave, $xJust, $nProt);
            });

            $stdCl = new Standardize($response);
            $std = $stdCl->toStd();
            $arr = $stdCl->toArray();
            $json = $stdCl->toJson();

            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            if ($std->cStat != 128) {
            } else {
                $cStat = $std->retEvento->infEvento->cStat;
                if ($cStat == '101' || $cStat == '135' || $cStat == '155') {
                    //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                    $xml = Complements::toAuthorize($this->tools->lastRequest, $response);
                    file_put_contents($public . 'xml_nfce_cancelada/' . $chave . '.xml', $xml);

                    return $arr;
                } else {
                    return $arr;
                }
            }
        } catch (\Throwable $e) {
            return
                [
                    'mensagem' => $e->getMessage(),
                    'erro' => true
                ];
            //TRATAR
        }
    }

    public function cancelarNFCevenda($vendaId, $justificativa)
    {
        try {
            $venda = Venda::where('id', $vendaId)
                ->first();

            $chave = $venda->chave;
            $xJust = $justificativa;
            $nProt = $this->getProtocolFromAuthorizedXml($chave, $venda->path_xml ?? null, (int)$venda->id);
            if (empty($nProt)) {
                $response = $this->runWithSslFallback(function () use ($chave) {
                    return $this->tools->sefazConsultaChave($chave);
                });
                sleep(1);
                $stdCl = new Standardize($response);
                $arr = $stdCl->toArray();
                $nProt = $arr['protNFe']['infProt']['nProt'];
            }
            sleep(1);

            $response = $this->runWithSslFallback(function () use ($chave, $xJust, $nProt) {
                return $this->tools->sefazCancela($chave, $xJust, $nProt);
            });

            $stdCl = new Standardize($response);
            $std = $stdCl->toStd();
            $arr = $stdCl->toArray();
            $json = $stdCl->toJson();

            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            if ($std->cStat != 128) {
            } else {
                $cStat = $std->retEvento->infEvento->cStat;
                if ($cStat == '101' || $cStat == '135' || $cStat == '155') {
                    //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                    $xml = Complements::toAuthorize($this->tools->lastRequest, $response);
                    file_put_contents($public . 'xml_nfce_cancelada/' . $chave . '.xml', $xml);

                    return $arr;
                } else {
                    return $arr;
                }
            }
        } catch (\Throwable $e) {
            return
                [
                    'mensagem' => $e->getMessage(),
                    'erro' => true
                ];
            //TRATAR
        }
    }


    public function cancelarSubstituicaoNFCe($vendaId, $justificativa, $chaveRef)
    {
        try {
            $venda = VendaCaixa::where('id', $vendaId)
                ->first();

            $chave = $venda->chave;
            $xJust = $justificativa;
            $nProt = $this->getProtocolFromAuthorizedXml($chave, $venda->path_xml ?? null, (int)$venda->id);
            if (empty($nProt)) {
                $response = $this->runWithSslFallback(function () use ($chave) {
                    return $this->tools->sefazConsultaChave($chave);
                });
                sleep(1);
                $stdCl = new Standardize($response);
                $arr = $stdCl->toArray();
                $nProt = $arr['protNFe']['infProt']['nProt'];
            }
            sleep(2);

            $response = $this->runWithSslFallback(function () use ($chave, $xJust, $nProt, $chaveRef) {
                return $this->tools->sefazCancelaPorSubstituicao(
                    $chave,
                    $xJust,
                    $nProt,
                    $chaveRef,
                    '1'
                );
            });

            $stdCl = new Standardize($response);
            $std = $stdCl->toStd();
            $arr = $stdCl->toArray();
            $json = $stdCl->toJson();

            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            if ($std->cStat != 128) {
            } else {
                $cStat = $std->retEvento->infEvento->cStat;
                if ($cStat == '101' || $cStat == '135' || $cStat == '155') {
                    //SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
                    $xml = Complements::toAuthorize($this->tools->lastRequest, $response);
                    file_put_contents($public . 'xml_nfce_cancelada/' . $chave . '.xml', $xml);

                    return $arr;
                } else {
                    return $arr;
                }
            }
        } catch (\Throwable $e) {
            return
                [
                    'mensagem' => $e->getMessage(),
                    'erro' => true
                ];
            //TRATAR
        }
    }

    public function format($number, $dec = 2)
    {
        return number_format((float) $number, $dec, ".", "");
    }

    private function mapTipoPagamentoNfce($tipoPagamento): string
    {
        $tipoPagamento = str_pad((string) $tipoPagamento, 2, '0', STR_PAD_LEFT);

        if ($tipoPagamento === '02') {
            return '04';
        }

        if ($tipoPagamento === '04') {
            return '17';
        }

        return $tipoPagamento;
    }

    private function isPagamentoCartaoNfce($tipoPagamentoSistema, $tipoPagamentoNfce): bool
    {
        return in_array($tipoPagamentoNfce, ['03', '04'], true)
            && $this->mapTipoPagamentoNfce($tipoPagamentoSistema) !== '17';
    }

    public function consultarNFCe($venda)
    {
        try {

            $this->tools->model('65');

            $chave = $venda->chave;
            Log::channel('nfce')->info('NFC-e consultar chave.start', [
                'venda_id' => $venda->id ?? null,
                'chave' => $chave,
                'ssl_verify_disabled_env' => $this->mustDisableSoapSecurityByEnv(),
            ]);

            $response = $this->runWithSslFallback(function () use ($chave) {
                return $this->tools->sefazConsultaChave($chave);
            });

            $stdCl = new Standardize($response);
            $arr = $stdCl->toArray();

            Log::channel('nfce')->info('NFC-e consultar chave.finish', [
                'venda_id' => $venda->id ?? null,
                'chave' => $chave,
                'cStat' => $arr['cStat'] ?? null,
                'xMotivo' => $arr['xMotivo'] ?? null,
                'nProt' => $arr['protNFe']['infProt']['nProt'] ?? null,
            ]);

            // $arr = json_decode($json);
            return json_encode($arr);
        } catch (\Throwable $e) {
            Log::channel('nfce')->error('NFC-e consultar chave.exception', [
                'venda_id' => $venda->id ?? null,
                'chave' => $venda->chave ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return json_encode([
                'erro' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function consultarNFCeRetXML($venda)
    {
        try {

            $this->tools->model('65');

            $chave = $venda->chave;
            Log::channel('nfce')->info('NFC-e consultar XML chave.start', [
                'venda_id' => $venda->id ?? null,
                'chave' => $chave,
                'ssl_verify_disabled_env' => $this->mustDisableSoapSecurityByEnv(),
            ]);

            $response = $this->runWithSslFallback(function () use ($chave) {
                return $this->tools->sefazConsultaChave($chave);
            });

            Log::channel('nfce')->info('NFC-e consultar XML chave.finish', [
                'venda_id' => $venda->id ?? null,
                'chave' => $chave,
                'response_preview' => substr((string) $response, 0, 500),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('nfce')->error('NFC-e consultar XML chave.exception', [
                'venda_id' => $venda->id ?? null,
                'chave' => $venda->chave ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return '';
        }
    }

    public function inutilizar($config, $nInicio, $nFinal, $justificativa, $serie = null)
    {
        try {

            $nSerie = $serie ?: $config->numero_serie_nfce;
            $nIni = $nInicio;
            $nFin = $nFinal;
            $xJust = $justificativa;
            $response = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust);

            $stdCl = new Standardize($response);
            $std = $stdCl->toStd();
            $arr = $stdCl->toArray();
            $json = $stdCl->toJson();

            return $arr;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }


    private function retiraAcentos($texto)
    {
        return preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/", "/(ç)/", "/(Ç)/"), explode(" ", "a A e E i I o O u U n N c C"), $texto);
    }
}
