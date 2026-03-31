---
description: Registro cronológico de bugs resolvidos no GENTE — consultar antes de investigar qualquer problema
---

# Histórico de Problemas e Soluções — GENTE

> Consulte este arquivo antes de investigar qualquer problema. Se já aconteceu antes, a solução está aqui.
> Adicione uma nova entrada sempre que corrigir um bug — na mesma sessão, enquanto o contexto ainda está disponível.

---

## [2026-03-17] SIDEBAR-01 — 8 módulos ausentes da sidebar + 15 perfis não reconhecidos

**Sprint / Módulo:** Sprint 2 / DashboardLayout — Sidebar

**Sintoma:** Módulos com rota válida no router (`/rpps`, `/diarias`, `/estagiarios`, etc.) nunca apareciam na sidebar porque faltavam entradas em `ALL_NAV_ITEMS`. Perfis do banco como "RH Folha", "Coordenador de Setor" e "Equipe SISGEP" não eram reconhecidos pelo `userRoleLevel()` — mapeados erroneamente como `funcionario`.

**Causa raiz:**
- `ALL_NAV_ITEMS` tinha 8 módulos sem entrada: `/rpps`, `/diarias`, `/acumulacao-cargos`, `/transparencia`, `/pss`, `/estagiarios`, `/terceirizados`, `/sagres-tce`
- 7 módulos de configuração ausentes: `/configuracao-sistema`, `/parametros-financeiros`, `/vinculos`, `/turnos`, `/feriados`, `/tabelas-auxiliares`, `/eventos-folha`
- `userRoleLevel()` só reconhecia 3 perfis; banco tem 15
- `routeMap` sem as 16 rotas novas — breadcrumb mostrava "Módulo" genérico
- `UsuariosPMSLzSeeder` não existia — sem usuários de teste para cada perfil

**Solução:**
- `ALL_NAV_ITEMS` reorganizado em 9 seções lógicas com todos os módulos
- `userRoleLevel()` e `userRole()` expandidos para reconhecer os 15 perfis reais
- `routeMap` com 16 entradas adicionadas
- `UsuariosPMSLzSeeder.php` criado com 17 usuários (1 por perfil, senha `md5('gente@2026')`)
- Registrado no `DatabaseSeeder`

**Arquivos:** `DashboardLayout.vue`, `router/index.js`, `database/seeders/UsuariosPMSLzSeeder.php`
**Validado:** ✅ 17/03/2026 — sidebar exibindo corretamente por perfil

---

## [2026-03-17] BUG-AC-01 a 06 — Aprovação do Autocadastro não criava PESSOA/USUARIO/FUNCIONARIO

**Sprint / Módulo:** Emergencial / Autocadastro

**Sintoma:** `POST /autocadastro/{token}/aprovar` retornava 500 com erros de coluna inexistente (`PESSOA_SEXO_ID`, `created_at`, etc.). Quando não dava erro, o usuário era aprovado sem matrícula, sem perfil e sem conseguir logar.

**Causa raiz (6 bugs):**
1. **BUG-AC-01:** `FUNCIONARIO_MATRICULA` nunca inserido no INSERT do FUNCIONARIO
2. **BUG-AC-02:** CPF em `PESSOA_CPF` (não existe) — correto é `PESSOA_CPF_NUMERO`
3. **BUG-AC-03:** Cinco campos com nome errado na tabela PESSOA:
   - `PESSOA_SEXO_ID` → `PESSOA_SEXO`
   - `ESTADO_CIVIL_ID` → `ESTADO_CIVIL`
   - `PESSOA_GRAU_INSTRUCAO` → `ESCOLARIDADE_ID`
   - `PESSOA_RACA_COR` → `PESSOA_RACA`
   - `PESSOA_RG_ORG_EMISSOR` → `PESSOA_ORG_EMISSOR`
4. **BUG-AC-04:** `DB::table('USUARIO')->insert()` sem `insertGetId()` → `USUARIO_ID` nunca capturado → PESSOA.USUARIO_ID = null
5. **BUG-AC-05:** `USUARIO_PERFIL` nunca criado → sidebar vazia, acesso zero
6. **BUG-AC-06:** `Hash::make()` (bcrypt) em sistema que valida MD5
7. **Extra:** `created_at`/`updated_at`, `PESSOA_ATIVO`, `PESSOA_DATA_CADASTRO` inseridos em tabelas que não têm essas colunas

**Solução:**
- Endpoint reescrito com campos reais das migrations (verificados via `Schema::getColumnListing()`)
- Matrícula gerada no formato `YYYY-NNNN` sequencial por ano
- `insertGetId()` usado para USUARIO e PESSOA; vínculos PESSOA.USUARIO_ID e FUNCIONARIO.USUARIO_ID preenchidos
- `USUARIO_PERFIL` criado com `PERFIL_ID = 5` (Externo)
- Senha armazenada como `md5($dados['senha'])`
- Colunas inexistentes removidas de todos os INSERTs e UPDATEs

**Arquivo:** `routes/web.php` — `Route::post('/autocadastro/{token}/aprovar', ...)`
**Validado:** ✅ 17/03/2026 — aprovação criou PESSOA + USUARIO + FUNCIONARIO com matrícula gerada

---

## [2026-03-11] GAP-07 — PDF Holerite referenciava view inexistente

**Sprint / Módulo:** Sprint 3 / Folha de Pagamento

**Sintoma:** `GET /api/v3/meus-holerites/{id}/pdf` retornava 500 — `View [holerite.holerite_pdf] not found`.

**Causa raiz:**
1. O endpoint buscava `CALCULO_FUNCIONARIO` (tabela legada) em vez de `DETALHE_FOLHA` (tabela real do sistema)
2. A view era referenciada como `holerite.holerite_pdf` mas existe como `v3.holerite-pdf`
3. As variáveis passadas (`$calculo`, `$pessoa`, `$proventos`) não correspondiam ao que a view espera (`$servidor`, `$rubricas`, `$competencia`)

