@echo off
REM ===========================================================================
REM  migrar_vendas_dia.bat
REM  Atalho para rodar scripts\migrar_vendas_dia.php sem digitar o caminho do PHP.
REM
REM  Os dados (vendas de 15/09/2026) estao na ORIGEM = pantanal27092026 (.env).
REM  Voce escolhe o DESTINO com --target.
REM
REM  Como usar (abra o CMD nesta pasta ou arraste o .bat):
REM     migrar_vendas_dia.bat --target=pantanal4                 (SIMULA, nao grava)
REM     migrar_vendas_dia.bat --target=pantanal4 --confirm       (GRAVA de verdade)
REM     migrar_vendas_dia.bat --target=pantanal2 --date=2026-09-15 --confirm
REM
REM  Sem argumentos, ele faz uma SIMULACAO em pantanal4 (seguro).
REM ===========================================================================
setlocal

REM --- diretorio deste .bat (…\pantanal\scripts\) ---
set "SCRIPT_DIR=%~dp0"

REM --- acha o php.exe do Laragon (pega a primeira versao encontrada) ---
set "PHP="
for /d %%D in ("C:\laragon\bin\php\*") do (
    if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)
if not defined PHP (
    where php >nul 2>nul && set "PHP=php"
)
if not defined PHP (
    echo ERRO: nao encontrei o php.exe. Ajuste o caminho no topo deste .bat.
    pause
    exit /b 1
)

REM --- argumentos: se nao passar nada, simula em pantanal4 ---
set "ARGS=%*"
if "%ARGS%"=="" set "ARGS=--target=pantanal4"

echo Usando PHP: %PHP%
echo Rodando: migrar_vendas_dia.php %ARGS%
echo.
"%PHP%" "%SCRIPT_DIR%migrar_vendas_dia.php" %ARGS%

echo.
echo (Terminou. Feche esta janela.)
pause
endlocal
