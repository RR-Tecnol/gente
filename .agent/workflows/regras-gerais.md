---
description: Regras de desenvolvimento e documentação do GENTE v3
---

# GENTE — Regras de Desenvolvimento do Agente
**Versão:** 4.4 — Seção 17 atualizada em 23/03/2026 (auditoria Gravity — sprints 0–4 concluídas)
                 Seções 22–25 adicionadas em 23/03/2026 (sprint segurança + gaps estratégicos)
**Aplicação:** Lido automaticamente a cada sessão. O agente DEVE verificar este arquivo antes de iniciar qualquer tarefa.

---

## 0. LEITURA DE CONTEXTO

### Sempre ler (leve, estado atual):
1. **Este arquivo** — regras de código e arquitetura
2. **`docs/MAPA_ESTADO_REAL.md`** — estado atual confirmado do sistema, varrido diretamente do código (problemas ativos, status de cada módulo, o que está correto). É a fonte de verdade sobre o estado real — prevalece sobre qualquer outro documento em caso de conflito.
3. **`docs/PLANO_SPRINTS.md`** — somente se a tarefa faz parte de uma sprint ativa

### Ler somente se relevante para a tarefa:
- `docs/historico-problemas.md` — se a tarefa envolve um bug já registrado
- `docs/historico-estrategias-erradas.md` — se a tarefa já foi tentada antes sem sucesso
- `docs/PLANO_MESTRE_V3.md` — somente ao criar módulo completamente novo ou ao planejar sprint ERP

### Sobre documentação pós-task:
- Usar `.agent/workflows/documentar-solucao.md` **somente** em tasks que alteraram comportamento funcional ou estrutura do sistema
- Ajustes de CSS, edições de documentação e refatorações simples: **não documentar**

### Outros workflows disponíveis:

| Arquivo | Quando usar |
|---------|-------------|
| `.agent/workflows/implementar-modulo.md` | Ao criar qualquer backend + frontend novo |
| `.agent/workflows/sprint-seguranca.md` | Ao executar Sprint 0 (SEC-01 a SEC-05) |
| `.agent/workflows/resolver-bug.md` | Ao diagnosticar e corrigir qualquer bug |
| `.agent/workflows/documentar-solucao.md` | Ao concluir qualquer tarefa — templates de registro |

### Documentos estratégicos (ler antes de planejar novas sprints):

| Arquivo | Conteúdo |
|---------|----------|
| `docs/SPRINT_SEGURANCA.md` | 10 tasks de segurança — executar ANTES do deploy VPS |
| `docs/GAPS_ESTRATEGICOS.md` | Gaps críticos: 13º, férias, rescisão, DIRF, RAIS, painel executivo, multi-tenancy |
| `docs/SPRINT_EXECUCAO_V3.md` | Spec completa Blocos A–F para o Antygravity |
| `docs/PLANO_MESTRE_V3.md` | Visão geral do projeto, estado atual, sequência de execução |

---

## 1. REGRA ABSOLUTA — LEGADO

> **O GENTE é um sistema NOVO. Não existe código legado que deva ser preservado ou contornado.**

- `CertidaoController`, `CartorioController`, `CertidaoMunicipioController` — **NÃO referenciar, herdar ou invocar** em código novo.
- Erros de lint em arquivos não tocados na sprint → registrar em `docs/historico-problemas.md` como "erro pré-existente não relacionado" e seguir.
- **NUNCA** adicionar `require` de arquivos legados em `web.php` como efeito colateral.
- Se a única solução passar por código legado, PARAR e reportar antes de prosseguir.

**Sinal de alerta:** se o agente pensar "vou usar o CartorioController porque já tem essa lógica" — documentar em `docs/historico-estrategias-erradas.md` e implementar do zero.

---

## 2. REGRA DE ARQUIVOS DE ROTA

### Estrutura correta:
```
routes/
  web.php        ← SPA + auth + require de módulos
  modulo.php     ← apenas Route::get/post/patch/delete diretos
```

### ❌ Proibido em arquivos require'd:
```php
Route::middleware(['auth'])->prefix('api/v3')->group(function () {
    // gera path /api/v3/api/v3/rota — middleware duplicado
});
```

### ✅ Correto:
```php
// Herdar o contexto do grupo pai do web.php diretamente:
Route::get('/minha-rota', function () { ... });
Route::post('/minha-rota', function () { ... });
```

