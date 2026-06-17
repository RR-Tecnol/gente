#!/usr/bin/env php
<?php
/**
 * Gate F.3 / S2.1: falha (exit 1) se existir colisão METHOD+URI no route:list.
 *
 * Uso: php artisan route:list --json 2>/dev/null | php scripts/check_route_duplicates.php
 * Ou:  composer run check-routes
 */
declare(strict_types=1);

$raw = stream_get_contents(STDIN);
if ($raw === false || $raw === '') {
    fwrite(STDERR, "Entrada vazia. Gere com: php artisan route:list --json\n");
    exit(2);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON inválido do route:list.\n");
    exit(2);
}

$counts = [];
foreach ($data as $row) {
    $method = isset($row['method']) ? (string) $row['method'] : '';
    $uri = isset($row['uri']) ? (string) $row['uri'] : '';
    $key = $method . "\t" . $uri;
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

$duplicates = array_filter(
    $counts,
    static function (int $c): bool {
        return $c > 1;
    }
);

if (count($duplicates) === 0) {
    echo 'OK: sem colisão METHOD+URI (' . count($data) . " rotas).\n";
    exit(0);
}

fwrite(STDERR, "FALHA: " . count($duplicates) . " colisão(ões) METHOD+URI:\n");
foreach ($duplicates as $key => $n) {
    fwrite(STDERR, "  ({$n}x) " . str_replace("\t", ' ', $key) . "\n");
}
exit(1);
