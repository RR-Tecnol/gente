---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-04
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 4/7 — PCCV + Progressão + Jornada + Ponto + VinculoEnum"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
arquivos_lidos_integralmente: 7
total_linhas_lidas: 1220
relatorios_anteriores:
  - "AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_02_MOTOR_FOLHA_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_03_SHADOW_SMOKE_IMPORT_2026-05-07.md"
---

# AUDITORIA PROFUNDA — ETAPA 4: PCCV + PROGRESSÃO + JORNADA + PONTO + VINCULOENUM

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 4 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| 1 | Inventário e arqueologia da raiz | ✅ Concluída |
| 2 | Motor de folha completo | ✅ Concluída |
| 3 | Camada Shadow + Smoke + Import + FolhaParserService | ✅ Concluída |
| **4** | **PCCV + Progressão + Jornada + Ponto + VinculoEnum** | ✅ **Concluída (este relatório)** |
| 5 | Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard | ⏳ Pendente |
| 6 | Models + Database (migrations/seeders) + Domain | ⏳ Pendente |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Escopo da Etapa 4

Arquivos lidos integralmente:

| Arquivo | Linhas | Tamanho |
|---|---|---|
| `app/MyLibs/VinculoEnum.php` | 77 | 2,2 KB |
| `app/Services/Pccv/PccvJornadaViolation.php` | 56 | 1,5 KB |
| `app/Services/Pccv/PccvValidatorService.php` | 176 | 5,5 KB |
| `app/Services/Progressao/ProgressaoFuncionalElegibilidadeService.php` | 188 | 7,4 KB |
| `app/Services/Progressao/ProgressaoFuncionalListagemService.php` | 463 | 18,6 KB |
| `app/Services/Jornada/JornadaRegraParametros.php` | 102 | 3,5 KB |
| `app/Services/ApuracaoPontoService.php` | 158 | 6,5 KB |
| **Total** | **1.220** | **45,2 KB** |

---

## 2. ACHADOS CRÍTICOS DA ETAPA

### 2.1 ACHADO #1 — `ApuracaoPontoService::fechar()` é stub

O método `fechar()` declara no docblock:

> Automação 1: HORA_EXTRA → EventoDetalheFolha
> Automação 2: DESCONTO_FALTA → EventoDetalheFolha

Mas o código tem:

```php
/** @todo Integração real com DetalheFolha — busca o detalhe da folha do mês */
// Por ora, registra o intenção no log. A integração completa com EventoDetalheFolha
// requer o DETALHE_FOLHA_ID que vem do processamento da folha.

$apuracao->update([
    'APURACAO_STATUS' => 'FECHADA',
    'APURACAO_FECHADA_EM' => now(),
    'APURACAO_FECHADA_POR' => Auth::id(),
]);
```

**O fechamento da apuração só atualiza o status — não gera registros de hora extra nem evento na folha.**

Combinado com a Etapa 3, isto fecha um **gap arquitetural completo**:

```
RegistroPonto → ApuracaoPontoService::calcular  ✅ ok
                ↓
                ApuracaoPontoService::fechar    ❌ STUB (só marca status)
                ↓
                HORA_EXTRA                      ❌ não gravada
                ↓
                FolhaParserService::incluirHorasExtras  ✅ lê HORA_EXTRA APROVADA
```

**Como horas extras estão chegando na folha hoje?** Provavelmente lançamento manual via UI, ou `INSERT` direto via outra rota não auditada. Validar com SEMAD urgentemente.

### 2.2 ACHADO #2 — `VinculoEnum` é incompleto vs. motor

`VinculoEnum::resolveVinculo()` reconhece **4 tipos**:
- `SERVIDOR_EFETIVO`
- `CARGO_COMISSAO`
- `ESTAGIARIO`
- `OUTRO` (fallback)

Mas o `MotorFolhaService` (Etapa 2) trata **5 tipos distintos**:
- `comissao_puro`
- `efetivo_cc_m1` (servidor efetivo em cargo comissionado, modelo 1)
- `efetivo_cc_m2` (modelo 2)
- `funcao_confianca`
- default (anuênio padrão)

E `VINCULOS_PISO = ['servico_prestado', 'pss', 'comissao_puro']` mostra que existem ainda mais categorias no domínio (PSS = contratação temporária, prestação de serviço).