**Erro real já ocorrido em:** `diarias.php` e `rpps.php`.

---

## 3. REGRA DE FRONTEND — NUNCA USAR fetch() NATIVO

```js
// ❌ PROIBIDO:
const r = await fetch('/api/v3/rota', {
    headers: { 'X-CSRF-TOKEN': document.cookie.match(...)[1] },
    body: JSON.stringify(payload)
})

// ✅ OBRIGATÓRIO:
import api from '@/plugins/axios'
const { data } = await api.post('/api/v3/rota', payload)
```

O plugin axios tem CSRF, interceptors 401 e base URL configurados. O fetch nativo não.

**Erro real já ocorrido em:** `ConsignacaoView.vue`, `ExoneracaoView.vue`, `HoraExtraView.vue`.

---

## 4. REGRA DE AUTOCOMPLETE DE SERVIDOR

- **Proibido:** `/api/v3/exoneracao/buscar` fora de `ExoneracaoView.vue`
- **Obrigatório:** `/api/v3/servidores/buscar` em todos os outros módulos

**Erro real já ocorrido em:** `DiariasView.vue`.

---

## 5. REGRA DE DADOS MOCK

Mock é permitido apenas como **fallback temporário** — nunca como solução permanente:

```js
async function carregar() {
    try {
        const { data } = await api.get('/api/v3/endpoint-real')
        lista.value = data.items ?? []
    } catch {
        lista.value = MOCK_DATA // placeholder enquanto backend não existe
    }
}
```

Quando o backend não existe, registrar em `docs/historico-problemas.md` com `[GAP-BACKEND]`.

---

## 6. REGRA DE ALÍQUOTAS E CONFIGURAÇÕES FIXAS

```php
// ❌ PROIBIDO:
$aliquota_servidor = 0.14;
$margem_consignacao = 0.35;

// ✅ CORRETO:
$cfg = DB::table('RPPS_CONFIG')->orderByDesc('VIGENCIA_INICIO')->first();
$aliquota_servidor = (float)($cfg->ALIQUOTA_SERVIDOR ?? 14) / 100;
```

**Erro real já ocorrido:** alíquotas RPPS/IPAM e margem de consignação estão hardcoded.

---

## 7. REGRA DE SEGURANÇA — ROTAS DE DESENVOLVIMENTO

Proibido em qualquer branch que vá para produção:
- Rotas sem autenticação que alterem dados
- Condição `if (env !== 'production')` para proteger rotas — usar `app()->isLocal()`

**Verificação antes de deploy:**
```bash
grep -r "dev/" routes/ | grep -v "isLocal()"
# Se retornar resultado → rota de dev exposta
```

---

## 8. REGRA DE DOCUMENTAÇÃO PÓS-TAREFA

Ao concluir qualquer tarefa, usar `.agent/workflows/documentar-solucao.md` como guia.
Registrar em `docs/historico-problemas.md` (bugs) e `docs/historico-estrategias-erradas.md` (abordagens que falharam).

---

## 9. REGRA DE ESTRATÉGIAS ERRADAS

Manter `docs/historico-estrategias-erradas.md`. Ver template completo em `.agent/workflows/documentar-solucao.md`.

---

## 10. VERIFICAÇÃO ANTES DE IMPLEMENTAR

O agente deve responder mentalmente antes de qualquer implementação:

1. **"Este endpoint já existe?"** → verificar `routes/` antes de criar
2. **"Esta view já existe?"** → verificar `resources/gente-v3/src/views/`
3. **"Esta migration já foi criada?"** → verificar `database/migrations/` pelo nome da tabela
4. **"Estou usando `/exoneracao/buscar` fora da ExoneracaoView?"** → parar, usar `/servidores/buscar`
5. **"Estou hardcoding valor variável (alíquota, margem, prazo)?"** → externalizar
6. **"Estou abrindo grupo de rotas em arquivo require'd?"** → remover o grupo

---

## 11. CONTEXTO FIXO DO PROJETO

