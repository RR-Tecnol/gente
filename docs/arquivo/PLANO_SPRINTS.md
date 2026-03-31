# GENTE v3 — Sprints de Desenvolvimento
**Gerado:** 12/03/2026 | **Baseado em varredura completa do código real**
**Projeto:** SISGEP — Prefeitura Municipal de São Luís / MA
**Stack:** Laravel 8 + Vue 3 SPA (Vite) + SQLite (dev) / SQL Server (prod)

---

## CONTEXTO

A Sprint Antigravity foi concluída em 11/03/2026 mas o sistema ficou inacessível — o login não funciona. A causa raiz foi confirmada por varredura direta do código: as rotas de autenticação estão presas dentro de um bloco `if (app()->isLocal())` que cria o prefixo `/dev/` nas URLs, enquanto o frontend Vue chama `/api/auth/login` sem esse prefixo. Somado a isso, o CORS está configurado de forma que bloqueia cookies de sessão.

Este documento define os sprints para corrigir o que está quebrado e concluir o escopo pendente.

---

## SPRINT 0 — EMERGENCIAL: CORRIGIR LOGIN
**Objetivo:** Sistema acessível. Login, navegação e logout funcionando.
**Estimativa:** 1 sessão de desenvolvimento (2–3 horas)
**Critério de conclusão:** Conseguir logar com usuário existente, navegar em rotas protegidas e fazer logout sem erros.

---

### TASK-01 🔴 Mover rotas de auth para fora do bloco `isLocal()`

**Arquivo:** `routes/web.php`

**Problema confirmado no código:** O bloco `if (app()->isLocal() || app()->environment('development', 'testing'))` usa `Route::prefix('dev')`, criando as URLs `/dev/csrf-cookie` e `/dev/api/auth/login`. O frontend Vue (via Vite proxy) chama `/csrf-cookie` e `/api/auth/login` sem o prefixo `/dev`. Resultado: 404 em todas as chamadas de autenticação.

**O que fazer:**

1. Localizar no `web.php` a linha `})->name('login');` (rota GET `/` que retorna a view de login)
2. Logo após essa linha, ANTES do bloco `if (app()->isLocal())`, inserir:

```php
// ══════════════════════════════════════════════════════════════
// CSRF cookie — necessário para SPA inicializar sessão
// ══════════════════════════════════════════════════════════════
Route::get('/csrf-cookie', function () {
    return response()->json(['csrfToken' => csrf_token()]);
})->middleware('web');

// ══════════════════════════════════════════════════════════════
// API DE AUTENTICAÇÃO — GENTE V3 SPA
// ══════════════════════════════════════════════════════════════
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {

    Route::get('/me', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $user = Auth::user();
        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
            if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuário') {
                $perfilNome = 'funcionario';
            }
        }
        return response()->json([
            'id'            => $user->USUARIO_ID,
            'login'         => $user->USUARIO_LOGIN,
            'nome'          => $user->USUARIO_NOME,
            'email'         => $user->USUARIO_EMAIL,
            'perfil'        => $perfilNome,
            'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
            'funcionario'   => $funcionario ? [
                'id'        => $funcionario->FUNCIONARIO_ID,
                'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                'nome'      => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
            ] : null,
        ]);
    });

    Route::post('/login', function (Request $request) {
        $login    = $request->input('USUARIO_LOGIN');
        $password = $request->input('USUARIO_SENHA');

        if (!$login || !$password) {
            return response()->json(['error' => 'Credenciais não informadas'], 422);
        }
        if ($login !== 'admin') {
            $login = preg_replace('/[^0-9]/', '', $login);
        }

        $user = \App\Models\Usuario::where('USUARIO_LOGIN', $login)
            ->where('USUARIO_ATIVO', 1)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Credenciais inválidas ou usuário inativo'], 401);
        }

        // Migração transparente MD5 → bcrypt
        if ($user->USUARIO_SENHA === md5($password)) {
            $user->USUARIO_SENHA        = bcrypt($password);
            $user->USUARIO_ALTERAR_SENHA = 1;
            $user->save();
        }

        if (!\Hash::check($password, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha incorreta'], 401);
        }

        if ($user->USUARIO_VIGENCIA && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
            return response()->json(['error' => 'Acesso expirado'], 401);
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        try {
            $user->USUARIO_ULTIMO_ACESSO = now();
            $user->save();
        } catch (\Throwable $e) { /* coluna pode não existir em ambiente antigo */ }

        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? 'funcionario';
        }

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'            => $user->USUARIO_ID,
                'login'         => $user->USUARIO_LOGIN,
                'nome'          => $user->USUARIO_NOME,
                'email'         => $user->USUARIO_EMAIL,
                'perfil'        => $perfilNome,
                'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
                'funcionario'   => $funcionario ? [
                    'id'        => $funcionario->FUNCIONARIO_ID,
                    'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                    'nome'      => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
                ] : null,
            ],
        ]);
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    });

    Route::post('/change-password', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $senhaAtual = $request->input('senha_atual');
        $senhaNova  = $request->input('senha_nova');
        if (!$senhaAtual || !$senhaNova) {
            return response()->json(['error' => 'Informe a senha atual e a nova senha'], 422);
        }
        if (strlen($senhaNova) < 6) {
            return response()->json(['error' => 'A nova senha deve ter pelo menos 6 caracteres'], 422);
        }
        $user = Auth::user();
        if (!\Hash::check($senhaAtual, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha atual incorreta'], 401);
        }
        $user->USUARIO_SENHA        = bcrypt($senhaNova);
        $user->USUARIO_ALTERAR_SENHA = 0;
        $user->save();
        return response()->json(['ok' => true, 'message' => 'Senha alterada com sucesso']);
    });

});
```

3. Dentro do bloco `if (app()->isLocal())`, **REMOVER** o `Route::get('/csrf-cookie', ...)` e o bloco `Route::prefix('api/auth')->...` inteiro. Manter apenas `/ping-db`, `/echo-request`, `/echo-raw`, `/v3`, `/autocadastro/{token}`.

---

### TASK-02 🔴 Corrigir CORS — `supports_credentials` e `allowed_origins`

**Arquivo:** `config/cors.php`

**Problema confirmado:** `'supports_credentials' => false` com `'allowed_origins' => ['*']`. Essa combinação faz browsers modernos bloquearem cookies de sessão em requisições cross-origin.

**Substituir o conteúdo completo do arquivo por:**

```php
<?php

return [
    'paths'                    => ['api/*', 'csrf-cookie', 'sanctum/*'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8000',
        'http://localhost:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];
```

---