**Implicação fiscal:** vínculos não mapeados caem em `OUTRO` no `VinculoEnum` → no `FolhaParserService` vão pro `calcularGenerico`, que **não calcula INSS nem IRRF**. Servidores PSS, prestadores de serviço, e vínculos especiais ficam sem retenção.

### 2.3 ACHADO #3 — Padrão SQLite/MySQL-only se repete

A Etapa 3 já marcou R23 e R24 (`strftime`/`julianday`/`whereYear`-`whereMonth` no FolhaParser). A Etapa 4 confirma o padrão:

- **R39:** `ProgressaoFuncionalListagemService` usa crase MySQL (`p.\`PESSOA_CPF\``) na busca por CPF
- **R40:** `ApuracaoPontoService` usa `whereYear`/`whereMonth` em datetime — full scan em SQL Server

**Sistema foi todo desenvolvido contra SQLite local.** Em SQL Server de produção, vários pontos vão quebrar ou degradar gravemente.

---

## 3. Análise por arquivo

### 3.1 `VinculoEnum.php` (77 linhas) — ⚠️ ATENÇÃO REAL

Detector heurístico por `str_contains` em `lowercase(sigla + ' ' + descricao)`.

**Mapa:**
- `SERVIDOR_EFETIVO`: efetivo, estatutário, rpps, concursado, regime próprio
- `CARGO_COMISSAO`: comissão, das, cds, cargo comissionado, livre nomeação, nomeado
- `ESTAGIARIO`: estágio, estagiário

**Problemas:**
- 🚩 Só 4 tipos vs. 5+ no motor
- 🟡 Termo `'das'` (3 letras) pode dar falso positivo: "pensionista das viúvas" contém `das`. Risco médio.
- 🚩 PSS, prestação de serviço, função de confiança, efetivo+CC sem mapeamento → `OUTRO` → cálculo genérico sem INSS/IRRF

### 3.2 `PccvJornadaViolation.php` (56 linhas) — ✅ BOM DTO

Classe `final` com 5 propriedades públicas tipadas. Método `toArray()` com `code = 'PCCV_JORNADA_EXCEDIDA'`. Cita Lei 4.928/2008 no docblock. Estrutura limpa, mensagem padronizada.

### 3.3 `PccvValidatorService.php` (176 linhas) — ✅ ARQUITETURALMENTE CORRETO

Valida se a soma de `TURNO_CARGA_HORARIA` na semana excede a carga contratual. Roda preventivamente na escrita da escala.

#### Pontos positivos
- ✅ Cache por turno (`$turnoHorasCache`) — evita N+1
- ✅ Suporta semana ISO ou Domingo→Sábado via `gente.pccv.semana_iso`
- ✅ Tolerância configurável (`gente.pccv.tolerancia_horas`, padrão 0.25h = 15min)
- ✅ Schema-tolerante: funciona com ou sem `FUNCIONARIO_CARGA_HORARIA` (fallback `CARGO.CARGO_CARGA_HORARIA`)
- ✅ **Simulação correta:** copia estado atual da semana, aplica mudança hipotética em `$dataCelulaYmd`, soma o resultado. Permite validar antes de gravar.
- ✅ **`normalizarCargaSemanal`:** detecta carga mensal (>60h) e converte pra semanal proporcional via `gente.pccv.carga_mensal_referencia`. Resolve cadastro misto (algumas tabelas guardam mensal, outras semanal).
- ✅ Retorna `null` quando dado falta (sem carga contratual → não valida) — correto
- ✅ `isEnabled()` por config — feature flag

#### Pontos de atenção
- 🟡 **Carga normalizada para int:** 22.5h → 23h (perde 0.5h). Pra escola com jornada 22h30, pode rejeitar quando deveria aceitar.
- 🟡 Não considera afastamentos no validador — esperado, é regra de teto.

### 3.4 `ProgressaoFuncionalElegibilidadeService.php` (188 linhas) — ✅ MUITO BOM

Avalia elegibilidade de progressão.

