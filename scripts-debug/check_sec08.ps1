$base = "C:\Users\joaob\Desktop\sisgep-job-main"

Write-Host "=== LINT web.php ==="
Write-Host (php -l "$base\routes\web.php" 2>&1)
Write-Host "Linhas=$((Get-Content "$base\routes\web.php").Count)"

Write-Host "=== sanitize.js ==="
$sp = "$base\resources\gente-v3\src\plugins\sanitize.js"
Write-Host "Existe=$((Test-Path $sp))"
if (Test-Path $sp) {
    $s = Get-Content $sp -Raw
    Write-Host "DOMPurify=$($s.Contains('DOMPurify'))"
    Write-Host "ALLOWED_TAGS=$($s.Contains('ALLOWED_TAGS'))"
    Write-Host "export=$($s.Contains('export'))"
}

Write-Host "=== package.json ==="
$p = Get-Content "$base\resources\gente-v3\package.json" -Raw
Write-Host "dompurify=$($p.Contains('dompurify'))"

Write-Host "=== ComunicadosView v-html ==="
$c = Get-Content "$base\resources\gente-v3\src\views\ComunicadosView.vue" -Raw
Write-Host "sanitize importado=$($c.Contains('sanitize'))"
$lines = Get-Content "$base\resources\gente-v3\src\views\ComunicadosView.vue"
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "v-html") {
        Write-Host "  L$($i+1): $($lines[$i].Trim())"
    }
}

Write-Host "=== v-html sem sanitize em todo o projeto ==="
Get-ChildItem -Recurse -Path "$base\resources\gente-v3\src" -Filter "*.vue" |
    ForEach-Object {
        $fc = Get-Content $_.FullName
        for ($i = 0; $i -lt $fc.Count; $i++) {
            if ($fc[$i] -match "v-html" -and $fc[$i] -notmatch "sanitize") {
                Write-Host "  $($_.Name) L$($i+1): $($fc[$i].Trim())"
            }
        }
    }
