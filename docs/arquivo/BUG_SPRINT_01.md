# BUG-SPRINT-01 — Correções de Qualidade GENTE v3
**Data:** 2026-04-15
**Origem:** Auditoria manual de 32 telas (QA: Davi) + validação de código (Claude/MCP)
**Total de bugs:** 51 catalogados | 25 para corrigir agora | 20 depois | 6 dependem de seed
**Workflow:** Antygravity executa cada onda → Claude audita via MCP → aprova antes da próxima

---

## REGRAS DESTA SPRINT

1. **Executar sempre na ordem das ondas** — algumas correções são pré-requisito de outras.
2. **Uma onda por vez** — não avançar para a próxima sem aprovação do Claude.
3. **Usar `edit_block` com `old_string`/`new_string` exatos** — nunca reescrever o arquivo inteiro.
4. **Após cada onda, reportar:** arquivo alterado + linhas modificadas + output do comando de verificação indicado.
5. **Nunca adicionar rotas inline no `web.php`** — novos módulos sempre em arquivo próprio + `require` no Bloco 1.
6. **Não alterar nenhum arquivo fora do escopo da onda** — mesmo que "pareça melhor".

---

## ONDA 1 — Correções de 1 linha (5 fixes, zero risco)

> Todas independentes entre si. Executar em qualquer ordem dentro da onda.

---

### [O1-01] DATE() inválido no SQL Server — Ponto + Banco de Horas

**Arquivo:** `routes/web.php` linha ~4436
**Impacto:** Desbloqueia Ponto Eletrônico e Banco de Horas inteiros
**Causa:** `DATE()` é função MySQL/SQLite. No SQL Server a equivalente é `CAST(... AS DATE)`.

```
old_string:
->whereBetween(DB::raw("DATE(REGISTRO_DATA_HORA)"), [$inicio, $fim])

new_string:
->whereBetween(DB::raw("CAST(REGISTRO_DATA_HORA AS DATE)"), [$inicio, $fim])
```

**Verificação após fix:**
```bash
php artisan route:list --path=api/v3/ponto 2>&1 | head -5
```

---

### [O1-02] dd() ativo causa DoS funcional

**Arquivo:** `app/Rules/ChecarAcessoUsuarioUnidade.php` linha 33
**Impacto:** Qualquer rota que usa esta Rule trava a aplicação com dump HTML
**Causa:** `dd($usuario)` esquecido em produção.

Remover completamente a linha `dd(` e o `$usuario` que está na linha seguinte se fizer parte do mesmo `dd()`. Verificar contexto exato com `read_file` antes de editar.

**Verificação após fix:**
```bash
php -l app/Rules/ChecarAcessoUsuarioUnidade.php
```

---

### [O1-03] progressao_funcional.php não incluído no web.php

**Arquivo:** `routes/web.php` — Bloco 1 de requires (linha ~918)
**Impacto:** Módulo "Gerir Progressões" retorna 404 em todas as 6 rotas admin
**Causa:** O arquivo `routes/progressao_funcional.php` existe mas não tem `require` no web.php.

Adicionar após a linha `require __DIR__ . '/ponto_terceirizado.php';`:

```
old_string:
    require __DIR__ . '/ponto_terceirizado.php'; // GAP-PONT — Ponto Terceirizados
    require __DIR__ . '/avaliacao_desempenho.php'; // Avaliação de Desempenho

new_string:
    require __DIR__ . '/ponto_terceirizado.php'; // GAP-PONT — Ponto Terceirizados
    require __DIR__ . '/progressao_funcional.php'; // Progressão Funcional
    require __DIR__ . '/avaliacao_desempenho.php'; // Avaliação de Desempenho
```

**Verificação após fix:**
```bash
php artisan route:list --path=api/v3/progressao 2>&1 | head -10
```

---

### [O1-04] CSP bloqueia avatares (dicebear)

**Arquivo:** `app/Http/Middleware/SecurityHeaders.php` linha ~38
**Impacto:** Todos os avatares dos cards de funcionários aparecem quebrados
**Causa:** `img-src` na CSP não inclui `https://api.dicebear.com`.

```
old_string:
"img-src 'self' data: blob:; "

new_string:
"img-src 'self' data: blob: https://api.dicebear.com; "
```

**Verificação após fix:**
```bash
php -l app/Http/Middleware/SecurityHeaders.php
```

