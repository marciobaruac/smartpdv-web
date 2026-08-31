<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venda;
use App\Models\VendaCaixa;
use App\Models\Cte;
use App\Models\Mdfe;
use App\Models\ConfigNota;
use App\Models\EscritorioContabil;
use Mail;

class EnviarXmlController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $value = session('user_logged');
            if (!$value) {
                return redirect("/login");
            } else {
                if ($value['acesso_fiscal'] == 0) {
                    return redirect("/sempermissao");
                }
            }
            return $next($request);
        });
    }

    public function index()
    {
        return view('enviarXml/list')
            ->with('title', 'Enviar XML');
    }

    public function filtro(Request $request)
    {
        // Intervalo
        $start = $this->parseDate($request->data_inicial);
        $end   = $this->parseDate($request->data_final, true);

        // Filtros opcionais
        $id    = $request->id ?? null;
        $chave = $request->chave ?? null;

        // (updated_at BETWEEN ... OR created_at BETWEEN ...)
        $rangeFilter = function ($q) use ($start, $end) {
            $q->whereBetween('updated_at', [$start, $end])
                ->orWhereBetween('created_at', [$start, $end]);
        };

        // Caminho base (compatível com seu código)
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';

        // -------- Helpers locais --------
        $nomeEntradaZip = function ($model) {
            $saved = trim((string)($model->path_xml ?? ''));
            if ($saved !== '') {
                $base = basename($saved);
                if (!preg_match('/\.xml$/i', $base)) {
                    $base .= '.xml';
                }
                return $base; // usa exatamente o que foi salvo; sem concatenação extra
            }
            // fallback
            if (!empty($model->chave)) {
                return $model->chave . '.xml';
            }
            return ((string)$model->id) . '.xml';
        };

        $acharOrigem = function ($model, array $candidatos) use ($public) {
            $saved = trim((string)($model->path_xml ?? ''));

            // 1) Se path_xml apontar para um caminho relativo existente, usa ele
            if ($saved !== '') {
                $try = $public . ltrim($saved, '/');
                if (file_exists($try)) {
                    return $try;
                }
                // se veio só o “nome” (sem pasta), tente nas pastas candidatas
                $base = basename($saved);
                if (!preg_match('/\.xml$/i', $base)) {
                    $base .= '.xml';
                }
                foreach ($candidatos as $pasta) {
                    $try = rtrim($public . $pasta, '/') . '/' . $base;
                    if (file_exists($try)) return $try;
                }
            }

            // 2) Tenta por chave nas pastas candidatas
            if (!empty($model->chave)) {
                foreach ($candidatos as $pasta) {
                    $try = rtrim($public . $pasta, '/') . '/' . $model->chave . '.xml';
                    if (file_exists($try)) return $try;
                }
            }

            // 3) Tenta por id.xml nas pastas candidatas
            foreach ($candidatos as $pasta) {
                $try = rtrim($public . $pasta, '/') . '/' . $model->id . '.xml';
                if (file_exists($try)) return $try;
            }

            return null;
        };

        // ---------- NFe (Vendas)
        $xml = Venda::where($rangeFilter)
            ->when($id, fn($q) => $q->where('id', $id))
            ->when($chave, fn($q) => $q->where('chave', $chave))
            ->where('estado', 'APROVADO')
            ->get();

        try {
            if ($xml->count() > 0) {
                $zip_file = $public . 'xml.zip';
                $zip = new \ZipArchive();
                $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                foreach ($xml as $x) {
                    $entry = $nomeEntradaZip($x);
                    $src   = $acharOrigem($x, ['xml_nfe', 'xml_nfce']); // alguns projetos misturam
                    if ($src) {
                        $zip->addFile($src, $entry);
                    }
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            // Log::warning($e->getMessage());
        }

        // ---------- CTe
        $xmlCte = collect();
        try {
            $xmlCte = Cte::where($rangeFilter)
                ->when($id, fn($q) => $q->where('id', $id))
                ->when($chave, fn($q) => $q->where('chave', $chave))
                ->where('estado', 'APROVADO')
                ->get();

            if ($xmlCte->count() > 0) {
                $zip_file = $public . 'xmlcte.zip';
                $zip = new \ZipArchive();
                $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                foreach ($xmlCte as $x) {
                    $entry = $nomeEntradaZip($x);
                    $src   = $acharOrigem($x, ['xml_cte']);
                    if ($src) {
                        $zip->addFile($src, $entry);
                    }
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            // opcional
        }

        // ---------- NFC-e (Vendas de Caixa)
        $xmlNfce = collect();
        try {
            $xmlNfce = VendaCaixa::where($rangeFilter)
                ->when($id, fn($q) => $q->where('id', $id))
                ->when($chave, fn($q) => $q->where('chave', $chave))
                ->where('estado', 'APROVADO')
                ->get();

            if ($xmlNfce->count() > 0) {
                $zip_file = $public . 'xmlnfce.zip';
                $zip = new \ZipArchive();
                $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                foreach ($xmlNfce as $x) {
                    $entry = $nomeEntradaZip($x);
                    $src   = $acharOrigem($x, ['xml_nfce']);
                    if ($src) {
                        $zip->addFile($src, $entry);
                    }
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            // opcional
        }

        // ---------- MDF-e
        $xmlMdfe = Mdfe::where($rangeFilter)
            ->when($id, fn($q) => $q->where('id', $id))
            ->when($chave, fn($q) => $q->where('chave', $chave))
            ->where('estado', 'APROVADO')
            ->get();

        if ($xmlMdfe->count() > 0) {
            try {
                $zip_file = $public . 'xmlmdfe.zip';
                $zip = new \ZipArchive();
                $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                foreach ($xmlMdfe as $x) {
                    $entry = $nomeEntradaZip($x);
                    $src   = $acharOrigem($x, ['xml_mdfe']);
                    if ($src) {
                        $zip->addFile($src, $entry);
                    }
                }
                $zip->close();
            } catch (\Exception $e) {
                // opcional
            }
        }

        // ---- view
        $dataInicial = str_replace("/", "-", $request->data_inicial);
        $dataFinal   = str_replace("/", "-", $request->data_final);

        return view('enviarXml/list')
            ->with('xml', $xml ?? collect())
            ->with('xmlNfce', $xmlNfce ?? collect())
            ->with('xmlCte', $xmlCte ?? collect())
            ->with('xmlMdfe', $xmlMdfe ?? collect())
            ->with('dataInicial', $dataInicial)
            ->with('dataFinal', $dataFinal)
            ->with('title', 'Enviar XML');
    }



    public function download()
    {
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        $file = $public . "xml.zip";
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($file);


        return redirect('/enviarXml');
    }

    public function downloadNfce()
    {
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        $file = $public . "xmlnfce.zip";
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($file);

        return redirect('/enviarXml');
    }

    public function downloadCte()
    {
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        $file = $public . "xmlcte.zip";
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($file);

        return redirect('/enviarXml');
    }

    public function downloadMdfe()
    {
        $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
        $file = $public . "xmlmdfe.zip";
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($file);

        return redirect('/enviarXml');
    }

    private function parseDate($date, $plusDay = false)
    {
        if ($plusDay == false)
            return date('Y-m-d', strtotime(str_replace("/", "-", $date)));
        else
            return date('Y-m-d', strtotime("+1 day", strtotime(str_replace("/", "-", $date))));
    }

    public function email($dataInicial, $dataFinal)
    {

        $empresa = ConfigNota::first();
        Mail::send('mail.xml', [
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'empresa' => $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
            'tipo' => 'NFe'
        ], function ($m) {
            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            $escritorio = EscritorioContabil::first();
            if ($escritorio == null) {
                echo "<h1>Configure o email do escritório <a target='_blank' href='/escritorio'>aqui</a></h1>";
                die();
            }
            $nomeEmail = getenv('MAIL_NAME');
            $nomeEmail = str_replace("_", " ", $nomeEmail);
            $m->from(getenv('MAIL_USERNAME'), $nomeEmail);
            $m->subject('Envio de XML');
            $m->attach($public . 'xml.zip');
            $m->to($escritorio->email);
        });
        echo '<h1>Email enviado</h1>';
    }

    public function emailNfce($dataInicial, $dataFinal)
    {

        $empresa = ConfigNota::first();
        Mail::send('mail.xml', [
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'empresa' => $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
            'tipo' => 'NFCe'
        ], function ($m) {
            $escritorio = EscritorioContabil::first();
            if ($escritorio == null) {
                echo "<h1>Configure o email do escritório <a target='_blank' href='/escritorio'>aqui</a></h1>";
                die();
            }
            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';

            $nomeEmail = getenv('MAIL_NAME');
            $nomeEmail = str_replace("_", " ", $nomeEmail);
            $m->from(getenv('MAIL_USERNAME'), $nomeEmail);
            $m->subject('Envio de XML');
            $m->attach($public . 'xmlnfce.zip');
            $m->to($escritorio->email);
        });
        echo '<h1>Email enviado</h1>';
    }

    public function emailCte($dataInicial, $dataFinal)
    {

        $empresa = ConfigNota::first();
        Mail::send('mail.xml', [
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'empresa' => $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
            'tipo' => 'CTe'
        ], function ($m) {
            $escritorio = EscritorioContabil::first();
            if ($escritorio == null) {
                echo "<h1>Configure o email do escritório <a target='_blank' href='/escritorio'>aqui</a></h1>";
                die();
            }
            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            $nomeEmail = getenv('MAIL_NAME');
            $nomeEmail = str_replace("_", " ", $nomeEmail);
            $m->from(getenv('MAIL_USERNAME'), $nomeEmail);
            $m->subject('Envio de XML');
            $m->attach($public . 'xmlcte.zip');
            $m->to($escritorio->email);
        });
        echo '<h1>Email enviado</h1>';
    }

    public function emailMdfe($dataInicial, $dataFinal)
    {

        $empresa = ConfigNota::first();
        Mail::send('mail.xml', [
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'empresa' => $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
            'tipo' => 'MDFe'
        ], function ($m) {
            $escritorio = EscritorioContabil::first();
            if ($escritorio == null) {
                echo "<h1>Configure o email do escritório <a target='_blank' href='/escritorio'>aqui</a></h1>";
                die();
            }
            $public = getenv('SERVIDOR_WEB') ? 'public/' : '';
            $nomeEmail = getenv('MAIL_NAME');
            $nomeEmail = str_replace("_", " ", $nomeEmail);
            $m->from(getenv('MAIL_USERNAME'), $nomeEmail);
            $m->subject('Envio de XML');
            $m->attach($public . 'xmlmdfe.zip');
            $m->to($escritorio->email);
        });
        echo '<h1>Email enviado</h1>';
    }
}
