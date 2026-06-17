# PROMPT ANTYGRAVITY — FASE 3 GENTE v3 (Aposentar motores legados)

> **Cole este prompt no Antygravity APENAS após o fix GAP-MF-04 ter sido aplicado e auditado por Claude.**
> Estimativa total: ~1h Antygravity (auditoria Claude separada: ~30min).
> Pré-condição: Fases 1 + 2-A + 2-B + fix GAP-MF-04 mergeadas. Branch limpa.

---

## CONTEXTO DA FASE 3

Decisão A do Ronaldo, etapa 3: **aposentar os motores legados** agora que `MotorFolhaService` cobre 100% das funcionalidades. Os 3 motores hoje convivendo no código são:

1. **`FolhaParserService` (PHP, 621 linhas)** — chamado por `ProcessarFolhaJob::handle($parser)`. Lê DETALHE_ESCALA_ITEM_FALTA/ATRASO (colunas que NÃO existem no schema oficial) e gera DETALHE_FOLHA via Eloquent. **Aposentar.**
2. **`Folha::reprocessarFolha()` → `sp_gera_folha` T-SQL** — chamado por `FolhaController::alterar`. Stored procedure SQL Server-only. **Aposentar.**
3. **`MotorFolhaService` (NOSSO)** — único motor canônico após Fase 2-B. Chamado pelas rotas SPA Vue 3 em `routes/folha.php` (já em produção real).

### Resultado final desta fase

- `FolhaController::inserir` e `::alterar` chamam `MotorFolhaService` (não mais ProcessarFolhaJob com FolhaParserService nem `reprocessarFolha`).
- `ProcessarFolhaJob` é refatorado para chamar `MotorFolhaService::despacharProcessamentoAssincrono` (consistência com `routes/folha.php`).
- `FolhaParserService.php` movido para `app/Services/_legacy/` (preservar histórico, não deletar — caso seja necessário diff em auditoria TCE).
- `Folha::salvaFolha`, `Folha::processarFolha`, `Folha::reprocessarFolha` marcados como `@deprecated` com aviso de não-uso no código novo.

### Princípios de design

1. **NÃO deletar `FolhaParserService.php`** — apenas mover para `app/Services/_legacy/`. Auditoria TCE-MA pode pedir comparação.
2. **NÃO deletar os métodos T-SQL do `Folha` model** — apenas marcar `@deprecated` para que IDE/análise estática avise.
3. **Smoke test obrigatório** pós-mudança: 3 disparos sintéticos confirmando que `MotorFolhaService::despacharProcessamentoAssincrono` é chamado em ambas as rotas.
4. **NÃO mexer em `routes/web.php`** — como sempre. Fase 4 vai cuidar disso.
5. **NÃO mexer em `routes/folha.php`** — já chama `MotorFolhaService`, está correto.

---

## REGRAS CRÍTICAS DE EXECUÇÃO

1. **Trabalhar em ordem:** T3.1 → T3.2 → T3.3 → T3.4 → T3.5. Cada um depende do anterior.
2. **Um commit por tarefa** (não consolidar — facilita rollback se algo der errado).
3. **Validar cada tarefa** com Select-String ou diff antes de seguir.
4. **Se algum trecho não bater** com o esperado, **PARAR e reportar**.
5. **Tinker dinâmico não é exigido** (PHP 8.1 local não roda artisan). Validação é via leitura textual.

---

## T3.1 — Refatorar `ProcessarFolhaJob` para usar `MotorFolhaService` (~10 min)

**Por que primeiro:** vai eliminar a dependência de `FolhaParserService` no Job. Após isso, podemos mover `FolhaParserService` para `_legacy/` sem afetar nada. ContabilidadeService já é chamado pelo MotorFolha indiretamente via outra cadeia? **NÃO.** ContabilidadeService precisa continuar sendo chamado aqui no Job — preservar essa parte.

**Arquivo:** `app/Jobs/ProcessarFolhaJob.php`

**Trecho atual (arquivo inteiro):**