| Dado | Valor |
|------|-------|
| Sistema | **GENTE** (nome anterior: SISGEP — não usar mais) |
| Prefeitura | Município de São Luís — MA (PMSLz) |
| RPPS | IPAM (não INSS, não FUNPREV) |
| Tribunal de contas | TCE-MA (não TCU, não TCM) — sistema SAGRES/SINC-Folha |
| Margem consignável | **30% empréstimos + 10% cartão** — NUNCA 40% unificado — Decreto 57.477/2021 |
| Email notificações | ronaldo@rrtecnol.com.br |
| SMTP | Brevo — smtp-relay.brevo.com:587, login: a11f6b001@smtp-brevo.com |
| Stack | Laravel + Vue 3 (Vite) — SPA com `/api/v3/*` e `/api/auth/*` |
| Autenticação principal | Sessão Laravel (web guard) — **não JWT** |
| JWT | Exclusivo para app mobile de ponto (`ponto_app.php`) |

---

## 12. REGRA DE CONSIGNAÇÃO — MARGEM SEPARADA

> Já ocorreu: código atual usa 35% unificado — **ilegal** pela regulamentação.

```php
// ❌ PROIBIDO:
$margem = $liquido * 0.35;
if ($valor_parcela > $margem - $total) abort(422);

// ✅ CORRETO — calcular por CONVENIO_TIPO:
$tipos_emp = ['BANCO', 'SINDICATO', 'COOPERATIVA'];

$usado_emp    = DB::table('CONSIG_CONTRATO as c')
    ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID','=','c.CONVENIO_ID')
    ->where('c.FUNCIONARIO_ID', $id)->where('c.STATUS','ATIVO')
    ->whereIn('cv.CONVENIO_TIPO', $tipos_emp)->sum('c.VALOR_PARCELA');

$usado_cartao = DB::table('CONSIG_CONTRATO as c')
    ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID','=','c.CONVENIO_ID')
    ->where('c.FUNCIONARIO_ID', $id)->where('c.STATUS','ATIVO')
    ->where('cv.CONVENIO_TIPO','CARTAO')->sum('c.VALOR_PARCELA');

$margem_emp    = $liquido * 0.30;
$margem_cartao = $liquido * 0.10;

if ($convenio->CONVENIO_TIPO === 'CARTAO') {
    if ($valor_parcela > ($margem_cartao - $usado_cartao))
        abort(422, 'Margem cartão insuficiente (10% — Decreto 57.477/2021)');
} else {
    if ($valor_parcela > ($margem_emp - $usado_emp))
        abort(422, 'Margem empréstimo insuficiente (30%)');
}
```

---

## 13. REGRA DE NOMEAÇÃO — TABELAS E CAMPOS SEMPRE MAIÚSCULAS

```php
// ❌ PROIBIDO:
Schema::create('banco_horas', fn($t) => $t->integer('funcionario_id'));
DB::table('banco_horas')->where('funcionario_id', $id)->get();

// ✅ CORRETO:
Schema::create('BANCO_HORAS', fn($t) => $t->integer('FUNCIONARIO_ID'));
DB::table('BANCO_HORAS')->where('FUNCIONARIO_ID', $id)->get();
```

Todas as migrations existentes usam maiúsculas. Minúsculas funcionam em Windows mas quebram em Linux/Docker.

---

## 14. REGRA DE EXPORTAÇÃO CSV — BOM UTF-8 OBRIGATÓRIO

**PHP (backend):**
```php
$csv = "\xEF\xBB\xBF" . implode("\n", $linhas); // BOM UTF-8
return response($csv, 200, [
    'Content-Type'        => 'text/csv; charset=UTF-8',
    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
]);
```

**Vue (frontend):**
```js
const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
const a = document.createElement('a')
a.href = URL.createObjectURL(blob)
a.download = `arquivo_${competencia}.csv`
a.click()
```

**Separador:** sempre `;` — padrão do Excel brasileiro e dos sistemas TCE-MA.

---

## 15. REGRA DE FORMATO DE COMPETÊNCIA

| Contexto | Formato | Exemplo |
|----------|---------|---------|
| Banco de dados (`FOLHA_COMPETENCIA`, `CONSIG_PARCELA.COMPETENCIA`) | `AAAAMM` sem separador | `202503` |
| Frontend Vue — input `type="month"` do HTML | `AAAA-MM` com hífen | `2025-03` |
| Parâmetros de URL / exibição | `AAAA-MM` com hífen | `2025-03` |

> ⚠️ O input HTML `type="month"` sempre retorna `AAAA-MM`. O backend DEVE converter antes de qualquer query.

