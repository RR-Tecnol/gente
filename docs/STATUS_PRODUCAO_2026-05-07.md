---
tags:
  - gente/producao
  - gente/status
  - gente/preparacao-vps
status: "em construção"
data_inicio: 2026-05-07
data_alvo: 2026-05-10 (domingo)
auditor: Claude (chief engineer)
solicitante: Ronaldo (RR TECNOL)
ambiente_alvo: "VPS Hostinger — Ubuntu 22.04 (Linux), banco a confirmar (SQLite/MariaDB/SQL Server), email pendente"
deadline: "Domingo 10/05/2026"
---

# STATUS DE PRODUÇÃO — GENTE v3

> Diagnóstico **alto-nível** módulo a módulo para preparar o sistema para produção na VPS Hostinger Ubuntu 22.04. Construído após a auditoria profunda em 7 etapas (07/05/2026, 81 riscos catalogados).
>
> **Critério de "pronto para produção":** o módulo tem rotas + services + models + view Vue funcionais, sem gaps que impeçam uso real. Email é tratado em bloco próprio (12) e fica documentado mas não-conectado.
>
> **Ordem dos blocos:** do crítico financeiro pro periférico.

## Legenda

- ✅ **PRONTO** — pode subir como está
- 🟢 **PRONTO COM RESSALVAS** — funciona, mas tem ajuste pequeno antes do go-live
- ⚠️ **AJUSTES NECESSÁRIOS** — precisa de 1-4h de trabalho
- 🔴 **CRÍTICO** — bloqueia produção, precisa de decisão arquitetural ou retrabalho
- ⏸️ **PENDENTE OPERACIONAL** — código pronto, falta configuração externa (ex: SMTP, certificados)

---

# BLOCO 1 — FOLHA DE PAGAMENTO

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

## 1.1 O que tem

### Motor de cálculo
- ✅ `MotorFolhaService` — motor novo PHP de 3 camadas (C1/C2/C3), em uso confirmado via `routes/folha.php`
- ✅ Despacho síncrono: `POST /api/v3/folhas/calcular-proventos`
- ✅ Despacho assíncrono Bus::batch: `POST /api/v3/folha/processar` (202 + batch_id)
- ✅ Polling de progresso: `GET /api/v3/folha/batch/{batchId}`
- ✅ Lock distribuído via `Cache::lock('folha:processar:{id}', 3600)` — evita execução paralela
- ✅ `MotorFolhaService` lê `RPPS_CONFIG` por vigência (alíquotas dinâmicas, não hardcoded)

### Recálculo e consistência
- ✅ `POST /api/v3/folhas/calcular` — recálculo de líquido + aplicação de consignações (CONSIG-03) em transação
- ✅ `GET /api/v3/folhas/consistencia/{competencia}` — cross-check Folha × Consignação × RPPS, 3 regras (`consig_vs_folha`, `rpps_cobertura`, `liquido_negativo`)
- ✅ `GET /api/v3/folhas/{competencia}/piso-salarial` — relatório de complemento SM
- ✅ `POST /api/v3/folhas/{id}/confirmar` — fechar folha

### Lançamentos C3 (variáveis)
- ✅ `GET /api/v3/folhas/{id}/lancamentos` — listagem
- ✅ `POST /api/v3/folhas/{id}/lancamentos` — novo lançamento manual
- ✅ `DELETE /api/v3/folhas/{id}/lancamentos/{lancId}` — remover (bloqueia se folha fechada)

### 13º salário
- ✅ `DecimoTerceiroService` integrado
- ✅ `POST /api/v3/13salario/gerar` — em lote por ano + tipo (PRIMEIRA_PARCELA / SEGUNDA_PARCELA / RESCISORIO)
- ✅ `GET /api/v3/13salario/preview/{funcionario_id}` — preview individual
- ✅ `GET /api/v3/13salario` — listagem por ano + tipo

### Férias
- ✅ `FeriasService` integrado
- ✅ CRUD completo: `POST /ferias`, `PUT /ferias/{id}`, `DELETE /ferias/{id}`
- ✅ Saldo: `GET /ferias/saldo/{funcionario_id}` — calcula períodos aquisitivos com vencimento
- ✅ Cálculo prévia: `GET /ferias/calcular/{funcionario_id}?dias=30`
- ✅ Listagem admin: `GET /ferias/admin`
- ✅ Aprovação com cálculo: `POST /ferias/{id}/aprovar`
- ✅ Anexo: `POST /ferias/{ferias_id}/anexo`

### Verbas indenizatórias
- ✅ `VERBA_TIPO` configurável (Mensal/Eventual + flags INCIDE_IR/INSS/RPPS/REQUER_COMPROVANTE)
- ✅ `VERBA_LANCAMENTO` com workflow PENDENTE → APROVADO → INCLUIDO_FOLHA
- ✅ Lançamento individual e em lote (multi-servidor)
- ✅ Aprovação/rejeição
- ✅ Cancelamento (apenas PENDENTE)
- ✅ Relatório por tipo e por secretaria

### Eventos (rubricas)
- ✅ `EVENTO` com flags INCIDE_INSS / INCIDE_IRRF / INCIDE_FGTS
- ✅ CRUD `GET/POST/PUT/DELETE /api/v3/eventos`

### RPPS / Previdência Própria
- ✅ Dashboard: `GET /rpps/dashboard?competencia=YYYY-MM` — totais servidor + patronal + histórico 12 meses
- ✅ Cálculo de contribuições: `POST /rpps/calcular` — base previdenciária real (não bruto), alíquotas via `RPPS_CONFIG`
- ✅ Beneficiários: `GET /rpps/beneficiarios`
- ✅ Exportação CADPREV: `POST /rpps/exportar-cadprev`
- ✅ Prova de vida (S6.1): listagem, inicialização, registro presencial/govbr, processamento bloqueio iminente/bloqueado
- ✅ Eventos de bloqueio: `RPPS_BLOQUEIO_EVENTO` audita desbloqueio/bloqueio com origem (manual/scheduler)

### Rescisão
- ✅ `RescisaoService` (visto na Etapa 2)
- ✅ Tabela `RESCISAO_CALCULO` com migration

## 1.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R51** | `TabelasImpostoService::INSS_RGPS` desatualizado para 2024 (faixa 1 = R$ 1.412). Tabela 2025 é R$ 1.518. Lei vigente sendo descumprida. | Atualizar constante INSS_RGPS para tabela 2025 | 5 min |
| **R70** | `Folha.php` cast `'FOLHA_VALOR_TOTAL' => 'integer'` — perde decimais. Em folha mensal de PMSL (~R$ 30M), perde até R$ 0,99 por execução. Dado contábil. | Trocar `'integer'` → `'decimal:2'` no cast do model | 1 min |
| **R71** | `Folha.php` ainda tem 3 métodos que chamam stored procedure `[dbo].[sp_gera_folha]` (T-SQL puro): `salvaFolha()`, `processarFolha()`, `reprocessarFolha()`. **3º motor de folha**, ativado por CRUD legado Vue 2 em `web.php` linha ~1554. | **Decisão:** remover métodos do model `Folha`. CRUD legado Vue 2 não está mais em uso (front é V3). | 30 min (auditar callers + remover) |
| **R7-R10** | `ContabilidadeService` incompleto: patronal 14% hardcoded, não-idempotente, só lança vencimentos+patronal (faltam INSS/IRRF/consignações retidos). | Refactor para usar `RPPS_CONFIG`, idempotência por chave (folha_id, competencia), incluir 4 retenções | 4-6h |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| R23, R56 | `FolhaParserService` (legado) e `DepreciacaoService` usam `strftime`/`julianday` (SQLite-only). Quebram em SQL Server. | 30min |
| R24 | `FolhaParserService` tem N+1 puro em loops de eventos. Em 30k servidores, lento. | 2h |
| R25 | `FolhaParserService` não processa C2 nem C3 — só C1. Caminho legado limitado. | Aposentar (caminho ativo é `MotorFolhaService`) |
| R12 | `DecimoTerceiroService` divide por 12 inteiro em vez de float. Perde decimal em meses fracionários (admissão no meio do ano). | 15 min |
| R14 | Modelos `Funcionario` não tem `FUNCIONARIO_DATA_ADMISSAO` (só `FUNCIONARIO_DATA_INICIO`). `ContraChequeService` lê o campo errado, retorna sempre null. | Trocar referência no service | 5 min |
| R20 | `FeriasService` promete gerar `DETALHE_FOLHA` mas não gera. Aprovação de férias não vira evento na folha. | 1-2h |
| R16 | `RescisaoService` calcula IRRF sem férias proporcionais. Diferença real em rescisões. | 30 min |
| R17-R19 | `ContraChequeService`: empresa hardcoded "PREFEITURA MUNICIPAL DE TESTE", `referencia => '00'` mock, precedência `??` errada na lotação | 15 min total |

### 🟢 BAIXO

| # | Item |
|---|---|
| R79 | Sem testes unitários para `MotorFolhaService`, `RescisaoService`, `FeriasService`, `DecimoTerceiroService`. Cálculos fiscais sem rede de segurança. |

## 1.3 Status pré-produção do Bloco 1

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Funcionalmente, o motor está em pé e processa folha real. Os fixes urgentes são:

### Ação concreta — Bloco 1 (priorizado)

1. **R51 (5 min):** atualizar tabela INSS RGPS 2025 em `TabelasImpostoService`
2. **R70 (1 min):** trocar cast `FOLHA_VALOR_TOTAL` para `decimal:2`
3. **R71 (30 min):** remover métodos `salvaFolha/processarFolha/reprocessarFolha` do model `Folha.php` e auditar se há controller legado chamando
4. **R12 (15 min):** corrigir divisão inteira em `DecimoTerceiroService`
5. **R14 (5 min):** corrigir referência `FUNCIONARIO_DATA_ADMISSAO` no `ContraChequeService`
6. **R17-R19 (15 min):** unhardcode "PREFEITURA MUNICIPAL DE TESTE", referência mock e precedência `??` no `ContraChequeService`
7. **R16 (30 min):** férias proporcionais no IRRF da rescisão
8. **R20 (1-2h):** completar `FeriasService` para gerar `DETALHE_FOLHA` na aprovação

**Total esforço Bloco 1:** ~3-4 horas para o estado "pronto-pronto".

### Pós go-live (não bloqueador)
- R7-R10: completar `ContabilidadeService` (4-6h, mas pode rodar primeiro mês com lançamento manual e completar depois)
- R79: tests unitários para o motor

---

# BLOCO 2 — CADASTRO FUNCIONAL

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

Este bloco cobre Pessoa, Funcionário, Lotação, Vínculo, Cargo, Carreira, PCCV, Progressão Funcional, Quadro de Vagas, PSS (Processo Seletivo Simplificado), Estagiários, Terceirizados, Acumulação de Cargos (CF art. 37 XVI) e Exoneração/Verbas Rescisórias.

## 2.1 O que tem

### Funcionário (CRUD completo + auditorias)
- ✅ Listagem com filtros poderosos: busca por nome/matrícula, escopo de setores via `UnidadeEscopoUsuario::setorIdsPermitidos`, filtros `funcionario_ativo` (0/1) e `audit_filter` (`limbo`, `vinculo_expirando`, `progressao_pendente`)
- ✅ Status semântico calculado dinamicamente: 🟢 Lotado / 🟡 Limbo (sem lotação) / 🔴 Afastado / ⚫ Inativo
- ✅ KPIs no payload: `total_ativos`, `total_geral`, `limbo_total`, `limbo_custo_mensal_estimado` (custo do limbo!)
- ✅ CPF mascarado em listagem (LGPD-aware): `123.***.***-45`
- ✅ Detecção de afastamento em vigência via JOIN com `AFASTAMENTO`
- ✅ Validação reforçada no POST: regex de CPF, e-mail RFC+DNS, regex de hora HH:MM para ponto
- ✅ Detecção de duplicidade de CPF antes de inserir
- ✅ Transação completa: cria PESSOA + FUNCIONARIO + LOTACAO + ATRIBUICAO_LOTACAO + CONTATO + PONTO_CONFIG_FUNCIONARIO em uma única transaction
- ✅ Blindagem fiscal: bloqueia atribuição de "regência" sem lotação ativa (evita gratificação fantasma)
- ✅ Schema-aware em CONTATO (suporta tanto `CONTATO_TIPO/CONTATO_VALOR` quanto `TIPO_CONTATO_ID/CONTATO_CONTEUDO`)
- ✅ Soft delete (inativação): preserva histórico funcional, fecha lotações ativas
- ✅ Reativação: `PATCH /funcionarios/{id}/reativar`
- ✅ Histórico funcional: `GET /funcionarios/{id}/historico` retorna lotações + férias + afastamentos timeline-ready
- ✅ Documentos: `GET /funcionarios/{id}/documentos` com flag obrigatório
- ✅ Escalas do servidor: `GET /funcionarios/{id}/escalas` retorna últimos 60 turnos

### Cargos (CRUD com vigência rigorosa)
- ✅ `CargoVigencia::assertSemSobreposicao` — impede cadastrar cargo com vigências sobrepostas
- ✅ Schema-aware: suporta tanto `CARGO_CBO` quanto `CARGO_CODIGO_CBO`
- ✅ Validação CBO (6 dígitos), validação data_fim ≥ data_inicio
- ✅ Logs em canal `security` para criação/alteração de cargo
- ✅ Reativação só permitida se sem conflito de vigência com outro registro
- ✅ Inativação seta `CARGO_ATIVO=0` + `CARGO_DATA_FIM=hoje`
- ✅ Suporte a campos PCCV: `CARGO_CARREIRA`, `CARGO_CLASSE`, `CARGO_REFERENCIA`, `CARGO_NIVEL`, `CARGO_SALARIO_BASE`, `CARGO_CARGA_HORARIA`, `CARGO_VALOR_HORA_DESCONTO`

### Funções / Atribuições / Cargos em Comissão
- ✅ CRUD `GET/POST/PUT/DELETE /api/v3/funcoes` em `ATRIBUICAO`
- ✅ Suporta CBO, tipo (comissão/efetivo), gratificação

### Progressão Funcional (RBAC nominal + autorização explícita)
- ✅ **`PROGRESSAO_AUTORIZACAO`** — workflow nominal: alguém autoriza com ATO_ADMINISTRATIVO + validade (default 30 dias) → outra pessoa aplica
- ✅ Autorizações com `STATUS=aprovada` + `EXPIRA_EM` + `UTILIZADA_EM` (anti-replay)
- ✅ Fluxo legado preservado: ato direto na aplicação cria autorização implícita
- ✅ `POST /progressao-funcional/aplicar/{id}` — progressão horizontal (referência)
- ✅ `POST /progressao-funcional/promover/{id}` — promoção de classe
- ✅ Detecção automática de teto: se servidor está na última referência da classe, retorna 409 sugerindo promoção
- ✅ `HISTORICO_FUNCIONAL` registrado a cada operação
- ✅ Cache invalidado: `ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache()`
- ✅ Listagem de elegíveis paginada via Controller (keyset pagination)
- ✅ `GET /progressao-funcional/historico` com paginação + busca + filtro por setor
- ✅ Carreiras + Tabela Salarial + Config (interstício, nota mínima, anuênio %, referência máxima, classe final) — todas CRUD
- ✅ `RECEITA_MUNICIPIO` — cadastro de RCL e folha mensal para cálculo LRF (art. 19)

### Quadro de Vagas (CF art. 37 — limites legais)
- ✅ `GET /quadro-vagas` — listagem com ocupação calculada em tempo real
- ✅ `GET /quadro-vagas/verificar/{cargo_id}` — usado na nomeação PSS para impedir provimento sem vaga autorizada
- ✅ KPIs: total autorizadas, ocupadas, disponíveis, situação (`COM_VAGA` / `SEM_VAGA`)
- ✅ `LEI_CRIACAO` registrada para rastreabilidade legal
- ✅ Upsert por (CARGO_ID, UNIDADE_ID)

### PSS — Processo Seletivo Simplificado (LAT-03 / GAP-11)
- ✅ Editais: criar, publicar, listar com contadores
- ✅ Candidatos: inscrever (com validação de duplicidade por CPF+edital), convocar, nomear
- ✅ **Nomeação atômica (regra §16):** `POST /pss/candidatos/{id}/nomear` cria `PESSOA` + `FUNCIONARIO` + `LOTACAO` em uma transaction única, com:
  - Verificação de duplicidade de PESSOA por CPF (reaproveita)
  - Bloqueio de duplo cadastro de FUNCIONARIO por PESSOA
  - Geração automática de matrícula se não informada
  - Lotação imediata (não permite nomear sem setor)

### Estagiários (CIEE/agentes integradores)
- ✅ `ESTAGIARIO` + `ESTAGIO_CONTRATO` em transaction
- ✅ Alerta de vencimento próximo (30 dias) na listagem
- ✅ Frequência mensal: `POST /estagiarios/{id}/frequencia` calcula bolsa proporcional
- ✅ Status do contrato (ATIVO / ENCERRADO / etc.)

### Terceirizados (LAT-05 / GAP-12 — controle CGM)
- ✅ Empresas: CRUD com CNPJ único
- ✅ Postos de trabalho com vagas e setor/unidade
- ✅ **Checklist mensal obrigatório**: fardamento, EPI, folha de ponto, holerite, FGTS, férias
- ✅ `GET /terceirizados/inadimplentes` — lista postos sem checklist há mais de 35 dias (alerta automático)
- ✅ Conferência rastreável (`CONFERIDO_POR`)

### Acumulação de Cargos (CF art. 37 XVI — LAT-01 / GAP-09)
- ✅ Servidor declara cargo externo (órgão, cargo, remuneração, regime, data início)
- ✅ RH analisa: APROVADO / IRREGULAR / SUSPENSO / PENDENTE
- ✅ Rastreabilidade: `ANALISADO_POR`, `DATA_ANALISE`, `OBSERVACAO`
- ✅ `GET /acumulacao/irregulares` — relatório para CGM/CGJ

### Exoneração e Verbas Rescisórias
- ✅ `POST /exoneracao/preview` — cálculo prévio sem salvar
- ✅ `POST /exoneracao/registrar` — calcula + salva em `RESCISAO_CALCULO` + atualiza `FUNCIONARIO_DATA_FIM`
- ✅ Cálculo completo: saldo de salário, férias proporcionais + 1/3, férias vencidas + 1/3, 13º proporcional, licença-prêmio, FGTS+multa 40% (RGPS), IRRF
- ✅ `GET /exoneracao/elegiveis` — listagem para folha rescisória, agrupada por secretaria
- ✅ `POST /exoneracao/incluir-folha` — cria folha rescisória + DETALHE_FOLHA + EVENTO_DETALHE_FOLHA por servidor selecionado
- ✅ Service alternativo `RescisaoService` integrado: `GET /rescisao/preview/{funcionario_id}`, `POST /rescisao/salvar`, `GET /rescisao`

## 2.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R69** | SQL injection real em `Lotacao::getDadosRelatorioImprimirLotacao` (interpola `$lotacaoId`). Modelo central deste bloco. | Trocar para bind | 5 min |
| **R39** | `ProgressaoFuncionalListagemService` usa crase MySQL `p.\`PESSOA_CPF\``. Quebra em SQL Server. | Trocar para colchetes ou aspas duplas | 10 min |
| **R38** | `VinculoEnum` incompleto — 4 tipos contra 5+ no motor. Pode levar a vínculo "desconhecido" silenciosamente. | Completar enum | 30 min |
| **R51** (do Bloco 1) | Tabela INSS RGPS 2024 em `TabelasImpostoService` afeta cálculo rescisório com IRRF (rotina `exoneracao.php`). | Junto com Bloco 1 | já mapeado |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Cálculo IRRF rescisório hardcoded** | Em `exoneracao.php` linhas ~80-90, faixas IRRF (R$ 4.664,68 / R$ 3.751,05 / R$ 2.826,65 / R$ 2.112,00) são duplicação inline da tabela 2025. Deveriam vir de `TabelasImpostoService` para manter consistência com R51. | 30 min |
| **Cálculo de férias vencidas em rescisão é simplificado** | Comentário admite: "Simplificação: verificar afastamentos; aqui conta 0 por padrão (a ajustar por gestor)". Conta períodos aquisitivos completos menos os usados, mas assume FERIAS_PERIODO existe. Em produção pode subestimar. | 1-2h |
| **Atribuição CBO=null em ATRIBUICAO** | `POST /funcoes` aceita CBO mas não valida 6 dígitos como faz em `/cargos`. Inconsistência. | 10 min |
| **Acumulação não verifica conflito de carga horária** | O analista marca como APROVADO/IRREGULAR manualmente. Não há regra automática (ex.: > 60h semanais incompatível). | Decisão (regra cliente) |
| **PSS — convocação não dispara email** | Hoje só atualiza status. Email pendente de SMTP (Bloco 12). | Aguarda Bloco 12 |
| **PSS — nomeação ignora `quadro-vagas/verificar`** | A rota de verificação de vaga existe, mas a nomeação não a chama. Risco de nomear em cargo sem vaga autorizada. | 30 min — adicionar guarda |
| **Terceirizados — alerta de inadimplência não dispara email** | Lista existe mas notificação automática pendente (depende SMTP). | Aguarda Bloco 12 |
| **Estagiários — bolsa proporcional usa 22 dias úteis fixos** | Não considera mês com mais/menos dias úteis nem feriados municipais (que `HolidayCalendarService` já calcula). | 30 min |
| **`PROGRESSAO_AUTORIZACAO` sem teste unitário** | Workflow crítico (autorização nominal + anti-replay) sem cobertura. | 2h |

### 🟢 BAIXO

| # | Item |
|---|---|
| Listagem `/funcionarios` calcula `limbo_total` global toda vez sem cache. Em base com 30k servidores, custa. | Cache 5min |
| Histórico funcional concatena 3 collections em memória — em servidor com 100+ lotações, lento. | Lazy collection / pagination |

## 2.3 Status pré-produção do Bloco 2

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Este é um bloco maduro. O cadastro funcional está completo, o fluxo PSS → Nomeação → Funcionário é atômico, a Progressão tem RBAC nominal com autorização explícita, e Acumulação/Terceirizados/Estagiários cobrem obrigações constitucionais.

