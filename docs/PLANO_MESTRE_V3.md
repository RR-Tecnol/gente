# GENTE v3 — Plano Mestre
**Versão:** 3.1 | **Data:** 23/03/2026 | **Autor:** Gravity (auditoria) + Ronaldo (decisões)
**Empresa:** RR TECNOL | **Cliente:** Prefeitura Municipal de São Luís / MA

---

## O que é o GENTE

ERP Municipal completo integrado em quatro camadas:

1. **RH + Folha** — núcleo operacional, já funcional
2. **Saúde, Segurança e Desenvolvimento** — medicina do trabalho, treinamentos, avaliação, benefícios
3. **Gestão de Consignatárias** — integração multi-operadora de crédito consignado
4. **ERP Financeiro + Administrativo** — Orçamento, Contabilidade PCASP, Tesouraria, Compras, Almoxarifado, Patrimônio, Frotas, SAGRES/TCE-MA

O diferencial central é a **integração nativa entre todas as camadas**: fechamento da folha gera
lançamentos PCASP automaticamente; empenho de compra atualiza o saldo orçamentário em tempo real;
entrada no almoxarifado dispara liquidação da despesa.

---

## Estado atual (23/03/2026)

### ✅ Completo e funcional

| Módulo | View | Backend |
|--------|------|---------|
| Login, auth, dashboard | ✅ | ✅ |
| Funcionários CRUD + Perfil | ✅ | ✅ |
| Motor de folha (E2E R$24.743) | ✅ | ✅ |
| Holerite PDF | ✅ | ✅ |
| Consignação (30%+10%) | ✅ | ✅ |
| Progressão funcional | ✅ | ✅ |
| RPPS/IPAM | ✅ | ✅ |
| Organograma accordion | ✅ | ✅ |
| Sidebar com busca | ✅ | — |
| Autocadastro / Recadastramento | ✅ | ✅ |
| Exoneração, Hora Extra, Verba Indenizatória | ✅ | ✅ |
| Diárias, Férias/Licenças, Abono de Faltas | ✅ | ✅ |
| Estagiários, PSS, Terceirizados, Acumulação | ✅ | ✅ |
| Transparência Pública, SAGRES/TCE-MA | ✅ | ✅ |
| Banco de Horas, Atestados Médicos | ✅ | ✅ |
| Contratos e Vínculos | ✅ | ✅ |
| Gestão de Declarações | ✅ | ✅ |
| Portal do Gestor | ✅ | ✅ |
| Escala de Trabalho (3 views) | ✅ | ✅ |
| eSocial (UI + backend parcial) | ✅ | ⚠️ XML inválido |
| Proporcional de salário (admissão/exoneração no mês) | — | ✅ Concluída — TASK-A0 |
| Config ponto por funcionário (PONTO_CONFIG_FUNCIONARIO) | ✅ UI + tabela + endpoints | 🔴 ApuracaoPontoService ignora config — TASK-PONTO-CONFIG |
| Depreciação de bens patrimoniais (NBCASP 16.9) | — | 🔴 Não existe — TASK-D3 |
| App mobile (ponto + face rec + geoloc) | ✅ | ✅ |

### 🟡 View existe, backend incompleto ou stub

| Módulo | View | Situação |
|--------|------|---------|
| Avaliação de Desempenho | ✅ AvaliacaoDesempenhoView (servidor) | 🔴 AvaliacaoGestorView (gestor) não existe — TASK-A1b |
| Benefícios | ✅ BeneficiosView (servidor) | 🔴 BeneficiosAdminView (RH) não existe — TASK-A2b |
| Medicina do Trabalho | ✅ MedicinaTrabalhoView (servidor) | 🔴 MedicinaAdminView (SESMT) não existe — TASK-A3b |
| Segurança do Trabalho | ✅ SegurancaTrabalhoView (servidor) | 🔴 SegurancaAdminView (SESMT) não existe — TASK-A4b |
| Treinamentos | ✅ TreinamentosView (servidor) | 🔴 TreinamentosAdminView (RH) não existe — TASK-A5b |
| Pesquisa de Satisfação | ✅ PesquisaSatisfacaoView | ✅ PesquisaAdminView — completo |
| Ouvidoria | ✅ OuvidoriaView | ✅ OuvidoriaAdminView — completo |
| Relatórios Central | ✅ RelatoriosView | Backend stub |
| ERP Orçamento | ✅ OrcamentoView | Backend stub |
| ERP Execução Despesa | ✅ ExecucaoDespesaView | Backend stub |
| ERP Contabilidade PCASP | ✅ ContabilidadeView | Backend stub |
| ERP Tesouraria | ✅ TesourariaView | Backend stub |
| ERP Receita Municipal | ✅ ReceitaMunicipalView | Backend stub |
| ERP Controle Externo | ✅ ControleExternoView | Backend stub |
| Remessa CNAB 240 | ✅ RemessaCnabView | Backend stub |
| Contra-Cheque (funcionário) | ✅ ContraChequeView | ✅ Funcional |

### 🔴 Planejado, sem view e sem backend

| Módulo | Origem |
|--------|--------|
| Gestão de Consignatárias | Renomeado do Neoconsig — multi-operadora |
| Compras e Licitações | Plano original ERP |
| Almoxarifado / Estoque | Plano original ERP |
| Patrimônio | Plano original ERP |
| Gestão de Contratos | Plano original ERP |
| Gestão de Frotas | Plano original ERP |
| App: tela holerites + escala | App mobile |

