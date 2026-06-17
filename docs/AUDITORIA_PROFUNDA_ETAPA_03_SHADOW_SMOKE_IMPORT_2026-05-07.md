---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-03
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 3/7 — Camada Shadow + Smoke + Import + FolhaParserService"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
arquivos_lidos_integralmente: 10
total_linhas_lidas: 2480
relatorios_anteriores:
  - "AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_02_MOTOR_FOLHA_2026-05-07.md"
---

# AUDITORIA PROFUNDA — ETAPA 3: CAMADA SHADOW + SMOKE + IMPORT + FOLHAPARSERSERVICE

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 3 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| 1 | Inventário e arqueologia da raiz | ✅ Concluída |
| 2 | Motor de folha completo | ✅ Concluída |
| **3** | **Camada Shadow + Smoke + Import + FolhaParserService** | ✅ **Concluída (este relatório)** |
| 4 | PCCV + Progressão + Jornada + Ponto | ⏳ Pendente |
| 5 | Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard | ⏳ Pendente |
| 6 | Models + Database (migrations/seeders) + Domain | ⏳ Pendente |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Escopo da Etapa 3

Arquivos lidos integralmente nesta etapa:

| Arquivo | Linhas | Tamanho |
|---|---|---|
| `config/shadow.php` | 25 | 770 B |
| `app/Jobs/ShadowIngestChunkJob.php` | 54 | 1,7 KB |
| `app/Jobs/ShadowCalcChunkJob.php` | 147 | 5,9 KB |
| `app/Jobs/ShadowDiffChunkJob.php` | 110 | 4,3 KB |
| `app/Services/Shadow/SnapshotManifestoCanonicoService.php` | 121 | 4,5 KB |
| `app/Services/Smoke/SmokeTeiaFolhaOptions.php` | 17 | 508 B |
| `app/Services/Smoke/SmokeTeiaFolhaRunner.php` | 700 | 28,8 KB |
| `app/Services/Import/SisfolhaImportOrchestrator.php` | 516 | 19,2 KB |
| `app/Services/Import/SisfolhaQuarantineResolver.php` | 63 | 2,2 KB |
| `app/Services/AfdParserService.php` | 127 | 4,0 KB |
| `app/Services/FolhaParserService.php` | 621 | 25,0 KB |
| **Total** | **2.501** | **96,9 KB** |

---

## 2. ACHADOS CRÍTICOS DA ETAPA

### 2.1 ACHADO #1 — O Smoke Runner reconhece em código a coexistência de motores

`SmokeTeiaFolhaRunner` linha ~226 (Flow 1):

```
'Motor: SKIP — AFASTAMENTO não altera DETALHE_FOLHA
(sem ligação LM→MotorFolhaService v3; ver Motor vs FolhaParser legado).'
```

Os próprios autores escreveram, **em código de produção**, que existe a separação "Motor vs FolhaParser legado" e que afastamentos não afetam o motor v3 ainda. A separação detectada na Etapa 2 é reconhecida internamente, e o smoke testa explicitamente os dois caminhos.

**Implicação:** o caminho legado (`FolhaParserService`) e o caminho novo (`MotorFolhaService`) coexistem por desenho, e o time sabe disso. A homologação shadow (P3) é exatamente a ponte para promover o novo a default.

### 2.2 ACHADO #2 — `FolhaParserService` é o motor atual e tem 4 problemas críticos

O arquivo se autodeclara substituto da stored procedure legada:

```php
/**
 * Motor de cálculo da Folha de Pagamento — GENTE 2.0
 * ...
 * Substitui gradualmente a sp_gera_folha do sistema legado.
 */
```

É o motor que `ProcessarFolhaJob` chama, ou seja, **é o motor que processa folha em produção hoje**.

**Problemas críticos identificados:**
1. **SQLite-only:** usa `strftime` e `julianday` em `whereRaw`/`selectRaw`. Quebra em SQL Server.
2. **N+1 puro:** `Funcionario::with(...)->find()` em loop aninhado escala→detalhes. Não usa `MotorFolhaLoteContext`.
3. **Não processa C2 nem C3:** ignora `ADICIONAL_SERVIDOR` (adicionais permanentes) e `LANCAMENTO_FOLHA` (lançamentos variáveis). Calcula só vencimento + HE/Plantão.
4. **Detalhamento por string:** persistirRubricas usa `LIKE` em descrição para encontrar `EVENTO` — duplica registros se grafia varia.