**Solução:**
- Reescrito o endpoint para buscar `DETALHE_FOLHA` via join com FOLHA, FUNCIONARIO, PESSOA, CARGO, LOTACAO/SETOR
- View corrigida para `v3.holerite-pdf` (já existia em `resources/views/v3/holerite-pdf.blade.php`)
- Variáveis alinhadas ao contrato da view (`$servidor`, `$rubricas`, `$competencia`, `$emitido_em`, `$total_proventos`, `$total_descontos`, `$liquido`)
- Rubricas buscadas de `ITEM_FOLHA` + fallback sintético se a tabela não existir

**DomPDF:** já estava em `composer.json` e instalado em `vendor/`.

**Arquivos alterados:**
- `routes/web.php` — endpoint `GET /meus-holerites/{id}/pdf` (L7385-7430)

---

## [2026-03-11] PERF-01 — N+1 na listagem admin de progressão funcional

**Sprint / Módulo:** Sprint 5 / Progressão Funcional

**Sintoma:** `GET /api/v3/progressao-funcional/admin` e `/lista-elegiveis` disparavam **2 queries por servidor** (AVALIACAO_DESEMPENHO e AFASTAMENTO), totalizando O(2N) queries para uma prefeitura com 500+ servidores.

**Causa raiz:** `$avaliarEleg()` chamada dentro do `->map()` executava `DB::table('AVALIACAO_DESEMPENHO')->where('FUNCIONARIO_ID', ...)` e `DB::table('AFASTAMENTO')->where(...)` individualmente para cada funcionário.

**Solução:**
1. Pré-buscar `AVALIACAO_DESEMPENHO` com `whereIn` → `groupBy('FUNCIONARIO_ID')` antes do loop
2. Pré-buscar `AFASTAMENTO` com `whereIn` → `pluck()->flip()` (lookup O(1)) antes do loop
3. Injetar como `$func->_avaliacao` e `$func->_com_penalidade`
4. Modificar `$avaliarEleg` para usar campos pré-carregados se disponíveis (fallback para query individual — mantém compatibilidade com chamada de um único funcionário)

**Resultado:** O(2N) → O(2) queries por endpoint. **Arquivos alterados:**
- `routes/progressao_funcional.php` — `$avaliarEleg`, `GET /admin`, `GET /lista-elegiveis`

---

## [2026-03-11] ERP-Sprint — routes PHP geradas sem `use Illuminate\Support\Facades\Auth`

**Sintoma:** `POST /empenho`, `/receita`, `/lancamentos`, `/sagres/gerar` causariam 500 — `Auth::user()` chamado sem o `use` declarado.

**Arquivos afetados:** `execucao_despesa.php`, `contabilidade.php`, `receita_municipal.php`, `controle_externo.php`

**Solução:** Adicionado `use Illuminate\Support\Facades\Auth;` no topo de cada um.

**Prevenção:** Todo arquivo de rota iniciar com o cabeçalho completo do workflow:
```php
<?php
use Illuminate\Support\Facades\Auth;
```

---

## [2026-03-11] BUG-05 — eSocial pendências com subquery O(n²)

**Sintoma:** `GET /api/v3/esocial/pendencias` lento pois executava N SELECTs aninhados.

**Causa:** Uso de subquery correlacionada `(SELECT EVENTO_ID FROM ESOCIAL_EVENTO WHERE FUNCIONARIO_ID = f.FUNCIONARIO_ID ... LIMIT 1)` dentro do `whereNull`.

**Solução:** Substituído por `leftJoin('ESOCIAL_EVENTO as e2200', ...)` + `whereNull('e2200.EVENTO_ID')`. Arquivo: `routes/esocial.php`.

---

## [2026-03-11] BUG-06 — Typo HISTORARIO_SALARIO_ANTES

**Sintoma:** Campo `salario_de` em `/api/v3/progressao-funcional` retornava `null` — o campo `HISTORARIO_SALARIO_ANTES` não existe no banco de dados.

**Causa:** Typo na linha 133 de `routes/progressao_funcional.php`. A sessão anterior aplicou uma correção com fallback duplicado que mascarava o bug:
```php
// ERRADO: 'salario_de' => $h->HISTORARIO_SALARIO_ANTES ?? $h->HISTORICO_SALARIO_ANTES,
```

**Solução:** `'salario_de' => $h->HISTORICO_SALARIO_ANTES`

---

## [2026-03-11] GAP-01/03/04 + Sprint 6 — Endpoints ausentes e módulos sem rota

**Sintoma:**
- `GET /api/v3/notificacoes` → 404 (polling contínuo causava erros no console)
- `/servidores/buscar` e `/secretarias` → 404 em múltiplos módulos
- 5 módulos com frontend completo mas sem nenhuma rota no backend

**Causa:** Endpoints nunca implementados. Arquivos de rota da Sprint 6 criados mas nunca registrados no web.php.

**Solução:**
- Adicionados stubs GET/POST `/notificacoes`, `GET /servidores/buscar`, `GET /secretarias` no web.php
- Criados: `routes/acumulacao.php`, `transparencia.php`, `pss.php`, `terceirizados.php`, `sagres.php`
- Todos os 14 arquivos externos registrados com `require __DIR__.'/XXX.php'` dentro do grupo `api/v3`
- Polling do DashboardLayout.vue pausado quando `document.hidden === true`

---

## [2026-02-23] Padrão "ID+ATIVO only" — colunas de dados faltantes em 14+ tabelas

**Sintoma:**
> `POST /modulo/inserir` retornava HTTP 500 com `SQLSTATE[42S22]: Invalid column name 'X'` para vários módulos. O `GET /listar` funcionava pois só filtrava por `ATIVO`.

**Causa:**
Migrations de criação usaram apenas `$table->id()` + `$table->integer('X_ATIVO')`, ignorando todas as colunas que os models PHP referenciam em `$fillable`.

**Padrão suspeito:** Se `GET /listar` retorna HTTP 200 com `total=0` E `POST /inserir` retorna 500 → colunas faltantes.

**Diagnóstico:**
```bash
docker compose exec app php audit_colunas.php
docker compose exec app sh check_errors.sh
# Retorna: Invalid column name 'NOME_COLUNA'
```

**Solução — migration segura:**
```php
if (!Schema::hasTable('TABELA')) {
    Schema::create('TABELA', function (Blueprint $table) { ... });
}
if (Schema::hasTable('TABELA') && !Schema::hasColumn('TABELA', 'COLUNA')) {
    Schema::table('TABELA', function (Blueprint $table) {
        $table->string('COLUNA')->nullable();
    });
}
```
```bash
docker compose exec app php artisan migrate --path=database/migrations/SEU_ARQUIVO.php --force
```

