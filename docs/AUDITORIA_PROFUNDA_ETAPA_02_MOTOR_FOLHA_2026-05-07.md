---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-02
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 2/7 — Motor de folha completo"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
arquivos_lidos_integralmente: 9
total_linhas_lidas: 1458
relatorio_anterior: "AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md"
---

# AUDITORIA PROFUNDA — ETAPA 2: MOTOR DE FOLHA COMPLETO

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 2 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| 1 | Inventário e arqueologia da raiz | ✅ Concluída — `AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md` |
| **2** | **Motor de folha completo** | ✅ **Concluída (este relatório)** |
| 3 | Camada Shadow + Smoke + Import (Shadow*Job, SnapshotManifestoCanonicoService, SmokeTeiaFolhaRunner, SisfolhaImport*, **FolhaParserService**) | ⏳ Pendente |
| 4 | PCCV + Progressão + Jornada + Ponto | ⏳ Pendente |
| 5 | Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard | ⏳ Pendente |
| 6 | Models + Database (migrations/seeders) + Domain | ⏳ Pendente |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Escopo da Etapa 2

Arquivos lidos integralmente nesta etapa (8 + `MotorFolhaService` lido na fase preliminar):

| Arquivo | Linhas | Tamanho |
|---|---|---|
| `app/Services/MotorFolhaService.php` (lido antes da Etapa 1) | 553 | 22 KB |
| `app/Services/MotorFolha/MotorFolhaLoteContext.php` | 126 | 4 KB |
| `app/Jobs/ProcessarFolhaJob.php` | 71 | 2,4 KB |
| `app/Jobs/ProcessarLoteFolhaJob.php` | 46 | 1,2 KB |
| `app/Services/ContabilidadeService.php` | 100 | 4,2 KB |
| `app/Services/ContraChequeService.php` | 98 | 4,1 KB |
| `app/Services/DecimoTerceiroService.php` | 165 | 6,1 KB |
| `app/Services/RescisaoService.php` | 148 | 6,3 KB |
| `app/Services/FeriasService.php` | 111 | 4,2 KB |
| **Total** | **1.418** | **54,5 KB** |

---

## 2. ACHADOS CRÍTICOS DA ETAPA

### 2.1 ACHADO #1 — Existem dois motores paralelos coexistindo

**`ProcessarFolhaJob` (caminho atual de produção) chama `FolhaParserService::processar()`, NÃO `MotorFolhaService`.**

Evidência (linhas 33–35 de `ProcessarFolhaJob.php`):

```php
public function handle(FolhaParserService $parser): void
{
    ...
    $parser->processar($folha);
    ...
}
```

E o comentário de bug fix no topo do arquivo confirma:

> `BUG-S2-15 corrigido: Folha::processarFolha() não existe no Model. O job agora usa FolhaParserService::processar() corretamente.`

#### Os dois caminhos coexistentes

| Caminho | Quem dispara | Quem executa | Arquitetura |
|---|---|---|---|
| **A — Legado (atual de produção)** | `ProcessarFolhaJob` (job tradicional, fila default) | `FolhaParserService::processar()` | A confirmar na Etapa 3 |
| **B — Novo (3 camadas)** | `MotorFolhaService::despacharProcessamentoAssincrono()` → `ProcessarLoteFolhaJob` → `MotorFolhaService::calcularLoteParaFuncionarios()` | `MotorFolhaService` com `LoteContext` | C1 / C2 / C3, Bus Batch, upsert |

**Interpretação:** isto é **coerente com o plano P3** (homologação matemática shadow antes de promover o motor novo a default), porém precisa estar explícito na governança. Enquanto o motor novo roda em modo shadow, o `FolhaParserService` continua como caminho de produção.

#### Fluxo arquitetural observado

