#!/usr/bin/env php
<?php

/**
 * Remove USUARIO sem nome alinhados ao mesmo FUNCIONARIO/CPF do login de referência,
 * migrando USUARIO_PERFIL (e vínculos) para o registo keeper.
 *
 *   php hard_cleanup.php [--dry-run] [--force] [email@dominio]
 *
 * Ex.: php hard_cleanup.php ti@saoluis.ma.gov.br
 */

use App\Support\Scripts\HardCleanupUsuariosFantasmas;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$force = in_array('--force', $args, true);
$args = array_values(array_filter($args, fn ($a) => $a !== '--dry-run' && $a !== '--force'));
$email = $args[0] ?? 'ti@saoluis.ma.gov.br';

echo "--- hard_cleanup.php: {$email} " . ($dry ? '(dry-run)' : '(execução)') . ($force ? ' [force]' : '') . " ---\n\n";

$result = HardCleanupUsuariosFantasmas::run($email, $dry, $force);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