### 2.3 ACHADO #3 — Divergência funcional entre motores aumenta o desafio do Shadow

A divergência entre `FolhaParserService` (atual) e `MotorFolhaService` (novo) **não é apenas arquitetural — é funcional**:

| Camada | `FolhaParserService` (atual) | `MotorFolhaService` (novo) |
|---|---|---|
| Vencimento base (C1) | ✅ Sim, com TASK-A0 | ✅ Sim |
| Anuênio | ❌ Não calcula | ✅ Calcula com `VINCULO_ANUENIO_PCT` |
| Adicionais permanentes (C2 — `ADICIONAL_SERVIDOR`) | ❌ **Não processa** | ✅ Processa com `ADICIONAL_INCIDE_PREV` |
| Lançamentos variáveis (C3 — `LANCAMENTO_FOLHA`) | ❌ **Não processa** | ✅ Processa com `LANCAMENTO_INCIDE_PREV` |
| Horas extras / Plantões | ✅ Sim, com BUG-HE-02 | ❌ Não processa explicitamente |
| Faltas / Abono afastamento | ✅ Sim, com BUG-S2-07 | Via `LoteContext::possuiAfastamentoSobreposto` (presença, não desconto) |
| Complemento SM | ❌ Não aplica | ✅ Aplica para `VINCULOS_PISO` |
| Consignações | ❌ Não processa | ✅ Soma de `CONSIG_PARCELA` PENDENTE |
| INSS RPPS / RGPS | ✅ 14% RPPS / faixas RGPS | ✅ Dinâmico via `RPPS_CONFIG` / faixas |
| IRRF (com dependentes) | ✅ Sem dependentes (BUG-S2-11 corrigido em ContraCheque) | ✅ Com dependentes 226,86 |

**Implicação grave para o Shadow:** servidores com `ADICIONAL_SERVIDOR` cadastrado (insalubridade, periculosidade, gratificação de função) vão aparecer como **divergência grande** entre legado e novo. Com limiar de R$ 0,03, vão cair como **`FALHA_SISTEMICA_CRITICA`** em massa, quando na verdade é o legado que estava incompleto. Esses casos precisam ser **pré-marcados como `justificavel = true`** antes do diff, ou o limiar precisa ser ajustado por categoria.

---

## 3. Análise por arquivo

### 3.1 `config/shadow.php` (25 linhas) — ✅ SIMPLES E CORRETO

```php
'snapshot_root' => env('SHADOW_SNAPSHOT_ROOT', storage_path('app/shadow')),
'queues' => [
    'etl' => env('SHADOW_QUEUE_ETL', 'queue-shadow-etl'),
    'calc' => env('SHADOW_QUEUE_CALC', 'queue-shadow-calc'),
    'diff' => env('SHADOW_QUEUE_DIFF', 'queue-shadow-diff'),
],
'chunk_size' => (int) env('SHADOW_CHUNK_SIZE', 500),
'limiar_tolerancia_rs' => env('SHADOW_LIMIAR_RS', '0.03'),
```

Filas separadas, chunk 500, tolerância R$ 0,03 padrão. ✅

### 3.2 `ShadowIngestChunkJob.php` (54 linhas) — ✅ ETL IDEMPOTENTE

- ETL apenas registra checkpoint; não persiste dados de servidor (decisão correta — ingest é metadados)
- `IDEMPOTENCY_KEY = competencia|snapshotSha|cpf` — composto pelo SHA do snapshot, garante que rodar 2× com snapshot diferente gera checkpoint distinto
- `PAYLOAD_HASH` SHA-256 por servidor — detecta mudança de payload
- `tries = 3`, trait `Batchable`

### 3.3 `ShadowCalcChunkJob.php` (147 linhas) — ✅ COM RESSALVAS

