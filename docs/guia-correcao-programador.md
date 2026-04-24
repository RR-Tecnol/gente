# GENTE v3 — Guia Técnico de Correção
> Documento para o desenvolvedor responsável. Stack: Laravel 8, Vue 3/Vite, SQL Server, Docker.
> Raiz do projeto: conforme o clone local (ex.: `.../gente/gente`); portas típicas: backend **8081**, Vite **5173**. O caminho `X:\gente\...` no original é apenas referência de ambiente Windows.
> Bugs classificados por autor: RR Tecnol (Sprint 0, commit eb6ba26, 2026-03-15) ou joao baluz (commits 2026-03-31).
> Nenhum dos bugs abaixo foi introduzido por Davi.

---

## COMO USAR ESTE GUIA

Para cada bug: leia **CAUSA**, entenda o **EFEITO** visível, aplique a **CORREÇÃO** exata.
Os bugs estão ordenados do mais crítico (FASE 0, bloqueia uso) ao menor (FASE 2, melhoria).
Corrija sempre na ordem apresentada — alguns bugs em FASE 0 são pré-requisito para testar os de FASE 1.

---

## Mapa de baterias e verificação (repositório atual)

O texto abaixo ([FASE 0] … [FASE 2] … BUG-xxx) descreve **o defeito original e a correção sugerida** no momento em que o guia foi escrito. O repositório pode já ter incorporado parte disso.

- **Verificação item a item (situação × código, por bateria A–L):** ver [`guia-baterias-verificacao-2026-04-22.md`](./guia-baterias-verificacao-2026-04-22.md).
- **Síntese:** baterias **A–C** (FASE 0 inicial) estão em grande parte **atendidas** no código (rotas, `CAST` no ponto, autocadastro, afastamentos, escala, smoke API, build Vue, `lucide`, app ponto, auth duplicado removido). Itens que dependem **só de ambiente/DB** (migrations, seeds, ERP) permanecem **condicionais**. Itens de **UX/organograma “some no F5”** e **perfil silencioso** exigem **teste manual**.

---

# FASE 0 — Bloqueadores críticos (corrigir primeiro)

---

## [FASE 0] Ponto e Banco de Horas — `DATE()` inexistente no SQL Server

**Arquivo:** `routes/web.php` — linha ~4435 (dentro de `Route::get('/ponto', ...)`)

**Causa:** A query usa `DB::raw("DATE(REGISTRO_DATA_HORA)")`. A função `DATE()` não existe no SQL Server — é exclusiva do MySQL/SQLite. Lança `SQLSTATE: Invalid function name 'DATE'` que é capturado pelo `catch` silencioso retornando `['registros' => []]`.

**Efeito:** O GET do ponto sempre retorna array vazio. O Vue detecta o array vazio e ativa `gerarRegistrosMock()`. O botão de bater ponto reinicia para "Registrar Entrada" mesmo com batidas já feitas, causando duplicatas no banco. O Banco de Horas herda o mesmo problema e nunca exibe dados reais.

**Correção:**
```php
// ANTES:
->whereBetween(DB::raw("DATE(REGISTRO_DATA_HORA)"), [$inicio, $fim])

// DEPOIS:
->whereBetween(DB::raw("CAST(REGISTRO_DATA_HORA AS DATE)"), [$inicio, $fim])
```

---

## [FASE 0] Dashboard — Avatar sem ação de navegação

**Arquivo:** `resources/gente-v3/src/layouts/DashboardLayout.vue`

**Causa:** `<div class="topbar-avatar" :title="userName">` não tem handler `@click`.

**Efeito:** Usuário clica no avatar esperando ir ao perfil — nada acontece.

**Correção:**
```html
<!-- ANTES: -->
<div class="topbar-avatar" :title="userName">{{ userInitials }}</div>

<!-- DEPOIS: -->
<div class="topbar-avatar" :title="userName" @click="$router.push('/meu-perfil')" style="cursor:pointer">{{ userInitials }}</div>
```

---

## [FASE 0] Cargos e Salários — `CARGO_CBO` não existe na tabela SQL Server

**Arquivo:** `routes/cargos_salarios.php` linhas 51 e 82 / `app/Models/Cargo.php` fillable

**Causa:** O model `Cargo` tem `CARGO_CBO` no `$fillable`. O `POST /cargos` atribui `$cargo->CARGO_CBO` e depois chama `$cargo->save()`. O Eloquent inclui `CARGO_CBO` no INSERT SQL. A tabela `CARGO` no SQL Server não tem essa coluna. O `try/catch` está na **atribuição** do campo, não no `save()` — então o erro acontece no save mesmo que a atribuição seja silenciada.

**Efeito:** `SQLSTATE[42S22]: Invalid column name 'CARGO_CBO'`. Qualquer tentativa de criar ou editar cargo retorna 500.

**Correção — opção A (remover o campo):**
```php
// Em app/Models/Cargo.php, remover 'CARGO_CBO' do $fillable
// Em routes/cargos_salarios.php, remover as linhas:
$cargo->CARGO_CBO = $request->CARGO_CBO ?? null;
// e no PUT, remover 'CARGO_CBO' do array $campos
```

**Correção — opção B (adicionar a coluna ao banco):**
```sql
ALTER TABLE CARGO ADD CARGO_CBO VARCHAR(10) NULL;
```

---

## [FASE 0] Gerir Progressões — Arquivo de rotas não incluído no web.php

**Arquivo:** `routes/web.php`

**Causa (histórico):** faltava `require __DIR__ . '/progressao_funcional.php'` no grupo `api/v3` autenticado.

**Efeito (se ainda reproduzível):** 404 no módulo "Gerir Progressões".

> **Atualização 2026-04-22 (código):** `require __DIR__ . '/progressao_funcional.php'` consta no `web.php` (há **mais de um** `require` do mesmo arquivo em blocos diferentes — possível duplicação de rotas; tratar em refator se necessário).

**Correção — adicionar 1 linha no grupo autenticado do web.php (próximo dos outros require):**
```php
// Dentro do grupo Route::middleware(['web', 'auth'])->prefix('api/v3')->group(...)
// Junto dos outros require existentes (linha ~879):
require __DIR__ . '/progressao_funcional.php';
```

---

## [FASE 0] Estagiários — Grupo de middleware interno quebra o prefix api/v3

**Arquivo:** `routes/estagiarios.php`

**Causa (histórico):** o arquivo podia definir um `Route::middleware(...)->group` interno e quebrar o prefixo `api/v3`.

**Efeito (se ainda reproduzível):** 404 em `/api/v3/estagiarios`.

> **Atualização 2026-04-22 (código):** `routes/estagiarios.php` **não** envolve as rotas em outro `middleware()->group` — o padrão “depois” abaixo já reflete a estrutura atual.

**Correção:** Abrir `routes/estagiarios.php` e remover o wrapper `Route::middleware()->group()` — as rotas devem estar no nível raiz do arquivo, herdando o contexto do pai:
```php
// ANTES (estrutura incorreta):
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/estagiarios', function (Request $req) { ... });
    Route::post('/estagiarios', function (Request $req) { ... });
});

// DEPOIS (herdar do pai):
Route::get('/estagiarios', function (Request $req) { ... });
Route::post('/estagiarios', function (Request $req) { ... });
```

---

## [FASE 0] Autocadastro — Rotas de gestão inexistentes

**Arquivo:** `routes/web.php` — ausência das rotas

**Causa (histórico):** As rotas de gestão (`GET /autocadastro/pendentes` e `POST /autocadastro/gerar-link`) **não existiam** no `web.php` autenticado.

**Efeito (se ainda reproduzível):** 404 na gestão de autocadastro.

> **Atualização 2026-04-22 (código):** Essas rotas estão definidas em `routes/web.php` dentro do prefixo `api/v3` autenticado (por volta das linhas comentadas como “Autocadastro gestão”).

**Correção — criar as rotas no grupo autenticado:**
```php
Route::get('/autocadastro/pendentes', function () {
    $pendentes = DB::table('AUTOCADASTRO_TOKEN')
        ->where('TOKEN_STATUS', 'pendente')
        ->orderByDesc('created_at')
        ->get();
    return response()->json(['pendentes' => $pendentes]);
});

Route::post('/autocadastro/gerar-link', function (Request $request) {
    $token = \Illuminate\Support\Str::uuid();
    DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN_VALOR' => $token,
        'TOKEN_STATUS' => 'pendente',
        'TOKEN_EMAIL' => $request->email,
        'created_at' => now(),
    ]);
    return response()->json(['link' => url("/autocadastro/{$token}")]);
});
```

---

## [FASE 0] Afastamentos/Licenças — Rota POST inexistente

**Arquivo:** ausência em `routes/`

**Causa (histórico):** `FeriasLicencasView.vue` chama `POST /api/v3/afastamentos`, rota que **não existia** no repositório antigo.

**Efeito (se ainda reproduzível):** 404 ao enviar afastamento.

> **Atualização 2026-04-22 (código):** `routes/afastamentos_v3.php` é carregado via `require` no `web.php` (há entradas duplicadas de `require` para o mesmo arquivo em blocos distintos — redundante, mas funcional). Validar com smoke/API real.

**Correção — criar arquivo `routes/afastamentos_v3.php`** com o padrão dos outros módulos:
```php
Route::get('/afastamentos', function () {
    $user = Auth::user();
    $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['afastamentos' => []]);
    $rows = DB::table('AFASTAMENTO')
        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('AFASTAMENTO_DATA_INICIO')->get();
    return response()->json(['afastamentos' => $rows]);
});

Route::post('/afastamentos', function (Request $request) {
    $user = Auth::user();
    $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
    $id = DB::table('AFASTAMENTO')->insertGetId([
        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
        'AFASTAMENTO_TIPO' => $request->tipo,
        'AFASTAMENTO_DATA_INICIO' => $request->inicio,
        'AFASTAMENTO_DATA_FIM' => $request->fim,
        'AFASTAMENTO_OBS' => $request->obs,
        'AFASTAMENTO_STATUS' => 'pendente',
        'created_at' => now(),
    ]);
    return response()->json(['ok' => true, 'id' => $id, 'protocolo' => 'AFT-' . str_pad($id, 5, '0', STR_PAD_LEFT)], 201);
});
```
Adicionar `require __DIR__ . '/afastamentos_v3.php';` no grupo autenticado do `web.php`.

---

## [FASE 0] Escala de Trabalho — Rota GET inexistente

**Arquivo:** ausência em `routes/`

**Causa (histórico):** `EscalaTrabalhoView.vue` chama `GET /api/v3/escala-trabalho`, rota **inexistente** no repositório antigo (confundir com `GET /api/v3/escalas` médica).

**Efeito (se ainda reproduzível):** 404 ao abrir a tela.

> **Atualização 2026-04-22 (código):** `GET /api/v3/escala-trabalho` (e `POST` correspondente) está em `routes/web.php` com implementação completa. O snippet abaixo é a forma **mínima** de referência; a implementação atual pode divergir.

**Correção — criar a rota no grupo autenticado:**
```php
Route::get('/escala-trabalho', function (Request $request) {
    $user = Auth::user();
    $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
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

Route::post('/escala-trabalho', function (Request $request) {
    // Salvar ajustes na escala do servidor
    return response()->json(['ok' => true]);
});
```

---

## [FASE 0] Escala Médica — IDs mock causam 500

**Arquivo:** `resources/gente-v3/src/views/escala/MatrizEscalaView.vue` + `routes/web.php:826`

**Causa:** O Vue usa IDs fictícios `'MOCK1'` e `'MOCK2'` como fallback quando não há escalas. A rota backend `GET /api/v3/escalas/{id}` declara o parâmetro como `int $id`. O PHP tenta converter `'MOCK1'` para `int`, falha, e a query SQL recebe um valor inválido lançando 500.

**Efeito:** `GET /api/v3/escalas/MOCK1 500` repetidamente ao entrar na tela.

**Correção — dois passos:**

1. No Vue (`MatrizEscalaView.vue`), substituir o estado inicial de `escalaAtiva` de `'MOCK1'` para `null` e só buscar quando há ID válido:
```js
// ANTES:
const escalaAtiva = ref('MOCK1')

// DEPOIS:
const escalaAtiva = ref(null)
// e proteger a chamada:
if (escalaAtiva.value && !String(escalaAtiva.value).startsWith('MOCK')) {
    await fetchEscala(escalaAtiva.value)
}
```

2. No backend, tornar o parâmetro tolerante:
```php
// ANTES:
Route::get('/escalas/{id}', function (int $id) {

// DEPOIS:
Route::get('/escalas/{id}', function ($id) {
    if (!is_numeric($id)) return response()->json(['erro' => 'ID inválido'], 422);
    $id = (int) $id;
```

> **Atualização 2026-04-22 (código):** Em `MatrizEscalaView.vue`, `carregarEscala()` **não** chama `GET /api/v3/escalas/{id}` quando o ID é `MOCK*` (`startsWith('MOCK')`). O fallback com `ESCALA_ID: MOCK1/MOCK2` só entra se `GET /escalas` falhar; com API ok, use escalas reais. Ajuste o backend conforme o snippet acima se ainda houver rota com type-hint `int` que receba string.

---

## [FASE 0] Escala Médica — POST /escalas retorna 405 e GET /setores retorna 404

**Arquivo:** `routes/web.php` — ausência das rotas

**Causa:** `POST /api/v3/escalas` não existe (só `GET /escalas` e `GET /escalas/{id}`). `GET /api/v3/setores` também não existe.

**Efeito:** 405 ao criar nova escala, 404 no seletor de setores.

> **Atualização 2026-04-22 (código):** `GET /api/v3/setores` e `POST /api/v3/escalas` existem no grupo autenticado; validação automática (smoke) retornou **2xx** com payloads de teste. O bloco abaixo permanece como **referência** do que foi necessário criar.

**Correção:**
```php
// Adicionar no grupo autenticado:
Route::post('/escalas', function (Request $request) {
    $id = DB::table('ESCALA')->insertGetId([
        'SETOR_ID' => $request->setor_id,
        'ESCALA_COMPETENCIA' => $request->competencia,
        'ESCALA_SITUACAO' => 'rascunho',
        'created_at' => now(),
        'updated_at' => now(),
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

---

## [FASE 0] Portal do Gestor — Equipe sem nome

**Arquivo:** `routes/gestor.php` linhas 25-30

**Causa:** A query usa `FUNCIONARIO_NOME` e `FUNCIONARIO_SOBRENOME` — colunas que **não existem** no model `Funcionario`. O sistema usa `pessoa.PESSOA_NOME`. O campo retorna string vazia.

**Efeito:** Cards da equipe exibem sem nome, cargo e qualquer identificação.

**Correção:**
```php
// ANTES:
'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
'cargo' => $f->CARGO_NOME ?? $f->FUNCIONARIO_CARGO ?? '',

// DEPOIS — fazer join com PESSOA:
$equipe = \App\Models\Funcionario::with(['pessoa', 'lotacao.setor', 'cargo'])
    ->where(/* filtro de setor */)
    ->take(25)->get()->map(fn($f) => [
        'id'    => $f->FUNCIONARIO_ID,
        'nome'  => $f->pessoa?->PESSOA_NOME ?? '—',
        'cargo' => $f->cargo?->CARGO_NOME ?? '—',
        'matricula' => $f->FUNCIONARIO_MATRICULA,
        'statusLabel' => 'Ativo',
    ])->toArray();
```

---

## [FASE 0] Portal do Gestor — Avatares bloqueados por CSP

**Arquivo:** `app/Http/Middleware/SecurityHeaders.php` linha 38

**Causa:** A CSP define `img-src 'self' data: blob:` sem incluir `https://api.dicebear.com`. O browser bloqueia o carregamento das imagens de avatar.

**Efeito:** Todos os avatares dos cards de funcionários aparecem quebrados.

**Correção:**
```php
// ANTES:
"img-src 'self' data: blob:; " .

// DEPOIS:
"img-src 'self' data: blob: https://api.dicebear.com; " .
```
**Alternativa recomendada:** Gerar avatares localmente com CSS (iniciais em `div` colorido) e remover a dependência externa. Mais seguro e elimina o problema de CSP permanentemente.

---

## [FASE 0] Hora Extra — GET e POST retornam 500

**Arquivo:** `routes/hora_extra.php:7` / `database/migrations/2026_03_11_000006_create_hora_extra_tables.php`

