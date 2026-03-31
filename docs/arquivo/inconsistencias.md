# GENTE v3 — Inconsistências Identificadas no Fluxo de Login

> **Escopo:** Estudo completo do fluxo de autenticação (login → sessão → /me). Nenhuma correção aplicada neste documento — apenas diagnóstico.
> **Data:** 11/03/2026

---

## Resumo Executivo

O sistema possui **8 grupos de inconsistências** no fluxo de login. A maioria não impede o funcionamento em `APP_ENV=local`, mas cria riscos sérios em produção e gera erros 500 em situações previsíveis. O problema mais crítico é a **duplicação do endpoint de login** com implementações diferentes.

---

## IC-01 — Rotas de Login Dentro do Bloco `isLocal()` ⚠️ CRÍTICO

**Arquivo:** `routes/web.php` · Linha 41

```php
if (app()->isLocal() || app()->environment('development', 'testing')) {
    Route::prefix('dev')->group(function () {
        // ...
        Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {
            Route::get('/me', ...);
            Route::post('/login', ...);   // ← Login aqui!
            Route::post('/logout', ...);
            Route::post('/change-password', ...);
        });
    });
}
```

**Problema:**
- As rotas `/api/auth/login`, `/api/auth/me`, `/api/auth/logout` e `/api/auth/change-password` estão **dentro do bloco `dev`**, que só é registrado quando `app()->isLocal()` retorna verdadeiro.
- Em **produção** (`APP_ENV=production`), essas rotas **simplesmente não existem** → qualquer tentativa de login retorna **404**, não 500.
- O Vue (frontend) chama `/api/auth/login` e `/api/auth/me` — ambas inexistentes em produção.

**Risco:** Sistema 100% inacessível em produção.

---

## IC-02 — Duplicação da Lógica de Login com Implementações Diferentes ⚠️ CRÍTICO

**Arquivos envolvidos:**
1. `routes/web.php` — closure anônima na linha ~131
2. `app/Http/Controllers/Api/SpaAuthController.php` — método `login()`

Ambos implementam login, mas com **diferenças importantes:**

| Aspecto | Closure em `web.php` | `SpaAuthController` |
|---|---|---|
| **Try/catch** | Não tem | Sim, captura todo erro |
| **Rate Limiter** | Não tem | Tem (5 tentativas) |
| **Regeneração de sessão** | Não tem | `$request->session()->regenerate()` |
| **USUARIO_ULTIMO_ACESSO** | Atualiza direto (pode 500) | Atualiza com try/catch |
| **Rota real usada** | `/api/auth/login` (bloco dev) | Não está registrado em lugar nenhum |
| **Retorno de erro** | JSON simples | JSON com `debug` em dev |

**Problema:** O `SpaAuthController` existe mas **não está registrado em nenhuma rota**. O frontend usa a closure de `web.php` (sem try/catch). Se a coluna `USUARIO_ULTIMO_ACESSO` não existir no banco, a closure lança exceção **sem tratamento → 500**.

---

## IC-03 — Ausência de CSRF antes do Login → Erro 419 ⚠️ ALTO

**Arquivo:** `resources/gente-v3/src/views/auth/LoginView.vue` + `plugins/axios.js`

**Fluxo atual do frontend:**
1. Usuário digita credenciais
2. Vue chama diretamente `POST /api/auth/login`

**Problema:**
- O middleware `web` do Laravel exige que requisições POST tenham um token CSRF válido.
- O frontend **não faz `GET /csrf-cookie` antes do POST** de login.
- Sem o cookie `XSRF-TOKEN`, o Laravel retorna **419 CSRF token mismatch**.
- O `axios.js` intercepta 419 e redireciona para `/login` → loop infinito.

**Nota:** O bloco dev ainda define `GET /dev/csrf-cookie`, mas o caminho `/csrf-cookie` (sem `/dev/`) não existe. O Vite proxy mapeia `/csrf-cookie` para o Laravel, mas a rota lá é `/dev/csrf-cookie`.