```
┌──────────────────────────────────────────────────────────────────┐
│  USUÁRIO clica "fechar folha"                                    │
└────────────────────────┬─────────────────────────────────────────┘
                         ▼
            ┌────────────────────────┐
            │  Controller / Rota     │
            └────────┬───────────────┘
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
┌──────────────────┐    ┌──────────────────────────────────┐
│ ProcessarFolhaJob│    │ MotorFolhaService                │
│ (LEGADO,         │    │ ::despacharProcessamento         │
│  CAMINHO ATUAL)  │    │  Async()                         │
└─────┬────────────┘    └──────────┬───────────────────────┘
      │                            │ Bus::batch
      ▼                            ▼
┌──────────────────┐    ┌──────────────────────────────────┐
│ FolhaParser      │    │ ProcessarLoteFolhaJob × N        │
│ Service          │    │ (Batchable, tries=3)             │
│ ::processar()    │    └──────────┬───────────────────────┘
└─────┬────────────┘               │
      │                            ▼
      ▼                  ┌──────────────────────────────────┐
┌──────────────────┐     │ MotorFolhaService                │
│ Contabilidade    │     │ ::calcularLoteParaFuncionarios   │
│ Service          │     │   (C1 + C2 + C3 + INSS + IRRF +  │
│ ::lancarFolha    │     │    consignações + complemento SM)│
│ (linkado!)       │     │   ↓                              │
└──────────────────┘     │   upsert DETALHE_FOLHA           │
                         └──────────────────────────────────┘
                              ↑
                              ❌ ContabilidadeService NÃO chamado aqui
```

### 2.2 ACHADO #2 — `ContabilidadeService` está fora do motor novo

`ProcessarFolhaJob` (caminho legado) chama `ContabilidadeService::lancarFolha()` ao final, dentro de try/catch (folha não é revertida em caso de falha contábil — decisão correta).

`ProcessarLoteFolhaJob` (caminho novo) **não chama** `ContabilidadeService`. `MotorFolhaService::despacharProcessamentoAssincrono()` apenas faz `Bus::batch($jobs)->dispatch()` e retorna, sem callback de finalização.

**Consequência:** quando o motor novo for promovido a default, lançamentos contábeis não serão gerados automaticamente.

**Solução técnica:** adicionar callback `then()` no Bus Batch que chame `ContabilidadeService::lancarFolha($folhaId, $competencia)` após todos os lotes terem sido processados com sucesso.

### 2.3 ACHADO #3 — `ContabilidadeService` é incompleto e não-idempotente

Análise de `ContabilidadeService::lancarFolha()` (100 linhas):

- 🚩 **Patronal RPPS hardcoded em 14%** (`$patronal = round($totalProventos * 0.14, 2)`). Comentário admite: "estimativa 14%". A patronal RPPS de São Luís pode ser diferente (geralmente patronal é ~22%, e 14% é a parte do servidor). Deveria buscar de `RPPS_CONFIG` (mesma fonte que `MotorFolhaService::resolverAliquotaRpps()` usa para a parte do servidor).
- 🚩 **Lança apenas vencimentos brutos e patronal** — falta lançamento de:
  - INSS/RPPS retido do servidor (D `2.1.3.1.01` / C `2.1.3.2.01`)
  - IRRF retido (D `2.1.3.1.01` / C `2.1.4.x.xx` IRRF a recolher)
  - Consignações retidas (D `2.1.3.1.01` / C `2.1.x.x.xx` por consignatária)
  - PCASP completo de folha tem ~6–8 lançamentos.
- 🚩 **Não é idempotente** — rodar 2x cria 2x os lançamentos. Falta:
  ```php
  $jaLancado = DB::table('LANCAMENTO_CONTABIL')
      ->where('ORIGEM_TIPO', 'FOLHA_PAGAMENTO')
      ->where('ORIGEM_ID', $folhaId)
      ->exists();
  if ($jaLancado) { /* deletar antes ou retornar erro */ }
  ```
