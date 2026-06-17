# PROMPT ANTYGRAVITY — FASE 4 GENTE v3 (Remoção GRADUAL de Rotas Legadas)

> **Cole este prompt no Antygravity APENAS após Fase 5 ter sido auditada e aprovada por Claude.**
> Estimativa total: ~1h30 Antygravity (auditoria Claude separada: ~30min).
> Pré-condição: Fases 1 + 2-A + 2-B + fix GAP-MF-04 + Fase 3 + Fase 5 mergeadas. Branch limpa.

---

## CONTEXTO DA FASE 4

A Fase 4 implementa as decisões 6, 7.a–7.g e 8 do `MAPA_ESCOPO_IMPLANTACAO_2026-05-07_v2.md`: **remoção gradual de rotas legadas** que existem em `routes/web.php` mas não são mais consumidas pelo SPA Vue 3 nem pelo app mobile.

### Mapeamento das rotas-alvo (auditado por Claude via MCP em 08/05/2026)

| Decisão | Prefix/rota | Ação | Por quê |
|---------|-------------|------|---------|
| **6** | `Route::get('cep/{cep}', ...)` linha ~1457 | REMOVER | AutocadastroView do SPA já usa `viacep.com.br` direto. Endpoint legado não tem mais consumer. |
| **7.a** | `Route::prefix('tabela_generica')` ~L1196 | COMENTAR | Tabela genérica administrativa. SPA usa `/api/v3/tabelas-genericas/*` no `api_v3_*.php`. |
| **7.b** | `Route::prefix('aplicacao')` ~L1278 | COMENTAR | Cadastro de aplicação RBAC legado. Substituído por `/api/v3/admin/*` (RBAC v3). |
| **7.c** | `Route::prefix('programa')` ~L1416 | REMOVER | Tabela `PROGRAMA` foi feita só pra import legado. Sem view, sem consumer SPA. |
| **7.d** | `Route::prefix('script')` ~L1437 | REMOVER | Editor de SQL ad-hoc do menu admin antigo. **SEC RISK** — não tem ACL nova; remover é seguro. |
| **7.e** | `Route::prefix('termo')` ~L1444 + `Route::prefix('termo_usuario')` ~L1454 | COMENTAR endpoints + DELETAR view zumbi | View Blade `resources/views/termo/termo_view.blade.php` órfã sem consumer. |
| **7.f** | `Route::prefix('comentario')` ~L1463 | REMOVER | Comentários de Escala v1 (Vue 2). Substituído por `/api/v3/escalas/{id}/comentarios`. |
| **7.g** | `Route::get('registrar')` + `Route::post('registrar')` ~L1561-1562 | REMOVER | Registro público legado. Substituído por `/autocadastro/{token}` (rota pública mantida). |
| **8** | `Route::get('ferias/alerta-vencer')` + `Route::get('afastamento/alerta-expirar')` ~L1481-1485 | REMOVER | Endpoints originalmente para cron via curl. Hoje cron é Schedule task em `app/Console/Kernel.php`. Endpoints HTTP órfãos. |

### Princípios de design

1. **NUNCA deletar Controllers.** Apenas as rotas. Os Controllers ficam disponíveis caso sejam reaproveitados em rota nova (ex.: `AfastamentoController::alertaExpirar` pode ser chamado como command-job futuro).
2. **COMENTAR com tag `// FASE-4-COMENTADO 08/05/2026:`** — facilita grep + rollback de produção em 30 segundos se algum cliente reclamar.
3. **REMOVER (apagar bloco inteiro)** apenas quando MAPA mestre disse REMOVER (decisões 6, 7.c, 7.d, 7.f, 7.g, 8).
4. **DELETAR a view zumbi `resources/views/termo/termo_view.blade.php`** — SPA não usa Blade, isso é poluição.
5. **NÃO mexer em outros arquivos de rota** (`api_v3_*.php`, `folha.php`, `motor.php`, `ponto_app.php`, etc.). Eles são consumidos pelo SPA.
6. **Múltiplos commits cirúrgicos**, **um por decisão**. Rollback granular se algum quebrar UI/menu.

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Trabalhar em ORDEM:** T4.1 → T4.2 → T4.3 → T4.4 → T4.5 → T4.6 → T4.7 → T4.8 → T4.9.
2. **Um commit por tarefa** (rollback isolado).
3. **Após CADA tarefa, validar:**
   ```powershell
   php -l routes/web.php
   ```
   Se der erro de sintaxe, **PARAR e reportar** — comentário pode ter quebrado fechamento `})` aninhado.
