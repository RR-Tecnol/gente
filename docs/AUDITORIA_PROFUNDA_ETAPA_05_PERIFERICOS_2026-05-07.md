---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-05
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 5/7 — Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
arquivos_lidos_integralmente: 10
total_linhas_lidas: 1716
relatorios_anteriores:
  - "AUDITORIA_PROFUNDA_ETAPA_01_INVENTARIO_RAIZ_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_02_MOTOR_FOLHA_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_03_SHADOW_SMOKE_IMPORT_2026-05-07.md"
  - "AUDITORIA_PROFUNDA_ETAPA_04_PCCV_PROGRESSAO_JORNADA_PONTO_2026-05-07.md"
---

# AUDITORIA PROFUNDA — ETAPA 5: PERIFÉRICOS

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 5 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| 1 | Inventário e arqueologia da raiz | ✅ Concluída |
| 2 | Motor de folha completo | ✅ Concluída |
| 3 | Camada Shadow + Smoke + Import + FolhaParserService | ✅ Concluída |
| 4 | PCCV + Progressão + Jornada + Ponto + VinculoEnum | ✅ Concluída |
| **5** | **Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard** | ✅ **Concluída (este relatório)** |
| 6 | Models + Database (migrations/seeders) + Domain | ⏳ Pendente |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Escopo da Etapa 5

Arquivos lidos integralmente:

| Arquivo | Linhas | Tamanho |
|---|---|---|
| `app/Services/TabelasImpostoService.php` | 114 | 4,0 KB |
| `app/Services/ConsigGeradorService.php` | 131 | 5,9 KB |
| `app/Services/ConsigParserService.php` | 86 | 3,2 KB |
| `app/Services/EsocialXmlService.php` | 268 | 8,4 KB |
| `app/Services/CNAB/CNAB240Builder.php` | 192 | 7,4 KB |
| `app/Services/RemessaBancariaService.php` | 364 | 18,0 KB |
| `app/Services/DepreciacaoService.php` | 89 | 3,3 KB |
| `app/Services/FeriadoService.php` | 78 | 2,3 KB |
| `app/Services/HolidayCalendarService.php` | 101 | 4,1 KB |
| `app/Services/Dashboard/DashboardOperacionalService.php` | 293 | 11,1 KB |
| **Total** | **1.716** | **67,7 KB** |

---

## 2. ACHADOS CRÍTICOS DA ETAPA

### 2.1 ACHADO #1 — `TabelasImpostoService` tem INSS RGPS de 2024 (bug fiscal real)

`TabelasImpostoService` é a **fonte de verdade fiscal usada por `FolhaParserService`, `DecimoTerceiroService`, `RescisaoService`, `FeriasService`** — referenciada em todas as etapas anteriores como dependência.

#### Comparação com `MotorFolhaService` (Etapa 2)

| Cálculo | `TabelasImpostoService` (legado) | `MotorFolhaService` (motor novo) |
|---|---|---|
| INSS RGPS faixa 1 teto | **`1412.00`** ❌ (2024) | **`1518.00`** ✅ (2025) |
| INSS RPPS | `0.14` fixo via constante | Lê `RPPS_CONFIG` por vigência |
| IRRF tabela | 2025 (MP 1.206/2024) ✅ | Hardcoded igual ✅ |
| Dedução dependente | `R$ 226,86` ✅ | `226.86` igual |
| Teto INSS RGPS | `7.786,02` ✅ | `7786.02` igual |

**Comentário no código admite a defasagem:**
```php
// ── INSS RGPS 2024 (alíquotas progressivas — DOU 29/12/2023) ─────────────
private const INSS_RGPS = [
    [1412.00, 0.075, 0.00],
    [2666.68, 0.09, 21.18],
    [4000.03, 0.12, 101.18],
    [7786.02, 0.14, 181.18],
];
```