---

## Sequência de execução (24/03/2026 →)

```
BLOCO S — Segurança ✅ CONCLUÍDO (30/03/2026)
  S1.  SecurityHeaders middleware ✅
  S2.  reCAPTCHA v3 no login ✅
  S3.  Bloqueio por IP (5 tentativas → 15 min) ✅
  S4.  Política de senha mínima ✅
  S5.  ValidateFileUpload middleware ✅
  S6.  Timeout de sessão + redirect 401 ✅
  S7.  CORS produção ✅
  S8.  DOMPurify em v-html ✅
  S9.  Varredura SQL injection ✅
  S10. Log de segurança expandido ✅
  S10b. Arquivamento mensal security.log ✅
  Spec completa: docs/SPRINT_SEGURANCA.md

TASK-00 — Fix rápido
  ConsignacaoView subtítulo 5%→10%

TASK-A0 — Proporcional de salário (BUG crítico)
  FolhaParserService.php — detectar FUNCIONARIO_DATA_INICIO/FIM no mês de competência

BLOCO A — RH complementar (views já prontas, backends + views admin faltam)
  A1.  Avaliação de Desempenho — backend
  A1b. AvaliacaoGestorView.vue — gestor avalia subordinado (view admin FALTANDO)
  A2.  Benefícios — backend
  A2b. BeneficiosAdminView.vue — RH aprova, gere catálogo (view admin FALTANDO)
  A3.  Medicina do Trabalho + Segurança do Trabalho — backend
  A3b. MedicinaAdminView.vue — SESMT registra ASOs, vê vencidos (view admin FALTANDO)
  A4b. SegurancaAdminView.vue — SESMT gere EPIs, CATs, laudos (view admin FALTANDO)
  A4.  Treinamentos e Capacitações — backend
  A5b. TreinamentosAdminView.vue — RH cadastra cursos, emite certificados (view admin FALTANDO)
  A5.  Pesquisa de Satisfação + Ouvidoria — backend
  A6.  Central de Relatórios — backend
  A7.  eSocial XML válido (S-1200, S-2200, S-2206, S-2299)

BLOCO B — Gestão de Consignatárias (novo módulo multi-operadora)
  B1. Tabelas CONSIGNATARIA + LAYOUT + REMESSA
  B2. 6 endpoints (importar, gerar, histórico, margem)
  B3. View ConsignatariasView.vue

BLOCO C — ERP Financeiro (views já prontas, backends faltam)
  C1. Orçamento (LOA/PPA/Execução)
  C2. Execução da Despesa (empenho→liquidação→pagamento)
  C3. Contabilidade PCASP + integração folha ← núcleo
  C4. Tesouraria (contas + CNAB 240)
  C5. Receita Municipal
  C6. SAGRES/TCE-MA backend real
  C7. Controle Externo (LRF)

BLOCO D — ERP Administrativo (sem views — criar tudo)
  D1. Compras e Licitações
  D2. Almoxarifado / Estoque
  D3. Patrimônio + DepreciacaoService (NBCASP 16.9)
  D4. Gestão de Contratos Administrativos
  D5. Gestão de Frotas

BLOCO E — App Mobile
  E1. Tela holerites (endpoint já existe)
  E2. Tela escala de trabalho

BLOCO F — Infraestrutura (último)
  F1. VPS Ubuntu + HTTPS + dados reais PMSLz
```

---

## Decisões arquiteturais fixadas

| Decisão | Valor |
|---------|-------|
| Sistema | GENTE (não SISGEP) |
| Cliente | PMSLz — São Luís / MA |
| RPPS | IPAM |
| TCE | TCE-MA — SAGRES / SINC-Folha |
| Margem consignável | 30% empréstimos + 10% cartão (Decreto 57.477/2021) |
| Auth web | Sessão Laravel — não JWT |
| Auth mobile | JWT exclusivo (`ponto_app.php`) |
| PCASP | MCASP 8ª edição (STN) — obrigatório por lei |
| CNAB | 240 posições (Febraban) |
| Consignatárias | Multi-operadora via LAYOUT_CONSIGNATARIA parametrizado |
| eSocial XML | Gerar XML válido — adiar envio real ao governo para pós-VPS |

---

## Documentos de referência

| Arquivo | Para quê |
|---------|---------|
| `docs/SPRINT_EXECUCAO_V3.md` | Spec técnica completa para o Antygravity (Blocos A–F) |
| `docs/SPRINT_SEGURANCA.md` | Spec completa Sprint Segurança (SEC-PROD-01 a 10) |
| `docs/GAPS_ESTRATEGICOS.md` | Gaps críticos: 13º, férias, rescisão, DIRF, RAIS, SICONFI, painel executivo |
| `docs/MAPA_ESTADO_REAL.md` | Estado confirmado do código — fonte de verdade |
| `.agent/workflows/regras-gerais.md` | Regras obrigatórias — ler antes de qualquer ação |
| `docs/MAPA_CAMPOS_TABELAS.md` | Colunas reais — consultar antes de qualquer INSERT |
| `docs/PLANO_SPRINTS.md` | Spec técnica Neoconsig (TASK-10a–f) |
| `PLANO_IMPLEMENTACAO_GENTE_V3.md` | Plano original completo com bugs e gaps resolvidos |

*RR TECNOL | São Luís — MA | 23/03/2026*