4. **Se algum trecho não bater** com o esperado, **PARAR e reportar**.
5. **NÃO REMOVER imports `use App\Http\Controllers\XXController;` nesta fase.** Os Controllers continuam disponíveis. Cleanup de imports não-usados é Fase 6.
6. **Validações via leitura textual + `php -l`.** Antygravity NÃO precisa rodar `php artisan route:list` (PHP 8.1 local não roda artisan completo).

---

## T4.1 — Remover rota `cep/{cep}` legada (decisão 6) (~5 min)

**Por que primeiro:** rota mais simples, sem dependência aninhada. Aquece o motor.

**Arquivo:** `routes/web.php`

**Trecho atual (linha ~1457, dentro do grupo `auth`):**

```php
    Route::get('cep/{cep}', [CepController::class, 'service']);
```

**Trecho corrigido (substituir pela linha vazia + comentário):**

```php
    // FASE-4-COMENTADO 08/05/2026 (decisão 6 do MAPA): rota CEP legada removida.
    // SPA Vue 3 (AutocadastroView) consome viacep.com.br diretamente.
    // CepController preservado em app/Http/Controllers/CepController.php para uso futuro.
    // Route::get('cep/{cep}', [CepController::class, 'service']);
```

**Atenção:** o `CepController` está importado no topo do arquivo (`use App\Http\Controllers\CepController;`). **NÃO remover o import** — Controller fica preservado.

**Validação:**

```powershell
php -l routes/web.php
```
Esperado: `No syntax errors detected`.

```powershell
Select-String -Path "routes/web.php" -Pattern "Route::get\('cep/\{cep\}'"
```
Esperado: 0 ocorrências (apenas em linha comentada — Select-String não pega comentários começando com `//` exatamente, mas o pattern busca rota viva).

**Commit:** `refactor(Fase4-T1,decisao-6): remover rota cep/{cep} legada (SPA usa viacep.com.br)`

---

## T4.2 — Comentar `Route::prefix('tabela_generica')` (decisão 7.a) (~10 min)

**Por que segundo:** começo da série de "comentar" (vs "remover"). Bloco com várias rotas aninhadas.

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1196-1212, dentro do grupo `auth`):**

```php
    Route::prefix('tabela_generica')->group(function () {
        Route::get('/', [TabelaGenericaController::class, "view"]);
        Route::get('view', [TabelaGenericaController::class, "view"]);
        Route::post('inserir', [TabelaGenericaController::class, "inserir"]);
        Route::get('listar', [TabelaGenericaController::class, "listar"]);
        Route::post('pesquisar', [TabelaGenericaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [TabelaGenericaController::class, "buscar"]);
        Route::delete('deletar', [TabelaGenericaController::class, "deletar"]);
        Route::put('alterar', [TabelaGenericaController::class, "alterar"]);
        Route::get('carregar', [TabelaGenericaController::class, "carregar"]);
        Route::get('listar_colunas', [TabelaGenericaController::class, "listarColunas"]);
        Route::put('alterar_coluna', [TabelaGenericaController::class, "alterarColuna"]);
        Route::post('inserir_coluna', [TabelaGenericaController::class, "inserirColuna"]);
        Route::delete('remover_coluna', [TabelaGenericaController::class, "removerColuna"]);
        Route::post('inserir_tabela', [TabelaGenericaController::class, "inserirTabela"]);
        Route::put('alterar_tabela', [TabelaGenericaController::class, "alterarTabela"]);
    });
```

**Trecho corrigido (encapsular bloco INTEIRO em comentário /* ... */):**

