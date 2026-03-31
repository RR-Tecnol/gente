$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar todos os blocos Route::prefix standalone que contem apenas require
# (esses sao os wrappers que ficaram apos a substituicao)
# Mas agora verificar: tem codigo inline entre o require e o }); ?

$problems = @()
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -eq "Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {") {
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $o = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $c = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $o - $c
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }
        $inner = $lines[($i+1)..($end-1)] | Where-Object { $_.Trim() -ne "" }
        $hasInline  = ($inner | Where-Object { $_ -match "^\s+Route::" }).Count
        $hasRequire = ($inner | Where-Object { $_ -match "require" }).Count
        Write-Host "L$($i+1)-L$($end+1): inline=$hasInline req=$hasRequire size=$($end-$i)"
        if ($hasInline -gt 0 -and $hasRequire -gt 0) {
            Write-Host "  ** MISTO - tem inline E require **"
        }
    }
}