**Prevenção futura:** Ao criar nova tabela, incluir TODAS as colunas do `$fillable` do model correspondente.

**Status:** ✅ Resolvido (migrations 000004 a 000007)

---

## [2026-02-23] Controllers sem método `view()` — erro 500 em rotas GET

**Sintoma:**
> `GET /setor/view`, `/afastamento/view`, `/ferias/view` retornavam `BadMethodCallException: Method XxxController::view does not exist`.

**Causa:**
Controllers `SetorController`, `AfastamentoController`, `FeriasController` não tinham o método `view()` definido, mas as rotas no `web.php` apontavam para ele.

**Solução:**
```php
public function view()
{
    return view('home'); // home.blade.php = SPA Vue principal — NÃO usar 'app' (não existe)
}
```

**Diagnóstico:**
```bash
docker compose exec app sh check_controllers.sh
# 0 = sem view(), 1 = com view()
```

**Status:** ✅ Resolvido

---

## [2026-02-23] Login via curl falhando — campo senha incorreto

**Sintoma:**
> POST `/login` com `password=xxx` retornava redirect para `/login`.

**Causa:**
O campo de senha não é `password` — é `USUARIO_SENHA`. O form é um componente Vue renderizado por JS, invisível ao curl sem JavaScript.

**Solução:**
```bash
curl -X POST http://nginx/login \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $CSRF" \
  -d '{"USUARIO_LOGIN":"admin","USUARIO_SENHA":"admin123"}'
# CSRF fica em <meta name="csrf-token"> — não em <input hidden>
```

**Status:** ✅ Resolvido

---

## [2026-02-23] Nova implantação falha — tabelas de dados base ausentes

**Sintoma:**
> Banco limpo: migrations rodam sem erro, login funciona, mas módulos de RH/Escala/Folha falham silenciosamente.

**Causa:**
Não existem seeders nativos para dados geográficos e estruturais. Cadastrar Funcionário sem UF/Cidade/Bairro/Unidade retorna listas vazias ou erros.

**Ordem de carga obrigatória:**
1. `UF` → `CIDADE` → `BAIRRO`
2. `UNIDADE` → `SETOR` → `ATRIBUICAO`
3. `PESSOA` → `FUNCIONARIO` → `LOTACAO`
4. `TURNO`, `FERIADO`

Scripts: `seed_dados_base.php` e `seed_funcionario.php` na raiz do projeto.

**Status:** ✅ Documentado

---

## [2026-02-24] Vue SFC — Element is missing end tag

**Sintoma:**
> Tela vermelha do Vite: `[plugin:vite:vue] Element is missing end tag.` apontando para a linha 1 ou tag pai de um SFC.

**Causa:**
Deleção automática de bloco HTML por IA/console removeu um `</div>` de fechamento do contêiner pai junto com o conteúdo.

**Solução:**
Verificar e rebalancear a árvore de `<div>` no arquivo afetado. Em 100% dos casos é uma tag sem fechamento após refatoração.

**Status:** ✅ Resolvido

---

## [2026-02-24] Porta 8000 sequestrada — Laravel sobe na 8001 silenciosamente

**Sintoma:**
> Frontend retorna 500 no `/sanctum/csrf-cookie`. Login não funciona.

**Causa:**
Processo zumbi segurando a porta 8000. Laravel sobe na 8001 sem avisar. `vite.config.js` tem proxy fixo na 8000.

**Diagnóstico e solução:**
```powershell
netstat -ano | findstr :8000
Stop-Process -Id <PID> -Force
# Reiniciar Laravel — deve mostrar http://127.0.0.1:8000
```

**Status:** ✅ Resolvido

---

## [2026-02-24] Auth guard [sanctum] is not defined — erro 500 global

**Sintoma:**
> Todas as telas do Vue param de carregar. Qualquer chamada retorna 500.

**Causa:**
`auth:sanctum` incluído em `routes/api.php`. Sanctum não está instalado neste projeto.

**Solução:**
1. Remover `auth:sanctum` de `routes/api.php`
2. Mover rotas da SPA Vue para `routes/web.php` (que tem `StartSession` + cookies de sessão)
3. Remover chamada pre-flight `api.get('/sanctum/csrf-cookie')` do frontend
4. Substituir `route()` por `$request->routeIs()` no middleware `UsuarioExterno.php`

**Status:** ✅ Resolvido

---

## 🛑 REGRA ARQUITETURAL — NÃO usar api.php para SPA Vue (Pós-Crise Fev/2026)

**Contexto:**
Tentativas de conectar o Vue ao Laravel via `routes/api.php` resultaram em HTTP 500/419 porque o projeto não tem Sanctum instalado.

**Regra definitiva:**

| ❌ PROIBIDO | ✅ CORRETO |
|-------------|-----------|
| Rotas Vue em `routes/api.php` | Todas as rotas Vue em `routes/web.php` |
| `auth:sanctum` ou `auth:api` | `auth` (web guard padrão) |
| `api.get('/sanctum/csrf-cookie')` | Gerenciar sessão pelo POST `/login` |
| `response()->json()` via api.php | `response()->json()` via web.php |

O `web.php` fornece `StartSession`, cookies e CSRF automaticamente. O `api.php` não.

---

## [2026-03-10] BOM UTF-8 em web.php corrompendo todas as respostas JSON

**Sintoma:**
> Dashboard exibe "Bom dia, Usuário" com valores `undefined`. `authStore.user` contém uma **string** em vez de objeto — `user.nome` retorna `undefined`.

**Causa:**
`routes/web.php` tinha BOM UTF-8 (`EF BB BF`) no início, introduzido por editor no Windows. O BOM vazava para o body HTTP antes dos headers JSON, impedindo o Axios de parsear a resposta como objeto.

**Diagnóstico:**
```powershell
Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
# BOM:  239 187 191  ← problema
# OK:    60  63 112  ← correto
# No browser: JSON.stringify(pinia.state.value.auth.user) começa com \ufeff
```

