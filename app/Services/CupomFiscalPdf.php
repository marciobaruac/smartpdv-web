<?php

namespace App\Services;

class CupomFiscalPdf
{
    private const PAGE_WIDTH = 164.4;
    private const PAGE_HEIGHT = 780;
    private const LEFT = 8;
    private const RIGHT = 154;

    private $xml;
    private $pathLogo;

    public function __construct(string $xml, ?string $pathLogo = null)
    {
        $this->xml = $xml;
        $this->pathLogo = $pathLogo;
    }

    public function render(): string
    {
        $dados = $this->dados();
        $logo = $this->logoJpeg();
        $stream = '';

        if ($logo) {
            $stream .= "q\n38 0 0 38 8 724 cm\n/Im1 Do\nQ\n";
        }

        $stream .= $this->textoBloco($dados['emitente'], $logo ? 50 : self::LEFT, 758, 5.1, 6.7);
        $stream .= $this->linha(self::LEFT, 714, self::RIGHT, 714);
        $stream .= $this->textoCentro('DANFE NFC-e', 704, 7, true);
        $stream .= $this->textoCentro('Documento Auxiliar da Nota Fiscal de Consumidor Eletronica', 694, 4.7);
        $stream .= $this->linha(self::LEFT, 686, self::RIGHT, 686);

        $y = 674;
        $stream .= $this->texto('Numero: ' . $dados['numero'] . ' Serie: ' . $dados['serie'], self::LEFT, $y, 5.8, true);
        $stream .= $this->textoDireita($dados['data'], self::RIGHT, $y, 5.3);
        $y -= 10;
        $stream .= $this->texto('Chave:', self::LEFT, $y, 5.3, true);
        $y -= 8;
        foreach ($this->quebrarLinha($dados['chave'], 34) as $linha) {
            $stream .= $this->texto($linha, self::LEFT, $y, 5.2);
            $y -= 7;
        }

        if ($dados['protocolo']) {
            $stream .= $this->texto('Protocolo: ' . $dados['protocolo'], self::LEFT, $y, 5.2);
            $y -= 9;
        }

        $stream .= $this->linha(self::LEFT, $y, self::RIGHT, $y);
        $y -= 11;
        $stream .= $this->texto('DESCRICAO', self::LEFT, $y, 5.8, true);
        $stream .= $this->textoDireita('TOTAL', self::RIGHT, $y, 5.8, true);
        $y -= 10;

        foreach ($dados['itens'] as $item) {
            foreach ($this->quebrarLinha($item['nome'], 24) as $idx => $linha) {
                $stream .= $this->texto($linha, self::LEFT, $y, 5.8, $idx === 0);
                $y -= 8;
            }
            $stream .= $this->texto($item['detalhe'], self::LEFT, $y, 5.2);
            $stream .= $this->textoDireita($item['total'], self::RIGHT, $y, 5.4, true);
            $y -= 11;
        }

        $stream .= $this->linha(self::LEFT, $y + 4, self::RIGHT, $y + 4);
        $stream .= $this->texto('Qtd. Total de Itens', self::LEFT, $y - 7, 5.7, true);
        $stream .= $this->textoDireita($dados['qtd_itens'], self::RIGHT, $y - 7, 5.7, true);
        $stream .= $this->texto('Valor Total', self::LEFT, $y - 17, 5.7, true);
        $stream .= $this->textoDireita($dados['total'], self::RIGHT, $y - 17, 5.7, true);
        $stream .= $this->texto('Desconto', self::LEFT, $y - 27, 5.5);
        $stream .= $this->textoDireita($dados['desconto'], self::RIGHT, $y - 27, 5.5);
        $stream .= $this->texto('Valor a Pagar', self::LEFT, $y - 39, 6.7, true);
        $stream .= $this->textoDireita($dados['pagar'], self::RIGHT, $y - 39, 6.7, true);
        $stream .= $this->linha(self::LEFT, $y - 47, self::RIGHT, $y - 47);
        $stream .= $this->texto('Forma de Pagamento', self::LEFT, $y - 59, 5.5, true);
        $stream .= $this->textoDireita('Valor', self::RIGHT, $y - 59, 5.5, true);

        $ypag = $y - 69;
        foreach ($dados['pagamentos'] as $pagamento) {
            $stream .= $this->texto($pagamento['tipo'], self::LEFT, $ypag, 5.4);
            $stream .= $this->textoDireita($pagamento['valor'], self::RIGHT, $ypag, 5.4);
            $ypag -= 8;
        }

        $stream .= $this->linha(self::LEFT, $ypag - 2, self::RIGHT, $ypag - 2);
        $ypag -= 13;
        $stream .= $this->textoCentro('Consulte pela chave de acesso em', $ypag, 5.1);
        $ypag -= 8;
        foreach ($this->quebrarLinha($dados['consulta'], 36) as $linha) {
            $stream .= $this->textoCentro($linha, $ypag, 4.6);
            $ypag -= 6.5;
        }

        if ($dados['qr_code']) {
            $ypag -= 4;
            $stream .= $this->textoCentro('QR Code NFC-e', $ypag, 5.4, true);
            $ypag -= 8;
            foreach ($this->quebrarLinha($dados['qr_code'], 38) as $linha) {
                $stream .= $this->texto($linha, self::LEFT, $ypag, 4.2);
                $ypag -= 5.6;
            }
        }

        return $this->pdf($stream, $logo);
    }

