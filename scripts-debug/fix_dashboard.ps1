$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines   = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# L3550 (0-based: 3549) = "    });"  ultimo Route::get do dashboard
# L3551 (0-based: 3550) = ""         linha vazia
# L3552 (0-based: 3551) = "            // campos salariais" ORFAO
# L3553 (0-based: 3552) = Route::prefix web (autocadastro)

# Acoes:
# 1. Remover linha 3552 (fragmento orfao)
# 2. Inserir });" apos L3550 para fechar o bloco do dashboard

$insertAfter = 3549   # 0-based: apos L3550
$removeIdx   = 3551   # 0-based: L3552 fragmento orfao (ajustado apos insercao)

# Primeiro: inserir o }); do dashboard
$before   = $lines[0..$insertAfter]
$closing  = @("", "});  // fim do bloco dashboard api/v3")
$after    = $lines[($insertAfter+1)..($lines.Count-1)]
$newLines = $before + $closing + $after

# Agora a linha orfaa esta em indice 3553 (3551 + 2 inseridas = 3553)
$removeIdx2 = 3553  # 0-based

Write-Host "Inserindo }); apos L$($insertAfter+1)"
Write-Host "Removendo fragmento orfao L$($removeIdx2+1): $($newLines[$removeIdx2])"

# Remover o fragmento
$final = $newLines[0..($removeIdx2-1)] + $newLines[($removeIdx2+1)..($newLines.Count-1)]

Copy-Item $webPath "$webPath.bak9" -Force
[System.IO.File]::WriteAllLines($webPath, $final, [System.Text.Encoding]::UTF8)
Write-Host "Concluido. Linhas: $($lines.Count) -> $($final.Count)"