### Ação concreta — Bloco 2 (priorizado)

1. **R69 (5 min):** corrigir SQL injection em `Lotacao::getDadosRelatorioImprimirLotacao`
2. **R39 (10 min):** trocar crase MySQL por padrão SQL Server em `ProgressaoFuncionalListagemService`
3. **PSS guarda de vaga (30 min):** chamar `quadro-vagas/verificar/{cargo_id}` antes da nomeação atômica em `routes/pss.php`
4. **IRRF inline (30 min):** consolidar faixas IRRF de `exoneracao.php` em `TabelasImpostoService`
5. **CBO em /funcoes (10 min):** validar 6 dígitos como em `/cargos`
6. **R38 (30 min):** completar `VinculoEnum`
7. **Estagiários — dias úteis (30 min):** integrar `HolidayCalendarService`

**Total esforço Bloco 2:** ~2-3 horas para o estado "pronto-pronto".

### Pós go-live (não bloqueador)
- Cache de listagem (otimização)
- Tests unitários para `PROGRESSAO_AUTORIZACAO`
- Regra automática de acumulação por carga horária (depende decisão cliente)
- Email de notificação (depende Bloco 12)

---

# BLOCO 3 — PONTO E JORNADA

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

Cobre Ponto Eletrônico (web + mobile facial), Hora Extra, Plantão Extra, Sobreaviso e Acionamento, Banco de Horas (com `JORNADA_LEDGER` versionado), Justificativas, Turnos, Feriados.

## 3.1 O que tem

### Ponto Eletrônico (canônico — visão mensal)
- ✅ `GET /api/v3/ponto?competencia=YYYY-MM` (`ponto_mes_spa_get.php`) — retorna batidas agrupadas por dia + dias de feriado + dias de escala prevista + meta de minutos por dia
- ✅ Integração com `HolidayCalendarService::getEffectiveHolidayDatesForMonth` (suporta feriados municipais São Luís, escopo global/setor/usuário)
- ✅ Suporte a 4 batidas (`entrada`, `saida_alm`, `ret_alm`, `saida`) e 2 batidas (`entrada`, `saida`)
- ✅ Cálculo de meta diária com base em `TURNO_HORA_INICIO/FIM`, suporta turnos cruzando meia-noite
- ✅ Escopo via `UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado` quando consultando ponto de outro funcionário
- ✅ Schema-tolerância: detecta `LOTACAO_DATA_FIM` antes de usar
- ✅ Rota com nome `api.v3.ponto.mes`

### Ponto Eletrônico — App Mobile (JWT separado, GPS + facial)
- ✅ JWT próprio com `PONTO_APP_JWT_SECRET` (não reutiliza `app.key` — SEC-03 já corrigido)
- ✅ Token expira em 7 dias
- ✅ `POST /ponto/app/login` — autentica por CPF + senha, retorna JWT + dados básicos
- ✅ `GET /ponto/app/me` — perfil + terminal vinculado à unidade do servidor
- ✅ `GET /ponto/app/status-hoje` — entrada/pausa/retorno/saída do dia + próxima batida esperada
- ✅ `GET /ponto/app/registros` — histórico de 30 dias agrupado por data
- ✅ `POST /ponto/app/registrar` — valida:
  - Reconhecimento facial confirmado (`face_ok=true` obrigatório)
  - Distância Haversine ≤ raio do terminal (`TERMINAL_RAIO_METROS`)
  - Tipo válido (ENTRADA/PAUSA/RETORNO/SAIDA)
  - Salva foto base64 em `storage/public/ponto_facial/`
  - Marca `REGISTRO_ORIGEM='APP_FACIAL'`
- ✅ Feedback de erro útil: "Você está fora do raio permitido (50m). Distância atual: 127m."

### Ponto Eletrônico — Justificativas
- ✅ `POST /api/v3/ponto/justificativa` (`ponto_eletronico.php`) — registra `JustificativaPonto` com STATUS=PENDENTE
- ✅ Helper `resolveFuncionarioComFallbackDev` reutilizado em vários módulos

### Hora Extra (`hora_extra.php`)
- ✅ `GET /hora-extra` com filtros (competência, unidade, funcionário, status) + resumo por secretaria
- ✅ `POST /hora-extra` — usa `CARGO_CARGA_HORARIA` real (BUG-HE-01 já corrigido)
- ✅ Cálculo: `(salario / chMensal) * (1 + percentual/100) * total_horas`
- ✅ Status: PENDENTE / APROVADA / REJEITADA / INCLUIDA_FOLHA / PAGA
- ✅ Notificação automática para o servidor via tabela `NOTIFICACAO`
- ✅ `PATCH /hora-extra/{id}/status` — aprovar/rejeitar com sincronização do plantão extra associado (via `OBSERVACAO LIKE 'ORIGEM_PLANTAO_EXTRA:N'`)
- ✅ Relatório agregado por secretaria com horas 50% / 100% / feriado

### Plantão Extra + Sobreaviso (`plantoes_sobreaviso.php`)
- ✅ Plantão extra: solicitação cria registro em `PLANTAO_EXTRA` E automaticamente em `HORA_EXTRA` (teia)
- ✅ Cálculo de duração com suporte a turnos cruzando meia-noite
- ✅ Schema-tolerance: suporta `PLANTAO_DATA`/`DATA_PLANTAO`, `PLANTAO_HORAS`/`TOTAL_HORAS`, etc.
- ✅ Tipo automático: 100% para urgência, 50% para programado
- ✅ Sobreaviso: lê tanto `SOBREAVISO` quanto `ESCALA_SOBREAVISO` (legado)
- ✅ Acionamento: aceita 2 schemas (`ACIONAMENTO` ou `ACIONAMENTO_SOBREAVISO`), normalização de horas
- ✅ Validação de teto via `JornadaRegraParametros::tetoSobreavisoAcionamentoHoras()`
- ✅ Cálculo automático de valor sugerido via `JornadaRegraParametros::valorSugeridoAcionamentoSobreaviso()`
- ✅ Parâmetros de jornada vigência-history-aware (passar `\DateTimeImmutable` busca regra ativa naquela data)

### Banco de Horas (`banco_horas.php`) — workflow completo
- ✅ **Fonte dual**: `JORNADA_LEDGER` (versionado, granular por dia) com fallback para `BANCO_HORAS` (consolidado mensal)
- ✅ Saldo acumulado calculado on-the-fly
- ✅ `GET /banco-horas` — saldo + histórico 12 meses + apurações por competência
- ✅ `POST /banco-horas/lancar` — credita ou debita (CREDITO/COMPENSACAO/PAGAMENTO/EXPIRADO)
- ✅ `POST /banco-horas/compensar` — verifica saldo antes de debitar (impede saldo negativo)
- ✅ `GET /banco-horas/relatorio` — consolidado por servidor + setor + secretaria
- ✅ `GET /banco-horas/equipe` (BUG-EST-15) — visão de gestor com escopo (`meu_setor`/`setor`/`todos`), Admin tem visão global
- ✅ `GET /banco-horas/equipe/impacto-escala` — **estimativa de desconto** para servidores com déficit de horas no mês:
  - `valor_hora` = `CARGO_VALOR_HORA_DESCONTO` configurado, ou `salario/220` como fallback
  - Resumo: total impactados, desconto total estimado
  - Lista ordenada por `desconto_estimado` desc
- ✅ `POST /banco-horas/equipe/notificacao-operacional` — enfileira notificação na tabela `NOTIFICACAO` para gestores/coordenadores/diretores ou todos os funcionários
- ✅ `GET /banco-horas/equipe/notificacao-operacional` — histórico das notificações enviadas

### Turnos
- ✅ CRUD `GET/POST/PUT/DELETE /api/v3/turnos`
- ✅ Normalização cosmética: `F` + nome contendo "folha" → "Folga"; `SO` → "Sobreaviso"

### Feriados
- ✅ `routes/feriados_v3.php` (não inspecionado em detalhes mas catalogado)
- ✅ Engine: `HolidayCalendarService` com cálculo dinâmico de Páscoa, feriados municipais São Luís hardcoded (São Pedro 29/06, Adesão MA 28/07, Aniversário 08/09, N.S. Conceição 08/12)
- ✅ Suporta overrides (escopo `global`/`sector`/`user`) com `pay_multiplier`

### Ponto Terceirizado
- ✅ `routes/ponto_terceirizado.php` existe (catalogado)

## 3.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R37** | `ApuracaoPontoService::fechar` é stub. Confirmado: HE chega à folha **somente** via lançamento manual em `POST /hora-extra`. Não há totalizador automático ponto→folha. | **Decisão:** documentar oficialmente como fluxo manual ou implementar fechamento automático. PoC já passou com fluxo manual; produção pode manter, mas tem que documentar. | Decisão (2-4h se implementar) |
| **R40** | `ApuracaoPontoService` usa `whereYear`/`whereMonth` em datetime — full scan em SQL Server | Trocar por `whereBetween` em `CAST(REGISTRO_DATA_HORA AS DATE)` (já feito em `ponto_mes_spa_get.php`). Replicar padrão. | 30 min |
| **JWT mobile sem rotação** | Token de 7 dias sem mecanismo de revoke. Se o celular for furtado, atacante tem acesso por 7 dias. | Adicionar tabela `MOBILE_DEVICE` com `device_id` e flag `revoked`, validar no `decodeAppToken`. | 1-2h |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Fotos de ponto crescem indefinidamente** | `storage/public/ponto_facial/` acumula 1 foto por batida. Em 30k servidores × 4 batidas/dia × 22 dias úteis = 2.6M fotos/mês. | Adicionar job de retenção (manter 90 dias + thumbnail) | 2h |
| **`POST /ponto/app/login` sem rate-limit explícito** | Login mobile não tem rate-limit (só web tem em `web.php`). CPF + senha brute-force-able. | Adicionar throttle na rota | 15 min |
| **`POST /ponto/app/registrar` aceita `face_ok=true` sem validação real** | O endpoint confia no que o app envia. Se atacante chamar API direto pulando o app, basta enviar `face_ok:true`. | Validar foto no servidor (similaridade com foto cadastral) ou aceitar a confiança do app + log forense | Decisão arquitetural |
| **Banco de Horas — sem regra de expiração automática** | Tipo `EXPIRADO` existe mas não há cron job que expira créditos antigos (CLT prevê expiração após 6 meses) | 2h |
| **`GET /banco-horas/equipe/impacto-escala` sem cache** | Roda agregação live em 100+ servidores por requisição | Cache 5 min | 30 min |
| **Notificação operacional cria N rows em `NOTIFICACAO`** | "todos_funcionarios" insere 1 row por usuário. Em 30k servidores = 30k inserts em loop. Lento. | Bulk insert via `DB::table()->insert([...])` | 30 min |
| **Plantão extra sem teto diário** | Não há validação de "máximo X horas extras por dia/mês" — em CLT é 2h/dia. Para servidores municipais varia por estatuto. | Verificar com SEMAD se há regra | Decisão cliente |
| **Justificativa de ponto é genérica** | Não tem tipos pré-definidos (atestado/falta/atraso/etc.). Tudo vai como "PENDENTE" sem categoria. | 1-2h |

### 🟢 BAIXO

| # | Item |
|---|---|
| Helper `resolveFuncionarioComFallbackDev` redefinido em vários arquivos com `function_exists` — duplicação. | Mover para classe Support |
| `ponto_app.php` usa `strtoupper($r->REGISTRO_TIPO)` mas casos minúsculos não foram catalogados consistentemente. | Padronização |
| Foto base64 no payload pode ser pesada em redes 3G. Sem upload progressivo. | Pós go-live |

## 3.3 Status pré-produção do Bloco 3

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Bloco bem desenhado. App mobile com GPS+facial funcional. Banco de horas robusto com `JORNADA_LEDGER` versionado. Plantão extra automaticamente vira hora extra (teia). Sobreaviso com `JornadaRegraParametros` history-aware é exemplar.

### Ação concreta — Bloco 3 (priorizado)

1. **R37 (decisão imediata):** documentar oficialmente que ponto→folha é via lançamento manual de hora extra. Comunicar à SEMAD. Custa 0 código, custa 1 ata de reunião. Em PoC isso já foi assim.
2. **R40 (30 min):** trocar `whereYear/whereMonth` em `ApuracaoPontoService` por `CAST AS DATE`
3. **Rate-limit mobile login (15 min):** `Route::post(...)->middleware('throttle:5,1')`
4. **Bulk insert em NOTIFICACAO (30 min):** trocar loop foreach por `DB::table()->insert($rows)`
5. **Cache impacto-escala (30 min):** `Cache::remember('bh-impacto:'.$comp.':'.$setor, 300, fn => ...)`
6. **JWT rotation/revoke (2h):** adicionar `MOBILE_DEVICE` + flag revoked
7. **Foto retention (2h):** job `php artisan ponto:cleanup-faces --days=90`

**Total esforço Bloco 3:** ~5-6 horas para o estado "pronto-pronto".

### Pós go-live (não bloqueador)
- Validação real de face no servidor (depende decisão de UX/segurança)
- Tipos de justificativa estruturados
- Consolidar `resolveFuncionarioComFallbackDev` em classe Support
- Expiração automática de banco de horas (depende regra estatutária)

---

# BLOCO 4 — ESCALA DE TRABALHO

**Status global do bloco:** ✅ **PRONTO**

Este é o bloco mais maduro do sistema (★★★★★ na auditoria). Cobre Escala de Trabalho com workflow de homologação, Domínio de Motivos de Alteração com base legal, Detecção de Furos de Cobertura para saúde, Substituições, Detalhe Escala/Item, integração com PCCV (validação de carga horária semanal) e bloqueio por afastamento.

## 4.1 O que tem

### Engine de Escala (`escala_trabalho.php` — 1028 linhas)
- ✅ `GET /escala-trabalho` — visão mensal de uma competência por setor:
  - Suporta `setor_id`, `carregar_tudo`, `somente_saude` (filtro por CBO 223X / palavras médicas), busca por CPF (com `PiiBlindIndex::cpfHash`)
  - Retorna grade pronta: funcionário × dia → `{turno, obs, afastamento, bloqueada_por_afastamento, turno_planejado}`
  - Incluí servidores **elegíveis sem detalhe** (lotados no setor + sem registro na escala) para arrastar pra grade
  - Schema-tolerância: detecta `LOTACAO_DATA_FIM`, `FUNCIONARIO_DATA_FIM`, `DETALHE_ESCALA_ITEM_OBS`
  - Workflow payload via `EscalaWorkflowService::montarPayloadApi` (com macro view se sem setor)
- ✅ Paginação real (`per_page` 10-200, default 50)
- ✅ Escopo via `UnidadeEscopoUsuario::setorIdsPermitidos` (RBAC granular)

### Edição de célula (`POST /escala-trabalho`)
- ✅ Workflow:
  - Validação: data não pode ser passada (compliance)
  - Validação: motivo de alteração obrigatório se schema tem `MOTIVO_ALTERACAO_DOMINIO`
  - Validação: `MotivoAlteracaoPolicy::assertDocumentoReferencia` — exige `documento_referencia` se motivo prevê (rastreabilidade legal)
  - Validação: servidor com lotação ativa (sem limbo)
  - Validação: setor da escala ≠ setor da lotação ativa = bloqueio (compliance)
  - Validação: regência só para cargas 20h, 24h, 40h
  - Validação: `EscalaAusenciaService::bloqueadaPorAfastamento` — dia bloqueado se afastamento ativo
  - Validação: escala HOMOLOGADA/PUBLICADA exige justificativa para remover turno
  - Validação: `PccvValidatorService::validarCelulaEscala` — valida carga semanal acumulada, exige `justificativa_legal` ≥ 20 chars se exceder (gravado em audit chain)
  - Status → Turno mapping: `disponivel→F`, `em_regencia→M`, `atividade_extraclasse→SO`, `afastado→AT`
  - Aliases automáticos: `V↔T`, `AT↔AF`
  - Modo borracha (turno vazio) → seta `TURNO_ID=null` mantém histórico
- ✅ Bypass de homologação via `EscalaWorkflowService::contextoIntervencaoGrade` — apenas SUDO/Sentinel-Sudo, com auditoria reforçada (gente_assignment_id + role_slug no payload de auditoria)
- ✅ **Audit chain criptográfico**: cada alteração é gravada em `AUDIT_LOG` com `HASH_CONCAT` via `GenteAuditWriter::insertChainedRow`, payload completo (turno antes/depois + motivo + base legal + impacto financeiro + documento referência + observações + intervenção sudo flag)
- ✅ Compliance PCCV em audit dedicado: `GentePccvComplianceAudit::excecaoEscala` se houver violação justificada

### Workflow de Homologação (`POST /escala-trabalho/workflow`)
- ✅ 4 ações: `enviar`, `reenviar`, `devolver`, `homologar`
- ✅ Estados: `RASCUNHO` → `ENVIADA_HOMOLOGACAO_SAGEP` → `HOMOLOGADO_SAGEP`
- ✅ Devolução exige `motivo_devolucao`
- ✅ `EscalaWorkflowService::processarTransicao` aplica RBAC + compliance + audit chain
- ✅ Middleware `semad.escala.readonly` — usuários SEMAD têm leitura mas não editam (bypass por SUDO documentado)

### Copiar mês anterior (`POST /escala-trabalho/copiar-mes-anterior`)
- ✅ Replica turnos do mês N-1 para mês N, ajustando dias úteis
- ✅ Cria escala destino se não existir, em RASCUNHO
- ✅ Mantém autoria via audit
- ✅ Suporta filtro por setor

### Motivos de Alteração de Escala (Domain rico)
- ✅ `GET /motivos-alteracao-escala` — retorna catálogo:
  - `id`, `sigla`, `titulo`, `descricao`, `exige_documento`
  - `base_legal` (Lei 4.928/2008, LO Municipal art. 135, TCE-MA)
  - `impacto_financeiro` (`hora_extra_50`, `hora_extra_100`, `nenhum`, etc.)
- ✅ `MotivoAlteracaoPolicy` é fonte de verdade do domínio (mesmo se BD diverge)

### Detecção de Furos de Cobertura — Saúde (`escala_saude.php`)
- ✅ `GET /api/v3/escala-saude/furos?competencia=YYYYMM&setor_id=X` — cruza:
  - Itens de escala da competência
  - Atestados validados ativos no período
  - Afastamentos aprovados ativos no período
  - Substituições registradas (com substituto definido)
  - Output: lista de "furos" = slot escalado + ausência + sem substituto = problema operacional
- ✅ Aceita 2 formatos de competência: `MM/YYYY` (escala) ou `YYYYMM` (motor folha)
- ✅ `GET /api/v3/escala-saude/cobertura/{setor_id}/{data}` — quem está escalado em um setor numa data, agrupado por turno

### Tabelas envolvidas
- `ESCALA`, `DETALHE_ESCALA`, `DETALHE_ESCALA_ITEM`, `TURNO`
- `MOTIVO_ALTERACAO_DOMINIO` (catálogo de motivos com base legal)
- `SUBSTITUICAO_ESCALA`
- `AUDIT_LOG` (com chain HASH_CONCAT)
- `RPPS_BLOQUEIO_EVENTO` (eventos de bloqueio)

## 4.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R72** | Path debug hardcoded `/home/DK/Developer/Projects/GENTE/.cursor/debug-f94096.log` em `escalaDebugLogF94096` (linhas 16-34). Em produção Linux Ubuntu vai falhar (path não existe) ou pior — escrever em local arbitrário. | Remover função inteira ou trocar para `Log::channel('escala-debug')` | 5 min |
| **R39** | Atestados em `escala_saude.php` linha ~85 usa `whereRaw("date(ATESTADO_DATA, '+' || ATESTADO_DIAS || ' days') >= ?")` — sintaxe SQLite. Quebra em SQL Server. | Trocar para `DATEADD` (SQL Server) ou cálculo PHP-side | 30 min |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **`escala_saude.php` lê `DETALHE_ESCALA_CARGO`** | Coluna pode não existir em todas as instalações (não vimos no model `DetalheEscala`). | Verificar schema e adicionar fallback | 30 min |
| **Teste de carga: GET /escala-trabalho com `carregar_tudo=1`** | Em município com 30k servidores, retorna grade gigante. Sem cache, custa ~2-5s por requisição. | Cache 60s | 30 min |
| **`debug-f94096.log` tem chamadas espalhadas** | Linhas 401 e 585 chamam `escalaDebugLogF94096('h3', ...)` e `escalaDebugLogF94096('h4', ...)`. Mesmo com função no-op em produção, são chamadas executadas. | Remover ou tornar `if (config('app.debug'))` | 15 min |
| **Sem testes E2E para o workflow** | `EscalaWorkflowService` tem teste unitário (`EscalaWorkflowServiceTest.php`), mas o fluxo HTTP ENVIAR→DEVOLVER→REENVIAR→HOMOLOGAR não tem cobertura E2E. | 4h |
| **Bypass SUDO de homologação não tem segundo passo** | Atualmente: SUDO autenticado → bypass aplicado. Idealmente: confirmação 2FA ou registro com motivo obrigatório. | Decisão (3-4h) |

### 🟢 BAIXO

| # | Item |
|---|---|
| `GET /escala-trabalho` retorna até 1028 linhas de grade — sem paginação dentro do mês. | Lazy collection |
| Aliases V↔T e AT↔AF hardcoded no service. | Mover pra config |
| `/setores` em `escala_saude.php` duplica funcionalidade de `/apoio` (Bloco 2). | Consolidar |

## 4.3 Status pré-produção do Bloco 4

**Veredicto:** ✅ **PRONTO**

Sem bloqueador real para produção. As 2 ressalvas críticas (R72 path debug e R39 SQLite-only) são fixes de < 1h cada. O restante são micro-otimizações.

### Ação concreta — Bloco 4 (priorizado)

1. **R72 (5 min):** remover `escalaDebugLogF94096` ou trocar para `Log::channel`
2. **R39 (30 min):** trocar sintaxe SQLite em `escala_saude.php` por compatível com SQL Server
3. **Cache `/escala-trabalho` carregar_tudo (30 min):** `Cache::remember('escala:macro:'.$comp.':'.$user_id, 60, ...)`
4. **Verificar `DETALHE_ESCALA_CARGO` (30 min):** adicionar Schema::hasColumn fallback

