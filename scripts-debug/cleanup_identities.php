#!/usr/bin/env php
<?php

/**
 * Alias de conveniência para unificar USUARIO duplicados (chama UnificarUsuarios).
 *
 *   php cleanup_identities.php
 *   php cleanup_identities.php --dry-run
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dry = in_array('--dry-run', $argv ?? [], true);

$r = \App\Support\Scripts\UnificarUsuarios::run($dry);

echo "--- cleanup_identities (UnificarUsuarios) " . ($dry ? "[DRY-RUN]\n" : "\n");
echo "Grupos: {$r['grupos']}, removidos: {$r['removidos']}\n";
foreach ($r['detalhe'] as $d) {
    if (isset($d['erro'])) {
        echo "  ERRO: {$d['erro']}\n";
        continue;
    }
    echo "  Manter {$d['manter']}; removidos: " . json_encode($d['removidos'] ?? []) . "\n";
}

exit(0);