```php
    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.a do MAPA): rotas tabela_generica legadas comentadas.
     * SPA Vue 3 consome /api/v3/tabelas-genericas/* (definido em api_v3_*.php).
     * TabelaGenericaController preservado em app/Http/Controllers/ para reaproveitamento futuro.
     * Para reativar, descomentar este bloco.
     *
    Route::prefix('tabela_generica')->group(function () {
        Route::get('/', [TabelaGenericaController::class, "view"]);
        Route::get('view', [TabelaGenericaController::class, "view"]);
        Route::post('inserir', [TabelaGenericaController::class, "inserir"]);
        Route::get('listar', [TabelaGenericaController::class, "listar"]);
        Route::post('pesquisar', [TabelaGenericaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [TabelaGenericaController::class, "buscar"]);
        Route::delete('deletar', [TabelaGenericaController::class, "deletar"]);
        Route::put('alterar', [TabelaGenericaController::class, "alterar"]);
        Route::get('carregar', [TabelaGenericaController::class, "carregar"]);
        Route::get('listar_colunas', [TabelaGenericaController::class, "listarColunas"]);
        Route::put('alterar_coluna', [TabelaGenericaController::class, "alterarColuna"]);
        Route::post('inserir_coluna', [TabelaGenericaController::class, "inserirColuna"]);
        Route::delete('remover_coluna', [TabelaGenericaController::class, "removerColuna"]);
        Route::post('inserir_tabela', [TabelaGenericaController::class, "inserirTabela"]);
        Route::put('alterar_tabela', [TabelaGenericaController::class, "alterarTabela"]);
    });
    */
```

**ATENÇÃO CRÍTICA:** o bloco original começa com `Route::prefix(...)->group(function () {` e termina com `});`. Se algum dos `}` ou `)` não fechar correto dentro do `/* */`, o PHP vai dar **parse error**. **Antygravity DEVE rodar `php -l routes/web.php` ANTES de commitar.**

**Validação:**

```powershell
php -l routes/web.php
```
Esperado: `No syntax errors detected`.

```powershell
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('tabela_generica'\)"
```
Esperado: 1 ocorrência (apenas dentro do bloco `/* */`).

```powershell
# Confirmar que o import foi PRESERVADO (não removemos)
Select-String -Path "routes/web.php" -Pattern "use App\\Http\\Controllers\\TabelaGenericaController"
```
Esperado: 1 ocorrência.

**Commit:** `refactor(Fase4-T2,decisao-7a): comentar Route::prefix('tabela_generica') legado`

---

## T4.3 — Comentar `Route::prefix('aplicacao')` (decisão 7.b) (~5 min)

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1278-1287):**

```php
    Route::prefix('aplicacao')->middleware('perfil:ADMINISTRADOR,Administrador')->group(function () {
        Route::get('/', [AplicacaoController::class, "view"]);
        Route::get('view', [AplicacaoController::class, "view"])->name('aplicacao.view');
        Route::get('search', [AplicacaoController::class, "search"]);
        Route::post('create', [AplicacaoController::class, "create"]);
        Route::delete('delete', [AplicacaoController::class, "delete"]);
        Route::put('update', [AplicacaoController::class, "update"]);
        Route::match(['get', 'post'], 'list', [AplicacaoController::class, "list"]);
    });
```

**Trecho corrigido:**

```php
    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.b do MAPA): rotas aplicacao legadas comentadas.
     * Cadastro de Aplicação RBAC v1 (Vue 2). Substituído por /api/v3/admin/* (RBAC v3).
     * AplicacaoController preservado em app/Http/Controllers/ para reaproveitamento futuro.
     *
    Route::prefix('aplicacao')->middleware('perfil:ADMINISTRADOR,Administrador')->group(function () {
        Route::get('/', [AplicacaoController::class, "view"]);
        Route::get('view', [AplicacaoController::class, "view"])->name('aplicacao.view');
        Route::get('search', [AplicacaoController::class, "search"]);
        Route::post('create', [AplicacaoController::class, "create"]);
        Route::delete('delete', [AplicacaoController::class, "delete"]);
        Route::put('update', [AplicacaoController::class, "update"]);
        Route::match(['get', 'post'], 'list', [AplicacaoController::class, "list"]);
    });
    */
```

**Atenção:** este bloco tem `name('aplicacao.view')`. Se algum middleware/Blade legado fizer `route('aplicacao.view')`, vai dar erro `RouteNotFoundException`. **Antygravity DEVE rodar:**

```powershell
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue" | Select-String -Pattern "route\('aplicacao\." | Where-Object { $_.Path -notmatch "_legacy" }
```

**Esperado: 0 ocorrências.** Se houver match, **PARAR e reportar a Claude** — pode ter view ou Controller dependendo dessa rota.

**Validação:**

```powershell
php -l routes/web.php
```

```powershell
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('aplicacao'\)"
```
Esperado: 1 ocorrência (apenas dentro do bloco comentado).

**Commit:** `refactor(Fase4-T3,decisao-7b): comentar Route::prefix('aplicacao') legado`

---

## T4.4 — Remover `Route::prefix('programa')` e `Route::prefix('script')` (decisões 7.c e 7.d) (~10 min)