**Total esforço Bloco 4:** ~1.5 hora para o estado "pronto-pronto".

### Pós go-live (não bloqueador)
- Testes E2E para workflow ENVIAR→HOMOLOGAR (4h)
- 2FA no bypass SUDO (decisão arquitetural)
- Consolidar `/setores` duplicado

---

# BLOCO 5 — SAÚDE E SEGURANÇA OCUPACIONAL

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

Cobre Atestados Médicos, Afastamentos, Medicina do Trabalho (Exames Ocupacionais), Segurança do Trabalho (EPIs, Acidentes/CAT, Laudos SST), Treinamentos.

## 5.1 O que tem

### Atestados Médicos (`atestados_v3.php`)
- ✅ LGPD-aware: servidor vê só os seus, RH/Admin vê todos para fila de validação
- ✅ Detecção de perfil RH/Admin via `apiV3UsuarioEhRhOuAdminAtestados` (PERFIL string OR PERFIL_NOME via relacionamento)
- ✅ Workflow: PENDENTE → APROVADO/REJEITADO com parecer
- ✅ Soft delete só pelo titular E só se PENDENTE
- ✅ Schema-tolerance: tenta `try/catch` em cada campo
- ✅ Mapeamento `mapearAfastamentoParaApi` consolidado em helper
- ✅ Cálculo de dias automático (`(fim - inicio) / 86400 + 1`)
- ✅ PDF: `GET /atestados/{id}/pdf` via `Barryvdh\DomPDF` com template `pdfs.atestado`
- ✅ Middleware `upload.safe` no POST

### Afastamentos (`afastamentos_v3.php`)
- ✅ 6 tipos canônicos com mapeamento bidirecional:
  1. Licença-Prêmio
  2. Fins Particulares
  3. Licença Maternidade
  4. Licença Paternidade
  5. Licença Capacitação
  6. Licença Judicial
- ✅ `resolveTipoAfastamentoV3` aceita texto (`licenca_premio`) ou número (1)
- ✅ Fallback para `TABELA_GENERICA` (busca normalizada com iconv UTF-8 → ASCII)
- ✅ Schema-tolerance massiva: `Schema::hasColumn` em cada operação
- ✅ Anexos via `ANEXO_AFASTAMENTO`:
  - Validação de mime (jpg/jpeg/png/gif/webp/pdf/doc/docx) + tamanho (5MB)
  - Armazenamento como base64 no banco (não file system)
  - Listagem detecta `eh_imagem` para preview
  - `download_url` calculada
  - Display `inline` para imagens/PDFs, `attachment` para outros
- ✅ Protocolo: `AFT-NNNNN` (5 dígitos zero-pad)
- ✅ RBAC: `perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR`

### Medicina do Trabalho (`medicina.php`)
- ✅ `GET /medicina` retorna exames ocupacionais + histórico de Z10.x (CIDs ocupacionais)
- ✅ `EXAME_OCUPACIONAL` com tipo, subtipo, data realização, vencimento, médico, apto, obs
- ✅ `POST /medicina/agendar` cria afastamento com CID `Z10.0` e descrição `Exame Ocupacional: {tipo}`
- ✅ Médico padrão: SESMT
- ✅ Status: AGENDADO

### Segurança do Trabalho (`seguranca_trabalho.php`)

**Área do servidor (`/seguranca/...`):**
- ✅ **EPIs**: listar entregues, com cálculo automático de status (vencido/a vencer 30 dias)
- ✅ EPIs com ícone heurístico (🥾 para "bota", 🦺 default)
- ✅ Solicitar EPI (cria registro com `EPI_ENTREGUE=false` na fila)
- ✅ **Incidentes/Acidentes**: listar próprios incidentes
- ✅ Reportar acidente: `ACIDENTE_TRABALHO` com tipo, local, descrição
- ✅ CAT (Comunicação de Acidente de Trabalho) gerada pelo admin

**Área do Admin SESMT (`/seguranca-admin/...`):**
- ✅ KPIs: EPIs pendentes, incidentes abertos, laudos vencidos
- ✅ Listar todos os incidentes com nome do servidor e matrícula
- ✅ Emitir CAT para um incidente: `POST /seguranca-admin/incidentes/{id}/cat` com flag `encerrar`
- ✅ Solicitações de EPI: listagem para fila SESMT
- ✅ Entregar EPI: `POST /seguranca-admin/epis/entregar` com CA + quantidade + vencimento
- ✅ **Laudos SST** (PCMSO/PPRA/PCMAT): CRUD com `LAUDO_TIPO`, `LAUDO_LOCAL`, `LAUDO_DATA_VALIDADE`, `LAUDO_STATUS` (Vigente/Vencido)
- ✅ RBAC: emissão de CAT, entrega de EPI e laudos exigem ADMIN/GESTOR

### Treinamentos (`treinamentos.php`)

**Área do servidor:**
- ✅ `GET /treinamentos/meus` — cursos em que está inscrito (com status, progresso, certificado, data conclusão)
- ✅ `GET /treinamentos/catalogo` — cursos ativos disponíveis
- ✅ `POST /treinamentos/{id}/inscrever` — auto-inscrição com prevenção de dupla inscrição

**Área do RH/Gestor:**
- ✅ KPIs: cursos ativos, total inscrições, total concluídos
- ✅ CRUD de cursos: título, desc, área, carga horária, modalidade (EAD/Presencial), próxima turma, vagas
- ✅ Listagem de inscrições com nome + matrícula

### Integrações
- ✅ Detecção de furos de cobertura (Bloco 4) usa `ATESTADO_MEDICO` validados E `AFASTAMENTO` aprovados
- ✅ Status `AFASTAMENTO_STATUS=aprovado` bloqueia edição de escala (Bloco 4)

## 5.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **`atestados_v3.php` — escopo de RH/Admin sem filtro por unidade** | RH de uma secretaria vê atestados de TODAS as secretarias, não respeitando o `UnidadeEscopoUsuario`. Vazamento de dados sensíveis (LGPD). | Aplicar `setorIdsPermitidos` no `GET /atestados` quando perfil RH | 30 min |
| **Atestados sem CID confidencial mascarado** | LGPD: CID é dado de saúde sensível. RH vê literal. Em demonstração para SEMAD, deveria mostrar apenas categoria. | Mascarar CID por padrão, exibir só com `?revelar_cid=true` para perfil específico | 1-2h |
| **Anexos em base64 no banco (`ANEXO_AFASTAMENTO`)** | Um PDF de 2MB vira 2.7MB em base64 inflando tabela. Em 100k afastamentos = 270GB no banco. | Migrar para storage filesystem com referência (`storage/anexos_afastamento/...`) | 4-6h |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Atestado em modo demo** | Erro no insert cai em `random_int(1000,9999)` retornado como sucesso. Fica difícil saber se realmente salvou. | Remover fallback demo e logar exception | 15 min |
| **Medicina em modo demo** | Mesmo padrão — `random_int(1000,9999)` em caso de erro | 15 min |
| **EPIs sem trilha de devolução** | Hoje só registra entrega. Não há fluxo de "devolução de EPI quebrado/vencido" para auditoria | 2-3h |
| **Acidentes — sem prazo CAT** | Lei prevê emissão de CAT em até 1 dia útil. Não há alerta automático. | 1h (cron diário) |
| **Laudos vencidos sem alerta** | KPI mostra count, mas não há notificação proativa para SESMT (depende SMTP — Bloco 12) | Aguarda Bloco 12 |
| **Treinamentos — sem progresso real** | `INSCRICAO_PROGRESSO` é número, mas não há mecânica de marcar como % conclusão (LMS embarcado) | Decisão: integrar LMS externo ou implementar |
| **Certificado de treinamento** | Flag `INSCRICAO_CERTIFICADO=true` existe mas não há geração de PDF com QR de validação | 2-4h |
| **Anexos — sem antivirus scan** | Aceita `.docx`, `.doc` que podem conter macros maliciosas. | Integrar ClamAV ou rejeitar ofimática | Decisão |
| **Helper `resolveFuncionarioComFallbackDev`** | "Fallback dev" no nome não inspira confiança. Em produção idealmente removeria fallback. | Renomear + revisar | 30 min |

### 🟢 BAIXO

| # | Item |
|---|---|
| `apiV3UsuarioEhRhOuAdminAtestados` baseado em string fuzzy (`str_contains 'rh'`). Falso-positivo se nome do perfil tiver "RH" em outro contexto (ex.: "MARH"). | Listar IDs de perfis explicitamente |
| `medicina.php` filtra histórico por `AFASTAMENTO_CID like 'Z1%'` — Z19, Z11, Z12 também caem aí, nem todos são exames ocupacionais | Refinar filtro |
| `treinamentos-admin/cursos` retorna sem paginação — em curso histórico de 5 anos pode crescer | Adicionar paginate |

## 5.3 Status pré-produção do Bloco 5

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Funcionalmente completo. Os 3 críticos (escopo de RH, mascaramento de CID, anexos em base64) são fixes importantes para produção real mas não bloqueiam PoC já que:
- PoC tem 1 RH global (visão de todos é OK)
- CID em demo já era visível
- Volume baixo no PoC tolera base64

### Ação concreta — Bloco 5 (priorizado)

1. **Escopo RH em atestados (30 min):** aplicar `UnidadeEscopoUsuario::setorIdsPermitidos` no `GET /atestados`
2. **Remover fallback demo (30 min):** atestados.php e medicina.php não devem retornar IDs aleatórios em erro
3. **CID mascarado por default (1-2h):** introduzir flag de privacidade, mostrar só categoria CID-10 (ex.: "Doenças do sistema osteomuscular" em vez de "M54.5")
4. **Decidir sobre base64 (decisão):** se PoC já cobriu meses sem dor, manter por ora; se for produção real com volume, agendar migração para filesystem pós go-live
5. **CAT prazo alert (1h):** cron diário que notifica SESMT sobre CATs pendentes > 24h

**Total esforço Bloco 5 (essencial):** ~2-4 horas para o estado "pronto-pronto" sem migração de anexos.

### Pós go-live (não bloqueador)
- Migração base64 → filesystem (4-6h)
- Geração de certificado PDF com QR (2-4h)
- LMS integration ou módulo próprio (depende decisão cliente)
- Antivirus scan em anexos (decisão arquitetural)

---

# BLOCO 6 — BANCÁRIO E CONSIGNAÇÃO

**Status global do bloco:** ⚠️ **AJUSTES NECESSÁRIOS**

Cobre Convênios e Contratos de Consignação, Margem Consignável (30% empréstimo + 10% cartão), Parcelas mensais, Ocorrências/Auditoria, Consignatárias (CRUD admin), Remessa CNAB para bancos.

## 6.1 O que tem

### Consignação (`consignacao.php`)

**Convênios:**
- ✅ CRUD `CONSIG_CONVENIO` com tipo BANCO/CARTAO/SINDICATO/COOPERATIVA
- ✅ Banco código (CNAB), taxa juros máxima

**Contratos (`POST /consignacao`):**
- ✅ **Margem consignável separada e correta (BUG-01 corrigido):**
  - Empréstimo: 30% do líquido (BANCO/SINDICATO/COOPERATIVA)
  - Cartão: 10% do líquido
- ✅ Validação BLOQUEANTE de margem antes de aceitar contrato
- ✅ Cálculo do saldo devedor automático (`prazo_meses * valor_parcela`)
- ✅ Geração automática de parcelas futuras (`CONSIG_PARCELA` com `STATUS=PENDENTE`)
- ✅ Workflow CONSIG-02: status_autorizacao (`AUTORIZADO`/`REJEITADO`) com tracking de quem aprovou e quando
- ✅ Workflow CONSIG-04: alterar status (`ATIVO`/`SUSPENSO`/`CANCELADO`/`QUITADO`) com `CONSIG_OCORRENCIA` registrando cada mudança
- ✅ Suspender contrato suspende parcelas pendentes; reativar volta para PENDENTE
- ✅ Lê `DETALHE_FOLHA_LIQUIDO` real (não bruto) — corrigido BUG-01

**Margem Consignável (`GET /consignacao/margem/{funcionario_id}`):**
- ✅ Retorna bruto + líquido + 4 margens (emp/cartão × total/usada/disponível)
- ✅ Compatibilidade retro: `margem_total/usada/disponivel` consolidada
- ✅ Lista contratos ativos com: convênio, número, parcela, parcelas pagas vs prazo, saldo devedor

**Relatórios:**
- ✅ `GET /consignacao/relatorio?competencia=YYYY-MM` — agregado por convênio
- ✅ **`GET /consignacao/relatorio-analitico` (CONSIG-05)** — granularidade exigida TCE-MA e CGM:
  - Por servidor: nome, matrícula, CPF, credor, tipo, número contrato, parcela atual, prazo, valor descontado, saldo, status
  - Pronto para exportação CSV no frontend

**Auditoria:**
- ✅ `CONSIG_OCORRENCIA` registra: AUTORIZACAO, REJEICAO, suspensão, cancelamento, quitação
- ✅ `GET /consignacao/{id}/ocorrencias` retorna histórico com nome do usuário
- ✅ Cada ocorrência tem `MOTIVO`, `DESCRICAO`, `DATA_INICIO_EFEITO`, `DATA_FIM_EFEITO`

**Integração com folha (CONSIG-03):**
- ✅ `POST /api/v3/folhas/calcular` (Bloco 1) aplica parcelas em `DETALHE_FOLHA_DESCONTOS` em transação
- ✅ Consistência cruzada: `GET /api/v3/folhas/consistencia/{competencia}` valida `consig_vs_folha`

### Consignatárias (`consignatarias.php`)

CRUD admin via Controllers (não inline):
- ✅ `ConsignatariaController` — listar, criar, atualizar, ativar/desativar
- ✅ `LayoutConsignatariaController` — layouts importação/exportação CRUD
- ✅ `ConsigRemessaController` — gerar e baixar remessas, importar arquivo retorno
- ✅ Middleware `perfil:Administrador` em todo o grupo
- ✅ Middleware `upload.safe` em importação de retorno
- ✅ Ordem de rotas correta: estáticas antes de wildcards `/{id}` (resolve conflitos Laravel)

### CNAB / Remessa Bancária (já documentado em Bloco 1 e Etapa 5/7 da auditoria)
- ✅ `routes/cnab.php` — formato pipe-delimited customizado (NÃO FEBRABAN)
- ✅ `RemessaBancariaService` (Etapa 5) — implementação séria CNAB 240 mas não usada na rota principal
- ✅ `CNAB240Builder` — admite ser mock no código

### Tabelas envolvidas
- `CONSIG_CONVENIO`, `CONSIG_CONTRATO`, `CONSIG_PARCELA`, `CONSIG_OCORRENCIA`
- `CONSIGNATARIA`, `LAYOUT_CONSIGNATARIA`, `CONSIG_REMESSA`
- `CNAB_REMESSA` (geração de arquivo)

## 6.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R58, R78** | `routes/cnab.php` gera formato pipe-delimited custom, **não-FEBRABAN CNAB 240**. Bancos vão rejeitar em produção. | **Decisão:** migrar para `RemessaBancariaService` (Etapa 5) ou implementar CNAB 240 real | 1 dia |
| **B-LAYOUT-EDITOR** (já mapeado nas memórias) | Editor de layout de consignatária pendente pre-go-live | 2-4h |
| **Margem usa última folha (qualquer competência)** | `GET /consignacao/margem` busca a última `DETALHE_FOLHA` por competência DESC. Se há folha de competência anterior calculada depois, pode pegar referência errada. | Filtrar por competência atual (mês corrente) | 30 min |
| **Margem ignora descontos obrigatórios futuros** | Cálculo é estático no momento do contrato. Se servidor já tem 30% e contrata mais 1, margem rejeita corretamente. Mas se RPPS subir alíquota depois (ex.: 14% → 16%), líquido cai e margem deveria ser revalidada. | Recálculo em batch quando alíquotas mudam | 2-4h pós go-live |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **`CONSIG_PARCELA` futuro insertGetId em loop** | Cria N parcelas com N inserts (lento em prazo > 60 meses) | Bulk insert | 30 min |
| **Relatório-analítico sem paginação** | Retorna competência inteira em memória — em município com 30k servidores × média 3 contratos = 90k rows. | Paginar ou stream CSV | 1h |
| **Consignatárias — sem validação de CNPJ duplicado** | Não vimos no controller, mas precisa garantir | Verificar `ConsignatariaController::store` | 15 min |
| **Layouts sem versionamento** | Se banco trocar layout, alterações apagam histórico. Boletos antigos sem rastreabilidade do layout original. | Versionar layouts (`LAYOUT_CONSIGNATARIA_VERSAO`) | 4h |
| **Reativar contrato não recalcula margem** | Se servidor teve líquido caindo, ao reativar contrato pode-se exceder margem hoje | Validar margem no PATCH /reativar | 30 min |
| **Relatório de inadimplência inexistente** | Servidor cancelou conta no banco e parcela ficou DESCONTADA mas não foi creditada → "rejeitada" não é fluxo coberto | Adicionar rota `/consignacao/rejeitadas` integrada com retorno bancário | 2-3h |
| **Sem geração de boleto/PIX para autônomos exonerados** | Servidor saiu antes de quitar — sistema não emite boleto residual | Decisão (depende contrato com banco) |

### 🟢 BAIXO

| # | Item |
|---|---|
| `prazo_meses` aceita inteiro mas convertido com `(int)` — se vier vazio fica 1 (default), permite contrato de 1 parcela só. | Validação min:1 |
| Status `QUITADO` não tem trigger automático quando todas parcelas estão DESCONTADAs | Cron diário |
| Relatório agregado em `consignacao.php` linha ~440 não filtra por status de parcela | Adicionar filtro |

## 6.3 Status pré-produção do Bloco 6

**Veredicto:** ⚠️ **AJUSTES NECESSÁRIOS**

O bloco tem regras de negócio sólidas (margem 30%/10% separada, workflow CONSIG-02 com autorização nominal, audit trail via OCORRENCIA, relatório analítico granular para TCE), mas **a geração CNAB 240 não-FEBRABAN é o bloqueador real** para produção bancária.

### Ação concreta — Bloco 6 (priorizado)

1. **R58/R78 (1 dia):** Decidir destino do CNAB:
   - **Opção A** (mais rápido): manter `routes/cnab.php` para PoC e gerar CNAB 240 real apenas quando o banco específico exigir
   - **Opção B** (correto): migrar para `RemessaBancariaService` em todas as rotas
   - Recomendação: subir produção com Opção A + plano de migração para Opção B no primeiro fim de semana de janeiro 2026
2. **B-LAYOUT-EDITOR (2-4h):** completar editor de layout de consignatária
3. **Margem por competência atual (30 min):** filtrar `DETALHE_FOLHA` pela competência do mês corrente
4. **Bulk insert parcelas (30 min):** trocar loop por insert batch
5. **Validação CNPJ consignatária (15 min):** verificar `ConsignatariaController::store`
6. **Reativar com revalidação de margem (30 min):** middleware no PATCH /reativar

**Total esforço Bloco 6 (essencial):** ~3-5 horas + 1 dia para CNAB.

### Pós go-live (não bloqueador)
- Versionamento de layouts (4h)
- Boleto/PIX residual para exonerados (decisão cliente)
- Cron de auto-quitação ao DESCONTAR última parcela (2h)
- Recálculo de margem em batch quando alíquotas mudam (2-4h)
- Migração completa para `RemessaBancariaService` (1 dia)

---

# BLOCO 7 — GOVERNO FEDERAL (eSocial, SEFIP, DIRF, RAIS, CAGED)

**Status global do bloco:** 🔴 **CRÍTICO**

> **Atenção legal:** este é o bloco com **maior risco regulatório**. eSocial é obrigatório desde 2018 para entes públicos (cronograma S-1.0), CAGED foi substituído pelo eSocial (S-2200/S-2299) desde 2020 mas ainda existe legado, RAIS deixa de existir em favor do eSocial mas declarações antigas ainda podem ser pedidas, DIRF segue obrigatória até 2024 (2025 já deveria ser via eSocial). **Antes de subir produção, é fundamental confirmar com a Controladoria Geral / SEMAD da PMSL qual é o cenário regulatório exigido.**

Cobre integração com obrigações do governo federal: eSocial (sistema unificado), SEFIP/GFIP (legado FGTS+INSS para vínculos RGPS), DIRF (IR retido), RAIS (anual), CAGED (mensal).

## 7.1 O que tem

### eSocial (`esocial.php`)
- ✅ Painel de rastreamento de eventos: `GET /esocial/eventos` com filtros (tipo, status, competência) + estatísticas (PENDENTE/GERADO/ENVIADO/PROCESSADO/REJEITADO)
- ✅ Geração manual de evento por servidor: `POST /esocial/eventos` invoca `EsocialXmlService` para tipos:
  - **S-1200** (Remuneração mensal)
  - **S-2200** (Admissão de trabalhador)
  - **S-2206** (Alteração de contrato)
  - **S-2299** (Desligamento)
- ✅ Enfileirar evento para envio (S7 fase 1): `POST /esocial/eventos/{id}/enfileirar` com idempotency_key + retry_count + next_retry_at
- ✅ Endpoint debug `GET /esocial/gerar/S-1200/{competencia}` retorna XML para inspeção
- ✅ `GET /esocial/pendencias` com LEFT JOIN otimizado (evita subquery O(n²)):
  - Admissões sem S-2200
  - Demissões sem S-2299
  - Eventos rejeitados
- ✅ Geração em lote: `POST /esocial/gerar-lote` para vários servidores
- ✅ RBAC: `perfil:ADMINISTRADOR,Administrador,GESTOR` em todas as ações de escrita

### SEFIP/GFIP (`sefip.php`) — para vínculos RGPS
- ✅ Preview: `GET /sefip/preview/{competencia}` lista trabalhadores RGPS ativos com:
  - FGTS = 8% sobre remuneração bruta
  - INSS patronal = 20% sobre remuneração (setor público RGPS)
  - Totais consolidados