**Solução:**
```powershell
$bytes = [IO.File]::ReadAllBytes('routes\web.php')
if ($bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    [IO.File]::WriteAllBytes('routes\web.php', $bytes[3..($bytes.Length-1)])
}
```

Parse defensivo no `store/auth.js` (proteção extra já implementada):
```js
this.user = typeof data === 'string'
    ? JSON.parse(data.replace(/^\uFEFF/, '').trim())
    : data
```

**Verificar também antes de deploy** — ver `docs/checklist-deploy-vps.md` §Atenção BOM.

**Status:** ✅ Resolvido

---

## [2026-03-10] Admin exibindo perfil "Gestor" no dashboard

**Sintoma:**
> Login com `admin` bem-sucedido mas dashboard exibe "Bom dia, Gestor" sem menu de admin.

**Causa:**
Endpoint `/me` consultava `usuarioPerfis` primeiro. O usuário `admin` tinha registro "Gestor" nessa tabela, sobrepondo o fallback esperado.

**Solução:**
Verificar `USUARIO_LOGIN === 'admin'` **antes** de consultar a relação:
```php
if (strtolower($user->USUARIO_LOGIN) === 'admin') {
    $perfilNome = 'admin';
} else {
    $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())
        ->perfil->PERFIL_NOME ?? null;
}
```

**Diagnóstico rápido:** Acessar `GET /api/auth/me` logado como admin. Se `perfil` ≠ `"admin"` → verificar lógica em `routes/web.php`.

**Status:** ✅ Resolvido

---

## [GAP-BACKEND] Endpoints faltando para views Vue existentes

**Situação identificada em:** 2026-03-10 (varredura completa das views)

**Endpoints ausentes que causam 404 constantes nos logs:**

| Endpoint | View afetada | Sprint |
|----------|-------------|--------|
| `GET /api/v3/notificacoes` | DashboardLayout — polling 60s | Sprint 1 |
| `GET/POST /api/v3/folhas` | FolhaPagamentoView | Sprint 3 |
| `GET /api/v3/secretarias` | FolhaPagamentoView | Sprint 3 |
| `GET /api/v3/banco-horas` | BancoHorasView | Sprint 5 |
| `GET /api/v3/atestados` | AtestadosMedicosView | Sprint 5 |
| `GET /api/v3/acumulacao` | AcumulacaoView | Sprint 6 |
| `POST /api/v3/transparencia/exportar` | TransparenciaView | Sprint 6 |
| `GET /api/v3/pss/*` | PSSView | Sprint 6 |
| `GET /api/v3/terceirizados/*` | TerceirizadosView | Sprint 6 |
| `POST /api/v3/sagres/gerar` | SagresView | Sprint 6 |

**Ver:** `PLANO_IMPLEMENTACAO_GENTE_V3.md` §GAP-01 a §GAP-13 para código completo de cada endpoint.

**Status:** ❌ Pendente

---

## [BUG-01] Margem de consignação sempre zero — campos inexistentes

**Identificado em:** 2026-03-10

**Sintoma:**
> `GET /consignacao/margem/{id}` retorna `{"margem": 0, "disponivel": 0}` para qualquer funcionário. Contratos criados sem validação real de margem.

**Causa:**
Query usa `df.DETALHE_TIPO` e `df.DETALHE_VALOR` — campos que não existem. Campos reais: `DETALHE_FOLHA_PROVENTOS`, `DETALHE_FOLHA_DESCONTOS`, `DETALHE_FOLHA_LIQUIDO`.

**Solução:**
```php
$folha = DB::table('DETALHE_FOLHA as df')
    ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
    ->where('df.FUNCIONARIO_ID', $funcionario_id)
    ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
    ->select(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0) as liquido'))
    ->first();
```

Corrigir em 2 lugares: `GET /margem/{id}` e `POST /consignacao`.

**Arquivos:** `routes/consignacao.php`
**Status:** ❌ Pendente

---

## [BUG-02] Guard de rota Vue com lógica invertida

**Identificado em:** 2026-03-10

**Sintoma:**
> Usuário `funcionario` acessa rotas que deveriam exigir `rh` ou `admin`.

**Causa:**
`hasAccess()` em `router/index.js` com comparação invertida. `ROLE_HIERARCHY = ['admin', 'rh', 'gestor', 'funcionario']` — índice 0 = mais permissivo. A lógica atual permite índices maiores (menos privilegiados) passarem em rotas restritas.

**Solução:**
```js
function hasAccess(perfil, requiredRoles) {
    if (!requiredRoles || requiredRoles.length === 0) return true
    const userLevel = ROLE_HIERARCHY.indexOf(userRole(perfil))
    if (userLevel === -1) return false
    const minRequired = Math.min(...requiredRoles.map(r => ROLE_HIERARCHY.indexOf(r)).filter(i => i !== -1))
    return userLevel <= minRequired
}
```

**Arquivos:** `resources/gente-v3/src/router/index.js`
**Status:** ❌ Pendente

---

## [BUG-03] fetchUser() sem cache TTL — request HTTP a cada navegação

**Identificado em:** 2026-03-10

**Sintoma:**
> Cada troca de rota no Vue dispara um `GET /api/auth/me`. Com múltiplos usuários, dezenas de requests desnecessários por minuto.

**Solução:**
Adicionar `_lastFetch` + TTL de 5 minutos em `store/auth.js`. Ver código completo em `PLANO_IMPLEMENTACAO_GENTE_V3.md` §BUG-03.

**Arquivos:** `resources/gente-v3/src/store/auth.js`
**Status:** ❌ Pendente

---

## [BUG-04] fetch() nativo com CSRF frágil — erro 419 intermitente

**Identificado em:** 2026-03-10

**Sintoma:**
> Formulários POST retornam 419 de forma intermitente. Não reproduzível em dev, aparece em produção.

**Causa:**
`ConsignacaoView.vue`, `ExoneracaoView.vue`, `HoraExtraView.vue` usam `fetch()` nativo com CSRF via regex de cookie. Token URL-encoded falha em alguns browsers.

**Solução:** Substituir todos por `import api from '@/plugins/axios'`.

**Arquivos:** ConsignacaoView (5 ocorrências), ExoneracaoView (5), HoraExtraView (3)
**Status:** ❌ Pendente

---

---

## [2026-03-11] Sprint 0 — SEC-01: Rota /dev/set-senha deletada

