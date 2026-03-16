---
description: Regras de desenvolvimento e documentação do GENTE v3
---

# GENTE — Regras de Desenvolvimento do Agente
**Versão:** 4.2 — Estrutura de workflows + regras por lacunas identificadas
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
- `PLANO_IMPLEMENTACAO_GENTE_V3.md` — somente ao criar módulo completamente novo

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

## 17. STATUS DOS MÓDULOS CRÍTICOS (atualizado 13/03/2026 — varredura direta)

> ⚠️ **Diagnóstico correto:** os `require` de todos os módulos estão FORA do bloco `isLocal()` (linhas 10.300+ do web.php). As rotas existem e estão nas URLs corretas.
> O problema é que **ninguém consegue autenticar** (TASK-01/02), então nenhuma request chega nos módulos protegidos por middleware `auth`.
> Corrigir login = desbloquear todos os módulos simultaneamente.

| Módulo | Frontend | Backend | Status real | Próxima ação |
|--------|----------|---------|-------------|-------------|
| LOGIN / AUTH | ✅ LoginView.vue | 🔴 preso dentro de isLocal() | 🔴 CAUSA RAIZ | Sprint 0 TASK-01 — fazer primeiro |
| CORS / Sessão | — | config/cors.php | 🔴 BLOQUEANTE | Sprint 0 TASK-02 |
| Progressão Funcional | ✅ ProgressaoFuncionalView.vue | ⚠️ progressao_funcional.php BOM | ⚠️ Risco 500 | Sprint 0 TASK-05 |
| Funcionários (CRUD) | ✅ FuncionariosView.vue | ✅ funcionarios.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Folha Pagamento | ✅ FolhaPagamentoView.vue | ✅ folha.php | ✅ Rota OK — inativa por auth | View holerite PDF → Sprint 2 |
| Consignação | ✅ ConsignacaoView.vue | ✅ consignacao.php | ✅ Rota OK — inativa por auth | Margem 10% → Sprint 2 |
| eSocial | ✅ ESocialView.vue | ✅ esocial.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| RPPS/IPAM | ✅ RPPSView.vue | ✅ rpps.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Exoneração | ✅ ExoneracaoView.vue | ✅ exoneracao.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Hora Extra | ✅ HoraExtraView.vue | ✅ hora_extra.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Verba Indenizatória | ✅ VerbaIndenizatoriaView.vue | ✅ verba_indenizatoria.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Diárias | ✅ DiariasView.vue | ✅ diarias.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Estagiários | ✅ EstagiariosView.vue | ✅ estagiarios.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Acumulação de Cargos | ✅ AcumulacaoView.vue | ✅ acumulacao.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Transparência Pública | ✅ TransparenciaView.vue | ✅ transparencia.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| PSS / Concursos | ✅ PSSView.vue | ✅ pss.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Terceirizados | ✅ TerceirizadosView.vue | ✅ terceirizados.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| SAGRES / TCE-MA | ✅ SagresView.vue | ✅ sagres.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Banco de Horas | ✅ BancoHorasView.vue | ✅ banco_horas.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Atestados Médicos | ✅ AtestadosMedicosView.vue | ✅ atestados.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Orçamento | ✅ OrcamentoView.vue | ✅ orcamento.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Execução Despesa | ✅ ExecucaoDespesaView.vue | ✅ execucao_despesa.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Contabilidade | ✅ ContabilidadeView.vue | ✅ contabilidade.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Tesouraria | ✅ TesourariaView.vue | ✅ tesouraria.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Receita Municipal | ✅ ReceitaMunicipalView.vue | ✅ receita_municipal.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| ERP Controle Externo | ✅ ControleExternoView.vue | ✅ controle_externo.php | ✅ Rota OK — inativa por auth | Depende de TASK-01 |
| Neoconsig | ❌ não existe | ❌ não existe | ❌ pendente | Sprint 3 — após Sprint 0 |
| Notificações | ✅ NotificacoesView.vue | ⚠️ stub no web.php | ⚠️ 404 constante | Criar endpoint → após Sprint 0 |


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

*Este arquivo é autoridade máxima sobre convenções do projeto. Em caso de conflito com qualquer outra fonte, este arquivo prevalece.*
