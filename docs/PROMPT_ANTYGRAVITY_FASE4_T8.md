# PROMPT ANTYGRAVITY — FASE 4 T4.8 (Migração Alertas para /api/v3/)

> **Cole este prompt no Antygravity** para destravar a Fase 4. Estimativa: ~30 min Antygravity + ~15 min auditoria Claude.
> Pré-condição: Fase 4 T4.1–T4.7 já mergeadas (7 commits aprovados). Branch limpa.

---

## CONTEXTO

A Fase 4 T4.8 foi PAUSADA com a decisão correta: você não removeu os endpoints HTTP `ferias/alerta-vencer` e `afastamento/alerta-expirar` porque o `app/Console/Kernel.php` não tinha Schedule equivalente.

Auditoria Claude (08/05/2026) revisou a premissa original do MAPA e identificou: **esses endpoints NÃO eram cron jobs**. Eram endpoints HTTP de leitura, consumidos pelo SPA Vue 3 (dashboards de gestor/coordenador) para listar servidores com férias vencendo / afastamentos expirando.

A análise:
- `Auth::user()` é usado para filtrar por `COORD_DE_SETOR` → endpoint user-facing, não CLI
- Retornam `response()->json([...])` sem efeito colateral (não enviam email, não criam notificação)
- Nenhum Schedule task no Kernel chamava esses endpoints; nenhum command Artisan os encapsula
- Nenhum SPA componente foi confirmado consumindo a URL antiga (mas pode haver — vamos acomodar)

**Decisão revisada para implantação real (NÃO PoC):** consolidar arquitetura. Mover os 2 endpoints para `/api/v3/` (padrão atual de tudo no SPA Vue 3) e remover os endpoints legados do `web.php`. Lógica de negócio fica idêntica; só roteamento muda.

Esta é a **última correção pendente da Fase 4** antes do deploy de produção PMSL/MA.

---

## REGRAS DE EXECUÇÃO

1. Trabalhar em ordem estrita: T4.8.A → T4.8.B → T4.8.C → T4.8.D.
2. **PARAR e reportar** se qualquer validação falhar.
3. **NÃO mexer** em `FeriasController.php` nem `AfastamentoController.php` — os métodos `alertaVencer()` e `alertaExpirar()` permanecem intocados.
4. **NÃO mexer** nos imports `use App\Http\Controllers\FeriasController;` nem `use App\Http\Controllers\AfastamentoController;` no topo do `web.php` — os Controllers continuam sendo usados pelos blocos `Route::prefix('ferias')` e `Route::prefix('afastamento')` (CRUD legado, fora do escopo desta fase).
5. **2 commits cirúrgicos** ao final (1 add, 1 remove).

---

## T4.8.A — Adicionar rota nova em `routes/ferias_v3.php` (~5 min)

**Arquivo:** `routes/ferias_v3.php`

**Por que esse arquivo:** ele é `require`-d em `routes/api_v3_auth_part1.php` dentro do grupo `Route::prefix('api/v3')->middleware(['web', 'auth', ...])`. Toda rota declarada aqui automaticamente vira `/api/v3/...` autenticada.

**Trecho atual** (último Route do arquivo, linha ~206-219):

```php
// ── GAP-FER: Aprovar férias e calcular valores ─────────────────────────────
Route::post('/ferias/{id}/aprovar', function (int $id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $competencia = request('competencia'); // AAAAMM
        if (!$competencia) {
            return response()->json(['erro' => 'competencia é obrigatório (AAAAMM).'], 422);
        }
        $service = new \App\Services\FeriasService();
        $calc = $service->aprovar($id, $user->USUARIO_ID, $competencia);
        return response()->json(['ok' => true, 'calculo' => $calc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});
```

**ADICIONAR DEPOIS DESTA ROTA** (anexar ao final do arquivo):