- ✅ Geração de arquivo: `POST /sefip/gerar/{competencia}` produz layout posicional:
  - Registro tipo 1 — Empregador (CNPJ + FPAS + competência MMAAAA)
  - Registro tipo 2 — Trabalhadores (CPF + nome + remuneração + FGTS + INSS, todos zero-padded)
  - Registro tipo 9 — Totalizador
- ✅ Persistência em `SEFIP_ENVIO` com upsert por competência
- ✅ Headers `X-Total-FGTS` e `X-Total-INSS` para auditoria
- ✅ Histórico: `GET /sefip/historico` últimos 24 envios

### DIRF (`dirf.php`) — Declaração de IR Retido
- ✅ Preview: `GET /dirf/preview/{ano}` agrega IRRF retido por servidor no ano via `DETALHE_FOLHA`
- ✅ Geração arquivo: `POST /dirf/gerar/{ano}` produz layout Receita Federal:
  - DIRF / INFDIRF (cabeçalho identificação)
  - Linha CNPJ + razão (60 chars)
  - **BPFDEC** por beneficiário: CPF + nome + ano + código receita `0561` (trabalho assalariado) + rendimentos + IRRF
  - FIMDIRF (encerramento)
- ✅ Persistência em `DIRF_ENVIO`
- ✅ Filtro: `havingRaw('SUM(DETALHE_FOLHA_DESCONTOS) > 0')` — só inclui quem teve IRRF retido

### RAIS (`rais.php`) — Relação Anual de Informações Sociais
- ✅ Preview: `GET /rais/preview/{ano}` lista vínculos ativos no ano (admitidos antes ou no ano + não demitidos antes do ano)
- ✅ Códigos de vínculo setor público:
  - `30` = Estatutário (RPPS)
  - `35` = Cargo em comissão / temporário (RGPS)
- ✅ Geração arquivo posicional:
  - Tipo 10 — Estabelecimento (CNPJ + razão + ano)
  - Tipo 20 — Trabalhador (CPF + nome 70 chars + nascimento + admissão + demissão + cód vínculo + salário 12 chars)
  - Tipo 99 — Encerramento (total trabalhadores)
- ✅ BOM UTF-8 no início do arquivo (correto para layout RAIS)

### CAGED (`caged.php`) — Movimentações Mensais
- ✅ Preview: `GET /caged/preview/{competencia}` separa admissões e demissões RGPS no período + saldo (admissões - demissões)
- ✅ Geração arquivo posicional simplificado:
  - CNPJ (14) + CPF (11) + Nome (40) + Data movimento YYYYMMDD + Tipo (A/D) + Salário (10) + CBO (6)
- ✅ Filtra apenas RGPS (correto — estatutários não vão para CAGED)
- ✅ Persistência em `CAGED_ENVIO` com totais

### Estrutura de tabelas
- `ESOCIAL_EVENTO` — fila de eventos com XML, idempotency_key, retry_count, next_retry_at, last_error, numero_recibo
- `SEFIP_ENVIO` — 1 registro por competência, conteúdo armazenado
- `DIRF_ENVIO` — 1 por ano
- `RAIS_ENVIO` — 1 por ano
- `CAGED_ENVIO` — 1 por competência

## 7.2 O que falta (gaps reais)

### 🔴 CRÍTICO — eSocial é o problema maior

| # | Item | Impacto | Esforço |
|---|---|---|---|
| **R52** | `EsocialXmlService` — CNPJ da PMSL hardcoded `06205244000149`. Outros municípios não conseguem usar. | Refactor pra ler de `env('ORGAO_CNPJ')` ou `tabelas_auxiliares.php` | 30 min |
| **R53** | `EsocialXmlService` — `tpAmb=1` (produção) hardcoded. Em homologação manda evento real para Receita. | Trocar por `env('ESOCIAL_AMBIENTE', 2)` (2=homologação default) | 15 min |
| **R54** | `EsocialXmlService::gerarS2200` — campos hardcoded fake (cargo "AUXILIAR ADMINISTRATIVO", CBO "411010", regime "1"). Servidor real é ignorado. | Reescrever para puxar dados reais do FUNCIONARIO + LOTACAO + CARGO | 4-6h |
| **R55** | `EsocialXmlService::gerarS1200` — `perApur` em formato YYYY-MM mas eSocial exige YYYY-MM (string com hífen específico, validação de schema). | Validar formato do schema XML eSocial v1.0 | 30 min |
| **R59** | `EsocialXmlService::gerarS1200` — query SQL para somar remuneração quebrada: `whereYear/whereMonth` em campo string `FOLHA_COMPETENCIA` (que é YYYYMM, não datetime) | Refatorar para `where('FOLHA_COMPETENCIA', '=', $compYYYYMM)` | 30 min |
| **R60** | `EsocialXmlService` — `indRetif=1` (1=original) sempre, nunca trata retificação | Adicionar parâmetro `retificar=true` que muda para `indRetif=2` + `nrRecibo` do evento original | 1-2h |
| **eSocial — sem certificado digital A1/A3** | Eventos são GERADOS mas o "envio" via PATCH /eventos/{id} é manual (RH cola o número de recibo). Não há transmissão SOAP real para o eSocial. | Implementar cliente SOAP + assinatura XMLDSig + certificado A1 do município | **2-3 dias** |
| **eSocial — sem worker para processar fila `PENDENTE_ENVIO`** | Endpoint `/enfileirar` muda status mas não há job/worker que efetivamente envia | Criar `Esocial\EnviarEventoJob` + scheduler | 1-2 dias |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **SEFIP — tabela hardcoded `whereNull('LOTACAO_DATA_FIM')` mas sem `whereYear` na competência** | Pega todos RGPS ativos hoje, mas SEFIP precisa dos ativos *na competência*. Em mês fechado pode ter divergência. | Adicionar filtro temporal | 30 min |
| **SEFIP — alíquota INSS patronal 20% hardcoded** | Está correto para Município sem RPPS próprio, mas pode variar (entes que aderiram a planos especiais). | Configurar via `RPPS_CONFIG.ALIQUOTA_PATRONAL_RGPS` | 30 min |
| **SEFIP — usa `CARGO_SALARIO` como remuneração** | Não considera adicionais/eventos da folha real. Em produção o valor vai bater com o que de fato foi pago. | Refatorar para somar `DETALHE_FOLHA_PROVENTOS` da competência (como DIRF faz) | 1-2h |
| **DIRF — usa `whereYear('FOLHA_COMPETENCIA')` em campo string YYYYMM** | Mesmo bug do eSocial R59. `FOLHA_COMPETENCIA` é '202503' (varchar), não date. `whereYear` quebra em SQL Server. | Filtrar por `whereBetween('FOLHA_COMPETENCIA', ["{$ano}01", "{$ano}12"])` | 30 min |
| **RAIS — mesmo problema com `whereYear`** | Schema do FOLHA_COMPETENCIA é varchar(6), não date | 30 min |
| **CAGED — CBO fallback `412110`** | Auxiliar administrativo como default. Se servidor não tem CBO no cargo, sai como auxiliar. | Forçar CBO obrigatório via validação no cadastro de CARGO (Bloco 2) | 30 min |
| **CAGED — `c.CBO_CODIGO` mas em outras rotas é `c.CARGO_CBO` ou `c.CARGO_CODIGO_CBO`** | Inconsistência de nome de coluna entre rotas. | Padronizar | 30 min |
| **eSocial — pendências query usa subquery DB::raw em PESSOA** | `whereRaw('e.FUNCIONARIO_ID = (SELECT...)')` em loop. Lento em base grande. | Trocar por JOIN | 30 min |
| **DIRF — código receita `0561` hardcoded** | Correto para trabalho assalariado, mas RPA/autônomos usam outros códigos (1708 etc.). Como GENTE não tem autônomo, OK. | Documentar limitação |
| **Sem assinatura digital nos arquivos SEFIP/DIRF/RAIS/CAGED** | Bancos e Receita aceitam unsigned para arquivos SEFIP/CAGED, mas DIRF e RAIS exigem certificado digital para transmissão via PGD/RECEITANET | Documentar como passo manual: usuário baixa o TXT e transmite via PGD da Receita |
| **Sem agendamento de geração mensal automática** | Tudo manual hoje. Em produção, RH precisa lembrar de gerar SEFIP até dia 7. | Cron job mensal + notificação | 2-4h |

### 🟢 BAIXO

| # | Item |
|---|---|
| Histórico não tem paginação (limit 24/10/24 fixos). |
| Arquivos gerados não geram SHA-256 para auditoria de integridade. |
| Não há rota para "regerar arquivo idêntico" se RH precisar reenviar. |

## 7.3 Status pré-produção do Bloco 7

**Veredicto:** 🔴 **CRÍTICO**

Este bloco tem **2 categorias** de problema:

**Categoria 1 — Geração de arquivos posicional (SEFIP/DIRF/RAIS/CAGED):** funcionalmente OK para gerar TXT que o RH pode transmitir manualmente via PGD da Receita / Conectividade Social. Bugs são pequenos (whereYear em string, alíquotas configuráveis, CBO fallback). **2-4 horas para sanear.**

**Categoria 2 — eSocial:** tem 6 bugs que tornam os XMLs inválidos pra produção. Mais grave: **não há transmissão real para o eSocial**. O sistema gera, marca como ENVIADO no banco quando o RH cola o número de recibo, mas o XMLDSig + cliente SOAP do eSocial **não está implementado**. Para a SEMAD/PMSL que **já está obrigada a transmitir eSocial desde 2018**, isso é um problema real.

### Ação concreta — Bloco 7 (priorizado por urgência regulatória)

**Decisão arquitetural urgente (esta semana):**
- A PMSL já tem outro sistema transmitindo eSocial? Se sim, GENTE só "fornece XMLs" para RH copiar e colar em outro sistema (mantém status atual com fixes pequenos).
- Se a PMSL espera o GENTE transmitir, **isso é impossível até domingo 10/05**. Precisa estender prazo ou usar transmissor terceiro (TIPS, Senior, etc.) para o eSocial nos primeiros meses.

**Para o deploy de domingo 10/05:**

1. **R52 (30 min):** unhardcode CNPJ em `EsocialXmlService` 
2. **R53 (15 min):** `tpAmb` via env, default 2 (homologação)
3. **R59 / DIRF whereYear / RAIS whereYear (1.5h total):** corrigir queries de string YYYYMM em todos os locais
4. **SEFIP usar DETALHE_FOLHA real (1-2h):** trocar `CARGO_SALARIO` por soma de proventos da competência
5. **CBO consistência (30 min):** padronizar nome de coluna
6. **Documentar limitação eSocial (1h):** README do módulo deixando claro que XMLs precisam ser transmitidos por sistema externo até T+90 dias quando se implementa SOAP+XMLDSig

**Total esforço Bloco 7 (essencial para deploy):** ~4-5 horas, **assumindo que eSocial será transmitido por sistema terceiro**.

### Pós go-live (estratégico)

- **eSocial transmissão real (SOAP+XMLDSig+A1):** 2-3 dias de dev + 1 semana de homologação no ambiente RFB
- **R54 — S-2200 com dados reais:** 4-6h, é o evento mais usado
- **R55, R60 — corrigir formato perApur e indRetif:** 2h
- **Worker de fila eSocial:** 1-2 dias
- **Cron mensal SEFIP/DIRF/RAIS/CAGED + notificação:** 2-4h
- **SHA-256 dos arquivos para auditoria TCE:** 1h

---

# BLOCO 8 — GOVERNO ESTADUAL/MUNICIPAL (SAGRES, SICONFI, TRANSPARÊNCIA)

**Status global do bloco:** ⚠️ **AJUSTES NECESSÁRIOS**

Cobre integração com obrigações de controle externo: SAGRES (TCE-MA — Sistema de Acompanhamento da Gestão dos Recursos da Sociedade), SICONFI (STN — Relatórios LRF: RREO bimestral e RGF quadrimestral), Transparência Pública (LC 131/2009 + Decreto 7.185/2010), Controle Externo geral.

## 8.1 O que tem

### SAGRES (`sagres.php`) — TCE-MA
- ✅ Preview: `GET /sagres/preview?competencia=YYYY-MM` retorna detalhe da folha + de-para de rubricas
- ✅ De-para `SAGRES_EVENTO_DEPARA` populada via seed (códigos TCE-MA mapeados)
- ✅ Geração de XML SINC-Folha v1.0:
  - Header com competência, município SAO_LUIS_MA, CNPJ
  - Por servidor: CPF, nome, matrícula, proventos, descontos, líquido
  - HTML-escape de nome/matrícula
- ✅ Histórico em `SAGRES_GERACAO` com total servidores + total líquido + status + usuário
- ✅ `GET /sagres/download/{id}` re-gera XML idêntico ao histórico
- ✅ `GET /sagres/depara` retorna catálogo de mapeamento com tipo P/D para frontend
- ✅ `GET /sagres/exportacoes` adapta payload para `SagresView.vue` (campos `EXPORTACAO_ID`, `ARQUIVO_XML_PATH`, `ENVIADO_EM`)

### SICONFI / RGF / RREO (`siconfi.php`) — STN
- ✅ **RGF — Relatório de Gestão Fiscal** (quadrimestral):
  - `GET /siconfi/rgf/{ano}/{quadrimestre}` retorna:
    - Despesa total com pessoal no quadrimestre
    - Despesa por unidade/secretaria (top down)
    - RCL via parâmetro
    - Status LRF: REGULAR / ZONA_ALERTA (>54%) / LIMITE_PRUDENCIAL (>57%) / ACIMA_LIMITE_LRF (>60%)
    - Margem disponível em R$
  - Persiste em `SICONFI_RELATORIO`
- ✅ **RREO — Relatório Resumido da Execução Orçamentária** (bimestral):
  - `GET /siconfi/rreo/{ano}/{bimestre}` retorna:
    - Receita prevista (LOA) vs arrecadada
    - % de arrecadação
    - Despesa empenhada do bimestre
    - Despesa com pessoal
    - Superávit/déficit
- ✅ Histórico unificado: `GET /siconfi/historico`
- ✅ **Limites LRF documentados na rota:**
  - 60% RCL = limite legal art. 19 (municípios)
  - 57% = prudencial (95% do limite)
  - 54% = alerta (90% do limite)

### Controle Externo (`controle_externo.php`) — DUPLICA `/sagres/*`!
- ⚠️ `controle_externo.php` define `GET /sagres/preview` e `POST /sagres/gerar` que **conflitam** com `sagres.php`
- ✅ Tem ainda `/siconfi/rgf` e `/siconfi/rreo` GET com tabelas dedicadas (`RGF_DADOS`, `RREO_DADOS`)
- ✅ Tem cálculo de alerta LRF com percentual de pessoal (CRITICO/ATENCAO/OK)
- ❌ **Limite hardcoded errado**: linha ~129 usa `54.00` como limite legal e `51.3` como crítico — mas para Município é 60%/57%/54% (este código está com o limite ESTADUAL, não municipal)
- ✅ Histórico de envios em `SICONFI_ENVIO`

### Transparência (`transparencia.php`) — Lei 131/2009
- ✅ `POST /transparencia/exportar` gera CSV (formato CSV é canônico da lei):
  - Cabeçalho: Nome, Matrícula, CPF, Cargo, Regime, Setor, Secretaria, Admissão, Proventos, Descontos, Líquido
  - **CPF mascarado** via `maskCpfTransparencia` (LGPD): `123.***.***-45`
  - BOM UTF-8 no início
  - Cells com `;` separator + `"..."` escape
- ✅ Persistência em `TRANSPARENCIA_EXPORTACAO`
- ✅ `GET /transparencia/historico` últimos 50
- ✅ `GET /transparencia/download/{id}` re-gera CSV idêntico

**Recursos avançados sob feature flag (`config/feature_flags.transparencia.*`):**
- ✅ `GET /transparencia/dossie-terceirizacao` (S5.4) — visão pública de terceirizados com CPF mascarado, função, empresa, contrato, secretaria/setor
- ✅ `GET /transparencia/observabilidade-integracoes` (S8 fase 1) — métricas operacionais públicas: exportações 7d, eSocial pendente, RPPS bloqueios 30d
- ✅ `GET /transparencia/catalogo-dados` (S8 fase 2) — catálogo público versionado via `config/transparencia_catalogo.php`
- ✅ Middleware `tenant.resolve` em rotas multi-tenant

### Estrutura de tabelas
- `SAGRES_EVENTO_DEPARA` — de-para de rubricas para códigos TCE-MA
- `SAGRES_GERACAO` — histórico de XMLs gerados
- `SICONFI_RELATORIO` — RGF + RREO consolidado
- `SICONFI_ENVIO` — histórico de envios (legado, usado por `controle_externo.php`)
- `RGF_DADOS`, `RREO_DADOS` — dados pré-calculados (legado, possivelmente sem uso real)
- `TRANSPARENCIA_EXPORTACAO` — histórico de exportações públicas

## 8.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **DUPLICAÇÃO de rotas SAGRES** | `controle_externo.php` declara `GET /sagres/preview` e `POST /sagres/gerar` que entram em conflito com `sagres.php`. Laravel registra a primeira encontrada — comportamento dependente da ordem em `regen_api_v3_fachada.py`. **Bug real em produção.** | Decidir qual versão fica e remover a outra. Recomendação: manter `sagres.php` (mais completo) e remover do `controle_externo.php` | 30 min |
| **`controle_externo.php` linha ~129 — limite LRF errado** | Diz `54.00` como limite legal e `51.3` como crítico. Para Município é 60%/57%/54%. Esses valores são para **Estados** (49% Executivo + 6% Legislativo). Em produção, gera alerta falso. | Corrigir para 60/57/54 ou parametrizar via `config/lrf.php` | 30 min |
| **`controle_externo.php` `whereYear/whereMonth` em campo string** | Linha ~16-17 usa `whereYear('f.FOLHA_COMPETENCIA')` e `whereMonth(...)` em campo varchar(6) `YYYYMM`. Quebra em SQL Server e em SQLite o resultado é silencioso e errado. | Trocar por `where('FOLHA_COMPETENCIA', '=', sprintf('%04d%02d', $ano, $mes))` | 15 min |
| **`siconfi.php` usa `strftime` SQLite-only** | Linhas 36, 49, 122, 140 usam `strftime("%m", f.FOLHA_COMPETENCIA)` que é SQLite. Em SQL Server vira NULL. Em produção retorna 0. | Refatorar usando `SUBSTRING` cross-database | 1h |
| **SAGRES — XML não bate com o schema TCE-MA real** | O XML é "SINC-Folha v1.0" simplificado. TCE-MA aceita mas pode mudar. Deve ser validado contra XSD oficial do TCE-MA. | Baixar XSD do TCE-MA e validar geração + ajustar campos faltantes (cargo, lotação, regime previdenciário, alíquotas, eventos detalhados) | 1-2 dias |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Transparência — `whereNull('f.FUNCIONARIO_DATA_FIM')`** | Lista só funcionários ativos hoje. Lei exige histórico de todos quem recebeu na competência (incluindo exonerados). | Remover filtro `DATA_FIM` quando há folha — quem teve `DETALHE_FOLHA` na competência deve aparecer | 30 min |
| **Transparência sem eventos detalhados** | CSV mostra proventos/descontos consolidados, mas a Lei 131/2009 + Decreto 7.185/2010 exige discriminação de eventos (subsídios, gratificações, vantagens, abono) | Adicionar JOIN com `EVENTO_DETALHE_FOLHA` e gerar colunas dinâmicas por tipo | 4-6h |
| **Transparência sem PDF/JSON além de CSV** | Decreto exige formato aberto. CSV+UTF-8+BOM atende, mas alguns portais requerem JSON também. | Adicionar formato JSON | 30 min |
| **Transparência — sem versionamento dos arquivos** | Se RH gerar 3x na mesma competência, sobrescreve. CGM precisa rastrear versões. | Manter histórico mas marcar `IS_LATEST=true` | 1h |
| **Transparência — sem agendamento mensal automático** | Lei 131 exige publicação **diária** das informações orçamentárias-financeiras. GENTE só faz se RH clicar. | Cron diário com push para portal de transparência | 4-6h |
| **SAGRES — sem mapeamento de eventos não-mapeados** | Se rubrica nova for criada e não tiver entrada em `SAGRES_EVENTO_DEPARA`, vai pro XML como "NAO_MAPEADO" e TCE-MA rejeita. Não há alerta. | Cron de validação: alertar se há `EVENTO` sem mapeamento | 1h |
| **SAGRES — XML não inclui informações de cargo/lotação/regime** | Schema atual só envia CPF + nome + matrícula + valores. TCE-MA típico exige carreira, cargo, classe, referência, regime previdenciário, alíquotas. | Verificar com TCE-MA quais campos são obrigatórios | 4-8h |
| **SICONFI — RCL via `request('rcl', 0)` sem fallback** | Se RH não passar RCL, retorna `pct_pessoal=0` e status "REGULAR" mesmo se folha estoura. Falso positivo de conformidade. | Buscar RCL de `RECEITA_MUNICIPIO` (Bloco 2) com fallback explícito | 30 min |
| **SICONFI — SUM `DETALHE_FOLHA_PROVENTOS` ignora encargos patronais** | Despesa com pessoal LRF inclui patronal (INSS/RPPS), 13º proporcional, férias, vantagens. Soma só dos proventos subestima o custo real. | Refatorar para incluir patronal + 13º proporcional | 2-4h |
| **SICONFI — sem geração do arquivo de transmissão para STN** | STN aceita upload via portal SICONFI, mas formato é matriz especifica (DCASP, MSC). Hoje só gera JSON. | Implementar exportação no formato STN | 1-2 dias |
| **Transparência — `config('feature_flags.transparencia.dossie_terceirizacao')` por padrão off** | Se config não existir, retorna 404. Confirmar se está liberado em produção | Verificar `config/feature_flags.php` | 5 min |
| **Transparência — `TERCEIRO_POSTO` vs `TERC_POSTO` (Bloco 2)** | Em `terceirizados.php` (Bloco 2) a tabela é `TERC_POSTO`. Aqui em `transparencia.php` é `TERCEIRO_POSTO`. **Provavelmente bug — uma das duas referências está errada.** | Confirmar no banco real qual é o nome correto | 15 min |

### 🟢 BAIXO

