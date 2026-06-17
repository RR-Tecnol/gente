<?php
$files = glob('routes/*.php');
foreach ($files as $f) {
    $c = file_get_contents($f);
    $lines = explode("\n", $c);
    foreach ($lines as $i => $l) {
        if (preg_match('/(DB::(?:select|statement|unprepared)|whereRaw|orderByRaw)\s*\(/i', $l) && preg_match('/\$[a-zA-Z_]/', $l)) {
            echo $f . ':' . ($i+1) . ':' . trim($l) . "\n";
        }
    }
}
