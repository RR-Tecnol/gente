$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

Write-Host "Mapeando blocos standalone com inline routes..."
Write-Host ""

$blocks = @()

for ($i = 0; $i -lt $lines.Count; $i++) {
    # Blocos standalone = Route::prefix no nivel raiz (sem indentacao)
    if ($lines[$i] -eq "Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {") {
        # Contar chaves
        $depth = 0; $end = -1
        for ($j = $i; $j -lt $lines.Count; $j++) {
            $open  = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '{'}).Count
            $close = ($lines[$j].ToCharArray() | Where-Object {$_ -eq '}'}).Count
            $depth += $open - $close
            if ($depth -eq 0 -and $j -gt $i) { $end = $j; break }
        }
        if ($end -lt 0) { continue }

        $inner = $lines[($i+1)..($end-1)]
        $hasInline  = ($inner | Where-Object { $_ -match "^\s+Route::(get|post|put|patch|delete)" }).Count
        $hasRequire = ($inner | Where-Object { $_ -match "require" }).Count

        # Pegar cabecalho (linhas de comentario antes do bloco)
        $hdrStart = $i
        for ($k = $i-1; $k -ge [Math]::Max(0,$i-5); $k--) {
            if ($lines[$k] -match "^\s*//|^$") { $hdrStart = $k } else { break }
        }

        $obj = [PSCustomObject]@{
            Start     = $hdrStart
            RouteStart= $i
            End       = $end
            Inline    = $hasInline
            Requires  = $hasRequire
            Preview   = ($inner | Where-Object {$_ -match "Route::(get|post)"} | Select-Object -First 2) -join " | "
        }
        $blocks += $obj
        Write-Host "L$($i+1)-L$($end+1) | Inline:$hasInline Req:$hasRequire | $($obj.Preview.Substring(0,[Math]::Min(80,$obj.Preview.Length)))"
    }
}

Write-Host ""
Write-Host "Total blocos raiz standalone: $($blocks.Count)"
Write-Host "Com apenas inline (sem require): $(($blocks | Where-Object {$_.Inline -gt 0 -and $_.Requires -eq 0}).Count)"
Write-Host "Com apenas require (sem inline): $(($blocks | Where-Object {$_.Inline -eq 0 -and $_.Requires -gt 0}).Count)"
Write-Host "Mistos: $(($blocks | Where-Object {$_.Inline -gt 0 -and $_.Requires -gt 0}).Count)"