**Por que juntos:** os dois blocos são **REMOVER** (não comentar) e são fisicamente próximos no arquivo (~L1416 e ~L1437).

**Decisão 7.d tem peso de segurança:** o módulo `script` permitia executar SQL ad-hoc com `ScriptController::executarQuery`. Em produção PMSL com SQL Server **isso é vetor de injeção crítica**. Remover é prioridade.

### 4.4.A — Remover bloco `programa`

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1416-1425):**

```php
    Route::prefix('programa')->group(function () {
        Route::get('/', [ProgramaController::class, 'view'])->name('view.programa');
        ;
        Route::get('view', [ProgramaController::class, "view"]);
        Route::post('inserir', [ProgramaController::class, "inserir"]);
        Route::put('alterar', [ProgramaController::class, "alterar"]);
        Route::delete('deletar', [ProgramaController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [ProgramaController::class, "listar"]);
        Route::post('pesquisar', [ProgramaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [ProgramaController::class, "buscar"]);
    });
```

**Trecho corrigido (substituir por linha vazia + comentário breve):**

```php
    // FASE-4-REMOVIDO 08/05/2026 (decisão 7.c do MAPA): bloco Route::prefix('programa') removido.
    // Tabela PROGRAMA criada apenas para import legado. Sem view, sem consumer SPA.
    // ProgramaController preservado em app/Http/Controllers/ caso surja necessidade futura.
```

### 4.4.B — Remover bloco `script` (segurança)

**Trecho atual (linhas ~1437-1442):**

```php
    Route::prefix('script')->group(function () {
        Route::get('/', [ScriptController::class, 'view'])->name('view.script');
        ;
        Route::get('view', [ScriptController::class, "view"]);
        Route::post('executar', [ScriptController::class, "executarQuery"]);
        Route::match(['get', 'post'], 'listar', [ScriptController::class, "listar"]);
    });
```

**Trecho corrigido:**

```php
    // FASE-4-REMOVIDO 08/05/2026 (decisão 7.d do MAPA): bloco Route::prefix('script') removido.
    // Editor SQL ad-hoc legado — vetor de SQL injection sem ACL nova.
    // Decisão de segurança: remover endpoint mesmo mantendo ScriptController.
```

**Atenção crítica para 7.d:** `ScriptController::executarQuery` aceita SQL puro do request body. Após remoção da rota, o método permanece no Controller mas inacessível via HTTP. **NÃO remover o Controller agora** — Antygravity pode acidentalmente quebrar imports em outro lugar.

**Validações T4.4 (ambos):**

```powershell
php -l routes/web.php
```

```powershell
# Confirmar remoção
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('programa'\)|Route::prefix\('script'\)"
```
Esperado: 0 ocorrências (REMOVIDO, não comentado — não fica no arquivo).

```powershell
# Confirmar que nenhuma view ou controller faz route('view.programa') ou route('view.script')
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php" | Select-String -Pattern "route\('view\.programa'\)|route\('view\.script'\)" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Commits (1 por decisão):**

```
refactor(Fase4-T4a,decisao-7c): remover Route::prefix('programa') legado
refactor(Fase4-T4b,decisao-7d): remover Route::prefix('script') legado (SEC: vetor SQL injection)
```

---

## T4.5 — Comentar `Route::prefix('termo')` + `termo_usuario` + DELETAR view zumbi (decisão 7.e) (~10 min)

**Decisão 7.e** é especial: tem **3 ações** combinadas:
1. Comentar bloco `Route::prefix('termo')` em `routes/web.php`
2. Comentar bloco `Route::prefix('termo_usuario')` em `routes/web.php`
3. **DELETAR** `resources/views/termo/termo_view.blade.php` (view zumbi órfã)

### 4.5.A — Comentar bloco `termo`

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1444-1452):**

```php
    Route::prefix('termo')->group(function () {
        Route::get('/', [TermoController::class, 'view'])->name('view.termo');
        ;
        Route::get('listar', [TermoController::class, "listar"]);
        Route::post('inserir', [TermoController::class, "inserir"]);
        Route::post('alterar', [TermoController::class, "alterar"]);
        Route::get('download', [TermoController::class, "download"])->name('download.termo');
        Route::get('download/{id}', [TermoController::class, "download"]);
    });