**Sprint / Módulo:** Sprint 0 / Segurança

**Sintoma:**
> `GET /dev/set-senha/{login}/{senha}` permitia resetar senha de qualquer usuário sem autenticação. Proteção era `if (app()->environment('production')) abort(404)` — falha se `APP_ENV` estiver errado.

**Causa raiz:**
Rota de desenvolvimento criada para emergência nunca foi removida. Proteção dependia de variável de ambiente — padrão frágil.

**Solução:**
Rota deletada permanentemente de `routes/web.php`. Comentário `// ⚠️ SEC-01: /dev/set-senha DELETADO DEFINITIVAMENTE` adicionado como marcador.

**Arquivos alterados:**
- `routes/web.php` — linhas 39–77 (bloco dev reestruturado)

**Status:** ✅ Resolvido

---

## [2026-03-11] Sprint 0 — SEC-02: Rotas /dev/* protegidas com isLocal()

**Sprint / Módulo:** Sprint 0 / Segurança

**Sintoma:**
> Rotas `/dev/ping-db`, `/dev/echo-request`, `/dev/echo-raw`, `/dev/diag-login`, `/dev/criar-admin`, `/dev/seed-dados` protegidas individualmente por `if (app()->environment('production')) abort(404)`. Se `APP_ENV=staging` ou `APP_ENV=homologacao`, rotas ficam expostas.

**Causa raiz:**
Proteção dependente de string exata `'production'`. Qualquer ambiente não-production tem acesso.

**Solução:**
Todos os blocos `/dev/*` agrupados em um único `if (app()->isLocal() || app()->environment('development', 'testing'))` com `Route::prefix('dev')->group()`. Verificado: `php -l routes/web.php` → sem erros de sintaxe.

**Arquivos alterados:**
- `routes/web.php` — linhas 39–77 (início) e 540–666 (fim do bloco)

**Status:** ✅ Resolvido

---

## [2026-03-11] BUG-07: Grupo de rotas duplicado — path /api/v3/api/v3/rota

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> Rotas de diárias registradas com path `/api/v3/api/v3/diarias`. Middleware duplicado.

**Causa:**
`routes/diarias.php` abria `Route::middleware()->prefix('api/v3')->group()` próprio, mas já era incluído dentro do grupo `api/v3` do `web.php`.

**Solução:**
Removido o wrapper `Route::middleware()->prefix('api/v3')->group()` de `routes/diarias.php`. Rotas agora herdam o contexto do grupo pai. Verificado: `php -l routes/diarias.php` → sem erros de sintaxe.

**Arquivos alterados:**
- `routes/diarias.php` — linha 7 (wrapper removido) e linha 87 (fechamento órfão removido)

**Status:** ✅ Resolvido


---

## [2026-03-11] BUG-01: Margem de consignação sempre zero

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> `GET /consignacao/margem/{id}` sempre retorna `{"margem": 0}`. Contratos criados sem validação real de margem.

**Causa:**
Query usava `CASE WHEN df.DETALHE_TIPO = "C"` e `df.DETALHE_VALOR` — campos inexistentes. O `DETALHE_TIPO` correto é `DETALHE_FOLHA_LIQUIDO` / `DETALHE_FOLHA_PROVENTOS` / `DETALHE_FOLHA_DESCONTOS`.

**Solução:**
Substituído `CASE WHEN DETALHE_TIPO` por `COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0)`. Implementada margem separada: **30% para empréstimos** (BANCO/SINDICATO/COOPERATIVA) e **5% para cartão** (CARTAO), conforme §12 das regras-gerais.

**Arquivos:** `routes/consignacao.php` — endpoints POST /consignacao e GET /consignacao/margem/{id}

**Status:** ✅ Resolvido

---

## [2026-03-11] BUG-02: Guard de rota Vue com lógica invertida

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> Usuário `funcionario` conseguia acessar rotas restritas a `rh` ou `admin`.

**Causa:**
`hasAccess()` em `router/index.js` usava `ROLE_HIERARCHY.indexOf(r) >= roleLevel`. Como `ROLE_HIERARCHY = ['admin', 'rh', 'gestor', 'funcionario']` (índice 0 = mais privilegiado), a condição `>=` era exatamente o oposto do correto — permitia usuários com índice maior (menos privilegiados).

**Solução:**
```js
return roleLevel <= minRequired  // corrigido: era >=
```

**Arquivos:** `resources/gente-v3/src/router/index.js`

**Status:** ✅ Resolvido

---

## [2026-03-11] BUG-04: fetch() nativo com CSRF frágil — erro 419 intermitente

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> Formulários POST retornam 419 de forma intermitente em produção.

**Causa:**
ConsignacaoView, ExoneracaoView e HoraExtraView usavam `fetch()` nativo com CSRF via regex de cookie. Token URL-encoded pode falhar em alguns browsers.

**Solução:**
Todas as views migradas para `import api from '@/plugins/axios'`. O axios gerencia CSRF automaticamente. Também corrigido uso de `/exoneracao/buscar` → `/servidores/buscar` (endpoint semanticamente correto).

**Views migradas:** ConsignacaoView (8 calls), ExoneracaoView (7 calls), HoraExtraView (5 calls)

**Status:** ✅ Resolvido

---

## [2026-03-11] SEC-03: JWT do app de ponto usando config('app.key')

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> App mobile de ponto usava a mesma chave do Laravel (`APP_KEY`) para assinar JWTs. Rotação do `APP_KEY` invalida todos os tokens do app.

**Causa:**
`routes/ponto_app.php` tinha `$secret = config('app.key')` em 2 lugares: no helper `$decodeAppToken` e na geração do token no login.

**Solução:**
1. Adicionado `PONTO_APP_JWT_SECRET=GERAR_ANTES_DO_DEPLOY_...` no `.env`
2. Substituído `config('app.key')` por `env('PONTO_APP_JWT_SECRET') ?: config('app.key')` (fallback seguro para desenvolvimento)

**Ação obrigatória antes do deploy:**
```bash
php -r "echo base64_encode(random_bytes(32));"
# Copiar resultado → PONTO_APP_JWT_SECRET no .env de produção
```

**Arquivos:** `.env`, `routes/ponto_app.php` (linhas 30 e 75)