---

### [O1-05] Avatar do topbar sem navegação

**Arquivo:** `resources/gente-v3/src/layouts/DashboardLayout.vue`
**Impacto:** Usuário clica no avatar e nada acontece
**Causa:** `<div class="topbar-avatar">` não tem `@click`.

```
old_string:
<div class="topbar-avatar" :title="userName">{{ userInitials }}</div>

new_string:
<div class="topbar-avatar" :title="userName" @click="$router.push('/meu-perfil')" style="cursor:pointer">{{ userInitials }}</div>
```

---

### Relatório esperado da ONDA 1

```
[O1-01] web.php linha X alterada — DATE() → CAST
[O1-02] ChecarAcessoUsuarioUnidade.php linha 33 — dd() removido
[O1-03] web.php — require progressao_funcional.php adicionado na linha X
[O1-04] SecurityHeaders.php linha X — img-src atualizado
[O1-05] DashboardLayout.vue linha X — @click adicionado
```

---

## ONDA 2 — Arquivo único, sem novas rotas (7 fixes)

> Aguardar aprovação da ONDA 1 antes de iniciar.

---

### [O2-01] CARGO_CBO — coluna inexistente no SQL Server

**Arquivo:** `routes/cargos_salarios.php` + `app/Models/Cargo.php`
**Impacto:** CREATE e EDIT de cargo retornam 500 — SQLSTATE[42S22]
**Causa:** `CARGO_CBO` está no `$fillable` do model mas a coluna não existe no SQL Server.

**Passo A — `app/Models/Cargo.php`:** remover `'CARGO_CBO'` do array `$fillable`.

**Passo B — `routes/cargos_salarios.php`:** localizar e remover todas as linhas que atribuem `CARGO_CBO`:
```
Remover qualquer linha do tipo:
$cargo->CARGO_CBO = ...
'CARGO_CBO' => ...   (em arrays de insert/update)
```

**Verificação:**
```bash
php artisan route:list --path=api/v3/cargos 2>&1 | head -5
```

---

### [O2-02] estagiarios.php — middleware group interno quebra prefix

**Arquivo:** `routes/estagiarios.php`
**Impacto:** GET e POST `/api/v3/estagiarios` retornam 404
**Causa:** O arquivo define `Route::middleware()->group()` próprio, redefinindo o contexto e quebrando o prefix `api/v3`.

Remover o wrapper `Route::middleware(['web', 'auth'])->group(function () { ... })` deixando as rotas no nível raiz do arquivo, herdando o contexto do grupo pai do web.php.

**Verificação:**
```bash
php artisan route:list --path=api/v3/estagiarios 2>&1
```

---

### [O2-03] Meu Perfil — try/catch internos silenciam erros de save

**Arquivo:** `routes/meu_perfil.php` — `Route::put('/perfil', ...)`
**Impacto:** Usuário salva perfil, vê "sucesso", dados voltam ao original no F5
**Causa:** try/catch vazio em torno de cada atribuição de campo — se o campo não existe, o erro é engolido e `save()` executa com dados vazios.

Remover todos os blocos `try { $pessoa->$campo = ...; } catch (\Throwable $e) {}` substituindo por atribuição direta:
```php
// ANTES (padrão ruim):
try { $pessoa->CAMPO = $request->CAMPO; } catch (\Throwable $e) {}

// DEPOIS:
if ($request->has('CAMPO')) $pessoa->CAMPO = $request->CAMPO;
```

**Verificação:**
```bash
php -l routes/meu_perfil.php
```

---

### [O2-04] Holerites — botão PDF chama URL inexistente

**Arquivo:** `resources/gente-v3/src/views/folha/ContraChequeView.vue` linha ~216
**Impacto:** 404 ao tentar baixar PDF de qualquer holerite
**Causa:** Frontend chama `/api/v3/meus-holerites/${calculoId}/pdf` mas a rota real é `/contra-cheque/{funcionarioId}/{competencia}/pdf`.

```
old_string:
window.open(`/api/v3/meus-holerites/${calculoId}/pdf`, '_blank')

new_string:
const comp = competencia?.replace('-', '/') ?? competencia
window.open(`/api/v3/contra-cheque/${funcionarioId}/${comp}/pdf`, '_blank')
```

---

### [O2-05] Ponto — data enviada em UTC registra dia errado