**Impacto prático:** servidores em cargo comissionado (RGPS) com salário entre R$ 1.412,01 e R$ 1.518 estão pagando INSS pela alíquota de 9% sobre a faixa entre 1.412 e seu salário, quando deveriam pagar 7,5% sobre tudo até 1.518. Diferença pequena em valor absoluto (~R$ 0,57/mês), mas é **lei vigente sendo descumprida**.

#### Outro ponto — parcelas dedutivas declaradas mas não usadas

A tupla é `[teto, alíquota, parcela_a_deduzir]`, mas o método `calcularInssRgps()` ignora o terceiro elemento (`$_`) e faz cálculo cumulativo por faixa. **O cálculo dá certo via método cumulativo**, mas o código declara dado que não usa. Mente sobre o que faz.

### 2.2 ACHADO #2 — `EsocialXmlService` é protótipo de demonstração, não produção

268 linhas. Gera 4 eventos: S-1200 (remuneração), S-2200 (admissão), S-2206 (alteração), S-2299 (desligamento). **Múltiplos problemas catastróficos:**

#### P1 — CNPJ da PMSL hardcoded em 4 lugares
```php
$cnpj = '06205244000149';  // CNPJ de São Luís/MA
```
Aparece em todos os 4 métodos. **Impede multi-município** (P6 do plano).

#### P2 — `indRetif=1` (evento original) hardcoded em todos os eventos
Re-emissão do mesmo evento na produção do eSocial vai gerar erro de duplicidade. Não há lógica de retificação.

#### P3 — `tpAmb=1` (PRODUÇÃO REAL do governo) hardcoded
Sem flag de ambiente. Se rodar em testes, vai pra ambiente real do governo.

#### P4 — S-2200 com dados FAKE em campos obrigatórios
```xml
<sexo>M</sexo>                      <!-- todos M -->
<racaCor>1</racaCor>                <!-- todos brancos -->
<estCiv>1</estCiv>                  <!-- todos solteiros -->
<grauInstr>01</grauInstr>           <!-- todos primário -->
<endereco><brasil>
    <tpLograd>Rua</tpLograd>
    <dscLograd>Nao Informado</dscLograd>
    <nrLograd>S/N</nrLograd>
    <bairro>Centro</bairro>
    <cep>65000000</cep>
    <codMunic>2111300</codMunic>
    <uf>MA</uf>
</brasil></endereco>
<vrSalFx>1412.00</vrSalFx>          <!-- mínimo 2024! -->
```
**eSocial vai rejeitar.** Salário fixo está em valor de 2024.

#### P5 — `perApur` recebe data completa em vez de AAAA-MM
```xml
<perApur>{$func->FUNCIONARIO_DATA_INICIO}</perApur>
```
Schema espera `AAAA-MM`, recebe `AAAA-MM-DD`. **Erro de validação garantido.**

#### P6 — `gerarS1200` tem query quebrada
```php
$remuneracaoTotal = DB::table('FOLHA')
    ->where('FUNCIONARIO_ID', $funcionarioId)  // ❌ FOLHA não tem FUNCIONARIO_ID
    ->where('FOLHA_COMPETENCIA', ...)
    ->sum('FOLHA_BRUTO') ?? '0.00';
```

`FOLHA` é a tabela mãe (uma linha por competência). `FUNCIONARIO_ID` está em `DETALHE_FOLHA`. **O método sempre retorna 0 ou explode.**

#### Conclusão

`EsocialXmlService` é um **placeholder/esqueleto**, não código de produção. Provavelmente reconhecido como pendência em `docs/NOTA_S7_ESOCIAL_2026-04-27.md` (a confirmar na Etapa 7).

### 2.3 ACHADO #3 — Dois geradores CNAB 240 coexistem

**`CNAB240Builder` (192 linhas) E `RemessaBancariaService` (364 linhas)** — ambos geram CNAB 240, ambos têm método de geração tradicional + versão `stream*` para grandes volumes. Dois caminhos paralelos.

