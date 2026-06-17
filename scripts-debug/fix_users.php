#!/usr/bin/env php
<?php

/**
 * Garante as 4 personas Lab (e-mail + senha gente@2026) no banco sem depender de timeout do Artisan.
 *
 *   php fix_users.php
 *
 * 1) Tenta `TestPersonasSeeder` (completo: PESSOA, FUNCIONARIO, USUARIO, perfis).
 * 2) Se falhar, insere só USUARIO com updateOrInsert (último recurso; login passa, mas vincule FUNCIONARIO com o seeder completo depois).
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\LoginLookupNormalizer;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- fix_users.php: personas Lab SEMAD ---\n\n";

try {
    Artisan::call('db:seed', ['--class' => 'TestPersonasSeeder', '--force' => true]);
    echo Artisan::output();
    echo "\n[OK] TestPersonasSeeder concluiu.\n";
} catch (\Throwable $e) {
    echo "[AVISO] Seeder falhou: " . $e->getMessage() . "\n";
    echo "Tentando inserir apenas USUARIO (mínimo)…\n\n";

    $senha = md5('gente@2026');
    $contas = [
        ['ti@saoluis.ma.gov.br', 'Lab SEMAD — TI (Super Admin)'],
        ['rh@saoluis.ma.gov.br', 'Lab SEMAD — RH Central'],
        ['folha@saoluis.ma.gov.br', 'Lab SEMAD — Gestão de Folha (Financeiro)'],
        ['gestor.escola@saoluis.ma.gov.br', 'Lab SEMAD — Diretor de Unidade'],
    ];

    foreach ($contas as [$login, $nome]) {
        $loginCanon = LoginLookupNormalizer::forStorage((string) $login);
        $row = [
            'USUARIO_LOGIN' => $loginCanon,
            'USUARIO_SENHA' => $senha,
            'USUARIO_NOME' => $nome,
            'USUARIO_ATIVO' => 1,
        ];
        if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $row['USUARIO_PRIMEIRO_ACESSO'] = 1;
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_ALTERAR_SENHA')) {
            $row['USUARIO_ALTERAR_SENHA'] = 0;
        }
        if (Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
            $row['USUARIO_EMAIL'] = $loginCanon;
        }
        DB::table('USUARIO')->updateOrInsert(
            ['USUARIO_LOGIN' => $loginCanon],
            $row
        );
        echo "  -> updateOrInsert: {$loginCanon}\n";
    }
    echo "\n[OK] Registros mínimos em USUARIO. Rode o seeder completo quando o SQL Server estiver estável.\n";
}

try {
    $emails = ['ti@saoluis.ma.gov.br', 'rh@saoluis.ma.gov.br', 'folha@saoluis.ma.gov.br', 'gestor.escola@saoluis.ma.gov.br'];
    $q = DB::table('USUARIO')->whereIn('USUARIO_LOGIN', $emails)->get(['USUARIO_ID', 'USUARIO_LOGIN', 'USUARIO_ATIVO', 'USUARIO_NOME']);
    echo "\n--- Verificação ---\n";
    foreach ($q as $u) {
        echo sprintf("  ID %d  ATIVO=%s  %s  (%s)\n", $u->USUARIO_ID, (string) $u->USUARIO_ATIVO, $u->USUARIO_LOGIN, $u->USUARIO_NOME);
    }
    if ($q->count() < 4) {
        echo "  (esperado 4 contas; conexão ou ROLLBACK podem ter impedido.)\n";
    }
} catch (\Throwable $e) {
    echo "\nNão foi possível listar USUARIO: " . $e->getMessage() . "\n";
}

echo "\nSenha de todas (legado MD5 no 1.º login; o sistema migra para bcrypt): gente@2026\n";
exit(0);