```php
<?php

namespace App\Jobs;

use App\Models\Folha;
use App\Services\FolhaParserService;
use App\Services\ContabilidadeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * BUG-S2-15 corrigido: Folha::processarFolha() não existe no Model.
 * O job agora usa FolhaParserService::processar() corretamente.
 */
class ProcessarFolhaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $request;
    private ?int $userId;

    public function __construct(array $request, ?int $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    public function handle(FolhaParserService $parser): void
    {
        $folhaId = $this->request['FOLHA_ID'] ?? null;

        if (!$folhaId) {
            Log::warning('[ProcessarFolhaJob] FOLHA_ID não informado — job ignorado.');
            return;
        }

        $folha = Folha::find($folhaId);

        if (!$folha) {
            Log::error("[ProcessarFolhaJob] Folha {$folhaId} não encontrada.");
            return;
        }

        Log::info("[ProcessarFolhaJob] Iniciando processamento da Folha {$folhaId} (competência {$folha->FOLHA_COMPETENCIA}) pelo usuário {$this->userId}.");

        $parser->processar($folha);

        // Gerar lançamentos contábeis automáticos após o processamento
        // Falha contábil não reverte a folha — apenas loga o erro
        try {
            $contabilidade = new ContabilidadeService();
            $resultado = $contabilidade->lancarFolha($folha->FOLHA_ID, (string) $folha->FOLHA_COMPETENCIA);
            Log::info("[ProcessarFolhaJob] Lançamentos contábeis gerados.", [
                'folha_id'    => $folhaId,
                'lancamentos' => $resultado['lancamentos_criados'],
                'proventos'   => $resultado['total_proventos'],
            ]);
        } catch (\Throwable $e) {
            Log::error("[ProcessarFolhaJob] Falha nos lançamentos contábeis — folha não revertida.", [
                'folha_id' => $folhaId,
                'erro'     => $e->getMessage(),
            ]);
        }

        Log::info("[ProcessarFolhaJob] Folha {$folhaId} processada com sucesso.");
    }
}
```

**Trecho corrigido (arquivo inteiro):**

```php
<?php

namespace App\Jobs;

use App\Models\Folha;
use App\Services\ContabilidadeService;
use App\Services\MotorFolhaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Despacha o processamento de folha via MotorFolhaService.
 *
 * Histórico:
 *   - BUG-S2-15: removida dependência de Folha::processarFolha() (não existe no Model).
 *   - Fase 3 (08/05/2026): aposentado FolhaParserService legado. Job agora chama
 *     MotorFolhaService::despacharProcessamentoAssincrono(), que internamente faz batch
 *     de ProcessarLoteFolhaJob (500 servidores por job) + persiste em DETALHE_FOLHA +
 *     EVENTO_DETALHE_FOLHA via PersistenciaRubricasService.
 *
 * Este Job continua existindo para manter a interface da rota legada
 * `FolhaController::inserir`. O caminho de rotas SPA Vue 3 (routes/folha.php) já chama
 * MotorFolhaService diretamente.
 */
class ProcessarFolhaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $request;
    private ?int $userId;

    public function __construct(array $request, ?int $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    public function handle(MotorFolhaService $motor): void
    {
        $folhaId = $this->request['FOLHA_ID'] ?? null;

        if (!$folhaId) {
            Log::warning('[ProcessarFolhaJob] FOLHA_ID não informado — job ignorado.');
            return;
        }

        $folha = Folha::find($folhaId);

        if (!$folha) {
            Log::error("[ProcessarFolhaJob] Folha {$folhaId} não encontrada.");
            return;
        }

        Log::info("[ProcessarFolhaJob] Iniciando processamento da Folha {$folhaId} (competência {$folha->FOLHA_COMPETENCIA}) pelo usuário {$this->userId} via MotorFolhaService.");

        // Fase 3: usa o caminho síncrono in-process do MotorFolha
        // (este Job já está rodando assíncrono — não precisa de novo batch interno).
        $resultado = $motor->calcularFolha((int) $folhaId);

        if (! ($resultado['ok'] ?? false)) {
            Log::error("[ProcessarFolhaJob] MotorFolha retornou erro.", [
                'folha_id' => $folhaId,
                'erro' => $resultado['erro'] ?? 'sem detalhes',
            ]);
            return;
        }

        Log::info("[ProcessarFolhaJob] MotorFolha concluiu cálculo.", [
            'folha_id'        => $folhaId,
            'servidores'      => $resultado['servidores'] ?? 0,
            'total_proventos' => $resultado['total_proventos'] ?? 0,
            'total_descontos' => $resultado['total_descontos'] ?? 0,
            'total_liquido'   => $resultado['total_liquido'] ?? 0,
        ]);

        // Gerar lançamentos contábeis automáticos após o processamento.
        // Falha contábil não reverte a folha — apenas loga o erro
        // (idempotência R7-R10 garante que reprocesso não duplica).
        try {
            $contabilidade = new ContabilidadeService();
            $contabRes = $contabilidade->lancarFolha((int) $folha->FOLHA_ID, (string) $folha->FOLHA_COMPETENCIA);
            Log::info("[ProcessarFolhaJob] Lançamentos contábeis gerados.", [
                'folha_id'    => $folhaId,
                'lancamentos' => $contabRes['lancamentos_criados'],
                'proventos'   => $contabRes['total_proventos'],
            ]);
        } catch (\Throwable $e) {
            Log::error("[ProcessarFolhaJob] Falha nos lançamentos contábeis — folha não revertida.", [
                'folha_id' => $folhaId,
                'erro'     => $e->getMessage(),
            ]);
        }

        Log::info("[ProcessarFolhaJob] Folha {$folhaId} processada com sucesso.");
    }
}
```