| Aspecto | `CNAB240Builder` | `RemessaBancariaService` |
|---|---|---|
| Tamanho | 192 linhas | 364 linhas |
| Banco padrão | `001` (BB) | `104` (Caixa) |
| Empresa | `'PREFEITURA MUNICIPAL DE TESTE'` hardcoded | Configurável via construtor |
| Layout | Mock simplificado (segmento A apenas com nome+valor, B só CPF) | Layout completo CNAB 240 v103 (todos os 240 chars preenchidos) |
| Auto-classificação | Comentário admite "MOCK SIMPLIFICADO P/ PoC" | Layout FEBRABAN real |
| Sequência registro | Reset implícito | Tracker explícito |

**`RemessaBancariaService` é o sério.** `CNAB240Builder` é o protótipo. Mas qual é chamado em produção? **Pergunta a responder na Etapa 7.**

#### Problemas adicionais do `RemessaBancariaService`

- 🚩 Header arquivo tem `'GENTE RR TECNOL'` como **nome do banco** — é o nome do software, não do banco. Banco emissor (BB/Caixa) vai estranhar.
- 🚩 Segmento B com CEP `'00000000'` — sem dado real
- 🚩 UF fixa `'MA'` — funciona pra PMSL, mas multi-município pode ter servidor em DF/PR

#### Problemas adicionais do `CNAB240Builder`

- 🚩 `empresaNome = 'PREFEITURA MUNICIPAL DE TESTE'` hardcoded sem setter
- 🟡 Header lote vai com `'30'` em vez de `'030 '` (versão de layout 030 com padding)
- 🟡 Cálculo de `qtdRegistrosTotais = $qtdRegistrosLote + 2` — soma errada se há múltiplos lotes

---

## 3. Análise por arquivo

### 3.1 `TabelasImpostoService.php` (114 linhas) — ⚠️ DESATUALIZADO

#### Pontos positivos
- ✅ IRRF tabela 2025 correta (MP 1.206/2024 + Lei 14.848/2024)
- ✅ Isenção até R$ 2.824,00 com BUG-S2 corrigido
- ✅ Dedução dependente R$ 226,86
- ✅ Teto INSS RGPS R$ 7.786,02
- ✅ INSS RPPS 14% via constante
- ✅ Método `aliquotaEfetivaRgps` para relatórios

#### Problemas
- 🚩 **R51 — INSS RGPS faixa 1 = R$ 1.412 (valor 2024)** quando deveria ser R$ 1.518 (2025)
- 🟡 **R64 — Parcelas dedutivas declaradas mas o método `calcularInssRgps` não as usa.** Cálculo via cumulativo dá certo, mas o código declara dado morto.

### 3.2 `ConsigGeradorService.php` (131 linhas) — ✅ BOM

Lê layout cadastrado (`LAYOUT_CONSIGNATARIA`) com mapeamento JSON de campos por posição [início, fim], tamanho de linha e encoding (NEOCONSIG_*).

#### Pontos positivos
- ✅ **Layout configurável no banco** — não hardcoded como o eSocial
- ✅ Fallback para qualquer layout SAIDA da operadora se não achar o nome esperado
- ✅ Match expression PHP 8 elegante mapeia campos do contrato pra posições
- ✅ Conversão de encoding via `mb_convert_encoding`
- ✅ Linha branca `str_repeat(' ', $tamanhoLinha)` antes de preencher
- ✅ `mb_substr` (não `substr`) para suporte UTF-8

#### Pontos de atenção
- 🟡 **`'rubrica' => '0099'` hardcoded** — rubrica padrão consignação. Pra layout que precisa rubrica diferente, tem que cadastrar tudo no mapeamento ou bater no fallback.

### 3.3 `ConsigParserService.php` (86 linhas) — ✅ BOM

Inverso do gerador. Lê arquivo recebido da operadora e extrai campos pelas posições.

#### Pontos positivos
- ✅ Valida tamanho de linha
- ✅ Reporta erros e log linha-a-linha
- ✅ `mb_substr` correto para UTF-8
- ✅ Aceita só layout `ENTRADA` (gerador só `SAIDA`)