```

**Trecho corrigido:**

```php
    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.e do MAPA): rotas termo legadas comentadas.
     * View Blade zumbi resources/views/termo/termo_view.blade.php DELETADA nesta mesma fase.
     * TermoController preservado em app/Http/Controllers/ para reaproveitamento futuro.
     *
    Route::prefix('termo')->group(function () {
        Route::get('/', [TermoController::class, 'view'])->name('view.termo');
        ;
        Route::get('listar', [TermoController::class, "listar"]);
        Route::post('inserir', [TermoController::class, "inserir"]);
        Route::post('alterar', [TermoController::class, "alterar"]);
        Route::get('download', [TermoController::class, "download"])->name('download.termo');
        Route::get('download/{id}', [TermoController::class, "download"]);
    });
    */
```

### 4.5.B — Comentar bloco `termo_usuario`

**Trecho atual (linhas ~1454-1456):**

```php
    Route::prefix('termo_usuario')->group(function () {
        Route::post('inserir', [TermoUsuarioController::class, "inserir"])->name('inserir.termo_usuario');
    });
```

**Trecho corrigido:**

```php
    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.e do MAPA): rota termo_usuario legada comentada.
     *
    Route::prefix('termo_usuario')->group(function () {
        Route::post('inserir', [TermoUsuarioController::class, "inserir"])->name('inserir.termo_usuario');
    });
    */
```

### 4.5.C — DELETAR view zumbi `resources/views/termo/termo_view.blade.php`

```powershell
git rm resources/views/termo/termo_view.blade.php
```

**Atenção:** depois disso a pasta `resources/views/termo/` fica vazia. Em sistemas de arquivos Unix, pasta vazia some no `git`. Em Windows, pode ficar um stub. **Não criar `.gitkeep`** — pasta órfã pode ficar.

Para confirmar pasta sumiu do Git:

```powershell
git status
```

Deve mostrar:
```
deleted: resources/views/termo/termo_view.blade.php
```

**Validações T4.5:**

```powershell
php -l routes/web.php
```

```powershell
# Confirmar termo comentado
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('termo'\)"
```
Esperado: 1 ocorrência (dentro do `/* */`).

```powershell
# Confirmar termo_usuario comentado
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('termo_usuario'\)"
```
Esperado: 1 ocorrência (dentro do `/* */`).

```powershell
# Confirmar view deletada
Test-Path "resources/views/termo/termo_view.blade.php"
```
Esperado: `False`.

```powershell
# Confirmar nenhum código faz route('view.termo') ou route('download.termo') ou route('inserir.termo_usuario')
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue" | Select-String -Pattern "route\('view\.termo'\)|route\('download\.termo'\)|route\('inserir\.termo_usuario'\)" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Commit:** `refactor(Fase4-T5,decisao-7e): comentar termo + termo_usuario + deletar view zumbi termo_view.blade.php`

---

## T4.6 — Remover `Route::prefix('comentario')` (decisão 7.f) (~5 min)

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1463-1467):**

```php
    Route::prefix('comentario')->group(function () {
        Route::get('listar', [ComentarioController::class, "listar"])->name('comentario.list');
        Route::post('inserir', [ComentarioController::class, "inserir"])->name('comentario.create');
        Route::put('alterar', [ComentarioController::class, "alterar"]);
    });
```

**Trecho corrigido (REMOVER, decisão MAPA é "remover" não "comentar"):**

```php
    // FASE-4-REMOVIDO 08/05/2026 (decisão 7.f do MAPA): bloco Route::prefix('comentario') removido.
    // Comentários de Escala v1 (Vue 2) — substituídos por /api/v3/escalas/{id}/comentarios.
    // ComentarioController preservado em app/Http/Controllers/.
```

**Atenção:** o bloco tem `name('comentario.list')` e `name('comentario.create')`. Verificar:

```powershell
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue" | Select-String -Pattern "route\('comentario\." | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Validação:**

```powershell
php -l routes/web.php
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('comentario'\)"
```
Esperado: 0 ocorrências.

**Commit:** `refactor(Fase4-T6,decisao-7f): remover Route::prefix('comentario') legado`

---

## T4.7 — Remover rotas `registrar` (decisão 7.g) (~5 min)

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1561-1562, FORA do grupo `auth`):**

```php
Route::get('registrar', [PessoaController::class, "registro_view"]);
Route::post('registrar', [PessoaController::class, "registro"]);
```

**Trecho corrigido (REMOVER):**

```php
// FASE-4-REMOVIDO 08/05/2026 (decisão 7.g do MAPA): rotas /registrar legadas removidas.
// Substituídas por /autocadastro/{token} (rota pública mantida no início do web.php).
// PessoaController::registro_view e ::registro preservados para uso futuro.
```

**Atenção:** `registrar` é rota PÚBLICA (fora do grupo `auth`). Confirmar via grep que nenhum link/menu/SPA aponta:

```powershell
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue" | Select-String -Pattern "url\('registrar'\)|route\('registrar'\)|href.*?/registrar" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Validação:**

