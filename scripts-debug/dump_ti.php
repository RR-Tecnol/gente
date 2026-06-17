#!/usr/bin/env php
<?php

/**
 * Listagem em stdout (var_dump) e em log Laravel de todos USUARIO com USUARIO_EMAIL = ti@saoluis.ma.gov.br.
 *
 *   php dump_ti.php
 */

use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = 'ti@saoluis.ma.gov.br';

if (! Schema::hasTable('USUARIO') || ! Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
    $msg = 'USUARIO_EMAIL ausente; não é possível listar por e-mail.';
    fwrite(STDERR, $msg . "\n");
    Log::error('dump_ti', ['erro' => $msg]);
    exit(1);
}

$q = Usuario::query();
if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['sqlsrv', 'dblib', 'odbc'], true)) {
    $q->whereRaw('LOWER(LTRIM(RTRIM(USUARIO_EMAIL))) = LOWER(?)', [trim($email)]);
} else {
    $q->whereRaw('LOWER(TRIM(USUARIO_EMAIL)) = ?', [strtolower(trim($email))]);
}
$linhas = $q->orderBy('USUARIO_ID')->get(['USUARIO_ID', 'USUARIO_LOGIN', 'USUARIO_NOME', 'USUARIO_EMAIL']);

$payload = $linhas->map(fn ($u) => [
    'USUARIO_ID' => (int) $u->USUARIO_ID,
    'USUARIO_LOGIN' => (string) ($u->USUARIO_LOGIN ?? ''),
    'USUARIO_NOME' => (string) ($u->USUARIO_NOME ?? ''),
    'USUARIO_EMAIL' => (string) ($u->USUARIO_EMAIL ?? ''),
])->all();

var_dump($payload);
echo "\n--- dump_ti: total " . count($payload) . " linha(s) ---\n";

foreach ($payload as $p) {
    Log::info('dump_ti.linhacom_email_ti', [
        'id' => $p['USUARIO_ID'],
        'login' => $p['USUARIO_LOGIN'],
        'nome' => $p['USUARIO_NOME'],
    ]);
}
Log::info('dump_ti.completo', ['email_filtro' => $email, 'total' => count($payload), 'dados' => $payload]);