#### Pontos positivos
- ✅ Idempotência forte por `RUN_ID + IDEMPOTENCY_KEY + ETAPA`
- ✅ Try/catch por folha — uma falha não derruba o lote inteiro
- ✅ Persiste resultado em `SHADOW_RESULTADO_CALC` por `(RUN_ID, COMPETENCIA, CPF)` com upsert
- ✅ Lê resultado calculado de `DETALHE_FOLHA` joinando `FUNCIONARIO/PESSOA` por CPF — chave de negócio estável
- ✅ Mensagens de log distintas por sucesso/falha em `DETALHE`

#### Problemas
- ⚠️ **Chama `MotorFolhaService::calcularFolha()` síncrono.** Para cada folha distinta no chunk, roda o motor inteiro. Se chunk tem 500 servidores e 2 folhas (competências diferentes), o motor processa **TODOS** os servidores das duas folhas, não apenas os 500 do chunk. **Possível over-processing.**
- ⚠️ **Valor convertido para string:** `(string) $resultado->LIQUIDO`. Preserva precisão decimal mas é frágil — depende do driver SQL retornar formato consistente (vírgula vs ponto, casas decimais, sinal).

### 3.4 `ShadowDiffChunkJob.php` (110 linhas) — ✅ EXCELENTE

Esta é a peça mais sofisticada da camada.

- ✅ **Exige BCMath:** guarda no início do `handle()`. Cálculo monetário determinístico.
  ```php
  if (!extension_loaded('bcmath')) {
      throw new \RuntimeException('BCMath não carregado. ShadowDiffChunkJob requer precisão decimal determinística.');
  }
  ```
- ✅ **4 classificações:**
  - `APROVADO_EXATO` — `bccomp($absDelta, '0.00', 2) === 0`
  - `DIVERGENCIA_TOLERAVEL` — `bccomp($absDelta, $limiarRs, 2) <= 0`
  - `DIVERGENCIA_JUSTIFICAVEL` — quando `row['justificavel']` é true
  - `FALHA_SISTEMICA_CRITICA` — fallback
- ✅ **Suporta agregação por rubrica** (não só líquido) — `RUBRICA_CODIGO`, `RUBRICA_TIPO`, `AGREGACAO`
- ✅ **Idempotência composta:** `competencia|diff|cpf|l` (líquido) ou `r{CODIGO}.{TIPO}` (rubrica)
- ✅ **Normalização de moeda:** `money()` aceita vírgula ou ponto, fallback para `0.00` se não numérico
- ✅ **`PAYLOAD_HASH`** SHA-256 do row inteiro

**Padrão sênior. Melhor que muito sistema bancário privado.**

### 3.5 `SnapshotManifestoCanonicoService.php` (121 linhas) — ✅ CRIPTOGRAFICAMENTE SÓLIDO

- ✅ Verifica campos obrigatórios: `competencia`, `schema_version`, `gerado_em`, `fonte_legacy`
- ✅ Aceita chaves `arquivos` ou `files` (compatibilidade)
- ✅ **Verifica SHA-256 com `hash_equals`** — comparação safe (timing-attack proof)
  ```php
  if (!hash_equals(strtolower($esperado), strtolower($h))) {
      $erros[] = "SHA-256 divergente para {$rel}";
  }
  ```
- ✅ Conta linhas de dados em CSV (exclui cabeçalho)
- ✅ Verifica `metadata.json` opcional com `limiar_divergencia`

#### Limitações reconhecidas
- 🟡 **Não valida tipagem de colunas** (§15.6 do plano) — pendência conhecida e documentada
- 🟡 **Hardcoded delimitador `;` em `contarLinhasDadosCsv`** — snapshot com vírgula ou tab conta errado em silêncio

### 3.6 `SmokeTeiaFolhaOptions.php` (17 linhas) — ✅ DTO LIMPO

Classe `final` com propriedades `readonly` (PHP 8.1+). 4 opções: `funcionarioId`, `folhaId`, `competencia`, `checkTenantScopeLog`.

### 3.7 `SmokeTeiaFolhaRunner.php` (700 linhas) — ✅ HONESTO E BEM ESTRUTURADO

**É um teste de integração end-to-end no estilo "smoke", roda dentro de transação com rollback (CLI default).**

