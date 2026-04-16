$srcDir = 'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src'
$problemas = @()

Get-ChildItem $srcDir -Recurse -Include '*.vue','*.js','*.ts','*.css' | ForEach-Object {
    $file = $_.FullName
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file)
        # Detect UTF-8 invalid sequences: procurar bytes 0xE2 0x80 0x94 (â€") ou sequencias corrompidas
        # Mais simples: tentar ler como UTF-8 strict e capturar falhas
        $enc = New-Object System.Text.UTF8Encoding($false, $true)
        [void]$enc.GetString($bytes)
    } catch {
        $problemas += $file
        Write-Host "ENCODING CORROMPIDO: $file"
    }
}

Write-Host ""
if ($problemas.Count -eq 0) {
    Write-Host "Nenhum arquivo com encoding corrompido encontrado via UTF8 strict."
    Write-Host "Buscando padrão de bytes 0xE2 0x94 (separadores corrompidos)..."
    
    Get-ChildItem $srcDir -Recurse -Include '*.vue','*.js','*.ts' | ForEach-Object {
        $content = [System.IO.File]::ReadAllText($_.FullName, [System.Text.Encoding]::UTF8)
        # â"€ = U+2500 BOX DRAWINGS LIGHT HORIZONTAL — aparece como â"€ quando lido como latin1
        if ($content -match 'â"€|â€"|Ã‡|Ã£|Ã©|Ã ') {
            Write-Host "CARACTERES CORROMPIDOS: $($_.FullName)"
        }
    }
}

Write-Host ""
Write-Host "=== DONE ==="