### TASK-03 🟠 Ajustar `.env` — SESSION_DOMAIN e APP_URL

**Arquivo:** `.env`

**Adicionar/corrigir estas duas linhas:**

```env
APP_URL=http://localhost:8000
SESSION_DOMAIN=127.0.0.1
```

---

### TASK-04 🟠 Corrigir rota `path: '/'` duplicada no Vue Router

**Arquivo:** `resources/gente-v3/src/router/index.js`

**Problema:** `{ path: '/', redirect: '/login' }` antes do layout protegido — usuários autenticados sempre mandados para `/login`.

**Substituir:**
```js
{ path: '/', redirect: '/login' },
```
**Por:**
```js
{
    path: '/',
    redirect: () => {
        const authStore = useAuthStore()
        return authStore.user ? '/dashboard' : '/login'
    }
},
```

---

### TASK-05 🟠 Corrigir BOM UTF-8 e `use Carbon\Carbon` em `progressao_funcional.php`

**Arquivo:** `routes/progressao_funcional.php`

**Problema 1:** arquivo salvo como UTF-8 com BOM (`EF BB BF` antes do `<?php`). Salvar como UTF-8 sem BOM.

**Problema 2:** usa `Carbon::now()` sem declarar `use Carbon\Carbon`. O comentário "não usar use statements aqui" está errado — `use` não é herdado entre arquivos via `require`.

**Adicionar no topo após `<?php`:**
```php
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
```

---

### Checklist Sprint 0

```
[ ] TASK-01 — web.php: mover rotas auth + csrf-cookie para fora do isLocal()
[ ] TASK-02 — config/cors.php: supports_credentials=true + origins específicos
[ ] TASK-03 — .env: APP_URL=http://localhost:8000 + SESSION_DOMAIN=127.0.0.1
[ ] TASK-04 — router/index.js: redirect '/' inteligente
[ ] TASK-05 — progressao_funcional.php: remover BOM + adicionar use Carbon

TESTE OBRIGATÓRIO antes de avançar:
  - php artisan serve (porta 8000)
  - npm run dev (porta 5173)
  - Logar com usuário existente → sucesso
  - Navegar para /dashboard → sem redirect para login
  - Navegar para /funcionarios (rota admin/rh) → acesso correto por perfil
  - Fazer logout → volta para /login
  - Tentar acessar /dashboard sem estar logado → redireciona para /login
```

---

## SPRINT 1 — BANCO DE DADOS E MIGRATIONS

**Objetivo:** Garantir que todas as migrations estejam aplicadas.
**Estimativa:** 30 minutos
**Critério de conclusão:** `php artisan migrate:status` mostra todas as migrations como "Ran".
**Dependência:** Sprint 0 concluído e login funcionando.

---

### TASK-06 🟠 Aplicar migrations pendentes

```bash
php artisan migrate:status
php artisan migrate
# Se aparecer SQLSTATE[42S21] (coluna já existe):
php artisan migrate --pretend
```

| Migration | Cria / Adiciona |
|---|---|
| `2026_03_11_add_consig_autorizacao_ocorrencia` | Colunas `STATUS_AUTORIZACAO`, `AUTORIZADO_POR`, `AUTORIZADO_EM`, `MOTIVO_REJEICAO` em `CONSIG_CONTRATO` + tabela `CONSIG_OCORRENCIA` |
| `2026_03_11_add_performance_indexes` | Índices de performance em `CONSIG_CONTRATO`, `DETALHE_FOLHA`, `FUNCIONARIO` |
| `2026_03_11_create_usuario_unidade_acesso_rpps_config` | Tabela `USUARIO_UNIDADE_ACESSO` + tabela `RPPS_CONFIG` com seed inicial |

---

### TASK-07 🟠 Rodar seeder de senhas MD5

```bash
php artisan db:seed --class=MigrarSenhasMd5Seeder
```

---

### Checklist Sprint 1

```
[ ] TASK-06 — php artisan migrate (zero erros, zero Pending)
[ ] TASK-07 — php artisan db:seed --class=MigrarSenhasMd5Seeder

VERIFICAR:
  - Tabela CONSIG_OCORRENCIA existe
  - Tabela RPPS_CONFIG existe com pelo menos 1 registro
  - Tabela USUARIO_UNIDADE_ACESSO existe
  - CONSIG_CONTRATO tem colunas STATUS_AUTORIZACAO, AUTORIZADO_POR, MOTIVO_REJEICAO
```

---

## SPRINT 2 — ESCOPO: MARGEM CARTÃO E HOLERITE PDF

**Objetivo:** Corrigir compliance da consignação e entregar holerite em PDF.
**Estimativa:** 1 sessão de desenvolvimento (3–4 horas)
**Dependência:** Sprint 0 e Sprint 1 concluídos.

---

### TASK-08 🔴 Corrigir margem cartão de 5% para 10%

**Arquivo:** `routes/consignacao.php`

**Confirmado via legislação (12/03/2026):** o percentual correto é **10%**.

**Base legal:** Decreto Municipal nº 57.477/2021 (Art. 4º), que alterou o Art. 14 do Decreto nº 47.548/2015:
- Margem facultativa total: **40%** (era 30% no decreto anterior)
- **30%** para empréstimos/financiamentos (BANCO, SINDICATO, COOPERATIVA)
- **10%** exclusivo para cartão de crédito consignado — não pode ser remanejado (parágrafo único do Art. 4º)

**Fazer as seguintes substituições em `routes/consignacao.php`:**

Substituição 1 — no POST `/consignacao` (criação de contrato):
```php
// DE:
$margem_cartao = $liquido * 0.05;
// mensagem: 'Margem cartão insuficiente (5%)'

// PARA:
$margem_cartao = $liquido * 0.10;
// mensagem: 'Margem cartão insuficiente (10% — Decreto 57.477/2021)'
```

Substituição 2 — no GET `/consignacao/margem/{funcionario_id}`:
```php
// DE:
$margem_cartao = round($liquido * 0.05, 2);

// PARA:
$margem_cartao = round($liquido * 0.10, 2);
```

---

### TASK-09 🟠 Criar view do holerite PDF

**Arquivo a criar:** `resources/views/pdf/holerite.blade.php`

**Dependência:** `barryvdh/laravel-dompdf` já está no `composer.json` — não precisa instalar.

O endpoint para gerar o PDF já existe em `routes/folha.php`. Falta apenas criar a view Blade.