#### 5 fluxos
1. **Flow 1 — RH ↔ Escala ↔ Motor ↔ Auditoria:** valida que afastamento LM aparece via `EscalaAusenciaService::indexarPorFuncionarioDia`, valida vínculos duplos (mesma `PESSOA_ID` → múltiplos `FUNCIONARIO_ID` veem a LM), verifica se LM afeta motor (espera `SKIP`), valida amarração `LANCAMENTO_FOLHA → RUBRICA_CODIGO 01` (Licença Médica)
2. **Flow 2 — Progressão ↔ Motor ↔ SPA:** muda `FUNCIONARIO_REFERENCIA` pra próximo degrau na `TABELA_SALARIAL`, recalcula folha, espera `DETALHE_FOLHA_PROVENTOS` mudar
3. **Flow 3 — Organograma ↔ MDE:** cria setor sob unidade, conta lotações ativas
4. **Flow 4a — AUDIT_LOG:** procura `gente_assignment_id` no payload de auditoria
5. **Flow 4b — TenantScope shadow:** lê tail do log `tenant_scope` procurando "shadow"

#### Pontos relevantes

- ✅ **Skip-friendly:** `missingCoreTables()` checa 12 tabelas e retorna `skip` em vez de erro fatal
- ✅ **Roda em transação com rollback** — não suja o banco
- ✅ **Schema-tolerante:** `Schema::hasColumn` antes de inserir colunas opcionais
- ✅ **Honestidade técnica:** Flow 1 reconhece em texto que LM→Motor não está ligado; Flow 2 reconhece SPA não recarrega após progressão; Flow 4a explica que depende de evento prévio. **Esse tipo de honestidade em testes é raro.**

#### Limitações
- ⚠️ Flow 4a só valida que `AUDIT_LOG` contém `gente_assignment_id` — não cria evento durante o smoke. Em ambiente novo dá `skip`.

### 3.8 `SisfolhaImportOrchestrator.php` (516 linhas) — ✅ ETL BEM ESTRUTURADO

**Pipeline:** `loadCsvToStaging → validateRun → applyRun`, com staging em `sisfolha_import_runs` + `sisfolha_import_stg_rows`.

#### Pontos positivos

- ✅ **`loadCsvToStaging`:** SHA-256 do arquivo, run_id, 4 colunas obrigatórias (`cpf`, `matricula`, `nome`, `setor_codigo`), opcionais (`cargo_codigo`, `pis`)
- ✅ **`validateRun`:**
  - CPF validado (11 dígitos)
  - Matrícula vazia detectada
  - **Matrícula duplicada no arquivo** (groupBy COUNT)
  - **Matrícula já existente em FUNCIONARIO** (existsBy)
  - Quarentena para setor inválido
  - Avisos não-bloqueantes para cargo sem de-para
- ✅ **`applyRun`:** chunks transacionais, `try/catch` por chunk (uma falha não derruba o run), audit por chunk e final em `AUDIT_LOG` via `GenteAuditWriter::insertChainedRow` (auditoria encadeada — provável hash chain)
- ✅ **Schema-tolerante:** usa `Schema::hasColumn` antes de inserir colunas opcionais
- ✅ **`upsertPessoa`** lida com `PESSOA_CPF_NUMERO` vs `PESSOA_CPF`
- ✅ **`ensureUsuarioPorCpf`:** senha padrão por config, fallback `bcrypt(Str::random(32))` (senha aleatória inacessível) — força reset no primeiro acesso
- ✅ **Quarentena idempotente** via `SisfolhaQuarantineResolver`

#### Problemas

- 🚩 **`primeiroVinculoId()` retorna o primeiro `VINCULO_ID` do banco** indistintamente. **Todos os importados ficam com o mesmo vínculo.** Não distingue efetivo/comissão/PSS. ETL real não pode rodar assim.
- 🚩 `LOTACAO_DATA_INICIO` só é setado se a coluna existir; sem ela, lotação fica sem data
- 🟡 `USUARIO_LOGIN = $cpf` cru — pode ser política da prefeitura, mas merece confirmação
- 🟡 `USUARIO_PERFIL`: atribui perfil "Funcionario/Funcionário/Externo" se existir; senão usuário fica perfilless

### 3.9 `SisfolhaQuarantineResolver.php` (63 linhas) — ✅ AUTO-CRIAÇÃO IDEMPOTENTE

