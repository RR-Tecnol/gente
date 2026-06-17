<?php

function patchRouteFile($filePath) {
    if (!file_exists($filePath)) {
        echo "File $filePath not found\n";
        return;
    }
    
    $content = file_get_contents($filePath);
    $targets = [
        "Route::post('/atestados', function",
        "Route::post('/declaracoes', function",
        "Route::post('/abono-faltas', function"
    ];
    
    $modified = false;
    foreach ($targets as $target) {
        $offset = 0;
        while (($pos = strpos($content, $target, $offset)) !== false) {
            // Check if already patched
            if (strpos(substr($content, 0, $pos), "'upload.safe'") !== false) {
                // this is a crude check, let's just find the end of the function.
            }
            
            // Find the ending '});' for this block
            // Start looking for braces after the initial '{'
            $braceCount = 0;
            $inString = false;
            $stringChar = '';
            $startOffset = strpos($content, '{', $pos);
            if ($startOffset === false) {
                $offset = $pos + 1;
                continue;
            }
            
            $endPos = -1;
            for ($i = $startOffset; $i < strlen($content); $i++) {
                $char = $content[$i];
                if ($inString) {
                    if ($char === $stringChar && $content[$i-1] !== '\\') {
                        $inString = false;
                    }
                } else {
                    if ($char === "'" || $char === '"') {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === '{') {
                        $braceCount++;
                    } elseif ($char === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $endPos = $i;
                            break;
                        }
                    }
                }
            }
            
            if ($endPos !== -1) {
                // The end is usually '});'
                $semicolonPos = strpos($content, ');', $endPos);
                if ($semicolonPos !== false && $semicolonPos - $endPos <= 2) {
                    // Check if middleware is already there
                    $afterClosure = substr($content, $endPos + 1, 30);
                    if (strpos($afterClosure, 'middleware') === false) {
                        $content = substr_replace($content, ")->middleware('upload.safe');", $semicolonPos, 2);
                        $modified = true;
                    }
                }
            }
            
            $offset = $pos + 1;
        }
    }
    
    if ($modified) {
        file_put_contents($filePath, $content);
        echo "Patched $filePath\n";
    } else {
        echo "No changes for $filePath\n";
    }
}

patchRouteFile(__DIR__ . '/routes/web.php');
patchRouteFile(__DIR__ . '/routes/atestados_v3.php');
patchRouteFile(__DIR__ . '/routes/atestados.php');