```php
// ❌ PROIBIDO:
DB::table('FOLHA')->where('FOLHA_COMPETENCIA', '2025-03')->first(); // nunca encontra

// ✅ CORRETO:
$comp = str_replace('-', '', $request->competencia); // '2025-03' → '202503'
DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $comp)->first();
```

---

## 16. REGRA PSS — NOMEAÇÃO CRIA REGISTROS VINCULADOS AUTOMATICAMENTE

Ao implementar `POST /pss/candidatos/{id}/nomear`, o endpoint **DEVE** criar os três registros em uma transaction:

```php
DB::transaction(function () use ($candidato, $request) {
    // 1. PESSOA — verificar duplicidade por CPF antes de criar
    $pessoa_id = DB::table('PESSOA')
        ->where('PESSOA_CPF_NUMERO', $candidato->cpf)->value('PESSOA_ID');
    if (!$pessoa_id) {
        $pessoa_id = DB::table('PESSOA')->insertGetId([
            'PESSOA_NOME' => $candidato->nome, 'PESSOA_CPF_NUMERO' => $candidato->cpf,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // 2. FUNCIONARIO — nunca criar sem verificar duplicidade
    if (DB::table('FUNCIONARIO')->where('PESSOA_ID', $pessoa_id)->exists())
        abort(422, 'Servidor já cadastrado para este CPF.');

    $func_id = DB::table('FUNCIONARIO')->insertGetId([
        'PESSOA_ID' => $pessoa_id, 'CARGO_ID' => $request->cargo_id,
        'FUNCIONARIO_MATRICULA' => $request->matricula,
        'FUNCIONARIO_DATA_INICIO' => $request->data_posse,
        'FUNCIONARIO_REGIME_PREV' => $request->regime_prev ?? 'RPPS',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // 3. LOTACAO — vinculação imediata
    DB::table('LOTACAO')->insert([
        'FUNCIONARIO_ID' => $func_id, 'SETOR_ID' => $request->setor_id,
        'LOTACAO_DATA_INICIO' => $request->data_posse, 'LOTACAO_DATA_FIM' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // 4. Atualizar status do candidato
    DB::table('PSS_CANDIDATO')->where('CANDIDATO_ID', $candidato->id)
        ->update(['STATUS' => 'NOMEADO', 'FUNCIONARIO_ID' => $func_id, 'updated_at' => now()]);
});
```

**Por que transaction:** falha em qualquer INSERT reverte tudo — evita FUNCIONARIO sem LOTACAO.

---

## 17. STATUS DOS MÓDULOS CRÍTICOS (atualizado 23/03/2026 — auditoria Gravity)

> ✅ **Login e CORS resolvidos** (Sprint 0 — 15/03/2026). Todos os módulos acessíveis após auth.
> ✅ **Motor de Folha E2E passou:** R$24.743 proventos, R$2.614 descontos (17/03/2026).
> 🔴 **Bugs ativos (Sprint Cleanup):** IC-06 (margem cartão 5%→10%), BUG-SEED-01/02, BUG-MOTOR-01, BUG-SIDEBAR-01/04, BUG-AC-07.
> Fonte de verdade: `docs/MAPA_ESTADO_REAL.md` — prevalece em caso de conflito.