#### Lógica
1. Busca `PROGRESSAO_CONFIG` por `CARREIRA_ID`, fallback global (`CARREIRA_ID = NULL`)
2. Defaults inteligentes: interstício 24m, nota mínima 7.0, anuênio 1%, estágio probatório 36m
3. Bloqueios:
   - Cargo comissionado (regime `comissionado`)
   - Estágio probatório
   - Interstício não cumprido
   - Nota abaixo do mínimo
   - Penalidade administrativa ativa
4. Calcula próxima referência via `TABELA_SALARIAL` ordenada por `TABELA_REFERENCIA_ORDEM`
5. Última referência da classe → `elegivel_promocao = true`

#### Pontos positivos
- ✅ Cache triplo (`progConfigCache`, `ordensSalariaisCache`, `cargoSalarioCache`)
- ✅ **`pickAvaliacaoOrderCol`** descobre dinamicamente coluna de ordenação (`created_at`, `AVALIACAO_DATA`, `updated_at`, `AVALIACAO_ID`)
- ✅ **`extractNota`** tenta 5 nomes possíveis (`AVALIACAO_NOTA`, `NOTA_FINAL`, `AVALIACAO_MEDIA`, `MEDIA_FINAL`, `NOTA`)
- ✅ Aceita pré-carregamento (`$func->_avaliacao`, `$func->_com_penalidade`)
- ✅ Bloqueios humanizados em português

#### Pontos de atenção
- 🟡 Distinção de penalidade por LIKE em string (`'disciplinar'`, `'suspen'`) — frágil. "Sanção" / "Pena" passa batido.
- 🟡 `(int) Carbon::diffInMonths()` trunca float — pequeno, mas mesmo problema do R16. Aqui é conservador a favor da prefeitura.
- 🟡 Não considera afastamentos longos no interstício — servidor afastado 12m pode progredir antes do tempo efetivo. Validar com SEMAD.

### 3.5 `ProgressaoFuncionalListagemService.php` (463 linhas) — ✅ EXCEPCIONAL

Listagem paginada de progressão + impacto orçamentário com aderência LRF. Serviço mais sofisticado da Etapa 4.

#### Recursos notáveis
- ✅ 3 modos: `paginateTodos`, `paginateElegiveis`, `impactoAgregado`
- ✅ **Chunk pattern keyset:** `where('FUNCIONARIO_ID', '>', $lastId)` — eficiente em volume
- ✅ Cache de total com TTL configurável (`GENTE_PF_ELEGIVEIS_TOTAL_TTL`, 120-600s)
- ✅ **Invalidação versionada:** incrementa contador, sem precisar limpar chaves uma a uma
- ✅ Batch eager-load: `batchAvaliacoes` e `batchPenalidade` carregam todos os IDs do chunk
- ✅ Scalar subquery pra `setor_id` da lotação ativa via `LIMIT 1`
- ✅ Filtro de busca inteligente: dígitos → matrícula/CPF, texto → nome
- ✅ **Cálculo LRF:** RCL × folha × art. 19, classifica em `seguro` (<48.6%), `alerta`, `prudencial` (≥51.3%), `limite_excedido` (≥54%)