**Arquivo:** `resources/gente-v3/src/views/ponto/PontoEletronicoView.vue`
**Impacto:** Após 21h local, batida registra no dia seguinte
**Causa:** `toISOString()` retorna UTC — no Brasil (UTC-3) já é o dia seguinte.

```
old_string:
data: hoje.toISOString().slice(0, 10)

new_string:
data: `${hoje.getFullYear()}-${String(hoje.getMonth()+1).padStart(2,'0')}-${String(hoje.getDate()).padStart(2,'0')}`
```

---

### [O2-06] Escala Médica — IDs MOCK causam 500

**Arquivo:** `resources/gente-v3/src/views/escala/MatrizEscalaView.vue`
**Impacto:** GET `/api/v3/escalas/MOCK1` 500 repetidamente
**Causa:** `const escalaAtiva = ref('MOCK1')` — PHP tenta converter 'MOCK1' para int e falha.

**Passo A — Vue:**
```
old_string:
const escalaAtiva = ref('MOCK1')

new_string:
const escalaAtiva = ref(null)
```
Proteger toda chamada que usa `escalaAtiva.value` com:
```js
if (escalaAtiva.value && !String(escalaAtiva.value).startsWith('MOCK')) {
    await fetchEscala(escalaAtiva.value)
}
```

**Passo B — backend** `routes/web.php` ou onde estiver `Route::get('/escalas/{id}', ...)`:
```
old_string:
Route::get('/escalas/{id}', function (int $id) {

new_string:
Route::get('/escalas/{id}', function ($id) {
    if (!is_numeric($id)) return response()->json(['erro' => 'ID inválido'], 422);
    $id = (int) $id;
```

---

### [O2-07] Portal do Gestor — "Ver ficha" exibe toast em vez de navegar

**Arquivo:** `resources/gente-v3/src/views/rh/PortalGestorView.vue` linha ~312
**Impacto:** Clicar em "Ver ficha" não navega
**Causa:** Função usa `showToast()` em vez de `router.push()`.

```
old_string:
const verFicha = (m) => { showToast(`🔍 Abrindo ficha de ${m.nome}...`, 'ok') }

new_string:
const verFicha = (m) => { router.push(`/funcionarios/${m.id}`) }
```

---

### Relatório esperado da ONDA 2

```
[O2-01] Cargo.php + cargos_salarios.php — CARGO_CBO removido
[O2-02] estagiarios.php — middleware wrapper removido
[O2-03] meu_perfil.php — try/catch internos removidos
[O2-04] ContraChequeView.vue — URL do PDF corrigida
[O2-05] PontoEletronicoView.vue — data local em vez de UTC
[O2-06] MatrizEscalaView.vue + escalas route — MOCK e int fix
[O2-07] PortalGestorView.vue — verFicha navega
```

---

## ONDA 3 — Criar rotas/arquivos novos (5 tasks)

> Aguardar aprovação da ONDA 2 antes de iniciar.
> Cada novo arquivo de rota deve seguir o padrão: sem middleware wrapper interno, herdar contexto do pai.

---

### [O3-01] POST /afastamentos — rota inexistente

**Arquivo a criar:** `routes/afastamentos_v3.php`
**Impacto:** 404 ao clicar "Enviar Solicitação" em Férias/Licenças
**Causa:** `FeriasLicencasView.vue` chama `POST /api/v3/afastamentos` — rota não existe.

Criar `routes/afastamentos_v3.php`:
```php
<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/afastamentos', function () {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['afastamentos' => []]);
    $rows = DB::table('AFASTAMENTO')
        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('AFASTAMENTO_DATA_INICIO')->get();
    return response()->json(['afastamentos' => $rows]);
});

Route::post('/afastamentos', function (\Illuminate\Http\Request $request) {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
    $id = DB::table('AFASTAMENTO')->insertGetId([
        'FUNCIONARIO_ID'          => $func->FUNCIONARIO_ID,
        'AFASTAMENTO_TIPO'        => $request->tipo,
        'AFASTAMENTO_DATA_INICIO' => $request->inicio,
        'AFASTAMENTO_DATA_FIM'    => $request->fim,
        'AFASTAMENTO_OBS'         => $request->obs,
        'AFASTAMENTO_STATUS'      => 'pendente',
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
    return response()->json([
        'ok'        => true,
        'id'        => $id,
        'protocolo' => 'AFT-' . str_pad($id, 5, '0', STR_PAD_LEFT),
    ], 201);
});
```