- ✅ Tem `ORIGEM_TIPO` / `ORIGEM_ID` apontando pra folha (rastreabilidade).
- ✅ Pega `CONTA_ID` por código PCASP (boa prática — código é estável, ID muda por ambiente).

---

## 3. Análise arquivo por arquivo

### 3.1 `MotorFolhaLoteContext.php` — ✅ EXCELENTE

- 126 linhas, classe `final`, tipos estritos PHP 8
- Construtor recebe: `competenciaYm`, `cargoSalarioPorFuncionario`, `afastamentosPorFuncionario`, `avaliacoesPorFuncionario`
- **Normaliza competência para `YYYY-MM`** via `substr($competenciaYm, 0, 7)` — resolve a discrepância `YYYYMM` vs `YYYY-MM` que estava nas userMemories da baseline 30/03
- `intervaloSobrepoeCompetencia()` faz parsing seguro com try/catch
- ⚠️ **`fatorProgressaoPorDesempenho()` retorna 1.0 fixo** com comentário: "regra São Luís futura lê melhorNotaFinal". Avaliação de desempenho **não afeta** o cálculo do anuênio na prática. Documentado como placeholder.

### 3.2 `ProcessarFolhaJob.php` — ⚠️ ATENÇÃO

- 71 linhas
- Caminho legado (chama `FolhaParserService`)
- ✅ Bom: falha contábil é capturada em try/catch e logada — folha não é revertida
- ✅ Bom: logs estruturados com array de contexto
- 🚩 **Não declara `$tries`** — usa default Laravel (1 tentativa). Pra produção real, deveria ser `public int $tries = 3` igual ao `ProcessarLoteFolhaJob`

### 3.3 `ProcessarLoteFolhaJob.php` — ✅ ÓTIMO

- 46 linhas, enxuto
- `tries = 3`, trait `Batchable`
- Checa `$this->batch()?->cancelled()` antes de processar (permite cancelamento parcial via `Bus`)
- Idempotente: re-deduplica IDs, re-prepara contexto a cada tentativa
- Lança `\RuntimeException` com mensagem em caso de falha (Laravel re-enfileira por causa do `tries=3`)

### 3.4 `ContabilidadeService.php` — ⚠️ PROBLEMA REAL

- 100 linhas
- Cria 2 lançamentos: vencimentos (D `3.1.1.1.01` / C `2.1.3.1.01`) e patronal IPAM (D `3.1.2.1.01` / C `2.1.3.2.01`)
- Problemas críticos: ver Achado #3 acima (patronal hardcoded 14%, descontos não lançados, não idempotente)

### 3.5 `ContraChequeService.php` — ⚠️ DÉBITO TÉCNICO RUIM

- 98 linhas
- Gera holerite em PDF via `Barryvdh\DomPDF`

#### Pontos positivos

- ✅ **BUG-S2-09 corrigido:** classifica provento/desconto por `EVENTO_TIPO` em vez de `strpos` no nome
- ✅ **BUG-S2-11 corrigido:** usa `DETALHE_BASE_IRRF` e `DETALHE_BASE_PREV` reais do motor em vez de hardcoded
- ✅ Mascara CPF antes de imprimir (`***.XXX.XXX-**`) — LGPD ok
- ✅ Formata moeda em pt-BR (`number_format($v, 2, ',', '.')`)

#### Problemas