#### Atenção
- 🟡 Não valida **integridade global** do arquivo (header, trailer, totais) — só estrutura linha por linha. Operadora maliciosa ou arquivo corrompido pode passar.

### 3.4 `EsocialXmlService.php` (268 linhas) — 🔴 PROTÓTIPO

Já documentado em §2.2. Resumo:

- 🔴 **R52** — `gerarS1200` tem query quebrada (`FUNCIONARIO_ID` em `FOLHA`)
- 🔴 **R53** — `tpAmb=1` (produção governo) hardcoded
- 🔴 **R54** — Dados pessoais e endereço fake hardcoded em S-2200
- 🔴 **R55** — `perApur` recebe `AAAA-MM-DD` onde schema espera `AAAA-MM`
- 🟡 **R59** — CNPJ PMSL `'06205244000149'` hardcoded em 4 lugares
- 🟡 **R60** — `indRetif=1` original hardcoded — re-emissão impossível

### 3.5 `CNAB240Builder.php` (192 linhas) — ⚠️ MOCK

Já documentado em §2.3. **Comentário admite** "MOCK SIMPLIFICADO P/ PoC".

- 🔴 **R58** — Coexiste com `RemessaBancariaService`; qual é o real?
- 🟡 **R61** — Reconhecido como mock no próprio código

### 3.6 `RemessaBancariaService.php` (364 linhas) — ⚠️ COMPLETO MAS COM HARDCODES

Layout CNAB 240 v103 completo, segmentos A e B com 240 chars todos preenchidos. Suporta `streamGerarPorFolha` com `cursor()` para volume.

#### Pontos positivos
- ✅ Layout FEBRABAN real, não mock
- ✅ Configurável via construtor (banco_codigo, empresa_nome, cnpj, agencia, conta)
- ✅ `streamGerarPorFolha` com `cursor()` — memória O(1) por registro
- ✅ Helpers `lpad`/`rpad`/`assertar240` para garantir tamanho de linha
- ✅ Filtra `whereNull('DETALHE_FOLHA_ERRO')` — só envia detalhes sem erro
- ✅ Sequenciamento explícito de registros

#### Problemas
- 🟡 **R62** — `'GENTE RR TECNOL'` no nome do banco (header arquivo). É o nome do software.
- 🟡 **R63** — CEP `'00000000'` e UF `'MA'` fixos no segmento B
- 🟡 **R58** — Coexiste com `CNAB240Builder`

### 3.7 `DepreciacaoService.php` (89 linhas) — ⚠️ SQLITE-ONLY

Cálculo linear NBCASP 16.9. Categorias com vida útil + valor residual.

#### Pontos positivos
- ✅ Categorias com parâmetros NBCASP corretos:
  - IMÓVEL: 25 anos / 0% residual
  - VEÍCULO: 5 anos / 20%
  - TI: 3 anos / 10%
  - EQUIPAMENTO/MÓVEL: 10 anos / 10%
- ✅ **Não deprecia além do depreciável** — `min($acumulada + $mensal, $maxDepreciavel)`
- ✅ Default razoável se categoria desconhecida (10 anos / 10%)
- ✅ Idempotência via filtro `BEM_DATA_ULTIMA_DEPRECIACAO < competencia`

#### Problema
- 🔴 **R56** — `whereRaw("strftime('%Y-%m', BEM_DATA_ULTIMA_DEPRECIACAO) < ?", [$anoMes])` — SQLite-only

### 3.8 `FeriadoService.php` (78 linhas) — ✅ THIN WRAPPER

Apenas delega pra `Feriado` model com métodos estáticos. Calendário mensal navegável (`isWeekend`, `isHoliday`). Sem complexidade. Sem problemas.

### 3.9 `HolidayCalendarService.php` (101 linhas) — ✅ MUITO BOM

Calendário de feriados nacionais + estaduais (MA) + municipais (São Luís) com cálculo dinâmico de Páscoa via `easter_date($year)`.