**A view deve exibir:**
- Cabeçalho: nome da prefeitura, competência, nome do servidor, matrícula, cargo, lotação
- Tabela de proventos (código, descrição, referência, valor)
- Tabela de descontos (código, descrição, referência, valor)
- Rodapé: total de proventos, total de descontos, valor líquido
- Campo de assinatura do servidor

**Dados disponíveis via `DETALHE_FOLHA` + `FUNCIONARIO` + `PESSOA`:**
```php
$funcionario  // nome, matrícula, cargo, lotação
$competencia  // ex: '03/2026'
$proventos    // collection com código, descrição, valor
$descontos    // collection com código, descrição, valor
$liquido      // valor líquido calculado
```

---

### Checklist Sprint 2

```
[ ] TASK-08 — Aplicar correção 0.05 → 0.10 em consignacao.php (2 lugares)
[ ] TASK-09 — Criar resources/views/pdf/holerite.blade.php

TESTAR:
  - GET /api/v3/consignacao/margem/{id} retorna margem_cartao_total = liquido * 0.10
  - POST /consignacao com valor acima da margem retorna 422 com "(10% — Decreto 57.477/2021)"
  - GET /api/v3/holerite/{id}/pdf retorna PDF renderizado sem erro 500
```

---

## SPRINT 3 — ESCOPO: NEOCONSIG

**Objetivo:** Implementar integração com o sistema Neoconsig (parser/gerador de arquivos de posição fixa).
**Estimativa:** 1 sessão de desenvolvimento (4–6 horas)
**Dependência:** Sprint 0, 1 e 2 concluídos.

---

### TASK-10 🔴 Criar `routes/neoconsig.php` com 6 endpoints

**Arquivo a criar:** `routes/neoconsig.php`

**Layout de referência:** `layoutsl_atualizado.pdf` — Convênio Prefeitura de São Luís / IPAM
**Arquivos de exemplo analisados:** `MovimentoSecretaria_Servidores_062025_debitos.txt`, `RETFINANCEIRO__2_.TXT`, `RETQUITADAS__5_.TXT`, `CadastroServidores202506.txt`, `ArquivoRetorno202506.txt`

---

#### Endpoints a implementar

| Endpoint | Método | Direção | Função |
|---|---|---|---|
| `POST /neoconsig/importar-debitos` | POST (multipart) | Neo → Convênio | Importa arquivo DEBITOS — cria/atualiza contratos |
| `POST /neoconsig/importar-retorno` | POST (multipart) | Neo → Convênio | Importa RETFINANCEIRO ou RETQUITADAS |
| `GET  /neoconsig/gerar-cadastro` | GET | Convênio → Neo | Gera arquivo CADSERVIDOR para envio ao Neoconsig |
| `GET  /neoconsig/gerar-financeiro` | GET | Convênio → Neo | Gera arquivo FINANCEIRO com descontos da competência |
| `GET  /neoconsig/gerar-retorno-quitadas` | GET | Convênio → Neo | Gera arquivo RETORNOQUITADAS |
| `GET  /neoconsig/gerar-retorno-pendentes` | GET | Convênio → Neo | Gera arquivo RETORNOPENDENTES com motivo de não desconto |

---

#### Estrutura geral dos arquivos (posição fixa)

Todo arquivo tem:
- **Header** (1 linha): col 1 = `1` (fixo), col 2-9 = data DDMMYYYY, col 10-29 = nome do arquivo (20 chars alfanumérico)
- **Detail** (N linhas): col 1 = `2` (fixo), demais campos variáveis por tipo

Regras de formatação:
- **Numérico:** zeros à esquerda, sem pontos/traços/separadores, alinhado à direita
- **Alfanumérico:** maiúsculo sem acentos, espaços à direita, apenas A-Z e 0-9
- **Valor monetário no DÉBITOS:** com vírgula — 12 chars, ex: `000000100,00` = R$100,00
- **Valor monetário no FINANCEIRO/RETORNO:** sem separador — 15 chars, 2 decimais embutidos, ex: `000000000010000` = R$100,00

---

#### POST /neoconsig/importar-debitos

**Arquivo recebido:** `MovimentoSecretaria_Servidores_*.txt` (Neoconsig → Convênio)

Layout do detail (115 chars por linha):

| Campo | Col De | Col Até | Tam | Obs |
|---|---|---|---|---|
| Matrícula | 1 | 8 | 8 | Numérico, zeros à esquerda |
| Vínculo | 9 | 12 | 4 | 2 dígitos + 2 espaços à esquerda quando menor |
| Rubrica | 13 | 16 | 4 | Código da consignatária na folha |
| Espécie | 17 | 36 | 20 | Alfanumérico, espaços à direita |
| Tipo Pagamento | 37 | 37 | 1 | `N` = valor fixo, `E` = percentual |
| Valor Parcela | 38 | 49 | 12 | Com vírgula: `000000100,00` = R$100,00 |
| DTINI | 50 | 55 | 6 | Início da operação — formato `MMAAAA` |
| DTFIM | 56 | 61 | 6 | Fim da operação — formato `MMAAAA` |
| Parcela (nº/total) | 62 | 81 | 20 | Numérico, espaços à direita |
| Data Início Contrato | 82 | 100 | 19 | Formato `DD/MM/AA HH:MM:SS` |
| ID Operação | 101 | 115 | 15 | ID Neoconsig — chave de idempotência |

**Lógica:**
1. Validar header (col 1 = `1`)
2. Para cada linha detail:
   - Extrair matrícula (cols 1-8), localizar funcionário em `FUNCIONARIO`
   - Extrair rubrica (cols 13-16), localizar convênio em `CONSIG_CONVENIO` pela rubrica
   - Extrair ID operação (cols 101-115) — se já existe em `CONSIG_CONTRATO.NEOCONSIG_ID_OPERACAO`, **atualizar**; senão, **criar**
   - Converter valor: remover zeros à esquerda, trocar vírgula por ponto
   - Converter DTINI/DTFIM: `MMAAAA` → `YYYY-MM-01`
   - Converter data início: `DD/MM/AA HH:MM:SS` → `Y-m-d`
   - Gravar em `CONSIG_CONTRATO` com `STATUS_AUTORIZACAO = 'APROVADO'` e `CONTRATO_ATIVO = 1`
3. Retornar JSON com: `importados`, `erros[]`, `total_linhas`
4. Rodar em `DB::transaction()` — rollback total se erro crítico

**Campos a adicionar em `CONSIG_CONTRATO`** (migration necessária antes de implementar):
- `NEOCONSIG_ID_OPERACAO` varchar(15) nullable — chave de idempotência na importação
- `NEOCONSIG_VINCULO` varchar(4) nullable — vínculo conforme arquivo Neoconsig

---