- 🚩 **Empresa hardcoded:** `'empresa_nome' => 'PREFEITURA MUNICIPAL DE TESTE'` e `'empresa_cnpj' => '00.000.000/0001-00'`. Deveria vir de `CONFIGURACAO_SISTEMA`.
- 🚩 **Referência mockada:** `'referencia' => '00'` com comentário `// Mock da referência (Qtd dias, horas, etc)`. Quantidade de horas/dias por evento não aparece no holerite — servidor não vê quanto de hora extra, falta, adicional noturno foi computado.
- 🚩 **Precedência de `??` errada na lotação:**
  ```php
  'lotacao' => $lotacao->setor->SETOR_NOME ?? '' . ' / ' . $lotacao->setor->unidade->UNIDADE_NOME ?? '',
  ```
  PHP avalia como `($SETOR_NOME) ?? ('' . ' / ' . $UNIDADE_NOME) ?? ''`. Quando `SETOR_NOME` existe, unidade nunca aparece. **Bug.** Forma correta:
  ```php
  'lotacao' => ($lotacao->setor->SETOR_NOME ?? '') . ' / ' . ($lotacao->setor->unidade->UNIDADE_NOME ?? ''),
  ```
- 🚩 **Campo possivelmente inexistente:** `$detalheFolha->funcionario->FUNCIONARIO_DATA_ADMISSAO`. O resto do sistema usa `FUNCIONARIO_DATA_INICIO` (ver `MotorFolhaService` linha ~256, `RescisaoService`, `DecimoTerceiroService`). Se o campo não existe, `strtotime(null) → false → date('d/m/Y', false) = '01/01/1970'`.

### 3.6 `DecimoTerceiroService.php` — ✅ BOM, mas com gaps

- 165 linhas
- Lógica das 3 modalidades correta:
  - 1ª parcela = salário/2 sem tributação ✅
  - 2ª parcela = proporcional - INSS - IRRF - adiantamento ✅
  - Rescisório = proporcional aos meses ✅
- Usa `TabelasImpostoService` (centralizado) ✅
- Cálculo de meses (regra ≥15 dias = mês completo) está correto ✅
- Upsert por `(FUNCIONARIO_ID, DT_ANO, DT_TIPO)` permite recalcular sem duplicar ✅

#### Problemas

- 🚩 **Não usa `MotorFolhaLoteContext`** — abre N+1 potencial em volume grande
- 🚩 **Base de cálculo incompleta:** `COALESCE(c.CARGO_SALARIO, f.FUNCIONARIO_SALARIO, 0)` — usa salário do cargo OU do funcionário, não passa pelo motor de 3 camadas. **Não considera adicionais permanentes (C2) nem lançamentos (C3)**. Em geral, 13º deve incluir adicionais incorporados.
- 🚩 **`'created_at' => now()` no updateOrInsert** — em update sobrescreve o `created_at` original. Só `updated_at` deveria estar no array de update. Bug menor.

### 3.7 `RescisaoService.php` — ✅ BOM, com 1 bug fiscal

- 148 linhas
- Cobertura completa de verbas: saldo de salário, férias prop+1/3, férias vencidas+1/3, 13º proporcional, FGTS+multa 40% (se RGPS), IRRF
- Usa `TabelasImpostoService` ✅

#### Problemas

- 🚩 **IRRF com base tributável incompleta:** `$baseTributavel = $feriasVencidas + $feriasVencidasTercio + $decimoProporcional`. **Não inclui** `feriasProp + feriasPropTercio`. Férias proporcionais TAMBÉM são tributáveis em rescisão (regra geral, salvo dispensa sem justa causa que tem regra específica). **Possível bug fiscal.**
- 🚩 **Truncamento de meses:** `min((int) $inicioAquisitivo->diffInMonths($dataExon), 12)` — `diffInMonths` retorna float; cast para int trunca. Se servidor sai depois de 11.5 meses, vira 11 em vez de 12 (regra ≥15 dias = mês completo, vide DecimoTerceiroService, deveria valer aqui também).
- 🚩 **Contagem de férias vencidas duvidosa:** `$periodosTotais = max(0, $anosCompletos - $gozadas)` onde `$gozadas` conta TODAS as férias gozadas históricas, não só as do período aquisitivo vencido. Pode subestimar. **Validar com SEMAD.**
- 🚩 **FGTS hardcoded em 0.08 (8%)** — está correto pela CLT, mas deveria estar em config.
- 🚩 **Não usa `MotorFolhaLoteContext`** — mesmo problema do 13º (lê `CARGO_SALARIO` direto, ignora C2/C3).
- 🚩 **`try/catch` silenciosa em férias vencidas** — `} catch (\Throwable $e) {}` engole erro. Se a tabela `FERIAS` não existir ou der timeout, conta como zero férias vencidas. Subestima em silêncio.