Cria `UNIDADE` + `SETOR` "MIG-NAO-CLASS" se não existir, retorna ID válido. Configurável via `gente_sisfolha_import.quarentena_setor_id`. Schema-tolerante.

### 3.10 `AfdParserService.php` (127 linhas) — ⚠️ HEURÍSTICO

Parser AFD (Portaria 671/2021 do MTE). Layout 100 chars: NSR(9) + Tipo(1) + Data/hora(12) + PIS(12) + Tipo operação(2).

#### Pontos positivos
- ✅ Valida sequência NSR (gap detection)
- ✅ Parse `DDMMAAAAhhmm → AAAA-MM-DD hh:mm:00`

#### Limitações reconhecidas
- 🟡 **`inferirTipo` por `índice % 4`:** ENTRADA/PAUSA/RETORNO/SAIDA cíclico. Comentário admite: "Em produção, deve-se considerar regras da jornada". Em jornada 12×36 ciclo é ENTRADA/SAIDA (2). Heurística frágil.
- 🟡 **Não resolve PIS → `FUNCIONARIO_ID`** — comentário admite: "(não implementado aqui)". Importação manual obrigatória.

### 3.11 `FolhaParserService.php` (621 linhas) — 🚩 MOTOR ATUAL DE PRODUÇÃO

#### Pontos positivos

- ✅ **Transação envolvendo todo o `processar()`** com rollback em catch — folha não fica meio gravada
- ✅ **`limparFolhaAnterior()`** antes de recalcular — idempotente
- ✅ **BUG-S2-05/06 corrigido:** `cal_days_in_month()` para fevereiro 28/29 dias
- ✅ **BUG-S2-07 corrigido:** abono por afastamentos remunerados (LICENCA_MEDICA/MATERNIDADE/etc.)
- ✅ **BUG-HE-02:** horas extras e plantões aprovados entram como provento, com **recálculo de IRRF** sobre base acumulada
- ✅ **TASK-A0:** ajuste proporcional por admissão/exoneração no mês
- ✅ **`JORNADA_FINANCEIRA_HORAS`** suportada (acordo informal)
- ✅ Usa `TabelasImpostoService` centralizado
- ✅ Distingue 3 tipos de vínculo: `SERVIDOR_EFETIVO` (RPPS 14%), `CARGO_COMISSAO` (RGPS + FGTS 8%), `genérico`
- ✅ FGTS marcado como tipo `'I'` (informativo, não desconta líquido) — semanticamente correto

#### Problemas críticos

##### P1 — SQLite-only nas queries de afastamento (linhas 145–149)

```php
->whereRaw("strftime('%Y-%m', AFASTAMENTO_DATA_INICIO) = ?", [$compFormatada])
->orWhereRaw("strftime('%Y-%m', AFASTAMENTO_DATA_FIM) = ?", [$compFormatada]);
->selectRaw("SUM(CASE WHEN AFASTAMENTO_DATA_FIM IS NULL 
            THEN julianday('now') - julianday(AFASTAMENTO_DATA_INICIO) 
            ELSE julianday(AFASTAMENTO_DATA_FIM) - julianday(AFASTAMENTO_DATA_INICIO) + 1 END)")
```

**`strftime` e `julianday` são funções SQLite que não existem em SQL Server.** Equivalentes:
- `strftime('%Y-%m', col)` → `FORMAT(col, 'yyyy-MM')` ou `CONVERT(VARCHAR(7), col, 120)`
- `julianday(b) - julianday(a) + 1` → `DATEDIFF(day, a, b) + 1`

##### P2 — N+1 puro

Método `apurarFuncionario` chamado em loop aninhado escala→detalhes:

```php
foreach ($escalas as $escala) {
    foreach ($detalhes as $detalhe) {
        $this->apurarFuncionario($folha, $detalhe);  // ← chama Funcionario::with(...)->find()
    }
}
```

Em folha de 30k servidores: ~30k queries Funcionario + 30k Lotacao + 30k Vinculo + 30k PontoConfig + 30k Afastamento. SQLite local sobrevive; SQL Server remoto morre.

##### P3 — Não processa C2 nem C3