**Atenção crítica:** o método chamado é `$motor->calcularFolha((int) $folhaId)`, **NÃO** `despacharProcessamentoAssincrono`. Por quê?

- `despacharProcessamentoAssincrono` cria um Bus::batch de jobs `ProcessarLoteFolhaJob` (assíncrono em chunks de 500).
- Mas este Job **já está rodando assíncrono** na fila. Disparar batch dentro de Job é overhead desnecessário e gera N+1 de jobs aninhados.
- `calcularFolha` é o caminho síncrono do MotorFolha — usa o mesmo `prepararContextoLote` + `calcularLoteParaFuncionarios` em chunks de memória, mas no thread atual.

Para PMSL com ~12.000 servidores, `calcularFolha` síncrono é OK em ambiente de fila assíncrona (uma execução leva alguns minutos por folha). Se a performance for problema futuro, swap para `despacharProcessamentoAssincrono` é trivial.

**Validação:**

```powershell
php -l app/Jobs/ProcessarFolhaJob.php
```

```powershell
Select-String -Path "app/Jobs/ProcessarFolhaJob.php" -Pattern "FolhaParserService"
```
Saída esperada: 0 ocorrências (use de FolhaParserService removido).

```powershell
Select-String -Path "app/Jobs/ProcessarFolhaJob.php" -Pattern "MotorFolhaService|calcularFolha"
```
Saída esperada: 3+ ocorrências.

**Commit:** `refactor(Fase3-T1): ProcessarFolhaJob usa MotorFolhaService no lugar de FolhaParserService`

---

## T3.2 — Refatorar `FolhaController::alterar` para usar `MotorFolhaService` (~5 min)

**Por que:** `FolhaController::alterar` chama `$folha->reprocessarFolha()`, que executa T-SQL `[dbo].[sp_gera_folha]`. Em PMSL com SQL Server vai funcionar mas mantém a stored procedure como dependência crítica. **Aposentar.**

**Arquivo:** `app/Http/Controllers/FolhaController.php`

**Trecho atual (método `alterar`, ~linha 116):**

```php
    public function alterar(FolhaUpdateRequest $request)
    {
        DB::beginTransaction();
        $folha = Folha::buscar($request->FOLHA_ID);
        $folha->fill($request->post());
        $folha->reprocessarFolha();
        // HistoricoFolha::setHistorico($folha);
        DB::commit();

        return response($folha, 200);
    }
```

**Trecho corrigido:**