**Causa:** As rotas existem em `hora_extra.php` e fazem JOIN com `HORA_EXTRA`. A tabela `HORA_EXTRA` existe na migration mas provavelmente não foi rodada no banco atual (ou foi rodada em banco diferente). O JOIN falha com 500.

**Efeito:** Entrar na tela Hora Extra → 500 imediato. Registrar hora extra → 500.

**Correção:**
```bash
# Verificar se a tabela existe no SQL Server:
php artisan tinker
# >>> DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'HORA_EXTRA'")

# Se não existir, rodar a migration:
php artisan migrate --path=database/migrations/2026_03_11_000006_create_hora_extra_tables.php
```
Se a migration já foi rodada mas a tabela não aparece: verificar se o `DB_DATABASE` no `.env` aponta para o banco correto.

---

## [FASE 0] Exoneração, PSS, Terceirizados, Acumulação — Tabelas não migradas

**Arquivos:** `routes/exoneracao.php`, `routes/pss.php`, `routes/terceirizados.php`, `routes/acumulacao.php`

**Causa:** As rotas existem e estão corretas. Os erros 500 são causados por tabelas que não existem no banco atual. As migrations existem no projeto mas podem não ter sido executadas no banco de dados em uso.

**Efeito:** Todas as telas listadas retornam 500 ao entrar.

**Correção — rodar todas as migrations pendentes:**
```bash
php artisan migrate --pretend  # ver o que será rodado primeiro
php artisan migrate             # executar
```

Se alguma migration falhar por dependência (ex: FK para tabela inexistente), identificar e rodar na ordem correta. Verificar especificamente:
- Tabela `PSS_EDITAL` (para PSS/Concurso)
- Tabela `TERCEIRIZADO_EMPRESA` e `TERCEIRIZADO_POSTO` (para Terceirizados)
- Tabela `ACUMULACAO_CARGO` (para Acumulação)

---

## [FASE 0] `dd()` ativo em ChecarAcessoUsuarioUnidade

**Arquivo:** `app/Rules/ChecarAcessoUsuarioUnidade.php` linha 33

**Causa:** `dd($usuario)` deixado ativo em produção.

**Efeito:** Qualquer rota que usa esta Rule trava a aplicação com dump HTML — DoS funcional.

**Correção:**
```php
// ANTES (linha 33):
dd($usuario);

// DEPOIS: remover completamente a linha
```

---

## [FASE 0] Organograma — Nova diretoria some após F5

**Arquivo:** `routes/organograma_v3.php` — `Route::get('/organograma', ...)`

**Causa:** O GET do organograma agrupa **setores** por `UNIDADE_ID`. Uma diretoria recém-criada sem setores vinculados não aparece na query de agrupamento. Ao recarregar, o Vue não encontra a diretoria no response e usa `mockEstrutura`.

**Efeito:** Nova diretoria aparece imediatamente (estado Vue local), some após F5.

**Correção — modificar a query para incluir diretorias sem setores:**
```php
// Substituir a query atual que começa em SETOR por uma que começa em UNIDADE:
$unidades = DB::table('UNIDADE')
    ->where('UNIDADE_ATIVA', 1)
    ->orderBy('UNIDADE_NOME')
    ->get();

foreach ($unidades as $unidade) {
    $setoresDestaUnidade = $setores->where('UNIDADE_ID', $unidade->UNIDADE_ID);
    // montar a estrutura incluindo unidades sem setores
}
```

---

## [FASE 0] Meu Perfil — Salvar retorna sucesso sem persistir

**Arquivo:** `routes/meu_perfil.php` — `Route::put('/perfil', ...)`

**Causa:** O backend tem `try/catch {}` vazio em volta de cada atribuição de campo. Se o campo não existir na tabela, o erro é silenciado. O `$pessoa->save()` executa depois mas pode não ter alterado nada. O endpoint retorna 200 com mensagem de sucesso independentemente.

**Efeito:** Usuário edita perfil, clica Salvar, vê "Perfil atualizado com sucesso!". Ao recarregar, dados voltam ao original.

**Correção — remover os try/catch internos:**
```php
// ANTES (padrão ruim repetido para cada campo):
foreach ($campos as $campo) {
    if ($request->has($campo)) {
        try {
            $pessoa->$campo = $request->$campo;
        } catch (\Throwable $e) {
        }
    }
}
$pessoa->save();

// DEPOIS:
$campos = ['PESSOA_NOME_SOCIAL', 'PESSOA_ESTADO_CIVIL', 'PESSOA_ESCOLARIDADE'];
foreach ($campos as $campo) {
    if ($request->has($campo)) {
        $pessoa->$campo = $request->$campo;
    }
}
$pessoa->save();
// Erros agora propagam para o catch externo e retornam 500 real em vez de 200 falso
```

---

## [FASE 0] Holerites — Botão PDF chama URL inexistente

**Arquivo:** `resources/gente-v3/src/views/folha/ContraChequeView.vue` (função `baixarHolerite`)

**Causa (histórico):** o SPA chegou a chamar URL de PDF incompatível com o backend (ex. caminhos com `meus-holerites/.../pdf` em versões antigas).

**Efeito (se ainda reproduzível):** 404 ou HTML errado ao abrir o PDF.

> **Atualização 2026-04-22 (código):** O `ContraChequeView.vue` chama `window.open(\`/api/v3/holerite-pdf/${detalheId}\`, '_blank')`, alinhado a `GET /api/v3/holerite-pdf/{detalheId}` no `web.php` (view Blade `v3.holerite-pdf`). Existe ainda `GET /api/v3/contra-cheque/{funcionarioId}/{competencia}/pdf` no controlador. O snippet abaixo é **referência** se a equipe preferir unificar tudo em `contra-cheque/.../pdf`:

**Correção (referência — rota alternativa por competência):**
```js
const comp = (competencia || '').toString()
window.open(`/api/v3/contra-cheque/${funcionarioId}/${comp}/pdf`, '_blank')
```

---

# FASE 1 — Impacto UX significativo

---

## [FASE 1] Ponto — Data enviada em UTC registra dia errado

**Arquivo:** `resources/gente-v3/src/views/ponto/PontoEletronicoView.vue` — função `baterPonto()`

**Causa:** `hoje.toISOString().slice(0, 10)` retorna a data em UTC. No Brasil (UTC-3), após as 21h local já é o dia seguinte em UTC.

**Correção:**
```js
// ANTES:
data: hoje.toISOString().slice(0, 10)

// DEPOIS:
data: `${hoje.getFullYear()}-${String(hoje.getMonth()+1).padStart(2,'0')}-${String(hoje.getDate()).padStart(2,'0')}`
```

---

## [FASE 1] Ponto — Férias — Ícones corrompidos em múltiplas telas

**Arquivo:** `resources/gente-v3/src/views/rh/FeriasLicencasView.vue` e outros arquivos do commit `24981214`

**Causa:** Commit de 2026-03-31 (joao baluz) corrompeu o encoding UTF-8 dos emojis. Emojis como `📎` viraram `??`, `✅` viraram `?`, `🏖️` virou `???`.

**Efeito:** Botões e abas exibem sequências de `?` no lugar de ícones.

**Correção:** Abrir `FeriasLicencasView.vue` em editor com UTF-8 explícito e substituir:

| Corrompido | Correto |
|---|---|
| `???` (nas abas de Férias) | `🏖️` |
| `??` (Afastamentos) | `📋` |
| `??` (Agendar) | `📅` |
| `??` (upload zone) | `📎` |
| `?` (arquivo selecionado) | `✅` |
| `??` (Períodos Aquisitivos) | `📊` |
| `??` (overlap warn) | `⚠️` |

Verificar também: `BancoHorasView.vue`, `FuncionariosView.vue`, `AutocadastroGestaoView.vue` do mesmo commit.

---

## [FASE 1] Portal do Gestor — "Ver ficha" não navega

**Arquivo:** `resources/gente-v3/src/views/rh/PortalGestorView.vue` linha 312

**Causa:** `const verFicha = (m) => { showToast(...) }` — apenas exibe um toast, sem navegação.

**Correção:**
```js
// ANTES:
const verFicha = (m) => { showToast(`🔍 Abrindo ficha de ${m.nome}...`, 'ok') }

// DEPOIS:
const verFicha = (m) => { router.push(`/funcionarios/${m.id}`) }
```

---

## [FASE 1] Dashboard — Sidebar profile sem navegação

**Arquivo:** `DashboardLayout.vue` — bloco `.sidebar-profile`

**Causa:** Bloco sem `@click`.

**Correção:** Adicionar `@click="$router.push('/meu-perfil')" style="cursor:pointer"` ao `<div class="sidebar-profile">`.

---

## [FASE 1] Declarações — Download falha (tabela vazia)

**Arquivo:** `resources/gente-v3/src/views/rh/DeclaracoesRequerimentosView.vue` — `mockPedidos`

**Causa:** Os pedidos exibidos usam IDs mock (1, 2, 3 hardcoded). A tabela `DECLARACAO` está vazia no banco. O download busca `DECLARACAO_ID = 1` e não encontra — retorna 404.

**Correção — duas partes:**

1. Popular a tabela `DECLARACAO` com dados reais via seed para os 19 funcionários.

2. Na função `solicitarDoc()`, para documentos instantâneos, chamar o download após o POST:
```js
if (d.instantaneo) {
  const novoId = resp.data?.id
  if (novoId) {
    window.open(`/api/v3/declaracoes/${novoId}/download`, '_blank')
  }
  toast.value = { visible: true, msg: `✅ "${d.nome}" emitido!` }
}
```

---

## [FASE 1] Minha Progressão — Exibe mock para o admin

**Causa:** A tela está corretamente conectada ao backend via `GET /api/v3/progressao-funcional`. O problema é que o admin foi criado no banco sem `CARREIRA_ID`, `FUNCIONARIO_CLASSE` e `FUNCIONARIO_REFERENCIA`. O backend retorna dados incompletos e o frontend ativa o mock.

**Correção — seed (não é bug de código):**
```sql
UPDATE FUNCIONARIO 
SET CARREIRA_ID = 1, 
    FUNCIONARIO_CLASSE = 'A',
    FUNCIONARIO_REFERENCIA = 'I',
    FUNCIONARIO_DATA_ULTIMA_PROGRESSAO = '2023-03-15'
WHERE USUARIO_ID = (SELECT USUARIO_ID FROM USUARIO WHERE USUARIO_LOGIN = 'admin');
```
Garantir que exista ao menos 1 registro em `PROGRESSAO_CONFIG` e 1 em `TABELA_SALARIAL` para `CARREIRA_ID = 1`.

---

## [FASE 1] Sobreaviso — Acionamento 500, dado some no F5

**Arquivo:** `routes/plantoes_sobreaviso.php` — `POST /sobreaviso/acionamento`

**Causa:** O Vue adiciona o dado localmente ao estado antes da confirmação do servidor (Vue otimista). O POST falha no servidor (500 — coluna ou tabela não migrada). No F5, o Vue mostra o estado real do banco (sem o dado).

**Correção:**
1. Verificar logs do Laravel para o erro exato: `storage/logs/laravel.log`
2. Rodar `php artisan migrate` para garantir que `SOBREAVISO_ACIONAMENTO` existe
3. No Vue, mover a atualização do estado local para DEPOIS da confirmação do servidor:
```js
// Padrão a seguir:
const resp = await api.post('/api/v3/sobreaviso/acionamento', payload)
// Só depois de sucesso, atualizar o estado local:
acionamentos.value.push(resp.data.acionamento)
```

---

## [FASE 1] Plantões Extras — Mesmo padrão do Sobreaviso

**Arquivo:** `routes/plantoes_sobreaviso.php:58`

Mesma causa e correção do Sobreaviso — Vue otimista + 500 no servidor. Verificar logs, rodar migrations, mover atualização de estado para após confirmação.

---

## [FASE 1] Escala Médica — Botão compartilhar → erro 500

**Arquivo:** `app/Http/Controllers/EscalaController.php:53`

**Causa:** O botão navega para `/escala/view` que tenta renderizar a Blade view `escala.escala_view`. A view não existe.

**Correção — criar o arquivo:** `resources/views/escala/escala_view.blade.php` com o conteúdo adequado, ou redirecionar para a rota Vue:
```php
// Em EscalaController@view, substituir:
return view('escala.escala_view', compact('escala'));
// por:
return redirect("/escala-matriz-v3?escala={$escala->ESCALA_ID}");
```

---

## [FASE 1] SESMT Medicina — KPIs e agendamentos retornam 500

**Arquivo:** `routes/medicina.php` ou `routes/medicina_admin.php`

**Causa:** As rotas `/medicina-admin/kpis` e `/medicina-admin/agendamentos` existem mas lançam 500 — tabelas de medicina ocupacional provavelmente não migradas.

**Correção:** Rodar `php artisan migrate` e verificar logs para o erro SQL específico.

---

# FASE 2 — Melhorias e UX

---

## [FASE 2] Holerites — Sem identificação do funcionário nos cards

**Arquivo:** `app/Http/Controllers/ContraChequeController.php` — `listarMinhasFolhas()`

**Causa:** A response não inclui dados de identificação do funcionário.

**Correção:** Adicionar `with('funcionario.pessoa')` e incluir `PESSOA_NOME`, `FUNCIONARIO_MATRICULA`, `PESSOA_CPF` na response. Atualizar o card no Vue para exibir esses campos.

---

## [FASE 2] Holerites — Sem filtro por ano/competência

**Arquivo:** `ContraChequeView.vue`

**Correção:** Adicionar `ref('anoFiltro')` com select de anos disponíveis e computed `holeiritesFiltrados` que filtra o array por ano.

---

## [FASE 2] Organograma — Modo cards sem CRUD

**Arquivo:** `OrganogramaView.vue` — bloco `v-if="modo === 'cards'"`

**Correção:** Adicionar botões Editar/Excluir ao modo cards, chamando as mesmas funções `editarDiretoria()` e `excluirDiretoria()` já existentes no modo árvore.

---

## [FASE 2] Organograma — "Sem Diretoria" sem editar/excluir

**Arquivo:** `OrganogramaView.vue` — bloco "Sem Diretoria"

**Correção:** Adicionar botões de ação ao bloco que renderiza a seção "Sem Diretoria", igual ao padrão das outras diretorias.

---

## [FASE 2] Diárias — Aceita data no passado

**Arquivo:** frontend do módulo de diárias + rota backend

**Correção:**
```html
<!-- No input de data: -->
<input type="date" :min="hoje" v-model="form.data_saida">
```
```php
// No backend:
if ($request->data_saida < now()->toDateString()) {
    return response()->json(['erro' => 'Data de saída não pode ser no passado.'], 422);
}
```

---

## [FASE 2] Portal do Gestor — Avaliar sem histórico visível

**Arquivo:** `PortalGestorView.vue` — botão Avaliar

Investigar qual rota é chamada, confirmar se a tabela de avaliações existe e se "Ver ficha" deveria listar o histórico. Implementar navegação para `/funcionarios/{id}` com aba de avaliações.

---

## [FASE 2] Avaliações da Equipe / Benefícios / Treinamentos / SESMT — Telas sem frontend

**Causa:** Entregues como esqueleto no Sprint 0 — Vue existe mas sem estilização, layout ou componentes visuais.

**Ação:** Implementar os componentes Vue dessas telas seguindo o design system do GENTE v3 (gradiente hero, cards com border-radius 18px, paleta azul/indigo do `DashboardLayout`).

---

# RESUMO DE PRIORIDADES

## Correções de 1 linha (máximo impacto, mínimo esforço)

1. `routes/web.php ~4435` — trocar `DATE()` por `CAST(... AS DATE)` → desbloqueia Ponto + Banco de Horas
2. `routes/web.php` — adicionar `require __DIR__ . '/progressao_funcional.php'` → desbloqueia Gerir Progressões
3. `app/Rules/ChecarAcessoUsuarioUnidade.php:33` — remover `dd($usuario)` → remove DoS
4. `app/Http/Middleware/SecurityHeaders.php:38` — adicionar `https://api.dicebear.com` ao img-src
5. `DashboardLayout.vue` — adicionar `@click="$router.push('/meu-perfil')"` ao topbar-avatar

## Correções de arquivo único

6. `routes/cargos_salarios.php` — remover CARGO_CBO do fillable e do INSERT
7. `routes/estagiarios.php` — remover middleware group interno
8. `routes/meu_perfil.php` — remover try/catch internos do PUT /perfil
9. `ContraChequeView.vue:216` — corrigir URL do PDF
10. `PontoEletronicoView.vue` — corrigir `toISOString()` para data local
11. `MatrizEscalaView.vue` — substituir IDs MOCK1/MOCK2 por null
12. `PortalGestorView.vue:312` — substituir toast por router.push no verFicha()