---

## IC-04 — Tabela `USUARIO` Pode Não Existir ou Diferir do Schema Esperado

**Arquivo:** `app/Models/Usuario.php`, `database/migrations/`

**Problema:**
- O banco usa SQLite localmente com 62 migrações aplicadas em cadeia.
- A migration principal é `2025_01_01_000010_create_tabelas_rh_sqlite.php` — recria a tabela `USUARIO`.
- Várias migrations posteriores adicionam colunas (`USUARIO_ALTERAR_SENHA`, `USUARIO_ULTIMO_ACESSO`, `USUARIO_VIGENCIA`) em migrations separadas.
- Se alguma migration falhou silenciosamente, a coluna pode não existir → `UPDATE` em `save()` lança exceção → 500.

**Colunas críticas usadas no login:**

| Coluna | Usada em | Risco se ausente |
|---|---|---|
| `USUARIO_ATIVO` | `WHERE USUARIO_ATIVO = 1` | Query SQL ok (ignora), retorna usuário não encontrado |
| `USUARIO_SENHA` | `Hash::check()` | 500 se coluna ausente |
| `USUARIO_VIGENCIA` | Verificação de expiração | 500 se ausente (sem try/catch) |
| `USUARIO_ALTERAR_SENHA` | `$user->save()` | 500 se ausente e a migration não rodou |
| `USUARIO_ULTIMO_ACESSO` | `$user->save()` pós-login | 500 **sem try/catch** na closure de web.php |

---

## IC-05 — Model `Usuario` Não Define `$password` Correto para Eloquent Auth

**Arquivo:** `app/Models/Usuario.php`

```php
protected $hidden = [
    'remember_token',
    'USUARIO_SENHA'   // ← oculta no JSON, mas...
];

public function getAuthPassword()
{
    return $this->USUARIO_SENHA;  // ← correto para Login manual
}
```

**Problema:**
- O campo `password` padrão do Eloquent é `password`. O modelo usa `USUARIO_SENHA`.
- `getAuthPassword()` resolve isso para `Hash::check()` manual.
- Mas `Auth::attempt(['password' => $senha])` **não funcionaria** — só funciona com o campo correto mapeado.
- A closure em `web.php` faz `Query + Hash::check()` manual (correto), mas qualquer código que use `Auth::attempt()` falharia silenciosamente (retornaria `false`).

**Risco:** Código legado ou futuro que use `Auth::attempt()` padronizado vai falhar sem erro descritivo.

---

## IC-06 — `SpaAuthController` Referenciado no Código mas Não Registrado nas Rotas

**Arquivo:** `app/Http/Controllers/Api/SpaAuthController.php`

O controller existe e tem toda a lógica de login robusta (try/catch, rate limiter, sessão, MD5→bcrypt), mas **nenhum arquivo de rotas** o registra:

```bash
# grep em todas as rotas:
grep -r "SpaAuthController" routes/
# → nenhum resultado
```

**Consequência:** Todo o trabalho feito no controller é código morto. O sistema usa a implementação inferior da closure em `web.php`.

---

## IC-07 — Proxy Vite × Laravel: Conflito de Prefixo `/dev/`

**Arquivo:** `resources/gente-v3/vite.config.js`

```js
'^/(api|csrf-cookie|login|logout|sanctum|storage|remessa)': {
    target: 'http://127.0.0.1:8000',
}
```

**Problema:**
- O proxy do Vite está configurado para redirecionar `/api/...` para o Laravel.
- As rotas de auth no Laravel têm prefixo `/dev/api/auth/...` (por causa do `Route::prefix('dev')`).
- O Vite **não** repassa `/dev/...` para o Laravel — esses paths vão para o Vue Router, que retorna a SPA.
- **Porém:** o frontend chama `/api/auth/login` (sem o `/dev/`).
- Isso funciona porque o Laravel registra as rotas como `/dev/api/auth/login` mas...