```php
    public function alterar(FolhaUpdateRequest $request)
    {
        // Fase 3: aposentado Folha::reprocessarFolha() (T-SQL sp_gera_folha SQL Server-only).
        // Reaproveitamos ProcessarFolhaJob (assíncrono) que internamente chama MotorFolhaService::calcularFolha().
        // Isso garante:
        //   - Mesmo motor canônico em ambas as rotas (insert e update)
        //   - Idempotência R7-R10 (lançamentos contábeis re-gerados sem duplicar)
        //   - Audit trail F4 da jornada financeira informal (GAP-MF-05) também é registrado em re-processo
        //   - Compatibilidade SQL Server / SQLite cross-driver
        DB::beginTransaction();
        $folha = Folha::buscar($request->FOLHA_ID);
        $folha->fill($request->post());
        $folha->save(); // persistir alterações de cabeçalho ANTES de re-processar
        DB::commit();

        // Despachar reprocessamento assíncrono — payload deve conter FOLHA_ID
        // para que o Job carregue a folha corretamente.
        ProcessarFolhaJob::dispatch(
            ['FOLHA_ID' => $folha->FOLHA_ID] + $request->post(),
            auth()->id()
        )->afterCommit();

        return response($folha, 200);
    }
```

**Atenção:** o `Folha::buscar($id)` retorna o model com relacionamentos. `->fill($request->post())` aplica as mudanças de cabeçalho (descrição, vínculo, etc.). `->save()` persiste. Em seguida, despachamos o Job para reprocessar.

**Trade-off:** antes era síncrono em-request (T-SQL retornava direto). Agora é assíncrono (response volta com o cabeçalho atualizado mas o cálculo roda em background na fila). Isso é mais consistente com `FolhaController::inserir` que já é assíncrono. **Frontend já lida com isso** — listagem polla status via `historicoUltimo`.

**Validação:**

```powershell
php -l app/Http/Controllers/FolhaController.php
```

```powershell
Select-String -Path "app/Http/Controllers/FolhaController.php" -Pattern "reprocessarFolha"
```
Saída esperada: 0 ocorrências.

```powershell
Select-String -Path "app/Http/Controllers/FolhaController.php" -Pattern "ProcessarFolhaJob"
```
Saída esperada: 2 ocorrências (1 import `use App\Jobs\ProcessarFolhaJob;` + 1 uso em `alterar`; pode haver +1 do `inserir` original = 2 ou 3 dependendo do estado).

**Commit:** `refactor(Fase3-T2): FolhaController::alterar despacha ProcessarFolhaJob no lugar de Folha::reprocessarFolha`

---

## T3.3 — Mover `FolhaParserService` para `app/Services/_legacy/` (~3 min)

**Estratégia:** Criar pasta `app/Services/_legacy/`, mover o arquivo para lá com `git mv` (preserva histórico). Atualizar o namespace dentro do arquivo. **NÃO atualizar imports em outros arquivos** porque T3.1 já removeu o uso.

### Sub-passo 3.3.a — Criar pasta `_legacy` e mover arquivo

```powershell
# Criar pasta se não existir
if (!(Test-Path "app/Services/_legacy")) { New-Item -ItemType Directory -Path "app/Services/_legacy" }

# Adicionar .gitkeep para garantir que pasta vai pro git
if (!(Test-Path "app/Services/_legacy/.gitkeep")) { New-Item -ItemType File -Path "app/Services/_legacy/.gitkeep" }

# Mover arquivo via git (preserva histórico)
git mv app/Services/FolhaParserService.php app/Services/_legacy/FolhaParserService.php
```

### Sub-passo 3.3.b — Atualizar namespace dentro do arquivo

**Arquivo:** `app/Services/_legacy/FolhaParserService.php` (após git mv)

**Trecho atual (linha ~3):**

```php
namespace App\Services;
```

**Trecho corrigido:**

```php
namespace App\Services\_Legacy;
```

**Atenção:** PHP **exige** que namespaces começam com letra maiúscula em cada segmento. `_Legacy` (com underscore + L maiúsculo) é o nome do segmento PHP, mesmo que a pasta no disco se chame `_legacy` (lowercase). PSR-4 case-insensitive em filesystem mas case-sensitive em namespace. **Sempre `_Legacy` no `namespace` do PHP.**

### Sub-passo 3.3.c — Adicionar comentário de @deprecated no topo do arquivo