## Requer criar rotas novas

13. `POST /api/v3/afastamentos` — criar arquivo `routes/afastamentos_v3.php`
14. `GET + POST /api/v3/escala-trabalho` — adicionar ao web.php
15. `POST /api/v3/escalas` e `GET /api/v3/setores` — adicionar ao web.php
16. `GET /api/v3/autocadastro/pendentes` e `POST /api/v3/autocadastro/gerar-link` — adicionar ao web.php

## Requer migrations/banco

17. Rodar `php artisan migrate` para Hora Extra, Terceirizados, PSS, Acumulação
18. Verificar logs para causa exata dos 500 em Exoneração, Sobreaviso, Plantões Extras
19. Adicionar `require __DIR__ . '/afastamentos_v3.php'` no web.php após criar o arquivo

---

---

# SESSÃO 4 (2026-04-15) — BUG-052 a BUG-061 (Telas 33-35)

---

## [FASE 0] Faltas e Atrasos — GET /abonos-gestao 500 (BUG-052)

**Arquivo:** `routes/web.php:3298`

**Causa:** A rota existe e faz JOIN com `ABONO_FALTA`. A tabela foi criada em `database/migrations/2026_02_23_000001_create_remaining_domain_tables.php` mas pode não ter sido executada no banco atual. Padrão idêntico ao BUG-035 (HORA_EXTRA), BUG-043 (Terceirizados).

**Efeito:** Tela entra em 500 imediato. Todos os dados são mock local.

**Correção:**
```bash
php artisan migrate:status  # verificar se migration foi rodada
php artisan migrate         # rodar se pendente
```

---

## [FASE 1] Faltas e Atrasos — Aprovar/Descontar loading infinito (BUG-053)

**Arquivo:** Vue `FaltasAtrasosView-*.js` + `routes/web.php:3349`

**Causa:** A rota `PUT /abonos-gestao/{id}/status` existe e funciona. O loop de loading é consequência do BUG-052: o GET retorna 500, então o Vue opera com dados mock sem IDs reais do banco. O PUT dispara para IDs inválidos e o estado de loading nunca é resolvido.

**Dependência:** BUG-052. Quando o GET funcionar, o botão deve normalizar.

---

## [FASE 0] Abono de Faltas — GET lê tabela errada (BUG-055)

**Arquivo:** `routes/web.php:3358` (GET) e `routes/web.php:3385` (POST)

**Causa:** Inconsistência crítica entre GET e POST:
```php
// GET (linha 3358) — lê de:
DB::table('JUSTIFICATIVA_PONTO')->where('FUNCIONARIO_ID', ...)

// POST (linha 3385) — insere em:
DB::table('ABONO_FALTA')->insertGetId([...])
```
São tabelas diferentes. O histórico nunca aparece porque o GET jamais lê onde o POST salva.

**Efeito:** Usuário cria abono, sistema confirma, histórico fica vazio para sempre.

**Correção:** Unificar o GET para ler de `ABONO_FALTA` usando os mesmos campos do POST:
```php
// Substituir o GET /abono-faltas por:
Route::get('/abono-faltas', function () {
    $user = Auth::user();
    $funcionario = optional($user)->funcionario
        ?? Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$funcionario) return response()->json([]);

    $abonos = DB::table('ABONO_FALTA')
        ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
        ->orderByDesc('ABONO_FALTA_DATA_INICIO')
        ->get()
        ->map(fn($a) => [
            'id' => $a->ABONO_FALTA_ID,
            'ABONO_FALTA_DATA' => $a->ABONO_FALTA_DATA_INICIO,
            'ABONO_FALTA_JUSTIFICATIVA' => $a->ABONO_FALTA_JUSTIFICATIVA,
            'tipo' => $a->ABONO_FALTA_TIPO,
            'status' => $a->ABONO_FALTA_STATUS ?? 'pendente',
        ]);
    return response()->json($abonos);
});
```

---

## [FASE 1] Abono de Faltas — Bloqueio indevido da data atual (BUG-056)

**Arquivo:** Vue `AbonoDeFaltasView-*.js` — campo de data

**Causa:** O datepicker provavelmente tem `min: amanha` em vez de `min: hoje`. Para abono de falta, o funcionário deve poder justificar o dia atual. Só datas **futuras** devem ser bloqueadas.

**Correção no Vue:**
```js
// ANTES (bloqueando hoje):
:min="new Date(Date.now() + 86400000).toISOString().slice(0,10)"

// DEPOIS (bloqueando só futuro):
:min="undefined"  // sem min, ou:
:max="new Date().toISOString().slice(0,10)"  // bloquear futuro, liberar passado+hoje
```

---

## [FASE 0] Abono de Faltas — Admin retorna 404 Funcionário não encontrado (BUG-057)

**Arquivo:** `routes/web.php:3385` — `POST /abono-faltas`

**Causa:** O código usa dois fallbacks para encontrar o funcionário:
```php
$funcionario = optional($user)->funcionario           // relacao quebrada para admin
    ?? Funcionario::where('FUNCIONARIO_ID', $user->FUNCIONARIO_ID ?? 0)->first(); // FUNCIONARIO_ID=0
if (!$funcionario)
    return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
```
O admin não tem `$user->funcionario` populado e `$user->FUNCIONARIO_ID` é null/0. Mesmo padrão raiz do BUG-030 (Ponto Eletrônico sem PONTO_CONFIG_FUNCIONARIO).

**Correção (seed — Davi):** Garantir que o admin tem `FUNCIONARIO_ID` setado na tabela `USUARIO`:
```sql
UPDATE USUARIO 
SET FUNCIONARIO_ID = (SELECT FUNCIONARIO_ID FROM FUNCIONARIO WHERE USUARIO_ID = 1)
WHERE USUARIO_LOGIN = 'admin';
```
Ou corrigir a relação `funcionario()` no model `Usuario` para fazer lookup por `USUARIO_ID` quando `FUNCIONARIO_ID` for nulo.

---

## [FASE 0] Atestados — Conflito de rotas, atestados.php sobrepõe atestados_v3.php (BUG-058)

**Arquivo:** `routes/web.php:893` e `routes/web.php:901`

**Causa:** O `web.php` inclui dois arquivos com as mesmas rotas `GET /atestados` e `POST /atestados` na mesma ordem:
```php
// Linha 893 — incluso PRIMEIRO (vence o match):
require __DIR__ . '/atestados.php';

// Linha 901 — incluso depois (nunca alcançado pelo Laravel):
require __DIR__ . '/atestados_v3.php';
```
O Laravel usa o **primeiro match**. As rotas do `atestados_v3.php` (corretas, com `Afastamento` model) são completamente ignoradas. As de `atestados.php` (antigas, com lógica diferente) são chamadas e falham com 500.

**Efeito:** GET e POST de atestados sempre retornam 500.

**Correção:** Remover ou comentar o `require atestados.php` da linha 893. O `atestados_v3.php` é o arquivo correto:
```php
// Linha 893 — REMOVER esta linha:
// require __DIR__ . '/atestados.php';

// Linha 901 — manter:
require __DIR__ . '/atestados_v3.php';
```

---

## [FASE 0] Atestados — PUT /aprovar e /rejeitar 404 (BUG-059)

**Arquivo:** ausência em `routes/atestados_v3.php` e `routes/atestados.php`

**Causa:** As rotas `/atestados/{id}/aprovar` e `/atestados/{id}/rejeitar` **não existem** em nenhum arquivo. A única rota de validação existente é `PATCH /atestados/{id}/validar` em `atestados.php`, que usa status genérico. O Vue foi criado chamando endpoints que nunca foram implementados.

**Correção:** Adicionar ao `atestados_v3.php` (após corrigir BUG-058):
```php
Route::put('/atestados/{id}/aprovar', function ($id, Request $request) {
    Afastamento::where('AFASTAMENTO_ID', $id)
        ->update([
            'AFASTAMENTO_STATUS' => 'aprovado',
            'AFASTAMENTO_PARECER' => $request->parecer ?? null,
        ]);
    return response()->json(['message' => 'Atestado aprovado.']);
});

Route::put('/atestados/{id}/rejeitar', function ($id, Request $request) {
    Afastamento::where('AFASTAMENTO_ID', $id)
        ->update([
            'AFASTAMENTO_STATUS' => 'rejeitado',
            'AFASTAMENTO_PARECER' => $request->parecer ?? null,
        ]);
    return response()->json(['message' => 'Atestado rejeitado.']);
});
```

---

## [FASE 2] Atestados — Botão PDF apenas visual (BUG-060)

**Arquivo:** Vue `AtestadosMedicosView-*.js`

**Causa:** Botão de download existe no Vue mas não chama nenhuma rota de geração de PDF. Mesmo padrão do BUG-004 (PDF Holerite) e BUG-011 (Declarações).

**Correção:** Criar rota de PDF usando DomPDF (já instalado no projeto) e apontar o botão:
```php
Route::get('/atestados/{id}/pdf', function ($id) {
    $af = Afastamento::find($id);
    if (!$af) return response()->json(['erro' => 'Não encontrado'], 404);
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.atestado', ['afastamento' => $af]);
    return $pdf->download('atestado-' . $id . '.pdf');
});
```
Depois criar a view `resources/views/pdfs/atestado.blade.php`.

---

---

# SESSÃO 5 (2026-04-15) — BUG-062 a BUG-069 (Telas 36-38)

---

## [FASE 0] Medicina do Trabalho — AGENDAMENTO_EXAME inexistente (BUG-062)

**Arquivo:** `routes/web.php:2884` — `POST /medicina/agendar`

**Causa:** A rota tenta `DB::table('AGENDAMENTO_EXAME')->insertGetId(...)` mas a tabela não existe em **nenhuma migration** (confirmado por grep em `/database/`). Diferente de `ACIDENTE_TRABALHO` que se auto-cria em `seguranca_trabalho.php`, esta tabela foi simplesmente esquecida.

O Vue exibe "✅ Agendamento registrado (modo demo)!" — fallback do frontend que mascara o erro real.

**Correção:** Criar a tabela inline no arquivo de rota (mesmo padrão de `seguranca_trabalho.php`):
```php
if (!Schema::hasTable('AGENDAMENTO_EXAME')) {
    Schema::create('AGENDAMENTO_EXAME', function (Blueprint $table) {
        $table->increments('AGENDAMENTO_ID');
        $table->unsignedInteger('FUNCIONARIO_ID')->index();
        $table->string('AGENDAMENTO_TIPO', 50);
        $table->date('AGENDAMENTO_DATA')->nullable();
        $table->string('AGENDAMENTO_OBS', 300)->nullable();
        $table->string('AGENDAMENTO_STATUS', 20)->default('pendente');
        $table->date('AGENDAMENTO_DT_SOLICITACAO')->nullable();
        $table->timestamps();
    });
}
```
Adicionar ANTES das rotas de medicina no `web.php`.

---

## [FASE 0] Segurança — Modal incidente não fecha + submissão múltipla (BUG-063) — CRÍTICO

**Arquivo:** Vue `SegurancaTrabalhoView-*.js`

**Causa:** O `POST /seguranca/incidentes` retorna 500 em alguns casos. O bug crítico de UX é que o modal de cadastro **nunca fecha** — nem em sucesso nem em erro. O botão não é desabilitado durante a request. Resultado: o usuário clicou 4x e criou 4 incidentes idênticos (“Quase-Acidente / DOR / UTI-ADULTO”).

**Por que é crítico:** Um incidente de trabalho duplicado pode gerar obrigações legais duplicadas (CAT, afastamento, relatórios ao Ministério do Trabalho).

**Correção urgente no Vue:**
```js
// No método de submit do incidente:
async salvarIncidente() {
    this.salvando = true;  // desabilita botão
    try {
        await api.post('/seguranca/incidentes', this.form);
        this.modalAberto = false;  // fechar SEMPRE após sucesso
        this.carregarIncidentes();
    } catch (e) {
        // mostrar erro mas não fechar modal
        this.erro = e.message;
    } finally {
        this.salvando = false;  // reabilitar botão
    }
}
```
O botão deve ter `:disabled="salvando"` para evitar dupla submissão.

---

## [FASE 1] Segurança — Histórico some (overflow CSS) (BUG-065)

**Arquivo:** Vue `SegurancaTrabalhoView-*.js` — lista de incidentes

**Causa:** A lista de incidentes provavelmente tem `max-height` com `overflow: hidden`. Os dados existem no banco, só não são visíveis.

**Correção CSS:**
```css
/* Trocar overflow: hidden por: */
overflow-y: auto;
max-height: 400px;
```

---

## [FASE 0] Folha — POST /calcular 404 para 2026-04 (BUG-066)

**Arquivo:** `routes/folha.php:125`

**Causa:** A rota busca `FOLHA` onde `FOLHA_COMPETENCIA = '202604'` (converte "2026-04" removendo o hífen). Os dados seedados são apenas Jan–Dez 2025. O 404 é intencional pelo código — não existe folha para abril 2026.

**Nota:** O motor de folha (engrename) FUNCIONA para as folhas existentes de 2025 — confirmado pelas imagens enviadas por Davi: 19 servidores, R$24.755,63 proventos, R$22.139,90 líquido.

**Correção (seed — Davi):** Criar registros `FOLHA` para os meses de 2026:
```sql
INSERT INTO FOLHA (FOLHA_COMPETENCIA, FOLHA_DESCRICAO, FOLHA_QTD_SERVIDORES, FOLHA_VALOR_TOTAL)
VALUES
    ('202601', 'Folha Janeiro 2026', 19, 24755.63),
    ('202602', 'Folha Fevereiro 2026', 19, 24755.63),
    ('202603', 'Folha Março 2026', 19, 24755.63),
    ('202604', 'Folha Abril 2026', 19, 24755.63);
```

---

## [FASE 1] Folha — Botão Salvar redireciona para CNAB 240 (BUG-069)

**Arquivo:** Vue `FolhaPagamentoView-*.js`

**Causa:** O botão "Salvar" aponta para a rota/view de Remessa Bancária (`RemessaBancariaController::view`). É um erro de roteamento no componente Vue — o salvar deveria gerar um arquivo ou confirmacão da folha, não abrir a tela de CNAB 240.

> Estes bugs existem no sistema, mas Davi não conseguiu reproduzi-los durante os testes porque dependem de dados que ainda não foram populados no banco. São de responsabilidade de **RR Tecnol ou Nossas sessões** — não de Davi.
> Para testa-los: criar o `MasterBancoHorasSeeder` e os seeds correspondentes, depois retestar.

---

## [SEED PENDENTE] Banco de Horas — Tabela BANCO_HORAS vazia (BUG-009)

**Autor:** Nossas sessões (nenhum seeder cobriu)

**Efeito:** KPIs do Banco de Horas mostram zero. Saldo acumulado sempre é zero.

**O que fazer (seed, não código):**
Criar `MasterBancoHorasSeeder` que popula a tabela `BANCO_HORAS` com créditos e débitos mensais baseados nos registros de ponto existentes de 2025.

---

## [SEED PENDENTE] Banco de Horas — Botão Equipe retorna vazio (BUG-008)

**Autor:** Nossas sessões (lotação do admin pode ter `LOTACAO_DATA_FIM` preenchido) + BANCO_HORAS vazia

**Efeito:** Clicar em "👥 Equipe" mostra "Nenhum membro da equipe encontrado".

**O que fazer (verificar banco + seed):**
```sql
-- Verificar se lotacao do admin tem DATA_FIM nula:
SELECT FUNCIONARIO_ID, LOTACAO_DATA_FIM 
FROM LOTACAO 
WHERE FUNCIONARIO_ID = (SELECT FUNCIONARIO_ID FROM FUNCIONARIO WHERE USUARIO_ID = 1);

-- Se DATA_FIM estiver preenchida, zerar:
UPDATE LOTACAO SET LOTACAO_DATA_FIM = NULL WHERE FUNCIONARIO_ID = X;
```
Após corrigir a lotação e popular BANCO_HORAS, o botão deve funcionar.

---

## [SEED PENDENTE] Banco de Horas — Filtros Extras/Negativos/Faltas vazios (BUG-010)

**Autor:** Nossas sessões — `MasterEscalaSeeder` gerou variação de apenas ±15min, nenhum registro qualifica como extra, negativo ou falta.

