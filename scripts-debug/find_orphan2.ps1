$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Orfao comeca na linha 3551 (0-based) = L3552
# Procurar onde o codigo orfao termina: proximo Route::prefix no nivel raiz
$orphanStart = 3551  # 0-based

$orphanEnd = -1
for ($i = $orphanStart; $i -lt $lines.Count; $i++) {
    $t = $lines[$i].Trim()
    # Fim: linha vazia seguida de comentario de secao, ou Route::prefix raiz com middleware
    if ($i -gt $orphanStart + 5 -and $t -match "^Route::prefix\('api/v3'\)->middleware") {
        # Verificar que a linha anterior e vazia ou comentario
        $prev = $lines[$i-1].Trim()
        if ($prev -eq "" -or $prev -match "^//") {
            $orphanEnd = $i - 1
            Write-Host "Orfao: L$($orphanStart+1) ate L$($orphanEnd+1)"
            Write-Host "Proximo bloco: L$($i+1): $t"
            break
        }
    }
}

if ($orphanEnd -lt 0) {
    # Tentar outra estrategia: encontrar o proximo bloco de requires
    for ($i = $orphanStart; $i -lt $lines.Count; $i++) {
        $t = $lines[$i].Trim()
        if ($i -gt $orphanStart + 5 -and $t -match "^//\s+(API V3|Sprint|ERP|Modulo)") {
            $orphanEnd = $i - 1
            Write-Host "Orfao: L$($orphanStart+1) ate L$($orphanEnd+1)"
            Write-Host "Proximo: L$($i+1): $t"
            break
        }
    }
}

if ($orphanEnd -lt 0) { Write-Host "Nao encontrado"; exit }

Write-Host "Total linhas orfas: $($orphanEnd - $orphanStart + 1)"
Write-Host "Ultima linha orfa: $($lines[$orphanEnd])"