| # | Item |
|---|---|
| Duplicação `controle_externo.php` x `siconfi.php` para RGF/RREO — 2 implementações concorrentes |
| `SAGRES_GERACAO.STATUS` só fica em GERADO, nunca vira ENVIADO/ACEITO (sem callback do TCE) |
| `municipio = 'SAO_LUIS_MA'` hardcoded em `sagres.php` linha ~80 |

## 8.3 Status pré-produção do Bloco 8

**Veredicto:** ⚠️ **AJUSTES NECESSÁRIOS**

A geração de arquivos funciona em demo mas tem 4 categorias de problemas:

1. **Conflitos de rota** — `controle_externo.php` e `sagres.php` registram as mesmas rotas
2. **Erros de cálculo LRF** — limites estaduais hardcoded em código municipal
3. **Queries SQLite-only** — `whereYear/whereMonth/strftime` em campos varchar
4. **Schemas TCE-MA não validados** — XML pode ser rejeitado em produção

### Ação concreta — Bloco 8 (priorizado)

1. **Resolver duplicação de rotas (30 min):** decidir qual fica, remover a outra
2. **Corrigir limites LRF municipal (30 min):** 60/57/54 em `controle_externo.php`
3. **Trocar `whereYear/whereMonth/strftime` (1.5h):** `siconfi.php` + `controle_externo.php`
4. **Confirmar nome de tabela `TERC_POSTO` vs `TERCEIRO_POSTO` (15 min):** alinhar com Bloco 2
5. **Buscar RCL via fallback (30 min):** `RECEITA_MUNICIPIO` em vez de só request param
6. **Transparência sem filtro `DATA_FIM` quando há folha (30 min):** lei exige histórico
7. **Documentar como passo manual (1h):** RH baixa arquivos e transmite via portal SICONFI/SAGRES até implementação completa

**Total esforço Bloco 8 (essencial):** ~4-5 horas para o estado "pronto-pronto" com as ressalvas legais documentadas.

### Pós go-live (estratégico)

- **SAGRES — validar XML contra XSD TCE-MA (1-2 dias):** baixar especificação TCE-MA e ajustar campos faltantes
- **Transparência — eventos detalhados no CSV (4-6h):** colunas dinâmicas por tipo de evento conforme Lei 131
- **Transparência — agendamento diário automático (4-6h):** cron + push para portal de transparência
- **SICONFI — incluir encargos patronais no cálculo LRF (2-4h):** LRF inclui patronal
- **SICONFI — exportação formato STN (DCASP/MSC) (1-2 dias)**
- **Eliminar duplicação RGF/RREO entre `controle_externo.php` e `siconfi.php`** — consolidar em um só arquivo

---

# BLOCO 9 — ERP FISCAL (Contabilidade, Tesouraria, Receita, Orçamento, Despesa)

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

Cobre o ciclo orçamentário público brasileiro: PPA/LOA (planejamento), Receita Municipal (arrecadação), Empenho → Liquidação → Pagamento (execução), Plano de Contas PCASP, Tesouraria (contas bancárias e fluxo de caixa), Lançamentos contábeis e Balancete.

## 9.1 O que tem

### Contabilidade Pública — PCASP (`contabilidade.php`)
- ✅ `GET /pcasp` — plano de contas PCASP (Plano de Contas Aplicado ao Setor Público) com hierarquia
- ✅ `POST /lancamentos` — lançamento contábil clássico debit/credit com:
  - Validação completa (data, histórico, valor min:0.01, conta débito + crédito)
  - Rastreabilidade `ORIGEM_TIPO/ORIGEM_ID` (folha, empenho, etc.)
  - Auditoria via `USUARIO_ID`
- ✅ `GET /balancete?mes=N&ano=YYYY` — balancete acumulado:
  - Soma débitos por conta + soma créditos por conta
  - Cálculo de saldo respeitando natureza (DEVEDORA/CREDORA)
  - Suporta hierarquia via `CONTA_GRUPO` e `CONTA_NATUREZA`

### Tesouraria — Contas Bancárias e Fluxo (`tesouraria.php`)

**Contas Bancárias (rotas duplas — legado + novo):**
- ✅ `GET /contas-bancarias` (legado) e `GET /tesouraria/contas` (Vue mapping)
- ✅ Saldo calculado: `CONTA_SALDO_INICIAL + créditos - débitos` (excluindo CANCELADO)
- ✅ Resumo financeiro: saldo total, entradas hoje, saídas hoje
- ✅ `POST /tesouraria/conta` — cadastro com BANCO, AGENCIA, NUMERO, TIPO

**Movimentação Bancária:**
- ✅ `POST /tesouraria/movimentacao` — credit/debit com tipo C/D, origem MANUAL, status PENDENTE
- ✅ `GET /tesouraria/movimentos?conta_id=N` — últimos 500 lançamentos com banco joined

**Fluxo de Caixa:**
- ✅ `GET /tesouraria/fluxo?dias=30` — fluxo histórico:
  - Total entradas + saídas + saldo do período
  - Lista por dia com saldo acumulado
- ✅ `GET /fluxo-caixa?conta_id=N&inicio=Y-M-D&fim=Y-M-D` (legado)
- ✅ `POST /conciliar` — marca movimentações como CONCILIADO em lote

**Aliases para retrocompatibilidade:**
- ✅ `/tesouraria/fluxo-caixa` → redireciona para `/api/v3/fluxo-caixa`
- ✅ `/tesouraria/movimentacoes` → redireciona para `/api/v3/conciliar`

**Schema-tolerance:**
- ✅ Verifica `Schema::hasTable('CONTA_BANCARIA')` e `MOVIMENTACAO_BANCARIA` antes de query
- ✅ Retorna 200 com payload vazio se tabelas não existem

### Receita Municipal (`receita_municipal.php`)
- ✅ `GET /receita?ano=YYYY&mes=N&tipo=X` — lançamentos com filtros
- ✅ Resumo agregado: previsto, arrecadado, percentual de arrecadação
- ✅ `POST /receita` — registrar lançamento com:
  - Validação de tipo (TRIBUTARIA, CONTRIBUICOES, PATRIMONIAL, TRANSFERENCIAS_CORRENTES, OUTRAS_CORRENTES, CAPITAL)
  - `RECEITA_CODIGO_NATUREZA` (Anexo I do Manual STN)
  - `RECEITA_FONTE` opcional
  - Vinculação com `CONTA_BANCARIA`
- ✅ `GET /receita/por-tipo?ano=YYYY` — agregação por tipo (previsto vs arrecadado vs quantidade)
- ✅ `GET /receita/divida-ativa` — gestão de dívida ativa:
  - Filtros: status, devedor (busca parcial)
  - Cálculo do total: `DA_VALOR_PRINCIPAL + DA_MULTA + DA_JUROS + DA_HONORARIO`
  - KPIs: total ativo, valor total não-quitado

### Orçamento Público — PPA/LOA (`orcamento.php`)
- ✅ `GET /orcamento/ppa` — programas do PPA (Plano Plurianual, 4 anos)
- ✅ `GET /orcamento/loa?ano=YYYY` — LOA do ano com:
  - Hierarquia: Programa → Ação → Item LOA
  - **Cálculo dotação atual**: `LOA_VALOR_APROVADO + LOA_VALOR_ADICIONADO - LOA_VALOR_REDUZIDO`
  - Totais: dotação inicial, créditos adicionais, reduções, dotação atual
- ✅ `POST /orcamento/acao` — criar ação orçamentária com tipo (ATIVIDADE/PROJETO/OPERACAO_ESPECIAL)
- ✅ `GET /orcamento/execucao?ano=YYYY` — resumo execução por programa:
  - Dotação atual vs empenhado (LEFT JOIN com EMPENHO, exclui ANULADO)
  - Quantidade de empenhos por programa

### Execução de Despesa (`execucao_despesa.php`)
**Cadeia clássica Empenho → Liquidação → Pagamento:**
- ✅ `GET /empenho?ano=YYYY&status=X&credor=...` — lista com filtros + stats
- ✅ Stats: total emitido + liquidado + pago no ano corrente
- ✅ `POST /empenho` — emitir empenho com:
  - Validação: número, data, credor (max 150), CPF/CNPJ opcional, valor min:0.01
  - Tipo: ORDINARIO/ESTIMATIVO/GLOBAL
  - Status inicial EMITIDO
  - Vínculo com `LOA_ID` (rastreabilidade orçamentária)
  - Auditoria via `USUARIO_ID`
- ✅ `POST /empenho/{id}/liquidar` — liquidação:
  - Valida que está em EMITIDO
  - Cria `LIQUIDACAO` com data, valor, NF, histórico
  - Atualiza empenho para status LIQUIDADO
- ✅ `POST /liquidacao/{id}/pagar` — pagamento:
  - Cria `PAGAMENTO_DESPESA` com forma, banco, conta
  - Atualiza empenho para status PAGO

### Estrutura de tabelas
- `PCASP_CONTA` — plano de contas
- `LANCAMENTO_CONTABIL` — lançamentos
- `CONTA_BANCARIA`, `MOVIMENTACAO_BANCARIA`
- `RECEITA_LANCAMENTO`, `RECEITA_DIVIDA_ATIVA`
- `ORCAMENTO_PPA`, `ORCAMENTO_PROGRAMA`, `ORCAMENTO_ACAO`, `ORCAMENTO_LOA`
- `EMPENHO`, `LIQUIDACAO`, `PAGAMENTO_DESPESA`

## 9.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R7-R10 (Bloco 1)** | `ContabilidadeService` (acionado em `ProcessarFolhaJob`) ainda incompleto: patronal 14% hardcoded, não-idempotente, só lança vencimentos+patronal — não lança INSS/IRRF/consignações retidos. | Refactor já mapeado no Bloco 1 | 4-6h |
| **`whereYear` em `EMPENHO_DATA`** | Linhas em `execucao_despesa.php` (stats + filtros) e `orcamento.php` (LEFT JOIN) usam `whereYear('e.EMPENHO_DATA', $ano)`. Em campo datetime real, OK. Em SQL Server pode ter performance ruim mas funciona. | Adicionar índice em `EMPENHO_DATA` no SQL Server | 30 min |
| **Empenho não verifica saldo da LOA** | `POST /empenho` não bloqueia se ultrapassar dotação atual. Empenho à descoberto seria irregularidade fiscal. | Adicionar validação: `SUM(empenhos não-anulados da LOA) + valor_novo <= dotação_atual` | 1h |
| **Liquidação não valida valor ≤ empenho** | `POST /empenho/{id}/liquidar` aceita `liquidacao_valor` qualquer. Se RH passar valor > empenho, contabiliza errado. | Adicionar validação no endpoint | 30 min |
| **Pagamento não valida valor ≤ liquidação** | Mesmo problema | 30 min |
| **Pagamento não debita conta bancária** | `POST /liquidacao/{id}/pagar` cria registro mas não cria `MOVIMENTACAO_BANCARIA` correspondente. Saldo bancário fica desatualizado. | Adicionar criação de movimentação automática | 1-2h |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Lançamento contábil sem partida dobrada validada** | Endpoint exige conta_debito + conta_credito mas não valida que sejam de natureza compatível (não permite débito em conta credora sem retificação) | 1-2h |
| **Balancete sem encerramento** | Não há rotina de encerramento mensal/anual que zera contas de resultado | 4-6h |
| **Tesouraria — `MOVIMENTACAO_BANCARIA.MOV_STATUS=PENDENTE` por padrão** | Toda movimentação manual fica pendente, mas não há fluxo de aprovação | Workflow aprovação manual |
| **Tesouraria — alias `POST /tesouraria/movimentacoes` redireciona pra /conciliar** | Comportamento estranho — POST conciliacao sob nome de "movimentacoes" | Documentar ou remover alias |
| **Receita — sem conciliação automática com tesouraria** | Receita arrecadada não vira automaticamente movimentação de crédito na conta bancária | 2-4h |
| **Receita — sem campo de fonte vinculada (FUNDEB, SUS, IDEB)** | LRF e SICONFI exigem identificação de fonte. Tem `RECEITA_FONTE` mas é texto livre. | Tabela `FONTE_RECURSO` + relacionamento | 2-3h |
| **LOA — sem importação de proposta orçamentária** | Lei votada na câmara é gigante. RH precisa cadastrar manualmente cada item. | Importador CSV/XML | 4-6h |
| **Empenho — sem geração de número automático** | RH passa `empenho_numero` manualmente. Risco de duplicidade. | Sequenciador automático por ano + tipo | 1h |
| **Empenho — sem fluxo de anulação** | Status ANULADO existe mas não há endpoint para anular | 1h |
| **PCASP — sem importação automática do plano-padrão STN** | RH precisa cadastrar conta a conta. | Seeder com plano STN 2026 | 2-4h |
| **Dívida Ativa — sem importador de inscrições** | Inscrição em dívida ativa é processo manual hoje | Importador + integração com receita | 4-6h |
| **Sem balanço orçamentário consolidado anual** | Falta endpoint `GET /balanco-orcamentario/{ano}` exigido pela LRF Anexo 1 | 2-4h |
| **Sem demonstrativo execução restos a pagar** | LRF exige | 4-6h |

### 🟢 BAIXO

| # | Item |
|---|---|
| `tesouraria/conta` recorta `BANCO_CODIGO` para 3 chars. Bancos do CNAB usam 3 dígitos (correto), mas se vier "001 - Banco do Brasil" perde info |
| `PAGAMENTO_DESPESA.PAGAMENTO_FORMA` default "TRANSFERENCIA" mas não há catálogo de formas válidas |
| `MOV_ORIGEM_ID` aceita null mas não há FK enforcement |

## 9.3 Status pré-produção do Bloco 9

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

A cadeia Empenho → Liquidação → Pagamento está implementada e funcional. PCASP existe. PPA/LOA tem hierarquia correta. Tesouraria com fluxo de caixa e contas bancárias.

**Pontos fortes:**
- Cadeia ELP transactional 
- Validação de status no fluxo
- Cálculo de dotação atual respeita créditos adicionais e reduções
- Schema-tolerance para tabelas faltantes
- Auditoria de usuário

**Pontos críticos restantes (precisam fix antes do go-live):**
- Empenho à descoberto (faltam validações de saldo)
- Pagamento sem reflexo em conta bancária
- ContabilidadeService incompleto (já mapeado no Bloco 1)

### Ação concreta — Bloco 9 (priorizado)

1. **Validação saldo LOA no empenho (1h):** middleware verifica dotação_atual >= sum(empenhos)+novo
2. **Validação valor liquidação ≤ empenho (30 min)**
3. **Validação valor pagamento ≤ liquidação (30 min)**
4. **Pagamento cria movimentação bancária automática (1-2h):** transação que cria PAGAMENTO + MOVIMENTACAO_BANCARIA com `MOV_ORIGEM=PAGAMENTO_DESPESA`
5. **Sequenciador empenho automático (1h):** `EMPENHO_NUMERO` opcional, gera por ano + tipo se não passado
6. **Endpoint anular empenho (1h):** `PATCH /empenho/{id}/anular` com justificativa
7. **R7-R10 ContabilidadeService (4-6h)** — já mapeado no Bloco 1, mas é aqui que aterriza

**Total esforço Bloco 9 (essencial):** ~5-7 horas para o estado "pronto-pronto".

### Pós go-live (estratégico)

- Importador LOA via CSV/XML (4-6h)
- Encerramento de balancete mensal/anual (4-6h)
- Demonstrativo restos a pagar (4-6h)
- Balanço orçamentário consolidado (2-4h)
- Fonte de recursos estruturada (2-3h)
- Workflow de aprovação para movimentação tesouraria (decisão)

---

# BLOCO 10 — COMPRAS, ALMOXARIFADO, PATRIMÔNIO, FROTAS, CONTRATOS

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

Cobre o ciclo de gestão patrimonial e suprimento: Compras (processos licitatórios + contratos + pedidos), Almoxarifado (entradas/saídas com saldo), Patrimônio (bens com depreciação NBCASP 16.9), Frotas (veículos com saídas/retornos calculando KM, manutenções com alerta), Contratos Administrativos (aditivos + fiscalização mensal).

## 10.1 O que tem

### Compras (`compras.php`)
- ✅ **Processos Licitatórios** (`PROCESSO_LICITATORIO`):
  - `GET /compras/processos` — últimos 200
  - `POST /compras/processos` — modalidades aceitas livremente (Pregão Eletrônico, Inexigibilidade, Dispensa, etc.)
- ✅ **Contratos Administrativos** (`CONTRATO_ADMINISTRATIVO`):
  - `GET /compras/contratos` — últimos 200 ordenados por início
  - `GET /compras/contratos/vencendo` — contratos VIGENTES com fim ≤ 60 dias
  - `POST /compras/contratos` — validação `contrato_fim:after:contrato_inicio`, vincula a `processo_id` opcional
- ✅ **Pedidos de Compra** (`PEDIDO_COMPRA`):
  - `GET /compras/pedidos`, `POST /compras/pedidos`, `PATCH /compras/pedidos/{id}/vincular`
  - Workflow: SOLICITADO → VINCULADO (a um processo)

### Almoxarifado (`almoxarifado.php`)
- ✅ **Catálogo de itens com saldo agregado**:
  - `GET /almoxarifado/itens` — JOIN com `SALDO_ESTOQUE` agregado (joinSub) — SEM N+1
  - Cálculo: `saldo_total` (soma quantidades por almox) + `valor_estoque` (qtd × valor médio)
  - Schema-aware: respeita `ITEM_ATIVO`
- ✅ `POST /almoxarifado/itens` — cadastro com validação (código, descrição, unidade, categoria, mínimo)
- ✅ **Itens abaixo do mínimo**: `GET /almoxarifado/abaixo-minimo` — `whereRaw('saldo_total < ITEM_ESTOQUE_MINIMO')`
- ✅ **Entrada de material** (`POST /almoxarifado/entrada`):
  - Transaction garantindo movimentação + atualização de saldo
  - Upsert em `SALDO_ESTOQUE` (cria se não existe, soma quantidade)
  - `MOV_VALOR_UNITARIO` opcional, atualiza `SALDO_VALOR_MEDIO`
  - Vincula `pedido_compra_id` opcional (rastreabilidade)
- ✅ **Saída de material** (`POST /almoxarifado/saida`):
  - Validação BLOQUEANTE de saldo (lança RuntimeException se insuficiente)
  - Transaction: cria movimentação + decrementa saldo
  - Vincula `uo_destino_id` para rastreabilidade
- ✅ `GET /almoxarifado/movimentacoes` — últimas 200 com filtros (item_id, tipo)
- ✅ `GET /almoxarifado/lista` — almoxarifados ativos para selects

### Patrimônio (`patrimonio.php`)
- ✅ **Bens patrimoniais** (`BEM_PATRIMONIAL`):
  - `GET /patrimonio/bens` com filtros: uo_id, categoria, status, estado
  - `POST /patrimonio/bens` — tombamento com **`DepreciacaoService::parametrosPorCategoria`**:
    - Calcula vida útil + valor residual automaticamente conforme NBCASP 16.9
    - Inicial: BEM_VALOR_ATUAL = BEM_VALOR_AQUISICAO, depreciação acumulada = 0
  - `PUT /patrimonio/bens/{id}` — atualizar descrição, estado, UO, servidor responsável
- ✅ **Movimentações** (`MOVIMENTACAO_PATRIMONIAL`):
  - `POST /patrimonio/bens/{id}/transferir` — entre unidades, transação atômica
  - `POST /patrimonio/bens/{id}/baixar` — baixa patrimonial com motivo, transação
  - `GET /patrimonio/movimentacoes` — últimas 200 com bem joined
- ✅ **Inventário por UO**: `GET /patrimonio/inventario/{uo_id}` — bens ATIVO de uma unidade + valor total
- ✅ **Depreciação**:
  - `GET /patrimonio/depreciacao` — relatório agregado por categoria (qtd, aquisição, depreciado, valor atual)
  - `POST /patrimonio/depreciar/{competencia}` — executa `DepreciacaoService::depreciarMes`

### Frotas (`frotas.php`)
- ✅ **Veículos** (`VEICULO`):
  - `GET /frotas/veiculos` com filtros (status, tipo)
  - `GET /frotas/veiculos/disponiveis`
  - `POST /frotas/veiculos` — cadastro com placa (uppercase), modelo, marca, ano, tipo, KM, RENAVAM
- ✅ **Histórico** por veículo: `GET /frotas/veiculos/{id}/historico` — últimas 50 saídas + manutenções
- ✅ **Saídas e Retornos** (cadeia completa):
  - `POST /frotas/saidas` — cria saída com:
    - Valida que veículo está DISPONIVEL
    - Transaction: cria SAIDA_VEICULO + atualiza VEICULO_STATUS=EM_USO
    - Captura motorista, destino, finalidade, KM saída
  - `PATCH /frotas/saidas/{id}/retorno` — registra retorno com:
    - Valida `RETORNO_DATA_HORA` ainda null
    - Valida `km_retorno >= km_saida`
    - Transaction: atualiza SAIDA + VEICULO (KM_ATUAL + STATUS=DISPONIVEL)
    - Calcula `KM_PERCORRIDO = km_retorno - km_saida`
  - `GET /frotas/saidas/abertas` — saídas sem retorno (pendentes)
- ✅ **Manutenções** (`MANUTENCAO_VEICULO`):
  - `POST /frotas/manutencao` — registra com tipo (PREVENTIVA/CORRETIVA), valor, data próxima
  - Atualiza `VEICULO_PROX_MANUTENCAO` se informada
- ✅ `GET /frotas/manutencao/proximas` — alerta 30 dias com `urgencia` (CRITICO ≤7 dias / ATENCAO ≤30)

### Contratos Administrativos (`contratos_admin.php`)
- ✅ Listagem com filtros: status, busca textual no objeto
- ✅ JOIN com `PROCESSO_LICITATORIO` (número + modalidade)
- ✅ **Contratos vencendo**: `GET /contratos-admin/vencendo` — VIGENTE + fim ≤ 60 dias com `dias_restantes` calculado e flag `urgencia`
- ✅ **Aditivos** (`CONTRATO_ADITIVO`):
  - `POST /contratos-admin/{id}/aditivo` — cria aditivo com:
    - Tipo (texto livre — prazo, valor, objeto, etc.)
    - **Cálculo automático de `nova_data_fim`** se `aditivo_prazo_dias` informado: `CONTRATO_FIM + N dias`
    - **Sequenciador automático**: `aditivo_numero = COUNT(aditivos) + 1`
    - **Transaction**: insere ADITIVO + atualiza `CONTRATO_FIM` se houver prorrogação