#### Problemas
- 🚩 **Crase MySQL na busca por CPF** (linha 405):
  ```php
  $w->orWhereRaw('REPLACE(REPLACE(REPLACE(COALESCE(p.`' . $col . "`,''),'.',''),'-',''),' ','') like ?", [$onlyDigits . '%']);
  ```
  Em SQL Server usa `[col]`, em PostgreSQL `"col"`. **Quebra busca por CPF em SQL Server.**
- 🚩 `Carbon::diffInYears` truncado em loop por servidor — em 30k servidores, anuênio sistematicamente subestimado.
- 🟡 Defaults RCL=50M, folha=2M (linha 226-227) — irrealistas pra PMSL (~30k servidores). Sem warn.
- 🟡 Cache TTL 120-600s pode ser menor que tempo de cálculo em volume.
- 🟡 Primeira requisição em 30k servidores percorre todos sem cache. Considerar warming via job.

### 3.6 `JornadaRegraParametros.php` (102 linhas) — ✅ MUITO BOM

Parâmetros legais com **vigência histórica**. 4 chaves:
- `ponto_tolerancia_minutos` (padrão 15)
- `sobreaviso_acionamento_teto_horas` (padrão 24)
- `sobreaviso_adicional_fracao_hora_normal` (padrão 1/3)
- `valor_hora_referencia_rs` (padrão 74,00)

#### Padrão excelente
- ✅ Vigência via `JRP_VIGENCIA_INI`/`JRP_VIGENCIA_FIM`
- ✅ Fallback duplo: tabela ausente → config → hardcoded
- ✅ Métodos derivados aplicam fórmula `duração × VHN × (1/3)` (BRAIN jornada §4)
- ✅ Aceita data específica (`?DateTimeInterface $em`) — permite reprocessar competências antigas com parâmetros da época
- ✅ `orderByDesc('JRP_VIGENCIA_INI')` retorna mais recente em caso de múltiplas vigências válidas

#### Atenção menor
- 🟡 Fallback silencioso. Em produção, SEMAD precisa cadastrar valor real; hoje sistema usa 74,00 sem avisar.

### 3.7 `ApuracaoPontoService.php` (158 linhas) — ⚠️ PROBLEMA CRÍTICO

#### Pontos positivos
- ✅ Suporta 4 batidas e 2 batidas via `PONTO_CONFIG_FUNCIONARIO.REGIME`
- ✅ **BUG-PONTO-01 corrigido:** desconta intervalo de almoço explicitamente
- ✅ Detecta falta por escala sem registro
- ✅ Idempotente (`firstOrNew` por `(FUNCIONARIO_ID, COMPETENCIA)`)
- ✅ Lê config individual (`INTERVALO_ALMOCO`)
- ✅ Preserva `APURACAO_STATUS` no recalc

#### Problemas

##### P1 — `fechar()` é stub 🔴
Já documentado em §2.1. **A integração ponto→folha está quebrada.**

##### P2 — `limiteAutoAprovacao` lido mas não usado 🟡
```php
$limiteAutoAprovacao = (float) ConfiguracaoSistema::get('PONTO_HORAS_EXTRA_AUTOAPROVAR', 0);
```
Carregado e nunca referenciado. Lixo de código incompleto.

##### P3 — `whereYear`/`whereMonth` em RegistroPonto 🔴
```php
->whereYear('REGISTRO_DATA_HORA', $ano)
->whereMonth('REGISTRO_DATA_HORA', $mes)
```
Não usa índice em SQL Server. Em volume PMSL (~1M registros/mês), full scan degrada gravemente.

##### P4 — `whereHas('detalheEscala', ...)` 🟡
Subquery EXISTS — funciona mas mais lento que JOIN explícito em volume.

---

## 4. Síntese arquitetural — Etapa 4

```
┌─────────────────────────────────────────────────────────┐
│  USUÁRIO marca ponto / RH cadastra escala               │
└────────────────────────┬────────────────────────────────┘
                         │
        ┌────────────────┴────────────────┐
        ▼                                 ▼
┌──────────────────┐            ┌────────────────────────┐
│ RegistroPonto    │            │ Escala / DetalheEscala │
│ (4 ou 2 batidas) │            │ Validado por           │
└────────┬─────────┘            │ PccvValidatorService   │
         │                      │ (carga semanal)        │
         │                      └─────────┬──────────────┘
         │                                │
         ▼                                ▼
┌──────────────────────────────────────────────────────────┐
│ ApuracaoPontoService::calcular                           │
│   - lê PONTO_CONFIG_FUNCIONARIO (regime, intervalo)      │
│   - compara entrada/saída x turno esperado               │
│   - calcula trab/extra/falta                             │
│   - SUPORTA 4 batidas e 2 batidas ✅                      │
└────────────────────┬─────────────────────────────────────┘
                     │
                     ▼
┌──────────────────────────────────────────────────────────┐
│ ApuracaoPontoService::fechar                             │
│   ❌ NÃO grava em HORA_EXTRA                              │
│   ❌ NÃO grava EventoDetalheFolha                         │
│   ✅ Apenas marca APURACAO_STATUS = FECHADA               │
└──────────────────────────────────────────────────────────┘
                     │
                     ▼ (?)  GAP funcional
