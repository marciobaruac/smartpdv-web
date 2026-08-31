<?php

$file = __DIR__ . '/../vendor/nfephp-org/sped-da/src/Common/DaCommon.php';

if (!is_file($file)) {
    fwrite(STDERR, "sped-da DaCommon.php nao encontrado, ignorando patch.\n");
    exit(0);
}

$source = file_get_contents($file);

$needle = <<<'PHP'
        if (substr($logo, 0, 24) !== 'data://text/plain;base64') {
            if (is_file($logo)) {
                $logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($logo));
            } else {
                //se não é uma string e nem um file retorna nulo
                return null;
            }
        }
PHP;

$replacement = <<<'PHP'
        if (substr($logo, 0, 24) !== 'data://text/plain;base64') {
            if (is_file($logo)) {
                if (ini_get('allow_url_fopen') == false) {
                    return $logo;
                }
                $logo = 'data://text/plain;base64,'. base64_encode(file_get_contents($logo));
            } else {
                //se não é uma string e nem um file retorna nulo
                return null;
            }
        }
PHP;

if (strpos($source, "ini_get('allow_url_fopen')") !== false) {
    echo "sped-da logo patch ja aplicado.\n";
    exit(0);
}

if (strpos($source, $needle) === false) {
    fwrite(STDERR, "Trecho do sped-da para patch nao encontrado.\n");
    exit(0);
}

file_put_contents($file, str_replace($needle, $replacement, $source));
echo "sped-da logo patch aplicado.\n";
