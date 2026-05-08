#!/usr/bin/env php
<?php

/**
 * 1) Reatribui e-mail do "admin" se ainda tiver o e-mail do TI.
 * 2) Funde (merge) e remove quaisquer outros USUARIO com o mesmo e-mail / login de TI,
 *    escolhendo keeper canónico dinamicamente.
 *
 *   php nuclear_ti.php [--dry-run] [--force]
 */

use App\Support\Scripts\NuclearTiDuplicados;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$force = in_array('--force', $args, true);

echo "--- nuclear_ti.php " . ($dry ? 'DRY-RUN' : 'EXECUÇÃO') . ($force ? ' [FORCE]' : '') . " ---\n\n";

$r = NuclearTiDuplicados::run(
    NuclearTiDuplicados::DEFAULT_EMAIL,
    null,
    $dry,
    $force
);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($r['ok'] ? 0 : 1);
