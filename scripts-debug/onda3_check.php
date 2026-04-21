<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$out = [];
// O3-01
$admin = DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->first();
try {
    $pct = DB::table('PROGRESSAO_CONFIG')->count();
    $tst = DB::table('TABELA_SALARIAL')->count();
    $out['O3-01'] = ['admin_exists' => (bool)$admin, 'prog_count' => $pct, 'tab_count' => $tst];

    if ($admin && $pct > 0 && $tst > 0) {
        $fid = DB::table('FUNCIONARIO')->where('USUARIO_ID', $admin->USUARIO_ID)->value('FUNCIONARIO_ID');
        if ($fid) {
            $updated = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $fid)->update([
                'CARREIRA_ID' => 1,
                'FUNCIONARIO_CLASSE' => 'A',
                'FUNCIONARIO_REFERENCIA' => 'I',
                'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => '2023-03-15',
            ]);
            $out['O3-01']['action'] = "CARREIRA_ID atualizado para admin (FUNCIONARIO_ID: $fid), records updated: $updated";
        }
    } else {
        $out['O3-01']['action'] = "PROGRESSAO_CONFIG ou TABELA_SALARIAL vazio (seed pendente)";
    }
} catch (\Exception $e) {
    $out['O3-01'] = ['error' => $e->getMessage(), 'action' => 'Tabelas de progressão não criadas.'];
}

// O3-02
try {
    $bancoCount = DB::table('BANCO_HORAS')->count();
    $out['O3-02'] = ['banco_horas_count' => $bancoCount];
} catch (\Exception $e) {
    $out['O3-02'] = ['banco_horas_count' => 'Tabela Inexistente', 'error' => $e->getMessage()];
}

if ($admin) {
    try {
        $fid = DB::table('FUNCIONARIO')->where('USUARIO_ID', $admin->USUARIO_ID)->value('FUNCIONARIO_ID');
        if ($fid) {
            $lotacao = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $fid)->whereNull('LOTACAO_DATA_FIM')->first();
            if (!$lotacao) {
                // LOTACAO_DATA_FIM is probably filled
                $updated = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $fid)->update(['LOTACAO_DATA_FIM' => null]);
                $out['O3-02']['action'] = "LOTACAO_DATA_FIM zerada para FUNCIONARIO_ID: $fid (records updated: $updated)";
            } else {
                $out['O3-02']['action'] = "LOTACAO_DATA_FIM correta (já é NULL)";
            }
        }
    } catch (\Exception $e) {
        $out['O3-02']['action'] = 'Erro ao verificar lotação: ' . $e->getMessage();
    }
}

echo json_encode($out, JSON_PRETTY_PRINT);
