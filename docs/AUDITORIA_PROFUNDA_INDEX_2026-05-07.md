---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/index-mestre
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Índice mestre — auditoria profunda em 7 etapas (07/05/2026)"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
total_relatorios: 7
total_riscos: 81
total_arquivos_auditados: 60+
total_linhas_codigo_auditado: "~13.000"
---

# AUDITORIA PROFUNDA GENTE v3 — ÍNDICE MESTRE

> Documento de navegação cruzada entre os 7 relatórios da auditoria profunda. Use este índice para localizar rapidamente um achado, um risco ou uma área do projeto.

## 1. Os 7 relatórios

| # | Etapa | Escopo | Linhas auditadas | Arquivos | Achados |
|---|---|---|---:|---:|---:|
| 1 | Inventário e arqueologia da raiz | Scripts PHP da raiz, lixo de sessão, SQL injection scan inicial, migration `patrimonio` | ~315 | 9+ | 6 riscos |
| 2 | Motor de folha completo | `MotorFolhaService`, `FolhaParserService` legado, `ContabilidadeService`, `ContraChequeService`, `RescisaoService`, `FeriasService`, `DecimoTerceiroService` | ~2.500 | 8 | 16 riscos |
| 3 | Camada Shadow + Smoke + Import | `ShadowDiffChunkJob`, `SnapshotManifestoCanonicoService`, `SmokeTeiaFolhaRunner`, `SisfolhaImportOrchestrator`, `FolhaParserService`, `AfdParserService` | ~2.700 | 7 | 14 riscos |
| 4 | PCCV + Progressão + Jornada + Ponto | `PccvValidatorService`, `ProgressaoFuncionalElegibilidadeService`, `ProgressaoFuncionalListagemService`, `JornadaRegraParametros`, `ApuracaoPontoService`, `VinculoEnum` | ~1.220 | 7 | 14 riscos |
| 5 | Periféricos | `TabelasImpostoService`, `ConsigGeradorService`, `ConsigParserService`, `EsocialXmlService`, `CNAB240Builder`, `RemessaBancariaService`, `DepreciacaoService`, `FeriadoService`, `HolidayCalendarService`, `DashboardOperacionalService` | ~1.716 | 10 | 18 riscos |
| 6 | Models + Database + Domain | `Funcionario`, `Folha`, `DetalheFolha`, `Lotacao`, `Vinculo`, `Cargo`, `Evento`, Domain Escala (5 arquivos), 3 migrations representativas, 37 seeders catalogados | ~2.435 | 15 + listagens | 9 riscos |
| 7 | Rotas + Controllers + Frontend + Tests + VEREDICTO | `routes/folha.php`, `motor.php`, `hora_extra.php`, `cnab.php`, `esocial.php`, `ponto_eletronico.php`, `web.php`, `api_v3_auth_part2.php`, tests, frontend Vue 3 | ~5.745 | 9 | 4 riscos + veredicto |
| **Total** | — | — | **~16.600** | **~65+** | **81 riscos** |

---

## 2. Cruzamento por tópico

### 2.1 SEGURANÇA

| Tópico | Onde encontrar | ID |
|---|---|---|
| SQL Injection em routes/comunicados.php | Etapa 1 §3 | R3 |
| SQL Injection em Lotacao::getDadosRelatorioImprimirLotacao | Etapa 6 §3.1 | **R69** |
| Senha aleatória em setUsuario (correção pós-Sprint S) | Etapa 6 §4.1 | ✅ |
| RBAC 3-camadas (Sudo + granular + perfis) | Etapa 6 §5.5 | ✅ |
| Audit chain criptográfico (HASH_CONCAT) | Etapa 6 §6.3 + Etapa 5 §3.10 | ✅ |
| PII protection (pessoa_cpf_hash) | Etapa 6 §6 (migrations) | ✅ |
| Honeytokens | Etapa 6 §7 (seeders) | ✅ |
| Path debug hardcoded /home/DK/ | Etapa 6 §5.4 | R72 |
| Login attempts + reCAPTCHA + lockout | Etapa 7 §1 (web.php) | ✅ |