```powershell
php -l routes/web.php
Select-String -Path "routes/web.php" -Pattern "^Route::(get|post)\('registrar'"
```
Esperado: 0 ocorrências (apenas em comentário).

**Commit:** `refactor(Fase4-T7,decisao-7g): remover rotas /registrar legadas (substituídas por /autocadastro/{token})`

---

## T4.8 — Remover endpoints HTTP de cron órfãos (decisão 8) (~5 min)

**Decisão 8** identificou 2 endpoints HTTP que **eram chamados via `curl` por cron antigo** mas hoje o cron é via `app/Console/Kernel.php` (Laravel Scheduler). Os endpoints HTTP ficaram órfãos.

**Arquivo:** `routes/web.php`

**Trecho atual (linhas ~1481-1485, dentro do grupo `auth`):**

```php
    // â”€â”€ Alertas de RH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('ferias/alerta-vencer', [FeriasController::class, 'alertaVencer'])
        ->name('ferias.alerta-vencer');
    Route::get('afastamento/alerta-expirar', [AfastamentoController::class, 'alertaExpirar'])
        ->name('afastamento.alerta-expirar');
```

**Trecho corrigido (REMOVER):**

```php
    // FASE-4-REMOVIDO 08/05/2026 (decisão 8 do MAPA): endpoints HTTP de cron órfãos removidos.
    // Hoje os alertas são disparados via app/Console/Kernel.php (Laravel Scheduler).
    // FeriasController::alertaVencer e AfastamentoController::alertaExpirar preservados.
```

**Atenção:** verificar se `app/Console/Kernel.php` realmente tem o Schedule task (se NÃO tiver, vamos quebrar a rotina de alertas). Antygravity DEVE rodar:

```powershell
Select-String -Path "app/Console/Kernel.php" -Pattern "alertaVencer|alertaExpirar|ferias.*alerta|afastamento.*alerta"
```

**Esperado: 1+ ocorrências.** Se for 0, **PARAR e reportar** — pode ser que o Schedule não esteja configurado e estamos quebrando produção.

**Validação:**

```powershell
php -l routes/web.php
Select-String -Path "routes/web.php" -Pattern "ferias\.alerta-vencer|afastamento\.alerta-expirar"
```
Esperado: 0 ocorrências.

```powershell
# Verificar que nenhum job ou comando faz route()->name() para essas rotas
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php" | Select-String -Pattern "route\('ferias\.alerta-vencer'\)|route\('afastamento\.alerta-expirar'\)" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Commit:** `refactor(Fase4-T8,decisao-8): remover endpoints HTTP de cron órfãos (Schedule é via Kernel.php)`

---

## T4.9 — Smoke test final + report (~10 min)

### Validação 1 — Sintaxe PHP final do arquivo

```powershell
php -l routes/web.php
```
Esperado: `No syntax errors detected`.

### Validação 2 — Confirmar todas as rotas legadas saíram do mapa ativo

```powershell
# Rotas comentadas (devem aparecer 1x cada, dentro de /* */)
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('tabela_generica'\)|Route::prefix\('aplicacao'\)|Route::prefix\('termo'\)|Route::prefix\('termo_usuario'\)"
# Esperado: 4 ocorrências (todas dentro de /* */)
```

```powershell
# Rotas removidas (devem aparecer 0x)
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('programa'\)|Route::prefix\('script'\)|Route::prefix\('comentario'\)"
# Esperado: 0 ocorrências
```

```powershell
# Rotas órfãs removidas
Select-String -Path "routes/web.php" -Pattern "Route::get\('cep/\{cep\}'|Route::get\('registrar'|Route::post\('registrar'|ferias\.alerta-vencer|afastamento\.alerta-expirar"
# Esperado: 0 ocorrências (apenas em comentários // single-line)
```

### Validação 3 — Confirmar que NENHUM consumidor ativo usa as rotas removidas

```powershell
# Auditoria global de menções a rotas legadas em código vivo
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue","*.js","*.ts" | Select-String -Pattern "route\('view\.programa'\)|route\('view\.script'\)|route\('view\.termo'\)|route\('download\.termo'\)|route\('inserir\.termo_usuario'\)|route\('aplicacao\..*'\)|route\('comentario\..*'\)|route\('ferias\.alerta-vencer'\)|route\('afastamento\.alerta-expirar'\)" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

