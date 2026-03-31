$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$lines = [System.IO.File]::ReadAllLines($webPath, [System.Text.Encoding]::UTF8)

# Encontrar a PRIMEIRA ocorrencia de atestados.php (linha 759, dentro do grupo principal)
$insertAfter = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match "require.*atestados\.php") {
        $insertAfter = $i
        Write-Host "Primeira ocorrencia linha $($i+1): $($lines[$i])"
        break  # para na primeira
    }
}

if ($insertAfter -lt 0) { Write-Host "NAO ENCONTRADO"; exit }

# Verificar se os requires novos ja nao existem logo abaixo
$nextLine = $lines[$insertAfter + 1]
if ($nextLine -match "Refatoracao") {
    Write-Host "Requires ja inseridos anteriormente. Removendo duplicata da linha 9780..."
    # Remover o bloco duplicado que foi inserido no lugar errado
    $removeStart = -1
    $removeEnd   = -1
    for ($i = 9770; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match "Refatoracao 30/03/2026") { $removeStart = $i }
        if ($removeStart -ge 0 -and $lines[$i] -match "organograma_v3") { $removeEnd = $i; break }
    }
    if ($removeStart -ge 0 -and $removeEnd -ge 0) {
        $cleaned = $lines[0..($removeStart-1)] + $lines[($removeEnd+1)..($lines.Count-1)]
        [System.IO.File]::WriteAllLines($webPath, $cleaned, [System.Text.Encoding]::UTF8)
        Write-Host "Duplicata removida. Total linhas: $($cleaned.Count)"
    }
    exit
}

$newRequires = @(
    "    // Refatoracao 30/03/2026 - blocos extraidos do web.php",
    "    require __DIR__ . '/cargos_salarios.php';",
    "    require __DIR__ . '/ferias_v3.php';",
    "    require __DIR__ . '/comunicados.php';",
    "    require __DIR__ . '/meu_perfil.php';",
    "    require __DIR__ . '/ponto_eletronico.php';",
    "    require __DIR__ . '/plantoes_sobreaviso.php';",
    "    require __DIR__ . '/atestados_v3.php';",
    "    require __DIR__ . '/contratos_v3.php';",
    "    require __DIR__ . '/medicina.php';",
    "    require __DIR__ . '/declaracoes.php';",
    "    require __DIR__ . '/ouvidoria.php';",
    "    require __DIR__ . '/gestor.php';",
    "    require __DIR__ . '/organograma_v3.php';"
)

$before   = $lines[0..$insertAfter]
$after    = $lines[($insertAfter + 1)..($lines.Count - 1)]
$newLines = $before + $newRequires + $after
[System.IO.File]::WriteAllLines($webPath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "OK - web.php tem agora $($newLines.Count) linhas"
