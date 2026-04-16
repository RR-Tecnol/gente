<?php
chdir(dirname(__DIR__));
$content = file_get_contents('routes/web.php');
preg_match_all('/\[([A-Z][a-zA-Z]+Controller)::class/', $content, $m);
$unique = array_unique($m[1]);
sort($unique);
$notFound = [];
foreach ($unique as $c) {
    $file = "app/Http/Controllers/{$c}.php";
    if (!file_exists($file)) {
        $notFound[] = $c;
        echo "NOT_FOUND: $c\n";
    }
}
if (empty($notFound)) {
    echo "ALL " . count($unique) . " CONTROLLERS EXIST ON DISK\n";
} else {
    echo "\n---NOT_FOUND: " . count($notFound) . "\n";
}