┌──────────────────────────────────────────────────────────┐
│ FolhaParserService::incluirHorasExtras                   │
│   Lê HORA_EXTRA STATUS=APROVADA                          │
│   Mas HORA_EXTRA é alimentada como? Manual? UI?          │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ ProgressaoFuncionalElegibilidade  (independente do      │
│   acima — usa interstício, nota, penalidades)           │
│   ↓                                                       │
│ ProgressaoFuncionalListagem                              │
│   - paginação keyset                                     │
│   - cache de total                                       │
│   - impacto LRF (RCL × folha × art. 19)                  │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ JornadaRegraParametros (parâmetros com vigência)         │
│   - tolerância ponto                                      │
│   - sobreaviso teto/fração                                │
│   - valor hora referência                                 │
└──────────────────────────────────────────────────────────┘
```

---

## 5. Riscos consolidados da Etapa 4

| ID | Severidade | Item | Validar em |
|---|---|---|---|
| **R37** | 🔴 ALTO | `ApuracaoPontoService::fechar` é **stub** — não gera `HORA_EXTRA` nem `EventoDetalheFolha`. Integração ponto→folha quebrada. Como horas extras chegam na folha hoje? Provavelmente lançamento manual. | Validar urgente com SEMAD |
| **R38** | 🔴 ALTO | `VinculoEnum` reconhece só 4 tipos; motor novo trata 5+. Vínculos PSS, prestação de serviço caem em `OUTRO` → cálculo genérico **sem INSS nem IRRF**. | Pré PoC |
| **R39** | 🔴 ALTO | `ProgressaoFuncionalListagemService` busca CPF com crase MySQL (`p.\`PESSOA_CPF\``). Quebra em SQL Server. | Pré go-live SQL Server |
| **R40** | 🔴 ALTO | `ApuracaoPontoService` usa `whereYear`/`whereMonth` — full scan em SQL Server. Em volume PMSL (~1M registros/mês), degrada. | Pré go-live SQL Server |
| **R41** | 🟡 MÉDIO | `VinculoEnum` tem termo `'das'` (3 letras) — possível falso positivo em descrições. | Pré PoC |
| **R42** | 🟡 MÉDIO | `ProgressaoFuncionalElegibilidadeService` detecta penalidade por LIKE em string. Falha se cadastro usa "Sanção"/"Pena". | Validar dicionário com SEMAD |
| **R43** | 🟡 MÉDIO | Interstício de progressão não desconta afastamentos longos. | Validar com SEMAD |
| **R44** | 🟡 MÉDIO | `ProgressaoFuncionalListagemService::impactoAgregado` defaults RCL=50M e folha=2M se `RECEITA_MUNICIPIO` vazia — irrealista pra PMSL, sem warn. | Pré PoC |
| **R45** | 🟡 MÉDIO | `ApuracaoPontoService::fechar` carrega `$limiteAutoAprovacao` mas nunca usa — `@todo` reconhecido. | Pós-PoC |
| **R46** | 🟢 BAIXO | `PccvValidatorService::normalizarCargaSemanal` arredonda carga para int — perde frações (22h30 → 23h). | Pós-PoC |
| **R47** | 🟢 BAIXO | `ProgressaoFuncionalElegibilidadeService::avaliarEleg` trunca meses (mesmo R16). Aqui é conservador a favor da prefeitura. | Pós-PoC |
| **R48** | 🟢 BAIXO | `JornadaRegraParametros` faz fallback silencioso (`74,00` para hora). Sem log de warn. | Pós-PoC |
| **R49** | 🟢 BAIXO | `ProgressaoFuncionalListagemService` cache TTL 120-600s pode ser menor que tempo de cálculo em volume. | Validar em volume |
| **R50** | 🟢 BAIXO | `ApuracaoPontoService` usa `whereHas` em vez de JOIN — mais lento em volume. | Pós-PoC |

---

## 6. Veredicto da Etapa 4

✅ **`PccvValidatorService` é EXEMPLAR.** Validador preventivo de jornada com cache, semana ISO/Domingo configurável, tolerância configurável, normalização inteligente de carga mensal vs semanal, schema-tolerância. Padrão sênior.

✅ **`ProgressaoFuncionalElegibilidadeService` é MUITO BOM.** Cache triplo, defaults inteligentes, schema-tolerance via `pickAvaliacaoOrderCol` e `extractNota` que tentam múltiplos nomes de coluna. Bloqueios humanizados.