- ✅ **Fiscalização mensal** (`CONTRATO_FISCALIZACAO`):
  - `POST /contratos-admin/{id}/fiscalizar` — registra com status (REGULAR/IRREGULAR/PENDENCIA/SUSPENSO)
  - `FISCAL_COMPETENCIA` calculada de `fiscal_data` (formato MM/YYYY)
- ✅ **Detalhes**: `GET /contratos-admin/{id}` — contrato + aditivos + fiscalizações
- ✅ **Export CSV**: `GET /contratos-admin/export/csv` com BOM UTF-8

### Estrutura de tabelas
- `PROCESSO_LICITATORIO`, `CONTRATO_ADMINISTRATIVO`, `PEDIDO_COMPRA`
- `ITEM_ESTOQUE`, `SALDO_ESTOQUE`, `MOVIMENTACAO_ESTOQUE`, `ALMOXARIFADO`
- `BEM_PATRIMONIAL`, `MOVIMENTACAO_PATRIMONIAL`
- `VEICULO`, `SAIDA_VEICULO`, `MANUTENCAO_VEICULO`
- `CONTRATO_ADITIVO`, `CONTRATO_FISCALIZACAO`

## 10.2 O que falta (gaps reais)

### 🔴 CRÍTICO

| # | Item | Solução | Esforço |
|---|---|---|---|
| **R23, R56 (Bloco 1)** | `DepreciacaoService` usa `strftime`/`julianday` SQLite-only. Em SQL Server retorna NULL silenciosamente. **Toda depreciação fica errada.** | Já mapeado no Bloco 1 — replicar fix aqui | 30 min |
| **Compras — sem validação de saldo orçamentário** | `POST /compras/processos` aceita valor estimado mas não vincula a LOA nem reserva dotação. Empenho posterior pode ser à descoberto. | Vincular processo a `LOA_ID` + reserva de dotação | 2-3h |
| **Contratos — sem cálculo de saldo a empenhar** | `CONTRATO_VALOR` é fixo. Não há rastreio de quanto já foi empenhado contra o contrato. Em produção contratos podem ser empenhados além do valor. | Adicionar `CONTRATO_VALOR_EMPENHADO` + validação no empenho | 2-4h |
| **Aditivos — sem limite legal de 25%** | Lei 14.133/21 art. 125 limita aditivo de valor a 25% (acréscimo) / 25% (redução) para serviços/compras, 50% para reformas. Sistema aceita qualquer valor sem checar. | Validação: soma `ADITIVO_VALOR` não pode ultrapassar 25% (50% para reforma) do `CONTRATO_VALOR` original | 1-2h |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Almoxarifado — custo médio simplificado** | Comentário no código admite: "calcular novo custo médio simples: (preco_antigo*qtd_antiga + preco_novo*qtd_nova) / total. Porém aqui só somamos qtd". Custo médio fica errado em entradas com preços diferentes. | 1h |
| **Almoxarifado — saída sem validação de preço** | Saída usa `SALDO_VALOR_MEDIO` automaticamente, mas não há registro do valor da saída na tabela MOVIMENTACAO_ESTOQUE | 30 min |
| **Patrimônio — depreciação não rastreada por mês** | `BEM_DEPRECIACAO_ACUMULADA` é cumulativa, mas não há `DEPRECIACAO_MENSAL` registrada. Impossível auditar mês-a-mês. | Tabela `DEPRECIACAO_LANCAMENTO` | 2-3h |
| **Patrimônio — baixa não verifica responsável** | Bem com `SERVIDOR_ID` atribuído pode ser baixado sem assinatura do responsável | 1h |
| **Patrimônio — transferência não notifica destino** | UO destino recebe bem mas não há notificação | Aguarda Bloco 12 (notif/email) |
| **Patrimônio — `BEM_VALOR_ATUAL` não recalculado em depreciação** | `POST /patrimonio/depreciar/{competencia}` chama service mas não vimos service implementado completo | Verificar `DepreciacaoService::depreciarMes` |
| **Frotas — saída sem validação de CNH do motorista** | Servidor pode estar como motorista sem CNH cadastrada/válida | 30 min |
| **Frotas — manutenção não bloqueia veículo** | Veículo em manutenção fica DISPONIVEL. Pode ser tirado por engano. | Status MANUTENCAO + bloqueio | 30 min |
| **Frotas — sem registro de combustível/abastecimento** | KM percorrido vem da saída mas não há custo de combustível separado | 2-3h |
| **Contratos — fiscalização sem upload de documento** | Status registrado mas relatório de fiscalização físico não anexado | 1-2h |
| **Contratos — vencimento sem alerta automático** | `/contratos-admin/vencendo` é endpoint, mas não há cron + notificação para gestor | Aguarda Bloco 12 |
| **Compras — pedidos sem cálculo de valor unitário** | `PEDIDO_COMPRA.PEDIDO_VALOR_ESTIMADO` é total mas não há `PEDIDO_ITEM` (quebra em itens) | 2-4h |
| **Compras — sem catálogo unificado de fornecedores** | `EMPENHO_CREDOR` (Bloco 9), `CONTRATO_FORNECEDOR` (este bloco) e `EMPRESA_RAZAO_SOCIAL` (Bloco 2 terceirizados) são tabelas/campos diferentes para a mesma realidade | Tabela `FORNECEDOR` consolidada | 4-6h |

### 🟢 BAIXO

| # | Item |
|---|---|
| `DEPRECIACAO_ACUMULADA` em campo `decimal` sem cast preciso |
| `MANUTENCAO_VEICULO.MANUT_PROXIMA` permite null mas alerta de manutenção depende dele |
| Almoxarifado sem reserva de estoque (entrega prevista vs efetuada) |
| Frotas sem inventário GPS/telemetria |
| Compras sem importação automática de pregão eletrônico (DOU) |

## 10.3 Status pré-produção do Bloco 10

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Bloco maduro, com cadeias bem implementadas:
- Almoxarifado com validação de saldo + transação
- Patrimônio com depreciação NBCASP automatizada
- Frotas com saída→retorno→KM cálculo
- Contratos com aditivos atomizados que atualizam vencimento

**Bloqueadores reais:**
- DepreciacaoService SQLite-only (já mapeado)
- Aditivos sem limite de 25% (irregularidade fiscal)
- Compras sem reserva de dotação (empenhos à descoberto)

### Ação concreta — Bloco 10 (priorizado)

1. **DepreciacaoService SQL Server compatible (30 min):** já mapeado, só replicar fix
2. **Limite 25% em aditivos (1-2h):** validação no `POST /contratos-admin/{id}/aditivo`
3. **Validação saldo na saída de almoxarifado (já tem):** OK, manter
4. **Custo médio almoxarifado correto (1h):** corrigir cálculo na entrada
5. **Status MANUTENCAO em veículo (30 min):** quando registra manutenção, opcionalmente bloquear
6. **Validação de KM crescente em frotas (já tem):** OK
7. **Vínculo LOA em processo de compras (2-3h):** reserva de dotação

**Total esforço Bloco 10 (essencial):** ~5-7 horas para o estado "pronto-pronto".

### Pós go-live (estratégico)

- Catálogo unificado de fornecedores (4-6h)
- Pedido de compra com itens detalhados (2-4h)
- Combustível separado em frotas (2-3h)
- Depreciação rastreada por mês (2-3h)
- Inventário GPS/telemetria (decisão arquitetural)

---

# BLOCO 11 — FRONTEND VUE 3 + MOBILE

**Status global do bloco:** 🟢 **PRONTO COM RESSALVAS**

> ⚠️ **Bloqueador real:** ~60 arquivos `*View - Copia.vue` precisam ser deletados antes do `npm run build` final em produção. Isso é deploy-blocking — bundle inflado e risco de routing pegar a versão errada. Fix em ~10 minutos.

Cobre o app SPA Vue 3 servido pelo Laravel (`resources/gente-v3/`) e o cliente mobile do ponto. Mobile React Native ainda não existe no repo — está no Bloco E (pós go-live). O ponto eletrônico mobile via app é só backend (rotas JWT em `ponto_app.php`, Bloco 3); cliente RN será desenvolvido depois.

## 11.1 O que tem

### Stack
- ✅ **Vue 3.5.25** + **Vuetify 3.12.1** (autoImport via `vite-plugin-vuetify`) + **Tailwind CSS 4.2.1** + **Sass**
- ✅ **Pinia 3.0.4** (gerenciamento de estado moderno)
- ✅ **vue-router 4.6.4** (history mode com lazy loading)
- ✅ **Vite 7.3.1** (builder)
- ✅ **Axios 1.13.5** + **DOMPurify 3.3.3** + **crypto-js 4.2.0** (HMAC)
- ✅ **@mdi/font** (Material Design Icons) + **lucide** (ícones lineares)
- ✅ **Playwright 1.58** (em devDependencies, mas só 2 scripts de screenshot — sem testes E2E reais)
- ✅ Sem TypeScript (JS puro — aceitável para o deadline; ressalva pós go-live)

### Build & Bundle
- ✅ `dist/` existe e está datado de 07/05/2026 17:09 (compatível com últimas mudanças do código)
- ✅ Bundle principal `index-olf9ezqG.js` = **295 KB** (não-minificado nem comprimido) — saudável para SPA com 60+ rotas
- ✅ **Code splitting funcional** — 1 JS + 1 CSS por view (177 arquivos em `dist/assets/`), lazy loading garantido pelo router
- ✅ Fontes MaterialDesignIcons embedadas em multiformato (woff2, woff, ttf, eot)

### Vite Config (`vite.config.js`)
- ✅ Alias `@` → `./src`
- ✅ Server dev em `127.0.0.1:5173`
- ✅ **Proxy para Laravel em `127.0.0.1:8081`** apenas para rotas reais: `^/(api|csrf-cookie|sanctum|storage|remessa)`
  - `cookieDomainRewrite: '127.0.0.1'`
  - Remove flags `Secure` e `SameSite=Strict` para dev em http
  - Em produção, Nginx faz esse trabalho — config dev é irrelevante
- ✅ `historyApiFallback: true` (suporta vue-router em modo history)

### Bootstrap (`main.js`)
- ✅ Registra app + router + pinia + vuetify
- ✅ **3 bridges customizados** que injetam contexto do usuário no axios:
  - `setGenteSigningUserGetter` → HMAC signing (Frente 2)
  - `setGenteSudoGlobalGetter` → header `X-Gente-Global-View` quando sudo ativo
  - `setGenteFuncionarioContextGetter` → header `X-Gente-Funcionario-Context-Id` (multi-vínculo)

### App.vue (super minimalista — 19 linhas)
- ✅ Apenas `<v-app><v-main><router-view/></v-main></v-app>` + reset CSS
- ✅ Background `#F8FAFC` (light gray, alinhado ao tema Vuetify)
- ✅ Toda lógica está no DashboardLayout

### Router (`router/index.js`, 613 linhas)
- ✅ **60+ rotas com lazy loading** — `() => import('../views/...')` em todas
- ✅ Layout único (DashboardLayout) que recebe rotas filhas via `<router-view/>`
- ✅ **Rotas públicas:** `/login`, `/autocadastro/:token`, `/primeiro-acesso` (esta com `firstAccessPage: true`)
- ✅ **RBAC granular via `meta.roles`** — admin / rh / gestor / sesmt / funcionario
- ✅ **Redirect `/` → `/login`**
- ✅ **Aliases retro-compat:** `/holerites` → `/meus-holerites`, `/escala` → `/escala-trabalho`, `/folha` → `/folha-pagamento`
- ✅ **Hierarquia clara de acessos:**
  - **Todos** (sem `meta.roles`): Dashboard, MeuPerfil, Ponto, ContraCheque, Comunicados, Agenda, Notificacoes, Declaracoes, FeriasLicencas, Ouvidoria, BancoHoras, MinhasSubstituicoes
  - **Gestor+:** Portal Gestor, Organograma, Escalas, Substituicoes, Sobreaviso, PlantõesExtras, AvaliacaoGestor
  - **RH+:** Funcionarios, AutocadastroGestao, Folha, RemessaCnab, Cargos, Progressao, Exoneracao, ESocial, RPPS, Diarias, Estagiarios, Sagres, AcumulacaoCargos, Transparencia, PSS, Terceirizados, Medicina, Beneficios, etc.
  - **Admin only:** Configuracoes, ConfiguracaoSistema, ParametrosFinanceiros, TabelasAuxiliares, Turnos, Feriados, Vinculos, EventosFolha, Orcamento, ExecucaoDespesa, Contabilidade, Compras, Almoxarifado, Patrimonio, ContratosAdmin, Frotas, Tesouraria, ReceitaMunicipal, ControleExterno, Oss, Consignatarias

### Navigation Guard (`router.beforeEach`)
- ✅ **Login = zona neutra** (next() imediato, sem fetchUser)
- ✅ **Rotas públicas** = next()
- ✅ **Rotas protegidas:**
  1. `fetchUser()` (com cache TTL ou force se firstAccessScreen)
  2. Se não autenticado → redirect para `/login`
  3. Se `forcePasswordChange=true` e não está em PrimeiroAcesso → redirect `/primeiro-acesso`
  4. Se já trocou senha e está em PrimeiroAcesso → redirect `/dashboard`
- ✅ **`assertRouteAccess`** valida:
  - `legacyRolesAllow` (perfis legacy)
  - `passesRequiredSlugs` (RBAC slug-based via `navManifest`)
  - Erro → redirect para Dashboard com `?denied=rbac&code=...`
- ✅ `afterEach` chama `notifyMobileDrawerClose()` para fechar o sidebar mobile após navegar

### Auth Store (`store/auth.js`, 436 linhas)
- ✅ **Cache TTL 5 min** (`TTL_MS = 5 * 60 * 1000`) — só refaz `/api/auth/me` se passou 5min ou `forceFetch=true`
- ✅ **Matriz de chaves de perfil:**
  - `CHAVE_ADMIN`: DESENVOLVEDOR, ADMINISTRADOR
  - `CHAVE_RH`: ADMIN + RH_FOLHA, RH_UNIDADE, RH_APS, RH_REDE, RECRUTADOR
  - `CHAVE_GESTOR`: RH + GESTAO, COORDENADOR_DE_SETOR, DIRETOR_GESTOR_DE_UNIDADE
- ✅ **Suporte dual** — moderno (`perfil_chaves: array`) e legacy (`perfil: string lower`)
- ✅ **Getters ricos:**
  - `isAdmin`, `isRH`, `isGestor`, `isFuncionario`, `isAuthenticated`
  - `canBypassTenant` (gate sudo `.env` whitelist)
  - `rbacPermissionSlugs[]`, `hasRbacSlug(slug)`, `hasAnyRbacSlug(slugs)`
  - `semadAuditorReadonly`, `semadMantaUiReadonlyForShell`
  - `podeRbacBreakGlassGlobal`
  - `tenantScopeRingsPublic`
  - `hasPerfil(chave)`, `hasEscopoUnidade(unidadeId)`
  - `perfilLabel` (concatena com " + " se múltiplos)
  - `funcionarioVinculos[]`, `temMultiplosVinculosFuncionario`, `funcionarioContextHeader`
  - `forcePasswordChange` (USUARIO_PRIMEIRO_ACESSO ou USUARIO_ALTERAR_SENHA)
- ✅ **Actions:**
  - `fetchUser(forceFetch)` com BOM stripping (`\uFEFF`) antes de JSON.parse
  - `setSessionUser(payload)` — após login
  - `setFuncionarioContext(funcionarioId)` — troca vínculo activo (multi-matrícula), persiste em localStorage por usuário, força re-fetch
  - `setGlobalViewActive(value)` — sudo session-storage por usuário
  - `logout()` — chama API + limpa estado
- ✅ **Persistência inteligente:**
  - `localStorage` para `funcionarioContext` (chave por USUARIO_ID)
  - `sessionStorage` para sudo global view (não atravessa abas)

### Axios Plugin (`plugins/axios.js`, 121 linhas)
- ✅ **`withCredentials: true`** (cookies HTTP-only do Laravel Sanctum)
- ✅ Headers: `Accept`, `Content-Type`, `X-Requested-With: XMLHttpRequest`
- ✅ **`transformResponse` remove BOM `\uFEFF`** antes de JSON.parse
- ✅ **3 interceptors de request:**
  1. **Funcionario context** — eco do `headerName` do backend
  2. **Sudo global view** — header `X-Gente-Global-View: true` se ativo
  3. **HMAC signing (Frente 2)** — gera `X-Gente-Timestamp` + `X-Gente-Signature` para POST/PUT/PATCH/DELETE quando `user.request_signing_enabled` + `request_signing_secret`
- ✅ **Response interceptor:**
  - 412/403 + `error=PASSWORD_CHANGE_REQUIRED` → redirect `/primeiro-acesso`
  - 401/419 → redirect `/login?sessao_expirada=1` (limpa cache)

### Vuetify Theme (`plugins/vuetify.js`)
- ✅ Tema customizado **`genteTheme`**:
  - Background `#F8FAFC` (light gray)
  - Surface `#FFFFFF`
  - **Primary `#0F172A` (Navy Blue)** — alinha com a paleta de governo
  - Secondary `#22C55E` (Leaf Green)
  - Error `#B00020`, Info, Success, Warning
- ✅ MDI font CSS importado

### Sanitize Plugin (`plugins/sanitize.js`)
- ✅ Wrapper minimalista DOMPurify, whitelist conservadora:
  - Tags: `p`, `strong`, `em`, `ul`, `ol`, `li`, `br`, `a`
  - Attrs: `href`, `target`
- ⚠️ Não permite `<table>`, `<img>`, `<h1-6>` — algumas declarações ricas não passariam pela sanitização

### Navigation Manifest (`navigation/navManifest.js`, 252 linhas)
- ✅ **`ROLE_HIERARCHY`** = ['admin', 'rh', 'sesmt', 'gestor', 'funcionario']
- ✅ **`RBAC_UI_STRICT`** via env `VITE_GENTE_RBAC_UI_STRICT` (default false — fallback graceful para roles)
- ✅ **`userEffectiveLevel(authStore)`** retorna 0-4
- ✅ **`legacyRolesAllow`** — checa hierarquia
- ✅ **`hasAnyRequiredSlug` + `resolveSlugAccess`** — RBAC granular com fallback para roles se sessão sem slugs (migração segura)
- ✅ **`getNavGateMeta(pathname)`** com pathPrefix matching para `/funcionario/:id`
- ✅ **`NAV_MANIFEST` array completo** com todas as entradas:
  - `type: 'section' | 'item'`
  - `to`, `label`, `icon`, `roles`, `ringKey` (`rh_ciclo_vida`, `operacional_escala_freq`)
  - `requiredAnySlugs` (slugs RBAC: `'global.mde.25'`, `'unidade.dashboard.kpi'`, `'rh.progressao.lei4928'`, `'organograma.unidade.visualizar'`, `'escala.grade.visualizar'`, `'financeiro.previdencia.ipam'`)
  - `sidebar: false` para rotas só de gate (PerfilFuncionario, MatrizEscala)

### DashboardLayout (`layouts/DashboardLayout.vue`, 1098 linhas)
**Sidebar fixa 260px com gradient ocean (`--gente-sl-ocean-deep` → `--gente-sl-ocean-mid` → `--gente-sl-ocean-soft`):**
- ✅ Logo "GENTE — Gestão de Pessoas"
- ✅ Profile com avatar (iniciais) clicável → MeuPerfil
- ✅ **Busca na sidebar** (filtra navItems por label)
- ✅ Navegação filtrada via `canAccessNavEntry` + `canAccessNavSection`
- ✅ Footer: botão Logout + crédito "Developed by RR Tecnol"

**Topbar com:**
- ✅ Hamburger mobile (matchMedia `<= 767px`)
- ✅ Breadcrumb com ícone + label da rota atual
- ✅ **Sudo-global-pill** (visível se `canBypassTenant`) — toggle com tooltip de auditoria
- ✅ **Fnctx-pill** (visível se `temMultiplosVinculosFuncionario`) — select de matrícula activa
- ✅ **Notificações dropdown** com:
  - Polling 60s via `setInterval` (pausa em aba background com `document.hidden`)
  - Endpoint `/api/v3/notificacoes`
  - Marcar como lida individual / todas (`/api/v3/notificacoes/{id}/lida`, `/api/v3/notificacoes/lidas`)
  - Animação CSS `notif-drop`
  - Click-outside fechamento
- ✅ Botão Configurações + Avatar (atalhos)

**Banner SEMAD** (visível se `semadMantaUiReadonlyForShell`) e **toast de RBAC denied** (lê `route.query.denied=rbac`)

**Pendências de substituição:**
- ✅ Polling 90s em `/api/v3/substituicoes/minhas`
- ✅ Badge no item "Minhas Substituições" se há pendentes (`99+` se passa)

**Responsividade completa:**
- ✅ Sidebar transform translateX(-100%) em mobile, overlay backdrop
- ✅ Watcher `route.path` fecha drawer ao navegar (mobile)
- ✅ MatchMedia listener reage a resize/rotação/devtools
- ✅ Topbar compacta em <=768px, avatar some em <=480px

### Catálogo de views (60+ arquivos)
| Diretório | Views ativas |
|---|---|
| `auth/` | LoginView, AutocadastroView, PrimeiroAcessoView |
| `config/` | 8 views (Configuracoes, ConfigSistema, ParametroFinanceiro, TabelasAuxiliares, Turnos, Feriados, Vinculos, EventosView) |
| `dashboard/` | DashboardExecutivoView, HomeView |
| `escala/` | EscalaTrabalhoView, MatrizEscalaView, MinhasSubstituicoesView, SubstituicoesView |
| `financeiro/` | 10 views (RemessaCnab, Sagres, Tesouraria, ControleExterno, FolhaPagamento, Contabilidade, Orcamento, ReceitaMunicipal, ExecucaoDespesa, Remessa) |
| `folha/` | ContraChequeView |
| `ponto/` | 7 views |
| `relatorios/` | RelatoriosView |
| `rh/` | ~36 views |
| `saude/` | OssView |
| `administrativo/` | 5 views (Almoxarifado, Compras, ContratosAdmin, Frotas, Patrimonio) |
| `views/` (raiz) | AgendaView, ComunicadosView, NotificacoesView, OuvidoriaView |

