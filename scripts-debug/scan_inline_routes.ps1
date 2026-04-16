$webphp = 'C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php'
$lines = Get-Content $webphp
$out = @()

# Scan L925-4946 (indices 924-4945)
$currentSection = ''
$lineNum = 0

for ($i = 924; $i -lt 4946 -and $i -lt $lines.Count; $i++) {
    $ln = $i + 1
    $l = $lines[$i]

    # Detect section comments
    if ($l -match '//\s*(GAP-\d+|GAP-[A-Z]+|Sprint\s*\d|Avalia|avalia|holerite|autocadastro|Painel|dashboard|ERP|Bloco|secr)') {
        $section = $l.Trim()
        Write-Host "  SECTION L${ln}: $section"
    }

    # Detect Route:: opening lines
    if ($l -match "^\s+(Route::(get|post|put|delete|patch|middleware|prefix|resource|any)\()") {
        Write-Host "  ROUTE  L${ln}: $($l.Trim().Substring(0, [Math]::Min(80, $l.Trim().Length)))"
    }
}