#### Pontos positivos
- ✅ **Feriados municipais corretos para São Luís:**
  - São Pedro (29/06)
  - Adesão do Maranhão (28/07)
  - Aniversário de São Luís (08/09)
  - Nossa Senhora da Conceição (08/12)
- ✅ **Suporta overrides** (`calendar_overrides`) com escopo `global`/`sector`/`user`
- ✅ **`pay_multiplier` 2.0/1.0** para feriados não facultativos vs. facultativos
- ✅ Feriados móveis calculados dinamicamente (Carnaval, Sexta Santa, Corpus Christi)

#### Atenção
- 🟢 **R65** — Depende de `easter_date` (extensão `php-calendar`). Sem fallback. Validar se está habilitada no runtime.

### 3.10 `DashboardOperacionalService.php` (293 linhas) — ✅ BOM

KPIs operacionais para `GET /api/v3/dashboard/operacional` (Fase 9A).

#### Métricas
- Total servidores ativos
- Taxa de "furo de escala" (ausência sem substituto)
- Índice MDE elegível (lotações em SEMED)
- VMDE (V_MDE = 0,25 × (T_municipais + T_transferidos))

#### Pontos positivos
- ✅ Schema-tolerância em todas as queries
- ✅ Suporte a `mock_taxa_furo` via config — permite demo sem dados reais
- ✅ Filtro por região via `SETOR_REGIAO` quando coluna existe
- ✅ **Considera substituições** (`SUBSTITUICAO_ESCALA`) ao contar furos
- ✅ Try/catch silenciosos em queries de atestado/afastamento — robusto contra schema parcial
- ✅ Distinct count em índice MDE

#### Problemas
- 🔴 **R57** — `whereRaw("date(ATESTADO_DATA, '+' || ATESTADO_DIAS || ' days') >= ?", ...)` — SQLite-only
- 🟡 **R66** — VMDE retorna nulls com nota explícita ("não integrado"). Honesto, mas é gap funcional.

---

## 4. Síntese — padrão geral consolidado nas 5 etapas

```
ENGENHARIA INTERNA (PHP/Eloquent puro):                      QUALIDADE: ★★★★★
- PccvValidatorService, ProgressaoListagem, JornadaParametros
- ShadowDiff (com BCMath), SnapshotManifestoCanonico
- HolidayCalendarService, ConsigGerador/Parser
- DashboardOperacionalService

COMPATIBILIDADE COM SQL SERVER:                              QUALIDADE: ★★☆☆☆
Ocorrências SQLite-only espalhadas:
- routes/motor.php (PRAGMA table_info)
- FolhaParserService (strftime, julianday)
- ApuracaoPontoService (whereYear, whereMonth)
- ProgressaoFuncionalListagemService (crase MySQL)
- DepreciacaoService (strftime)
- DashboardOperacionalService (date(col, '+N days'))
+ outros menores

INTEGRAÇÕES EXTERNAS REAIS:                                  QUALIDADE: ★☆☆☆☆
- eSocial: PROTÓTIPO com hardcodes catastróficos (R52-R55, R59-R60)
- CNAB 240: DUPLICADO, ambos com hardcodes (R58, R61-R63)

FONTE DE VERDADE FISCAL:                                     QUALIDADE: ★★★☆☆
- TabelasImpostoService: tabela IRRF 2025 ✅
- TabelasImpostoService: INSS RGPS 2024 ❌ (R51)

CONTABILIDADE:                                               QUALIDADE: ★★☆☆☆
- ContabilidadeService incompleto e não-idempotente (R8-R10)
- Patronal hardcoded 14%
- Não chamado pelo motor novo
```

---

## 5. Riscos consolidados da Etapa 5