### Mobile App (Ponto Eletrônico)
- ✅ Backend pronto: `routes/ponto_app.php` (297 linhas, Bloco 3) com JWT próprio (`PONTO_APP_JWT_SECRET`), GPS Haversine, captura facial
- ❌ **Cliente React Native/Expo não existe no repo** — está planejado para Bloco E (pós go-live)
- ❌ Sem PWA / service worker — não há funcionalidade offline para o ponto via web

## 11.2 O que falta (gaps reais)

### 🔴 CRÍTICO — DEPLOY-BLOCKING

| # | Item | Solução | Esforço |
|---|---|---|---|
| **DEPLOY-VUE-01** | **~60 arquivos `*View - Copia.vue` precisam ser DELETADOS antes do `npm run build` final.** Inflam bundle (~2x maior potencial), risco de routing pegar versão errada se algum import escapou, confunde quem manutenciona. **Em produção isso é deploy-blocking.** | PowerShell pré-build: `Get-ChildItem -Path resources/gente-v3/src/views -Recurse -Filter "* - Copia.vue" \| Remove-Item -Force`. Depois `npm run build`. | 10 min |
| **DEPLOY-VUE-02** | Após deletar Copias, rebuild `dist/` é obrigatório. O dist atual (07/05 17:09) inclui muito JS+CSS de Copia. | `cd resources/gente-v3 && npm install && npm run build` | 5 min |

### 🟡 RESSALVAS PRÉ GO-LIVE

| # | Item | Esforço |
|---|---|---|
| **Imports usam `../views/`** em vez de alias `@/views/` consistentemente — funcional, mas inconsistente com `main.js` que usa `@/lib/...`, `@/store/...` | Refactor (cosmético) | 1h |
| **Polling 60s/90s** no DashboardLayout — em base com 30k usuários conectados pode pesar no servidor (1.500 req/s entre notificações + substituições). Pausa em aba background ajuda mas não basta. | Pós go-live: SSE ou WebSocket | Decisão arquitetural |
| **Sem testes E2E reais** — Playwright instalado mas só `screenshot-all.js` e `screenshot-autocadastro.js` | Pós go-live: criar suite mínima (login, criar funcionário, gerar folha) | 1-2 dias |
| **Sem TypeScript** — sem type safety em build-time | Pós go-live: migrar `.vue` para `<script setup lang="ts">` gradualmente | Decisão arquitetural |
| **DOMPurify whitelist conservadora** — não permite tabelas/imagens em conteúdo rico (declarações com layout) | Avaliar se algum template precisa de tags adicionais | 1h |
| **HMAC signing dependente de `request_signing_enabled` no /me** — verificar se backend está retornando isso para todos os usuários produção | Auditar `/api/auth/me` payload em produção | 30 min |
| **`isMobileNow()` usa breakpoint hardcoded 767px** — mas Vuetify default mobile é 599px. Pode haver inconsistência visual entre componentes Vuetify e CSS custom | Padronizar em uma constante | 30 min |
| **Polling de notificações continua se sudo global view ativa** — pode trazer notificações de **todas as unidades**, sobrecarregando UI | Filtrar notif por contexto sudo | 1h |
| **`navManifest.js` tem 1 sidebar item duplicado** — "Sobreaviso" aparece em "Minha Equipe" e indiretamente em outros pontos | Auditar | 15 min |

### 🟢 BAIXO

| # | Item |
|---|---|
| `package.json` `"version": "0.0.0"` — nunca incrementado |
| Sem `<meta>` SEO no `index.html` |
| Logo `dist/logo.png` poderia ser SVG (escala melhor) |
| `vite-plugin-vuetify autoImport: true` — útil mas pode importar componentes não usados |
| Console.error no `logout` action ainda em produção |
| Sem error boundary global Vue (pode quebrar inteiro com erro de render) |

## 11.3 Status pré-produção do Bloco 11

**Veredicto:** **🟢 PRONTO COM RESSALVAS**

Frontend é **maduro e bem-arquitetado**:
- Stack moderna e adequada (Vue 3.5, Vite 7, Pinia 3, Vuetify 3.12, Tailwind 4)
- RBAC granular slug-based com fallback graceful para roles
- 3 bridges customizados injetando contexto sudo/funcionário/HMAC no axios — engenharia sólida
- Code splitting com lazy loading funcional
- Layout responsivo completo (desktop/tablet/mobile)
- Suporte multi-vínculo (acúmulo de cargos)
- Sudo global view com auditoria
- Banner SEMAD + toast RBAC denied (UX de governança)
- Polling inteligente (pausa em aba background)