**Status:** ✅ Resolvido (segredo gerado pendente no ambiente de produção)





---

## [2026-03-11] BUG-03: fetchUser() sem cache TTL — request a cada navegação

**Identificado em:** 2026-03-10 | **Resolvido em:** 2026-03-11

**Sintoma:**
> Cada troca de rota no Vue dispara um `GET /api/auth/me`. Com múltiplos usuários, dezenas de requests desnecessários por minuto.

**Causa:**
`fetchUser()` em `store/auth.js` não tinha controle de cache — fazia GET /me incondicionalmente a cada chamada do navigation guard.

**Solução:**
- Adicionado `_lastFetch: 0` no state
- `fetchUser(forceFetch = false)` retorna imediatamente se `_lastFetch` < 5 min e `user` existe
- `clearCache()` reseta `_lastFetch` (chamado no logout e pós-login)
- `LoginView.vue` chama `authStore.clearCache()` antes de `router.push('/dashboard')` para garantir que o guard busque dados frescos do usuário recém-autenticado

**Arquivos:** `resources/gente-v3/src/store/auth.js`, `resources/gente-v3/src/views/auth/LoginView.vue`

**Status:** ✅ Resolvido

## [2026-03-15] Porta 8000 bloqueada pelo XAMPP — backend mudou para :8080

**Sprint / Módulo:** Sprint 0 / Infraestrutura local

**Sintoma:**
> Login retorna 401 — `GET /api/auth/me` falha. Backend artisan serve não sobe na :8000.

**Causa:**
XAMPP (processo Manager) estava ocupando a porta 8000. `php artisan serve --port=8000` falha silenciosamente ou sobe em porta alternativa.

**Solução:**
- Backend rodando em `:8080`: `php artisan serve --port=8080 --host=127.0.0.1`
- `vite.config.js` proxy target atualizado: `http://127.0.0.1:8000` → `http://127.0.0.1:8080`
- `.env` atualizado: `APP_URL=http://localhost:8080`

**Como identificar no futuro:**
```powershell
netstat -ano | findstr :8000
# Se aparecer LISTENING com PID diferente do artisan → porta ocupada
# Solução: usar --port=8080 ou matar o processo XAMPP
```

**Arquivos alterados:**
- `resources/gente-v3/vite.config.js` — proxy target
- `.env` — APP_URL

**Status:** ✅ Resolvido

---

## [2026-03-15] Proxy Vite capturando /login e /logout — tela branca no Vue 3

**Sprint / Módulo:** Sprint 0 / Frontend

**Sintoma:**
> `localhost:5173/login` mostra tela branca. Laravel Debugbar aparece no rodapé. Vue 3 não renderiza.

**Causa:**
Proxy do Vite tinha `login` e `logout` na regex de rotas capturadas. O browser ia para `localhost:5173/login`, o Vite proxy mandava para o Laravel `:8080/login`, que retornava a view Blade do legado Vue 2 (`js/app.js`). O Vue 3 nunca era montado.

**Solução:**
Remover `login` e `logout` da regex do proxy em `vite.config.js`. Essas rotas devem ser tratadas pelo Vue Router, não pelo Laravel diretamente.
```js
// ❌ ANTES:
'^/(api|csrf-cookie|login|logout|sanctum|storage|remessa)'
// ✅ DEPOIS:
'^/(api|csrf-cookie|sanctum|storage|remessa)'
```

**Regra derivada:** Nunca adicionar rotas de frontend (login, dashboard, etc.) no proxy do Vite. Proxy só para rotas de API e assets do Laravel.

**Arquivos alterados:**
- `resources/gente-v3/vite.config.js`

**Status:** ✅ Resolvido

---

## [2026-03-15] Call to undefined relationship [atribuicao] on model Lotacao

**Sprint / Módulo:** Sprint 0 / Funcionários

**Sintoma:**
> Ao abrir `/funcionario/{id}`, aparece: `Call to undefined relationship [atribuicao] on model [App\Models\Lotacao]`.

**Causa:**
Rota `GET /funcionarios/{id}/historico` em `web.php` linha ~2292 usava `'lotacoes.atribuicao'` — relacionamento que não existe em `Lotacao`. O correto é `'lotacoes.atribuicaoLotacoes.atribuicao'` (passa pelo model intermediário `AtribuicaoLotacao`).

**Solução:**
```php
// ❌ ANTES:
'lotacoes.atribuicao'
optional($l->atribuicao)->ATRIBUICAO_NOME

// ✅ DEPOIS:
'lotacoes.atribuicaoLotacoes.atribuicao'
optional($l->atribuicaoLotacoes?->first()?->atribuicao)->ATRIBUICAO_NOME
```

**Regra:** Nunca acessar `atribuicao` diretamente em `Lotacao`. Sempre passar por `atribuicaoLotacoes->first()->atribuicao`.

**Arquivos alterados:**
- `routes/web.php` — rota GET /funcionarios/{id}/historico

**Status:** ✅ Resolvido

---

## [2026-03-15] Favicon padrão do Vite (vite.svg) em vez do brasão da PMSL

**Sprint / Módulo:** Sprint 0 / Frontend

**Sintoma:**
> Aba do browser mostra ícone padrão do Vite em vez do brasão.

**Causa:**
`resources/gente-v3/index.html` usava `href="/vite.svg"` — padrão do template Vite nunca atualizado.

**Solução:**
```html
<!-- ❌ ANTES: -->
<link rel="icon" type="image/svg+xml" href="/vite.svg" />
<!-- ✅ DEPOIS: -->
<link rel="icon" type="image/png" href="/img/favicons.png" />
```

**Arquivos alterados:**
- `resources/gente-v3/index.html`

**Status:** ✅ Resolvido

---

## [2026-03-15] Remoção do legado Vue 2 — Fase 1

**Sprint / Módulo:** Limpeza de código / Infraestrutura

**O que foi removido:**