`FolhaParserService` calcula apenas:
- Vencimento base (com proporcionalidade de admissão/falta)
- Horas extras / Plantões (BUG-HE-02)
- INSS, IRRF

**Não consulta `ADICIONAL_SERVIDOR` (C2) nem `LANCAMENTO_FOLHA` (C3).** Adicionais permanentes (insalubridade, periculosidade, gratificação de função) ficam fora.

##### P4 — `persistirRubricas` cria `Evento` por descrição via LIKE

```php
$evento = Evento::where('EVENTO_DESCRICAO', 'like', $rubrica['descricao'])->first();
if (!$evento) {
    $evento = Evento::create([...]);
}
```

Duas grafias diferentes ("INSS RPPS (14%)" vs "INSS RPPS 14%") criam Eventos duplicados. Sem dicionário canônico, `EVENTO_ID` que aparece no holerite (4 dígitos zero-pad em `ContraChequeService`) varia conforme ordem de criação.

##### P5 — Detecção de IRRF/INSS por string match exato (linhas 311–315)

```php
fn($r) => in_array($r['descricao'], ['INSS RPPS (14%)', 'INSS RGPS']) ? $r['valor'] : 0
```

Se alguém renomear a rubrica, o recálculo de IRRF pós-HE quebra silenciosamente.

##### P6 — Caso de borda em `calcularServidorEstatutario`

```php
$vencimentoBruto = round($salario / $diasMes * ($diasTrabalhados + $faltas), 2);
```

Combinado com TASK-A0 (admissão proporcional), `$diasTrabalhados` pode ser apenas dias do recorte de admissão. Servidor admitido dia 15 com 1 falta dia 20: bruto = `salário × (16+1)/30 = salário × 17/30`. Isso **excede** o que ele teria direito apenas pela admissão (16/30). Caso de borda fiscal real, vale teste.

---

## 4. Riscos consolidados da Etapa 3

| ID | Severidade | Item | Validar em |
|---|---|---|---|
| **R23** | 🔴 ALTO | `FolhaParserService` usa `strftime` e `julianday` (SQLite-only) em queries de afastamento. Quebra em SQL Server. | Pré go-live SQL Server |
| **R24** | 🔴 ALTO | `FolhaParserService` faz N+1 puro: `Funcionario::with(...)->find()` em loop aninhado. 30k servidores = ~150k queries. | Pré go-live |
| **R25** | 🔴 ALTO | `FolhaParserService` não processa C2 (`ADICIONAL_SERVIDOR`) nem C3 (`LANCAMENTO_FOLHA`). Adicionais permanentes ficam de fora do cálculo. | Validar com SEMAD se adicionais já estão sendo lançados via outro caminho |
| **R26** | 🔴 ALTO | `SisfolhaImportOrchestrator` atribui **primeiro `VINCULO_ID`** indistintamente. ETL real não pode rodar assim. | Pré ETL produção |
| **R27** | 🟡 MÉDIO | `FolhaParserService::persistirRubricas` cria `Evento` por descrição via `LIKE` — duplica registros se grafia varia. | Pré PoC (limpeza EVENTO + dicionário) |
| **R28** | 🟡 MÉDIO | `FolhaParserService` recálculo de IRRF pós-HE detecta INSS por string match exato. Renomeação quebra silenciosamente. | Refactor com EVENTO canônico |
| **R29** | 🟡 MÉDIO | `FolhaParserService::calcularServidorEstatutario`: combinação TASK-A0 + `$faltas` no bruto pode gerar bruto > salário-mês. | Caso de teste |
| **R30** | 🟡 MÉDIO | `ShadowCalcChunkJob` chama `calcularFolha()` síncrono — possível over-processing quando chunk tem múltiplas folhas. | Validar comportamento em volume |
| **R31** | 🟡 MÉDIO | `SnapshotManifestoCanonicoService::contarLinhasDadosCsv` hardcoded delimitador `;`. | Validar formato canônico |
| **R32** | 🟡 MÉDIO | `AfdParserService::inferirTipo` deduz batida por `índice % 4` — não considera regras de jornada. | Validar antes de aceitar AFD em produção |
| **R33** | 🟡 MÉDIO | `AfdParserService` não resolve PIS → `FUNCIONARIO_ID`. Importação manual obrigatória. | Item de roadmap |
| **R34** | 🟢 BAIXO | `SisfolhaImportOrchestrator` atribui perfil só se existir; senão usuário fica perfilless. | Validar seeders de PERFIL |
| **R35** | 🟢 BAIXO | `SnapshotManifestoCanonicoService` não valida tipagem de colunas (§15.6). Pendência reconhecida. | Roadmap shadow |
| **R36** | 🟢 BAIXO | `SmokeTeiaFolhaRunner` Flow 4a depende de evento prévio em `AUDIT_LOG`. | Pós-PoC |