```php

// ═════════════════════════════════════════════════════════════════════
// GAP-ALERT (Fase 4 T4.8 — 08/05/2026): Migração da rota legada
// /ferias/alerta-vencer (web.php, bloco autenticado) → /api/v3/ferias/alerta-vencer
// Lógica idêntica delegada ao FeriasController::alertaVencer (já Auth-aware).
// Filtro: COORD_DE_SETOR vê apenas seu setor; demais perfis veem todos.
// ═════════════════════════════════════════════════════════════════════
Route::get('/ferias/alerta-vencer', [\App\Http\Controllers\FeriasController::class, 'alertaVencer'])
    ->name('api.v3.ferias.alerta-vencer');
```

**Atenção:**
- A sintaxe **NÃO usa o método `alertaVencer` re-implementado**. Aponta direto para o Controller já existente.
- O Controller usa `Auth::user()` que já vem populado pelo middleware `auth` do grupo pai.
- O middleware `tenant.scope` do grupo pai vai aplicar isolamento de tenant automático — isso é GANHO que o endpoint legado em `web.php` não tinha.

**Validação após adicionar:**

```powershell
php -l routes/ferias_v3.php
```
Esperado: `No syntax errors detected`.

```powershell
Select-String -Path "routes/ferias_v3.php" -Pattern "ferias/alerta-vencer"
```
Esperado: 1 ocorrência.

---

## T4.8.B — Adicionar rota nova em `routes/afastamentos_v3.php` (~5 min)

**Arquivo:** `routes/afastamentos_v3.php`

**Atenção crítica de naming:** o arquivo se chama `afastamentos_v3.php` (plural com S) mas o Controller é `AfastamentoController` (singular). Para coerência com o restante do `afastamentos_v3.php` (que usa `/afastamentos` plural em todas as rotas), a nova rota também usa **plural**: `/afastamentos/alerta-expirar`. Isso DIFERE da rota legada do `web.php` que era `/afastamento/alerta-expirar` singular. **Esta mudança é intencional** para alinhar com o padrão do arquivo.

**ADICIONAR AO FINAL DO ARQUIVO** (após o último `Route::get('/afastamentos/{id}/anexo/{anexoId}/download', ...)` ~linha 304):

```php

// ═════════════════════════════════════════════════════════════════════
// GAP-ALERT (Fase 4 T4.8 — 08/05/2026): Migração da rota legada
// /afastamento/alerta-expirar (web.php, bloco autenticado) → /api/v3/afastamentos/alerta-expirar
// Mudança de naming: singular → plural para alinhar com restante deste arquivo.
// Lógica idêntica delegada ao AfastamentoController::alertaExpirar (já Auth-aware).
// Filtro: COORD_DE_SETOR vê apenas seu setor; demais perfis veem todos.
// ═════════════════════════════════════════════════════════════════════
Route::get('/afastamentos/alerta-expirar', [\App\Http\Controllers\AfastamentoController::class, 'alertaExpirar'])
    ->name('api.v3.afastamentos.alerta-expirar');
```

**Validação após adicionar:**

```powershell
php -l routes/afastamentos_v3.php
```
Esperado: `No syntax errors detected`.

```powershell
Select-String -Path "routes/afastamentos_v3.php" -Pattern "afastamentos/alerta-expirar"
```
Esperado: 1 ocorrência.

**Commit T4.8.A + T4.8.B (1 commit consolidado):**

```
git add routes/ferias_v3.php routes/afastamentos_v3.php
git commit -m "feat(Fase4-T8.AB,decisao-8): migra alertas RH para /api/v3/ (alerta-vencer + alerta-expirar)"
```

---

## T4.8.C — Remover endpoints legados de `routes/web.php` (~5 min)

**Arquivo:** `routes/web.php`

**Trecho atual** (linhas ~1481-1485, dentro do grupo autenticado `Route::middleware(['auth', 'web', 'CompartilharVariaveis', 'usuario.externo'])->group(...)`):

```php
    // â”€â”€ Alertas de RH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('ferias/alerta-vencer', [FeriasController::class, 'alertaVencer'])
        ->name('ferias.alerta-vencer');
    Route::get('afastamento/alerta-expirar', [AfastamentoController::class, 'alertaExpirar'])
        ->name('afastamento.alerta-expirar');
```

**Trecho corrigido (substituir os 5 linhas acima por):**