| Módulo | Frontend | Backend | Status real | Próxima ação |
|--------|----------|---------|-------------|-------------|
| Login / Auth | ✅ LoginView.vue | ✅ fora do isLocal() | ✅ Funcionando | — |
| CORS / Sessão | — | config/cors.php | ✅ Funcionando | — |
| Funcionários (CRUD) | ✅ | ✅ funcionarios.php | ✅ Funcional | — |
| Folha Pagamento | ✅ | ✅ folha.php | ✅ Motor E2E OK | IC-07 holerite PDF — Sprint Cleanup |
| Consignação | ✅ | ✅ consignacao.php | 🔴 Margem cartão 5% (ilegal) | IC-06 → Sprint Cleanup TASK-C1 |
| Progressão Funcional | ✅ | ✅ BOM removido | ✅ Funcional | TASK-14/15 tabela salarial |
| eSocial | ✅ | ✅ esocial.php | ✅ Funcional | — |
| RPPS/IPAM | ✅ | ✅ rpps.php | ✅ Funcional | BUG-RPPS-01/02 pendentes |
| Exoneração | ✅ | ✅ exoneracao.php | ✅ Funcional | — |
| Hora Extra | ✅ | ✅ hora_extra.php | ✅ Funcional | BUG-HE-01/02 pendentes |
| Verba Indenizatória | ✅ | ✅ | ✅ Funcional | — |
| Diárias | ✅ | ✅ diarias.php | ✅ Funcional | — |
| Estagiários | ✅ | ✅ estagiarios.php | ✅ Funcional | — |
| Acumulação de Cargos | ⚠️ sem sidebar | ✅ acumulacao.php | 🔴 Invisível na UI | BUG-SIDEBAR-01 → Sprint Cleanup |
| Transparência | ⚠️ sem sidebar | ✅ transparencia.php | 🔴 Invisível na UI | BUG-SIDEBAR-01 → Sprint Cleanup |
| PSS / Concursos | ⚠️ sem sidebar | ✅ pss.php | 🔴 Invisível na UI | BUG-SIDEBAR-01 → Sprint Cleanup |
| Terceirizados | ⚠️ sem sidebar | ✅ terceirizados.php | 🔴 Invisível na UI | BUG-SIDEBAR-01 → Sprint Cleanup |
| SAGRES / TCE-MA | ⚠️ sem sidebar | ✅ sagres.php | 🔴 Invisível na UI | BUG-SIDEBAR-01 → Sprint Cleanup |
| Banco de Horas | ✅ | ✅ banco_horas.php | ✅ Funcional | — |
| Atestados Médicos | ✅ | ✅ atestados.php | ✅ Funcional | — |
| Autocadastro | ✅ AutocadastroGestaoView | ✅ BUG-AC x6 corrigidos | ⚠️ BUG-AC-07 pendente | Dependentes na tabela errada |
| ERP (6 módulos) | ✅ stubs | ✅ stubs | 🟡 Pós-contrato | — |
| Neoconsig | ❌ não existe | ❌ não existe | 🔴 pendente | Sprint 6 |
| Notificações | ✅ stub | ⚠️ stub web.php | ⚠️ 404 constante | Pós-PoC |

---

## 18. REGRA DE PREFIXO DE ROTAS LEGACY — UNDERSCORE, NÃO HÍFEN

Módulos do sistema legado (Vue 2 + controllers) usam underscore no prefixo de rota:

```php
// ❌ RETORNA 404 no legado:
Route::prefix('tipo-documento')->...
Route::prefix('abono-falta')->...

// ✅ CORRETO no legado:
Route::prefix('tipo_documento')->...
Route::prefix('abono_falta')->...
```

> ⚠️ Esta regra é específica para rotas do sistema legado (Vue 2 / `web.php` antigo). As rotas do GENTE v3 (`/api/v3/*`) usam hífen na URL — ex: `/api/v3/banco-horas`, `/api/v3/hora-extra`. Os dois sistemas coexistem no mesmo `web.php`.

---

## 19. REGRA DE BOM UTF-8 — VERIFICAR ANTES DE DEPLOY

Editores Windows (Notepad, alguns VSCode) podem introduzir BOM (`EF BB BF`) em arquivos PHP. Em `routes/web.php`, o BOM vaza para o body HTTP antes dos headers JSON, corrompendo todas as respostas para o frontend.

**Verificar antes de qualquer deploy:**
```powershell
Get-Content 'routes\web.php' -Encoding Byte -TotalCount 3
# OK:       60  63 112  (< ? p)
# PROBLEMA: 239 187 191 (BOM — vai corromper JSON)
```

**Remover BOM se presente:**
```powershell
$bytes = [IO.File]::ReadAllBytes('routes\web.php')
if ($bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    [IO.File]::WriteAllBytes('routes\web.php', $bytes[3..($bytes.Length-1)])
}
```

**Sintoma no browser:** `JSON.stringify(pinia.state.value.auth.user)` começa com `\ufeff`. Ver `docs/historico-problemas.md` §2026-03-10.

---

## 20. REGRA — ROTAS AUTH NUNCA DENTRO DE isLocal()

> **Causa do login quebrado na Sprint Antigravity.** As rotas `/api/auth/*` e `/csrf-cookie` ficaram presas dentro do bloco `if (app()->isLocal()) { Route::prefix('dev')-> ... }`, gerando URLs `/dev/api/auth/login` que o frontend nunca chama.