    private function dados(): array
    {
        $xml = simplexml_load_string($this->xml);
        $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        $infNFe = $this->first($xml->xpath('//nfe:infNFe'));
        $ide = $this->first($xml->xpath('//nfe:ide'));
        $emit = $this->first($xml->xpath('//nfe:emit'));
        $ender = $this->first($xml->xpath('//nfe:emit/nfe:enderEmit'));
        $total = $this->first($xml->xpath('//nfe:ICMSTot'));
        $prot = $this->first($xml->xpath('//nfe:protNFe/nfe:infProt'));
        $qr = $this->first($xml->xpath('//nfe:infNFeSupl/nfe:qrCode'));
        $consulta = $this->first($xml->xpath('//nfe:infNFeSupl/nfe:urlChave'));

        $emitente = array_filter([
            $this->textoLimpo((string) ($emit->xNome ?? '')),
            'CNPJ: ' . $this->formatarCnpj((string) ($emit->CNPJ ?? '')),
            'IE: ' . $this->textoLimpo((string) ($emit->IE ?? '')),
            $this->textoLimpo(trim((string) ($ender->xLgr ?? '') . ', ' . (string) ($ender->nro ?? ''))),
            $this->textoLimpo(trim((string) ($ender->xBairro ?? '') . ' ' . (string) ($ender->xMun ?? '') . '-' . (string) ($ender->UF ?? ''))),
            'CEP: ' . $this->textoLimpo((string) ($ender->CEP ?? '')),
        ]);

        $itens = [];
        foreach ($xml->xpath('//nfe:det') as $det) {
            $prod = $det->prod;
            $qtd = (float) ($prod->qCom ?? 0);
            $valor = (float) ($prod->vUnCom ?? 0);
            $vProd = (float) ($prod->vProd ?? 0);
            $itens[] = [
                'nome' => $this->textoLimpo((string) ($prod->xProd ?? 'Produto')),
                'detalhe' => number_format($qtd, 2, ',', '.') . ' x R$ ' . number_format($valor, 2, ',', '.'),
                'total' => 'R$ ' . number_format($vProd, 2, ',', '.'),
            ];
        }

        $pagamentos = [];
        foreach ($xml->xpath('//nfe:detPag') as $detPag) {
            $pagamentos[] = [
                'tipo' => $this->tipoPagamento((string) ($detPag->tPag ?? '')),
                'valor' => 'R$ ' . number_format((float) ($detPag->vPag ?? 0), 2, ',', '.'),
            ];
        }

        $dhEmi = (string) ($ide->dhEmi ?? $ide->dEmi ?? '');
        $chave = $infNFe ? preg_replace('/\D/', '', (string) $infNFe['Id']) : '';

        return [
            'emitente' => $emitente,
            'numero' => (string) ($ide->nNF ?? ''),
            'serie' => (string) ($ide->serie ?? ''),
            'data' => $this->formatarData($dhEmi),
            'chave' => $chave,
            'protocolo' => (string) ($prot->nProt ?? ''),
            'itens' => $itens,
            'qtd_itens' => (string) count($itens),
            'total' => 'R$ ' . number_format((float) ($total->vProd ?? 0), 2, ',', '.'),
            'desconto' => 'R$ ' . number_format((float) ($total->vDesc ?? 0), 2, ',', '.'),
            'pagar' => 'R$ ' . number_format((float) ($total->vNF ?? 0), 2, ',', '.'),
            'pagamentos' => $pagamentos ?: [['tipo' => 'Pagamento', 'valor' => 'R$ 0,00']],
            'consulta' => $this->textoLimpo((string) ($consulta ?: 'www.sefaz.mt.gov.br/nfce/consultanfce')),
            'qr_code' => $this->textoLimpo((string) $qr),
        ];
    }