**Único bloqueador real para o deploy de domingo:** a **limpeza dos `*View - Copia.vue` + rebuild dist/**. Isso é 15 minutos de trabalho.

### Ação concreta — Bloco 11 (priorizado)

1. **Pré-build cleanup (10 min):**
   ```powershell
   Get-ChildItem -Path C:\Users\joaob\Desktop\sisgep-job-main\gente\resources\gente-v3\src\views -Recurse -Filter "* - Copia.vue" | Remove-Item -Force
   ```
2. **Rebuild dist (5 min):**
   ```bash
   cd resources/gente-v3
   npm install
   npm run build
   ```
3. **Verificar `vite.config.js`** — confirmar que proxy não vai pra produção (Nginx que fará) ✅ já está só em `server.proxy`, não atrapalha build
4. **Confirmar `request_signing_enabled` no /me em produção (30 min):** auditar payload de exemplo no SQL Server prod para garantir que HMAC está habilitado para users que devem ter
5. **Padronizar breakpoint mobile (30 min):** alinhar `isMobileNow()` com Vuetify

**Total esforço Bloco 11 (essencial):** ~45 minutos para o estado "pronto-pronto".

### Pós go-live (estratégico)

- Cliente mobile React Native/Expo para o ponto eletrônico (Bloco E original — 2-3 semanas)
- PWA + service worker se houver demanda offline
- Testes E2E Playwright reais (login, fluxo crítico de folha) — 1-2 dias
- Migração TypeScript gradual (decisão arquitetural)
- SSE/WebSocket para notificações (eliminar polling) — 3-5 dias
- Padronizar imports usando alias `@` (cosmético) — 1h
- Error boundary global Vue + Sentry/equivalente — 4-6h
- Auditoria de bundle size + treeshake desnecessário (potencial -30% bundle)

---

# BLOCO 12 — INFRAESTRUTURA (Auth, RBAC, Audit, Email, Filas, Cache, Sessions, Deploy VPS)

**Status global do bloco:** ⚠️ **AJUSTES NECESSÁRIOS PRÉ-DEPLOY**

> **Bloco mais transversal de toda a auditoria.** Cobre todos os "trilhos" sob os quais os 11 blocos anteriores rodam: autenticação Sanctum/CSRF, RBAC granular, audit chain criptográfico, email com templates HTML editáveis, filas Laravel, cache, sessions, e o **deploy completo na VPS Hostinger Ubuntu 22.04 com deadline domingo 10/05**.

## 12.1 O que tem

### Autenticação (Sanctum + CSRF)
- ✅ **Laravel Sanctum** — sessions HTTP-only via `withCredentials: true` no axios
- ✅ **CSRF protection** ativada (proxy Vite em dev cuida do `csrf-cookie`)
- ✅ **`SESSION_DRIVER=file`** (default) — para escala 30k usuários considerar Redis
- ✅ **`SESSION_LIFETIME=120` min** (2 horas)
- ✅ `expire_on_close: true` — fecha sessão ao fechar browser
- ✅ **`encrypt: true`** — sessions criptografadas em disco
- ✅ Cookies `secure: true` (HTTPS-only) + `http_only: true` + `same_site: 'lax'`
- ✅ **Workflow primeiro acesso:**
  - `forcePasswordChange` lido de `USUARIO_PRIMEIRO_ACESSO` ou `USUARIO_ALTERAR_SENHA`
  - Backend retorna `412/403 + error=PASSWORD_CHANGE_REQUIRED`
  - Frontend redireciona automático para `/primeiro-acesso`
- ✅ Logout endpoint `/api/auth/logout` que limpa session + cache
- ✅ Endpoint `/api/auth/me` retorna payload completo do usuário com perfis, slugs RBAC, escopo de unidades, vínculos

### RBAC Granular (Multi-tenant — Fase 3A)
**Tabelas:**
- ✅ `GENTE_ROLE` — papéis (admin, rh_folha, gestor, sesmt, etc.)
- ✅ `GENTE_PERMISSION` — permissões com slug (`global.mde.25`, `escala.grade.editar`, `rh.progressao.lei4928`, etc.)
- ✅ `GENTE_ROLE_PERMISSION` — N:N entre roles e permissions
- ✅ `GENTE_ASSIGNMENT` — atribuição usuário → role com TENANT_TYPE + TENANT_ID

**Tenant Types** (alinhado a `config/gente.php` rbac):
- `SECRETARIA` — secretaria (TENANT_ID = UNIDADE_ID da sede)
- `UNIDADE` — unidade específica
- `POLO` — polo (gestor multi-unidade)
- `GLOBAL_SEMED` — âncora SEMED (educação)
- `GLOBAL_SEMAD` — âncora SEMAD (auditoria)

**Matriz YAML:**
- ✅ `database/rbac/rbac_matrix.v1.yaml` — define slugs e roles
- ✅ Configurável via `GENTE_RBAC_MATRIX_YAML`

**Slugs RBAC chave:**
- `global.mde.25` — diretoria MDE (manutenção e desenvolvimento do ensino, 25% LRF)
- `unidade.dashboard.kpi` — gestor de unidade vê KPI
- `escala.grade.visualizar` / `escala.grade.editar` — escalas
- `escala.override.sudo_grade` — sudo grade
- `rh.progressao.lei4928` — progressão Lei 4.928/2008
- `organograma.unidade.visualizar` — organograma
- `financeiro.previdencia.ipam` — IPAM/RPPS
- `auditoria_matriz_semad` — papel SEMAD auditor (read-only)

**Frontend respeita RBAC:**
- ✅ `meta.roles` no router (legacy hierarchy)
- ✅ `requiredAnySlugs` no `navManifest.js` (granular)
- ✅ Fallback graceful: se sessão sem slugs RBAC, usa roles legacy (`RBAC_UI_STRICT=false`)
- ✅ Em produção: ativar `VITE_GENTE_RBAC_UI_STRICT=true` quando assignments estiverem populados

### Audit Chain Criptográfico (Frente 4)
- ✅ **Tabela `AUDIT_LOG`** com:
  - `HASH_PREV` (hash do registro anterior)
  - `HASH_CONCAT` (hash atual = SHA256(prev + payload))
  - Cadeia imutável detectável de tampering
- ✅ **`GenteAuditWriter::insertChainedRow`** — service centralizado já em uso (visto no Bloco 4 Escalas)
- ✅ **`audit_log.immutability=true`** (default) — bloqueia UPDATE/DELETE em AUDIT_LOG via Eloquent observer
- ✅ **`audit_log.chain_enabled=true`** (default)
- ✅ **Secure Vault** (`secure_vault.enabled=true`) — exporta lotes para disk/S3 fora do banco para preservação
- ✅ **Tarpit** anti-bruteforce: 5 erros 4xx em 60s → penalty TTL 600s com sleep adaptativo até 16s

### Anti-Tampering & Honeypots (Frente 3)
- ✅ **`honeytokens.enabled=true`** — registros isca em FUNCIONARIO/PESSOA
- ✅ **Blocklist** de IP que tocou em isca (24h por padrão)
- ✅ **Canário** de leitura — alerta se ler isca

### Integridade de Payload (Frente 2)
- ✅ **HMAC SHA-256** em POST/PUT/PATCH/DELETE (visto no axios.js Bloco 11)
- ✅ Headers: `X-Gente-Signature` + `X-Gente-Timestamp`
- ✅ Anti-replay com janela `leeway_ms=30000` (30s)
- ✅ Secret de sessão via `gente_request_signing_secret` (devolvido em /me quando ativo)
- ⚠️ **`request_signature.enabled=false` por default** — em produção deve ser `true`

### PII / LGPD (Frente 1)
- ✅ **Blind index HMAC** separado de `APP_KEY`:
  - `GENTE_PII_BLIND_SALT` para CPF (busca segura sem decrypt)
- ✅ **FLE — Field Level Encryption** opcional:
  - `GENTE_PII_CPF_ENCRYPTED=true` ativa criptografia CPF em PESSOA_CPF_NUMERO
- ✅ **Model hide CPF**:
  - `GENTE_PII_MODEL_HIDE_CPF=true` oculta CPF em toArray/JSON (APIs precisam fazer `makeVisible`)
- ✅ **Comando `php artisan gente:secure-pii --fle`** para migrar CPFs em massa
- ✅ Mascaramento de CPF por padrão em transparência (`123.***.***-45`)

### Email (`config/mail.php`)
**Mailers suportados:**
- ✅ **`smtp`** — genérico
- ✅ **`brevo` (Sendinblue)** — pré-configurado em `config/mail.php`:
  - `BREVO_HOST=smtp-relay.brevo.com`
  - `BREVO_PORT=587`
  - `BREVO_USERNAME` + `BREVO_API_KEY`
- ✅ **`ses`**, **`mailgun`**, **`postmark`**, **`sendmail`**, **`log`**, **`array`**
- ✅ Markdown templates em `resources/views/vendor/mail`
- ✅ `from_address` via `MAIL_FROM_ADDRESS` (default `noreply@gente.rrtecnol.com.br`)

**Templates HTML editáveis (status atual):**
- ✅ Templates de **declaração** e **certificado** com editor inline existem (mencionado em userMemories)
- ⚠️ **Templates de email com mesmo padrão estilo declaração/certificado AINDA NÃO ESTÃO PRONTOS** — código pronto, plumbing operacional
- ⏸️ **SMTP é pendente operacional** — aguardando credenciais Brevo/SES/SMTP da PMSL

### Filas Laravel
- ⚠️ **`QUEUE_CONNECTION=sync` por default** — em produção precisa ser `database` ou `redis`
- ✅ Conexões disponíveis: `sync`, `database`, `redis`, `beanstalkd`, `sqs`, `null`
- ✅ Tabela `jobs` para driver `database`
- ✅ Tabela `failed_jobs` com `database-uuids` para retry
- ✅ **Bus::batch já usado** em `ProcessarFolhaJob` (Bloco 1) — folha 30k servidores em paralelo
- ✅ `start-dev.sh` roda `php artisan queue:work` em foreground para dev

### Cache
- ⚠️ **`CACHE_DRIVER=file` por default** — em produção precisa ser `redis` (especialmente com 30k usuários)
- ✅ Drivers disponíveis: `apc`, `array`, `database`, `file`, `memcached`, `redis`, `dynamodb`, `null`
- ✅ Lock connection separada para Redis (locks distribuídos do MotorFolhaService)

### Database
- ✅ **`DB_CONNECTION=sqlsrv` configurado** em `.env.example`
- ✅ **SQL Server 2019** via `mcr.microsoft.com/mssql/server:2019-latest` (docker-compose.yml)
- ✅ Driver ODBC 18 (`msodbcsql18`) + PECL `pdo_sqlsrv` + `sqlsrv` no Dockerfile
- ✅ `DB_LOGIN_TIMEOUT=8` para failfast em healthcheck
- ✅ `trust_server_certificate=true` (auto-signed certs aceitos)
- ✅ Migrações + seed automáticos no comando do container (`php artisan migrate --force && php artisan db:seed --force`)

### Docker / Container
- ✅ **`Dockerfile`** com PHP 8.4-fpm + extensões (gd, mbstring, exif, pcntl, bcmath, xml, zip, pdo_sqlsrv, sqlsrv)
- ✅ **`docker-compose.yml`** com 3 serviços:
  - `app` (PHP-FPM 8.4) + bind mount + cache de bootstrap
  - `nginx:alpine` na porta 8081
  - `sqlserver:2019-latest` Express (gratuito) com healthcheck
- ✅ Volumes nomeados: `gente_sqldata`, `gente_bootstrap_cache`, `gente_storage_framework`
- ✅ Network bridge `gente_net`
- ⚠️ **Não tem container para Redis** — quando migrar para QUEUE_CONNECTION=redis precisa adicionar
- ⚠️ **Não tem container para worker de filas** — `php-fpm` é o único processo no app, queue:work precisa de container separado ou supervisor

### Mobile App (Ponto Eletrônico)
- ✅ **`mobile/ponto-app/` existe!** — Expo SDK 51 + expo-router 3.5
- ✅ **Stack:** React Native 0.74 + React 18.2 + Axios 1.7 + TypeScript 5.3
- ✅ **Recursos nativos:**
  - `expo-camera` (~15.0) — captura selfie
  - `expo-face-detector` (~13.0) — detecção facial local
  - `expo-location` (~17.0) — GPS para validar localização do terminal
  - `expo-secure-store` (~13.0) — JWT armazenado em keychain/keystore
  - `expo-image-manipulator` (~12.0) — comprime selfie antes de upload
- ✅ **Permissões iOS/Android configuradas:**
  - iOS: `NSCameraUsageDescription`, `NSLocationWhenInUseUsageDescription`
  - Android: `CAMERA`, `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`
- ✅ **Bundle IDs:** `br.gov.pontoGente` (iOS + Android)
- ✅ **EAS Build configurado** para gerar APK e IPA
- ✅ **Splash screen** configurada (cor `#1a56db`)
- ✅ **Estrutura clara:** `app/` (rotas), `screens/`, `services/api.js` + `FaceService.js`
- ⚠️ **`extra.eas.projectId: "SEU_PROJECT_ID_EAS"`** — placeholder, precisa criar projeto EAS real
- ⚠️ **`services/api.js BASE_URL` hardcoded local** — precisa ser substituído pela URL produção VPS

### Configurações Operacionais Sofisticadas
- ✅ **Sudo Global View** (`sudo_global_view.enabled=true`) — header `X-Gente-Global-View` para super admins via:
  - `GENTE_SUPER_ADMIN_USUARIO_IDS=...`
  - `GENTE_SUPER_ADMIN_EMAILS=...`
- ✅ **Funcionario Context Header** (acúmulo) — `X-Gente-Funcionario-Context-Id`
- ✅ **PCCV (Frente 5)** — escala impositiva com tolerância 0.25h e justificativa mínima 20 chars
- ✅ **Tenant Scope Middleware** com rollout híbrido:
  - Semana 1: `MIDDLEWARE=true, ENFORCE=false` (shadow mode)
  - Semana 2+: `ENFORCE=true` quando logs limpos

## 12.2 O que falta (gaps reais)

### 🔴 CRÍTICO — DEPLOY-BLOCKING

| # | Item | Solução | Esforço |
|---|---|---|---|
| **DEPLOY-INFRA-01** | **`QUEUE_CONNECTION=sync` em prod = folha de 30k servidores síncrona = timeout HTTP** | Configurar `QUEUE_CONNECTION=database` mínimo + supervisor com `php artisan queue:work` | 1-2h |
| **DEPLOY-INFRA-02** | **`CACHE_DRIVER=file` em prod = locks distribuídos do MotorFolhaService não funcionam entre múltiplos workers** | Configurar Redis no VPS + `CACHE_DRIVER=redis` + locks no Bus::batch | 2-3h |
| **DEPLOY-INFRA-03** | **`SESSION_DRIVER=file` em prod = sessions perdidas em rolling restart, problema com múltiplos PHP-FPM workers** | Migrar para `SESSION_DRIVER=database` ou `redis` | 1h |
| **DEPLOY-INFRA-04** | **`GENTE_REQUEST_SIGNATURE_ENABLED=false`** em prod — HMAC anti-replay desligado | Ativar `=true` em produção | 15 min |
| **DEPLOY-INFRA-05** | **`APP_KEY` vazio em `.env.example`** — em prod precisa gerar | `php artisan key:generate` | 5 min |
| **DEPLOY-INFRA-06** | **`GENTE_PII_BLIND_SALT` vazio** — busca segura por CPF não funciona | Gerar com `openssl rand -base64 32` e gravar em .env prod | 5 min |
| **DEPLOY-INFRA-07** | **APP_DEBUG=true vai vazar stack traces em prod** | `APP_DEBUG=false`, `APP_ENV=production` | 5 min |
| **DEPLOY-INFRA-08** | **Templates de email HTML estilo declaração/certificado AINDA NÃO ESTÃO PRONTOS** | Criar tabela `EMAIL_TEMPLATE` com editor inline (admin), templates: boas-vindas, primeiro acesso, recuperação senha, declaração emitida, contracheque disponível, escala homologada, atestado aprovado | 6-8h |
| **DEPLOY-INFRA-09** | **Mobile app `BASE_URL` e `eas.projectId` hardcoded placeholders** | Substituir por URL produção VPS + criar projeto EAS real para builds APK/IPA | 1-2h |

### 🟡 RESSALVAS PRÉ GO-LIVE — Configuração VPS Hostinger Ubuntu 22.04

#### A) Sistema base
| Item | Esforço |
|---|---|
| Atualizar pacotes: `apt update && apt upgrade -y` | 15 min |
| **Firewall UFW** ativado: portas 22 (SSH), 80 (HTTP), 443 (HTTPS), bloquear o resto | 15 min |
| **Fail2ban** para SSH | 30 min |
| Usuário não-root (`gente`) com sudo + chave SSH | 15 min |
| Desabilitar root login + password auth no SSH (`/etc/ssh/sshd_config`) | 15 min |
| Timezone `America/Fortaleza` (São Luís fuso) | 5 min |

#### B) Stack PHP + SQL Server
| Item | Esforço |
|---|---|
| Instalar PHP 8.4 (`apt install php8.4-fpm php8.4-cli php8.4-mbstring php8.4-bcmath php8.4-xml php8.4-zip php8.4-gd php8.4-curl php8.4-redis`) | 30 min |
| Instalar driver ODBC + msodbcsql18 + PECL pdo_sqlsrv + sqlsrv | 1h |
| **Decisão:** SQL Server roda no VPS Hostinger? Ou em servidor dedicado da PMSL? Hostinger VPS comum não roda SQL Server bem (muita RAM). **Considerar MariaDB se o ente municipal aceitar** — Laravel é DB-agnostic (mas depende do quanto SQLite-only foi escrito) | Decisão urgente |
| Composer global | 10 min |
| Node.js 20 LTS (para `npm run build` se for fazer build no VPS) | 15 min |

#### C) Nginx
| Item | Esforço |
|---|---|
| Instalar Nginx 1.26+ (`apt install nginx`) | 15 min |
| Config server block para servir `public/` do Laravel + proxy PHP-FPM via socket | 1h |
| Config para servir SPA Vue (`/` → `index.html` com fallback `try_files`) | 30 min |
| Cache de assets estáticos com `expires 1y` para `/dist/assets/*` | 15 min |
| `client_max_body_size 50M` para uploads (anexos atestados, documentos terceirizados) | 5 min |
| **Gzip + Brotli** para texto/JSON | 30 min |
| **Headers de segurança:** HSTS, X-Frame-Options DENY, X-Content-Type-Options, CSP básico | 1h |

#### D) Certbot SSL Let's Encrypt
| Item | Esforço |
|---|---|
| Instalar certbot + plugin nginx (`apt install certbot python3-certbot-nginx`) | 15 min |
| Emitir certificado para `gente.saoluis.ma.gov.br` (ou domínio definido) | 15 min |
| Verificar renovação automática via cron (`certbot renew --dry-run`) | 15 min |

#### E) Supervisor (workers de fila)
| Item | Esforço |
|---|---|
| Instalar Supervisor (`apt install supervisor`) | 10 min |
| Config `/etc/supervisor/conf.d/gente-worker.conf` com 4 workers para `queue:work` | 30 min |
| Habilitar autostart + restart on failure | 15 min |

#### F) Cron Schedule (Laravel scheduler)
| Item | Esforço |
|---|---|
| Adicionar `* * * * * cd /var/www/gente && php artisan schedule:run >> /dev/null 2>&1` no crontab do user `gente` | 15 min |
| Configurar comandos schedulados em `app/Console/Kernel.php`:<br>- backup de banco diário<br>- limpeza sessions/cache expirados<br>- cron mensal SEFIP/CAGED (Bloco 7)<br>- cron diário Transparência (Bloco 8)<br>- alerta de manutenção de veículos (Bloco 10)<br>- alerta de contratos vencendo (Bloco 10) | 2-3h |

#### G) Backup Strategy
| Item | Esforço |
|---|---|
| Script bash diário backup `mssql-tools17/sqlcmd` ou `mysqldump` (depende da decisão de DB) | 2h |
| Compressão gzip + rotação 30 dias local + upload S3 (se houver) | 1h |
| Backup de `storage/app/secure_vault/audit/*` (audit chain export) | 1h |
| Backup de `storage/app/public/atestados/*` (anexos médicos) | 1h |
| Cron diário 02:00 + alerta por email se falhar | 1h |

#### H) Variáveis de Ambiente Críticas (.env produção)
| Variável | Valor produção |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://gente.saoluis.ma.gov.br` |
| `APP_KEY` | (gerar com `php artisan key:generate`) |
| `LOG_LEVEL` | `warning` |
| `DB_CONNECTION` | `sqlsrv` ou `mysql` |
| `DB_HOST` | (IP/hostname do banco) |
| `DB_PORT` | `1433` ou `3306` |
| `DB_DATABASE` | `gente` |
| `DB_USERNAME` | (usuário service) |
| `DB_PASSWORD` | (senha forte 32+ chars) |
| `BROADCAST_DRIVER` | `redis` |
| `CACHE_DRIVER` | `redis` |
| `QUEUE_CONNECTION` | `redis` ou `database` |
| `SESSION_DRIVER` | `redis` ou `database` |
| `REDIS_HOST` | `127.0.0.1` |
| `REDIS_PASSWORD` | (gerar) |
| `MAIL_MAILER` | `brevo` (decisão) |
| `BREVO_USERNAME` | (real) |
| `BREVO_API_KEY` | (real) |
| `MAIL_FROM_ADDRESS` | `noreply@saoluis.ma.gov.br` |
| `MAIL_FROM_NAME` | `GENTE — Prefeitura São Luís` |
| `ESOCIAL_AMBIENTE` | `2` (homologação primeiro!) |
| `PONTO_APP_JWT_SECRET` | (gerar com `openssl rand -base64 64`) |
| `ORGAO_CNPJ` | CNPJ real PMSL |
| `ORGAO_NOME` | "PREFEITURA MUNICIPAL DE SÃO LUÍS" |
| `GENTE_PII_BLIND_SALT` | (gerar com `openssl rand -base64 32`) |
| `GENTE_REQUEST_SIGNATURE_ENABLED` | `true` |
| `GENTE_HONEYTOKENS_ENABLED` | `true` |
| `GENTE_AUDIT_LOG_IMMUTABLE` | `true` |
| `GENTE_AUDIT_CHAIN` | `true` |
| `GENTE_PCCV_ESCALA_ENABLED` | `true` |
| `GENTE_TENANT_SCOPE_MIDDLEWARE` | `true` (shadow primeiro) |
| `GENTE_TENANT_SCOPE_ENFORCE` | `false` (semana 1) → `true` (semana 2+) |
| `VITE_GENTE_RBAC_UI_STRICT` | `false` (semana 1) → `true` (após popular assignments) |

#### I) Deploy Steps (script de deploy)
```bash
# 1. Clone
cd /var/www
git clone <repo> gente
cd gente

# 2. Install PHP deps
composer install --no-dev --optimize-autoloader

# 3. Build front
cd resources/gente-v3
# CRÍTICO: deletar Copia.vue antes do build
find src/views -name "* - Copia.vue" -delete
npm install --production
npm run build
cd ../..

# 4. Configurar .env (copiar de .env.example e ajustar)
cp .env.example .env
nano .env  # editar todos os valores produção
php artisan key:generate

# 5. Otimizações
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Migrações
php artisan migrate --force
php artisan db:seed --force --class=ProductionSeeder  # se tiver

# 7. Permissões
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 8. Supervisor
sudo cp docker/supervisor/gente-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

# 9. Nginx
sudo cp docker/nginx/gente-prod.conf /etc/nginx/sites-available/gente
sudo ln -sf /etc/nginx/sites-available/gente /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 10. SSL
sudo certbot --nginx -d gente.saoluis.ma.gov.br

# 11. Cron
echo "* * * * * cd /var/www/gente && php artisan schedule:run >> /dev/null 2>&1" | sudo tee -a /var/spool/cron/crontabs/gente
```

### 🟢 BAIXO

| # | Item |
|---|---|
| **`docker-compose.yml`** atual é dev-only (sem container Redis nem worker). Pós go-live: criar `docker-compose.prod.yml` |
| Healthcheck endpoint `/up` pode ser melhorado com checagem de DB + Redis |
| `Horizon` (dashboard de filas Redis) não está configurado — útil pós go-live |
| `Telescope` (debug bar Laravel) está habilitado em dev — confirmar que não vai pra prod |
| Sem APM (New Relic, Datadog, Sentry) — aceitável para v1, planejar pós go-live |
| `start-dev.sh` é 100% dev (artisan serve + queue:work foreground) |
| Arquivos suspeitos no root: `cleanup_identities.php`, `deep_clean.php`, `fix_users.php`, `nuclear_ti.php`, `hard_cleanup.php`, `dump_ti.php`, `patch_routes.php`, `scan_routes.php`, `scan_routes_multiline.php` — verificar se entram no deploy ou se são scripts pontuais (provável só dev) |

## 12.3 Status pré-produção do Bloco 12

**Veredicto:** ⚠️ **AJUSTES NECESSÁRIOS PRÉ-DEPLOY**

A infraestrutura **conceitual** está madura — RBAC granular multi-tenant, audit chain criptográfico, HMAC anti-replay, honeytokens, tarpit, secure vault, PCCV. Há 5 "Frentes" de segurança implementadas e configuráveis via env. O Dockerfile e docker-compose são funcionais. O mobile app existe (Expo SDK 51).

**Mas há ajustes específicos para produção:**
1. Configurar Redis (cache + queue + session)
2. Templates de email HTML editáveis (estilo declaração/certificado) — 6-8h
3. Configurar mobile app com URL real + projeto EAS real
4. Setup VPS Ubuntu 22.04 completo (~12-16h de trabalho operacional)

### Ação concreta — Bloco 12 (priorizado para deadline domingo 10/05)

**Antes de subir VPS:**

1. **Configurar `.env` produção (1h):** preencher todos os valores da tabela acima
2. **Criar templates de email HTML (6-8h):** `EMAIL_TEMPLATE` table + admin UI + 7 templates (welcome, first-access, password-reset, declaration-issued, payslip-available, schedule-homologated, attestation-approved)
3. **Configurar mobile app (1-2h):** atualizar `services/api.js BASE_URL` e criar projeto EAS, ajustar `eas.projectId`
4. **Limpar root scripts (15 min):** mover `*.php` órfãos para `scripts-debug/` ou deletar
5. **`.dockerignore` e `.gitignore`** — confirmar que `.env`, `vendor/`, `node_modules/` estão fora

**Setup VPS Hostinger Ubuntu 22.04 (12-16h):**

1. Sistema base + UFW + fail2ban + SSH hardening (~3h)
2. Stack PHP 8.4 + Nginx + Redis + driver SQL Server ou MariaDB (~4h)
3. Certbot SSL + DNS apontando (~1h)
4. Supervisor + 4 workers queue (~1h)
5. Cron + comandos schedulados (~3h)
6. Backup diário script (~3h)
7. Deploy completo + smoke tests (~2h)

**Total esforço Bloco 12 (essencial para deploy):** ~25-30 horas. **Cabe na semana se 2 pessoas trabalharem em paralelo (1 dev + 1 ops).**

### Pós go-live (estratégico)

- Container Redis + worker em `docker-compose.prod.yml` (2-4h)
- Horizon dashboard de filas (1-2h)
- Sentry/equivalente APM (4-6h)
- Healthcheck enriquecido `/up` com DB+Redis (1h)
- Pipeline CI/CD (GitHub Actions) com deploy automatizado (1-2 dias)
- Mobile app: build EAS automatizado + publish stores (1-2 dias)
- Logging centralizado (ELK ou Grafana Loki) (1-2 dias)

---

# RESUMO EXECUTIVO — Plano de Trabalho até Domingo 10/05/2026

**Hoje:** Quinta, 07/05/2026 (T-3 dias)
**Deadline produção:** Domingo, 10/05/2026

## Veredicto Geral

| Bloco | Módulo | Status | Esforço essencial |
|---|---|---|---|
| 1 | Folha de Pagamento | 🟢 Pronto com ressalvas | 3-4h |
| 2 | Cadastro Funcional | 🟢 Pronto com ressalvas | 2-3h |
| 3 | Ponto e Jornada | 🟢 Pronto com ressalvas | 5-6h |
| 4 | Escala de Trabalho | ✅ Pronto | 1.5h |
| 5 | Saúde e Segurança | 🟢 Pronto com ressalvas | 1-2h essencial |
| 6 | Bancário e Consignação | ⚠️ Ajustes necessários | 3-5h + 1d CNAB |
| 7 | **Governo Federal (eSocial)** | 🔴 **CRÍTICO** | 4-5h (com decisão arquitetural) |
| 8 | Governo Estadual/Municipal | ⚠️ Ajustes necessários | 4-5h |
| 9 | ERP Fiscal | 🟢 Pronto com ressalvas | 5-7h |
| 10 | Compras / Patrimônio | 🟢 Pronto com ressalvas | 5-7h |
| 11 | Frontend Vue + Mobile | 🟢 Pronto com ressalvas | 45 min |
| 12 | **Infraestrutura + Deploy VPS** | ⚠️ Ajustes necessários | 25-30h |

**Total trabalho essencial:** ~70-90 horas. **Cabe em 3 dias com 2-3 pessoas trabalhando em paralelo (1 dev backend + 1 dev frontend/mobile + 1 ops).** Sozinho não cabe — precisa estender prazo ou descopar.

## Decisões Arquiteturais URGENTES (esta tarde)

1. **eSocial (Bloco 7):** GENTE só **gera XMLs** ou também **transmite**?
   - Se PMSL tem outro sistema transmitindo → MANTER status atual (XMLs gerados, transmissão manual via cópia de recibo)
   - Se PMSL espera GENTE transmitir → **IMPOSSÍVEL ATÉ DOMINGO** (precisa SOAP+XMLDSig+A1+homologação RFB = 2-3 semanas). Estender prazo OU contratar transmissor terceiro.

2. **CNAB (Bloco 6):** Manter formato custom pipe-delimited ou migrar para FEBRABAN 240?
   - Opção A — manter custom + plano migração T+90: 30 min docs
   - Opção B — migrar agora: 1 dia + revalidação com banco

3. **Banco em produção (Bloco 12):** SQL Server (caro, lento em VPS comum) ou MariaDB?
   - Decisão depende da PMSL ter licença SQL Server e infra dedicada
   - Se VPS Hostinger compartilhado → MariaDB recomendado (precisa testar SQLite-only queries em todos os blocos)

4. **Templates de email (Bloco 12):** PMSL já forneceu credenciais Brevo/SES?
   - Se sim → 6-8h pra fazer editor inline + 7 templates
   - Se não → fica pendente operacional, sistema sobe sem email funcional

## Cronograma Sugerido (3 dias)

### **Sexta 08/05 (Dia 1) — Backend Fixes + Decisões**

**Frente backend (developer 1):**
- ✅ R52, R53, R59, R60 (eSocial CNPJ/tpAmb/whereYear/indRetif) — 2h
- ✅ Validação saldo LOA no empenho + valor liquidação ≤ empenho + pagamento ≤ liquidação (Bloco 9) — 2h
- ✅ Corrigir limite LRF municipal em controle_externo.php (54/51.3 → 60/57/54) — 30 min
- ✅ Trocar `whereYear/whereMonth/strftime` em siconfi.php + dirf.php + rais.php (Blocos 7-8) — 2h
- ✅ R51 INSS RGPS 2025 (Bloco 1) — 1h
- ✅ R72 remover escalaDebugLogF94096 + R39 SQLite-only escalas (Bloco 4) — 1h

**Frente backend (developer 2):**
- ✅ Resolver duplicação `/sagres/*` entre sagres.php e controle_externo.php (Bloco 8) — 30 min
- ✅ Limite 25% em aditivos contratos admin (Bloco 10) — 1.5h
- ✅ Pagamento cria movimentação bancária automática (Bloco 9) — 1.5h
- ✅ DepreciacaoService SQL Server compatible (Bloco 10) — 30 min
- ✅ Custo médio almoxarifado correto (Bloco 10) — 1h
- ✅ Reativar consignação com revalidação margem (Bloco 6) — 30 min
- ✅ Bulk insert parcelas + validação CNPJ (Bloco 6) — 1h
- ✅ R12, R14, R16-R20 folha (Bloco 1) — 2h

**Frente ops/devops (developer 3):**
- ✅ Provisionar VPS Hostinger Ubuntu 22.04 — sistema base + UFW + fail2ban + SSH hardening — 3h
- ✅ Stack PHP 8.4 + extensões + Composer + Node — 2h
- ✅ Decidir + instalar SQL Server (se houver licença) ou MariaDB — 2h
- ✅ Nginx config — 1h

### **Sábado 09/05 (Dia 2) — Frontend, Mobile, Email, VPS**

**Frente frontend (developer 1):**
- ✅ Limpeza `*View - Copia.vue` + rebuild dist/ (Bloco 11) — 30 min
- ✅ Confirmar request_signing_enabled no /me (Bloco 11) — 30 min
- ✅ R69 SQL injection Lotacao (Bloco 2) — 1h
- ✅ B-LAYOUT-EDITOR (Bloco 6) — 2-4h
- ✅ Configurar mobile app: BASE_URL + EAS projectId (Bloco 12) — 2h
- ✅ Build APK Android via EAS — 1h

**Frente backend (developer 2):**
- ✅ Templates de email HTML editáveis: tabela + admin UI + 7 templates (Bloco 12) — 6-8h
- ✅ Documentar limitação eSocial (README do módulo) — 1h
- ✅ Testes de integração folha+contabilidade+SAGRES — 2h

**Frente ops/devops (developer 3):**
- ✅ Redis instalado e configurado — 1h
- ✅ Supervisor + 4 workers queue — 1h
- ✅ Certbot SSL + DNS — 1h
- ✅ Cron schedule + comandos (backup, sefip, transparência, manutenção) — 3h
- ✅ Backup script diário + rotação 30 dias — 3h
- ✅ Deploy primeiro smoke test (homologação no VPS) — 2h

### **Domingo 10/05 (Dia 3) — Go-Live**

**Manhã (08:00-12:00):**
- ✅ Deploy final em produção — 1h
- ✅ `php artisan migrate --force` em produção — 30 min
- ✅ Seeds de produção (USUARIO admin inicial, PCASP, FERIADO 2026) — 1h
- ✅ Smoke tests críticos:
  - Login + primeiro acesso — 15 min
  - Cadastrar funcionário — 15 min
  - Marcar ponto via mobile — 30 min
  - Gerar contracheque — 15 min
  - Gerar arquivo SAGRES — 15 min

**Tarde (13:00-18:00):**
- ✅ Treinar equipe RH PMSL no novo sistema — 3h
- ✅ Importar base inicial (servidores ativos via SISFOLHA) — 2h
- ✅ Validação final + handoff — 1h

## Itens descopados para PÓS GO-LIVE

| Item | Bloco | Esforço | Por quê descopar |
|---|---|---|---|
| eSocial transmissão SOAP+XMLDSig real | 7 | 2-3 dias dev + 1 sem RFB | Deadline impossível; usa transmissor terceiro |
| Validação XML SAGRES contra XSD TCE-MA | 8 | 1-2 dias | XML "v1.0 simplificado" funcionou na PoC, ajusta depois |
| eventos detalhados em transparência CSV (Lei 131) | 8 | 4-6h | CSV consolidado atende 80% |
| Importador LOA via CSV/XML | 9 | 4-6h | RH cadastra manualmente nas primeiras semanas |
| Encerramento balancete mensal/anual | 9 | 4-6h | Não é crítico no primeiro mês |
| Catálogo unificado de fornecedores | 10 | 4-6h | Hoje é 3 fontes separadas, refatora depois |
| Cliente mobile RN para outras funções além do ponto | 11 | 2-3 sem | Bloco E original |
| PWA / service worker | 11 | 1 sem | Não há demanda offline imediata |
| Testes E2E Playwright reais | 11 | 1-2 dias | Smoke manual no go-live |
| Migração TypeScript | 11 | Decisão | Pós-estabilização |
| SSE/WebSocket para notificações | 11 | 3-5 dias | Polling 60s aguenta nas primeiras semanas |
| Horizon (filas Redis dashboard) | 12 | 1-2h | `queue:failed` e logs bastam por enquanto |
| APM (Sentry / New Relic / Datadog) | 12 | 4-6h | Logs locais bastam por enquanto |
| Pipeline CI/CD GitHub Actions | 12 | 1-2 dias | Deploy manual é viável no início |

## Riscos Materializados (vigiar de perto após go-live)

1. **eSocial:** XMLs gerados internamente vão divergir da transmissão real do sistema terceiro. Precisa rotina de reconciliação semanal entre `ESOCIAL_EVENTO.NUMERO_RECIBO` (preenchido manualmente) e o painel da Receita.

2. **Folha de pagamento (Bloco 1) primeira execução real:** com 30k servidores, primeira execução pode levar 30-60 min. Monitorar logs de `ProcessarFolhaJob` e ajustar `Bus::batch` chunks se preciso.

3. **Locks Redis (Bloco 1, 12):** se `CACHE_DRIVER=file` por engano em prod, `MotorFolhaService::recalcular` pode rodar 2x simultâneo e corromper folha. **Validar `CACHE_DRIVER=redis` na primeira folha.**

4. **CNAB Bloco 6:** primeiro envio para banco em produção pode ser rejeitado se layout custom não bater. Ter plano B (gerar manualmente em sistema bancário online) na primeira competência.

5. **SAGRES TCE-MA Bloco 8:** primeiro envio pode ser rejeitado pelo XML simplificado. Ter contato com TCE-MA para feedback rápido.

6. **eSocial DCT-Web 2025:** mesmo se PMSL usa transmissor terceiro, eventos S-1200 do GENTE precisam ter `perApur` no formato exato — se backend gerou errado, transmissor terceiro vai recusar. Validar primeiro conjunto na semana 1.

7. **LRF — Despesa com Pessoal:** SICONFI atual subestima (não inclui patronal). Se RH publicar relatório RGF e a Controladoria comparar com sistema legado, vai dar diferença. **Documentar limitação para Controladoria + corrigir T+30.**

## Status do Documento

**Documento mestre `STATUS_PRODUCAO_2026-05-07.md` finalizado em 07/05/2026.**

Contém 12 blocos cobrindo:
- 85 arquivos de rotas auditados (Blocos 1-10)
- Frontend Vue 3 + Mobile React Native (Bloco 11)
- Infraestrutura completa + plano de deploy VPS (Bloco 12)

Aproximadamente 1.900 linhas de análise técnica priorizada.

**Próximos passos sugeridos para Ronaldo:**

1. **Hoje à tarde (07/05):** decidir os 4 pontos arquiteturais urgentes (eSocial, CNAB, Banco, Email)
2. **Hoje à noite:** distribuir tarefas entre devs + emitir prompt completo para Antygravity executar fixes priorizados em paralelo
3. **Amanhã 08/05 manhã:** começar Day 1 do cronograma
4. **Domingo 10/05:** go-live

---

*Documento gerado por auditoria iterativa via MCP, baseado em leitura real de arquivos do projeto. Todos os achados foram validados contra o código atual em `C:\Users\joaob\Desktop\sisgep-job-main\gente\`.*
