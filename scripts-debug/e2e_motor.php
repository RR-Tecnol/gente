<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$log = fopen(__DIR__ . '/e2e_log.txt', 'w');

function w($msg)
{
    global $log;
    fwrite($log, $msg . "\n");
    echo $msg . "\n";
}

w("PASSO 1 — Criar/recuperar folha 202503");
$existe = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', '202503')->first();
if ($existe) {
    $folhaId = $existe->FOLHA_ID;
    w("  Já existe FOLHA_ID={$folhaId}");
} else {
    $folhaId = DB::table('FOLHA')->insertGetId([
        'FOLHA_COMPETENCIA' => '202503',
        'FOLHA_STATUS' => 'Aberta',
        'FOLHA_DESCRICAO' => 'Folha Marco 2026',
    ]);
    w("  CRIADA FOLHA_ID={$folhaId}");
}

w("\nPASSO 2 — Folha: " . json_encode(DB::table('FOLHA')->find($folhaId)));

w("\nPASSO 3 — Rodando motor para FOLHA_ID={$folhaId}");
try {
    $motor = new App\Services\MotorFolhaService();
    $result = $motor->calcularFolha((int) $folhaId);
    w("ok=" . ($result['ok'] ? 'true' : 'false'));
    foreach ($result as $k => $v) {
        if (!is_array($v))
            w("  {$k}: {$v}");
    }
} catch (\Throwable $e) {
    w("EXCEPTION: " . $e->getMessage());
    w("File: " . $e->getFile() . " Line: " . $e->getLine());
    w($e->getTraceAsString());
}

w("\nPASSO 4 — DETALHE_FOLHA count: " . DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folhaId)->count());

fclose($log);
w("\nLog gravado em e2e_log.txt");
