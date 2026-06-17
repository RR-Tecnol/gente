# Auditoria de rotas de mutação (sem `auth` no middleware)

## Comando

```bash
php artisan gente:auditar-rotas-mutacao
php artisan gente:auditar-rotas-mutacao --json
```

## O que verifica

Inclui rotas `POST` / `PUT` / `PATCH` / `DELETE` cujo `gatherMiddleware()` **não** contém `auth`, `auth:*`, `usuario.externo`, `perfil`, etc.

**Exclui** automaticamente: login/recuperação de senha (`api/auth/*`, `password/*`), `logout`, `registrar`, `dev/*`, Log Viewer, Debugbar, `quiosque/*`, fluxos de autocadastro e pesquisas públicas, export de transparência com `tenant.resolve`, e ferramentas (`_ignition`, `telescope`).

**Exclui** também (autenticação **não** é middleware Laravel; ver interpretação abaixo):

| URI (prefixo) | Motivo |
|---------------|--------|
| `api/ponto/bater` | Batida em terminal REP: `Authorization: Bearer` com token de `TERMINAL_PONTO` validado no closure (`routes/api.php`). |
| `api/v3/ponto/app/login` | Login do app móvel de ponto (CPF/senha → JWT). Equivalente funcional a rota de login pública. |
| `api/v3/ponto/app/registrar` | Registo de batida no app: JWT validado no closure (`routes/ponto_app.php`). |

## Veredito final (rodada 2026-04-27)

- Com as mitigações no código e as exclusões acima, o comando deve reportar: **«Nenhuma rota de mutação (após excluir fluxos login/dev/debug) sem middleware de autenticação listada.»** (exit code `0`).
- Rotas de **negócio interno** em `api_v3_web_part1.php` que estavam só em `['web']` foram envolvidas em `Route::middleware(['auth', 'audit'])->group(...)`, incluindo:
  - **Funções** (`/api/v3/funcoes*`) — CRUD Cargos e Salários.
  - **Ponto (SPA)**: `PUT /ponto/config`, `PUT /ponto/config/funcionarios/{id}` (juntamente com `GET` admin de listagem de configs no mesmo grupo), `POST /ponto/registro`, `POST /ponto/reset-dia-teste`, `GET /ponto/ledger/verificar-integridade`, `POST /ponto/reconciliacao/sugerir`, `GET` e `PUT /ponto/heatmap-risco/config`.
  - **Declarações e modelos RH**: `GET/POST /declaracoes`, `GET /declaracoes/{id}/download`, `GET/PATCH /rh/declaracoes*`, `GET/POST/DELETE /rh/modelos*`.
- **Nota:** as rotas homónimas em `api_v3_auth_part1/2.php` são registadas **antes**; o `require` de `api_v3_web_part1.php` em `web.php` **substitui** a definição final em runtime para esses paths — daí a necessidade de `auth`+`audit` no ficheiro `web_part1`.

## Interpretação (importante)

- Grupo **`api/v3` com apenas `web`** (ver `routes/web.php`): existe sessão e CSRF, mas sem `auth` no grupo as rotas são listadas por este script **até** serem agrupadas com `auth`+`audit` ou excluídas.
- **Ponto móvel** (`api/v3/ponto/app/*` exceto as excluídas): outras rotas do app usam o mesmo JWT; o auditor pode assinalá-las se forem mutação sem `auth` — nesta base, `login` e `registrar` estão excluídos por design.

## Falso negativo / falso positivo

- **Falso negativo:** middlewares personalizados que substituam `auth` (nomes não listados) não são reconhecidos.
- **Falso positivo:** registo vazio após excluir fluxos públicos; ou rotas com controlo no controller ainda sinalizadas.