    private function first($nodes)
    {
        return is_array($nodes) && isset($nodes[0]) ? $nodes[0] : null;
    }

    private function tipoPagamento(string $tipo): string
    {
        $tipos = [
            '01' => 'Dinheiro',
            '02' => 'Cheque',
            '03' => 'Cartao de Credito',
            '04' => 'Cartao de Debito',
            '05' => 'Credito Loja',
            '10' => 'Vale Alimentacao',
            '11' => 'Vale Refeicao',
            '12' => 'Vale Presente',
            '13' => 'Vale Combustivel',
            '15' => 'Boleto',
            '16' => 'Deposito Bancario',
            '17' => 'Pix',
            '18' => 'Transferencia',
            '19' => 'Fidelidade',
            '90' => 'Sem pagamento',
            '99' => 'Outros',
        ];

        return $tipos[$tipo] ?? ('Pagamento ' . $tipo);
    }

    private function formatarData(string $data): string
    {
        if (!$data) {
            return date('d/m/Y H:i:s');
        }

        try {
            return \Carbon\Carbon::parse($data)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return date('d/m/Y H:i:s');
        }
    }

    private function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    private function textoBloco(array $linhas, float $x, float $y, float $fonte, float $altura): string
    {
        $stream = '';
        foreach ($linhas as $linha) {
            $stream .= $this->texto($linha, $x, $y, $fonte, true);
            $y -= $altura;
        }

        return $stream;
    }

    private function texto(string $texto, float $x, float $y, float $fonte = 7, bool $bold = false): string
    {
        $font = $bold ? 'F2' : 'F1';
        return "BT\n/{$font} {$fonte} Tf\n{$x} {$y} Td\n(" . $this->pdfText($texto, 70) . ") Tj\nET\n";
    }

    private function textoCentro(string $texto, float $y, float $fonte = 7, bool $bold = false): string
    {
        $largura = $this->larguraTexto($texto, $fonte);
        return $this->texto($texto, max(self::LEFT, (self::PAGE_WIDTH - $largura) / 2), $y, $fonte, $bold);
    }

    private function textoDireita(string $texto, float $xDireita, float $y, float $fonte = 7, bool $bold = false): string
    {
        return $this->texto($texto, max(self::LEFT, $xDireita - $this->larguraTexto($texto, $fonte)), $y, $fonte, $bold);
    }

    private function larguraTexto(string $texto, float $fonte): float
    {
        return strlen($this->textoLimpo($texto)) * $fonte * 0.44;
    }

    private function linha(float $x1, float $y1, float $x2, float $y2): string
    {
        return "0.4 w\n{$x1} {$y1} m\n{$x2} {$y2} l\nS\n";
    }

    private function quebrarLinha(string $texto, int $limite): array
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

    private function textoLimpo($texto): string
    {
        $texto = html_entity_decode((string) $texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
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

    private function pdfText(string $texto, int $limite): string
    {
        $texto = substr($this->textoLimpo($texto), 0, $limite);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function logoJpeg(): ?array
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

    private function pdf(string $stream, ?array $logo): string
    {
        $objects = [];
        $xObject = $logo ? ' /XObject << /Im1 6 0 R >>' : '';

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . " " . self::PAGE_HEIGHT . "] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>{$xObject} >> /Contents " . ($logo ? '7' : '6') . " 0 R >>";
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