| ID | Severidade | Item | Validar em |
|---|---|---|---|
| **R51** | 🔴 ALTO | `TabelasImpostoService` tem **INSS RGPS 2024** (teto faixa 1 = R$ 1.412), enquanto `MotorFolhaService` usa 2025 (R$ 1.518). Bug fiscal real em servidores RGPS de baixa faixa. | Pré PoC — validar com SEMFAZ |
| **R52** | 🔴 ALTO | `EsocialXmlService::gerarS1200` faz `where('FUNCIONARIO_ID')` na tabela `FOLHA` (que não tem essa coluna) — método sempre retorna 0 ou explode. | Pré geração eSocial real |
| **R53** | 🔴 ALTO | `EsocialXmlService` usa `tpAmb=1` (PRODUÇÃO governo) hardcoded. Roda em testes contra ambiente real. | Pré qualquer envio |
| **R54** | 🔴 ALTO | `EsocialXmlService::gerarS2200` envia `sexo=M`, `racaCor=1`, `estCiv=1`, endereço "Rua Nao Informado", CEP `65000000` hardcoded para todos. eSocial vai rejeitar. | Pré PoC eSocial |
| **R55** | 🔴 ALTO | `EsocialXmlService` envia `perApur = FUNCIONARIO_DATA_INICIO` (data completa) onde schema espera `AAAA-MM`. Erro de validação garantido. | Pré PoC eSocial |
| **R56** | 🔴 ALTO | `DepreciacaoService` usa `strftime` SQLite-only — quebra em SQL Server. | Pré go-live |
| **R57** | 🔴 ALTO | `DashboardOperacionalService::buscarAtestadosPeriodo` usa `date(col, '+N days')` SQLite-only — quebra em SQL Server. | Pré go-live |
| **R58** | 🔴 ALTO | Dois CNAB 240 coexistem (`CNAB240Builder` + `RemessaBancariaService`). Qual é usado em produção? | Etapa 7 (rotas) |
| **R59** | 🟡 MÉDIO | CNPJ da PMSL `'06205244000149'` hardcoded em 4 lugares no `EsocialXmlService`. Não permite multi-município. | Pré P6 (multi-tenant) |
| **R60** | 🟡 MÉDIO | `EsocialXmlService` `indRetif=1` (original) hardcoded — re-emissão de evento gera duplicidade no governo. | Pré PoC eSocial |
| **R61** | 🟡 MÉDIO | `CNAB240Builder` admite ser "MOCK SIMPLIFICADO P/ PoC" — não é layout FEBRABAN real completo. | Pré PoC bancário |
| **R62** | 🟡 MÉDIO | `RemessaBancariaService` tem `'GENTE RR TECNOL'` no header (nome do banco). É o nome do software. | Pré PoC bancário |
| **R63** | 🟡 MÉDIO | `RemessaBancariaService` envia CEP `'00000000'` e UF fixo `'MA'`. Bancos podem rejeitar TED/PIX automatizado. | Pré PoC bancário |
| **R64** | 🟡 MÉDIO | `TabelasImpostoService::INSS_RGPS` declara parcelas dedutivas mas o `calcularInssRgps` não as usa — código mente sobre o que faz. Cálculo dá certo via cumulativo. | Refactor pós-PoC |
| **R65** | 🟢 BAIXO | `HolidayCalendarService` depende de `easter_date` (extensão `php-calendar`). Sem fallback. | Validar runtime |
| **R66** | 🟢 BAIXO | `DashboardOperacionalService::vmdePayload` retorna nulls com nota explícita ("não integrado"). | Roadmap |
| **R67** | 🟢 BAIXO | `ConsigParserService` não valida integridade global do arquivo (header/trailer/totais). | Pós-PoC |
| **R68** | 🟢 BAIXO | `ConsigGeradorService` tem rubrica `'0099'` hardcoded como padrão. | Pós-PoC |

---

## 6. Veredicto da Etapa 5

✅ **Periféricos puros (Consig, Feriado, Holiday, Dashboard) são bons.** Schema-tolerantes, configuráveis, com cálculos corretos. `HolidayCalendarService` é exemplar (feriados municipais corretos, suporta overrides, calcula Páscoa).

🔴 **`TabelasImpostoService` tem desatualização fiscal.** O serviço que serve de fonte de verdade pro motor legado, 13º, rescisão e férias está com tabela INSS RGPS de 2024. **R51 é o bug fiscal mais real até agora — é lei vigente sendo descumprida.**