Após criar, adicionar no Bloco 1 do `web.php`:
```
old_string:
    require __DIR__ . '/progressao_funcional.php'; // Progressão Funcional

new_string:
    require __DIR__ . '/progressao_funcional.php'; // Progressão Funcional
    require __DIR__ . '/afastamentos_v3.php';       // Afastamentos/Licenças
```

**Verificação:**
```bash
php artisan route:list --path=api/v3/afastamentos 2>&1
```

---

### [O3-02] GET+POST /escala-trabalho — rota inexistente

**Arquivo:** adicionar direto em `routes/web.php` — Bloco 1 NÃO — criar `routes/escala_trabalho.php`
**Impacto:** `EscalaTrabalhoView.vue` retorna 404 ao entrar na tela
**Causa:** Rota `GET /api/v3/escala-trabalho` não existe (diferente de `/escalas` médicas).

Criar `routes/escala_trabalho.php`:
```php
<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['escala' => []]);
    $mes = $request->mes ?? now()->month;
    $ano = $request->ano ?? now()->year;
    $comp = sprintf('%04d-%02d', $ano, $mes);
    $escala = DB::table('ESCALA as e')
        ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
        ->where('de.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->where('e.ESCALA_COMPETENCIA', $comp)
        ->select('e.*', 'de.*')
        ->get();
    return response()->json(['escala' => $escala, 'competencia' => $comp]);
});

Route::post('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    return response()->json(['ok' => true]);
});
```

Adicionar require no Bloco 1 do web.php após afastamentos_v3.

**Verificação:**
```bash
php artisan route:list --path=api/v3/escala-trabalho 2>&1
```

---

### [O3-03] POST /escalas e GET /setores — rotas inexistentes

**Arquivo:** adicionar em `routes/escala_saude.php` ou criar `routes/escalas_admin.php`
**Impacto:** 405 ao criar nova escala médica; 404 no seletor de setores
**Causa:** Só existe `GET /escalas` e `GET /escalas/{id}` — falta o POST e o /setores.

Verificar primeiro se `routes/escala_saude.php` já tem `POST /escalas` antes de criar. Se não tiver, adicionar:

```php
Route::post('/escalas', function (\Illuminate\Http\Request $request) {
    $id = DB::table('ESCALA')->insertGetId([
        'SETOR_ID'           => $request->setor_id,
        'ESCALA_COMPETENCIA' => $request->competencia,
        'ESCALA_SITUACAO'    => 'rascunho',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
    return response()->json(['ok' => true, 'ESCALA_ID' => $id], 201);
});

Route::get('/setores', function () {
    $setores = DB::table('SETOR')
        ->where('SETOR_ATIVO', 1)
        ->orderBy('SETOR_NOME')
        ->select('SETOR_ID as id', 'SETOR_NOME as nome')
        ->get();
    return response()->json(['setores' => $setores]);
});
```

**Verificação:**
```bash
php artisan route:list --path=api/v3/setores 2>&1
php artisan route:list --path=api/v3/escalas 2>&1
```

---

### [O3-04] GET /autocadastro/pendentes e POST /gerar-link — rotas inexistentes

**Arquivo:** adicionar em `routes/web.php` no grupo público (fora do auth) OU dentro do grupo auth se for admin-only
**Impacto:** Tela de gestão de autocadastro não carrega e não gera links
**Nota:** Verificar no Vue se essas rotas exigem autenticação ou são públicas antes de posicioná-las.

Rotas a criar (dentro do grupo autenticado):
```php
Route::get('/autocadastro/pendentes', function () {
    $pendentes = DB::table('AUTOCADASTRO_TOKEN')
        ->where('TOKEN_STATUS', 'pendente')
        ->orderByDesc('created_at')
        ->get();
    return response()->json(['pendentes' => $pendentes]);
});

Route::post('/autocadastro/gerar-link', function (\Illuminate\Http\Request $request) {
    $token = \Illuminate\Support\Str::uuid();
    DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN'        => (string) $token,
        'TOKEN_STATUS' => 'pendente',
        'TOKEN_EMAIL'  => $request->email,
        'expira_em'    => now()->addDays(7),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    return response()->json(['link' => url("/autocadastro/{$token}"), 'token' => $token]);
});
```