#### POST /neoconsig/importar-retorno

**Arquivos recebidos:** `RETFINANCEIRO_*.TXT` e `RETQUITADAS_*.TXT` (Neoconsig → Convênio)

Detectar o tipo pelo nome do arquivo no header (cols 10-29): contém `QUITADAS` → tipo quitadas; caso contrário → tipo financeiro.

Layout do detail:

| Campo | Col De | Col Até | Tam | Obs |
|---|---|---|---|---|
| Inicial | 1 | 1 | 1 | Fixo `2` |
| Sequencial | 2 | 10 | 9 | Iniciado em 1 |
| Competência | 11 | 16 | 6 | Formato `MMYYYY` |
| Matrícula | 17 | 30 | 14 | Alfanumérico, pode ter prefixo `I` ou `P` |
| Rubrica | 31 | 34 | 4 | Código da verba |
| Valor Parcela | 35 | 49 | 15 | Sem separador, 2 decimais embutidos |
| Número da Parcela | 50 | 51 | 2 | Parcela atual |
| ID Operação | 52 | 66 | 15 | ID Neoconsig |
| Motivo não desconto | 67 | 106 | 40 | Só existe no RETPENDENTES |

**Lógica (RETFINANCEIRO):**
1. Para cada linha: localizar contrato pelo ID operação (cols 52-66)
2. Converter valor: dividir por 100 (2 decimais embutidos)
3. Registrar parcela paga em `CONSIG_PARCELA`
4. Incrementar `PARCELA_ATUAL` em `CONSIG_CONTRATO`

**Lógica adicional (RETQUITADAS):**
- Após registrar a parcela, marcar `CONSIG_CONTRATO.CONTRATO_ATIVO = 0` (quitado)

**Lógica adicional (RETPENDENTES — se detectado pelo header):**
- Registrar motivo (cols 67-106) em `CONSIG_OCORRENCIA` com tipo `NAO_DESCONTADO`

---

#### GET /neoconsig/gerar-cadastro

**Arquivo gerado:** `CADSERVIDOR` (Convênio → Neoconsig)

Layout do detail (523 chars por linha):

| Campo | Col De | Col Até | Tam | Fonte |
|---|---|---|---|---|
| Inicial | 1 | 1 | 1 | Fixo `2` |
| Sequencial | 2 | 10 | 9 | Incrementar por linha |
| Código Secretaria | 11 | 25 | 15 | `UNIDADE_CODIGO` ou `LOTACAO_CODIGO` |
| Matrícula | 26 | 39 | 14 | `FUNCIONARIO_MATRICULA` + vínculo; prefixo `I`/`P` se inativo/pensionista |
| CPF | 40 | 50 | 11 | `PESSOA_CPF` sem pontos/traços |
| Nome | 51 | 110 | 60 | `PESSOA_NOME` maiúsculo sem acentos |
| Email | 111 | 160 | 50 | `USUARIO_EMAIL` ou `FUNCIONARIO_EMAIL` |
| Logradouro | 161 | 210 | 50 | Endereço do servidor |
| Número | 211 | 220 | 10 | Número do endereço |
| Complemento | 221 | 240 | 20 | Complemento |
| Bairro | 241 | 290 | 50 | Bairro |
| Município | 291 | 340 | 50 | Cidade |
| UF | 341 | 342 | 2 | Estado |
| CEP | 343 | 350 | 8 | Sem pontos/traço |
| Situação | 351 | 370 | 20 | `Ativo`, `Inativo`, `Exonerado`, `Falecido` |
| Categoria | 371 | 390 | 20 | `CLT`, `Temporario`, `Comissionado` |
| Código Lotação | 391 | 405 | 15 | `LOTACAO_CODIGO` ou sigla |
| Admissão | 406 | 413 | 8 | `FUNCIONARIO_DATA_ADMISSAO` → DDMMYYYY |
| Afastamento | 414 | 421 | 8 | Data afastamento se houver, senão zeros |
| Código Banco | 422 | 424 | 3 | Código BACEN do banco |
| Código Agência | 425 | 430 | 6 | Agência sem dígito |
| Conta | 431 | 455 | 25 | Conta com dígito, sem traço |
| Data Nascimento | 456 | 463 | 8 | `PESSOA_DATA_NASCIMENTO` → DDMMYYYY |
| Telefone | 464 | 483 | 20 | DDD + número |
| Celular | 484 | 503 | 20 | DDD + celular |
| Fonte Pagadora | 504 | 523 | 20 | `PREFEITURA MUNICIPAL SL` (fixo ou config) |

**Lógica:**
1. Aceitar parâmetro `?competencia=MMYYYY` (default: competência atual)
2. Aceitar parâmetro `?situacao=` (default: apenas ativos)
3. Consultar `FUNCIONARIO` JOIN `PESSOA` — montar linha de exatamente 523 chars por servidor
4. Retornar como download: `Content-Disposition: attachment; filename="CADSERVIDOR{MMYYYY}.txt"`
5. Encoding: UTF-8 convertido para ASCII sem acentos (`iconv UTF-8 ASCII//TRANSLIT`)

---

#### GET /neoconsig/gerar-financeiro

**Arquivo gerado:** `FINANCEIRO` (Convênio → Neoconsig)

Layout do detail (66 chars por linha):

| Campo | Col De | Col Até | Tam | Fonte |
|---|---|---|---|---|
| Inicial | 1 | 1 | 1 | Fixo `2` |
| Sequencial | 2 | 10 | 9 | Incrementar por linha |
| Competência | 11 | 16 | 6 | Formato `MMYYYY` |
| Matrícula | 17 | 30 | 14 | `FUNCIONARIO_MATRICULA` + vínculo |
| Rubrica | 31 | 34 | 4 | `RUBRICA_FOLHA` do contrato |
| Valor da Parcela | 35 | 49 | 15 | Sem separador, 2 decimais embutidos: R$100,00 → `000000000010000` |
| Número da Parcela | 50 | 51 | 2 | `PARCELA_ATUAL` do contrato |
| ID Operação | 52 | 66 | 15 | `NEOCONSIG_ID_OPERACAO` |

**Lógica:**
1. Aceitar parâmetro `?competencia=MMYYYY` (default: competência atual)
2. Consultar `CONSIG_CONTRATO` JOIN `FUNCIONARIO` — somente contratos ativos (`CONTRATO_ATIVO = 1`) com `NEOCONSIG_ID_OPERACAO` preenchido
3. Converter valor: multiplicar por 100, remover decimais, zero-pad 15 chars
4. **Margem negativa ou zero:** enviar `000000000000000` (conforme nota do layout)
5. Retornar como download: `Content-Disposition: attachment; filename="FINANCEIRO{MMYYYY}.txt"`