> ⚠️ **Verificar:** O `Route::prefix('dev')` cria `/dev/api/auth/login` ou apenas `/api/auth/login` dentro do grupo dev?

Analisando o código: o bloco é `Route::prefix('dev')->group(function() { Route::prefix('api/auth')->group(...)` → a URL real é `/dev/api/auth/login`.

Mas o frontend chama `/api/auth/login` (sem `/dev`). O Vite envía `/api/auth/login` para o Laravel. O Laravel não encontra `/api/auth/login` → **404**.

**Isso explica os erros de conexão vistos ao tentar logar.**

---

## IC-08 — Fluxo de Autenticação Sem Sanctum e Com Sessão Stateful Híbrida

**Arquivos:** `config/auth.php`, `app/Http/Middleware/`, `routes/web.php`

**Configuração atual:**
- `config/auth.php`: guard `web` com driver `session` (correto para SPA stateful)
- Middleware `web` aplicado nas rotas de auth (inclui CSRF, sessão, cookies)
- **Sanctum NÃO está sendo usado** — apesar de estar instalado

**Problema:**
- SPA stateful com sessão requer:
  1. `GET /sanctum/csrf-cookie` **OU** mecanismo equivalente antes do login
  2. Cookie de sessão compartilhado entre o domínio do Vite (`:5173`) e Laravel (`:8000`)
  3. `withCredentials: true` no axios ✅ (está configurado)
  4. `CORS` liberado para `localhost:5173` com `supports_credentials: true`

- **O `config/cors.php` não foi verificado** — se não liberar `localhost:5173` com credentials, **todos os cookies de sessão são bloqueados pelo browser** → login sempre falha → 401 ou comportamento inconsistente.

---

## Resumo Tabular de Risco

| ID | Inconsistência | Severidade | Impacto em Prod |
|----|---------------|------------|-----------------|
| IC-01 | Rotas de login dentro do `isLocal()` | 🔴 CRÍTICO | Sistema inacessível |
| IC-02 | Login duplicado, controller ignorado | 🔴 CRÍTICO | Sem try/catch → 500 |
| IC-03 | CSRF não solicitado antes do POST | 🟠 ALTO | 419 loop de redirect |
| IC-04 | Colunas ausentes no SQLite | 🟠 ALTO | 500 aleatório |
| IC-05 | `getAuthPassword()` vs `Auth::attempt()` | 🟡 MÉDIO | Falha silenciosa |
| IC-06 | SpaAuthController não registrado | 🟡 MÉDIO | Código morto |
| IC-07 | Prefix `/dev/` vs chamada `/api/auth/` | 🔴 CRÍTICO | Login nunca funciona |
| IC-08 | CORS/Sanctum sem verificação | 🟠 ALTO | Cookies bloqueados |

---

## Causa Raiz dos Erros 500 Observados

Com base no estudo, as causas mais prováveis para o 500 ao tentar logar são, em ordem de probabilidade:

1. **IC-07** — A URL `/api/auth/login` não existe; o Laravel retorna 404, que o Vite repassa como resposta de servidor quebrado, gerando comportamento de 500 no cliente
2. **IC-04 + IC-02** — Se a rota chegasse ao Laravel, o `$user->save()` após login atualizando `USUARIO_ULTIMO_ACESSO` pode lançar exceção sem tratamento
3. **IC-03** — 419 CSRF pela ausência de requisição ao endpoint de cookie antes do POST
4. **IC-08** — Cookies de sessão bloqueados pelo CORS entre portas diferentes (`:5173` e `:8000`)

---

---

## SEÇÃO 2 — Auditoria do Restante do Sistema

> Cobertura: CORS, sessão, .env, Router Vue, progressão funcional, migrações, segurança geral.

---

## IC-09 — CORS Incompatível com SPA Stateful ⚠️ CRÍTICO

**Arquivo:** `config/cors.php`

```php
'allowed_origins' => ['*'],      // ← INCOMPATÍVEL com credentials
'supports_credentials' => false, // ← PRECISA ser true para cookies de sessão
```

