# BRIEFING ANTYGRAVITY — FASE 1 GENTE v3 (correções pontuais bloqueadoras)

**Data execução:** Sex 09/05/2026 manhã
**Estimativa:** 2h45 a 3h15
**Pré-requisito:** ler `docs/MAPA_ESCOPO_IMPLANTACAO_2026-05-07_v2.md` Parte 4 e Parte 5
**Output esperado:** 8 correções cirúrgicas, smoke test verde

---

## CONTEXTO

Você é o agente executor (Antygravity/Gemini Cursor) do projeto GENTE v3 — ERP municipal RR TECNOL para Prefeitura São Luís/MA. Deadline implantação: segunda 12/05.

Esta é a Fase 1 de 6 fases. São correções cirúrgicas que precisam ser feitas ANTES de qualquer trabalho de refatoração maior nos motores de folha (Fase 2-A/2-B).

**REGRAS GERAIS DE EXECUÇÃO:**
1. **NÃO simular output.** Sempre forneça output real do PowerShell/Artisan.
2. **NÃO inventar arquivos ou linhas.** Se não encontrar, reportar e parar.
3. **APÓS cada correção,** rodar `php artisan test --filter=Smoke` se houver suite, OU smoke test sintético manual.
4. **NÃO mexer em routes/web.php** nesta fase. Adições inline no bloco dashboard são bug recorrente — Fase 4 trata disso.
5. **Sempre commit por correção** com mensagem `fix(R##): descrição curta`.

---

## TAREFAS (executar em sequência)

### TAREFA 1.1 — R70: cast 'integer' → 'decimal:2' em Folha.php (1 min)

**Arquivo:** `app/Models/Folha.php`
**Problema:** linha ~52 tem `'FOLHA_VALOR_TOTAL' => 'integer'` o que perde decimais. R$ 1234.56 vira R$ 1234.

**Correção exata:**

```diff
     protected $casts = [
         'FOLHA_TIPO' => 'integer',
         'VINCULO_ID' => 'integer',
         'FOLHA_COMPETENCIA' => Periodo::class,
         'FOLHA_QTD_SERVIDORES' => 'integer',
-        'FOLHA_VALOR_TOTAL' => 'integer',
+        'FOLHA_VALOR_TOTAL' => 'decimal:2',
     ];
```

**Validação:** `php artisan tinker` → `\App\Models\Folha::first()->FOLHA_VALOR_TOTAL` deve retornar string com 2 decimais.

**Commit:** `fix(R70): FOLHA_VALOR_TOTAL cast decimal:2 (preserva centavos)`

---

### TAREFA 1.2 — R72: path debug `/home/DK/Developer/...` em EscalaAusenciaService (1 min)

**Arquivo:** `app/Services/EscalaAusenciaService.php`
**Problema:** path hardcoded de máquina de desenvolvimento vaza em log/erro de produção.

**Buscar a string `/home/DK/` no arquivo e substituir por `storage_path()` apropriado.**

**Comando para localizar:**
```powershell
Select-String -Path "app/Services/EscalaAusenciaService.php" -Pattern "/home/"
```

**Correção:** substituir o caminho hardcoded por:
```php
// ANTES (exemplo)
$logPath = '/home/DK/Developer/sisgep/storage/logs/escala_ausencia.log';

// DEPOIS
$logPath = storage_path('logs/escala_ausencia.log');
```

**Validação:** `Select-String` acima deve retornar 0 ocorrências após a correção.

**Commit:** `fix(R72): remover path debug hardcoded EscalaAusenciaService`

---

### TAREFA 1.3 — R69: SQL injection em Lotacao::getDadosRelatorioImprimirLotacao (5 min)

**Arquivo:** `app/Models/Lotacao.php`
**Problema:** método `getDadosRelatorioImprimirLotacao` usa concatenação direta de string em DB::select ou DB::raw, permitindo SQL injection via parâmetro de URL.

**Comando para localizar:**
```powershell
Select-String -Path "app/Models/Lotacao.php" -Pattern "getDadosRelatorioImprimirLotacao" -Context 0,30
```

**Correção:** substituir concatenação por bind parameters.
```php
// ANTES (exemplo)
$sql = "SELECT ... WHERE LOTACAO_ID = " . $lotacaoId;
DB::select($sql);

// DEPOIS
DB::select("SELECT ... WHERE LOTACAO_ID = ?", [$lotacaoId]);
```

