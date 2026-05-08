#!/usr/bin/env php
<?php

/**
 * Unifica USUARIO duplicados por USUARIO_LOGIN / USUARIO_EMAIL (default: ti@saoluis.ma.gov.br),
 * escolhendo keeper canónico dinamicamente.
 *
 *   php deep_clean.php [--dry-run] [--force] [email@dominio]
 */

use App\Support\Scripts\DeepCleanByLogin;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$force = in_array('--force', $args, true);
$args = array_values(array_filter($args, fn ($a) => $a !== '--dry-run' && $a !== '--force'));
$email = $args[0] ?? 'ti@saoluis.ma.gov.br';

echo "--- deep_clean.php: {$email} " . ($dry ? '(dry-run)' : '(execução)') . ($force ? ' [force]' : '') . " ---\n\n";

$result = DeepCleanByLogin::run($email, null, $dry, $force);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

exit($result['ok'] ? 0 : 1);