**Problema:**
O axios envia `withCredentials: true` (correto para SPA stateful). Para que o browser aceite cookies de resposta em requisições cross-origin com credentials, o servidor DEVE:

1. Retornar `Access-Control-Allow-Credentials: true` → exige `supports_credentials: true`
2. **Não usar `*` em `allowed_origins`** quando credentials está ativo — é proibido pela spec CORS do browser

O estado atual garante que **nenhum cookie de sessão seja enviado ou recebido** pelo browser entre o Vite (`:5173`) e o Laravel (`:8000`). Isso significa que login, logout e qualquer verificação de sessão falham silenciosamente.

**Impacto:** Login nunca funciona em ambiente de desenvolvimento com Vite + Laravel separados.

---

## IC-10 — APP_URL Incorreto para Ambiente de Desenvolvimento

**Arquivo:** `.env` · Linha 6

```env
APP_URL=http://localhost    # ← aponta para porta 80, não para :8000 ou :8001
```

**Problema:**
- Laravel gera URLs absolutas baseado em `APP_URL` (links de e-mail, asset URLs, redirecionamentos internos)
- O servidor está rodando em `127.0.0.1:8000` ou `127.0.0.1:8001`, mas o `APP_URL` diz `http://localhost`
- Cookie de sessão gerado com domínio `localhost` pode não ser enviado para `127.0.0.1`
- E-mails de onboarding e reset de senha terão links quebrados apontando para `http://localhost` sem porta

---

## IC-11 — Credenciais SMTP em Texto Puro no .env Comitado

**Arquivo:** `.env` · Linhas 33–34

```env
MAIL_USERNAME=a11f6b001@smtp-brevo.com
MAIL_PASSWORD=<REDACTED>
```

**Problema:**
- Credenciais de SMTP Brevo expostas em texto puro
- Se o repositório for público ou tiver acesso compartilhado, qualquer pessoa com acesso ao código pode enviar e-mails em nome do sistema
- O `.gitignore` deve incluir `.env` — verificar se o arquivo está sendo rastreado pelo Git

**Verificar:** `git check-ignore .env` e `git log --diff-filter=A -- .env`

---

## IC-12 — `Carbon` Usado sem `use` Statement em progressao_funcional.php ⚠️ ALTO

**Arquivo:** `routes/progressao_funcional.php` · Linhas 48, 123, 151, 154, 155, 156, 157, 258, 308

```php
$mesesNaRef = $ultima ? (int) Carbon::now()->diffInMonths(Carbon::parse($ultima)) : 0;
//                             ^^^^^^ — sem 'use Carbon\Carbon' no topo do arquivo
```

**Problema:**
- O arquivo tem comentário explícito: `"NÃO usar use statements aqui (herdados do web.php)"`
- `Carbon` só está disponível porque `web.php` o importa globalmente via `use Carbon\Carbon` ou via Facade
- Mas `progressao_funcional.php` é um arquivo PHP incluído via `require __DIR__` no contexto de `web.php` — essa herança de `use` statements **não funciona em PHP**: `use` é declaração de *namespace* e tem escopo de arquivo
- **Em PHP, `use` NÃO é herdado entre arquivos.** Cada arquivo precisa declarar seus próprios imports
- Funciona atualmente apenas porque `Carbon` está registrado como alias global no `app.php` via `Facade` → `Carbon\Carbon`. Se o alias for removido ou o arquivo for movido, quebra

**Verificar:** `grep -n 'use Carbon' routes/web.php` — se Carbon é importado lá, é via Facade global, não via `use`

---

## IC-13 — Router Vue com Rota `path: '/'` Duplicada

**Arquivo:** `resources/gente-v3/src/router/index.js` · Linhas 54–57 e 59–63

```js
// Rota 1:
{ path: '/', redirect: '/login' }   // ← redireciona / para /login

// Rota 2 (logo abaixo):
{ path: '/', component: DashboardLayout, meta: { requiresAuth: true }, children: [...] }
```