Views Blade legadas:
- `resources/views/auth/register.blade.php`
- `resources/views/auth/verify.blade.php`
- `resources/views/auth/passwords/` (pasta completa)
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/home.blade.php`
- `resources/views/welcome.blade.php`

Assets Vue 2 compilados:
- `public/css/app.css`
- `public/js/app.js.LICENSE.txt`
- `public/mix-manifest.json`

Rotas legadas em `routes/web.php`:
- Rota `GET /home` (HomeController)
- Bloco `Route::prefix('certidao')` (CertidaoController)
- Bloco `Route::prefix('cartorio')` (CartorioController)

**O que foi mantido (ainda em uso):**
- `resources/views/auth/login.blade.php` → rota GET / ainda serve esta view (Fase 2)
- `ContraChequeController` → holerite PDF ainda em uso
- `resources/views/v3/holerite-pdf.blade.php` → PDF ativo
- `resources/views/v3/app.blade.php` → SPA Vue 3

**Verificação:** `php -l routes/web.php` → No syntax errors detected

**Status:** ✅ Fase 1 concluída

---

## [YYYY-MM-DD] Título curto do problema


**Sintoma:**
> Mensagem de erro exata, rota, status HTTP

**Causa:**
Causa raiz — não o sintoma.

**Solução:**
```php ou js
// código que resolveu
```

**Como identificar no futuro:**
Comando ou sintoma diagnóstico.

**Arquivos alterados:**
- `routes/arquivo.php`

**Status:** ✅ Resolvido | ❌ Pendente

-->

## [2026-03-11] CONSIG-01/05 — Frontend ConsignacaoView.vue desatualizado em relação ao backend

**Sprint / Módulo:** Sprint 2 / Consignação

**Sintoma:**
> Aba "Margem" exibia "35% unificado" e a aba "Relatório" não tinha botão de exportação CSV.

**Causa raiz:**
- `ConsignacaoView.vue` ainda usava `margem_total`/`margem_usada`/`margem_disponivel` (campos legados) em vez dos novos `margem_emp_*` e `margem_cartao_*` já retornados pelo backend
- Ausência de `exportarCSV()` e do botão "📥 CSV TCE-MA"

**Solução:**
- 2 barras de margem separadas: 🏦 Empréstimos (30%) + 💳 Cartão (5%)
- `pctEmp` e `pctCartao` como `computed()` sobre os novos campos
- `exportarCSV()` consome `/relatorio-analitico`, gera CSV com BOM UTF-8 e separador `;`
- Totais do relatório exibidos abaixo dos cards
- Build Vite → ✅ 3.88s sem erros

**Arquivos alterados:**
- `resources/gente-v3/src/views/rh/ConsignacaoView.vue`

**Status:** ✅ Resolvido

---

## [2026-03-15] cookieDomainRewrite: '127.0.0.1' quebra sessão ao acessar via localhost

**Sprint / Módulo:** Sprint 0 / Infraestrutura local

**Sintoma:**
> Login retorna 200, `router.push('/dashboard')` é chamado, mas navegação guard dispara `GET /api/auth/me` → 401. Usuário é redirecionado de volta ao login.

**Causa raiz:**
`vite.config.js` tinha `cookieDomainRewrite: '127.0.0.1'`. O cookie de sessão, que o Laravel define com `Domain=localhost` (SESSION_DOMAIN=localhost no .env), era reescrito para `Domain=127.0.0.1` pelo proxy Vite. O browser em `localhost:5173` não envia cookies com `Domain=127.0.0.1` → sessão perdida na requisição seguinte.

**Solução que funcionou:**
```js
// ❌ ERRADO — cookie reescrito para 127.0.0.1, browser em localhost não o envia:
cookieDomainRewrite: '127.0.0.1'