### 2.2 BUGS FISCAIS

| Tópico | Onde encontrar | ID |
|---|---|---|
| INSS RGPS 2024 (R$ 1.412) em TabelasImpostoService | Etapa 5 §2.1 | **R51** |
| FOLHA_VALOR_TOTAL cast 'integer' perde decimal | Etapa 6 §3.2 | **R70** |
| FUNCIONARIO_DATA_ADMISSAO inexistente lido por ContraChequeService | Etapa 2 §análise + Etapa 6 §2.2 | R14 |
| ContabilidadeService incompleto e não-idempotente | Etapa 2 §riscos | R7-R10 |
| RescisaoService sem férias proporcionais no IRRF | Etapa 2 §riscos | R16 |
| DecimoTerceiroService divisão por 12 inteiro | Etapa 2 §riscos | R12 |
| FeriasService promete DETALHE_FOLHA mas não gera | Etapa 2 §riscos | R20 |
| VinculoEnum incompleto (4 vs 5+ tipos no motor) | Etapa 4 §3.1 | **R38** |
| Patronal 14% hardcoded em ContabilidadeService | Etapa 2 §riscos | R8 |
| Empresa hardcoded "PREFEITURA MUNICIPAL DE TESTE" | Etapa 2 + Etapa 5 (CNAB) | R11, R59 |

### 2.3 COMPATIBILIDADE SQL SERVER (dívida sistemática)

| Local | Padrão problemático | ID |
|---|---|---|
| routes/motor.php | `PRAGMA table_info` SQLite-only | R1 |
| FolhaParserService | `strftime`, `julianday` | R23 |
| FolhaParserService | N+1 puro | R24 |
| ApuracaoPontoService | `whereYear`/`whereMonth` em datetime | **R40** |
| ProgressaoFuncionalListagemService | crase MySQL `p.\`PESSOA_CPF\`` | **R39** |
| DepreciacaoService | `strftime` | **R56** |
| DashboardOperacionalService | `date(col, '+N days')` | **R57** |
| Migration audit_log_hash_concat | `after('id')` mysql-only | R77 |

**8+ ocorrências espalhadas em 6+ serviços.** Resumo no veredicto final §6.4.

### 2.4 INTEGRAÇÕES EXTERNAS QUEBRADAS OU PROTOTIPADAS

| Sistema | Estado | IDs |
|---|---|---|
| **eSocial** | Protótipo com 6 bugs catastróficos: query quebrada em S-1200, tpAmb=1 produção, dados fake hardcoded em S-2200, perApur formato errado, CNPJ PMSL hardcoded em 4 lugares, indRetif=1 sempre original | **R52-R55, R59-R60** |
| **CNAB 240** | 3 implementações: `CNAB240Builder` (admite "mock"), `RemessaBancariaService` (sério mas não chamado), `routes/cnab.php` (formato custom non-FEBRABAN, em uso) | **R58, R61-R63, R78** |
| **Sisfolha import** | Excelente, exceto `primeiroVinculoId` retorna primeiro indistintamente | R26 |
| **Consignação NEOCONSIG** | Bom — layout configurável, parser robusto, 1 ressalva (rubrica '0099' default) | R67-R68 |
| **AFD (ponto biométrico)** | Heurístico (índice%4) | R28-R36 |

### 2.5 MOTORES DE FOLHA (3 caminhos)

| Motor | Local | Status | ID |
|---|---|---|---|
| `MotorFolhaService` (PHP novo, C1/C2/C3) | `app/Services/MotorFolhaService.php` | ✅ Em uso confirmado (Etapa 7 §2.4) | — |
| `FolhaParserService` (PHP legado) | `app/Services/FolhaParserService.php` | Sem rota chamando | — |
| `Folha::salvaFolha/processarFolha/reprocessarFolha` | `app/Models/Folha.php` (T-SQL `sp_gera_folha`) | Sem rota direta no `folha.php`; pode estar em CRUD legado Vue 2 | **R71** |