**Problema:**
- Há duas rotas com `path: '/'`. O Vue Router usa a **primeira que encontrar**
- A rota `redirect` vem antes da rota do layout autenticado
- Resultado: acessar a URL raiz `/` sempre redireciona para `/login`, mesmo que o usuário já esteja autenticado
- Usuário logado ao tentar acessar `/` é jogado de volta para login → comportamento confuso
- O correto seria um guard que redirecione `/` → `/dashboard` se autenticado, ou `/login` se não

---

## IC-14 — Navigation Guard Faz `fetchUser()` em Toda Navegação Protegida

**Arquivo:** `resources/gente-v3/src/router/index.js` · Linha 488

```js
router.beforeEach(async (to, from, next) => {
    await authStore.fetchUser()   // ← Request HTTP em CADA navegação
    if (!authStore.user) return next({ name: 'Login' })
})
```

**Problema:**
- O guard faz `await authStore.fetchUser()` antes de cada rota protegida
- Mesmo com o cache TTL de 5 minutos no `auth.js`, o guard chama `fetchUser()` sem `forceFetch=false` explícito — analise: a signature é `fetchUser(forceFetch = false)`, então o cache funciona
- **MAS:** se `/api/auth/me` retornar 401 (por qualquer instabilidade de rede ou sessão expirada), o guard redireciona para Login — se o usuário estava no meio de um formulário, perde tudo sem aviso
- **Maior problema:** `fetchUser()` faz request para `/api/auth/me` que, como diagnosticado em IC-07, está em `/dev/api/auth/me` — URL errada → **toda navegação entre rotas protegidas faz um request que falha com 404**

---

## IC-15 — Session Cookie `same_site: 'lax'` Bloqueia Requests Cross-Port

**Arquivo:** `config/session.php` · Linha 199

```php
'same_site' => 'lax',
```

**Problema:**
- `SameSite=Lax` permite cookies em navegações top-level mas **bloqueia em requisições XHR/fetch cross-site**
- `localhost:5173` e `localhost:8000` são **origens diferentes** (mesmo domínio, ports diferentes = cross-origin)
- Browsers modernos tratam `localhost:5173 → localhost:8000` como cross-site para fins de cookies
- Com `SameSite=Lax`, o cookie de sessão não é enviado em requisições AJAX do Vite para o Laravel
- A solução seria `SameSite=None; Secure` (mas exige HTTPS) ou usar um único servidor (proxy Vite funcionando) e ajustar o `SESSION_DOMAIN`

---

## IC-16 — Progressão Funcional com N+1 Query na Rota do Servidor

**Arquivo:** `routes/progressao_funcional.php` · Linha 55

```php
// Na rota GET /progressao-funcional (visão do servidor):
$aval = $func->_avaliacao ?? DB::table('AVALIACAO_DESEMPENHO')
    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
    ->orderByDesc('created_at')->first();
```

**Problema:**
- A rota de visão do **servidor individual** busca avaliação com query inline
- **Isso não é N+1** na visão individual (apenas 1 funcionário)
- Porém a rota `/progressao-funcional/impacto` (linha 296–326) itera sobre TODOS os funcionários chamando `avaliarEleg()` **dentro de um `each()`**, que por sua vez pode chamar o path sem cache
- A flag `_avaliacao` só é injetada quando o chamador pré-popula — na rota `/impacto`, isso **não** é feito → cada funcionário faz 2 queries adicionais (avaliação + afastamento) → N+1

---

## IC-17 — `web.php` com 10.417 Linhas — Manutenibilidade Crítica

**Arquivo:** `routes/web.php` · 10.417 linhas