### Validação 4 — Confirmar Controllers preservados

```powershell
# Os imports DEVEM continuar lá
Select-String -Path "routes/web.php" -Pattern "use App\\Http\\Controllers\\(TabelaGenericaController|AplicacaoController|ProgramaController|ScriptController|TermoController|TermoUsuarioController|ComentarioController|CepController);"
```
Esperado: 7 ocorrências (1 por Controller).

```powershell
# Os arquivos dos Controllers continuam existentes
Test-Path "app/Http/Controllers/TabelaGenericaController.php"
Test-Path "app/Http/Controllers/AplicacaoController.php"
Test-Path "app/Http/Controllers/ProgramaController.php"
Test-Path "app/Http/Controllers/ScriptController.php"
Test-Path "app/Http/Controllers/TermoController.php"
Test-Path "app/Http/Controllers/TermoUsuarioController.php"
Test-Path "app/Http/Controllers/ComentarioController.php"
Test-Path "app/Http/Controllers/CepController.php"
```
Esperado: 8x `True`.

### Validação 5 — View zumbi deletada

```powershell
Test-Path "resources/views/termo/termo_view.blade.php"
```
Esperado: `False`.

```powershell
# Listar pasta termo/ (pode estar vazia ou inexistente)
Test-Path "resources/views/termo"
```
Esperado: `False` ou pasta vazia. Se for vazia, OK — Git ignora pastas vazias automaticamente.

### Validação 6 — Confirmar Schedule do Kernel ativo (decisão 8)

```powershell
Select-String -Path "app/Console/Kernel.php" -Pattern "alertaVencer|alertaExpirar|ferias.*alerta|afastamento.*alerta"
```
Esperado: 1+ ocorrências (significa que cron foi migrado para Schedule).

**Se for 0 ocorrências, NÃO COMMITAR a Fase 4 e REPORTAR a Claude.** Pode estar quebrando produção.

### Validação 7 — Git log das 8 correções

```powershell
git log --oneline -n 12
```
Esperado: 8 commits novos:
- `refactor(Fase4-T1,decisao-6): remover rota cep/{cep} legada (SPA usa viacep.com.br)`
- `refactor(Fase4-T2,decisao-7a): comentar Route::prefix('tabela_generica') legado`
- `refactor(Fase4-T3,decisao-7b): comentar Route::prefix('aplicacao') legado`
- `refactor(Fase4-T4a,decisao-7c): remover Route::prefix('programa') legado`
- `refactor(Fase4-T4b,decisao-7d): remover Route::prefix('script') legado (SEC: vetor SQL injection)`
- `refactor(Fase4-T5,decisao-7e): comentar termo + termo_usuario + deletar view zumbi termo_view.blade.php`
- `refactor(Fase4-T6,decisao-7f): remover Route::prefix('comentario') legado`
- `refactor(Fase4-T7,decisao-7g): remover rotas /registrar legadas (substituídas por /autocadastro/{token})`
- `refactor(Fase4-T8,decisao-8): remover endpoints HTTP de cron órfãos (Schedule é via Kernel.php)`

### Validação 8 — Linhas removidas do web.php

```powershell
# Conferir tamanho do arquivo (deve ter diminuído ~50-80 linhas vs antes da fase)
(Get-Content "routes/web.php" | Measure-Object -Line).Lines
```
Esperado: ~1660 linhas (antes da fase: 1730).

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 4 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T4.1 cep/{cep} removido (decisão 6) ........................ commit: ____
[ ] T4.2 tabela_generica comentado (decisão 7.a) ............... commit: ____
[ ] T4.3 aplicacao comentado (decisão 7.b) ..................... commit: ____
[ ] T4.4a programa removido (decisão 7.c) ...................... commit: ____
[ ] T4.4b script removido (decisão 7.d, SEC) ................... commit: ____
[ ] T4.5 termo + termo_usuario comentado + view zumbi deletada . commit: ____
[ ] T4.6 comentario removido (decisão 7.f) ..................... commit: ____
[ ] T4.7 /registrar removido (decisão 7.g) ..................... commit: ____
[ ] T4.8 endpoints cron HTTP órfãos removidos (decisão 8) ...... commit: ____

