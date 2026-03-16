
# Converte apresentacao-sistema.md em HTML auto-contido com imagens base64
$docDir = "C:\Users\joaob\OneDrive\Desktop\sisgep-job-main\docs"
$mdFile = Join-Path $docDir "apresentacao-sistema.md"
$outFile = Join-Path $docDir "GENTE_v3_Apresentacao_NOVA_11.html"
$imgDir = Join-Path $docDir "screenshots"

$md = Get-Content $mdFile -Raw -Encoding UTF8

# Mantém referências externas relativas (screenshots/xxx.png)
# Não converte para base64 — o HTML fica leve e as imagens ficam na pasta screenshots/

# Converte markdown para HTML simples (headers, bold, tabelas, imagens, listas)
$html = $md

# Headers
$html = $html -replace '(?m)^#{1} (.+)$', '<h1>$1</h1>'
$html = $html -replace '(?m)^#{2} (.+)$', '<h2>$1</h2>'
$html = $html -replace '(?m)^#{3} (.+)$', '<h3>$1</h3>'
$html = $html -replace '(?m)^#{4} (.+)$', '<h4>$1</h4>'

# Negrito e itálico
$html = $html -replace '\*\*([^*]+)\*\*', '<strong>$1</strong>'
$html = $html -replace '\*([^*]+)\*', '<em>$1</em>'

# Imagens (já têm o base64)
$html = [System.Text.RegularExpressions.Regex]::Replace($html,
    '!\[([^\]]*)\]\(([^)]+)\)',
    '<figure><img src="$2" alt="$1"><figcaption>$1</figcaption></figure>'
)

# Linha horizontal
$html = $html -replace '(?m)^---$', '<hr>'

# Listas com bullet
$html = $html -replace '(?m)^- (.+)$', '<li>$1</li>'
$html = [System.Text.RegularExpressions.Regex]::Replace($html,
    '(<li>.+?</li>\n?)+',
    { "<ul>$($args[0].Value)</ul>" }
)

# Tabelas (converte | col | col | em <table>)
$lines = $html -split "`n"
$out = @()
$inTable = $false
foreach ($line in $lines) {
    if ($line -match '^\|') {
        if (-not $inTable) { $out += '<table>'; $inTable = $true }
        if ($line -match '^\|[-| ]+\|$') { continue } # linha separadora
        $cells = ($line -replace '^\||\|$', '') -split '\|' | ForEach-Object { $_.Trim() }
        $tag = if ($cells[0] -match '<strong>') { 'th' } else { 'td' }
        $row = '<tr>' + ($cells | ForEach-Object { "<$tag>$_</$tag>" }) + '</tr>'
        $out += $row
    }
    else {
        if ($inTable) { $out += '</table>'; $inTable = $false }
        $out += $line
    }
}
if ($inTable) { $out += '</table>' }
$html = $out -join "`n"

# Parágrafos — linhas não-vazias que não são tags HTML
$html = [System.Text.RegularExpressions.Regex]::Replace($html,
    '(?m)^(?!<)(?!$)(.+)$',
    '<p>$1</p>'
)

$fullHtml = @"
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GENTE v3 — Apresentação do Sistema</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 40px 20px; }
  .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 48px 56px; box-shadow: 0 4px 32px rgba(0,0,0,0.08); }
  h1 { font-size: 32px; color: #0f172a; margin-bottom: 8px; padding-bottom: 16px; border-bottom: 3px solid #6366f1; }
  h2 { font-size: 22px; color: #1e3a5f; margin: 40px 0 12px; padding-left: 12px; border-left: 4px solid #6366f1; }
  h3 { font-size: 17px; color: #334155; margin: 24px 0 8px; }
  h4 { font-size: 15px; color: #475569; margin: 16px 0 6px; }
  p  { font-size: 14px; color: #475569; line-height: 1.7; margin: 8px 0; }
  ul { margin: 8px 0 12px 24px; }
  li { font-size: 14px; color: #475569; line-height: 1.8; }
  hr { border: none; border-top: 1px solid #e2e8f0; margin: 36px 0; }
  blockquote { background: #f0f9ff; border-left: 4px solid #38bdf8; border-radius: 8px; padding: 12px 16px; margin: 12px 0; font-size: 14px; color: #0369a1; }
  figure { margin: 20px 0; text-align: center; }
  figure img { max-width: 100%; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
  figcaption { font-size: 12px; color: #94a3b8; margin-top: 6px; font-style: italic; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
  th { background: #f1f5f9; color: #334155; font-weight: 700; padding: 10px 14px; text-align: left; border: 1px solid #e2e8f0; }
  td { padding: 9px 14px; border: 1px solid #e2e8f0; color: #475569; vertical-align: top; }
  tr:nth-child(even) td { background: #f8fafc; }
  strong { color: #1e293b; font-weight: 700; }
  .pdf-btn { position: fixed; top: 18px; right: 24px; z-index: 999; background: #6366f1; color: #fff; border: none; border-radius: 8px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 14px rgba(99,102,241,0.35); transition: background .2s; }
  .pdf-btn:hover { background: #4f46e5; }
  @media print {
    @page { size: A4; margin: 15mm 12mm; }
    .pdf-btn { display: none !important; }
    body { background: white; padding: 0; }
    .container { box-shadow: none; border-radius: 0; padding: 20px 32px; max-width: 100%; }
    figure img { box-shadow: none; }
  }
</style>
</head>
<body>
<button class="pdf-btn" onclick="window.print()">⬇ Salvar como PDF</button>
<div class="container">
$html
</div>
</body>
</html>
"@

[IO.File]::WriteAllText($outFile, $fullHtml, [System.Text.Encoding]::UTF8)
Write-Host "HTML gerado em: $outFile"
Write-Host "Tamanho: $([Math]::Round((Get-Item $outFile).Length / 1KB, 0)) KB"