---

#### GET /neoconsig/gerar-retorno-quitadas e gerar-retorno-pendentes

Mesmo layout do FINANCEIRO (cols 1-66), com campo adicional no pendentes:
- Col 67-106 (40 chars): motivo do não desconto — buscar de `CONSIG_OCORRENCIA.OCORRENCIA_DESCRICAO`

**Lógica (quitadas):** contratos com `CONTRATO_ATIVO = 0` quitados na competência
**Lógica (pendentes):** contratos ativos com parcela sem baixa na competência + motivo registrado em `CONSIG_OCORRENCIA`

---

#### Mapeamento de códigos de verba

O campo **Código Verba** aparece no arquivo de margem (layout pág. 3):

| Código | Tipo de margem |
|---|---|
| `1111` | Margem empréstimo (30%) |
| `2222` | Margem cartão de crédito (10%) |
| `3333` | Margem entidade sindical |

Este mapeamento deve ser considerado ao gerar o FINANCEIRO e ao importar retornos.

---

**Após criar o arquivo, registrar no `web.php`** dentro do grupo `api/v3 + auth`:
```php
require __DIR__ . '/neoconsig.php';
```

---

### Checklist Sprint 3

```
[ ] Migration: adicionar NEOCONSIG_ID_OPERACAO e NEOCONSIG_VINCULO em CONSIG_CONTRATO
[ ] TASK-10 — routes/neoconsig.php criado com 6 endpoints
[ ] Registrado em web.php

TESTAR:
  - POST /neoconsig/importar-debitos com arquivo de teste → contratos criados
  - POST /neoconsig/importar-retorno RETFINANCEIRO → parcelas registradas
  - POST /neoconsig/importar-retorno RETQUITADAS → contratos marcados inativos
  - GET /neoconsig/gerar-cadastro → download CADSERVIDOR com 523 chars por linha
  - GET /neoconsig/gerar-financeiro → download FINANCEIRO com 66 chars por linha
  - GET /neoconsig/gerar-retorno-quitadas → download correto
  - GET /neoconsig/gerar-retorno-pendentes → download com motivo na col 67-106
```

---

## SPRINT 3.5 — TABELA SALARIAL E CICLO DE PROGRESSÃO

**Objetivo:** Fechar o gap operacional do módulo de progressão funcional — sem a tabela salarial populada, o motor de elegibilidade não consegue determinar a próxima referência de nenhum servidor, tornando o Sprint 2 (progressão) inoperante na prática.
**Estimativa:** 1 sessão de desenvolvimento (2–3 horas)
**Dependência:** Sprint 0, 1 e 2 concluídos.

---

### Contexto — o gap identificado em auditoria (16/03/2026)

Auditoria direta do código revelou que o ciclo de progressão funcional tem **três atores** no banco:

| Tabela | Quem popula | Interface existente |
|--------|-------------|---------------------|
| `CARGO` + `CARGO_SALARIO` | `CargosSalariosView.vue` | ✅ CRUD completo |
| `CARREIRA` + `PROGRESSAO_CONFIG` | `ProgressaoAdminView.vue` aba Config | ✅ Parcial (cria carreira, edita parâmetros) |
| `TABELA_SALARIAL` | **Ninguém** | ❌ Sem interface — só via banco direto |

Sem `TABELA_SALARIAL` populada, o helper `$getVencBase` cai no fallback (`CARGO_SALARIO`), e mais crítico: o helper `$avaliarEleg` não consegue encontrar a próxima referência, retornando `proxima_referencia: null` para todos os servidores. O painel de elegíveis fica sempre vazio mesmo com servidores que cumprem o interstício.

---

### TASK-13 🔴 Corrigir migration SQLite — adicionar `CARGO_SALARIO` à tabela `CARGO`

**Arquivo a criar:** `database/migrations/2026_03_16_000003_add_salario_to_cargo.php`

**Problema:** A migration `2026_02_23_000001_create_remaining_domain_tables.php` criou a tabela `CARGO` sem a coluna `CARGO_SALARIO`. Em produção (SQL Server) essa coluna existe no banco legado. Em dev (SQLite) a coluna nunca foi adicionada, causando exceção no `select()` de `progressao_funcional.php` → fallback para `['fallback' => true]` → Vue sempre renderiza dados mock.