```php
    // FASE-4-MIGRADO 08/05/2026 (decisão 8 do MAPA — revisada para Opção B):
    // Endpoints alertaVencer/alertaExpirar movidos para /api/v3/ferias/alerta-vencer
    // e /api/v3/afastamentos/alerta-expirar (ver routes/ferias_v3.php e routes/afastamentos_v3.php).
    // Controllers FeriasController::alertaVencer e AfastamentoController::alertaExpirar preservados.
```

**Validação:**

```powershell
php -l routes/web.php
```
Esperado: `No syntax errors detected`.

```powershell
# Confirmar que rotas legadas saíram (esperado 0)
Select-String -Path "routes/web.php" -Pattern "ferias/alerta-vencer|afastamento/alerta-expirar"
```
Esperado: 0 ocorrências.

```powershell
# Confirmar que os imports dos Controllers continuam (esperado 1 cada)
Select-String -Path "routes/web.php" -Pattern "use App\\\\Http\\\\Controllers\\\\FeriasController"
Select-String -Path "routes/web.php" -Pattern "use App\\\\Http\\\\Controllers\\\\AfastamentoController"
```
Esperado: 1 ocorrência cada (preservados — outros blocos usam).

```powershell
# Confirmar que blocos Route::prefix('ferias')/('afastamento') ainda existem (CRUD legado intocado)
Select-String -Path "routes/web.php" -Pattern "Route::prefix\('ferias'\)|Route::prefix\('afastamento'\)"
```
Esperado: 2 ocorrências.

```powershell
# Auditoria global: nenhuma view, controller ou JS chamando route('ferias.alerta-vencer') ou route('afastamento.alerta-expirar')
Get-ChildItem -Path "app", "resources" -Recurse -Filter "*.php","*.blade.php","*.vue","*.js","*.ts" | Select-String -Pattern "route\('ferias\.alerta-vencer'\)|route\('afastamento\.alerta-expirar'\)" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

**Se houver QUALQUER ocorrência** no último grep (algum lugar gerando link para a rota nomeada legada), **PARAR e reportar a Claude** — significa que existe view ou componente que vai quebrar.

**Commit T4.8.C:**

```
git add routes/web.php
git commit -m "refactor(Fase4-T8.C,decisao-8): remover endpoints HTTP legados ferias/alerta-vencer + afastamento/alerta-expirar (migrados para /api/v3/)"
```

---

## T4.8.D — Smoke test consolidado (~10 min)

**Validação 1 — Sintaxe global**

```powershell
php -l routes/web.php
php -l routes/ferias_v3.php
php -l routes/afastamentos_v3.php
```
Esperado: 3× `No syntax errors detected`.

**Validação 2 — Boot Laravel sem erro**

```powershell
php artisan route:list --columns=method,uri,name 2>&1 | Select-String -Pattern "alerta-vencer|alerta-expirar"
```

Esperado: 2 linhas mostrando:
```
GET|HEAD   api/v3/ferias/alerta-vencer        api.v3.ferias.alerta-vencer
GET|HEAD   api/v3/afastamentos/alerta-expirar api.v3.afastamentos.alerta-expirar
```

**Se aparecer alguma linha com `ferias/alerta-vencer` SEM o prefixo `api/v3/`**, significa que a remoção do `web.php` não pegou — REVERTER e investigar.

**Se NÃO aparecerem as 2 linhas com prefixo `api/v3/`**, significa que `routes/ferias_v3.php` ou `routes/afastamentos_v3.php` não estão sendo `require`d corretamente — investigar `routes/api_v3_auth_part1.php`.

**Validação 3 — Tamanho do `web.php` final**

```powershell
(Get-Content "routes/web.php" | Measure-Object -Line).Lines
```
Esperado: ~1657 linhas (de 1660 antes da T4.8 — 3 linhas removidas, 4 de comentário adicionadas líquido ≈ 1 linha a menos).

**Validação 4 — Git log das 3 correções**

```powershell
git log --oneline -n 12
```

Esperado entre os últimos commits:
- 7 commits da Fase 4 T4.1–T4.7 (já mergeados)
- `feat(Fase4-T8.AB,decisao-8): migra alertas RH para /api/v3/ ...`
- `refactor(Fase4-T8.C,decisao-8): remover endpoints HTTP legados ...`

**Validação 5 — Teste funcional via tinker (opcional, se ambiente local rodar)**

```bash
php artisan tinker
# No prompt:
> $user = \App\Models\Usuario::first();
> \Illuminate\Support\Facades\Auth::login($user);
> $controller = new \App\Http\Controllers\FeriasController();
> $req = new \Illuminate\Http\Request();
> $resp = $controller->alertaVencer($req);
> echo $resp->getContent();
> exit
```

Esperado: JSON válido com chaves `cod`, `msg`, `retorno`. Sem fatal error.

**Se der erro `Trying to access property of null`**, significa que o `Usuario::first()` retornou um usuário sem perfil/funcionário definido — não é problema de roteamento, é dado inconsistente no banco local. OK.

---

## REPORT FINAL — Fase 4 T4.8

```
═══════════════════════════════════════════════════════════════════
FASE 4 T4.8 — REPORT EXECUÇÃO ANTYGRAVITY
═══════════════════════════════════════════════════════════════════