Criar `routes/autocadastro_admin.php` com essas rotas e adicionar require no Bloco 1.

**Verificação:**
```bash
php artisan route:list --path=api/v3/autocadastro 2>&1
```

---

### [O3-05] Portal do Gestor — query usa colunas erradas (FUNCIONARIO_NOME)

**Arquivo:** `routes/gestor.php` linhas ~25-30
**Impacto:** Cards da equipe exibem sem nome, cargo e identificação
**Causa:** Query usa `FUNCIONARIO_NOME` e `FUNCIONARIO_SOBRENOME` — colunas que não existem. O nome real está em `PESSOA.PESSOA_NOME`.

Localizar a query que monta a lista de equipe e substituir para fazer JOIN com PESSOA:
```php
// ANTES:
'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
'cargo' => $f->CARGO_NOME ?? $f->FUNCIONARIO_CARGO ?? '',

// DEPOIS — adicionar join com PESSOA e CARGO na query:
->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
// e nos selects:
'nome'  => $f->PESSOA_NOME ?? '—',
'cargo' => $f->CARGO_NOME  ?? '—',
```

**Verificação:**
```bash
php artisan route:list --path=api/v3/gestor 2>&1 | head -5
```

---

### Relatório esperado da ONDA 3

```
[O3-01] afastamentos_v3.php criado + require adicionado no web.php
[O3-02] escala_trabalho.php criado + require adicionado no web.php
[O3-03] POST /escalas e GET /setores adicionados
[O3-04] autocadastro_admin.php criado + require adicionado no web.php
[O3-05] gestor.php — join com PESSOA corrigido
```

---

## ONDA 4 — Migrations e verificações de banco (verificar antes de corrigir)

> Aguardar aprovação da ONDA 3 antes de iniciar.
> Esta onda é de diagnóstico + ação condicional — verificar primeiro, corrigir só se necessário.

---

### [O4-01] Verificar tabelas não migradas

Executar no terminal do projeto:
```bash
php artisan migrate:status 2>&1 | Select-String "No "
```

Se houver migrations pendentes (`No` na coluna Ran?), rodar:
```bash
php artisan migrate
```

Tabelas específicas a confirmar existência:
- `HORA_EXTRA` — para módulo Hora Extra
- `PSS_EDITAL` — para PSS/Concurso
- `TERCEIRIZADO_EMPRESA`, `TERCEIRIZADO_POSTO` — para Terceirizados
- `ACUMULACAO_CARGO` — para Acumulação de Cargos
- `SOBREAVISO_ACIONAMENTO` — para Sobreaviso

Reportar resultado completo do `migrate:status`.

---

### [O4-02] Organograma — nova diretoria some após F5

**Arquivo:** `routes/organograma_v3.php`
**Impacto:** Nova diretoria criada aparece imediatamente (estado Vue), some no F5
**Causa:** Query começa em SETOR — unidades sem setores vinculados não aparecem.

Modificar a query para começar em UNIDADE e incluir todas, com ou sem setores:
```php
// Substituir query atual por:
$unidades = DB::table('UNIDADE')
    ->where('UNIDADE_ATIVA', 1)
    ->orderBy('UNIDADE_NOME')
    ->get();
// Iterar por unidades e buscar setores de cada uma
```

---

### [O4-03] Meu Perfil — verificar campos existentes no banco

Após fix O2-03 (remover try/catch), o save passará a propagar erros reais. Se campos como `PESSOA_ESTADO_CIVIL` ou `PESSOA_ESCOLARIDADE` não existirem na tabela PESSOA do SQL Server, o save vai retornar 500. Verificar antes de testar:

```bash
php artisan tinker --execute="DB::select(\"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'PESSOA' ORDER BY COLUMN_NAME\")" 2>&1
```

Reportar lista de colunas. Se algum campo referenciado em meu_perfil.php não existir, remover do PUT.

---

### [O4-04] Hora Extra — verificar tabela e rotas

```bash
php artisan tinker --execute="DB::select(\"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'HORA_EXTRA'\")" 2>&1
```

Se vazio: rodar migration específica:
```bash
php artisan migrate --path=database/migrations/2026_03_11_000006_create_hora_extra_tables.php
```

---

### Relatório esperado da ONDA 4

```
[O4-01] migrate:status output completo
[O4-02] organograma_v3.php query corrigida
[O4-03] lista de colunas da tabela PESSOA
[O4-04] status da tabela HORA_EXTRA + migrate se necessário
```