---

## 5. Veredicto da Etapa 3

✅ **Camada Shadow é EXCEPCIONAL.** `ShadowDiffChunkJob` exige BCMath, classifica em 4 níveis com tolerância configurável, suporta diff por líquido OU por rubrica, idempotente por chave composta. `SnapshotManifestoCanonicoService` usa SHA-256 com `hash_equals`. Padrão sênior, melhor que muito sistema bancário privado.

✅ **Smoke Runner é honesto.** Não inventa que tudo está perfeito — reconhece em código que LM→Motor não está ligado, que SPA não recarrega após progressão. Honestidade técnica em testes é rara e valiosa.

✅ **Sisfolha Import bem estruturado.** Pipeline staging → validate → apply, com chunk transacional, audit chain, schema-tolerance. Pronto para ETL real **exceto pelo VINCULO_ID indistinto (R26)**.

🔴 **`FolhaParserService` (motor atual de produção) tem 4 problemas sérios:** SQLite-only nas queries (R23), N+1 escalando ruim (R24), não processa C2/C3 (R25), `EVENTO` duplicado por LIKE (R27). Funciona em ambiente Lab com SQLite. Em SQL Server com 30k servidores, vai estourar.

🟡 **A divergência entre os motores não é só arquitetural — é funcional.** Servidores com `ADICIONAL_SERVIDOR` cadastrado vão aparecer como divergência grande no shadow diff. Com limiar de R$ 0,03, vão cair em massa como `FALHA_SISTEMICA_CRITICA` quando o legado é que está incompleto.

### Recomendações pré-promoção do motor novo

1. **Marcar como `justificavel = true`** todos os servidores com `ADICIONAL_SERVIDOR` cadastrado antes do diff, ou ajustar limiar por categoria
2. **Substituir `strftime` / `julianday`** no `FolhaParserService` por funções compatíveis com SQL Server (R23) — mesmo que o motor seja deprecado, ele ainda processa hoje
3. **Criar dicionário canônico de `EVENTO`** com `EVENTO_CODIGO` estável, e fazer `persistirRubricas` buscar por código em vez de LIKE em descrição (R27)
4. **Corrigir `SisfolhaImportOrchestrator::primeiroVinculoId()`** para resolver vínculo via de-para por padrão de matrícula ou coluna explícita do CSV (R26)
5. **Validar com SEMAD se adicionais permanentes (insalubridade, periculosidade, gratificações)** estão sendo lançados via outro caminho hoje, ou se o `FolhaParserService` está pagando incompleto

---

## 6. Próxima etapa

**Etapa 4 — PCCV + Progressão + Jornada + Ponto.** Escopo previsto:

- `app/Services/Pccv/PccvValidatorService.php`
- `app/Services/Pccv/PccvJornadaViolation.php`
- `app/Services/Progressao/ProgressaoFuncionalElegibilidadeService.php`
- `app/Services/Progressao/ProgressaoFuncionalListagemService.php`
- `app/Services/Jornada/JornadaRegraParametros.php`
- `app/Services/ApuracaoPontoService.php`
- `app/MyLibs/VinculoEnum.php` (referenciado mas não lido ainda)

**Objetivos da Etapa 4:**
1. Validar regras PCCV/PCS conforme normativos São Luís
2. Auditar elegibilidade de progressão funcional (anuênio, mérito, formação)
3. Verificar parametrização de jornada (12×36, 6h, 8h, 30h escolar, 40h)
4. Validar `ApuracaoPontoService` — apuração de banco de horas, atrasos, faltas
5. Confirmar enum de vínculo e seu mapeamento

---

*Fim do relatório da Etapa 3.*
