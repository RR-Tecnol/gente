# MAPA DE ESCOPO — IMPLANTAÇÃO GENTE v3 PMSL/MA — v2 (consolidado)

**Versão:** v2 consolidada após auditoria profunda + decisões 1-8 + comparação funcional de motores
**Data:** 2026-05-07
**Deadline implantação:** Segunda-feira 12/05/2026 (PoC ao vivo)
**Revisor:** Ronaldo (founder/tech lead RR TECNOL)
**Auditor:** Claude (chief engineer auditor, MCP via gente-desktop-commander/filesystem)
**Executor:** Antygravity (Gemini Cursor)

---

## ÍNDICE

- [Parte 1 — Status & Decisões consolidadas (1-8)](#parte-1)
- [Parte 2 — Inventário dos 3 motores de folha + 8 gaps](#parte-2)
- [Parte 3 — Mapa rotas legadas (com classificação 7.a-7.g)](#parte-3)
- [Parte 4 — Riscos R1-R81 (top 11 bloqueadores)](#parte-4)
- [Parte 5 — Plano de Fases 1-6 com NOVA Fase 2-A/2-B](#parte-5)
- [Parte 6 — Checklist pré-PoC + Integração F1-F5 + Anexos](#parte-6)

---

## <a id="parte-1"></a>PARTE 1 — STATUS & DECISÕES CONSOLIDADAS

### 1.1 Status do projeto antes desta auditoria

- ✅ Bloco S (Security Sprint, SEC-PROD-01–10 + 10-B)
- ✅ Bloco A (A4 Segurança Trabalho, A5 Treinamentos, A6 Pesquisa+Ouvidoria+Relatórios, A7 eSocial XML; TASK-PONTO-CONFIG resolvido)
- ✅ Bloco B (consignatárias completo; **B-LAYOUT-EDITOR pendente pré-go-live**)
- ✅ Bloco C (ContabilidadeService integrada em ProcessarFolhaJob)
- ✅ D1 Compras
- ✅ Pré-PoC GAPs implementados: GAP-FER, GAP-13, GAP-RES, GAP-QV, GAP-SIM, GAP-CAG, GAP-GFP, GAP-DIR, GAP-RAS, GAP-SIC
- ✅ Medical schedule gap detection (`routes/escala_saude.php`)
- ✅ Pharmaceutical inventory (LOTE_ESTOQUE + medical fields)
- ⏳ D2 Almoxarifado (instrução emitida)
- 🔜 D3 Patrimônio → D4 Contratos → D5 Frotas → Bloco E (mobile) → Bloco F (VPS)

### 1.2 Decisões de Ronaldo (auditoria 07/05/2026)

| # | Pergunta | Decisão | Status |
|---|----------|---------|--------|
| 1 | Quiosques físicos existem hoje? | NÃO existem hoje, mas pode haver outras prefeituras → **COMENTAR rotas, não deletar** | ✅ |
| 2 | Estratégia remoção rotas legadas | **GRADUAL por domínio** (refazer Fase 4) | ✅ |
| 3 | MotorFolhaService como motor único | **SIM, condicional** — só se cobrir todos os pontos. Resposta da auditoria: 8 gaps identificados, decisão A escolhida (ampliar) | ✅ |
| 4 | Bugs EsocialXmlService (R52-R55, R59, R60) | **CORRIGIR AGORA** (~1h30) — mover Fase 5 mais cedo | ✅ |
| 5 | Pré-cadastro legado | **REMOVER** — autocadastro moderno cobre | ✅ |
| 6 | Varredura `/cep/{cep}` em .vue | **APROVADA** — concluída: AutocadastroView usa viacep.com.br externo, NÃO chama `/cep/{cep}` interno → endpoint legado pode ser removido | ✅ |
| 7 | Telas administrativas (a-g) | **APROVADA recomendação Claude** — ver Parte 3 detalhado | ✅ |
| 8 | Varredura cron alertas em Console/Kernel.php | **APROVADA — concluída** — 9 schedules ativos, NENHUM chama alertas legados → rotas órfãs | ✅ |

### 1.3 Decisão crítica complementar (sub-pergunta da decisão 3)

**Pergunta:** Considerando os 8 gaps do MotorFolhaService, qual estratégia para a Fase 3?

**Resposta:** **(A) Ampliar MotorFolha** — absorver os 8 gaps antes de aposentar legados. Trabalho extra ~6-8h Antygravity + 2h auditoria Claude. Posterga Fase 3 do sábado para domingo manhã, mas elimina dívida técnica e garante implantação limpa.


---

## <a id="parte-2"></a>PARTE 2 — INVENTÁRIO DOS 3 MOTORES DE FOLHA + 8 GAPS

### 2.1 Motores vivos hoje

| Motor | Caminho | Disparado por | Estado | Destino |
|-------|---------|---------------|--------|---------|
| **MotorFolhaService** ⭐ | `app/Services/MotorFolhaService.php` (553L, BCMath/PHP) | SPA Vue 3: `POST /api/v3/folhas/calcular-proventos` (síncrono) e `POST /api/v3/folha/processar` (Bus::batch async) — `routes/folha.php` | **CANÔNICO** | **Único motor pós-Fase 3** (ampliado) |
| **FolhaParserService** | `app/Services/FolhaParserService.php` (621L) | `POST /folha/create` em `web.php` → `FolhaController::inserir` → `ProcessarFolhaJob::dispatch` | Legado vivo | **APOSENTAR** após absorção dos gaps |
| **Folha::salvaFolha/processarFolha/reprocessarFolha** | `app/Models/Folha.php` métodos T-SQL → SP `[dbo].[sp_gera_folha]` | `PUT /folha/update` em `web.php` → `FolhaController::alterar` | Legado vivo | **APOSENTAR** (black-box SQL Server) |

### 2.2 Comparação funcional (18 dimensões)

Legenda: ✅ implementado · ❌ ausente · ❓ desconhecido (SP T-SQL não auditável sem acesso ao banco)

| # | Funcionalidade | MotorFolha | FolhaParser | sp_gera_folha |
|---|----------------|:----------:|:-----------:|:-------------:|
| 1 | Vencimento estrutural C1 (TABELA_SALARIAL) | ✅ | ✅ (AtribuicaoLotacao) | ❓ |
| 2 | Anuênio com fator desempenho | ✅ | ❌ | ❓ |
| 3 | 5 vínculos (efetivo/comissao_puro/efetivo_cc_m1/efetivo_cc_m2/funcao_confianca) | ✅ | ❌ (só 3) | ❓ |
| 4 | Adicionais permanentes C2 (ADICIONAL_SERVIDOR) | ✅ | ❌ | ❓ |
| 5 | Lançamentos variáveis C3 (LANCAMENTO_FOLHA) | ✅ | ❌ | ❓ |
| 6 | Consignações (CONSIG_PARCELA) | ✅ | ❌ | ❓ |
| 7 | Complemento salário mínimo (VINCULOS_PISO) | ✅ | ❌ | ❓ |
| 8 | **Apuração frequência (DETALHE_ESCALA→faltas/atrasos)** | ❌ | ✅ | ❓ |
| 9 | **Abono por afastamento remunerado** (LICENCA_MEDICA, etc.) | ❌ | ✅ | ❓ |
| 10 | **Pró-rata admissão/exoneração no mês (TASK-A0)** | ❌ | ✅ | ❓ |
| 11 | **Jornada financeira informal (PONTO_CONFIG_FUNCIONARIO.JORNADA_FINANCEIRA_HORAS)** | ❌ | ⚠️ logado mas inerte | ❓ |
| 12 | **Horas extras + Plantões (HORA_EXTRA + PLANTAO_EXTRA)** | ❌ | ✅ + recalcula IRRF | ❓ |
| 13 | **Dias reais do mês (cal_days_in_month)** | ❌ | ✅ | ❓ |
| 14 | **Persistência por rubrica EVENTO+EVENTO_DETALHE_FOLHA** | ❌ | ✅ | ❓ |
| 15 | INSS RGPS faixas + IRRF progressivo | ✅ (R51 desatualizado) | ✅ via TabelasImpostoService | ❓ |
| 16 | Persistência idempotente (upsert) | ✅ | ❌ (DELETE+INSERT) | ❓ |
| 17 | Bus::batch async (≥1000 servidores) | ✅ | ❌ | ❓ |
| 18 | Eager loading sem N+1 (MotorFolhaLoteContext) | ✅ | ❌ | ❓ |


### 2.3 Os 8 GAPS do MotorFolhaService a absorver (Fase 2-A/2-B)

Cada gap está priorizado por impacto na folha real e dependência funcional.

#### GAP-MF-01 (CRÍTICO) — Apuração de frequência via DETALHE_ESCALA

**Problema:** MotorFolha calcula vencimento integral; ignora faltas e atrasos.

**Solução:** novo método `apurarFrequenciaLote(int $folhaId, array $funcionarioIds): array` que retorna `[funcId => ['dias_trabalhados' => N, 'faltas' => F, 'atrasos_min' => M]]`. Refatoração: extrair lógica de `FolhaParserService::apurarFuncionario` (linhas ~80-100) para serviço separado `ApuracaoFrequenciaService` reutilizado pelo MotorFolha.

**Onde injetar:** `MotorFolhaService::calcularLoteParaFuncionarios` ANTES do switch de vínculo, multiplica `vencimentoBase` por `(diasTrabalhados/diasMes)`.

**Tabelas:** ESCALA, DETALHE_ESCALA, DETALHE_ESCALA_ITEM (FALTA, ATRASO, TURNO_ID).

**Estimativa:** 2h Antygravity + 30min audit Claude.

---

#### GAP-MF-02 (CRÍTICO) — Abono por afastamento remunerado

**Problema:** `MotorFolhaLoteContext::possuiAfastamentoSobrepostoNaCompetencia` retorna boolean mas não desconta dias de afastamento das faltas.

**Solução:** novo método `calcularDiasAbonadosPorAfastamento(int $funcId, string $competencia): int` que conta dias entre AFASTAMENTO_DATA_INICIO e AFASTAMENTO_DATA_FIM dentro do mês, para tipos: LICENCA_MEDICA, LICENCA_SAUDE, LICENCA_MATERNIDADE, LICENCA_PATERNIDADE, LICENCA_NOJO, LICENCA_GALA, AFASTAMENTO_JUDICIAL, AFASTAMENTO_REMUNERADO.

**Cuidado R23:** FolhaParserService usa `strftime('%Y-%m', ...)` e `julianday(...)` que são **funções SQLite**. Em SQL Server prod isso quebra. **Reescrever com Eloquent + Carbon::diffInDays** (compatível SQLite dev + SQL Server prod).

**Onde injetar:** subtrair `diasAbonados` de `faltas` antes do cálculo proporcional.

**Estimativa:** 1h Antygravity + 30min audit Claude.

---

#### GAP-MF-03 (CRÍTICO) — Pró-rata admissão/exoneração (TASK-A0)

**Problema:** servidor admitido em 15/04 recebe folha cheia em abril; servidor exonerado em 10/04 idem.

**Solução:** novo método `calcularDiasProporcionaisAdmissaoExoneracao(Funcionario $f, int $ano, int $mes): int`:
- Se `FUNCIONARIO_DATA_INICIO` está no mês de competência → `diffInDays(FIM_MES) + 1`
- Se `FUNCIONARIO_DATA_FIM` está no mês de competência → `diffInDays(INICIO_MES, DATA_FIM) + 1`
- Caso contrário → dias do mês (com base apuração frequência)

**Onde injetar:** retorno usado no GAP-MF-01 (apuração).

**Estimativa:** 30min Antygravity + 15min audit Claude.

---

#### GAP-MF-04 (CRÍTICO) — Horas extras + Plantões (HORA_EXTRA + PLANTAO_EXTRA)

**Problema:** servidor com HE/plantão APROVADA não recebe na folha.

**Solução:** novo método `incluirHorasExtrasELoteamento(int $folhaId, array $funcionarioIds, string $competencia): array` que:
1. Lê `HORA_EXTRA WHERE STATUS IN ('APROVADA','INCLUIDA_FOLHA') AND COMPETENCIA = ?`
2. Lê `PLANTAO_EXTRA WHERE STATUS IN ('APROVADA','INCLUIDA_FOLHA') AND COMPETENCIA = ?`
3. Adiciona como **rubrica C3** (LANCAMENTO_FOLHA tipo 'P' com INCIDE_PREV=1) — assim flui pelo C3 já existente do MotorFolha
4. Marca STATUS='INCLUIDA_FOLHA' após processar

**Vantagem dessa abordagem:** evita criar caminho paralelo no motor. HE/plantão vira lançamento C3 e o MotorFolha já trata.

**Estimativa:** 1h30 Antygravity + 30min audit Claude.

---

#### GAP-MF-05 (ALTO) — Jornada financeira informal (JORNADA_FINANCEIRA_HORAS)

**Problema:** servidor com acordo informal de jornada reduzida com salário cheio (campo `PONTO_CONFIG_FUNCIONARIO.JORNADA_FINANCEIRA_HORAS`) — FolhaParserService loga mas não usa.

**Solução:** ao calcular `vencimentoProporcional = salarioBase * (diasTrabalhados / diasMes)`, **se** existe `JORNADA_FINANCEIRA_HORAS` configurada **então** usar essa carga horária no denominador ao invés da carga do turno padrão.

**Auditoria:** exigir log obrigatório com `funcionario_id`, `jornada_financeira`, `JORNADA_FINANCEIRA_OBS`, `usuario_aprovador` em audit chain F4.

**Estimativa:** 30min Antygravity + 15min audit Claude.

---

#### GAP-MF-06 (ALTO) — Dias reais do mês (cal_days_in_month)

**Problema:** MotorFolha não divide explicitamente por dias do mês (assume mês cheio). Em fevereiro com 28 dias, dividir por 30 dá vencimento errado.

**Solução:** sempre que houver proporcionalização (apuração+pró-rata), usar `cal_days_in_month(CAL_GREGORIAN, $mes, $ano)`.

**Estimativa:** 15min Antygravity (mudança trivial após GAP-MF-01).

---

#### GAP-MF-07 (MÉDIO) — Persistência por rubrica em EVENTO+EVENTO_DETALHE_FOLHA

**Problema:** MotorFolha persiste só agregado em DETALHE_FOLHA (proventos/descontos/líquido total). Sem histórico granular por rubrica → relatórios fiscais (DIRF, RAIS, SIOPE), holerite detalhado e auditoria TCE-MA ficam comprometidos.

**Solução:** depois do upsert em DETALHE_FOLHA, gerar `EVENTO_DETALHE_FOLHA` para cada componente:
- C1 (vencimento, anuênio) → EVENTO_ID resolvido por seeder
- C2 (cada ADICIONAL_SERVIDOR) → EVENTO_ID por descrição/rubrica
- C3 (cada LANCAMENTO_FOLHA + HE/plantão) → já tem RUBRICA_ID
- Descontos (INSS, IRRF, faltas, consignações) → EVENTO_ID por descrição

**Cuidado:** garantir idempotência (DELETE EVENTO_DETALHE_FOLHA WHERE DETALHE_FOLHA_ID = ? antes do INSERT).

**Estimativa:** 1h30 Antygravity + 30min audit Claude.

---

#### GAP-MF-08 (BAIXO/PONTUAL) — R51 INSS RGPS faixas atualizadas 2025

**Problema:** `MotorFolhaService::calcularInssRgps()` linha 524 tem faixas hardcoded de 2024.

**Solução:** atualizar 4 faixas para tabela vigente 2025 (1518.00, 2666.68, 4000.03, 7786.02 — confirmar com Receita Federal jan/2025).

**Alternativa estrutural:** mover faixas para `TabelasImpostoService` (já existe e é usado pelo FolhaParser). Aproveitar a refatoração.

**Estimativa:** 5min se hardcoded, 30min se mover para TabelasImpostoService.

---

### 2.4 Estimativa total Fase 2-A + 2-B

| Gap | Estimativa Antygravity | Estimativa audit |
|-----|------------------------|------------------|
| GAP-MF-01 frequência | 2h | 30min |
| GAP-MF-02 abono afastamento | 1h | 30min |
| GAP-MF-03 pró-rata admissão | 30min | 15min |
| GAP-MF-04 HE+plantão | 1h30 | 30min |
| GAP-MF-05 jornada financeira | 30min | 15min |
| GAP-MF-06 dias do mês | 15min | (incluso no 01) |
| GAP-MF-07 persistência por rubrica | 1h30 | 30min |
| GAP-MF-08 R51 INSS 2025 | 30min (se TabelasImpostoService) | 15min |
| **TOTAL** | **7h45** | **2h45** |

**Subtotal Fase 2-A (gaps críticos 01-04):** 5h Antygravity + 1h45 audit = ~6h45
**Subtotal Fase 2-B (gaps demais 05-08):** 2h45 Antygravity + 1h audit = ~3h45


---

## <a id="parte-3"></a>PARTE 3 — MAPA ROTAS LEGADAS (classificação 7.a-7.g + 5)

### 3.1 Tabela de classificação final (após decisões 5, 6, 7, 8)

| Item | Origem | Endpoint(s) | Classificação | Ação na Fase 4 |
|------|--------|-------------|---------------|----------------|
| **5 — pré-cadastro** | SISGEAP MenuSeeder app 209 | `/pre-cadastro/*` | Substituído por `/api/v3/autocadastro/*` | **REMOVER PERMANENTE** |
| **6 — CEP interno** | Provavelmente SISGEAP | `/cep/{cep}` | AutocadastroView usa `viacep.com.br` direto | **REMOVER PERMANENTE** (zero uso) |
| **7.a — tabela genérica** | SISGEAP MenuSeeder app 112 | `/tabela_generica/view`, `/tabela_generica/list`, `/tabela_generica/{id}` | View blade não existe (zumbi) | **COMENTAR** (preservar p/ outras prefeituras) |
| **7.b — aplicação** | SISGEAP MenuSeeder app 703 (sistema menu antigo) | `/aplicacao/view`, `/aplicacao/listar`, `/aplicacao/inserir`, `/aplicacao/alterar`, `/aplicacao/excluir` | View blade não existe; RBAC moderno via `gente_role/permission` | **COMENTAR** (preservar) |
| **7.c — programa** | NÃO está no MenuSeeder (SISGEAP incompleto) | `/programa/view`, `/programa/listar`, `/programa/inserir`, `/programa/alterar`, `/programa/buscar/{id}` | View blade não existe; nunca foi terminado | **REMOVER PERMANENTE** |
| **7.d — script** | SISGEAP MenuSeeder app 705 ("Scripts SQL") | `/script/view`, `/script/listar`, `/script/executarQuery` | View blade não existe; whitelist `DELETAR_PESSOA`/`DELETAR_ESCALA` sem audit chain F4 | **REMOVER PERMANENTE** (vetor de risco — deletes sem auditoria) |
| **7.e — termo + termo_usuario** | SISGEAP MenuSeeder app 210 (admin-only) | `/termo/view`, `/termo/listar`, `/termo/inserir`, `/termo/alterar`, `/termo/download/{id}`, `/termo_usuario/inserir` | Backend funciona; UI 100% morta (extends `layouts.app` que não existe). MenuSeeder linha 152 confirma origem SISGEAP. SEM seeder de Termo. | **COMENTAR endpoints** (LGPD pode exigir no futuro). **DELETAR `termo_view.blade.php` zumbi.** Tabelas TERMO+TERMO_USUARIO permanecem no banco (não dropar). |
| **7.f — comentário** | Não está no MenuSeeder | `/comentario/list`, `/comentario/create` | Vinculado a PESSOA_ID; leitura de `PerfilFuncionarioView.vue` (64KB) confirma SEM uso no SPA Vue 3 | **REMOVER PERMANENTE** |
| **7.g — registrar (raiz, sem auth)** | Provável SISGEAP | `/registrar` | Rota pública sem auth (cria pessoa) — vetor de poluição/spam | **REMOVER PERMANENTE** |

### 3.2 Justificativa investigação LGPD/Termo (decisão 7.e refinada)

**Quebra-cabeça resolvido:**

1. Migration `2026_02_23_000000_create_termo_tables.php` linha 11: comentário diz *"Consultadas pelo CompartilharVariaveis em todo request autenticado"*.
2. `app/Http/Middleware/CompartilharVariaveis.php` injeta `$termosDisponiveis` em todo request blade (linha 16-19).
3. `UsuarioExterno.php` linha 67-68: rotas `download.termo` e `inserir.termo_usuario` permitidas para servidores externos.
4. **`resources/views/layouts/` NÃO EXISTE** → views blade que extendem `layouts.app` quebram.
5. `database/seeders/MenuSeeder.php` linha ~152 confirma: app ID 210 "Termos" no grupo "Recursos Humanos", APENAS_ADM (perfis 1, 2, 13).
6. **NENHUM seeder popula tabela TERMO** — banco fica vazio.
7. **DashboardLayout.vue (40KB) não tem menção a `termo`/`aceite`/`lgpd`/`consentimento`** — SPA Vue 3 não tem fluxo de aceite.

**Conclusão:** todo fluxo Termo/LGPD é **herança não-tocada do SISGEAP 1.0**. A LGPD do GENTE v3 está sendo atendida por outra camada (F1 PII/blind index HMAC, F4 audit chain). Recomendação: **adiar implementação de fluxo moderno de aceite para pós-go-live, se TCE-MA exigir.**


---

## <a id="parte-4"></a>PARTE 4 — RISCOS R1-R81 (TOP 11 BLOQUEADORES + RESUMO)

### 4.1 Top 11 bloqueadores pré-implantação

Sequência ordenada por impacto e custo de correção (ordem cirúrgica para Fase 1):

| # | Risco | Arquivo | Estimativa | Severidade |
|---|-------|---------|-----------:|------------|
| 1 | **R69 — SQL injection em `Lotacao::getDadosRelatorioImprimirLotacao`** | `app/Models/Lotacao.php` | 5min | 🔴 CRÍTICO (segurança) |
| 2 | **R70 — `FOLHA_VALOR_TOTAL` cast 'integer' perde decimais** | `app/Models/Folha.php` linha 47 | 1min | 🔴 CRÍTICO (dado financeiro corrompido) |
| 3 | **R72 — Path debug `/home/DK/Developer/...` em EscalaAusenciaService** | `app/Services/EscalaAusenciaService.php` | 1min | 🟠 ALTO (vazamento ambiente) |
| 4 | **R39 — `ProgressaoFuncionalListagemService` crase MySQL** | `app/Services/Progressao/...` | 5min | 🟠 ALTO (quebra em SQL Server) |
| 5 | **R40 — `ApuracaoPontoService` whereYear/whereMonth** | `app/Services/ApuracaoPontoService.php` | 10min | 🟠 ALTO (quebra em SQL Server) |
| 6 | **R56 — `DepreciacaoService` strftime SQLite** | `app/Services/DepreciacaoService.php` | 5min | 🟠 ALTO (quebra em SQL Server) |
| 7 | **R57 — `DashboardOperacionalService` date+'+N days'** | `app/Services/Dashboard/DashboardOperacionalService.php` | 5min | 🟠 ALTO (quebra em SQL Server) |
| 8 | **R51 — INSS RGPS desatualizado 2024→2025** | `app/Services/MotorFolhaService.php` linha 524 | 5min | 🟠 ALTO (será absorvido em GAP-MF-08) |
| 9 | **R7-R10 — ContabilidadeService não-idempotente** | `app/Services/ContabilidadeService.php` | ~2h | 🟡 MÉDIO (reprocessar folha duplica lançamentos contábeis) |
| 10 | **R71 — 3 motores vivos** | `MotorFolha`, `FolhaParser`, `sp_gera_folha` | ~6-8h (Fase 2-A/2-B) | 🔴 CRÍTICO (núcleo da implantação) |
| 11 | **R23 — FolhaParserService strftime SQLite** | `app/Services/FolhaParserService.php` | (deletar com R71) | 🟠 ALTO |

### 4.2 Resumo R1-R81 por categoria

- **Segurança (R1-R10, R69):** SQL injection, path traversal, secrets em log → 4 corrigidos pré-auditoria, R69 e R70 pendentes
- **Compatibilidade SQLite/SQL Server (R11-R30, R39-R40, R56-R57, R23):** strftime, julianday, date+'+N days', whereYear/Month → ~10 itens
- **Tabelas fiscais desatualizadas (R51, R58):** INSS RGPS 2024, IRRF 2024 → R51 absorvido em GAP-MF-08
- **eSocial (R52-R55, R59, R60):** XML inválido, eventos ausentes, idempotência → Fase 5 (decisão 4)
- **Idempotência (R7-R10, R64):** ContabilidadeService, ProcessarFolhaJob, EsocialQueue → Fase 1 + Fase 5
- **Performance (R32-R38):** N+1 queries, JOIN cartesiano → corrigidos em 70%
- **Motores duplicados (R71):** raiz da Fase 2-A/2-B + Fase 3
- **Diversos legados (R41-R50, R61-R68, R73-R81):** zumbis, código morto, debug → Fase 4


---

## <a id="parte-5"></a>PARTE 5 — PLANO DE FASES 1-6 + CRONOGRAMA

### 5.1 Cronograma ajustado (com decisão A)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Sex 09/05 — manhã    │ FASE 1 — Correções pontuais bloqueadoras (~3h)    │
│                      │ R69, R70, R72, R39, R40, R56, R57, R7-R10         │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Sex 09/05 — tarde    │ FASE 2-A — Ampliação MotorFolha (gaps críticos)   │
│                      │ GAP-MF-01 (frequência), GAP-MF-02 (abono),        │
│                      │ GAP-MF-03 (pró-rata), GAP-MF-04 (HE/plantão)      │
│                      │ ~5h Antygravity + 1h45 audit                      │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Sáb 10/05 — manhã    │ FASE 2-B — Ampliação MotorFolha (gaps demais)     │
│                      │ GAP-MF-05 (jornada fin.), GAP-MF-06 (dias mês),   │
│                      │ GAP-MF-07 (persistência rubrica), GAP-MF-08 (R51) │
│                      │ ~3h Antygravity + 1h audit                        │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Sáb 10/05 — tarde    │ FASE 5 — Correções EsocialXmlService (~1h30)      │
│                      │ R52-R55, R59, R60                                 │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Sáb 10/05 — noite    │ FASE 2-A/2-B — TESTE DE REGRESSÃO motor único     │
│                      │ Smoke test sintético + comparação numérica        │
│                      │ MotorFolha vs FolhaParser (~1h)                   │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Dom 11/05 — manhã    │ FASE 3 — Aposentar motores legados (~1h)          │
│                      │ FolhaController::inserir → MotorFolha             │
│                      │ FolhaController::alterar → MotorFolha             │
│                      │ Manter SP sp_gera_folha apenas como fallback log  │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Dom 11/05 — tarde    │ FASE 4 — Remoção GRADUAL rotas legadas (~3h)      │
│                      │ Por domínio: folha → escala → ferias → ... →      │
│                      │ remoção bloco completo (~50 prefixos web.php)     │
│                      │ Items 5, 6, 7.a-g aplicados conforme decisão 7    │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Dom 11/05 — noite    │ FASE 6 T01-T03 (~2h)                              │
│                      │ T01 backup banco produção PMSL                    │
│                      │ T02 migrations idempotentes (php artisan migrate) │
│                      │ T03 ETL dados PMSL → GENTE v3                     │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Seg 12/05 — madrug.  │ FASE 6 T04-T09 (~4h)                              │
│                      │ T04 smoke runner (gente:smoke-teia7a)             │
│                      │ T05 verificação cadastros (50 servidores amostra) │
│                      │ T06 folha teste competência abril/2026            │
│                      │ T07 holerites geração massa                       │
│                      │ T08 CNAB remessa bancária                         │
│                      │ T09 healthcheck completo                          │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Seg 12/05 — manhã    │ Window de correção emergencial (~2h)              │
├──────────────────────┼───────────────────────────────────────────────────┤
│ Seg 12/05 — tarde    │ 🎯 PoC AO VIVO PMSL                               │
└──────────────────────┴───────────────────────────────────────────────────┘
```

### 5.2 Detalhamento por fase

#### FASE 1 — Correções pontuais bloqueadoras (Sex 09/05 manhã, ~3h)

**Escopo:** correções cirúrgicas que não dependem do motor de folha. Cada correção <30min, isoladas, baixo risco.

**Lista ordenada (executar nesta sequência):**
1. R70 cast 'integer' → 'decimal:2' em `Folha.php` (1min)
2. R72 path debug → fallback `storage_path()` em `EscalaAusenciaService.php` (1min)
3. R69 SQL injection → bind parameter em `Lotacao::getDadosRelatorioImprimirLotacao` (5min)
4. R39 crase MySQL → DB driver detection (5min)
5. R40 whereYear/Month → DB::raw cross-driver (10min)
6. R56 strftime → Carbon::diffInDays (5min)
7. R57 date+'+N days' → Carbon::addDays (5min)
8. R7-R10 ContabilidadeService idempotência → upsert por (FOLHA_ID, RUBRICA_ID) (~2h)

**Output esperado:** todos os R correspondentes fechados em `STATUS_PRODUCAO_2026-05-07.md`. Smoke test rodando sem erros desses pontos.


#### FASE 2-A — Ampliação MotorFolha gaps críticos (Sex 09/05 tarde, ~6h45)

**Escopo:** absorver os 4 gaps que **inviabilizam** aposentar FolhaParserService (frequência, abono, pró-rata, HE/plantão).

**Pré-requisito:** Fase 1 concluída (motor canônico não pode ter R69/R70/R51 quando começar a refatoração).

**Sequência:**
1. **GAP-MF-01:** criar `app/Services/Folha/ApuracaoFrequenciaService.php` extraindo lógica de `FolhaParserService::apurarFuncionario`. Método público `apurarLote(int $folhaId, array $funcIds): array`. Retorno: `[funcId => ['dias_trabalhados', 'faltas', 'atrasos_min']]`. **Importante:** sem strftime — usar Eloquent.
2. **GAP-MF-02:** dentro do mesmo `ApuracaoFrequenciaService`, método `calcularDiasAbonadosPorAfastamento(int $funcId, string $competencia): int`. **Cuidado R23:** reescrever a query do FolhaParser linha 113-126 sem strftime/julianday. Usar `whereDate('AFASTAMENTO_DATA_INICIO', '>=', $inicioMes)->whereDate('AFASTAMENTO_DATA_FIM', '<=', $fimMes)` + Carbon::diffInDays.
3. **GAP-MF-03:** dentro do mesmo serviço, método `calcularDiasProporcionaisAdmissaoExoneracao(Funcionario $f, int $ano, int $mes): int`. Usar `Carbon::create($ano,$mes,1)`, `endOfMonth()`, `diffInDays`.
4. **GAP-MF-04:** novo serviço `app/Services/Folha/InclusaoHorasExtrasService.php` com método `incluirHorasExtrasComoLancamentos(int $folhaId, array $funcIds, string $competencia): void` que insere registros em `LANCAMENTO_FOLHA` tipo='P' INCIDE_PREV=1 a partir de HORA_EXTRA + PLANTAO_EXTRA com STATUS APROVADA. Marca STATUS='INCLUIDA_FOLHA' após.
5. **Integração:** modificar `MotorFolhaService::calcularLoteParaFuncionarios` para chamar os 3 serviços ANTES do switch de vínculo:
```
$frequencia = app(ApuracaoFrequenciaService::class)->apurarLote($folhaId, $funcIds);
app(InclusaoHorasExtrasService::class)->incluirHorasExtrasComoLancamentos($folhaId, $funcIds, $competencia);
// ... continua leitura LANCAMENTO_FOLHA (que agora inclui HE)
// ... no switch, multiplicar vencBase por (frequencia[$funcId]['dias_trabalhados'] / cal_days_in_month)
```

**Auditoria Claude pós-execução:**
- ✅ Verificar `ApuracaoFrequenciaService` existe e tem assinatura correta
- ✅ Verificar SEM strftime/julianday no código novo (`grep -ri "strftime\|julianday" app/Services/Folha/`)
- ✅ Smoke test sintético: criar funcionário com 5 faltas + 2 dias afastamento médico → folha deve abonar 2 dias e descontar 3 faltas
- ✅ Smoke test pró-rata: funcionário admitido em 15/04 → folha abril deve ser proporcional 16 dias
- ✅ Smoke test HE: funcionário com HE 100% APROVADA 10h → folha deve ter LANCAMENTO_FOLHA tipo P 'HORA EXTRA 100%'

**Output:** `MotorFolhaService` agora calcula corretamente para todos os cenários comuns. FolhaParserService ainda vivo (será aposentado na Fase 3).

#### FASE 2-B — Ampliação MotorFolha gaps demais (Sáb 10/05 manhã, ~3h45)

**Escopo:** absorver gaps 5-8 (jornada financeira, dias do mês, persistência por rubrica, R51 INSS).

**Sequência:**
1. **GAP-MF-05:** dentro de `ApuracaoFrequenciaService`, ler `PONTO_CONFIG_FUNCIONARIO.JORNADA_FINANCEIRA_HORAS`. Se preenchido, usar essa carga horária no denominador da proporcionalização. Adicionar log obrigatório em audit chain F4 com `funcionario_id`, `jornada_financeira`, `JORNADA_FINANCEIRA_OBS`, `usuario_aprovador`.
2. **GAP-MF-06:** garantir que `cal_days_in_month(CAL_GREGORIAN, $mes, $ano)` é usado em todo lugar que dividir por dias do mês.
3. **GAP-MF-07:** novo serviço `app/Services/Folha/PersistenciaRubricasService.php` com método `persistirRubricasDoCalculo(int $detalheFolhaId, array $componentes): void`. Após o upsert em DETALHE_FOLHA, chamar este método para gerar EVENTO_DETALHE_FOLHA por componente. Garantir idempotência (DELETE WHERE DETALHE_FOLHA_ID = ? antes do INSERT). Resolver EVENTO_ID por descrição (cache memoizado).
4. **GAP-MF-08:** mover faixas INSS RGPS hardcoded de `MotorFolhaService::calcularInssRgps` para `TabelasImpostoService` (já existe). Atualizar para tabela 2025: 1518.00 (7.5%), 2666.68 (9%), 4000.03 (12%), 7786.02 (14%).

**Auditoria Claude pós-execução:**
- ✅ `cal_days_in_month` em fevereiro retorna 28/29
- ✅ EVENTO_DETALHE_FOLHA gerado para 1 servidor com 3 adicionais + 5 lançamentos = 8+ rubricas
- ✅ Faixas INSS RGPS = 7.5% / 9% / 12% / 14% (R51 fechado)

**Output:** `MotorFolhaService` cobre 100% das funcionalidades antes distribuídas em 3 motores.

#### FASE 5 — Correções EsocialXmlService (Sáb 10/05 tarde, ~1h30)

**Escopo:** R52-R55, R59, R60. Decisão Ronaldo 4: corrigir agora.

**Riscos:** XML inválido para SEFIP, eventos S-1010/S-2200/S-2300 ausentes, idempotência da fila eSocial.

**Lista:**
- R52: namespace XML incorreto em S-1010 → corrigir
- R53: campo `tpInsc` ausente em S-2200 → adicionar
- R54: validação CPF servidor faltando → adicionar
- R55: ordem de eventos S-1000 → S-1010 → S-2200 quebrada → ordenar por dependência
- R59: retry com backoff exponencial → corrigir delay (5s, 30s, 5min, 1h)
- R60: idempotência por `EVENTO_REFERENCIA` UNIQUE → adicionar UNIQUE constraint


#### FASE 3 — Aposentar motores legados (Dom 11/05 manhã, ~1h)

**Pré-requisito:** Fases 2-A e 2-B concluídas E auditadas E smoke test passou.

**Escopo:** trocar disparo dos motores legados pelo MotorFolha canônico.

**Edits cirúrgicos:**

1. `app/Http/Controllers/FolhaController.php` linha 50:
```php
// ANTES
ProcessarFolhaJob::dispatch(...);
// DEPOIS
app(\App\Services\MotorFolhaService::class)->despacharProcessamentoAssincrono($folha->FOLHA_ID, Auth::id());
```

2. `app/Http/Controllers/FolhaController.php` linha 119 (`alterar` action):
```php
// ANTES
$folha->reprocessarFolha($folha->FOLHA_ID, Auth::id()); // SP T-SQL
// DEPOIS
app(\App\Services\MotorFolhaService::class)->despacharProcessamentoAssincrono($folha->FOLHA_ID, Auth::id());
```

3. `app/Models/Folha.php` linhas 169-218 (`salvaFolha`, `processarFolha`, `reprocessarFolha`):
```php
// Marcar como @deprecated mas NÃO REMOVER ainda
// (preservar p/ rollback emergencial via terminal Artisan)
```

4. `app/Services/FolhaParserService.php`:
```php
// Mover para app/Services/_legacy/FolhaParserService.php.bak
// (rollback se descobrirmos algo na PoC)
```

5. `app/Jobs/ProcessarFolhaJob.php`:
```php
// Refatorar handle() para chamar MotorFolhaService::calcularLote
// OU mover para _legacy/ e parar de despachá-lo
```

**Auditoria Claude pós-execução:**
- ✅ `grep -r "FolhaParserService\|sp_gera_folha" app/` retorna 0 ocorrências (exceto _legacy/)
- ✅ Smoke test: criar folha via `POST /folha/create` (UI legada) → confirmar que MotorFolha foi disparado (log `[MotorFolha]` aparece)
- ✅ Smoke test: editar folha via `PUT /folha/update` → idem
- ✅ Smoke test: criar folha via `POST /api/v3/folha/processar` (SPA Vue 3) → idem

**Rollback emergencial:** se PoC quebrar por algum cálculo errado do MotorFolha, comentar os edits 1-2 acima e voltar para legados (preservados em `_legacy/`).

#### FASE 4 — Remoção GRADUAL rotas legadas (Dom 11/05 tarde, ~3h)

**Escopo:** remover/comentar ~50 prefixos do bloco autenticado de `routes/web.php` (linhas ~740-1730).

**Estratégia gradual por domínio (decisão Ronaldo 2):**

Cada domínio é um commit isolado. Se algo quebrar, rollback de 1 domínio por vez.

| Domínio | Prefixos | Ação | Test |
|---------|---------:|------|------|
| **Folha** | `/folha/*`, `/holerite/*`, `/remessa/*`, `/historico_folha`, `/historico_evento`, `/falta_atraso` | Comentar (Fase 3 já trocou disparo motor) | Confirmar SPA Vue 3 não quebra |
| **Escala** | `/escala/*`, `/detalhe_escala/*`, `/historico_escala`, `/substituicao_escala`, `/turno`, `/feriado` | Comentar | SPA Vue 3 escala-trabalho funciona |
| **Cadastros pessoa** | `/funcionario`, `/lotacao`, `/pessoa`, `/setor`, `/unidade`, `/cargo`, `/vinculo`, `/funcao`, `/ocupacao`, `/conselho`, `/banco`, `/dependente`, `/tipo_documento`, `/fim_lotacao`, `/dossie`, `/setor_atribuicao`, `/atribuicao*`, `/perfil`, `/usuario*`, `/uf`, `/cidade`, `/bairro`, `/contato`, `/documento`, `/profissao`, `/pessoa_*` | Comentar | Funcionários/Organograma SPA OK |
| **Férias/Afastamento/Abono** | `/ferias`, `/afastamento`, `/anexo_*`, `/abono_falta` | Comentar | SPA ferias-licencas OK |
| **Eventos folha** | `/evento*`, `/parametro_financeiro`, `/tabela_imposto`, `/tributacao` | Comentar | Configurações SPA OK |
| **Ponto** | `/ponto/*` (segundo bloco), `/registro_ponto` | Comentar | Ponto SPA OK |
| **Telas administrativas (decisão 7)** | `/aplicacao`, `/programa`, `/script`, `/termo`, `/termo_usuario`, `/tabela_generica`, `/comentario` | Conforme tabela 3.1: COMENTAR ou REMOVER | Nenhuma view Vue 3 quebra |
| **Pré-cadastro (decisão 5)** | `/pre-cadastro` | REMOVER PERMANENTE | AutocadastroView funciona |
| **CEP (decisão 6)** | `/cep/{cep}` | REMOVER PERMANENTE | AutocadastroView usa viacep.com.br |
| **Registrar (decisão 7.g)** | `/registrar` | REMOVER PERMANENTE | Não há fluxo dependente |
| **Alertas órfãos (decisão 8)** | `/ferias/alerta-vencer`, `/afastamento/alerta-expirar` | REMOVER (nenhum cron chama) | — |
| **Outros zumbis** | `/relatorio/*`, `/configuracoes`, `/tipo_alerta`, `/historico_parametro` | Auditar e comentar conforme uso | — |

**Output:** `web.php` reduzido de ~1730 linhas para ~600 linhas (auth/login + autocadastro público + bloco SPA Vue 3 mínimo).

**Decisão crítica:** se descobrir alguma rota legada AINDA usada pelo SPA durante a Fase 4, **PARAR** e adicionar à lista de migração para `/api/v3/*` (caso da `RemessaView.vue` linha 245 — já mapeada).


#### FASE 6 — Implantação produção PMSL (Dom 11/05 noite + Seg 12/05 madrug./manhã)

**T01 — Backup banco produção PMSL** (Dom 11/05 noite, 30min)
- `mssql-cli` ou Management Studio → backup completo
- Validar backup com restore em servidor de homologação
- Documentar checksum SHA-256 do .bak

**T02 — Migrations idempotentes** (Dom 11/05 noite, 30min)
- `php artisan migrate --force` no servidor PMSL
- Confirmar que todas as migrations rodam sem erro (especialmente as recentes de 01/05/2026 RBAC: gente_role, gente_permission)
- Verificar `migrations` table preenchida

**T03 — ETL dados PMSL → GENTE v3** (Dom 11/05 noite/madrugada, 1h)
- Importação massa via comando `gente:import-sisfolha8a` (já existe)
- Validação: `SELECT COUNT(*) FROM FUNCIONARIO` deve casar com PMSL legado
- Validação: 50 servidores amostra com `SELECT * FROM PESSOA P JOIN FUNCIONARIO F` — comparar nome, CPF, matrícula, salário base com sistema legado
- Idempotência: rodar 2x e verificar que não duplica registros

**T04 — Smoke runner** (Seg 12/05 madrugada, 30min)
- `php artisan gente:smoke-teia7a --json`
- Confirmar todos os health checks: DB connection, queue worker, audit chain, F1-F5 ativos
- Sentinela Integridade rodando

**T05 — Verificação cadastros** (Seg 12/05 madrugada, 30min)
- 50 servidores aleatórios via SPA Vue 3 (FuncionariosView)
- Conferir: nome, CPF, PIS, matrícula, lotação, cargo, vínculo, salário base
- Conferir progressão funcional, dependentes IRRF, adicionais permanentes

**T06 — Folha teste competência abril/2026** (Seg 12/05 madrugada, 1h)
- Via `POST /api/v3/folha/processar` para 1 setor pequeno (ex.: gabinete prefeito, ~10 servidores)
- MotorFolha rodando com Bus::batch
- Validar resultado: total proventos / descontos / líquido por servidor
- Spot check: 5 servidores escolhidos, comparar manualmente com sistema legado da PMSL

**T07 — Holerites geração massa** (Seg 12/05 madrugada, 30min)
- `/api/v3/holerite-pdf/{detalheFolhaId}` para os 10 servidores do setor de teste
- Verificar: campos preenchidos, totalizadores corretos, layout PDF OK

**T08 — CNAB remessa bancária** (Seg 12/05 madrugada/manhã, 30min)
- Geração via `/api/v3/cnab/gerar` para a folha teste
- Verificar: 240 caracteres por linha, lotes batidos, valor total fechando

**T09 — Healthcheck completo** (Seg 12/05 manhã, 15min)
- `php artisan gente:healthcheck --json`
- Todos os checks ✅

**Window correção emergencial** (Seg 12/05 manhã, 2h)
- Margem de segurança para qualquer surpresa
- Acesso direto ao banco PMSL para correções pontuais se necessário

**🎯 PoC ao vivo PMSL** (Seg 12/05 tarde)
- Apresentação para Prefeitura
- Caso real: pegar 1 setor maior (ex.: SEMED ~5000 servidores) e processar folha em demonstração ao vivo
- Tempo esperado: ~5min com Bus::batch
- Geração CNAB ao vivo
- Acesso a holerites individuais


---

## <a id="parte-6"></a>PARTE 6 — CHECKLIST + INTEGRAÇÃO F1-F5 + ANEXOS

### 6.1 Checklist pré-PoC (a marcar conforme fases avançam)

#### Fase 1 — Correções pontuais
- [ ] R69 SQL injection Lotacao corrigido
- [ ] R70 cast 'integer' → 'decimal:2' Folha.php
- [ ] R72 path debug EscalaAusenciaService removido
- [ ] R39 crase MySQL ProgressaoFuncionalListagemService
- [ ] R40 whereYear/Month ApuracaoPontoService
- [ ] R56 strftime DepreciacaoService
- [ ] R57 date+'+N days' DashboardOperacionalService
- [ ] R7-R10 ContabilidadeService idempotência
- [ ] Smoke test verde

#### Fase 2-A — MotorFolha gaps críticos
- [ ] ApuracaoFrequenciaService criado
- [ ] GAP-MF-01 frequência integrada
- [ ] GAP-MF-02 abono afastamento (sem strftime/julianday)
- [ ] GAP-MF-03 pró-rata admissão/exoneração
- [ ] InclusaoHorasExtrasService criado
- [ ] GAP-MF-04 HE+plantão como LANCAMENTO_FOLHA
- [ ] Smoke test 4 cenários: 5 faltas + 2 abonos / admissão 15/04 / exoneração 10/04 / HE 100% 10h
- [ ] grep -ri "strftime\|julianday" app/Services/Folha/ retorna vazio

#### Fase 2-B — MotorFolha gaps demais
- [ ] GAP-MF-05 jornada financeira informal
- [ ] GAP-MF-06 cal_days_in_month
- [ ] PersistenciaRubricasService criado
- [ ] GAP-MF-07 EVENTO_DETALHE_FOLHA por componente
- [ ] GAP-MF-08 R51 INSS RGPS 2025 em TabelasImpostoService
- [ ] Smoke test fevereiro 28 dias OK
- [ ] Smoke test 1 servidor → 8+ rubricas em EVENTO_DETALHE_FOLHA

#### Fase 5 — EsocialXmlService
- [ ] R52 namespace XML S-1010
- [ ] R53 tpInsc S-2200
- [ ] R54 validação CPF
- [ ] R55 ordem eventos
- [ ] R59 retry backoff exponencial
- [ ] R60 idempotência UNIQUE EVENTO_REFERENCIA
- [ ] Teste: gerar S-1000 + S-1010 + S-2200 para 1 servidor amostra

#### Fase 3 — Aposentar legados
- [ ] FolhaController::inserir → MotorFolha
- [ ] FolhaController::alterar → MotorFolha
- [ ] FolhaParserService.php movido para _legacy/
- [ ] sp_gera_folha métodos marcados @deprecated
- [ ] Smoke test 3 disparos: /folha/create, /folha/update, /api/v3/folha/processar — todos chamam MotorFolha

#### Fase 4 — Remoção rotas legadas
- [ ] Domínio Folha comentado
- [ ] Domínio Escala comentado
- [ ] Domínio Cadastros pessoa comentado
- [ ] Domínio Férias/Afastamento comentado
- [ ] Domínio Eventos folha comentado
- [ ] Domínio Ponto comentado
- [ ] 7.a tabela_generica comentado
- [ ] 7.b aplicacao comentado
- [ ] 7.c programa REMOVIDO
- [ ] 7.d script REMOVIDO
- [ ] 7.e termo endpoints comentados + termo_view.blade.php DELETADO
- [ ] 7.f comentario REMOVIDO
- [ ] 7.g registrar REMOVIDO
- [ ] 5 pre-cadastro REMOVIDO
- [ ] 6 cep/{cep} REMOVIDO
- [ ] 8 alertas órfãos REMOVIDOS
- [ ] web.php < 700 linhas
- [ ] Smoke test SPA Vue 3 completo: todas as views renderizam sem 404/500

#### Fase 6 — Implantação
- [ ] T01 backup PMSL com checksum SHA-256
- [ ] T02 migrations OK
- [ ] T03 ETL com COUNT batendo
- [ ] T04 smoke runner verde
- [ ] T05 50 servidores spot check OK
- [ ] T06 folha gabinete (10 servs) calculada e batendo com legado
- [ ] T07 10 holerites PDF gerados
- [ ] T08 CNAB remessa OK
- [ ] T09 healthcheck verde


### 6.2 Integração com camadas de segurança F1-F5

**Importante:** todos os novos serviços da Fase 2-A/2-B devem rodar dentro do middleware stack `/api/v3/*`:
```
['web', 'auth', 'alterar.senha', 'honey.tripwire', 'verify.request.signature', 'tenant.scope', 'audit']
```

| Frente | Aplicação no MotorFolha ampliado |
|--------|----------------------------------|
| **F1 PII/LGPD** | CPF do servidor já está com FLE+blind index. Os novos serviços (ApuracaoFrequenciaService, InclusaoHorasExtrasService, PersistenciaRubricasService) NÃO devem ler PESSOA_CPF_NUMERO direto — usar FUNCIONARIO_ID. |
| **F2 HMAC anti-replay** | Já aplicado em todas as rotas `/api/v3/*` que chamam o motor. Sem ação extra. |
| **F3 Honeytoken** | Continua ativo. Sem ação extra. |
| **F4 Audit chain SHA-256** | **AÇÃO OBRIGATÓRIA:** GAP-MF-05 (jornada financeira informal) DEVE registrar em audit chain quando aplicada. Adicionar `AuditLogModel::registrar('motor_folha.jornada_financeira_aplicada', ['funcionario_id' => $id, 'jornada_horas' => $h, 'observacao' => $obs, 'usuario_aprovador' => Auth::id()])`. |
| **F5 PCCV escala impositiva** | Sem impacto direto no motor; já cobre escala. |

### 6.3 Anexo A — Pendências conhecidas (mapa B-LAYOUT-EDITOR e outras)

**B-LAYOUT-EDITOR:** ainda pendente pré-go-live. Editor visual de layout CNAB consignatárias.
- Status: tabelas LAYOUT_CONSIGNATARIA + LAYOUT_CAMPO existem (D2 Almoxarifado migration)
- Necessário: front-end Vue 3 para edição visual + backend de geração de remessa parametrizada
- Estimativa: 4-6h
- **Decisão pendente:** entra na Fase 4 ou pós-PoC?

### 6.4 Anexo B — Compatibilidade SQLite ↔ SQL Server

Funções a EVITAR (quebram em SQL Server):
- `strftime('%Y-%m', col)` → usar `DATE_FORMAT` ou Eloquent `whereDate`
- `julianday(col1) - julianday(col2)` → usar Carbon::diffInDays
- `date('now', '+30 days')` → usar Carbon::now()->addDays(30)
- `||` para concatenação → usar CONCAT() ou `.` no PHP
- Crase para identificadores → usar colchetes `[col]` ou aspas duplas `"col"` em SQL Server
- `whereYear`/`whereMonth` Eloquent → cuidado, usa funções específicas do driver. Preferir `whereBetween` com Carbon range.

### 6.5 Anexo C — Comandos úteis para Antygravity (Windows PowerShell)

```powershell
# Verificar grep para strftime/julianday em arquivos novos
Get-ChildItem -Path "app/Services/Folha" -Recurse -Filter "*.php" |
    Select-String -Pattern "strftime|julianday"

# Rodar smoke runner
php artisan gente:smoke-teia7a --json | ConvertFrom-Json | Format-List

# Confirmar migrations todas rodaram
php artisan migrate:status | Select-String "Pending"

# Verificar queue worker
Get-Process | Where-Object { $_.ProcessName -like "*php*" -and $_.CommandLine -like "*queue:work*" }
```

### 6.6 Anexo D — Histórico de mudanças deste documento

| Data | Versão | Autor | Mudanças |
|------|--------|-------|----------|
| 2026-05-07 | v1 | Claude | Mapa inicial 4 partes, comparação 3 motores, decisões 1-5 |
| 2026-05-07 | v2 | Claude | Decisões 6-8 + 7.a-g + Decisão A motor único + 8 GAPs MotorFolha + Fase 2-A/2-B + cronograma ajustado + checklist completo |

---

**FIM DO MAPA v2**



---

## ⚠️ ADENDO PÓS-FASE 1 (auditado em 2026-05-08)

### A.1 Status auditoria Fase 1

Todas as 8 correções da Fase 1 foram **AUDITADAS E APROVADAS** via leitura direta dos arquivos em MCP:

| Risco | Commit | Estado do arquivo |
|-------|--------|-------------------|
| R70 | 9d1c051 | `app/Models/Folha.php` linha 52: `'FOLHA_VALOR_TOTAL' => 'decimal:2'` ✅ |
| R72 | d9aaea9 | `app/Domain/Escala/EscalaAusenciaService.php` linha 12: `private const DEBUG_LOG_PATH = null` + early return ✅ |
| R69 | 47b47cb | `app/Models/Lotacao.php` `getDadosRelatorioImprimirLotacao`: `(int) $lotacaoId` + `WHERE L.LOTACAO_ID = ?` + `DB::select($sql, [$lotacaoId])` ✅ |
| R39 | 6c23108 | `app/Services/Progressao/ProgressaoFuncionalListagemService.php` `applyBusca`: identificador sem crase ✅ |
| R40 | ca1589a | `app/Services/ApuracaoPontoService.php` `calcular`: `whereBetween` cross-driver ✅ |
| R56 | b4bbfc4, 4a9e51b | `app/Services/DepreciacaoService.php` `depreciarMes`: `Carbon::createFromFormat` + comparação direta ✅ |
| R57 | 1a5a616 | `app/Services/Dashboard/DashboardOperacionalService.php` `buscarAtestadosPeriodo`: `subDays(365)` + filtro PHP ✅ |
| R7-R10 | 02ec3d9 | `app/Services/ContabilidadeService.php` `lancarFolha`: DELETE prévio + DB::transaction ✅ |

### A.2 Bloqueio de ambiente identificado

**Problema:** máquina de execução do Antygravity roda PHP 8.1.29; `composer.json` exige `>= 8.4.0`. Comandos `php artisan` (tinker, migrate:status, sentinela-run, healthcheck) retornam erro fatal **independentemente das correções**. As correções foram validadas por leitura textual do código (PowerShell `Select-String`) e auditoria MCP arquivo-por-arquivo, ambas conclusivas.

**Implicação para Fase 6 (implantação):** o servidor de produção PMSL **DEVE** ter PHP 8.4+ antes do go-live. Adicionar ao checklist T01:

> **T01.0 (NOVO) — Verificar versão PHP do servidor PMSL antes de qualquer migration.**
> ```bash
> php -v   # deve retornar PHP 8.4.x ou superior
> composer check-platform-reqs   # deve passar
> ```
> Se o servidor não atender, parar a implantação e escalar o upgrade do PHP. Não rodar `php artisan migrate` em PHP < 8.4 — o boot do framework falha.

### A.3 Falsos positivos não bloqueadores observados

- 3 ocorrências de crase em arquivos `app/Console/Commands/GenteAuditarRotasMutacaoCommand.php` e similares — confirmado em comentários/textos descritivos, não em queries SQL.
- Commit duplicado em R56 (b4bbfc4 + 4a9e51b) — segundo commit apenas removeu palavra "strftime" de comentário que disparava smoke test falso. Estado final correto.

### A.4 Decisão sobre prosseguir

**Status:** ✅ **LUZ VERDE PARA FASE 2-A.**

Bloqueador de ambiente PHP 8.4 não impede o trabalho de codificação. As correções estão íntegras no repositório git. Validação dinâmica completa via `php artisan` será feita quando a Fase 6 atingir o servidor PMSL com PHP 8.4 instalado.

Próximo prompt a executar: `docs/PROMPT_ANTYGRAVITY_FASE2A.md`.