---

## FASE 1 — Para sprint subsequente (não desta sprint)

Os itens abaixo foram catalogados pelo QA mas **não bloqueiam** o uso do sistema. Serão abordados na próxima sprint após validação do PoC.

| ID | Descrição | Arquivo |
|---|---|---|
| F1-01 | Ícones corrompidos UTF-8 (FeriasLicencas, BancoHoras, etc) | `FeriasLicencasView.vue` e outros |
| F1-02 | Sidebar profile sem navegação | `DashboardLayout.vue` |
| F1-03 | Declarações — download com IDs mock | `DeclaracoesView.vue` |
| F1-04 | Progressão — admin sem CARREIRA_ID (seed) | SQL seed |
| F1-05 | Sobreaviso — Vue otimista antes do servidor | `plantoes_sobreaviso.php` |
| F1-06 | Escala Médica — blade view inexistente (compartilhar) | `EscalaController.php` |
| F1-07 | SESMT Medicina — KPIs 500 (migration) | `medicina.php` |
| F1-08 | Holerites — sem identificação do funcionário | `ContraChequeController.php` |
| F1-09 | Diárias — aceita data no passado | frontend + backend |

---

## FASE 2 — Melhorias UX (backlog)

| ID | Descrição |
|---|---|
| F2-01 | Organograma modo cards sem CRUD |
| F2-02 | Diárias — total mês não atualiza após prestação |
| F2-03 | Avaliações, Benefícios, Treinamentos, SESMT — telas esqueleto |
| F2-04 | Holerites — filtro por ano/competência |

---

## SEEDS PENDENTES (não são bugs de código)

| Bug | Depende de |
|---|---|
| Banco de Horas sempre zero | `MasterBancoHorasSeeder` |
| Equipe vazia no gestor | Lotação do admin com `LOTACAO_DATA_FIM = NULL` |
| Escala de Trabalho vazia | Verificar se ESCALA cobre escala de trabalho além da médica |
| Substituições de Plantão vazias | Seed de substituições |

---

## CHECKLIST DE AUDITORIA POR ONDA

Claude verifica cada item via MCP antes de aprovar a onda seguinte.

### ONDA 1 checklist
- [ ] O1-01: `CAST(REGISTRO_DATA_HORA AS DATE)` confirmado em web.php
- [ ] O1-02: Nenhuma ocorrência de `dd(` em ChecarAcessoUsuarioUnidade.php
- [ ] O1-03: `progressao_funcional.php` aparece no `route:list`
- [ ] O1-04: `api.dicebear.com` presente no img-src de SecurityHeaders.php
- [ ] O1-05: `@click` presente no topbar-avatar do DashboardLayout.vue

### ONDA 2 checklist
- [ ] O2-01: `CARGO_CBO` ausente do $fillable e das queries
- [ ] O2-02: `estagiarios.php` sem middleware wrapper; rotas no route:list
- [ ] O2-03: `meu_perfil.php` sem try/catch internos
- [ ] O2-04: URL do PDF corrigida no ContraChequeView.vue
- [ ] O2-05: Data local em vez de UTC no PontoEletronicoView.vue
- [ ] O2-06: `escalaAtiva = ref(null)` e guard no MatrizEscalaView.vue
- [ ] O2-07: `router.push` no verFicha() do PortalGestorView.vue

### ONDA 3 checklist
- [ ] O3-01: `/api/v3/afastamentos` GET e POST no route:list
- [ ] O3-02: `/api/v3/escala-trabalho` GET e POST no route:list
- [ ] O3-03: `/api/v3/escalas` POST e `/api/v3/setores` GET no route:list
- [ ] O3-04: `/api/v3/autocadastro/pendentes` e `/gerar-link` no route:list
- [ ] O3-05: gestor.php com JOIN em PESSOA

### ONDA 4 checklist
- [ ] O4-01: Zero migrations pendentes
- [ ] O4-02: organograma_v3.php query começa em UNIDADE
- [ ] O4-03: Campos de meu_perfil.php existem na tabela PESSOA
- [ ] O4-04: Tabela HORA_EXTRA existe no banco

---

*Gerado em: 2026-04-15 | Baseado na auditoria de Davi (QA) + validação Claude/MCP*
*Bugs de responsabilidade de Davi: 0*