**Conteúdo da migration:**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('CARGO') && !Schema::hasColumn('CARGO', 'CARGO_SALARIO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->decimal('CARGO_SALARIO', 12, 2)->nullable()->default(0);
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('CARGO') && Schema::hasColumn('CARGO', 'CARGO_SALARIO')) {
            Schema::table('CARGO', function (Blueprint $table) {
                $table->dropColumn('CARGO_SALARIO');
            });
        }
    }
};
```

**Após criar:** rodar `php artisan migrate`

---

### TASK-14 🔴 Adicionar aba "Tabela Salarial" no `ProgressaoAdminView.vue`

**Arquivo:** `resources/gente-v3/src/views/rh/ProgressaoAdminView.vue`

**Problema:** Sem interface para popular a `TABELA_SALARIAL`, o RH não consegue cadastrar a escada de classes/referências/vencimentos que o motor de progressão percorre. O endpoint `POST /api/v3/progressao-funcional/tabela-salarial` já existe no backend — falta apenas a UI.

**O que adicionar:** uma 5ª aba "Tabela Salarial" no `ProgressaoAdminView.vue`, após a aba "Configuração", com:

1. **Seletor de carreira** — dropdown alimentado pelo endpoint `GET /api/v3/progressao-funcional/carreiras`
2. **Grade da tabela salarial** — exibe as linhas de `TABELA_SALARIAL` filtradas pela carreira selecionada, colunas: Classe, Referência, Ordem, Vencimento Base, Titulação, Ações (excluir)
3. **Formulário de nova linha** — campos: Classe (texto, ex: A/B/C), Referência (texto, ex: I/II/III), Ordem (número inteiro — define a sequência de progressão), Vencimento Base (decimal), Titulação (select opcional: médio/graduação/especialização/mestrado)
4. **Botão excluir** por linha — chama `DELETE /api/v3/progressao-funcional/tabela-salarial/{id}`

**Abas atualizadas:**
```js
const abas = [
  { id: 'elegiveis',      ico: '✅', label: 'Elegíveis' },
  { id: 'todos',          ico: '👥', label: 'Todos' },
  { id: 'impacto',        ico: '💰', label: 'Impacto LRF' },
  { id: 'config',         ico: '⚙️',  label: 'Configuração' },
  { id: 'tabela_salarial',ico: '📊', label: 'Tabela Salarial' },  // NOVA
]
```

**Endpoints utilizados (já existem no backend):**
- `GET  /api/v3/progressao-funcional/carreiras` → lista carreiras + tabela salarial atual
- `POST /api/v3/progressao-funcional/tabela-salarial` → cria linha (carreira_id, classe, referencia, ordem, vencimento, titulacao)
- `DELETE /api/v3/progressao-funcional/tabela-salarial/{id}` → remove linha

**Nenhum endpoint novo precisa ser criado no backend.**

---

### TASK-15 🟠 Vincular servidor a carreira/classe/referência no cadastro

**Arquivo:** `resources/gente-v3/src/views/rh/PerfilFuncionarioView.vue` (ou `FuncionariosView.vue`)

**Problema:** As colunas `CARREIRA_ID`, `FUNCIONARIO_CLASSE` e `FUNCIONARIO_REFERENCIA` existem na tabela `FUNCIONARIO` (adicionadas pela migration de progressão), mas não há campo no formulário de cadastro do servidor para preenchê-las. Sem esses valores, o motor de progressão não consegue localizar o servidor na tabela salarial.

**O que adicionar:** uma seção "Progressão Funcional" no formulário de edição do servidor (`PerfilFuncionarioView.vue`), com:

1. **Carreira** — select alimentado por `GET /api/v3/progressao-funcional/carreiras`
2. **Classe** — input texto (ex: A, B, C) ou select dinâmico baseado na carreira selecionada
3. **Referência** — select dinâmico baseado nas referências disponíveis para a classe selecionada (filtrado de `TABELA_SALARIAL`)
4. **Data da última progressão** — date input para `FUNCIONARIO_DATA_ULTIMA_PROGRESSAO` (base do cálculo do interstício)
5. **Em estágio probatório** — checkbox para `FUNCIONARIO_ESTAGIO_PROBATORIO`

O `PUT /api/v3/funcionarios/{id}` já aceita esses campos via `$func->fill()` — apenas precisa que sejam incluídos no payload enviado pelo Vue.

---

### Checklist Sprint 3.5

```
[ ] TASK-13 — Migration criada e php artisan migrate executado (CARGO_SALARIO no SQLite)
[ ] TASK-14 — Aba "Tabela Salarial" adicionada no ProgressaoAdminView.vue
[ ] TASK-15 — Seção de progressão adicionada no formulário do servidor

TESTAR:
  - GET /api/v3/progressao-funcional → retorna fallback: false (não mais fallback: true)
  - ProgressaoFuncionalView.vue exibe dados reais do banco (não mock)
  - Cadastrar carreira + tabela salarial → motor encontra próxima referência
  - Vincular servidor a carreira/classe/referência → aparece como elegível no painel admin
  - ProgressaoAdminView aba Elegíveis lista servidores reais quando interstício cumprido
```

---

## SPRINT 3 — ENGINE DE FOLHA DE PAGAMENTO

**Objetivo:** Transformar o processamento de folha de passivo (recebe proventos prontos e aplica descontos) para ativo (calcula proventos automaticamente por tipo de vínculo). Contempla também o tratamento correto do concursado em cargo em comissão.
**Estimativa:** 2–3 sessões de desenvolvimento (8–12 horas)
**Dependência:** Sprints 0, 1, 2 e 3.5 concluídos — especialmente a tabela salarial populada (TASK-14) e o vínculo do servidor definido (TASK-15).

---

### Contexto — por que o engine atual é insuficiente

O `POST /api/v3/folhas/calcular` atual faz apenas:
```
LIQUIDO = PROVENTOS - DESCONTOS
DESCONTOS += parcelas de consignação
```

O valor de `PROVENTOS` é inserido manualmente ou importado de sistema externo. O sistema **não calcula** o salário bruto — ele apenas **processa** o que recebe. Isso é aceitável para MVP, mas impede automação e gera risco de erro humano nas composições de salário mais complexas.

---

### TASK-16 🔴 Tipagem de vínculo para distinguir modalidades remuneratórias

**Arquivo:** `database/migrations/2026_03_16_000004_add_tipo_to_vinculo.php`

**Contexto legal (pesquisa 16/03/2026):** Quando um servidor efetivo assume cargo em comissão, existem três modalidades distintas de remuneração — cada uma com composição diferente na folha:

| Modalidade | Composição dos Proventos | Impacto na Progressão | Base RPPS/IPAM |
|------------|--------------------------|----------------------|----------------|
| **M1 — CC puro** (efetivo afastado) | Remuneração do CC + vantagens permanentes do efetivo | ❌ Interstício suspenso | Vencimento do efetivo (não do CC) |
| **M2 — Opção pelo efetivo** | Vencimento efetivo + % do CC (percentual definido pelo Estatuto Municipal) | ✅ Interstício continua | Vencimento do efetivo |
| **M3 — Função de Confiança / FG** | Vencimento efetivo + valor fixo da FG | ✅ Interstício continua | Vencimento do efetivo |
| **Comissionado puro** (sem vínculo efetivo) | Apenas remuneração do CC | ❌ Sem progressão (não tem cargo efetivo) | Sobre o CC |

A tabela `VINCULO` atualmente é só um rótulo de nome. Precisa de um campo `VINCULO_TIPO` para o motor de folha e de progressão saber como tratar o servidor.

**Migration a criar:**
```php
Schema::table('VINCULO', function (Blueprint $table) {
    if (!Schema::hasColumn('VINCULO', 'VINCULO_TIPO')) {
        // efetivo | comissao_puro | efetivo_cc_m1 | efetivo_cc_m2 | funcao_confianca
        $table->string('VINCULO_TIPO', 30)->nullable()->default('efetivo');
    }
});
```

**Impacto no motor de progressão (`$avaliarEleg`):** Atualizar o bloqueio de `CARREIRA_REGIME = comissionado` para considerar apenas `VINCULO_TIPO IN ('comissao_puro')` como bloqueante. Os tipos `efetivo_cc_m1`, `efetivo_cc_m2` e `funcao_confianca` devem continuar elegíveis à progressão do cargo efetivo.

---

### TASK-17 🔴 Motor de cálculo de proventos por tipo de vínculo

**Arquivo:** `routes/folha.php` — novo endpoint `POST /api/v3/folhas/calcular-proventos`

**O que o motor precisa calcular por servidor, baseado no `VINCULO_TIPO`:**

```
efetivo:
  PROVENTOS = vencimento_base (TABELA_SALARIAL)
            + anuênios (CONFIG_ANUENIO_PCT × anos_serviço × vencimento_base)
            + adicionais permanentes (triênios, quinquênios, titulação)