Adicionar **antes** da linha `namespace App\Services\_Legacy;`:

```php
<?php

/**
 * @deprecated Aposentado em 08/05/2026 (Fase 3). Substituído por App\Services\MotorFolhaService.
 *             Mantido em app/Services/_legacy/ apenas para auditoria comparativa TCE-MA.
 *
 * NÃO USAR EM CÓDIGO NOVO. Esta classe usa colunas DETALHE_ESCALA_ITEM_FALTA / ATRASO
 * que NÃO existem no schema oficial das migrations 2026_03_05_210000_create_escala_tables.php.
 * Funciona em SQLite legado pelo create-on-the-fly do Eloquent, mas pode quebrar em SQL Server.
 *
 * Para reativar (não recomendado), restaurar `app/Services/_legacy/FolhaParserService.php`
 * para `app/Services/FolhaParserService.php` e atualizar namespace de volta.
 */
namespace App\Services\_Legacy;
```

### Sub-passo 3.3.d — Confirmar que nenhum outro arquivo importa `App\Services\FolhaParserService`

```powershell
Get-ChildItem -Path "app", "tests", "database" -Recurse -Filter "*.php" | Select-String -Pattern "App\\Services\\FolhaParserService" | Where-Object { $_.Path -notmatch "_legacy" }
```

**Saída esperada:** 0 ocorrências. Se houver match (provavelmente em `tests/` ou em algum service inativo), reportar a Claude antes de prosseguir.

**Validação:**

```powershell
# Confirmar que arquivo foi movido
Test-Path "app/Services/_legacy/FolhaParserService.php"  # Esperado: True
Test-Path "app/Services/FolhaParserService.php"           # Esperado: False

# Confirmar namespace atualizado
Select-String -Path "app/Services/_legacy/FolhaParserService.php" -Pattern "^namespace"
# Saída esperada: namespace App\Services\_Legacy;
```

```powershell
php -l app/Services/_legacy/FolhaParserService.php
# Esperado: No syntax errors detected
```

**Commit:** `refactor(Fase3-T3): mover FolhaParserService para app/Services/_legacy/ (deprecated)`

---

## T3.4 — Marcar métodos T-SQL do `Folha` model como @deprecated (~5 min)

**Por que:** `Folha::salvaFolha()`, `::processarFolha()` e `::reprocessarFolha()` chamam `[dbo].[sp_gera_folha]` (T-SQL). Após T3.2, **nenhum código novo chama esses métodos**. Mantemos como `@deprecated` para que IDE/análise estática avise se alguém tentar reusar.

**Arquivo:** `app/Models/Folha.php`

**Localização 1 — método `salvaFolha` (linha ~141):**

**Trecho atual:**

```php
    public function salvaFolha($lista_id_setores)
    {
        $retorno = '';
        DB::select("exec [dbo].[sp_gera_folha]?,N'?',?,?,?,N'?',?", array(
            $this->FOLHA_ID ? $this->FOLHA_ID : 'null',
            $this->FOLHA_DESCRICAO,
            $this->FOLHA_TIPO,
            $this->VINCULO_ID,
            $this->FOLHA_COMPETENCIA,
            $lista_id_setores,
            Auth::id(),
            $retorno
        ));
    }
```

**Trecho corrigido (adicionar docblock @deprecated antes do método):**

```php
    /**
     * @deprecated Fase 3 (08/05/2026). Substituído por MotorFolhaService::despacharProcessamentoAssincrono().
     *             Stored procedure T-SQL sp_gera_folha NÃO é mais o motor canônico do GENTE v3.
     *             Mantido apenas para compatibilidade com código legado que possa chamar diretamente.
     */
    public function salvaFolha($lista_id_setores)
    {
        $retorno = '';
        DB::select("exec [dbo].[sp_gera_folha]?,N'?',?,?,?,N'?',?", array(
            $this->FOLHA_ID ? $this->FOLHA_ID : 'null',
            $this->FOLHA_DESCRICAO,
            $this->FOLHA_TIPO,
            $this->VINCULO_ID,
            $this->FOLHA_COMPETENCIA,
            $lista_id_setores,
            Auth::id(),
            $retorno
        ));
    }
```

