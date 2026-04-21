<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== O3-01 Admin CARREIRA_ID ===\n";
$f = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', 1)->first();
if ($f) {
    echo "CARREIRA_ID=" . ($f->CARREIRA_ID ?? 'NULL') . "\n";
    echo "FUNCIONARIO_CLASSE=" . ($f->FUNCIONARIO_CLASSE ?? 'NULL') . "\n";
    echo "FUNCIONARIO_REFERENCIA=" . ($f->FUNCIONARIO_REFERENCIA ?? 'NULL') . "\n";
    echo "DATA_ULTIMA_PROGRESSAO=" . ($f->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? 'NULL') . "\n";
} else {
    echo "FUNCIONARIO_ID=1 nao encontrado\n";
}

echo "\n=== O3-02 BANCO_HORAS table ===\n";
$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='BANCO_HORAS'");
echo count($tables) ? "EXISTS\n" : "MISSING — migration pendente\n";

echo "\n=== O3-02 LOTACAO admin ===\n";
$l = DB::table('LOTACAO')->where('FUNCIONARIO_ID', 1)->whereNull('LOTACAO_DATA_FIM')->first();
if ($l) {
    echo "LOTACAO_ATIVA: SETOR_ID=" . $l->SETOR_ID . " DATA_FIM=NULL\n";
} else {
    echo "NENHUMA LOTACAO ATIVA para FUNCIONARIO_ID=1\n";
}
