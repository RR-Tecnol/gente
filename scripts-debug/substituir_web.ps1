# Substitui blocos standalone no web.php por requires
# Usa marcadores unicos dos comentarios de cabecalho de cada bloco

$webPath = "C:\Users\joaob\Desktop\sisgep-job-main\routes\web.php"
$txt = [System.IO.File]::ReadAllText($webPath, [System.Text.Encoding]::UTF8)
$orig = $txt.Length

function Replace-Block {
    param([string]$anchor, [string]$require, [ref]$t)
    # Encontra o Route::prefix logo apos o anchor e substitui ate o }); de fechamento
    $pattern = [regex]::Escape($anchor) + "[\s\S]*?Route::prefix\('api/v3'\)->middleware\(\['web', 'auth'\]\)->group\(function \(\) \{[\s\S]*?\}\);"
    $rep = $anchor + "`nRoute::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {`n    require __DIR__ . '/$require';`n});"
    $newTxt = [regex]::Replace($t.Value, $pattern, $rep, [System.Text.RegularExpressions.RegexOptions]::Singleline)
    if ($newTxt -ne $t.Value) {
        Write-Host "  OK substituido: $require"
        $t.Value = $newTxt
    } else {
        Write-Host "  AVISO nao encontrado: $require"
    }
}

$ref = [ref]$txt

Replace-Block "//`n//  API V3  CARGOS" "cargos_salarios.php" $ref
Replace-Block "//  API V3  F" "ferias_v3.php" $ref
Replace-Block "//  API V3  COMUNICADOS (CRUD" "comunicados.php" $ref
Replace-Block "//  API V3  PERFIL DO FUNCION" "meu_perfil.php" $ref
Replace-Block "//  API V3  PONTO ELETR" "ponto_eletronico.php" $ref
Replace-Block "//  API V3  BANCO DE HORAS" "plantoes_sobreaviso.php" $ref
Replace-Block "//  API V3  ATESTADOS M" "atestados_v3.php" $ref
Replace-Block "//  API V3  CONTRATOS/V" "contratos_v3.php" $ref
Replace-Block "//  API V3  MEDICINA DO TRABALHO" "medicina.php" $ref
Replace-Block "//  API V3  DECLARACOES / REQUERIMENTOS" "declaracoes.php" $ref
Replace-Block "//  API V3  OUVIDORIA" "ouvidoria.php" $ref
Replace-Block "//  API V3  ORGANOGRAMA  CRUD" "organograma_v3.php" $ref

$novo = $ref.Value.Length
Write-Host "`nOriginal: $orig chars | Novo: $novo chars | Reducao: $($orig - $novo) chars"

# Backup antes de salvar
Copy-Item $webPath "$webPath.bak" -Force
Write-Host "Backup criado: web.php.bak"

[System.IO.File]::WriteAllText($webPath, $ref.Value, [System.Text.Encoding]::UTF8)
Write-Host "web.php atualizado com sucesso."