T4.8.A — adicionar /api/v3/ferias/alerta-vencer:
[ ] routes/ferias_v3.php editado: rota adicionada
[ ] php -l ferias_v3.php OK
[ ] Select-String "ferias/alerta-vencer": 1 ocorrência

T4.8.B — adicionar /api/v3/afastamentos/alerta-expirar:
[ ] routes/afastamentos_v3.php editado: rota adicionada
[ ] php -l afastamentos_v3.php OK
[ ] Select-String "afastamentos/alerta-expirar": 1 ocorrência

Commit T4.8.AB: ____________ (hash)

T4.8.C — remover endpoints legados:
[ ] routes/web.php editado: 5 linhas removidas, 4 de comentário adicionadas
[ ] php -l web.php OK
[ ] Select-String "ferias/alerta-vencer|afastamento/alerta-expirar" no web.php: 0
[ ] use FeriasController preservado: SIM
[ ] use AfastamentoController preservado: SIM
[ ] Route::prefix('ferias') e ('afastamento') (CRUD legado) preservados: SIM
[ ] Grep global por route('ferias.alerta-vencer') ou route('afastamento.alerta-expirar'): 0 ocorrências

Commit T4.8.C: ____________ (hash)

T4.8.D — smoke test consolidado:
[ ] php -l x3 OK
[ ] route:list mostra api/v3/ferias/alerta-vencer + api/v3/afastamentos/alerta-expirar: SIM
[ ] route:list NÃO mostra rotas legadas sem prefixo /api/v3/: SIM
[ ] web.php final: ___ linhas
[ ] git log mostra 9 commits da Fase 4 (T4.1 a T4.8 inclusive)

Tinker test (opcional):
[ ] alertaVencer chamado direto no Controller: ____ (sucesso/erro)
[ ] alertaExpirar chamado direto no Controller: ____ (sucesso/erro)

PROBLEMAS / DECISÕES:
___

TEMPO TOTAL: ___min

FASE 4 COMPLETA E PRONTA PARA DEPLOY? SIM / NÃO
═══════════════════════════════════════════════════════════════════
```

---

## OBSERVAÇÃO IMPORTANTE PÓS-FASE-4

Após T4.8 mergeado, **a Fase 6 T6.8 fica DESCARTADA**. O `app/Console/Kernel.php` NÃO precisa de Schedule task para alertas — porque os alertas continuam sendo endpoints HTTP de leitura, agora apenas no lugar arquitetural correto (`/api/v3/`). Se PMSL/MA pedir alertas proativos por email no futuro, isso será sprint dedicado pós-go-live.

Claude vai atualizar o `MAPA_ESCOPO_IMPLANTACAO_2026-05-07_v2.md` marcando "decisão 8 — implementação revisada após auditoria: Opção B (migração para /api/v3/) em vez de remoção".

Também vai atualizar o `PROMPT_ANTYGRAVITY_FASE6.md` removendo T6.8 e renumerando.

**FIM DO PROMPT FASE 4 T4.8.**