efetivo_cc_m2 (opção pelo efetivo):
  PROVENTOS = vencimento_base (TABELA_SALARIAL do cargo efetivo)
            + anuênios
            + gratificação_cc (FUNCAO.GRATIFICACAO ou % definido no Estatuto)

efetivo_cc_m1 (afastado para CC — recebe o CC):
  PROVENTOS = remuneracao_cc (FUNCAO.GRATIFICACAO ou tabela do CC)
            + vantagens_permanentes_efetivo (anuênios calculados sobre o efetivo)

funcao_confianca:
  PROVENTOS = vencimento_base (TABELA_SALARIAL)
            + valor_FG (FUNCAO.GRATIFICACAO)
            + anuênios

comissao_puro:
  PROVENTOS = remuneracao_cc apenas
```

**Pré-requisito para este motor funcionar:** TASK-14 (tabela salarial populada) e TASK-15 (servidor vinculado a carreira/classe/referência) precisam estar concluídas.

**Base RPPS/IPAM:** Para todos os tipos exceto `comissao_puro`, a contribuição previdenciária incide sobre o vencimento do cargo efetivo, não sobre a remuneração do CC. O motor deve registrar separadamente `BASE_PREVIDENCIA` em `DETALHE_FOLHA`.

---

### TASK-18 🟠 Adicionar `BASE_PREVIDENCIA` e `DETALHE_FOLHA_LIQUIDO` ao schema

**Arquivo:** `database/migrations/2026_03_16_000005_add_previdencia_to_detalhe_folha.php`

```php
Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
    if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_FOLHA_LIQUIDO')) {
        $table->decimal('DETALHE_FOLHA_LIQUIDO', 15, 2)->nullable()->default(0);
    }
    if (!Schema::hasColumn('DETALHE_FOLHA', 'BASE_PREVIDENCIA')) {
        // Base sobre a qual incide a contribuição ao IPAM
        $table->decimal('BASE_PREVIDENCIA', 15, 2)->nullable()->default(0);
    }
    if (!Schema::hasColumn('DETALHE_FOLHA', 'VINCULO_TIPO_SNAPSHOT')) {
        // Snapshot do tipo de vínculo no momento do cálculo (imutabilidade histórica)
        $table->string('VINCULO_TIPO_SNAPSHOT', 30)->nullable();
    }
});
```

---

### TASK-19 🟠 Tela de configuração do Estatuto Municipal — parâmetros de composição salarial

**Arquivo:** `resources/gente-v3/src/views/config/ParametroFinanceiroView.vue` (ou nova aba em `ConfiguracaoSistemaView.vue`)

**O que configurar:**
- Percentual da gratificação M2 (% do CC que vai para o efetivo em opção) — default 55% conforme Lei 8.911/94, mas o Estatuto Municipal de São Luís pode definir diferente
- Percentual de anuênio por ano (já existe em `PROGRESSAO_CONFIG` — sincronizar)
- Se triênios e quinquênios são calculados automaticamente ou inseridos manualmente

**Endpoint backend necessário:** `GET/PUT /api/v3/configuracao/parametros-folha`

---

### Checklist Sprint 3

```
[ ] TASK-16 — Migration VINCULO_TIPO criada e aplicada
[ ] TASK-16 — Motor de progressão atualizado (não bloquear M2, M3, funcao_confianca)
[ ] TASK-17 — POST /api/v3/folhas/calcular-proventos implementado
[ ] TASK-18 — Migration BASE_PREVIDENCIA + DETALHE_FOLHA_LIQUIDO aplicada
[ ] TASK-19 — Parâmetros de composição salarial configuráveis

TESTAR:
  - Servidor efetivo puro: proventos calculados = base + anuênios
  - Servidor em CC M2: proventos = efetivo + gratificação % do CC
  - Servidor em CC M1: proventos = CC + vantagens permanentes
  - Servidor em FG: proventos = efetivo + valor FG
  - Motor de progressão: M2/M3 continuam elegíveis, M1 suspende interstício, comissao_puro bloqueado
  - BASE_PREVIDENCIA = vencimento_efetivo para todos exceto comissao_puro
  - RPPS/IPAM: desconto previdenciário sobre BASE_PREVIDENCIA correta
```

---

## SPRINT 4 — BUILD E DEPLOY

**Objetivo:** Preparar o sistema para deploy em VPS de produção.
**Estimativa:** 2–3 horas
**Dependência:** Todos os sprints anteriores concluídos e validados.

---

### TASK-11 🟠 Build e otimização

```bash
composer install --no-dev

# Verificar BOM no web.php (PowerShell)
Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
# Esperado: 60 63 112  (bytes de '<?p')
# Se: 239 187 191 → remover BOM

npm install
npm run build

php artisan config:cache
php artisan view:cache
# NÃO rodar route:cache — web.php tem closures inline

