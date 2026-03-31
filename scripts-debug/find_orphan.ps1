$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Grupo principal fecha na linha 3551 (0-based: 3550) com });
# A partir da 3552 (0-based: 3551) comeca codigo orfao
# Precisamos achar o proximo bloco valido: Route::prefix ou comentario de secao

$orphanStart = 3551  # 0-based, linha 3552
$orphanEnd   = -1

for ($i = $orphanStart; $i -lt $lines.Count; $i++) {
    $t = $lines[$i].Trim()
    # Fim do orfao: uma linha Route::prefix no nivel raiz OU comentario de cabecalho de secao
    if ($t -match "^Route::|^//.*API V3|^//.*Sprint|^require") {
        $orphanEnd = $i - 1
        Write-Host "Orfao de L$($orphanStart+1) ate L$($orphanEnd+1)"
        Write-Host "Proximo bloco em L$($i+1): $t"
        break
    }
}

if ($orphanEnd -lt 0) { Write-Host "Fim nao encontrado"; exit }

# Mostrar as linhas orfas
Write-Host "--- Linhas orfas ($($orphanEnd - $orphanStart + 1) linhas) ---"
for ($i = $orphanStart; $i -le [Math]::Min($orphanEnd, $orphanStart+5); $i++) {
    Write-Host "L$($i+1): $($lines[$i])"
}
Write-Host "..."
Write-Host "L$($orphanEnd+1): $($lines[$orphanEnd])"