```php
// ❌ PROIBIDO:
if (app()->isLocal()) {
    Route::prefix('dev')->group(function () {
        Route::get('/csrf-cookie', ...);
        Route::prefix('api/auth')->group(function () {
            Route::post('/login', ...);
        });
    });
}

// ✅ CORRETO — auth sempre no escopo global, ANTES do isLocal():
Route::get('/csrf-cookie', function () { ... })->middleware('web');
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () {
    Route::post('/login', ...);
    Route::get('/me', ...);
    Route::post('/logout', ...);
    Route::post('/change-password', ...);
});

if (app()->isLocal()) {
    Route::prefix('dev')->group(function () {
        // Manter: /ping-db, /echo-request, /echo-raw, /v3, /autocadastro/{token}
        // /diag-login, /criar-admin, /seed-dados
        // ⚠️ REMOVER: /csrf-cookie e Route::prefix('api/auth') — movidos para escopo global
    });
}
```

**Erro real que gerou esta regra:** Sprint Antigravity — login com 404 em todas as chamadas de auth.

---

## 21. REGRA — CORS: supports_credentials NUNCA false com wildcard

> Browsers modernos bloqueiam cookies de sessão quando `supports_credentials = false` + `allowed_origins = ['*']`. Isso impede que o Vite (5173) estabeleça sessão com o Laravel (8000).

```php
// ❌ PROIBIDO:
'allowed_origins'      => ['*'],
'supports_credentials' => false,

// ✅ CORRETO:
'allowed_origins' => [
    'http://127.0.0.1:5173',
    'http://localhost:5173',
    'http://127.0.0.1:8000',
    'http://localhost:8000',
],
'supports_credentials' => true,
```

Quando `supports_credentials = true`, `allowed_origins` **nunca pode ser** `['*']` — o browser rejeita. Sempre listar origens explícitas.

**Erro real que gerou esta regra:** Sprint Antigravity — sessão nunca estabelecida mesmo com login retornando 200.

---

---

## 22. REGRA — PRÉ-CONDIÇÕES DE BLOCO (dependências entre sprints)

Antes de iniciar qualquer task de um bloco, verificar no `MAPA_ESTADO_REAL.md` se as dependências estão marcadas como `RESOLVIDO` com data confirmada.

**Mapa de dependências obrigatórias:**

| Task | Depende de estar RESOLVIDO |
|------|---------------------------|
| C3 — Contabilidade PCASP | C1 (Orçamento) + C2 (Execução Despesa) |
| C4 — Tesouraria | C2 (Empenho/Pagamento) |
| C6 — SAGRES backend real | C3 (lançamentos contábeis funcionais) |
| C7 — Controle Externo LRF | C1 + C3 (execução orçamentária) |
| D3 — Depreciação NBCASP | C3 (ContabilidadeService::lancar()) |
| D4 — Contratos Admin | D1 (tabelas PROCESSO_LICITATORIO criadas) |
| A1b — AvaliacaoGestorView | A1 (backend avaliação funcional) |
| TASK-A0 — Proporcional | Motor folha E2E validado |
| GAP-13 — 13º salário | TASK-A0 resolvido |
| GAP-FER — Férias | TASK-A0 resolvido |
| GAP-RES — Rescisão | GAP-13 + GAP-FER resolvidos |

**Protocolo de verificação:**
```
ANTES de iniciar qualquer task:
1. Abrir docs/MAPA_ESTADO_REAL.md
2. Localizar as dependências da tabela acima
3. Confirmar status = RESOLVIDO com data
4. Se qualquer dependência estiver pendente: PARAR e reportar
5. NUNCA assumir que a task anterior funcionou — verificar o arquivo
```

**Por que esta regra existe:** o Antygravity pode criar código sintaticamente correto que funciona isolado mas quebra na integração porque a fundação ainda não existia. Ex: `ContabilidadeService::lancarFolha()` criado antes de `DOTACAO` existir — compila, não lança erro, mas nunca executa o que deveria.

---

## 23. REGRA DE SEGURANÇA — HEADERS HTTP OBRIGATÓRIOS EM PRODUÇÃO

Antes de qualquer deploy em VPS, verificar que `SecurityHeaders` middleware está registrado globalmente em `Kernel.php`:

```php
// app/Http/Middleware/SecurityHeaders.php — deve estar em $middleware global
\App\Http\Middleware\SecurityHeaders::class,
```