VALIDAÇÕES (cole saídas reais):

V1 php -l routes/web.php após cada commit:
   ___ (esperado: 9× "No syntax errors detected" — 1 por tarefa)

V2 rotas comentadas (esperadas dentro de /* */):
   - tabela_generica: ___ (esperado 1)
   - aplicacao: ___ (esperado 1)
   - termo: ___ (esperado 1)
   - termo_usuario: ___ (esperado 1)

V3 rotas removidas (esperado 0 ocorrências):
   - programa: ___ (esperado 0)
   - script: ___ (esperado 0)
   - comentario: ___ (esperado 0)
   - cep/{cep}: ___ (esperado 0)
   - /registrar GET/POST: ___ (esperado 0)
   - ferias.alerta-vencer / afastamento.alerta-expirar: ___ (esperado 0)

V4 grep auditoria global por route('NOMES.LEGADOS'):
   ___ ocorrências fora de _legacy (esperado 0)

V5 imports de Controllers preservados:
   ___ ocorrências (esperado 7)

V6 arquivos dos Controllers continuam existindo:
   - TabelaGenericaController.php: ___
   - AplicacaoController.php: ___
   - ProgramaController.php: ___
   - ScriptController.php: ___
   - TermoController.php: ___
   - TermoUsuarioController.php: ___
   - ComentarioController.php: ___
   - CepController.php: ___
   (esperado 8× True)

V7 view zumbi deletada:
   resources/views/termo/termo_view.blade.php existe? ___ (esperado False)

V8 Schedule decisão 8 ativo:
   alertaVencer|alertaExpirar em app/Console/Kernel.php: ___ ocorrências (esperado 1+)
   ⚠ SE FOR 0, REPORTAR ANTES DE COMMITAR T4.8.

V9 git log -n 12:
   ___

V10 tamanho do web.php após Fase 4:
   ___ linhas (esperado ~1660, antes era 1730)

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

## CHECKLIST PÓS-FASE 4 (NÃO PARA ANTYGRAVITY)

Após Claude auditar e aprovar, **antes do go-live**:

1. **Smoke test em ambiente staging:**
   - Login no SPA Vue 3 → todas as 8 telas principais ainda renderizam? (Funcionários, Folhas, Escala, Ponto, Férias, Afastamentos, Atestados, Configurações)
   - Não há `console.error` no DevTools sobre 404 em rota legada?
   - `/autocadastro/{token-de-teste}` ainda funciona?

2. **Validar que cron Schedule está rodando:**
   ```bash
   php artisan schedule:list
   ```
   Saída esperada: 1+ task com `alertaVencer` e/ou `alertaExpirar`.

3. **Em PRODUÇÃO PMSL com SQL Server:**
   - Backup do banco antes do deploy.
   - Deploy fora do horário de pico (madrugada de segunda já planejado).

4. **Rollback em 30s** se algo quebrar:
   ```bash
   git revert <hash-do-commit-problematico>
   git push
   ```

---

## PRÓXIMA ETAPA APÓS APROVAÇÃO DA FASE 4

**Fase 6 — Deploy PMSL** (dom noite + seg madrugada).

A Fase 6 cobre:
- T01: validar PHP 8.4 no servidor
- T02: validar SQL Server connection
- T03: rodar seeders críticos (`EventosBaseSeeder`, `RubricasCatalogoSeeder` se ainda não)
- T04: rodar migrations pendentes
- T05: smoke test pós-deploy (login + 1 folha de teste)
- T06: cron Schedule ativado
- T07: monitor de logs primeira hora
- T08: rollback plan documentado
- T09: warm-up do cache OPcache + queue worker

Sequência cronológica para o deadline 12/05/2026:

```
[✅] Fase 1 — concluída + auditada (08/05 manhã)
[✅] Fase 2-A — concluída + auditada (08/05 tarde)
[✅] Fase 2-B — concluída + auditada (08/05 noite)
[✅] Fix GAP-MF-04 — concluído + auditado
[✅] Fase 3 — concluída + auditada
[ ] Fase 5 — em fila (~1h30, sex/sáb)
[ ] Fase 4 — em fila (~1h30, sáb/dom)
[ ] Fase 6 — dom noite + seg madrugada (deploy PMSL)
[ ] PoC — seg 12/05 tarde 🎯
```

**FIM DO PROMPT FASE 4.**
