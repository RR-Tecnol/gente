# BUG-SPRINT-02 — Fase 1: Correções de Impacto UX
**Data:** 2026-04-16
**Origem:** Continuação da auditoria QA (Davi) — itens de FASE 1 do relatório original
**Pré-requisito:** BUG-SPRINT-01 commitada em `5a0dacd` ✅
**Workflow:** Antygravity executa cada onda → Claude audita via MCP → aprova antes da próxima

---

## ESTADO REAL CONFIRMADO (auditoria Claude pré-sprint)

| Item | Estado real | Ação necessária |
|---|---|---|
| F1-01 Ícones UTF-8 FeriasLicencas | UTF-8 OK (fix anterior) | Verificar emojis no template |
| F1-01 BancoHorasView | UTF-8 OK | Verificar emojis no template |
| F1-02 Sidebar profile | EXISTS, sem router.push | Adicionar @click |
| F1-03 Declarações mock IDs | MOCK FOUND | Corrigir download |
| F1-04 Progressão seed | Não está no DatabaseSeeder | SQL seed |
| F1-05 Sobreaviso Vue otimista | 377 linhas, padrão não detectado | Verificar manualmente |
| F1-06 Escala Médica blade | Blade não existe, controller aponta para ela | Redirecionar controller |
| F1-07 SESMT KPIs | Rota existe, verificar erro real | Testar e corrigir |
| F1-08 Holerites sem identificação | PESSOA_NOME ausente na response | Adicionar join |
| F1-09 Diárias data passado | Validação já existe | Confirmar funciona |

---

## REGRAS DESTA SPRINT

1. Executar na ordem das ondas — não pular.
2. Uma onda por vez — aguardar aprovação do Claude.
3. Usar `edit_block` com `old_string`/`new_string` exatos.
4. Nunca adicionar rotas inline no `web.php`.
5. Não alterar arquivos fora do escopo da onda.
6. Reportar: arquivo alterado + linhas + output do comando de verificação.

---

## ONDA 1 — Fixes de 1 arquivo, sem lógica nova (4 fixes)

---

### [O1-01] Escala Médica — controller aponta para blade inexistente

**Arquivo:** `app/Http/Controllers/EscalaController.php`
**Impacto:** Botão "Compartilhar" na escala médica → 500 (blade não existe)
**Estado confirmado:** `return view('escala.escala_view', compact('setores', 'tiposEscalas'));` — blade ausente

Substituir o `return view(...)` por redirect para a rota Vue:

```
old_string:
return view('escala.escala_view', compact('setores', 'tiposEscalas'));

new_string:
return redirect('/escala-matriz-v3');
```

**Verificação:**
```bash
php -l app/Http/Controllers/EscalaController.php
```

---

### [O1-02] Sidebar profile — sem navegação

**Arquivo:** `resources/gente-v3/src/layouts/DashboardLayout.vue`
**Impacto:** Clicar no perfil da sidebar não navega
**Estado confirmado:** `.sidebar-profile` existe mas sem `@click`

Localizar o bloco `sidebar-profile` no template e adicionar o handler:

```
old_string:
<div class="sidebar-profile"

new_string:
<div class="sidebar-profile" @click="$router.push('/meu-perfil')" style="cursor:pointer"
```

**Verificação:** confirmar que `sidebar-profile` aparece uma única vez no arquivo com o `@click`.

---

### [O1-03] Holerites — response sem identificação do funcionário

**Arquivo:** `app/Http/Controllers/ContraChequeController.php`
**Impacto:** Cards de holerite sem nome, matrícula e CPF do funcionário
**Estado confirmado:** `PESSOA_NOME` ausente na response

Localizar o método `listarMinhasFolhas` (ou equivalente) e adicionar `join` com `PESSOA` e `FUNCIONARIO`:

```php
// Adicionar ao select existente:
'p.PESSOA_NOME as nome',
'f.FUNCIONARIO_MATRICULA as matricula',
// e o join correspondente:
->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
```

**Importante:** verificar quais joins já existem na query antes de adicionar — não duplicar.

**Verificação:**
```bash
php -l app/Http/Controllers/ContraChequeController.php
```

---

### [O1-04] Declarações — download usa IDs mock

**Arquivo:** `resources/gente-v3/src/views/rh/DeclaracoesRequerimentosView.vue`
**Impacto:** Download de declaração retorna 404 porque usa IDs 1, 2, 3 hardcoded
**Estado confirmado:** `mockPedidos` com IDs estáticos encontrado

**Passo A:** Localizar a função `solicitarDoc()` (ou equivalente) e corrigir para usar o ID retornado pelo POST:

