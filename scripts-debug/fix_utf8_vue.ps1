$files = @(
    'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src\views\rh\FeriasLicencasView.vue',
    'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src\views\rh\OrganogramaView.vue'
)

foreach ($path in $files) {
    $bytes = [System.IO.File]::ReadAllBytes($path)

    # Ler com UTF-8 leniente (substitui bytes invalidos por U+FFFD)
    $lenient = New-Object System.Text.UTF8Encoding($false, $false)
    $content = $lenient.GetString($bytes)

    # Remover o caractere de substituicao U+FFFD (gerado pelos bytes invalidos)
    $cleaned = $content -replace [char]0xFFFD, ''

    # Gravar de volta como UTF-8 sem BOM
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false, $false)
    [System.IO.File]::WriteAllText($path, $cleaned, $utf8NoBom)

    # Verificar que esta limpo agora
    $enc = New-Object System.Text.UTF8Encoding($false, $true)
    $newBytes = [System.IO.File]::ReadAllBytes($path)
    try {
        [void]$enc.GetString($newBytes)
        Write-Output "OK (clean): $path"
    } catch {
        Write-Output "STILL BAD: $path"
    }
}

Write-Output "Fix concluido."