### 2.6 PONTO ELETRÔNICO

| Tópico | Onde | ID |
|---|---|---|
| ApuracaoPontoService::fechar é stub | Etapa 4 §análise + confirmado Etapa 7 §2.2 | **R37** |
| Hora extra chega via lançamento manual em UI | Etapa 7 §2.2 | R37 confirmado |
| BUG-PONTO-01 corrigido (intervalo de almoço) | Etapa 4 §análise | ✅ |
| BUG-HE-01 corrigido (CARGO_CARGA_HORARIA real) | Etapa 7 §2.2 | ✅ |
| 4 batidas vs 2 batidas suportado | Etapa 4 §análise | ✅ |
| `whereYear`/`whereMonth` full scan SQL Server | Etapa 4 §análise | R40 |

### 2.7 ESCALA — ARQUITETURA EXEMPLAR

| Componente | Onde | Avaliação |
|---|---|---|
| `EscalaWorkflowStatus` (4 estados curtos) | Etapa 6 §5.1 | ✅ Honesto sobre dívida VARCHAR(20) |
| `MotivoAlteracaoEscala` (6 motivos + base legal) | Etapa 6 §5.2 | ★★★★★ Padrão raro |
| `MotivoAlteracaoPolicy` (domínio prevalece sobre BD) | Etapa 6 §5.3 | ★★★★★ |
| `EscalaAusenciaService` (schema-tolerância radical) | Etapa 6 §5.4 | ✅ exceto path debug R72 |
| `EscalaWorkflowService` (592 linhas, RBAC 3-camadas, audit chain) | Etapa 6 §5.5 | ★★★★★ A melhor peça do projeto |
| Tests: `EscalaWorkflowServiceTest` | Etapa 7 §2.6 | ✅ |

### 2.8 CAMADA SHADOW — HOMOLOGAÇÃO CRUZADA

| Componente | Onde | Avaliação |
|---|---|---|
| `ShadowDiffChunkJob` (BCMath, 4 classificações) | Etapa 3 §análise | ★★★★★ |
| `SnapshotManifestoCanonicoService` (SHA-256, hash_equals) | Etapa 3 §análise | ★★★★★ |
| `SmokeTeiaFolhaRunner` (honesto sobre LM→Motor não ligado) | Etapa 3 §análise | ✅ |
| Migration `SHADOW_RUN` + `SHADOW_CHECKPOINT` | Etapa 6 §6.1 | ✅ Idempotência por IDEMPOTENCY_KEY |
| Test: `ShadowSmokeE2eFixtureTest` | Etapa 7 §2.6 | ✅ |
| Test: `SnapshotManifestoCanonicoServiceTest` | Etapa 7 §2.6 | ✅ |

### 2.9 PCCV + PROGRESSÃO

| Componente | Onde | Avaliação |
|---|---|---|
| `PccvValidatorService` (cache, semana ISO, normalização carga) | Etapa 4 §análise | ★★★★★ |
| `ProgressaoFuncionalElegibilidadeService` (cache triplo) | Etapa 4 §análise | ★★★★ |
| `ProgressaoFuncionalListagemService` (keyset + LRF) | Etapa 4 §análise | ★★★★★ exceto crase MySQL R39 |
| `JornadaRegraParametros` (vigência histórica) | Etapa 4 §análise | ★★★★ |
| Test: `JornadaRegraParametrosPureTest` | Etapa 7 §2.6 | ✅ |
| Test: `ProgressaoFuncionalListagemCacheTest` | Etapa 7 §2.6 | ✅ |

### 2.10 ROTAS

| Tópico | Onde | ID |
|---|---|---|
| Bug Antygravity (rotas inline em web.php L1850) | Etapa 7 §2.5 — RESOLVIDO via agregador Python | ✅ |
| `regen_api_v3_fachada.py` agregador | Etapa 7 §2.1 | R80 |
| 85 arquivos modulares de rotas | Etapa 7 §1 | — |
| `routes/cnab.php` formato custom non-FEBRABAN | Etapa 7 §2.3 | R78 |
| `web.php` — bloco autorizado limpo | Etapa 7 §2.5 | ✅ |