**Validação:** smoke manual — chamar a rota que dispara este método com `?id=1; DROP TABLE x` e confirmar que NÃO executa.

**Commit:** `fix(R69): SQL injection Lotacao via bind parameter`

---

### TAREFA 1.4 — R39: crase MySQL ProgressaoFuncionalListagemService (5 min)

**Arquivo:** `app/Services/Progressao/ProgressaoFuncionalListagemService.php` (ou caminho similar)
**Problema:** uso de crase `\`coluna\`` que é válido em MySQL mas quebra em SQL Server (que usa colchetes `[coluna]`).

**Comando para localizar:**
```powershell
Get-ChildItem -Path "app/Services/Progressao" -Recurse -Filter "*.php" |
    Select-String -Pattern '`[A-Z_]+`'
```

**Correção:** substituir cada `\`COLUNA\`` por `[COLUNA]` (compatível SQL Server) OU melhor — remover totalmente e deixar Eloquent ou DB::table cuidar do quoting (cross-driver).

**Exemplo:**
```php
// ANTES
DB::raw("\`PROGRESSAO_DATA\`")
// DEPOIS
DB::raw('[PROGRESSAO_DATA]')  // ou ainda melhor: usar Eloquent puro
```

**Validação:** rodar a listagem de progressão funcional via SPA Vue 3 com `DB_CONNECTION=sqlite` e depois com `sqlsrv` (se possível).

**Commit:** `fix(R39): crase MySQL → bracket SQL Server em ProgressaoFuncionalListagemService`

---

### TAREFA 1.5 — R40: whereYear/whereMonth ApuracaoPontoService (10 min)

**Arquivo:** `app/Services/ApuracaoPontoService.php`
**Problema:** `whereYear` e `whereMonth` do Eloquent usam funções específicas de driver (MySQL `YEAR()`, SQL Server `YEAR()` mas com sintaxe diferente).

**Comando para localizar:**
```powershell
Select-String -Path "app/Services/ApuracaoPontoService.php" -Pattern "whereYear|whereMonth"
```

**Correção:** substituir por `whereBetween` com Carbon range.

```php
// ANTES
->whereYear('REGISTRO_DATA', 2025)
->whereMonth('REGISTRO_DATA', 5)

// DEPOIS
$inicio = Carbon::create(2025, 5, 1)->startOfMonth();
$fim = $inicio->copy()->endOfMonth();
->whereBetween('REGISTRO_DATA', [$inicio, $fim])
```

**Validação:** rodar `ApuracaoPontoService::apurar(...)` em SQLite e confirmar resultado correto para fevereiro 28 dias.

**Commit:** `fix(R40): whereYear/Month → whereBetween cross-driver`

---

### TAREFA 1.6 — R56: strftime DepreciacaoService (5 min)

**Arquivo:** `app/Services/DepreciacaoService.php`
**Problema:** uso de `strftime` SQLite que não existe em SQL Server.

**Comando para localizar:**
```powershell
Select-String -Path "app/Services/DepreciacaoService.php" -Pattern "strftime|julianday"
```

**Correção:** substituir por Carbon::diffInDays e whereBetween (igual à TAREFA 1.5).

**Validação:** rodar `DepreciacaoService::calcularDepreciacao(...)` e confirmar.

**Commit:** `fix(R56): strftime → Carbon em DepreciacaoService`

---

### TAREFA 1.7 — R57: date+'+N days' DashboardOperacionalService (5 min)

**Arquivo:** `app/Services/Dashboard/DashboardOperacionalService.php`
**Problema:** `DB::raw("date(col, '+30 days')")` é SQLite only.

**Correção:** substituir por Carbon::addDays no PHP, antes do SQL.

```php
// ANTES
->whereRaw("date(DATA, '+30 days') > date('now')")

// DEPOIS
$limite = Carbon::now()->addDays(30);
->where('DATA', '>=', $limite)
```

**Comando para localizar:**
```powershell
Select-String -Path "app/Services/Dashboard/DashboardOperacionalService.php" -Pattern "date\("
```

**Commit:** `fix(R57): date+'+N days' SQLite → Carbon addDays`

---

### TAREFA 1.8 — R7-R10: ContabilidadeService idempotência (~2h)

**Arquivo:** `app/Services/ContabilidadeService.php`
**Problema:** método de geração de lançamentos contábeis a partir de DETALHE_FOLHA não é idempotente — reprocessar a mesma folha duplica os lançamentos em LANCAMENTO_CONTABIL.