```
old_string:
window.open(`/api/v3/declaracoes/${d.id}/download`, '_blank')

new_string:
const resp = await api.post('/api/v3/declaracoes', { tipo: d.tipo })
const novoId = resp.data?.id
if (novoId) window.open(`/api/v3/declaracoes/${novoId}/download`, '_blank')
```

**Passo B:** Se a lista de pedidos exibida na tela usa IDs mockados hardcoded para popular a tabela, substituir por chamada real ao backend `GET /api/v3/declaracoes` ao montar a view (`onMounted`).

**Nota:** Ler o arquivo completo antes de editar — o padrão exato pode diferir do exemplo acima.

**Verificação:** confirmar que não há mais referência a `mockPedidos` ou IDs estáticos na lógica de download.

---

### Relatório esperado da ONDA 1

```
[O1-01] EscalaController.php — return view substituído por redirect
[O1-02] DashboardLayout.vue — sidebar-profile com @click adicionado
[O1-03] ContraChequeController.php — join PESSOA adicionado, PESSOA_NOME na response
[O1-04] DeclaracoesView.vue — download usa ID real do POST
```

---

## ONDA 2 — Verificações e fixes condicionais (3 items)

> Aguardar aprovação da ONDA 1 antes de iniciar.

---

### [O2-01] Sobreaviso e Plantões Extras — Vue otimista

**Arquivos:** `resources/gente-v3/src/views/ponto/EscalaSobreavisoView.vue`
           `resources/gente-v3/src/views/ponto/PlantoesExtrasView.vue`
**Impacto:** Dado aparece na tela antes de ser salvo; some no F5 se o servidor falhou
**Ação:** Ler cada arquivo e localizar onde o estado local é atualizado após POST.

Padrão errado a procurar:
```js
// Vue otimista: push ANTES do await
lista.value.push(novoItem)
await api.post('/api/v3/...', payload)
```

Padrão correto:
```js
// Push DEPOIS de confirmar o servidor
const resp = await api.post('/api/v3/...', payload)
if (resp.data?.ok) lista.value.push(resp.data.item ?? novoItem)
```

Se o padrão otimista for encontrado, corrigir. Se já estiver correto, reportar "já correto" com a linha onde o push acontece.

**Verificação:**
```bash
php artisan route:list --path=api/v3/sobreaviso 2>&1 | head -10
```

---

### [O2-02] SESMT Medicina — KPIs retornam 500

**Arquivo:** `routes/medicina_admin.php`
**Impacto:** Dashboard SESMT não carrega KPIs
**Ação:** Verificar o erro real antes de corrigir:

```bash
# Testar a rota diretamente e ver o erro no log:
php artisan route:list --path=api/v3/medicina-admin/kpis 2>&1
tail -30 storage/logs/laravel.log
```

O trecho relevante usa `date('Y-m-d', strtotime('+30 days'))` — compatível com SQLite.
Verificar se o problema é tabela não migrada ou query incompatível com SQL Server.

Se for tabela: `php artisan migrate`
Se for query `DATE()` MySQL-only: aplicar o mesmo fix da ONDA 1 da sprint anterior — `CAST(... AS DATE)`.

**Verificação:**
```bash
php artisan route:list --path=api/v3/medicina-admin 2>&1 | head -10
```

---

### [O2-03] F1-01 Ícones — verificar emojis corrompidos

**Arquivos:** `FeriasLicencasView.vue`, `BancoHorasView.vue`, `FuncionariosView.vue`, `AutocadastroGestaoView.vue`
**Estado:** UTF-8 OK nos bytes, mas emojis podem ter sido substituídos por `?` no conteúdo textual durante o commit corrompido de 31/03/2026.

**Ação:** Ler cada arquivo e buscar padrões de corrupção textual:

```bash
# Buscar ?? ou sequencias de ? que substituiram emojis
Select-String -Path "resources/gente-v3/src/views/rh/FeriasLicencasView.vue" -Pattern "\?\?"
```

Tabela de substituição confirmada pelo Davi:

| Corrompido | Correto |
|---|---|
| `??` nas abas de Férias | `🏖️` |
| `??` Afastamentos | `📋` |
| `??` Agendar | `📅` |
| `??` upload zone | `📎` |
| `?` arquivo selecionado | `✅` |
| `??` Períodos Aquisitivos | `📊` |
| `??` overlap warn | `⚠️` |

Se encontrar corrupção, corrigir com `edit_block`. Se não encontrar, reportar "não detectado" com evidência.