php artisan cache:clear
php artisan config:clear
```

---

### TASK-12 🟡 Checklist de segurança pré-deploy

```
[ ] APP_ENV=production no .env de produção
[ ] APP_DEBUG=false no .env de produção
[ ] APP_KEY gerado e presente
[ ] /dev/* retorna 404 (isLocal() = false em produção)
[ ] CORS allowed_origins atualizado com o domínio real (remover localhost)
[ ] SESSION_DOMAIN atualizado para o domínio real
[ ] PONTO_APP_JWT_SECRET definido (já está no .env)
[ ] Credenciais SMTP Brevo presentes (já estão no .env)
[ ] DB_CONNECTION apontando para SQL Server (não SQLite) em produção
[ ] php artisan migrate rodado no banco de produção
[ ] php artisan db:seed --class=MigrarSenhasMd5Seeder rodado no banco de produção
[ ] Storage link criado: php artisan storage:link
[ ] Permissões de escrita em storage/ e bootstrap/cache/
```

---

### Checklist Sprint 4

```
[ ] TASK-11 — Build executado sem erros
[ ] TASK-12 — Checklist de segurança revisado

TESTAR EM PRODUÇÃO:
  - Login funcionando no domínio real
  - /dev/ping-db retorna 404
  - PDF holerite gerado corretamente
  - Consignação: margem calculada corretamente (30% empréstimo / 10% cartão)
  - Neoconsig: importação e geração de arquivos funcionando
  - Folha: POST /calcular aplica parcelas
```

---

## ROADMAP — O QUE FALTA PARA CHEGAR AO SPRINT 3 (ENGINE DE FOLHA)

O Sprint 3 (engine de cálculo de proventos) tem **pré-requisitos duros** — sem eles o motor não tem dados para calcular. O caminho crítico é:

```
Sprint 0 ──────────────────────────────────────────────────────────────────┐
  TASK-01  Login fora do isLocal()           [BLOQUEANTE — sem isso nada funciona]
  TASK-02  CORS credentials=true             [BLOQUEANTE — sem isso sessão não persiste]
  TASK-03  APP_URL + SESSION_DOMAIN          [necessário para sessão em dev]
  TASK-04  Vue Router redirect inteligente   [UX — evita loop /login]
  TASK-05  use Carbon + remover BOM          [corrige 500 na progressão]
           │
           ▼
Sprint 1 ──────────────────────────────────────────────────────────────────┐
  TASK-06  php artisan migrate               [garante CONSIG_OCORRENCIA, RPPS_CONFIG]
  TASK-07  Seeder senhas MD5 → bcrypt        [usuários conseguem logar]
           │
           ├─────────────────────────────────────────────────────────────┐
           ▼                                                             ▼
Sprint 2                                                          Sprint 3.5
  TASK-08  Margem cartão 10%                               TASK-13  CARGO_SALARIO migration
  TASK-09  View Blade holerite PDF                         TASK-14  Aba Tabela Salarial (UI)
           │                                               TASK-15  Vínculo servidor → carreira
           │                                                         │
           ├──────────────────(paralelo possível)───────────────────┘
           │
           ▼
Sprint 3 — NEOCONSIG (escopo contratual)
  TASK-10  6 endpoints Neoconsig             [importação/geração arquivos posição fixa]
           │
           ▼
Sprint 3 — ENGINE DE FOLHA (este sprint — TASK-16 a 19)
  PRÉ-REQUISITO DURO: Sprint 3.5 concluído (tabela salarial + vínculo do servidor)
  TASK-16  VINCULO_TIPO migration + motor progressão corrigido
  TASK-17  Motor de cálculo proventos por tipo de vínculo
  TASK-18  BASE_PREVIDENCIA + DETALHE_FOLHA_LIQUIDO no schema
  TASK-19  Parâmetros de composição salarial configuráveis
           │
           ▼
Sprint 4 — BUILD E DEPLOY
  TASK-11  Build produção
  TASK-12  Checklist de segurança
```

**Resumo do caminho crítico:** Sprint 0 → Sprint 1 → Sprint 3.5 → Sprint 3 (engine)

Sprint 2 (margem + PDF) e Sprint 3 Neoconsig podem rodar em **paralelo** após Sprint 1, pois não têm dependência entre si nem com o Sprint 3.5.

---

## RESUMO EXECUTIVO

| Sprint | Tasks | Prioridade | Dependências |
|--------|-------|------------|--------------|
| **Sprint 0** — Corrigir login | TASK-01 a 05 | 🔴 Bloqueante | Nenhuma — fazer primeiro |
| **Sprint 1** — Banco de dados | TASK-06, 07 | 🟠 Alta | Sprint 0 validado |
| **Sprint 2** — Margem + Holerite PDF | TASK-08, 09 | 🟠 Alta | Sprint 1 — paralelo com 3.5 e Neoconsig |
| **Sprint 3 Neoconsig** | TASK-10 | 🔴 Escopo contratual | Sprint 1 — paralelo com 2 e 3.5 |
| **Sprint 3.5** — Tabela Salarial / Progressão | TASK-13 a 15 | 🟠 Alta | Sprint 1 — **pré-requisito do engine** |
| **Sprint 3 Engine** — Folha de Pagamento | TASK-16 a 19 | 🟠 Alta | Sprint 3.5 obrigatório |
| **Sprint 4** — Deploy | TASK-11, 12 | 🟡 Final | Todos os sprints anteriores |

---

## REFERÊNCIA: O QUE JÁ ESTAVA CORRETO

| Item | Arquivo | Status |
|------|---------|--------|
| BUG-02 — `hasAccess()` usa `<=` corretamente | `router/index.js` | ✅ Correto |
| BUG-03 — Cache TTL 5min no `fetchUser()` | `store/auth.js` | ✅ Correto |
| BUG-04 — `LoginView.vue` usa axios (não fetch manual) | `LoginView.vue` | ✅ Correto |
| BUG-06 — `HISTORICO_FUNCIONAL` (não `HISTORARIO`) | `progressao_funcional.php` | ✅ Correto |
| PERF-01 — N+1 progressão com pré-fetch `whereIn` | `progressao_funcional.php` | ✅ Correto |
| CONSIG-01 — Margem separada 30% empréstimo / **10% cartão** | `consignacao.php` | ✅ Confirmado 12/03/2026 — Decreto 57.477/2021 Art. 4º |
| CONSIG-02 — Fluxo autorização + tabela CONSIG_OCORRENCIA | `consignacao.php` | ✅ Correto |
| CONSIG-03 — Desconto automático de parcelas | `folha.php` | ✅ Correto |
| GAP-02 — Endpoints de folha (`/folhas`, `/calcular`) | `folha.php` | ✅ Correto |
| DomPDF — `barryvdh/laravel-dompdf` instalado | `composer.json` | ✅ Correto |
| PONTO_APP_JWT_SECRET no `.env` | `.env` | ✅ Definido |
| Brevo SMTP no `.env` | `.env` | ✅ Configurado |
| Rotas ERP no `router/index.js` | `router/index.js` | ✅ Todas presentes |
| Todos os `require` de módulos no `web.php` | `web.php` | ✅ Corretos |
| SEC-02 — `isLocal()` protegendo rotas `/dev/*` | `web.php` | ✅ Correto |

---

*Documento gerado por varredura direta do código em 12/03/2026. Atualizado 12/03/2026 — margem cartão confirmada 10% via Decreto Municipal nº 57.477/2021. TASK-10 detalhada após análise do layout Neoconsig e arquivos de exemplo. Atualizado 16/03/2026 — Sprint 3.5 adicionado (TASK-13 a 15): gap de tabela salarial e vínculo do servidor. Sprint 3 Engine de Folha detalhado (TASK-16 a 19): motor de cálculo de proventos por tipo de vínculo, tratamento legal das 3 modalidades de remuneração para concursado em CC (pesquisa legislativa 16/03/2026 — Lei 8.112/90, Lei 8.911/94, Decreto Municipal 57.477/2021). Roadmap de dependências adicionado.*
*Projeto: SISGEP — Sistema Integrado de Gestão de Pessoas — PMSLz*
*Tecnologia: RR Tecnol*