**Solução:** trocar INSERT por UPSERT na chave (FOLHA_ID, RUBRICA_ID, FUNCIONARIO_ID) ou (FOLHA_ID, EVENTO_ID).

**Passos:**

1. Identificar a chave natural de unicidade. Provavelmente `(FOLHA_ID, RUBRICA_ID, FUNCIONARIO_ID)` ou `(FOLHA_ID, EVENTO_ID, FUNCIONARIO_ID)`. Confirmar com `php artisan tinker` ou inspecionando schema.

2. Adicionar UNIQUE constraint via migration nova:
```php
Schema::table('LANCAMENTO_CONTABIL', function (Blueprint $t) {
    $t->unique(['FOLHA_ID', 'RUBRICA_ID', 'FUNCIONARIO_ID'], 'uq_lanc_contabil_folha_rub_func');
});
```

3. Trocar `DB::table('LANCAMENTO_CONTABIL')->insert(...)` por `->upsert(...)` com a chave acima.

4. Limpeza prévia (alternativa mais simples se UPSERT for complexo): no início do método, `DB::table('LANCAMENTO_CONTABIL')->where('FOLHA_ID', $folhaId)->delete()` antes de inserir. **PORÉM:** isso quebra se houver constraints FK de outras tabelas apontando pra LANCAMENTO_CONTABIL. Verificar primeiro.

**Validação:**
- Rodar `ContabilidadeService::gerarLancamentosFolha($folhaId)` 2x consecutivas
- `SELECT COUNT(*) FROM LANCAMENTO_CONTABIL WHERE FOLHA_ID = ?` deve retornar o mesmo número nas 2 execuções

**Commit:** `fix(R7-R10): ContabilidadeService idempotência via upsert`

---

## VALIDAÇÃO FINAL — Smoke test pós-Fase 1

```powershell
# 1. Confirmar nenhum strftime/julianday remanescente
Get-ChildItem -Path "app/Services" -Recurse -Filter "*.php" |
    Select-String -Pattern "strftime|julianday"
# Esperado: nenhuma ocorrência (ou só FolhaParserService, que será aposentado na Fase 3)

# 2. Confirmar nenhum path /home/DK/
Get-ChildItem -Path "app" -Recurse -Filter "*.php" |
    Select-String -Pattern "/home/DK"
# Esperado: 0 ocorrências

# 3. Confirmar nenhuma crase em queries
Get-ChildItem -Path "app" -Recurse -Filter "*.php" |
    Select-String -Pattern '`[A-Z_]+`'
# Esperado: 0 ocorrências (ou só comentários)

# 4. Smoke test artisan
php artisan migrate:status | Select-String "Pending"
# Esperado: nenhuma pendente

# 5. Smoke test motor de folha (ainda usa FolhaParserService nesta fase, mas valida que nada quebrou)
php artisan tinker
> \App\Models\Folha::first()->FOLHA_VALOR_TOTAL
# Esperado: string com 2 decimais (R70 fechado)

# 6. Smoke test sentinela
php artisan gente:sentinela-run --json
# Esperado: status "ok"
```

---

## REPORT TEMPLATE PARA DEVOLVER A RONALDO/CLAUDE

Cole este template preenchido após executar:

```
FASE 1 — REPORT EXECUÇÃO ANTYGRAVITY

[ ] R70 cast decimal:2 — concluído / commit hash: ____
[ ] R72 path debug — concluído / commit hash: ____
[ ] R69 SQL injection — concluído / commit hash: ____
[ ] R39 crase MySQL — concluído / commit hash: ____
[ ] R40 whereYear/Month — concluído / commit hash: ____
[ ] R56 strftime — concluído / commit hash: ____
[ ] R57 date+'+N days' — concluído / commit hash: ____
[ ] R7-R10 ContabilidadeService — concluído / commit hash: ____

Smoke tests pós-fase:
- grep strftime/julianday: ___ ocorrências (esperado 0)
- grep /home/DK/: ___ ocorrências (esperado 0)
- grep crase queries: ___ ocorrências (esperado 0)
- migrate:status pending: ___ migrations pendentes (esperado 0)
- sentinela-run: status ___

Bugs encontrados durante execução (que NÃO estavam na lista):
- ____

Tempo total real: ___h ___min
```

---

**Próximo briefing:** Fase 2-A — Ampliação MotorFolha gaps críticos (frequência + abono + pró-rata + HE/plantão). Aguardar OK do auditor (Claude) antes de iniciar.
