<?php
chdir(dirname(__DIR__));
$content = file_get_contents('routes/web.php');
preg_match_all('/\[([A-Z][a-zA-Z]+Controller)::class/', $content, $m);
$unique = array_unique($m[1]);
sort($unique);
$missing = [];
foreach ($unique as $c) {
    $hasUse = strpos($content, 'use App\\Http\\Controllers\\' . $c) !== false;
    if (!$hasUse) {
        $missing[] = $c;
        echo "MISSING: $c\n";
    } else {
        echo "OK:      $c\n";
    }
}
echo "\n---MISSING: " . count($missing) . " / TOTAL: " . count($unique) . "\n";