**Efeito:** Clicar em "📈 Extras", "📉 Negativos" ou "🚫 Faltas" mostra lista vazia.

**O que fazer (seed):**
O `MasterBancoHorasSeeder` deve incluir registros variados:
- ~10% dos dias: sem entrada (falta total) — `saldoMin = -480`
- ~15% dos dias: saída antes do horário (negativo) — `saldoMin < -15`
- ~15% dos dias: hora extra real — `saldoMin >= 60`

---

## [SEED PENDENTE] Escala de Trabalho — Sem dados após criação da rota (BUG-022)

**Autor:** Nossas sessões

**Efeito:** Mesmo após criar a rota `GET /api/v3/escala-trabalho` (BUG-021), a tela exibiria lista vazia.

**O que fazer (seed):** Verificar se a tabela `ESCALA` cobre Escala de Trabalho além de Escalas Hospitalares. Se sim, o `MasterEscalaSeeder` já populou dados — apenas corrigir a rota resolve. Se são tabelas diferentes, criar seed específico.

---

## [SEED PENDENTE] Substituições de Plantão — Tabela vazia (BUG-033)

**Autor:** Nossas sessões

**Efeito:** Tela de Substituições carrega mas exibe "Nenhuma substituição encontrada". A rota funciona corretamente.

**O que fazer (seed):** Adicionar ao `MasterBancoHorasSeeder` (ou seeder próprio) inserções na tabela de substituições com pelo menos 5-10 registros de exemplo entre funcionários.

---

## [RESOLVIDO AUTOMATICAMENTE] Banco de Horas — Sempre exibe dados fictícios (BUG-007)

**Autor:** RR Tecnol — Sprint 0

**Este bug é consequência direta do BUG-030 (DATE SQL Server).** Quando `CAST(REGISTRO_DATA_HORA AS DATE)` for aplicado em `routes/web.php:~4435`, o GET do ponto passará a retornar dados reais. O `BancoHorasView` usa esse mesmo endpoint — o mock será substituido pelos dados reais automaticamente, sem nenhuma alteração adicional no código.

**Não requer correção separada — corrigir BUG-030 resolve este.**

---

## [FASE 2] Diárias — Total mês não atualiza após prestação de contas (BUG-045)

**Autor:** RR Tecnol — Sprint 0

**Causa:** O valor digitado em prestação de contas sem separador decimal (ex: `3500` em vez de `3500,00`) é provavelmente rejeitado ou interpretado como zero pelo backend. O total do mês não atualiza porque o valor não passou na validação silenciosa.

**Efeito:** Após prestar contas, o card "Total Mês" não reflete o valor prestado.

**Correção — aceitar valor sem separador e normalizar no backend:**
```php
// Na rota de prestação de contas:
$valor = str_replace(',', '.', $request->valor_prestado ?? '0');
$valor = (float) $valor; // converte "3500" ou "3500,00" ou "3500.00"
if ($valor <= 0) {
    return response()->json(['erro' => 'Valor inválido.'], 422);
}
```

**Correção no frontend — aceitar formatos brasileiros no input:**
```html
<input type="text" v-model="valorPrestado" placeholder="3.500,00" />
```

---

# RESUMO FINAL DE COBERTURA

| Categoria | BUGs | Total |
|---|---|---|
| **Corrigir agora (código)** | BUG-002, 004, 014, 015, 018, 021, 023-025, 027, 029-032, 034-044, 052, 055, 057-059, 062, 063, 066, 069-071 | 35 |
| **Corrigir depois (código)** | BUG-001, 003, 005-006, 011-013, 016-017, 019-020, 026, 028, 045-051, 053, 056, 060-061, 064-065, 067-068 | 26 |
| **Requer seed primeiro** | BUG-007*, 008, 009, 010, 022, 033, 054 | 7 |
| **Total** | | **72** |

*BUG-007 resolvido automaticamente ao corrigir BUG-030 (não conta como separado)

---

# SESSÃO 6 (2026-04-15) — BUG-070 a BUG-071 (Telas 39-40)

---

## [FASE 0] Consignação — PATCH /status 500 ao pausar (BUG-070)

**Arquivo:** `routes/consignacao.php:182`

**Causa provável:** A migration `2026_03_11_add_consig_autorizacao_ocorrencia.php` pode não ter sido rodada. O `INSERT INTO CONSIG_OCORRENCIA` dentro do catch silenciado pode não estar sendo silenciado corretamente pelo driver `sqlsrv`.

**Diagnóstico:**
```bash
php artisan migrate:status
# Procurar: 2026_03_11_add_consig_autorizacao_ocorrencia
# Se Pending → rodar: php artisan migrate
```

---

## [FASE 0] Consignatárias — 403 para TODOS os usuários (BUG-071)

**Arquivo:** `routes/consignatarias.php:8`

**Causa confirmada em código:** Mismatch entre parâmetro do middleware e nome de perfil seedado.

```php
// PROBLEMA:
Route::prefix('consignatarias')->middleware(['perfil:ADMIN'])->group(function () {
// CheckPerfil procura PERFIL_NOME = 'ADMIN'
// PerfilSeeder cria 'Administrador' (ID=2) — nunca 'ADMIN'
// Resultado: 100% dos usuários recebem 403
```

**Correção — 1 palavra em consignatarias.php:8:**
```php
// ANTES:
Route::prefix('consignatarias')->middleware(['perfil:ADMIN'])->group(function () {

// DEPOIS:
Route::prefix('consignatarias')->middleware(['perfil:Administrador'])->group(function () {
```

---

# SESSOES 7+8+9 (2026-04-16) — BUG-072 a BUG-102

---

## [FASE 0] Monitor OSS — middleware perfil:ADMIN (BUG-084)

**Arquivo:** `routes/web.php` — grupo que envolve `oss.php`

**Causa:** Mesmo padrao do BUG-071. O grupo usa `perfil:ADMIN` mas o perfil seedado e `Administrador`.

**Correcao:**
```php
// ANTES:
Route::middleware('perfil:ADMIN')->group(function () {
    require __DIR__ . '/oss.php';
});

// DEPOIS:
Route::middleware('perfil:Administrador')->group(function () {
    require __DIR__ . '/oss.php';
});
```

---

## [FASE 0] Padrao recorrente — Vue chama rotas sem prefix api/v3 (BUG-085/086/088/089/090)

**Afeta:** Compras, Almoxarifado, Patrimonio — e possivelmente outros modulos ERP

**Sintoma:** `GET /compras/processos 404`, `GET /almoxarifado/lista 404`, `GET /patrimonio/bens 404`

**Causa:** As views Vue chamam `/compras/processos`, `/almoxarifado/lista`, `/patrimonio/bens` sem o prefix `api/v3`. As rotas estao dentro do grupo `prefix('api/v3')` do `web.php` — as URLs reais sao `/api/v3/compras/...`.

**Correcao — em cada arquivo Vue afetado:**
```js
// ANTES:
const resp = await api.get('/compras/processos')
const resp = await api.get('/almoxarifado/lista')
const resp = await api.get('/patrimonio/bens')

// DEPOIS:
const resp = await api.get('/api/v3/compras/processos')
const resp = await api.get('/api/v3/almoxarifado/lista')
const resp = await api.get('/api/v3/patrimonio/bens')
```

**Diagnostico completo:** Verificar TODOS os `api.get()` e `api.post()` nos arquivos Vue dos modulos administrativos.

---

## [FASE 0] eSocial — tabela ESOCIAL_EVENTO nao migrada (BUG-093/094)

**Arquivo:** `routes/esocial.php` + migration pendente

**Causa:** A rota `GET /esocial/eventos` faz JOIN com `ESOCIAL_EVENTO` que nao foi migrada.

**Correcao:**
```bash
docker compose exec app php artisan migrate:status | grep -i esocial
docker compose exec app php artisan migrate --force
```

---

## [FASE 0] SAGRES — rotas /exportacoes e /depara inexistentes (BUG-095)

**Arquivo:** `routes/sagres.php`

**Correcao — adicionar em `routes/sagres.php`:**
```php
Route::get('/sagres/exportacoes', function () {
    try {
        $hist = DB::table('SAGRES_GERACAO')
            ->orderByDesc('created_at')->limit(50)->get();
        return response()->json(['exportacoes' => $hist]);
    } catch (\Throwable $e) {
        return response()->json(['exportacoes' => []]);
    }
});

Route::get('/sagres/depara', function () {
    try {
        $depara = DB::table('SAGRES_EVENTO_DEPARA')->get();
        return response()->json(['depara' => $depara]);
    } catch (\Throwable $e) {
        return response()->json(['depara' => []]);
    }
});
```

---

## [FASE 0] Avaliacao de Desempenho — formulario sem campo funcionario_id (BUG-098)

**Arquivo:** Vue `AvaliacaoDesempenhoView`

**Causa:** O backend valida `funcionario_id` obrigatorio, mas o formulario Vue nao tem campo de selecao de funcionario.

**Correcao — adicionar autocomplete ao formulario Vue:**
```vue
<v-autocomplete
  v-model="form.funcionario_id"
  :items="servidores"
  item-title="nome"
  item-value="id"
  label="Servidor a avaliar"
  @update:search="buscarServidores"
/>
```
```js
const buscarServidores = async (q) => {
  if (!q) return
  const { data } = await api.get(`/api/v3/servidores/buscar?q=${q}`)
  servidores.value = data.servidores
}
```

---

## [FASE 0] Agenda — rotas POST/GET /agenda inexistentes (BUG-102)

**Arquivo:** `routes/comunicados.php`

**Causa:** A tabela `AGENDA_EVENTO` esta na whitelist mas as rotas nao foram criadas.

**Correcao — adicionar ao final de `routes/comunicados.php`:**
```php
Route::get('/agenda', function () use ($tabelaExiste) {
    if ($tabelaExiste('AGENDA_EVENTO')) {
        $eventos = \Illuminate\Support\Facades\DB::table('AGENDA_EVENTO')
            ->orderBy('EVENTO_DATA_INICIO')->get()
            ->map(fn($e) => [
                'id' => $e->EVENTO_ID,
                'titulo' => $e->EVENTO_TITULO,
                'inicio' => $e->EVENTO_DATA_INICIO,
                'fim' => $e->EVENTO_DATA_FIM,
                'tipo' => $e->EVENTO_TIPO ?? 'reuniao',
                'descricao' => $e->EVENTO_DESCRICAO ?? '',
            ]);
        return response()->json(['eventos' => $eventos]);
    }
    return response()->json(['eventos' => [], 'fallback' => true]);
});

Route::post('/agenda', function (\Illuminate\Http\Request $request) use ($tabelaExiste) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($tabelaExiste('AGENDA_EVENTO')) {
            $id = \Illuminate\Support\Facades\DB::table('AGENDA_EVENTO')->insertGetId([
                'EVENTO_TITULO' => $request->titulo,
                'EVENTO_DATA_INICIO' => $request->inicio,
                'EVENTO_DATA_FIM' => $request->fim ?? $request->inicio,
                'EVENTO_TIPO' => $request->tipo ?? 'reuniao',
                'EVENTO_DESCRICAO' => $request->descricao ?? '',
                'USUARIO_ID' => $user->USUARIO_ID,
                'created_at' => now(),
            ]);
            return response()->json(['ok' => true, 'id' => $id], 201);
        }
        return response()->json(['ok' => true, 'id' => rand(1000,9999), 'fallback' => true], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

**Corrigir Vue AgendaView — mover estado local para APOS confirmacao:**
```js
// ANTES (Vue otimista):
eventos.value.push(novoEvento)
await api.post('/api/v3/agenda', novoEvento)

// DEPOIS:
const resp = await api.post('/api/v3/agenda', novoEvento)
if (resp.data.ok) eventos.value.push({ ...novoEvento, id: resp.data.id })
```

---

## [FASE 0] Pesquisa de Satisfacao — 500 ao responder (BUG-100)

**Arquivo:** `routes/pesquisa.php`

**Causa provavel:** Coluna `json` em SQL Server ou `updated_at` ausente no insert.

**Diagnostico:**
```bash
docker compose exec app tail -50 storage/logs/laravel.log
```

**Correcao mais provavel:**
Se a tabela `PESQUISA_RESPOSTA` foi criada com schema incorreto, dropar e recriar:
```sql
USE gente;
DROP TABLE IF EXISTS PESQUISA_RESPOSTA;
-- Recarregar a rota para auto-migration recriar a tabela
```

---

## [FASE 1] Verbas Indenizatorias — tabelas nao migradas (BUG-072)

```bash
docker compose exec app php artisan migrate:status | grep -i verba
docker compose exec app php artisan migrate --force
```

---

## [FASE 1] Transparencia Publica — 500 ao exportar (BUG-097)

```bash
docker compose exec app tail -30 storage/logs/laravel.log
# Se for permissao de Storage:
docker compose exec app chmod -R 775 storage/app/public
```

---

# RESUMO ATUALIZADO (Sessões 1-9) — BUG-001 a BUG-102 / CORR-001 a CORR-136

| Categoria | Total |
|---|---|
| FASE 0 — corrigir agora (modulo inutilizavel) | ~50 bugs |
| FASE 1 — UX comprometida | ~38 bugs |
| FASE 2 — melhorias | ~14 bugs |
| **Total documentado** | **102 bugs** |

---

# SESSÃO 10 (2026-04-16) — BUG-103 a BUG-121

---

## [FASE 1] Comunicados — CSP bloqueia avatares dicebear (BUG-103)

**Arquivo:** `app/Http/Middleware/SecurityHeaders.php:38`

**Causa:** `img-src 'self' data: blob:` não inclui `https://api.dicebear.com`. Mesma causa do BUG-015 (Portal Gestor). Uma única correção resolve ambos.

**Correção:**
```php
// ANTES:
"img-src 'self' data: blob:; " .

// DEPOIS:
"img-src 'self' data: blob: https://api.dicebear.com; " .
```

**Alternativa recomendada:** Substituir avatares dicebear por iniciais geradas em CSS (sem dependência externa). Remove o risco de CSP permanentemente.

---

## [FASE 1] Comunicados — Marcar como lido não persiste (BUG-104)

**Arquivo:** `routes/comunicados.php`

**Causa:** `GET /comunicados` retorna `'lido' => false` hardcoded. Não existe rota para salvar leitura.

**Correção — adicionar ao final de `comunicados.php`:**
```php
// Registrar leitura de um comunicado
Route::post('/comunicados/{id}/lido', function (int $id) use ($tabelaExiste) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($tabelaExiste('COMUNICADO_LEITURA')) {
            // Upsert: evitar duplicata
            $existe = \Illuminate\Support\Facades\DB::table('COMUNICADO_LEITURA')
                ->where('COMUNICADO_ID', $id)
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->exists();
            if (!$existe) {
                \Illuminate\Support\Facades\DB::table('COMUNICADO_LEITURA')->insert([
                    'COMUNICADO_ID' => $id,
                    'USUARIO_ID'    => $user->USUARIO_ID,
                    'LIDO_EM'       => now(),
                ]);
            }
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Marcar TODOS como lidos
Route::post('/comunicados/lidos', function () use ($tabelaExiste) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($tabelaExiste('COMUNICADO') && $tabelaExiste('COMUNICADO_LEITURA')) {
            $ids = \Illuminate\Support\Facades\DB::table('COMUNICADO')
                ->pluck('COMUNICADO_ID');
            foreach ($ids as $id) {
                $existe = \Illuminate\Support\Facades\DB::table('COMUNICADO_LEITURA')
                    ->where('COMUNICADO_ID', $id)
                    ->where('USUARIO_ID', $user->USUARIO_ID)->exists();
                if (!$existe) {
                    \Illuminate\Support\Facades\DB::table('COMUNICADO_LEITURA')->insert([
                        'COMUNICADO_ID' => $id,
                        'USUARIO_ID'    => $user->USUARIO_ID,
                        'LIDO_EM'       => now(),
                    ]);
                }
            }
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

**Correção no GET — adicionar LEFT JOIN para determinar `lido` por usuário:**
```php
// Dentro do Route::get('/comunicados', ...) substituir o map:
$user = \Illuminate\Support\Facades\Auth::user();
$rows = \Illuminate\Support\Facades\DB::table('COMUNICADO as c')
    ->leftJoin(
        \Illuminate\Support\Facades\DB::raw('(
            SELECT COMUNICADO_ID FROM COMUNICADO_LEITURA
            WHERE USUARIO_ID = ' . (int)$user->USUARIO_ID . '
        ) as lr'),
        'lr.COMUNICADO_ID', '=', 'c.COMUNICADO_ID'
    )
    ->orderByDesc('c.COMUNICADO_DATA')
    ->select('c.*', \Illuminate\Support\Facades\DB::raw('CASE WHEN lr.COMUNICADO_ID IS NOT NULL THEN 1 ELSE 0 END as JA_LIDO'))
    ->get()
    ->map(fn($r) => [
        // ... campos existentes ...
        'lido' => (bool) $r->JA_LIDO,  // <- substituir o false hardcoded
    ]);