### 2.11 TESTS

| Cobertura | Onde | Status |
|---|---|---|
| 7 Feature tests + 13 Unit tests | Etapa 7 §2.6 | ✅ |
| Domain Escala coberto | Etapa 7 §2.6 | ✅ |
| RBAC 4 variações testadas | Etapa 7 §2.6 | ✅ |
| Shadow E2E + Sisfolha runner | Etapa 7 §2.6 | ✅ |
| `MotorFolhaServiceTest` faltante | Etapa 7 §2.6 | **R79** |
| `RescisaoServiceTest`, `FeriasServiceTest`, `DecimoTerceiroServiceTest` faltantes | Etapa 7 §2.6 | **R79** |

---

## 3. Os 81 riscos por severidade

### 3.1 ALTO 🔴 (24 riscos)

R1, R7, R8, R9, R10, R23, R24, R25, R26, R37, R38, R39, R40, R51, R52, R53, R54, R55, R56, R57, R58, R69, R70, R71

**Distribuição:**
- 7 SQLite-only / SQL Server incompatibility
- 4 eSocial protótipo
- 3 Motor de folha (incompleto, descoberto, novo desconectado)
- 4 ContabilidadeService
- 2 SQL injection real (R3 médio + R69 alto)
- 1 Bug fiscal real (INSS RGPS 2024)
- 1 Cast integer perde decimal
- 1 ApuracaoPonto stub
- 1 VinculoEnum incompleto

### 3.2 MÉDIO 🟡 (35 riscos)

Distribuídos em 7 etapas. Inclui code smells, padrões frágeis, hardcodes não-críticos, lacunas de tests, dependências de schema consistente.

### 3.3 BAIXO 🟢 (22 riscos)

Refactor pós-PoC, otimizações, padronizações.

---

## 4. Top 10 ações priorizadas

Ver `AUDITORIA_PROFUNDA_ETAPA_07_VEREDICTO_FINAL_2026-05-07.md` §4.2 e §5.

**Resumo:**
- **5 ações urgentes** pré-PoC (~15 min de código + 1 decisão arquitetural)
- **7 ações** pré-go-live SQL Server (1-2 semanas de trabalho focado)
- **3 melhorias de processo** (documentação `regen_api_v3_fachada.py`, CI, validações com SEMAD)

---

## 5. Veredicto final em uma frase

**A arquitetura GENTE v3 não foi afundada.** O motor de 3 camadas está intacto, foi protegido por uma camada Shadow exemplar, e a equipe construiu um Programa de Prontidão Operacional sólido entre 30/03 e 28/04. Os 24 riscos ALTO 🔴 são localizados, identificáveis e tratáveis. **PoC viável com 15 minutos de fixes urgentes; go-live em SQL Server requer 1-2 semanas de fechamento focado.**

Ver veredicto completo em `AUDITORIA_PROFUNDA_ETAPA_07_VEREDICTO_FINAL_2026-05-07.md` §6.

---

## 6. Como usar este índice

1. **Para localizar um risco específico (R##):** procure o ID na seção 2 (cruzamento por tópico) ou no relatório da etapa correspondente
2. **Para entender uma área do projeto:** vá direto à seção 2 relevante (ex.: 2.7 Escala, 2.8 Shadow)
3. **Para tomar decisões pré-PoC:** veja `AUDITORIA_PROFUNDA_ETAPA_07_VEREDICTO_FINAL_2026-05-07.md` §5.1
4. **Para planejar go-live:** veja `AUDITORIA_PROFUNDA_ETAPA_07_VEREDICTO_FINAL_2026-05-07.md` §5.2
5. **Para apresentar a auditoria a Ronaldo/SEMAD:** comece pelo veredicto final §6 e use as tabelas do §4 deste índice

---

*Índice mestre concluído em 07/05/2026.*
*Auditoria profunda: 7 relatórios, 81 riscos mapeados, ~16.600 linhas de código auditadas em ~65 arquivos.*
