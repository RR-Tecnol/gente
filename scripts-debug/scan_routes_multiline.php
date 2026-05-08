<?php
$files = glob('routes/*.php');
foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Find DB::statement("... {$v}")
    if (preg_match_all('/(DB::(?:select|statement|unprepared)|whereRaw|orderByRaw)\s*\(\s*(["\'])(.*?)\2/is', $c, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[3] as $match) {
            $queryStr = $match[0];
            $offset = $match[1];
            
            // If the query string has $var or {$var
            if (preg_match('/\$[a-zA-Z_]|\{\$/', $queryStr)) {
                // Determine line number
                $linesBefore = substr($c, 0, $offset);
                $lineNo = substr_count($linesBefore, "\n") + 1;
                echo $f . ':' . $lineNo . " -> " . substr($queryStr, 0, 50) . "...\n";
            }
        }
    }
}