```

---

## [FASE 0] Ouvidoria — Manifestações somem após F5 (BUG-105)

**Arquivo:** `routes/ouvidoria.php`

**Causa:** INSERT dentro de `if ($func)` com `catch` silencioso. Se admin não tem `FUNCIONARIO_ID` válido ou tabela `OUVIDORIA` não existe, INSERT falha sem retornar erro.

**Correção:**
```php
// No Route::post('/ouvidoria', ...)
// ANTES (catch silencioso):
if ($func) {
    try {
        \DB::table('OUVIDORIA')->insert([...]);
    } catch (\Throwable $e) {
        // silencioso — BUG
    }
}
return response()->json(['protocolo' => $proto, 'status' => 'recebida'], 201);

// DEPOIS:
if (!$func) {
    return response()->json(['erro' => 'Funcionário não encontrado para este usuário.'], 404);
}

// Verificar se tabela existe
if (!\Illuminate\Support\Facades\Schema::hasTable('OUVIDORIA')) {
    return response()->json(['erro' => 'Módulo Ouvidoria não configurado no banco.'], 500);
}

\Illuminate\Support\Facades\DB::table('OUVIDORIA')->insert([
    'FUNCIONARIO_ID'     => $func->FUNCIONARIO_ID,
    'OUVIDORIA_TIPO'     => $request->tipo,
    'OUVIDORIA_AREA'     => $request->area,
    'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
    'OUVIDORIA_DESC'     => $request->descricao,
    'OUVIDORIA_STATUS'   => 'recebida',
    'OUVIDORIA_PROTOCOLO'=> $proto,
    'OUVIDORIA_DATA'     => date('Y-m-d'),
    'OUVIDORIA_ANONIMO'  => $request->anonimo ? 1 : 0,
]);

return response()->json(['protocolo' => $proto, 'status' => 'recebida'], 201);
// Qualquer excecao agora propagara e retornara 500 real — nao mais 201 falso
```

---

## [FASE 0] Ouvidoria Admin — Tabelas divergentes (BUG-106)

**Arquivo:** `routes/ouvidoria_admin.php`

**Causa:** Admin lê de `OUVIDORIA_MANIFESTACAO`, usuário escreve em `OUVIDORIA`. Dados nunca se cruzam.

**Correção — unificar `ouvidoria_admin.php` para ler de `OUVIDORIA`:**
```php
Route::get('/ouvidoria/admin', function () {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('OUVIDORIA')) {
            return response()->json(['manifestacoes' => [], 'fallback' => true]);
        }
        $manifestacoes = \Illuminate\Support\Facades\DB::table('OUVIDORIA as o')
            ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'o.FUNCIONARIO_ID')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->orderByDesc('o.OUVIDORIA_DATA')
            ->select(
                'o.OUVIDORIA_ID as id',
                'o.OUVIDORIA_PROTOCOLO as protocolo',
                'o.OUVIDORIA_TIPO as tipo',
                'o.OUVIDORIA_AREA as area',
                'o.OUVIDORIA_URGENCIA as urgencia',
                'o.OUVIDORIA_DESC as descricao',
                'o.OUVIDORIA_STATUS as status',
                'o.OUVIDORIA_DATA as data',
                'o.OUVIDORIA_ANONIMO as anonimo',
                'o.OUVIDORIA_RESPOSTA as resposta',
                \Illuminate\Support\Facades\DB::raw(
                    "CASE WHEN o.OUVIDORIA_ANONIMO = 1 THEN 'An\u00f4nimo' ELSE COALESCE(p.PESSOA_NOME, 'Desconhecido') END as solicitante"
                )
            )
            ->get();

        $totais = [
            'total'      => $manifestacoes->count(),
            'pendentes'  => $manifestacoes->whereIn('status', ['recebida', 'em_analise'])->count(),
            'em_analise' => $manifestacoes->where('status', 'em_analise')->count(),
            'respondidas'=> $manifestacoes->where('status', 'respondida')->count(),
        ];

        return response()->json(['manifestacoes' => $manifestacoes, 'totais' => $totais]);
    } catch (\Throwable $e) {
        return response()->json(['manifestacoes' => [], 'erro' => $e->getMessage()], 500);
    }
});

