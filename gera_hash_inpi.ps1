$proj = 'C:\Users\joaob\Desktop\sisgep-job-main\gente'
$excludePattern = '\\(vendor|node_modules|\.git|public\\build-v3|public\\build|storage\\logs|storage\\framework\\cache|storage\\framework\\sessions|storage\\framework\\views)\\'
$files = Get-ChildItem $proj -Recurse -File | Where-Object { $_.FullName -notmatch $excludePattern } | Sort-Object FullName
$sha = [System.Security.Cryptography.SHA256]::Create()
$allBytes = [System.Collections.Generic.List[byte]]::new()
foreach ($f in $files) {
    $allBytes.AddRange([System.IO.File]::ReadAllBytes($f.FullName))
}
$hash = [BitConverter]::ToString($sha.ComputeHash($allBytes.ToArray())).Replace('-','').ToLower()
Write-Host "SHA256: $hash"
Write-Host "Total files hashed: $($files.Count)"