### 3.8 `FeriasService.php` — ✅ BOM, com promessa quebrada

- 111 linhas
- Cálculo correto: base proporcional aos dias, +1/3, INSS sobre base total, IRRF sobre (base - INSS)
- Usa `TabelasImpostoService` ✅
- Transação no `aprovar()` ✅

#### Problemas

- 🚩 **Promessa não cumprida:** docblock do método `aprovar()` diz "calcula valores, persiste **e gera lançamento em DETALHE_FOLHA**" mas o código só atualiza a tabela `FERIAS`. **Férias aprovadas não vão para folha automaticamente.** Quem garante o pagamento?
- 🚩 **Mesmo problema de base:** `COALESCE(c.CARGO_SALARIO, f.FUNCIONARIO_SALARIO, 0)` — não passa pelo motor.

---

## 4. Riscos consolidados da Etapa 2

| ID | Severidade | Item | Validar em |
|---|---|---|---|
| **R7** | 🔴 ALTO | Caminho atual de produção é o legado (`FolhaParserService`), não o motor novo. Promoção depende da homologação shadow (P3). | Etapa 3 |
| **R8** | 🔴 ALTO | `ContabilidadeService` não é chamado pelo motor novo. Quando promover, lançamentos contábeis não saem. | Pré-promoção |
| **R9** | 🔴 ALTO | `ContabilidadeService` tem patronal de **14% hardcoded** (deveria buscar de `RPPS_CONFIG`) e **não lança descontos**. PCASP incompleto. | Pré go-live |
| **R10** | 🔴 ALTO | `ContabilidadeService` **não é idempotente** — rodar 2x cria 2x os lançamentos. | Pré go-live |
| **R11** | 🟡 MÉDIO | `ContraChequeService`: `empresa_nome` e CNPJ **hardcoded** como "PREFEITURA MUNICIPAL DE TESTE". | Pré PoC |
| **R12** | 🟡 MÉDIO | `ContraChequeService`: `referencia => '00'` mock — quantidade de horas/dias por evento não aparece. | Pré PoC |
| **R13** | 🟡 MÉDIO | `ContraChequeService`: precedência de `??` errada na lotação — unidade não aparece. | Pré PoC |
| **R14** | 🟡 MÉDIO | `ContraChequeService`: lê `FUNCIONARIO_DATA_ADMISSAO` (campo possivelmente inexistente; padrão é `FUNCIONARIO_DATA_INICIO`). | Etapa 6 (validar schema) |
| **R15** | 🟡 MÉDIO | `RescisaoService`: IRRF sobre base incompleta — não inclui férias proporcionais + 1/3 (que são tributáveis). | Validar com SEMAD/PGM |
| **R16** | 🟡 MÉDIO | `RescisaoService`: `(int) diffInMonths()` trunca fração — pode subestimar férias proporcionais em ~1/12. | Validar com SEMAD |
| **R17** | 🟡 MÉDIO | `FeriasService::aprovar()`: docblock promete lançamento em `DETALHE_FOLHA` mas não gera. Férias aprovadas não vão pra folha automática. | Pré go-live |
| **R18** | 🟢 BAIXO | `DecimoTerceiroService`, `RescisaoService`, `FeriasService` não usam `MotorFolhaLoteContext` — leem `CARGO_SALARIO` direto, ignorando adicionais permanentes (C2). | Refactor pós-PoC |
| **R19** | 🟢 BAIXO | `MotorFolhaLoteContext::fatorProgressaoPorDesempenho` retorna 1.0 fixo — avaliação de desempenho não afeta anuênio. Placeholder documentado. | Etapa 4 (Progressão) |
| **R20** | 🟢 BAIXO | `ProcessarFolhaJob` não declara `$tries` — usa default 1. | Pós-PoC |
| **R21** | 🟢 BAIXO | `DecimoTerceiroService` faz `'created_at' => now()` em `updateOrInsert`, sobrescreve em update. | Pós-PoC |
| **R22** | 🟢 BAIXO | `RescisaoService` tem `try/catch` silenciosa em contagem de férias vencidas — engole erro. | Pós-PoC |