// PATCH para responder
Route::patch('/ouvidoria/{id}/responder', function (\Illuminate\Http\Request $request, int $id) {
    try {
        $request->validate(['resposta' => 'required|string']);
        \Illuminate\Support\Facades\DB::table('OUVIDORIA')
            ->where('OUVIDORIA_ID', $id)
            ->update([
                'OUVIDORIA_RESPOSTA' => $request->resposta,
                'OUVIDORIA_STATUS'   => 'respondida',
            ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

**Vue — recarregar após responder (BUG-108):**
```js
// Apos o PATCH bem-sucedido no OuvidoriaAdminView:
await api.patch(`/api/v3/ouvidoria/${id}/responder`, { resposta: textoResposta.value })
await carregarManifestacoes()  // <- ADICIONAR esta linha
```

---

## [FASE 0] Relatórios — Todas as opções 404 (BUG-109)

**Arquivo:** `routes/relatorios.php` + `RelatoriosView.vue CONFIG_RELATORIOS`

**Causa:** Vue chama `/relatorios/funcionarios`, `/relatorios/admissoes`, `/relatorios/frequencia`, `/relatorios/stats`. Backend tem `/relatorios/quadro-servidores`, `/relatorios/folha/{competencia}` etc. Zero coincidência.

**Correção — adicionar aliases no backend (`routes/relatorios.php`) com os nomes do Vue + rota stats:**
```php
// Aliases para os nomes que o Vue chama (adicionar ao final de relatorios.php)
Route::prefix('relatorios')->group(function () {

    // Stats para o hero do painel
    Route::get('/stats', function () {
        try {
            $ultFolha = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->orderByDesc('FOLHA_COMPETENCIA')->first();
            $qtdAtivos = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->whereNull('FUNCIONARIO_DATA_FIM')->count();
            return response()->json([
                'funcionarios' => $qtdAtivos,
                'competencia'  => $ultFolha?->FOLHA_COMPETENCIA ?? null,
                'fallback'     => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true]);
        }
    });

    // Alias: /funcionarios -> paginado (Vue espera paginacao)
    Route::get('/funcionarios', function (\Illuminate\Http\Request $request) {
        $query = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'f.SETOR_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_NOME as nome',
                'c.CARGO_NOME as cargo',
                's.SETOR_NOME as setor',
                'f.FUNCIONARIO_DATA_INICIO as admissao'
            );
        if ($request->busca) {
            $q = '%' . $request->busca . '%';
            $query->where('p.PESSOA_NOME', 'like', $q);
        }
        $paginator = $query->orderBy('p.PESSOA_NOME')->paginate(20);
        $paginator->getCollection()->transform(fn($r) => (array) $r);
        return response()->json($paginator);
    });

    // Alias: /admissoes -> filtro por periodo
    Route::get('/admissoes', function (\Illuminate\Http\Request $request) {
        $inicio = $request->data_inicio ?? date('Y-01-01');
        $fim    = $request->data_fim    ?? date('Y-m-d');
        $dados = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'f.SETOR_ID')
            ->whereBetween('f.FUNCIONARIO_DATA_INICIO', [$inicio, $fim])
            ->select('f.FUNCIONARIO_MATRICULA as matricula', 'p.PESSOA_NOME as nome',
                     'c.CARGO_NOME as cargo', 's.SETOR_NOME as setor',
                     'f.FUNCIONARIO_DATA_INICIO as admissao')
            ->orderBy('f.FUNCIONARIO_DATA_INICIO', 'desc')->get()
            ->map(fn($r) => (array) $r);
        return response()->json($dados);
    });

    // Alias: /frequencia -> espelho de ponto por periodo
    Route::get('/frequencia', function (\Illuminate\Http\Request $request) {
        $inicio = $request->data_inicio ?? date('Y-m-01');
        $fim    = $request->data_fim    ?? date('Y-m-t');
        try {
            $dados = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO as rp')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rp.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'f.SETOR_ID')
                ->whereBetween(\Illuminate\Support\Facades\DB::raw('CAST(rp.DATA_REGISTRO AS DATE)'), [$inicio, $fim])
                ->select('p.PESSOA_NOME as nome', 's.SETOR_NOME as setor',
                         \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CAST(rp.DATA_REGISTRO AS DATE)) as presencas'))
                ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 's.SETOR_NOME')
                ->orderBy('p.PESSOA_NOME')
                ->get()->map(fn($r) => (array) $r);
            return response()->json($dados);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Alias: /folha -> delegar para o existente
    Route::get('/folha', function (\Illuminate\Http\Request $request) {
        $comp = $request->competencia
            ? str_replace('-', '', $request->competencia)
            : date('Ym');
        return app()->call('\\Illuminate\\Support\\Facades\\Route::dispatch',
            // Redirecionar para rota existente via redirect interno
            [$request->merge(['competencia' => $comp])]
        );
        // Alternativa simples: duplicar a logica de /relatorios/folha/{competencia} aqui
    });
});
```

> **Nota:** Para /folha o mais simples é duplicar a lógica do GET `/relatorios/folha/{competencia}` dentro de `/relatorios/folha` recebendo `?competencia=YYYY-MM` como query param.

---

## [FASE 0] Configurações — GET /ponto/config/funcionarios 403 (BUG-110)

**Causa:** Mesmo padrão BUG-071/084. Middleware `perfil:ADMIN` vs perfil seedado `Administrador`.

**Correção — localizar no `web.php` ou `ponto_eletronico.php` e corrigir:**
```bash
# Encontrar a linha:
grep -rn "perfil:ADMIN" routes/
# Para cada ocorrencia encontrada:
```
```php
// ANTES:
Route::middleware(['perfil:ADMIN'])->group(...);
// DEPOIS:
Route::middleware(['perfil:Administrador'])->group(...);
```
> Buscar por `perfil:ADMIN` em **todo** `routes/` e substituir por `perfil:Administrador` em todos os arquivos. Esta correção resolve BUG-071, BUG-084, BUG-110 e qualquer outro com o mesmo padrão.

---

## [FASE 0] Configurações — POST /perfil/alterar-senha 404 (BUG-111)

**Arquivo:** `routes/meu_perfil.php`

**Correção — adicionar ao final de `meu_perfil.php`:**
```php
Route::post('/perfil/alterar-senha', function (\Illuminate\Http\Request $request) {
    try {
        $request->validate([
            'senha_atual'     => 'required|string',
            'nova_senha'      => 'required|string|min:8',
            'confirmar_senha' => 'required|same:nova_senha',
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        if (!\Illuminate\Support\Facades\Hash::check($request->senha_atual, $user->USUARIO_SENHA)) {
            return response()->json(['erro' => 'Senha atual incorreta.'], 422);
        }

        $user->USUARIO_SENHA = \Illuminate\Support\Facades\Hash::make($request->nova_senha);
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['erro' => $e->errors()], 422);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

---

## [FASE 0] Motor de Folha / Vínculos — PRAGMA SQLite (BUG-113, BUG-116)

**Arquivo:** `routes/motor.php:10` e `:35`

**Causa:** `DB::select('PRAGMA table_info(VINCULO)')` é SQLite-only. SQL Server sempre 500.

**Correção — substituir em todas as ocorrências do arquivo:**
```php
// ANTES (2 ocorrencias em motor.php):
$cols = array_column(DB::select('PRAGMA table_info(VINCULO)'), 'name');
$cols = array_column(DB::select('PRAGMA table_info(ADICIONAL_SERVIDOR)'), 'name');

// DEPOIS:
$cols = \Illuminate\Support\Facades\Schema::getColumnListing('VINCULO');
$cols = \Illuminate\Support\Facades\Schema::getColumnListing('ADICIONAL_SERVIDOR');
```

> `Schema::getColumnListing()` é driver-agnóstico — funciona em SQL Server, MySQL, SQLite e PostgreSQL. Aplicar em **toda** ocorrência de `PRAGMA table_info` no projeto.

---

## [FASE 0] Motor de Folha — PUT /configuracoes/motor.* 403 + dispara N vezes (BUG-114)

**Dois problemas independentes:**

**Problema 1 — 403:** O grupo de rotas `/configuracoes` usa `perfil:ADMIN`. Mesma correção do BUG-110 (trocar `perfil:ADMIN` por `perfil:Administrador`).

**Problema 2 — N requests sem debounce (`ConfiguracaoSistemaView.vue`):**
```js
// ANTES:
const salvarMotorParams = async () => {
  try {
    const pares = Object.entries(motorParams.value)
    for (const [k, v] of pares) {
      await api.put(`/configuracoes/motor.${k}`, { CONFIG_VALOR: String(v) }).catch(() => {})
    }
    okMsg.value = 'Parametros do motor salvos!'
  } catch (e) { erroMsg.value = 'Erro: ' + e.message }
}

// DEPOIS:
const salvandoMotor = ref(false)  // <- adicionar ref

const salvarMotorParams = async () => {
  if (salvandoMotor.value) return  // bloquear duplo clique
  salvandoMotor.value = true
  okMsg.value = ''; erroMsg.value = ''
  try {
    const pares = Object.entries(motorParams.value)
    for (const [k, v] of pares) {
      await api.put(`/configuracoes/motor.${k}`, { CONFIG_VALOR: String(v) }).catch(() => {})
    }
    okMsg.value = 'Parametros do motor salvos!'; setTimeout(() => okMsg.value = '', 3000)
  } catch (e) {
    erroMsg.value = 'Erro: ' + e.message
  } finally {
    salvandoMotor.value = false  // sempre reabilitar
  }
}
```
```html
<!-- No template, adicionar :disabled ao botao: -->
<button class="save-btn" :disabled="salvandoMotor" @click="salvarMotorParams">
  <span v-if="salvandoMotor" class="btn-spin"></span>
  <template v-else>💾 Salvar Parâmetros</template>
</button>
```

---

## [FASE 0] Parâmetros Financeiros — GET+POST 404 (BUG-115)

**Correção — criar `routes/parametros_financeiros_v3.php`:**
```php
<?php
// PARAMETROS FINANCEIROS (INSS, IRRF, FGTS, Salário Mínimo)
// Herda prefix api/v3 + auth do web.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Auto-criar tabela se nao existir
if (!Schema::hasTable('PARAMETRO_FINANCEIRO')) {
    Schema::create('PARAMETRO_FINANCEIRO', function ($t) {
        $t->increments('PARAM_ID');
        $t->string('PARAM_TIPO', 30);           // INSS, IRRF, FGTS, SALARIO_MINIMO, OUTROS
        $t->string('PARAM_DESCRICAO', 200);
        $t->string('PARAM_COMPETENCIA', 6)->nullable();
        $t->decimal('PARAM_VALOR', 12, 4);
        $t->string('PARAM_TIPO_VALOR', 20)->default('ALIQUOTA'); // ALIQUOTA, VALOR, DEDUCAO, FAIXA
        $t->date('PARAM_VIGENCIA_INICIO')->nullable();
        $t->date('PARAM_VIGENCIA_FIM')->nullable();
        $t->timestamps();
    });
}

Route::get('/parametros-financeiros', function () {
    try {
        $rows = DB::table('PARAMETRO_FINANCEIRO')
            ->orderBy('PARAM_TIPO')->orderBy('PARAM_VIGENCIA_INICIO', 'desc')
            ->get()->map(fn($r) => [
                'id'              => $r->PARAM_ID,
                'tipo'            => $r->PARAM_TIPO,
                'descricao'       => $r->PARAM_DESCRICAO,
                'competencia'     => $r->PARAM_COMPETENCIA,
                'valor'           => (float) $r->PARAM_VALOR,
                'tipo_valor'      => $r->PARAM_TIPO_VALOR,
                'vigencia_inicio' => $r->PARAM_VIGENCIA_INICIO,
                'vigencia_fim'    => $r->PARAM_VIGENCIA_FIM,
            ]);
        return response()->json(['parametros' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['parametros' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::post('/parametros-financeiros', function (Request $request) {
    try {
        $id = DB::table('PARAMETRO_FINANCEIRO')->insertGetId([
            'PARAM_TIPO'            => strtoupper($request->tipo),
            'PARAM_DESCRICAO'       => $request->descricao,
            'PARAM_COMPETENCIA'     => $request->competencia ?: null,
            'PARAM_VALOR'           => (float) $request->valor,
            'PARAM_TIPO_VALOR'      => strtoupper($request->tipo_valor ?? 'ALIQUOTA'),
            'PARAM_VIGENCIA_INICIO' => $request->vigencia_inicio ?: null,
            'PARAM_VIGENCIA_FIM'    => $request->vigencia_fim ?: null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::put('/parametros-financeiros/{id}', function (int $id, Request $request) {
    try {
        DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID', $id)->update([
            'PARAM_TIPO'            => strtoupper($request->tipo),
            'PARAM_DESCRICAO'       => $request->descricao,
            'PARAM_COMPETENCIA'     => $request->competencia ?: null,
            'PARAM_VALOR'           => (float) $request->valor,
            'PARAM_TIPO_VALOR'      => strtoupper($request->tipo_valor ?? 'ALIQUOTA'),
            'PARAM_VIGENCIA_INICIO' => $request->vigencia_inicio ?: null,
            'PARAM_VIGENCIA_FIM'    => $request->vigencia_fim ?: null,
            'updated_at'            => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/parametros-financeiros/{id}', function (int $id) {
    try {
        DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID', $id)->delete();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

**Registrar no `web.php` (grupo autenticado):**
```php
require __DIR__ . '/parametros_financeiros_v3.php';
```

---

## [FASE 0] Vínculos — POST /vinculos 405 (BUG-117)

**Arquivo:** `routes/motor.php`

**Correção — adicionar após o GET existente:**
```php
// POST /api/v3/vinculos — Criar novo vinculo
Route::post('/vinculos', function (Request $request) {
    try {
        $request->validate([
            'VINCULO_NOME' => 'required|string|max:200',
        ]);
        $id = DB::table('VINCULO')->insertGetId([
            'VINCULO_NOME'         => $request->VINCULO_NOME,
            'VINCULO_DESCRICAO'    => $request->VINCULO_DESCRICAO ?? null,
            'VINCULO_SIGLA'        => $request->VINCULO_SIGLA ?? null,
            'VINCULO_TIPO_ESOCIAL' => $request->VINCULO_TIPO_ESOCIAL ?? null,
            'VINCULO_FGTS'         => $request->VINCULO_FGTS  ? 1 : 0,
            'VINCULO_INSS'         => $request->VINCULO_INSS  ? 1 : 0,
            'VINCULO_IRRF'         => $request->VINCULO_IRRF  ? 1 : 0,
            'VINCULO_ATIVO'        => 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// DELETE /api/v3/vinculos/{id} — Inativar vinculo
Route::delete('/vinculos/{id}', function (int $id) {
    try {
        DB::table('VINCULO')->where('VINCULO_ID', $id)
            ->update(['VINCULO_ATIVO' => 0, 'updated_at' => now()]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// PUT /api/v3/vinculos/{id} — Edicao completa (VinculosView.vue usa PUT)
Route::put('/vinculos/{id}', function (int $id, Request $request) {
    try {
        $data = ['updated_at' => now()];
        foreach (['VINCULO_NOME','VINCULO_SIGLA','VINCULO_DESCRICAO','VINCULO_TIPO_ESOCIAL'] as $col) {
            if ($request->has($col)) $data[$col] = $request->input($col);
        }
        foreach (['VINCULO_FGTS','VINCULO_INSS','VINCULO_IRRF'] as $flag) {
            if ($request->has($flag)) $data[$flag] = $request->input($flag) ? 1 : 0;
        }
        DB::table('VINCULO')->where('VINCULO_ID', $id)->update($data);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

---

## [FASE 0] Turnos — GET+POST 404 (BUG-118)

**Correção — criar `routes/turnos_v3.php`:**
```php
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/turnos', function () {
    try {
        $rows = DB::table('TURNO')->orderBy('TURNO_NOME')->get()
            ->map(fn($t) => [
                'id'            => $t->TURNO_ID,
                'nome'          => $t->TURNO_NOME,
                'codigo'        => $t->TURNO_SIGLA ?? $t->TURNO_CODIGO ?? null,
                'hora_entrada'  => $t->TURNO_HORA_ENTRADA ?? null,
                'hora_saida'    => $t->TURNO_HORA_SAIDA ?? null,
                'carga_horaria' => $t->TURNO_CARGA_HORARIA ?? null,
                'obs'           => $t->TURNO_OBS ?? null,
                'ativo'         => (bool) ($t->TURNO_ATIVO ?? 1),
            ]);
        return response()->json(['turnos' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['turnos' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::post('/turnos', function (Request $request) {
    try {
        $request->validate(['TURNO_NOME' => 'required', 'TURNO_CODIGO' => 'required']);
        $id = DB::table('TURNO')->insertGetId([
            'TURNO_NOME'          => $request->TURNO_NOME,
            'TURNO_SIGLA'         => $request->TURNO_CODIGO,
            'TURNO_HORA_ENTRADA'  => $request->TURNO_HORA_ENTRADA ?: null,
            'TURNO_HORA_SAIDA'    => $request->TURNO_HORA_SAIDA ?: null,
            'TURNO_CARGA_HORARIA' => $request->TURNO_CARGA_HORARIA ?: null,
            'TURNO_OBS'           => $request->TURNO_OBS ?: null,
            'TURNO_ATIVO'         => 1,
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::put('/turnos/{id}', function (int $id, Request $request) {
    try {
        DB::table('TURNO')->where('TURNO_ID', $id)->update([
            'TURNO_NOME'          => $request->TURNO_NOME,
            'TURNO_SIGLA'         => $request->TURNO_CODIGO,
            'TURNO_HORA_ENTRADA'  => $request->TURNO_HORA_ENTRADA ?: null,
            'TURNO_HORA_SAIDA'    => $request->TURNO_HORA_SAIDA ?: null,
            'TURNO_CARGA_HORARIA' => $request->TURNO_CARGA_HORARIA ?: null,
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/turnos/{id}', function (int $id) {
    try {
        DB::table('TURNO')->where('TURNO_ID', $id)->update(['TURNO_ATIVO' => 0]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```
**Registrar no `web.php`:** `require __DIR__ . '/turnos_v3.php';`

---

## [FASE 0] Feriados — GET+POST 404 (BUG-119)

**Correção — criar `routes/feriados_v3.php`:**
```php
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::get('/feriados', function () {
    try {
        $rows = DB::table('FERIADO')->orderBy('FERIADO_DATA')->get()
            ->map(fn($f) => [
                'id'         => $f->FERIADO_ID,
                'nome'       => $f->FERIADO_NOME ?? $f->FERIADO_DESCRICAO ?? '',
                'data'       => $f->FERIADO_DATA,
                'tipo'       => $f->FERIADO_TIPO ?? 'N',
                'recorrente' => (bool) ($f->FERIADO_RECORRENTE ?? false),
            ]);
        return response()->json(['feriados' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['feriados' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::post('/feriados', function (Request $request) {
    try {
        $request->validate(['FERIADO_NOME' => 'required', 'FERIADO_DATA' => 'required|date']);
        $id = DB::table('FERIADO')->insertGetId([
            'FERIADO_NOME'       => $request->FERIADO_NOME,
            'FERIADO_DATA'       => $request->FERIADO_DATA,
            'FERIADO_TIPO'       => $request->FERIADO_TIPO ?? 'N',
            'FERIADO_RECORRENTE' => $request->FERIADO_RECORRENTE ? 1 : 0,
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::put('/feriados/{id}', function (int $id, Request $request) {
    try {
        DB::table('FERIADO')->where('FERIADO_ID', $id)->update([
            'FERIADO_NOME'       => $request->FERIADO_NOME,
            'FERIADO_DATA'       => $request->FERIADO_DATA,
            'FERIADO_TIPO'       => $request->FERIADO_TIPO ?? 'N',
            'FERIADO_RECORRENTE' => $request->FERIADO_RECORRENTE ? 1 : 0,
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/feriados/{id}', function (int $id) {
    try {
        DB::table('FERIADO')->where('FERIADO_ID', $id)->delete();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```
**Registrar no `web.php`:** `require __DIR__ . '/feriados_v3.php';`

---

## [FASE 0] Tabelas Auxiliares — GET /tabelas/* 404 (BUG-120)

**Correção — criar `routes/tabelas_auxiliares.php`:**
```php
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Helper genérico de CRUD para tabelas de apoio
$crudTabela = function (string $tabela, string $pkCol, array $campos) {
    return [
        'get' => function () use ($tabela, $pkCol, $campos) {
            try {
                $rows = DB::table($tabela)->orderBy($campos[0])->get()
                    ->map(function ($r) use ($pkCol, $campos) {
                        $item = ['id' => $r->$pkCol];
                        foreach ($campos as $c) {
                            $key = strtolower(preg_replace('/^[A-Z]+_/', '', $c)); // ex: BANCO_NOME -> nome
                            $item[$key] = $r->$c ?? null;
                        }
                        return $item;
                    });
                return response()->json(['itens' => $rows]);
            } catch (\Throwable $e) {
                return response()->json(['itens' => [], 'erro' => $e->getMessage()], 500);
            }
        },
    ];
};

// BANCO
Route::get('/tabelas/banco', function () {
    try {
        $rows = DB::table('BANCO')->orderBy('BANCO_NOME')->get()
            ->map(fn($r) => ['id' => $r->BANCO_ID, 'codigo' => $r->BANCO_CODIGO ?? '', 'nome' => $r->BANCO_NOME]);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});
Route::post('/tabelas/banco', function (Request $r) {
    try {
        $id = DB::table('BANCO')->insertGetId(['BANCO_CODIGO' => $r->codigo, 'BANCO_NOME' => $r->nome]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 422); }
});
Route::put('/tabelas/banco/{id}', function (int $id, Request $r) {
    try { DB::table('BANCO')->where('BANCO_ID', $id)->update(['BANCO_CODIGO' => $r->codigo, 'BANCO_NOME' => $r->nome]);
        return response()->json(['ok' => true]); } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 500); }
});
Route::delete('/tabelas/banco/{id}', function (int $id) {
    try { DB::table('BANCO')->where('BANCO_ID', $id)->delete(); return response()->json(['ok' => true]); }
    catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 500); }
});

// UF
Route::get('/tabelas/uf', function () {
    try {
        $rows = DB::table('UF')->orderBy('UF_SIGLA')->get()
            ->map(fn($r) => ['id' => $r->UF_ID, 'sigla' => $r->UF_SIGLA, 'nome' => $r->UF_NOME, 'regiao' => $r->UF_REGIAO ?? null]);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});

// CIDADE
Route::get('/tabelas/cidade', function () {
    try {
        $rows = DB::table('CIDADE as c')->leftJoin('UF as u', 'u.UF_ID', '=', 'c.UF_ID')
            ->orderBy('c.CIDADE_NOME')
            ->select('c.CIDADE_ID as id', 'c.CIDADE_NOME as nome', 'u.UF_SIGLA as uf_sigla', 'c.CIDADE_IBGE as ibge')
            ->get()->map(fn($r) => (array) $r);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});
Route::post('/tabelas/cidade', function (Request $r) {
    try {
        $uf = DB::table('UF')->where('UF_SIGLA', $r->uf_sigla)->first();
        $id = DB::table('CIDADE')->insertGetId(['CIDADE_NOME' => $r->nome, 'UF_ID' => $uf?->UF_ID, 'CIDADE_IBGE' => $r->ibge]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 422); }
});

// BAIRRO, CARTORIO, CONSELHO, TIPO-DOCUMENTO
// Padrão identico: GET lista, POST cria, PUT atualiza, DELETE remove
// Substituir nome da tabela e colunas conforme schema real do banco
Route::get('/tabelas/bairro', function () {
    try {
        $rows = DB::table('BAIRRO as b')->leftJoin('CIDADE as c', 'c.CIDADE_ID', '=', 'b.CIDADE_ID')
            ->orderBy('b.BAIRRO_NOME')
            ->select('b.BAIRRO_ID as id', 'b.BAIRRO_NOME as nome', 'c.CIDADE_NOME as cidade_nome')
            ->get()->map(fn($r) => (array) $r);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});

Route::get('/tabelas/conselho', function () {
    try {
        $rows = DB::table('CONSELHO')->orderBy('CONSELHO_NOME')
            ->get()->map(fn($r) => ['id' => $r->CONSELHO_ID, 'sigla' => $r->CONSELHO_SIGLA, 'nome' => $r->CONSELHO_NOME]);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});

Route::get('/tabelas/tipo-documento', function () {
    try {
        $rows = DB::table('TIPO_DOCUMENTO')->orderBy('TIPO_DOCUMENTO_NOME')
            ->get()->map(fn($r) => ['id' => $r->TIPO_DOCUMENTO_ID, 'codigo' => $r->TIPO_DOCUMENTO_CODIGO ?? '', 'nome' => $r->TIPO_DOCUMENTO_NOME]);
        return response()->json(['itens' => $rows]);
    } catch (\Throwable $e) { return response()->json(['itens' => []]); }
});

// POST/PUT/DELETE genericos para as demais tabelas seguem o mesmo padrao
// Verificar nomes exatos das colunas no banco antes de implementar
```
**Registrar no `web.php`:** `require __DIR__ . '/tabelas_auxiliares.php';`

---

## [FASE 0] Eventos de Folha — GET+POST 404 (BUG-121)

**Correção — criar `routes/eventos_folha_v3.php`:**
```php
<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::get('/eventos', function () {
    try {
        $rows = DB::table('EVENTO')->where('EVENTO_ATIVO', 1)->orderBy('EVENTO_CODIGO')->get()
            ->map(fn($e) => [
                'id'     => $e->EVENTO_ID,
                'codigo' => $e->EVENTO_CODIGO,
                'nome'   => $e->EVENTO_DESCRICAO ?? $e->EVENTO_NOME ?? '',
                'tipo'   => $e->EVENTO_TIPO ?? 'P',  // P=Provento, D=Desconto
                'inss'   => (bool) ($e->EVENTO_INCIDE_INSS ?? false),
                'irrf'   => (bool) ($e->EVENTO_INCIDE_IRRF ?? false),
                'fgts'   => (bool) ($e->EVENTO_INCIDE_FGTS ?? false),
                'ativo'  => (bool) ($e->EVENTO_ATIVO ?? true),
            ]);
        return response()->json(['eventos' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['eventos' => [], 'erro' => $e->getMessage()], 500);
    }
});

Route::post('/eventos', function (Request $request) {
    try {
        $request->validate(['EVENTO_CODIGO' => 'required', 'EVENTO_NOME' => 'required']);
        $id = DB::table('EVENTO')->insertGetId([
            'EVENTO_CODIGO'        => strtoupper($request->EVENTO_CODIGO),
            'EVENTO_DESCRICAO'     => $request->EVENTO_NOME,
            'EVENTO_TIPO'          => strtoupper($request->EVENTO_TIPO ?? 'P'),
            'EVENTO_INCIDE_INSS'   => $request->EVENTO_INSS  ? 1 : 0,
            'EVENTO_INCIDE_IRRF'   => $request->EVENTO_IRRF  ? 1 : 0,
            'EVENTO_INCIDE_FGTS'   => $request->EVENTO_FGTS  ? 1 : 0,
            'EVENTO_ATIVO'         => 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::put('/eventos/{id}', function (int $id, Request $request) {
    try {
        DB::table('EVENTO')->where('EVENTO_ID', $id)->update([
            'EVENTO_CODIGO'    => strtoupper($request->EVENTO_CODIGO),
            'EVENTO_DESCRICAO' => $request->EVENTO_NOME,
            'EVENTO_TIPO'      => strtoupper($request->EVENTO_TIPO ?? 'P'),
            'EVENTO_INCIDE_INSS' => $request->EVENTO_INSS ? 1 : 0,
            'EVENTO_INCIDE_IRRF' => $request->EVENTO_IRRF ? 1 : 0,
            'EVENTO_INCIDE_FGTS' => $request->EVENTO_FGTS ? 1 : 0,
            'updated_at'         => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/eventos/{id}', function (int $id) {
    try {
        DB::table('EVENTO')->where('EVENTO_ID', $id)->update(['EVENTO_ATIVO' => 0, 'updated_at' => now()]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```
**Registrar no `web.php`:** `require __DIR__ . '/eventos_folha_v3.php';`

---

# SESSÃO 11 (2026-04-16) — BUG-122 a BUG-130

---

## [FASE 1] Orçamento Público — Sem dados (BUG-122) + Execução de Despesa (BUG-124)

**Causa:** Tabelas ERP sem seed. Não são auto-geradas pelo RH — precisam de carga inicial.

**Correção — seed demonstrativo (executar 1 vez):**
```sql
-- 1. PPA 2024-2027
INSERT INTO ORCAMENTO_PPA (PPA_DESCRICAO, PPA_ANO_INICIO, PPA_ANO_FIM)
VALUES ('PPA 2024-2027 - Prefeitura Municipal', 2024, 2027);

-- 2. Programas (use o ID do PPA inserido acima, ex: 1)
INSERT INTO ORCAMENTO_PROGRAMA (PPA_ID, PROGRAMA_CODIGO, PROGRAMA_NOME)
VALUES
    (1, '0001', 'Gestão Administrativa'),
    (1, '0010', 'Saúde Preventiva'),
    (1, '0020', 'Educação Fundamental');

-- 3. Ações
INSERT INTO ORCAMENTO_ACAO (PROGRAMA_ID, ACAO_CODIGO, ACAO_NOME, ACAO_TIPO, ACAO_VALOR_PREVISTO)
VALUES
    (1, '2001', 'Manutenção da Secretaria Municipal', 'ATIVIDADE', 500000.00),
    (1, '2002', 'Pagamento de Pessoal - Adm', 'ATIVIDADE', 3500000.00),
    (2, '2010', 'UBS - Atendimento Básico', 'ATIVIDADE', 1200000.00);

-- 4. LOA 2025
INSERT INTO ORCAMENTO_LOA (ACAO_ID, LOA_ANO, LOA_NATUREZA_DESPESA, LOA_VALOR_APROVADO, LOA_VALOR_ADICIONADO, LOA_VALOR_REDUZIDO)
VALUES
    (1, 2025, '3.1.90.11.00', 500000.00,  0.00, 0.00),
    (2, 2025, '3.1.90.11.00', 3500000.00, 0.00, 0.00),
    (3, 2025, '3.1.90.11.00', 1200000.00, 0.00, 0.00);
```

---

## [FASE 0] Execução de Despesa — POST /empenho 422 (BUG-123)

**Causa:** Vue envia `loa_id: null` pois `ORCAMENTO_LOA` está vazia. Validação falha.

**Correção 1 (seed):** Executar o seed do BUG-122 acima primeiro.

**Correção 2 (validação no frontend — `ExecucaoDespesaView.vue`):**
```js
// Antes de submeter o formulario:
const emitirEmpenho = async () => {
  if (!form.value.loa_id) {
    erro.value = 'Selecione uma ação orçamentária (LOA) antes de emitir.'
    return
  }
  // ... submit
}
```

---

## [FASE 1] Contabilidade — PCASP_CONTA vazia (BUG-126) + POST 422 (BUG-125)

**Causa:** `PCASP_CONTA` nunca populada. `POST /lancamentos` falha pois seletores ficam vazios.

**Correção — seed mínimo do PCASP (contas de pessoal):**
```sql
-- Estrutura mínima para lançamentos de folha de pessoal
INSERT INTO PCASP_CONTA (CONTA_CODIGO, CONTA_NOME, CONTA_NATUREZA, CONTA_GRUPO, CONTA_ATIVA)
VALUES
    -- ATIVO
    ('1.1.1.1.1.00.00', 'Caixa e Equivalentes de Caixa', 'DEVEDORA',  'ATIVO',    1),
    ('1.2.3.1.1.00.00', 'Créditos a Receber',            'DEVEDORA',  'ATIVO',    1),
    -- PASSIVO
    ('2.1.3.1.1.00.00', 'Obrigações Trabalhistas',        'CREDORA',   'PASSIVO',  1),
    ('2.1.3.2.1.00.00', 'INSS a Recolher',                'CREDORA',   'PASSIVO',  1),
    ('2.1.3.2.2.00.00', 'IRRF a Recolher',                'CREDORA',   'PASSIVO',  1),
    -- DESPESA
    ('3.1.9.0.1.1.00',  'Vencimentos e Vantagens',        'DEVEDORA',  'DESPESA',  1),
    ('3.1.9.0.1.3.00',  'Obrigações Patronais',           'DEVEDORA',  'DESPESA',  1),
    -- VARIACAO PATRIMONIAL
    ('6.2.1.1.1.00.00', 'VPD - Pessoal e Encargos',       'DEVEDORA',  'VPD',      1);
```

> **Nota:** O PCASP completo está disponível em CSV no portal STN (Secretaria do Tesouro Nacional). Recomendável importar o arquivo oficial para ter o plano completo.

**Correção 2 (validação no frontend — `ContabilidadeView.vue`):**
```js
// Antes de submeter:
if (!form.value.conta_debito_id || !form.value.conta_credito_id) {
  erro.value = 'Selecione as contas de débito e crédito.'
  return
}
```

---

## [FASE 0] Tesouraria — GET /tesouraria/contas 404 (BUG-127)

**Arquivo:** `routes/tesouraria.php`

**Causa:** Vue chama `/tesouraria/contas`, `/tesouraria/fluxo-caixa`, `/tesouraria/movimentacoes`. Backend tem `/contas-bancarias`, `/fluxo-caixa`, `/conciliar` sem prefix.

**Correção A (adicionar prefix no backend — 1 linha):**
```php
// No web.php, ao incluir tesouraria.php, envolver com prefix:
Route::prefix('tesouraria')->group(function () {
    // RENOMEAR as rotas dentro de tesouraria.php:
    // /contas-bancarias -> /contas
    // /fluxo-caixa     -> /fluxo-caixa  (ja bate)
    // /conciliar       -> /movimentacoes ou /conciliar
    require __DIR__ . '/tesouraria.php';
});
```

**Correção B (mais simples — adicionar aliases em tesouraria.php):**
```php
// Adicionar ao final de routes/tesouraria.php:
Route::get('/tesouraria/contas',          function () use ($app) { return $app->call('/* logica do contas-bancarias */'); });
// Ou simplesmente adicionar os aliases diretos:
Route::get('/tesouraria/contas', /* ... mesma funcao de /contas-bancarias ... */);
Route::get('/tesouraria/fluxo-caixa', /* ... mesma funcao de /fluxo-caixa ... */);
Route::get('/tesouraria/movimentacoes', /* ... listar MOVIMENTACAO_BANCARIA ... */);
```

> Recomendado: usar correção A (mais limpa). Verificar no `TesourariaView.vue` todos os endpoints chamados e alinhar com o backend.

**Seed básico (BUG-128):**
```sql
INSERT INTO CONTA_BANCARIA (CONTA_DESCRICAO, BANCO_ID, CONTA_AGENCIA, CONTA_NUMERO, CONTA_SALDO_INICIAL)
VALUES
    ('Conta Corrente Principal', 1, '0001', '12345-6', 0.00),
    ('Conta Folha de Pagamento', 1, '0001', '12345-7', 0.00),
    ('Conta de Investimentos',   1, '0001', '12345-8', 0.00);
```

---

## [FASE 1] Receita Municipal — Sem dados iniciais (BUG-129)

**Causa:** `RECEITA_LANCAMENTO` sem seed. `POST /receita` já funciona (confirmado por Davi).

**Seed demonstrativo:**
```sql
INSERT INTO RECEITA_LANCAMENTO
    (RECEITA_DATA, RECEITA_ANO, RECEITA_MES, RECEITA_CODIGO_NATUREZA, RECEITA_DESCRICAO,
     RECEITA_TIPO, RECEITA_VALOR_PREVISTO, RECEITA_VALOR_ARRECADADO)
VALUES
    ('2025-01-10', 2025, 1,  '1.1.1.0.0', 'IPTU - Imposto Predial',              'TRIBUTARIA',              240000.00, 198000.00),
    ('2025-01-15', 2025, 1,  '1.2.0.0.0', 'ISS - Serviços',                      'TRIBUTARIA',               80000.00,  72000.00),
    ('2025-01-20', 2025, 1,  '6.1.1.0.0', 'FPM - Fundo Particip. Municípios',    'TRANSFERENCIAS_CORRENTES', 850000.00, 850000.00),
    ('2025-02-10', 2025, 2,  '1.1.1.0.0', 'IPTU - Imposto Predial',              'TRIBUTARIA',              240000.00, 210000.00),
    ('2025-02-20', 2025, 2,  '6.1.1.0.0', 'FPM - Fundo Particip. Municípios',    'TRANSFERENCIAS_CORRENTES', 850000.00, 862000.00);
-- Repetir para meses seguintes conforme necessidade
```

---

## [FASE 1] Controle Externo — Gerar XML SAGRES sem download (BUG-130)

**Arquivo:** `routes/controle_externo.php:42`

**Causa:** `POST /sagres/gerar` só insere em `SICONFI_ENVIO` e retorna JSON. Não gera arquivo físico nem stream.

**Correção — implementar geração real do XML:**
```php
Route::post('/sagres/gerar', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $ano = (int) $request->ano ?? (int) date('Y');
        $mes = (int) $request->mes ?? (int) date('n');
        $nomeArquivo = "SAGRES_{$ano}_{$mes}_folha.xml";

        // 1. Buscar dados da folha com mapeamento SAGRES
        $depara = \Illuminate\Support\Facades\DB::table('SAGRES_EVENTO_DEPARA')
            ->get()->keyBy('EVENTO_ID');

        $folha = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f',       'f.FOLHA_ID',       '=', 'df.FOLHA_ID')
            ->join('FUNCIONARIO as fn','fn.FUNCIONARIO_ID','=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p',      'p.PESSOA_ID',      '=', 'fn.PESSOA_ID')
            ->where(\Illuminate\Support\Facades\DB::raw('YEAR(f.FOLHA_COMPETENCIA)'), $ano)
            ->where(\Illuminate\Support\Facades\DB::raw('MONTH(f.FOLHA_COMPETENCIA)'), $mes)
            ->select('df.*', 'p.PESSOA_NOME as nome', 'p.PESSOA_CPF_NUMERO as cpf', 'fn.FUNCIONARIO_MATRICULA as matricula')
            ->get();

        // 2. Montar XML no formato SAGRES/TCE-MA
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SAGRES_FOLHA/>');
        $xml->addAttribute('ano', $ano);
        $xml->addAttribute('mes', $mes);
        $xml->addAttribute('geradoEm', now()->toIso8601String());

        foreach ($folha as $linha) {
            $serv = $xml->addChild('SERVIDOR');
            $serv->addChild('MATRICULA', $linha->matricula);
            $serv->addChild('NOME',      $linha->nome);
            $serv->addChild('CPF',       $linha->cpf);
            $serv->addChild('PROVENTOS', number_format($linha->DETALHE_FOLHA_PROVENTOS ?? 0, 2, '.', ''));
            $serv->addChild('DESCONTOS', number_format($linha->DETALHE_FOLHA_DESCONTOS ?? 0, 2, '.', ''));
            $serv->addChild('LIQUIDO',   number_format($linha->DETALHE_FOLHA_LIQUIDO   ?? 0, 2, '.', ''));
            // Codigo SAGRES do evento
            $sagresCode = $depara[$linha->EVENTO_ID ?? 0]?->SAGRES_CODIGO ?? 'NAO_MAPEADO';
            $serv->addChild('SAGRES_COD', $sagresCode);
        }

        $xmlContent = $xml->asXML();

        // 3. Registrar no historico
        \Illuminate\Support\Facades\DB::table('SICONFI_ENVIO')->insertGetId([
            'ENVIO_TIPO'         => 'SAGRES',
            'ENVIO_ANO'          => $ano,
            'ENVIO_MES'          => $mes,
            'ENVIO_STATUS'       => 'GERADO',
            'ENVIO_ARQUIVO'      => $nomeArquivo,
            'USUARIO_ID'         => $user?->USUARIO_ID,
            'ENVIO_DT_GERACAO'   => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // 4. Retornar como stream de download
        return response($xmlContent, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$nomeArquivo\"",
            'Content-Length'      => strlen($xmlContent),
        ]);

    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
```

**Vue — tratar o download do stream (`ControleExternoView.vue`):**
```js
// Substituir o api.post simples por download via blob:
const gerarXML = async () => {
  try {
    gerandoXML.value = true
    const resp = await api.post('/api/v3/sagres/gerar',
      { ano: anoSelecionado.value, mes: mesSelecionado.value },
      { responseType: 'blob' }  // <- importante para receber o arquivo
    )
    // Criar link de download automatico
    const url = window.URL.createObjectURL(new Blob([resp.data], { type: 'application/xml' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `SAGRES_${anoSelecionado.value}_${mesSelecionado.value}_folha.xml`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    // Recarregar historico apos geracao
    await carregarHistorico()
  } catch (e) {
    erro.value = 'Erro ao gerar XML: ' + e.message
  } finally {
    gerandoXML.value = false
  }
}
```

---

# RESUMO FINAL — BUG-001 a BUG-130 / CORR-001 a CORR-164

| Categoria | Total |
|---|---|
| FASE 0 — bloqueadores (corrigir primeiro) | ~72 |
| FASE 1 — UX comprometida | ~53 |
| FASE 2 — melhorias | ~25 |
| FASE 3/4 — hardening | ~14 |
| **Total CORRs** | **164** |

## Arquivos novos a criar (`require` no `web.php`)

| Arquivo | Resolve |
|---|---|
| `routes/afastamentos_v3.php` | BUG-002 |
| `routes/parametros_financeiros_v3.php` | BUG-115 |
| `routes/turnos_v3.php` | BUG-118 |
| `routes/feriados_v3.php` | BUG-119 |
| `routes/tabelas_auxiliares.php` | BUG-120 |
| `routes/eventos_folha_v3.php` | BUG-121 |

## `require` a adicionar no `web.php` (grupo autenticado)

```php
// Adicionar junto dos outros require existentes (~linha 879):
require __DIR__ . '/progressao_funcional.php';  // BUG-039
require __DIR__ . '/afastamentos_v3.php';       // BUG-002
require __DIR__ . '/parametros_financeiros_v3.php'; // BUG-115
require __DIR__ . '/turnos_v3.php';             // BUG-118
require __DIR__ . '/feriados_v3.php';           // BUG-119
require __DIR__ . '/tabelas_auxiliares.php';    // BUG-120
require __DIR__ . '/eventos_folha_v3.php';      // BUG-121
```

## Correções globais de 1 linha (máximo impacto)

```bash
# Substituir TODOS os perfil:ADMIN por perfil:Administrador em routes/
grep -rn "perfil:ADMIN" routes/  # encontrar
# Corrigir cada arquivo encontrado

# Substituir PRAGMA table_info por Schema::getColumnListing
grep -rn "PRAGMA table_info" routes/ app/  # encontrar
# Corrigir cada arquivo encontrado
```

---

# FASE DE SEGURANÇA — Correções das Vulnerabilidades (VULN + PENTEST)

> Referência diagnóstica completa: [[_Global/PROJETOS/RRTECNOL/GENTE/vulnerabilidades-gente]]
> Cenários de ataque executivos: [[_Global/PROJETOS/RRTECNOL/GENTE/catalogo-bugs-formal]]

⚠️ **Estas correções são pré-requisito para qualquer deploy em produção.** Aplique na ordem abaixo — as primeiras bloqueiam os vetores de ataque mais fáceis (dificuldade ⭐).

---

## BLOCO 1 — Correções imediatas (executar antes de qualquer outra coisa)

### VULN-003 — Remover `dd()` ativo em produção
**Arquivo:** `app/Http/Middleware/ChecarAcessoUsuarioUnidade.php:33`
```php
// REMOVER esta linha:
dd($usuario);
// Não substituir por nada — apenas deletar a linha
```

---

### PENTEST-21 — Gerar APP_KEY real
**A chave mestra está vazia no .env — qualquer sessão pode ser forjada sem brute force.**
```bash
# Na raiz do projeto:
php artisan key:generate
# Verificar que .env agora contém APP_KEY=base64:... (64 chars)
```

---

### PENTEST-34 — Remover log de debug que grava CPF/e-mail de todos os usuários
**Arquivo:** `app/Http/Controllers/SpaAuthController.php` — método `me()`
```php
// REMOVER esta linha:
Log::info('VUE3_ME_DEBUG', $payload);
// Não substituir por nada
```

---

### PENTEST-24 — Remover senha `SISGEP123` hardcoded do código
**Arquivo:** `app/Http/Middleware/AlterarSenha.php`
```php
// ANTES (inseguro — senha em texto puro no código):
if (Auth::user()->USUARIO_SENHA == md5('SISGEP123')) {
    return redirect('/alterar-senha');
}

// DEPOIS:
if (Auth::user()->USUARIO_ALTERAR_SENHA == 1) {
    return redirect('/alterar-senha');
}
```

---

### VULN-002 — Remover `$isAdmin = true` no catch do holerite
**Arquivo:** `routes/web.php` — rota do holerite PDF (buscar por `$isAdmin`)
```php
// ANTES (eleva privilégios em caso de erro de banco):
try {
    $permissoes = DB::table('...')->...
} catch (\Exception $e) {
    $isAdmin = true; // <- REMOVER ESTA LINHA
}

// DEPOIS:
try {
    $permissoes = DB::table('...')->...
} catch (\Exception $e) {
    return response()->json(['error' => 'Erro ao verificar permissões'], 500);
}
```

---

### VULN-004 — Remover credenciais do histórico Git
**`.env.docker` com `sa/Gente@2024` foi commitado no commit `eb6ba26`.**
```bash
# 1. Instalar git-filter-repo (se não tiver):
pip install git-filter-repo

# 2. Remover o arquivo do histórico completo:
git filter-repo --path .env.docker --invert-paths

# 3. Remover também scripts-debug/
git filter-repo --path scripts-debug/ --invert-paths

# 4. Force push (avisar toda a equipe antes — eles precisam re-clonar):
git push origin --force --all

# 5. ROTACIONAR IMEDIATAMENTE as credenciais do banco (ver DECISÃO-001 e 002):
# - Trocar senha do usuário 'sa'
# - Criar usuário 'gente_app' com permissões mínimas (SELECT/INSERT/UPDATE nas tabelas necessárias)
# - Atualizar .env de produção com as novas credenciais
```

---

## BLOCO 2 — Correções de alta prioridade

### PENTEST-01 — Remover perfil admin concedido pelo nome de login
**Arquivo:** `app/Http/Controllers/SpaAuthController.php` — método `login()` ou `me()`
```php
// LOCALIZAR e REMOVER este bloco:
if (strtolower($user->USUARIO_LOGIN) === 'admin') {
    $perfilNome = 'admin';
}
// O perfil deve vir EXCLUSIVAMENTE da tabela USUARIO_PERFIL no banco
```

---

### PENTEST-02 — Remover campos sensíveis do `$fillable` do model Usuario
**Arquivo:** `app/Models/Usuario.php`
```php
// ANTES:
protected $fillable = [
    'USUARIO_LOGIN',
    'USUARIO_SENHA',      // <- REMOVER
    'USUARIO_ATIVO',      // <- REMOVER
    'USUARIO_ALTERAR_SENHA', // <- REMOVER
    ...
];

// DEPOIS — esses campos só devem ser alterados por métodos específicos:
protected $fillable = [
    'USUARIO_LOGIN',
    'USUARIO_NOME',
    // demais campos não-sensíveis
];

// Criar métodos dedicados para operações sensíveis:
public function setPassword(string $plain): void {
    $this->USUARIO_SENHA = Hash::make($plain);
    $this->save();
}

public function ativar(): void {
    $this->USUARIO_ATIVO = 1;
    $this->save();
}
```

---

### PENTEST-03 — Remover CPF/RG/PIS do `$fillable` do model Pessoa
**Arquivo:** `app/Models/Pessoa.php`
```php
// REMOVER do $fillable:
// 'PESSOA_CPF_NUMERO'
// 'PESSOA_RG_NUMERO'
// 'PESSOA_PIS_PASEP'
// 'PESSOA_DATA_NASCIMENTO'

// Esses campos só devem ser alterados via rota exclusiva com permissão de RH admin
```

---

### PENTEST-22 — Corrigir TrustProxies (rate limit bypass)
**Arquivo:** `app/Http/Middleware/TrustProxies.php`
```php
// ANTES (aceita qualquer IP como proxy):
protected $proxies = null;

// DEPOIS (definir IPs reais do load balancer/proxy reverso):
// Se usar Nginx local:
protected $proxies = '127.0.0.1';
// Se usar balanceador externo (AWS, Cloudflare etc), colocar o IP real:
// protected $proxies = ['203.0.113.1'];

// TAMBÉM corrigir os headers confiáveis:
protected $headers =
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO;
```

---

### PENTEST-25 — Corrigir IDOR em PATCH /declaracoes/{id}
**Arquivo:** `routes/declaracoes.php` — rota `PATCH /declaracoes/{id}`
```php
// ANTES (sem verificar dono):
DB::table('DECLARACAO')
    ->where('DECLARACAO_ID', $id)
    ->update(['DECLARACAO_STATUS' => $request->status]);

// DEPOIS:
$declaracao = DB::table('DECLARACAO')
    ->where('DECLARACAO_ID', $id)
    ->first();

if (!$declaracao) {
    return response()->json(['error' => 'Não encontrada'], 404);
}

$user = auth()->user();
$funcId = optional($user->funcionario)->FUNCIONARIO_ID;

// Apenas o próprio dono pode alterar, a menos que seja admin
if ($declaracao->FUNCIONARIO_ID !== $funcId && !$user->isAdmin()) {
    return response()->json(['error' => 'Sem permissão'], 403);
}

DB::table('DECLARACAO')
    ->where('DECLARACAO_ID', $id)
    ->update(['DECLARACAO_STATUS' => $request->status]);
```

---

### PENTEST-26 — Corrigir SQL Injection via VALOR_PARCELA na folha
**Arquivo:** `routes/folha.php` — seção de cálculo com consignações
```php
// ANTES (interpolação direta — SQL injection):
'DETALHE_FOLHA_DESCONTOS' =>
    DB::raw("COALESCE(DETALHE_FOLHA_DESCONTOS,0) + {$p->VALOR_PARCELA}"),

// DEPOIS (usar binding paramétrico):
// Opção A — validar e forçar tipo numérico:
$valor = (float) $p->VALOR_PARCELA;
if ($valor <= 0 || $valor > 99999.99) {
    throw new \Exception("VALOR_PARCELA inválido: {$p->CONSIG_ID}");
}
'DETALHE_FOLHA_DESCONTOS' =>
    DB::raw("COALESCE(DETALHE_FOLHA_DESCONTOS,0) + " . number_format($valor, 2, '.', '')),

// Opção B (mais segura) — usar UPDATE com binding:
DB::statement(
    'UPDATE DETALHE_FOLHA SET DETALHE_FOLHA_DESCONTOS = COALESCE(DETALHE_FOLHA_DESCONTOS,0) + ? WHERE DETALHE_ID = ?',
    [$valor, $detalhe->DETALHE_ID]
);
```

---

### PENTEST-27 — Whitelist de valores em $acao no portal gestor
**Arquivo:** `routes/gestor.php` — rota `POST /gestor/aprovar`
```php
// ANTES (qualquer valor aceito):
$acao = $request->acao;
$tabela = $request->ref_tabela;

// DEPOIS:
$acoesPermitidas = ['aprovado', 'reprovado', 'pendente'];
if (!in_array($request->acao, $acoesPermitidas, true)) {
    return response()->json(['error' => 'Ação inválida'], 422);
}

$tabelasPermitidas = ['FERIAS_PERIODO', 'PLANTAO_EXTRA', 'SOBREAVISO_ACIONAMENTO'];
if (!in_array($request->ref_tabela, $tabelasPermitidas, true)) {
    return response()->json(['error' => 'Tabela inválida'], 422);
}

$acao = $request->acao;
$tabela = $request->ref_tabela;
```

---

## BLOCO 3 — Correções de configuração (.env e middleware)

### PENTEST-14 + PENTEST-33 — Desativar debug e logs verbosos
**Arquivo:** `.env` (produção)
```bash
# Alterar:
APP_DEBUG=false        # era true
LOG_LEVEL=error        # era debug
APP_ENV=production     # confirmar
```

---

### PENTEST-08 — Corrigir CSP (remover unsafe-inline e unsafe-eval)
**Arquivo:** `app/Http/Middleware/SecurityHeaders.php`
```php
// ANTES:
"Content-Security-Policy" => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ..."

// DEPOIS — remover unsafe-inline e unsafe-eval, usar nonce:
// Para Vue 3 compilado (Vite gera JS separado), não precisa de inline:
"Content-Security-Policy" => "default-src 'self'; " .
    "script-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .   // manter unsafe-inline só em style se necessário para Vue
    "img-src 'self' data: blob: https://api.dicebear.com; " .
    "connect-src 'self'; " .
    "font-src 'self' data:;"
```

---

### PENTEST-10 — Reativar HSTS
**Arquivo:** `app/Http/Middleware/SecurityHeaders.php`
```php
// DESCOMENTAR (ou adicionar):
$response->headers->set(
    'Strict-Transport-Security',
    'max-age=31536000; includeSubDomains'
);
```

---

### PENTEST-17 + PENTEST-18 + PENTEST-29 — Proteger sessões e cookies
**Arquivo:** `config/session.php`
```php
// Alterar:
'encrypt'       => true,          // era false
'secure'        => true,          // era false — só enviar cookie via HTTPS
'http_only'     => true,          // confirmar que está true
'same_site'     => 'lax',         // proteção CSRF adicional

// Arquivo: .env
// SESSION_SECURE_COOKIE=true
```

---

### PENTEST-23 — Revisar exclusões CSRF
**Arquivo:** `app/Http/Middleware/VerifyCsrfToken.php`
```php
// Verificar se as rotas abaixo precisam realmente ser excluídas:
protected $except = [
    'api/auth/login',    // <- avaliar se pode remover da lista
    'api/auth/logout',   // <- avaliar se pode remover
    'api/auth/me',       // <- avaliar se pode remover
];
// Se o frontend Vue envia o header X-CSRF-TOKEN corretamente (padrão Axios),
// remover essas exceções não vai quebrar nada.
```

---

### PENTEST-31 — Corrigir CORS para produção
**Arquivo:** `config/cors.php`
```php
// ANTES (origem de produção comentada):
'allowed_origins' => ['http://localhost:8081'],

// DEPOIS:
'allowed_origins' => [
    env('APP_URL', 'http://localhost:8081'),
    'https://gente.prefeitura.ma.gov.br',  // <- URL de produção real
],

// TAMBÉM corrigir os headers permitidos (não usar '*'):
'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-CSRF-TOKEN'],
```

---

## BLOCO 4 — Correções de menor impacto imediato

### PENTEST-04 — IDOR por IDs sequenciais (verificação de ownership)
**Padrão a aplicar em todas as rotas que retornam recursos por ID:**
```php
// Adicionar em rotas GET /recurso/{id} onde o recurso pertence a um usuário:
$funcId = optional(auth()->user()->funcionario)->FUNCIONARIO_ID;

$registro = DB::table('TABELA')
    ->where('ID', $id)
    ->where('FUNCIONARIO_ID', $funcId)  // <- garantir que pertence ao usuário
    ->first();

if (!$registro) {
    return response()->json(['error' => 'Não encontrado'], 404);
}
// Rotas afetadas: holerites, declarações, banco de horas, férias, atestados
```

---

### PENTEST-12 — Sanitizar buscas com LIKE
**Qualquer endpoint com busca livre (buscar funcionário, etc.)**
```php
// ANTES:
->where('PESSOA_NOME', 'LIKE', "%{$q}%")

// DEPOIS (usar binding — Laravel faz escape automaticamente):
->where('PESSOA_NOME', 'LIKE', '%' . $q . '%')
// OU limitar o tamanho da query:
$q = substr(strip_tags($request->input('q', '')), 0, 100);
->where('PESSOA_NOME', 'LIKE', '%' . $q . '%')
```

---

### PENTEST-16 — Remover grupo api/auth duplicado
**Arquivo:** `routes/web.php`
```bash
# Localizar as duas ocorrências:
grep -n "Route::prefix.*api/auth" routes/web.php
# ou
grep -n "api/auth" routes/web.php | grep group
# Manter apenas UMA — a que aponta para SpaAuthController
# Remover o grupo inline (~linha 51) se o SpaAuthController (~linha 1088) for o correto
```

---

### PENTEST-20 + PENTEST-30 — Estender auditoria para GETs sensíveis
**Arquivo:** `routes/web.php` — grupos de rotas financeiras e de RH
```php
// Adicionar middleware audit em rotas que retornam dados sensíveis:
Route::middleware(['auth', 'audit'])->group(function () {
    // folha, holerites, declarações, avaliações, eSocial
    Route::get('/folhas/{id}/detalhes', ...);
    Route::get('/contra-cheque/{id}/{comp}/pdf', ...);
    Route::get('/declaracoes/admin', ...);
    // ... demais rotas sensíveis
});
```

---

### PENTEST-32 — Validar parâmetro competência na folha
**Arquivo:** `routes/folha.php` — rota `POST /folha/calcular`
```php
// Adicionar validação no início da rota:
$competencia = $request->input('competencia');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) {
    return response()->json(['error' => 'Competência inválida. Use YYYY-MM'], 422);
}
// Validar também que o mês é entre 01-12:
[$ano, $mes] = explode('-', $competencia);
if ((int)$mes < 1 || (int)$mes > 12) {
    return response()->json(['error' => 'Mês inválido'], 422);
}
```

---

## Checklist de Verificação Pós-Correção de Segurança

```bash
# 1. Confirmar APP_KEY gerada:
php artisan tinker --execute="echo config('app.key');"
# Deve retornar base64:... com 44 chars

# 2. Confirmar dd() removido:
grep -rn "dd(" app/Rules/ app/Http/Middleware/
# Não deve retornar nada em ChecarAcessoUsuarioUnidade.php

# 3. Confirmar SISGEP123 removido:
grep -rn "SISGEP123" app/ routes/
# Não deve retornar nada

# 4. Confirmar log debug removido:
grep -rn "VUE3_ME_DEBUG" app/
# Não deve retornar nada

# 5. Confirmar admin hardcoded removido:
grep -rn "=== 'admin'" app/Http/Controllers/SpaAuthController.php
# Não deve retornar nada

# 6. Confirmar APP_DEBUG false:
grep "APP_DEBUG" .env
# Deve retornar APP_DEBUG=false

# 7. Testar rota sem auth (VULN-001):
curl -s http://localhost:8081/api/v3/rh/declaracoes | head -c 100
# Deve retornar 401 ou redirect para login, NUNCA dados
```

| Correção | Arquivo(s) | Complexidade | Impacto |
|---|---|---|---|
| VULN-003 dd() | ChecarAcessoUsuarioUnidade.php | 🟢 1 linha | DoS bloqueado |
| PENTEST-21 APP_KEY | terminal | 🟢 1 comando | Forge de sessão bloqueado |
| PENTEST-34 debug log | SpaAuthController.php | 🟢 1 linha | Leak de CPF bloqueado |
| PENTEST-24 SISGEP123 | AlterarSenha.php | 🟢 3 linhas | Backdoor removida |
| VULN-002 $isAdmin catch | web.php | 🟢 2 linhas | Privilege escalation bloqueada |
| VULN-004 git filter | terminal | 🟡 30 min | Credenciais removidas do histórico |
| PENTEST-22 TrustProxies | TrustProxies.php | 🟢 2 linhas | Rate limit restaurado |
| PENTEST-01 admin hardcoded | SpaAuthController.php | 🟢 3 linhas | Privilege escalation bloqueada |
| PENTEST-26 SQL injection | folha.php | 🟡 10 linhas | Destruição de folha bloqueada |
| PENTEST-25 IDOR declarações | declaracoes.php | 🟡 10 linhas | Fraude documental bloqueada |
| PENTEST-27 whitelist ação | gestor.php | 🟡 8 linhas | Autoaprovação bloqueada |
| PENTEST-14/33 debug off | .env | 🟢 2 linhas | Info leakage bloqueado |
| PENTEST-17/18/29 sessão | config/session.php | 🟢 3 linhas | Cookie hijacking bloqueado |
| PENTEST-31 CORS | config/cors.php | 🟢 3 linhas | Sistema acessível em produção |
| PENTEST-02/03 fillable | Models | 🟡 10 linhas | Mass assignment bloqueado |