**Verificação:** grep de `??` nos arquivos listados.

---

### Relatório esperado da ONDA 2

```
[O2-01] Sobreaviso — padrão otimista: [corrigido / já correto em linha X]
[O2-01] Plantões Extras — padrão otimista: [corrigido / já correto em linha X]
[O2-02] SESMT KPIs — causa: [tabela / query] — ação tomada
[O2-03] Ícones — [corrupção encontrada e corrigida / não detectada] por arquivo
```

---

## ONDA 3 — Seeds e dados (não são bugs de código)

> Aguardar aprovação da ONDA 2 antes de iniciar.
> Esta onda não altera nenhum arquivo de rota ou Vue.

---

### [O3-01] Progressão Funcional — admin sem CARREIRA_ID

**Impacto:** Tela "Minha Progressão" exibe mock para o admin
**Causa:** Admin criado sem `CARREIRA_ID`, `FUNCIONARIO_CLASSE`, `FUNCIONARIO_REFERENCIA`

Executar no tinker (confirmar USUARIO_ID do admin primeiro):

```bash
php artisan tinker
```

```php
// Verificar ID do admin:
DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->first();

// Verificar se existe pelo menos 1 PROGRESSAO_CONFIG e TABELA_SALARIAL:
DB::table('PROGRESSAO_CONFIG')->count();
DB::table('TABELA_SALARIAL')->count();

// Se existirem, atualizar o funcionário admin:
$uid = DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->value('USUARIO_ID');
$fid = DB::table('FUNCIONARIO')->where('USUARIO_ID', $uid)->value('FUNCIONARIO_ID');
DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $fid)->update([
    'CARREIRA_ID' => 1,
    'FUNCIONARIO_CLASSE' => 'A',
    'FUNCIONARIO_REFERENCIA' => 'I',
    'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => '2023-03-15',
]);
```

Se `PROGRESSAO_CONFIG` estiver vazio, reportar — precisará de seed adicional.

---

### [O3-02] Banco de Horas — tabela vazia

**Impacto:** KPIs de Banco de Horas mostram zero; botão Equipe vazio
**Ação:** Verificar estado antes de criar seed:

```bash
php artisan tinker
```

```php
// Checar se tabela existe e tem dados:
DB::table('BANCO_HORAS')->count();

// Checar lotação do admin:
$uid = DB::table('USUARIO')->where('USUARIO_LOGIN', 'admin')->value('USUARIO_ID');
$fid = DB::table('FUNCIONARIO')->where('USUARIO_ID', $uid)->value('FUNCIONARIO_ID');
DB::table('LOTACAO')->where('FUNCIONARIO_ID', $fid)->whereNull('LOTACAO_DATA_FIM')->first();
```

Se `LOTACAO_DATA_FIM` estiver preenchida, zerar:
```php
DB::table('LOTACAO')->where('FUNCIONARIO_ID', $fid)->update(['LOTACAO_DATA_FIM' => null]);
```

Se `BANCO_HORAS` estiver vazia, reportar — seed complexo será planejado em sessão separada.

---

### Relatório esperado da ONDA 3

```
[O3-01] Progressão: CARREIRA_ID atualizado para admin / PROGRESSAO_CONFIG vazio (seed pendente)
[O3-02] BANCO_HORAS count: X registros / LOTACAO_DATA_FIM corrigida se necessário
```

---

## CHECKLIST DE AUDITORIA

### ONDA 1
- [ ] O1-01: `EscalaController.php` — `return redirect` confirmado, sem `return view`
- [ ] O1-02: `DashboardLayout.vue` — `sidebar-profile` com `@click` e `cursor:pointer`
- [ ] O1-03: `ContraChequeController.php` — `PESSOA_NOME` e `FUNCIONARIO_MATRICULA` na query
- [ ] O1-04: `DeclaracoesView.vue` — sem `mockPedidos` na lógica de download

### ONDA 2
- [ ] O2-01: Sobreaviso — push após await confirmado
- [ ] O2-01: Plantões Extras — push após await confirmado
- [ ] O2-02: SESMT KPIs — causa identificada e corrigida
- [ ] O2-03: Ícones — corrupção verificada por arquivo

### ONDA 3
- [ ] O3-01: Admin com CARREIRA_ID ou impedimento documentado
- [ ] O3-02: BANCO_HORAS count e LOTACAO status reportados

---

*Gerado em: 2026-04-16 | Baseado em auditoria real via MCP antes da sprint*
*Estado de cada arquivo confirmado em `scripts-debug/audit_fase1.ps1` e `audit_fase1b.ps1`*