Headers obrigatórios: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Content-Security-Policy`.

`Strict-Transport-Security` (HSTS): ativar **somente após HTTPS estar configurado** — remover o comentário no middleware.

Spec completa: `docs/SPRINT_SEGURANCA.md` → SEC-PROD-01.

---

## 24. REGRA DE SEGURANÇA — UPLOAD DE ARQUIVOS

Todo endpoint que aceita upload DEVE aplicar o middleware `upload.safe`:

```php
Route::post('/atestados', ...)->middleware('upload.safe');
Route::post('/documentos', ...)->middleware('upload.safe');
```

O middleware `ValidateFileUpload` verifica: MIME real (não a extensão declarada), tamanho máximo 10 MB, extensão dupla suspeita (`arquivo.php.jpg` → rejeitado).

Spec completa: `docs/SPRINT_SEGURANCA.md` → SEC-PROD-05.

---

## 24. REGRA DE SEGURANÇA — v-html COM CONTEÚDO DO USUÁRIO

Qualquer uso de `v-html` com conteúdo que veio de input do usuário (comunicados, ouvidoria, pesquisas) DEVE sanitizar com DOMPurify:

```js
import { sanitize } from '@/plugins/sanitize'
// No template:
<div v-html="sanitize(conteudo)"></div>
```

Instalar: `npm install dompurify`. Plugin em `resources/gente-v3/src/plugins/sanitize.js`.

Spec completa: `docs/SPRINT_SEGURANCA.md` → SEC-PROD-08.

---

## 26. REGRA — GAPS CRÍTICOS DA FOLHA (bloqueadores jurídicos pós-contrato)

Os itens abaixo são obrigações legais — não podem ir para produção real sem eles:

| Gap | Impacto |
|-----|---------|
| `GAP-13` — 13º salário | Ilegal processar folha anual sem 13º |
| `GAP-FER` — pagamento de férias | Aprovação de férias sem cálculo financeiro |
| `GAP-RES` — TRCT rescisão | Exoneração sem calcular verbas rescisórias |
| `GAP-GFP` — SEFIP/GFIP | Obrigação mensal da Caixa para cargos RGPS |
| `GAP-DIR` — DIRF | Obrigação anual da Receita Federal |
| `GAP-RAS` — RAIS | Obrigação anual do MTE |

Spec detalhada de todos: `docs/GAPS_ESTRATEGICOS.md`.

Ordem de execução: implementar após assinatura do contrato com São Luís, antes de processar a primeira folha real em produção.

---

## 27. REGRA — DECISÕES DE UX PROIBIDAS AO AGENTE

O Antygravity **nunca toma decisões de UX por conta própria**. Quando a spec de uma task não descreve explicitamente o layout, fluxo ou comportamento de uma view, o agente deve:

1. **PARAR**
2. Listar as decisões de UX pendentes como perguntas objetivas
3. Aguardar resposta antes de criar qualquer arquivo Vue

**O que conta como decisão de UX (requer aprovação antes de implementar):**
- Qual ação é o botão primário de uma tela
- Ordem e rótulo de tabs
- O que aparece no estado vazio de uma lista
- Comportamento de formulários (inline vs modal vs página separada)
- Quais campos são obrigatórios vs opcionais numa tela de cadastro
- O que o painel/hero exibe por padrão ao carregar

**O que NÃO é decisão de UX (agente decide sozinho):**
- Estilo visual (cores, bordas, animações) — seguir o padrão das views existentes
- Estrutura interna do componente Vue (refs, computed, onMounted)
- Endpoints chamados e mapeamento de dados
- Tratamento de erros e fallbacks

**Referência de estilo visual obrigatória:**
Antes de criar qualquer view nova, ler `resources/gente-v3/src/views/rh/PesquisaAdminView.vue`
como referência de padrão visual. Replicar: hero com gradiente escuro, tabs, cards com border-radius 20px, hero-kpis no canto superior direito.

**Critério de aceite de UX mínimo para views admin:**
Toda view administrativa deve ter:
- Hero com título, subtítulo e pelo menos 1 KPI numérico visível
- Ação primária (botão "Nova X" ou "Registrar X") claramente visível no hero ou topo da lista
- Estado vazio com mensagem e ícone — nunca lista em branco sem explicação
- Feedback visual após toda ação (toast de sucesso ou erro)
- Confirmação antes de qualquer ação destrutiva (excluir, rejeitar, baixar bem)

---

*Este arquivo é autoridade máxima sobre convenções do projeto. Em caso de conflito com qualquer outra fonte, este arquivo prevalece.*