---

## 5. Veredicto da Etapa 2

✅ **Motor novo (`MotorFolhaService` + `LoteContext` + `ProcessarLoteFolhaJob`) está bem arquitetado.** Bus Batch, transações, upsert, eager-load. Padrão sênior.

🟡 **Motor novo NÃO é o caminho de produção atual.** Quem processa folha hoje é o `FolhaParserService` chamado pelo `ProcessarFolhaJob` legado. Coerente com o plano P3 (homologação shadow antes de promover), mas precisa estar explícito na governança.

🔴 **Quando promover o motor novo a default, vão precisar ser feitas 3 coisas:**
1. Adicionar callback `then()` no Bus Batch chamando `ContabilidadeService::lancarFolha()`
2. Tornar `ContabilidadeService` idempotente (`exists` em `LANCAMENTO_CONTABIL` por `ORIGEM_TIPO/ORIGEM_ID`)
3. Completar `ContabilidadeService`: descontos retidos, patronal de `RPPS_CONFIG` em vez de hardcoded 14%

🟡 **13º, Rescisão e Férias estão funcionalmente corretos mas isolados.** Não usam o motor de 3 camadas. Bom o suficiente pro PoC, mas dívida técnica clara para resolver depois (ignoram adicionais incorporados).

🔴 **`ContraChequeService` tem 4 bugs sérios** que vão aparecer pra qualquer servidor olhando o holerite no PoC: empresa hardcoded como "PREFEITURA MUNICIPAL DE TESTE", referência mockada como "00", lotação concatenada errada, campo de data possivelmente errado.

🟡 **`RescisaoService` tem 1 bug fiscal real** — IRRF sobre base incompleta. Validar com SEMAD/PGM antes de processar primeira rescisão real.

---

## 6. Próxima etapa

**Etapa 3 — Camada Shadow + Smoke + Import.** Escopo previsto:

- `app/Services/FolhaParserService.php` ⚠️ **CRÍTICO** — é o caminho atual de produção, precisa ser auditado a fundo
- `app/Jobs/ShadowIngestChunkJob.php`
- `app/Jobs/ShadowCalcChunkJob.php`
- `app/Jobs/ShadowDiffChunkJob.php`
- `app/Services/Shadow/SnapshotManifestoCanonicoService.php`
- `app/Services/Smoke/SmokeTeiaFolhaRunner.php`
- `app/Services/Smoke/SmokeTeiaFolhaOptions.php`
- `app/Services/Import/SisfolhaImportOrchestrator.php`
- `app/Services/Import/SisfolhaQuarantineResolver.php`
- `app/Services/AfdParserService.php` (se relacionado a parsing)
- `config/shadow.php`

**Objetivos da Etapa 3:**
1. Auditar o `FolhaParserService` — é o motor atualmente em produção
2. Validar arquitetura da camada Shadow (ETL → CALC → DIFF)
3. Confirmar que `SnapshotManifestoCanonicoService` valida snapshots de forma criptograficamente sólida (SHA256, contagem de linhas)
4. Entender como o `SmokeTeiaFolhaRunner` testa o pipeline
5. Auditar o orquestrador de import do Sisfolha (ETL real do legado)
6. Confirmar Poison Pill / DLQ na fila eSocial

---

*Fim do relatório da Etapa 2.*