**Problema:**
- O arquivo de rotas principal tem **mais de 10 mil linhas** — o maior risco de manutenção do sistema
- Toda a lógica de negócio (queries, cálculos, validações) está em closures inline dentro das rotas
- Qualquer erro de sintaxe num ponto do arquivo invalida **todas as 10.417 rotas**
- Impossível navegar, revisar ou fazer code review eficiente
- Conflitos de `use` statements entre closures do mesmo arquivo
- Não há separação por domínio: folha, CNAB, RH, configurações, eSocial — tudo misturado
- O `php artisan route:cache` falha em closures → rotas não são cacheáveis → performance prejudicada em produção

---

## IC-18 — Nenhuma Validação de Input nas Rotas de Escrita

**Arquivo:** `routes/web.php`, `routes/progressao_funcional.php`

**Exemplo em progressao_funcional.php · Linha 410:**
```php
Route::post('/progressao-funcional/promover/{id}', function (Request $request, $id) {
    if (!$request->nova_classe)
        return response()->json(['erro' => 'Nova classe obrigatória.'], 422);
    $novaClasse = $request->nova_classe;  // ← sem sanitização
    // ... INSERT direto no banco
});
```

**Problemas encontrados:**
- Maioria das rotas POST/PUT não usa `$request->validate()` — apenas verificações básicas de presença
- Campos como `nova_referencia`, `ato`, `descricao`, `nova_classe` são inseridos diretamente no banco sem type casting ou length check
- Se o banco for SQL Server em produção, alguns campos podem ter length limit e a query falha com 500
- Ausência de validação expõe a campos inesperados via `$request->all()` em queries de insert
- Nenhum uso de `FormRequest` classes — toda validação inline ou ausente

---

## Resumo Tabular Completo (IC-01 a IC-18)

| ID | Inconsistência | Área | Severidade | Impacto Prod |
|----|---------------|------|------------|--------------|
| IC-01 | Rotas de login dentro do `isLocal()` | Backend | 🔴 CRÍTICO | Sistema inacessível |
| IC-02 | Login duplicado, controller ignorado | Backend | 🔴 CRÍTICO | Sem try/catch → 500 |
| IC-03 | CSRF não solicitado antes do POST | Frontend | 🟠 ALTO | 419 loop redirect |
| IC-04 | Colunas ausentes no SQLite | Banco | 🟠 ALTO | 500 aleatório |
| IC-05 | `getAuthPassword()` vs `Auth::attempt()` | Backend | 🟡 MÉDIO | Falha silenciosa |
| IC-06 | SpaAuthController não registrado | Backend | 🟡 MÉDIO | Código morto |
| IC-07 | Prefix `/dev/` vs chamada `/api/auth/` | Roteamento | 🔴 CRÍTICO | Login nunca funciona |
| IC-08 | CORS/Sanctum sem verificação | Config | 🟠 ALTO | Cookies bloqueados |
| IC-09 | CORS `supports_credentials=false` + `origins=*` | Config | 🔴 CRÍTICO | Sessão quebrada |
| IC-10 | `APP_URL=http://localhost` sem porta | Config | 🟡 MÉDIO | Links de e-mail quebrados |
| IC-11 | Credenciais SMTP no .env | Segurança | 🟠 ALTO | Exposição de dados |
| IC-12 | `Carbon` sem `use` em arquivo separado | Backend | 🟠 ALTO | 500 se alias removido |
| IC-13 | Rota `path: '/'` duplicada no Router | Frontend | 🟡 MÉDIO | Redirect loop para autenticados |
| IC-14 | `fetchUser()` em toda navegação protegida | Frontend | 🟡 MÉDIO | Requests para URL errada |
| IC-15 | `SameSite=Lax` bloqueia cross-port | Config | 🟠 ALTO | Cookie de sessão não enviado |
| IC-16 | N+1 query na rota `/impacto` | Performance | 🟡 MÉDIO | Lento com muitos funcionários |
| IC-17 | `web.php` com 10.417 linhas | Arquitetura | 🟠 ALTO | Manutenção impossível |
| IC-18 | Sem validação de input nas rotas de escrita | Segurança | 🟠 ALTO | Dados inválidos no banco |

---

*Gerado por estudo de código em 11/03/2026 · Nenhuma alteração realizada*
