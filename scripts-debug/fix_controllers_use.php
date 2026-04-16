<?php
chdir(dirname(__DIR__));
$webPath = 'routes/web.php';
$lines = file($webPath); // preserva line endings
$lineEnding = (strpos($lines[2], "\r\n") !== false) ? "\r\n" : "\n";

// Encontrar linha do Route facade (index 0-based)
$insertAfter = -1;
foreach ($lines as $i => $line) {
    if (trim($line) === 'use Illuminate\Support\Facades\Route;') {
        $insertAfter = $i;
        break;
    }
}
if ($insertAfter === -1) {
    echo "ERRO: linha Route facade nao encontrada\n";
    exit(1);
}

// Verificar se ja existe
foreach ($lines as $line) {
    if (strpos($line, '// Controllers legados') !== false) {
        echo "AVISO: bloco ja existe\n";
        exit(0);
    }
}

// Listar controllers
$content = implode('', $lines);
preg_match_all('/\[([A-Z][a-zA-Z]+Controller)::class/', $content, $m);
$unique = array_unique($m[1]);
sort($unique);

// Montar linhas a inserir
$newLines = [$lineEnding];
$newLines[] = "// Controllers legados — importados automaticamente (fix_controllers_use.php)" . $lineEnding;
foreach ($unique as $c) {
    $newLines[] = "use App\\Http\\Controllers\\{$c};" . $lineEnding;
}
$newLines[] = $lineEnding;

// Inserir após $insertAfter
array_splice($lines, $insertAfter + 1, 0, $newLines);

// Backup
file_put_contents($webPath . '.bak', implode('', file($webPath)));
file_put_contents($webPath, implode('', $lines));
echo "OK: inseridos " . count($unique) . " use statements apos linha " . ($insertAfter + 1) . "\n";
echo "Backup: routes/web.php.bak\n";