🔴 **`EsocialXmlService` é um esqueleto de demonstração**, não código de produção. 4 problemas críticos (R52-R55) que garantem rejeição pelo governo. Provavelmente está documentado em `NOTA_S7_ESOCIAL_2026-04-27.md` como pendência.

🔴 **CNAB duplicado** (R58) — `CNAB240Builder` se autodeclara "mock", `RemessaBancariaService` é o sério, mas qual está em uso? Risco de demonstração apontar pra arquivo errado.

🔴 **Padrão SQLite-only se confirma como dívida sistemática.** Agora 7+ ocorrências espalhadas em 5+ serviços (FolhaParser, ApuracaoPonto, ProgressaoListagem, Depreciacao, DashboardOperacional, motor.php, comunicados.php). **Recomendação forte:** rodar `grep -rn 'strftime\|julianday\|whereYear\|whereMonth\|PRAGMA table_info' app/ routes/` antes do go-live SQL Server.

✅ **`DashboardOperacionalService` é honesto.** Admite VMDE não integrado, suporta mock, considera substituições. Bom serviço.

### Recomendações pré-PoC

1. **Atualizar `TabelasImpostoService::INSS_RGPS`** (R51) para tabela 2025: faixa 1 com teto R$ 1.518.
2. **Marcar `EsocialXmlService` como deprecado/protótipo** explicitamente (R52-R55, R59-R60). Se PoC não inclui demonstração de eSocial, remover do escopo. Se inclui, refatorar pra usar:
   - `config('esocial.cnpj_empregador')` em vez de hardcoded
   - `config('esocial.ambiente')` (1=produção, 2=produção restrita)
   - Coluna real `PESSOA_SEXO`, `PESSOA_RACA_COR`, `PESSOA_ESTADO_CIVIL`, endereço de `PESSOA_ENDERECO` (assumindo schema)
   - Calcular `perApur` via `Carbon::parse(FUNCIONARIO_DATA_INICIO)->format('Y-m')`
   - Corrigir query do S-1200 para `DETALHE_FOLHA join FOLHA`
3. **Decidir qual CNAB usar** (R58). Recomendação: aposentar `CNAB240Builder`, manter só `RemessaBancariaService` com config completa.
4. **Substituir `'GENTE RR TECNOL'` no header CNAB** (R62) pelo nome real do banco emissor.
5. **Auditoria global SQLite-only:** rodar busca por padrões SQLite no codebase inteiro e listar todos os pontos a refatorar antes do SQL Server.

---

## 7. Próxima etapa

**Etapa 6 — Models + Database (migrations/seeders) + Domain.** Escopo previsto:

- `app/Models/` — listar e auditar models críticos (Funcionario, Pessoa, Folha, DetalheFolha, Lotacao, Vinculo, Cargo, Carreira, ApuracaoPonto, Atestado, Afastamento, etc.)
- `app/Domain/Escala/` — `EscalaAusenciaService`, `EscalaWorkflowService`, `EscalaWorkflowStatus`, `MotivoAlteracaoEscala`, `MotivoAlteracaoPolicy`
- `database/migrations/` — focar nas mais recentes vs nossa baseline 30/03 e auditar a sequência cronológica
- `database/seeders/` — validar dados de bootstrap (perfis, permissões, vínculos, eventos canônicos)
- Confirmar R6 (migration `2026_03_30_000041_create_patrimonio_tables` mencionada como inexistente em `rollback_out.txt`)
- Confirmar R14 (campo `FUNCIONARIO_DATA_ADMISSAO` vs `FUNCIONARIO_DATA_INICIO`)

**Objetivos da Etapa 6:**
1. Validar integridade de schema de modelos críticos
2. Confirmar consistência de FKs entre tabelas
3. Mapear migrations adicionadas após 30/03 (sprint shadow + outros)
4. Validar Domain de Escala (estado, transições, motivos)
5. Auditar seeders de bootstrap

---

*Fim do relatório da Etapa 5.*
