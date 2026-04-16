$srcDir = 'C:\Users\joaob\Desktop\sisgep-job-main\resources\gente-v3\src'
$enc = New-Object System.Text.UTF8Encoding($false, $true)
$bad = New-Object System.Collections.Generic.List[string]

Get-ChildItem $srcDir -Recurse -Filter '*.vue' | ForEach-Object {
    $path = $_.FullName
    $size = $_.Length
    $bytes = [System.IO.File]::ReadAllBytes($path)
    try {
        [void]$enc.GetString($bytes)
    } catch {
        $bad.Add("SIZE=$size PATH=$path")
    }
}

if ($bad.Count -eq 0) {
    Write-Output "NONE FOUND"
} else {
    foreach ($item in $bad) {
        Write-Output $item
    }
}