✅ **`ProgressaoFuncionalListagemService` é EXCEPCIONAL.** Keyset pagination, cache versionado, batch eager-load, cálculo LRF aderente ao art. 19. Engenharia de qualidade — exceto pelo bug do CPF com crase MySQL (R39).

✅ **`JornadaRegraParametros` é MUITO BOM.** Vigência histórica, fallback duplo, suporta reprocessamento de competência antiga com parâmetros da época.

🔴 **`ApuracaoPontoService::fechar` é o calcanhar de Aquiles.** Apuração calcula corretamente, mas o fechamento não fecha o ciclo — não gera HE nem evento na folha. **A integração ponto→folha só funciona se alguém lançar HE manualmente.** Item crítico antes do PoC.

🔴 **`VinculoEnum` é incompleto.** Só reconhece 4 tipos; o sistema real tem mais. Servidores PSS / prestação de serviço viram `OUTRO` e o `FolhaParserService` calcula sem INSS/IRRF pra eles. Bug fiscal.

🔴 **Dois problemas SQLite/MySQL-only adicionais** (R39, R40) reforçam o padrão das Etapas 1 e 3 (R1, R23, R24): o sistema foi todo desenvolvido contra SQLite local. Em SQL Server de produção, vários pontos vão quebrar ou degradar.

**Padrão observado:** os serviços puramente PHP/Eloquent (PCCV, Elegibilidade, Listagem, Jornada) são **excelentes**. Os pontos de integração com banco em volume (Apuração, Listagem CPF) ou com regras de negócio externas (VinculoEnum incompleto, fechar() stub) são onde mora a dívida.

### Recomendações pré-PoC

1. **Implementar `ApuracaoPontoService::fechar` por completo** (R37): gerar `HORA_EXTRA` `STATUS=APROVADA` (ou `PENDENTE_APROVACAO` conforme `PONTO_HORAS_EXTRA_AUTOAPROVAR`) a partir das horas extras computadas, e gerar `EventoDetalheFolha` de DESCONTO_FALTA. Ou — alternativamente — confirmar com SEMAD que o fluxo será **lançamento manual** e documentar isso explicitamente.
2. **Expandir `VinculoEnum`** (R38) para reconhecer ao menos: `funcao_confianca`, `efetivo_cc_m1`, `efetivo_cc_m2`, `pss`, `servico_prestado`. Adicionar mapeamento de termos correspondentes.
3. **Substituir crase MySQL** (R39) por sintaxe portável ou by-database. Padrão: `Schema::getConnection()->getQueryGrammar()->wrap($col)`.
4. **Substituir `whereYear`/`whereMonth`** (R40) por `whereBetween('REGISTRO_DATA_HORA', [$inicio, $fim])` com índice em `(FUNCIONARIO_ID, REGISTRO_DATA_HORA)`.
5. **Validar com SEMAD/RH** dicionário de tipos de penalidade administrativa (R42) e regra de afastamentos no interstício (R43).

---

## 7. Próxima etapa

**Etapa 5 — Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard.** Escopo previsto:

- `app/Services/ConsigGeradorService.php`
- `app/Services/ConsigParserService.php`
- `app/Services/EsocialXmlService.php`
- `app/Services/RemessaBancariaService.php`
- `app/Services/CNAB/CNAB240Builder.php`
- `app/Services/DepreciacaoService.php`
- `app/Services/FeriadoService.php`
- `app/Services/HolidayCalendarService.php`
- `app/Services/TabelasImpostoService.php` ⚠️ (referenciado em várias etapas — finalmente lido aqui)
- `app/Services/Dashboard/DashboardOperacionalService.php`

**Objetivos da Etapa 5:**
1. Auditar gerador e parser de consignações (margem 30%, autorização, ocorrência)
2. Auditar geração de XML eSocial (S-1200, S-1210, etc.)
3. Auditar geração de remessa bancária CNAB 240
4. Auditar cálculo de depreciação NBCASP 16.9 (patrimônio)
5. Auditar `TabelasImpostoService` — fonte de verdade fiscal usada por todos os serviços
6. Auditar dashboard operacional

---

*Fim do relatório da Etapa 4.*