// ✅ CORRETO — cookie mantém Domain=localhost, browser em localhost:5173 o envia:
cookieDomainRewrite: 'localhost'
```

**O que NÃO funcionou:**
- Mudar `host` do Vite para `'localhost'` (sem mexer no cookieDomainRewrite) — não resolveu o 401
- Acessar via `127.0.0.1:5173` — funcionou, mas não é o URL padrão usado

**Como identificar no futuro:**
Login retorna 200, `router.push` é chamado, mas `GET /api/auth/me` retorna 401 imediatamente após. Verificar `cookieDomainRewrite` no vite.config.js e SESSION_DOMAIN no .env — devem ser o mesmo domínio que o browser usa para acessar.

**Arquivos alterados:**
- `resources/gente-v3/vite.config.js` — `cookieDomainRewrite: '127.0.0.1'` → `'localhost'`

**Status:** ✅ Resolvido

---

## [2026-03-15] Migração OneDrive → Desktop: views Vue 3 não copiadas (arquivos somente-online)

**Sprint / Módulo:** Infraestrutura local

**Sintoma:**
> Vite inicia mas mostra `[plugin:vite:import-analysis] Failed to resolve import "../views/auth/LoginView.vue"`. Apenas 4 de 71 views existem no Desktop.

**Causa raiz:**
OneDrive mantém arquivos em modo "somente online" (placeholder na nuvem, não baixado localmente). Ao copiar a pasta no Explorer, apenas os arquivos ja baixados localmente são copiados. Os demais (a maioria das views Vue) ficam no OneDrive.

**Solução que funcionou:**
Copiar a pasta `resources/gente-v3/src/views` diretamente do OneDrive para o Desktop via PowerShell ou Explorer após selecionar "Sempre manter neste dispositivo" no OneDrive.

**Como identificar no futuro:**
`(Get-ChildItem -Recurse "C:\...\Desktop\projeto\resources\gente-v3\src\views" -Filter "*.vue").Count` mostra número muito menor que o esperado. Verificar o mesmo path no OneDrive.

**Arquivos alterados:**
- `resources/gente-v3/src/views/` — copiado do OneDrive (67 views restauradas)

**Status:** ✅ Resolvido

---

## [2026-03-15] node_modules corrompidos após migração de pasta

**Sprint / Módulo:** Infraestrutura local

**Sintoma:**
> `npm run dev` falha com `Cannot find module './utils/dynamic-import-loader'` ou `Cannot find module 'fs-extra'`.

**Causa raiz:**
`node_modules` copiado entre pastas (ou do OneDrive) pode ter arquivos truncados ou links simbólicos quebrados.

**Solução que funcionou:**
```powershell
Remove-Item -Recurse -Force node_modules
npm cache clean --force
npm install
```

**Arquivos alterados:**
- `node_modules/` — reinstalado limpo

**Status:** ✅ Resolvido

---

## [2026-03-15] php.ini com extensões inexistentes causando Warning e exit code 1

**Sprint / Módulo:** Infraestrutura local

**Sintoma:**
> `php artisan serve` falha com exit code 1. `php -v` retorna `PHP Warning: Unable to load dynamic library 'pdo_firebird'`.

**Causa raiz:**
`C:\tools\php81\php.ini` tinha extensões habilitadas (`pdo_firebird`, `pdo_oci`, `pdo_odbc`, `pdo_pgsql`) cujas DLLs não existiam na pasta `ext/`. O PHP inicializava com warnings e exit code 1.

**Solução que funcionou:**
Comentar as extensões desnecessárias no `php.ini`:
```ini
;extension=pdo_firebird
;extension=pdo_oci
;extension=pdo_odbc
;extension=pdo_pgsql
```

**Como identificar no futuro:**
```powershell
php -v 2>&1 | Out-String
# Se mostrar "Unable to load dynamic library 'XXX'" → comentar no php.ini
```

**Arquivos alterados:**
- `C:\tools\php81\php.ini` — extensões sem DLL comentadas

**Status:** ✅ Resolvido

---

## [2026-03-16] BUG-S1-01 — Consignação 500: `Request::$funcionario_id`

**Sprint / Módulo:** Sprint 1 / Consignação

**Sintoma:**
> `GET /api/v3/consignacao` → 500 `{"erro":"Undefined property: Illuminate\\Support\\Facades\\Request::$funcionario_id"}`

**Causa raiz:**
`routes/consignacao.php` não tem `use` statements (comentário do arquivo: "rotas sem use statements"). O type hint `function (Request $request)` resolvia para `Illuminate\Support\Facades\Request` (Facade) em vez de `Illuminate\Http\Request`. Em PHP 8.2, acessar propriedade não existente no Facade gera fatal error.

**Solução:**
```powershell
(Get-Content routes\consignacao.php -Raw) -replace 'function \(Request \$request\)', 'function (\Illuminate\Http\Request $request)' | Set-Content ...
```

**Arquivos alterados:**
- `routes/consignacao.php` — type hint FQCN em todos os closures

**Status:** ✅ Resolvido

---

## [2026-03-16] BUG-S1-02 — `arquivoFerias` undefined em FeriasLicencasView.vue

**Sprint / Módulo:** Sprint 1 / Férias e Licenças

**Sintoma:**
> Vue warn: `Property "arquivoFerias" was accessed during render but is not defined on instance`

**Causa raiz:**
Template usava `arquivoFerias`, `onArquivoFerias()` e `onDropFerias()` para upload de arquivo, mas nenhum estava declarado no `<script setup>`.

**Solução:**
Adicionado após `const loadingSaldo = ref(false)`:
```js
const arquivoFerias  = ref(null)
const onArquivoFerias = (e) => { arquivoFerias.value = e.target.files[0] ?? null }
const onDropFerias    = (e) => { arquivoFerias.value = e.dataTransfer.files[0] ?? null }
```

**Arquivos alterados:**
- `resources/gente-v3/src/views/rh/FeriasLicencasView.vue`

**Status:** ✅ Resolvido

---

## [2026-03-16] BUG-S1-03 — Progressão Funcional: `fallback: true` / `no such column: c.CARGO_SALARIO`

**Sprint / Módulo:** Sprint 1 / Progressão Funcional

**Sintoma:**
> API retorna `{"fallback":true,"erro":"SQLSTATE[HY000]: General error: 1 no such column: c.CARGO_SALARIO"}` — UI exibe "Ref. undefined"

**Causa raiz:**
A migration que criou a tabela `CARGO` no SQLite (`2026_02_23_000001_create_remaining_domain_tables.php`) só incluiu `CARGO_ID`, `CARGO_NOME` e `CARGO_ATIVO`. `CARGO_SALARIO` existe no SQL Server legado mas nunca foi adicionado ao SQLite. Idem a `CARGO_ID` na tabela `FUNCIONARIO` e outros campos de progressão.

**Solução:**
Duas novas migrations criadas:
- `2026_03_16_000001_add_salario_to_cargo.php` — adiciona `CARGO_SALARIO decimal(12,2)` em `CARGO`
- `2026_03_16_000002_add_progressao_columns_to_funcionario.php` — adiciona `CARGO_ID`, `FUNCIONARIO_DATA_INICIO`, `FUNCIONARIO_ESTAGIO_PROBATORIO`, `FUNCIONARIO_DATA_ULTIMA_PROGRESSAO` em `FUNCIONARIO`

Ambas com `Schema::hasColumn()` guard para idempotência.

**Como identificar no futuro:**
API retorna `fallback: true` + erro de coluna não existente. Verificar se a coluna existe na migration de dev vs SQL Server.

**Arquivos alterados:**
- `database/migrations/2026_03_16_000001_add_salario_to_cargo.php` — [NEW]
- `database/migrations/2026_03_16_000002_add_progressao_columns_to_funcionario.php` — [NEW]

**Status:** ✅ Resolvido

---

## [2026-03-16] BUG-S1-04 — `/funcionario/{id}` 500: `atribuicao` relationship undefined

**Sprint / Módulo:** Sprint 1 / Funcionários

**Sintoma:**
> Tela de perfil do funcionário mostra "Call to undefined relationship [atribuicao] on model [App\Models\Lotacao]"

**Causa raiz:**
`routes/web.php` linha ~1967 ainda tinha `'lotacoes.atribuicao'` (relação legada que não existe no model `Lotacao`). A correção com `atribuicaoLotacoes.atribuicao` havia sido feita em outros lugares do arquivo mas não nesse endpoint específico (migração incompleta OneDrive → Desktop).

**Solução:**
```powershell
(Get-Content routes\web.php -Raw) -replace "'lotacoes.setor', 'lotacoes.vinculo', 'lotacoes.atribuicao'", "'lotacoes.setor', 'lotacoes.vinculo', 'lotacoes.atribuicaoLotacoes.atribuicao'" | Set-Content ...
```

**Arquivos alterados:**
- `routes/web.php` — `'lotacoes.atribuicao'` → `'lotacoes.atribuicaoLotacoes.atribuicao'`

**Status:** ✅ Resolvido