**Localização 2 — método `processarFolha` (linha ~158):**

**Trecho atual:**

```php
    public static function processarFolha($request, $userId)
    {
```

**Trecho corrigido:**

```php
    /**
     * @deprecated Fase 3 (08/05/2026). Substituído por MotorFolhaService::despacharProcessamentoAssincrono().
     *             Stored procedure T-SQL sp_gera_folha NÃO é mais o motor canônico.
     *             Mantido apenas como API legada — não chamar em código novo.
     */
    public static function processarFolha($request, $userId)
    {
```

**Localização 3 — método `reprocessarFolha` (linha ~187):**

**Trecho atual:**

```php
    public static function reprocessarFolha($folhaId, $userId)
    {
```

**Trecho corrigido:**

```php
    /**
     * @deprecated Fase 3 (08/05/2026). Substituído por ProcessarFolhaJob (que internamente chama MotorFolhaService).
     *             Stored procedure T-SQL sp_gera_folha NÃO é mais o motor canônico.
     *             FolhaController::alterar agora despacha o Job no lugar de chamar este método.
     */
    public static function reprocessarFolha($folhaId, $userId)
    {
```

**Atenção:** **NÃO mexer no Eloquent model `Folha::reprocessarFolha()` versão de instância (sem `static`)** — não existe, este método sempre foi `static`. Ler bem o código antes para confirmar.

**Atenção 2:** `FolhaController::alterar` chamava `$folha->reprocessarFolha()` (sem argumentos, em instância). Vendo o método no model está `public static function reprocessarFolha($folhaId, $userId)`. Isso significa que o FolhaController estava chamando **incorretamente** o método estático em instância — funcionava porque PHP permite mas é warning. Após T3.2, esse uso foi removido. ✅

**Validação:**

```powershell
php -l app/Models/Folha.php
```

```powershell
Select-String -Path "app/Models/Folha.php" -Pattern "@deprecated"
# Esperado: 3 ocorrências (uma por método)
```

```powershell
# Confirmar que nenhum código ATIVO chama esses métodos
Get-ChildItem -Path "app", "tests" -Recurse -Filter "*.php" | Select-String -Pattern "->reprocessarFolha|->salvaFolha|::processarFolha|::reprocessarFolha" | Where-Object { $_.Path -notmatch "_legacy|Folha\.php" }
# Esperado: 0 ocorrências
```

**Commit:** `refactor(Fase3-T4): marcar Folha::{salvaFolha,processarFolha,reprocessarFolha} como @deprecated`

---

## T3.5 — Smoke test final + report

### Sub-passo 3.5.a — Confirmar que código novo não importa FolhaParserService antigo

```powershell
Get-ChildItem -Path "app", "tests", "database", "routes" -Recurse -Filter "*.php" | Select-String -Pattern "App\\\\Services\\\\FolhaParserService" | Where-Object { $_.Path -notmatch "_legacy" }
```
Esperado: 0 ocorrências.

### Sub-passo 3.5.b — Confirmar que `FolhaController` chama `MotorFolhaService` (direta ou indiretamente via Job)

```powershell
Select-String -Path "app/Http/Controllers/FolhaController.php" -Pattern "ProcessarFolhaJob|MotorFolhaService"
```
Esperado: 2+ ocorrências (Job em `inserir` e `alterar`).

### Sub-passo 3.5.c — Confirmar que `ProcessarFolhaJob` chama `MotorFolhaService::calcularFolha`

```powershell
Select-String -Path "app/Jobs/ProcessarFolhaJob.php" -Pattern "MotorFolhaService|calcularFolha"
```
Esperado: 3+ ocorrências (import + type-hint do parâmetro + chamada).

### Sub-passo 3.5.d — Verificar que pasta `_legacy` foi criada e contém o arquivo

```powershell
Get-ChildItem -Path "app/Services/_legacy" -Filter "*.php"
```
Esperado: pelo menos `FolhaParserService.php`.

### Sub-passo 3.5.e — Verificar que docblocks `@deprecated` estão no Folha model

```powershell
Select-String -Path "app/Models/Folha.php" -Pattern "@deprecated"
```
Esperado: 3 ocorrências.

### Sub-passo 3.5.f — Git log das 4 correções

```powershell
git log --oneline -n 6
```
Esperado: 4 commits novos:
- `refactor(Fase3-T1): ProcessarFolhaJob usa MotorFolhaService no lugar de FolhaParserService`
- `refactor(Fase3-T2): FolhaController::alterar despacha ProcessarFolhaJob no lugar de Folha::reprocessarFolha`
- `refactor(Fase3-T3): mover FolhaParserService para app/Services/_legacy/ (deprecated)`
- `refactor(Fase3-T4): marcar Folha::{salvaFolha,processarFolha,reprocessarFolha} como @deprecated`

### Sub-passo 3.5.g — Sintaxe PHP de todos os arquivos modificados

```powershell
php -l app/Jobs/ProcessarFolhaJob.php
php -l app/Http/Controllers/FolhaController.php
php -l app/Services/_legacy/FolhaParserService.php
php -l app/Models/Folha.php
```
Esperado: `No syntax errors detected` em todos.

---

## REPORT TEMPLATE — preencha e devolva ao Ronaldo/Claude

```
═══════════════════════════════════════════════════════════════════
FASE 3 — REPORT EXECUÇÃO ANTYGRAVITY (data/hora: ____)
═══════════════════════════════════════════════════════════════════

CORREÇÕES (cole hash do commit):
[ ] T3.1 ProcessarFolhaJob usa MotorFolhaService ........... commit: ____
[ ] T3.2 FolhaController::alterar usa ProcessarFolhaJob .... commit: ____
[ ] T3.3 FolhaParserService movido para _legacy ............ commit: ____
[ ] T3.4 Folha model métodos T-SQL @deprecated ............. commit: ____

VALIDAÇÕES (cole saídas reais):

V1 (3.5.a) FolhaParserService importado fora de _legacy:
   ___ ocorrências (esperado 0)

V2 (3.5.b) FolhaController chama ProcessarFolhaJob/MotorFolhaService:
   ___ ocorrências

V3 (3.5.c) ProcessarFolhaJob chama MotorFolhaService:
   ___ ocorrências

V4 (3.5.d) ls app/Services/_legacy:
   ___ (esperado: FolhaParserService.php presente)

V5 (3.5.e) @deprecated em Folha.php:
   ___ ocorrências (esperado 3)

V6 (3.5.f) git log -n 6:
   ___

V7 (3.5.g) php -l de todos os arquivos:
   ___ (esperado: 4× "No syntax errors detected")

BUGS ENCONTRADOS DURANTE EXECUÇÃO (não estavam na lista):
   ___

PROBLEMAS / DECISÕES TOMADAS QUE PRECISAM DE CONFIRMAÇÃO:
   ___

TEMPO TOTAL REAL: ___h ___min
═══════════════════════════════════════════════════════════════════
```

---

## PRÓXIMA ETAPA APÓS APROVAÇÃO DA FASE 3

**Fase 5 — Correções no `EsocialXmlService`** (R52-R55, R59, R60).

Por que pular Fase 4 (remoção rotas legadas) primeiro? Porque a Fase 4 é **gradual e por domínio** — pode esperar até a Fase 6 estar próxima. Já a Fase 5 corrige **bugs que impactam integração eSocial** que precisam estar fechados antes do PoC.

A ordem cronológica para o deadline 12/05/2026 é:

```
[✅] Fase 1 — concluída + auditada (08/05 manhã)
[✅] Fase 2-A — concluída + auditada (08/05 tarde)
[✅] Fase 2-B — concluída + auditada (08/05 noite)
[ ] Fix GAP-MF-04 — em fila
[ ] Fase 3 — em fila (~1h Antygravity)
[ ] Fase 5 — sex/sáb (EsocialXmlService bugs, ~1h30)
[ ] Fase 4 — sáb/dom (remoção rotas legadas, gradual por domínio)
[ ] Fase 6 — dom noite + seg madrugada (deploy PMSL)
[ ] PoC — seg 12/05 tarde
```

Margem de segurança continua boa.

**FIM DO PROMPT FASE 3.**
