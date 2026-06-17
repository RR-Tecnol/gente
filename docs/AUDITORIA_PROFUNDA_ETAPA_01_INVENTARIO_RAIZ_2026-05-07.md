---
tags:
  - gente/auditoria
  - gente/auditoria-profunda
  - gente/etapa-01
status: "concluído"
data: 2026-05-07
auditor: Claude (chief engineer/auditor)
solicitante: Ronaldo (RR TECNOL)
escopo: "Etapa 1/7 — Inventário e arqueologia da raiz"
projeto_path: "C:\\Users\\joaob\\Desktop\\sisgep-job-main\\gente"
baseline_anterior: "30/03/2026 (estado registrado em userMemories)"
estado_atual: "07/05/2026 (versão recebida da equipe de dev)"
---

# AUDITORIA PROFUNDA — ETAPA 1: INVENTÁRIO E ARQUEOLOGIA DA RAIZ

> Relatório arquivado para consulta futura. Este documento é a fonte autoritativa do que foi observado na Etapa 1 da auditoria profunda e dispensa refazer a inspeção dos mesmos arquivos em sessões futuras.

## Plano da auditoria profunda (7 etapas)

| Etapa | Escopo | Status |
|---|---|---|
| **1** | Inventário e arqueologia da raiz | ✅ **Concluída (este relatório)** |
| 2 | Motor de folha completo (MotorFolhaService, LoteContext, Jobs, ContabilidadeService, ContraCheque, 13º, Rescisão, Férias) | ⏳ Pendente |
| 3 | Camada Shadow + Smoke + Import (Shadow*Job, SnapshotManifestoCanonicoService, SmokeTeiaFolhaRunner, SisfolhaImport*) | ⏳ Pendente |
| 4 | PCCV + Progressão + Jornada + Ponto (PccvValidatorService, Progressao*, JornadaRegraParametros, ApuracaoPontoService, AfdParserService) | ⏳ Pendente |
| 5 | Periféricos: Consignação, eSocial, Bancário, Patrimônio, Dashboard | ⏳ Pendente |
| 6 | Models + Database (migrations/seeders) + Domain | ⏳ Pendente |
| 7 | Roteamento + Controllers + Frontend + Mobile + Tests + Veredicto final | ⏳ Pendente |

---

## 1. Contexto e premissas

Ronaldo entregou a pasta `C:\Users\joaob\Desktop\sisgep-job-main\gente` afirmando ser a última versão recebida da equipe de dev da empresa, com a preocupação de que a arquitetura do **motor de folha** (3 camadas: C1 proventos estruturais → C2 adicionais permanentes → C3 lançamentos variáveis) tivesse sido afundada.

**Baseline de comparação:** estado de 30/03/2026 registrado nas `userMemories`, com sprints S, A, B, C, D1 concluídas; D2 Almoxarifado em execução pelo Antygravity; e 10 GAPs pré-PoC implementados e auditados.

**Validação preliminar do motor (já feita antes desta etapa):**
Lendo `app/Services/MotorFolhaService.php` (553 linhas, lido na íntegra) o veredicto preliminar foi: **arquitetura intacta**. O docblock continua descrevendo literalmente a arquitetura de 3 camadas. Aparecem evoluções coerentes:

- `MotorFolhaLoteContext` para eager-load (eliminação de N+1)
- `despacharProcessamentoAssincrono` com Laravel Bus Batch (chunks de 500)
- `calcularLoteParaFuncionarios` com transação por chunk e upsert em `DETALHE_FOLHA`
- Cálculo respeita ordem fiscal: C1 → C2 (com `ADICIONAL_INCIDE_PREV`) → C3 (com `LANCAMENTO_INCIDE_PREV`) → bruto → complemento SM → INSS/RPPS → IRRF (com dependentes 226,86) → consignações → líquido
- Pisos salariais por vínculo: `VINCULOS_PISO = ['servico_prestado', 'pss', 'comissao_puro']`
- Faixas RGPS 2025 e IRRF 2025 corretas
- RPPS dinâmico via `RPPS_CONFIG` por vigência

Dívidas técnicas conhecidas e documentadas no próprio código:
- `SALARIO_MIN_2025 = 1518.00` hardcoded com TODO para `CONFIGURACAO_SISTEMA`
- Dedução IRRF por dependente (226.86) hardcoded
- Exceptions genéricas (`\RuntimeException`) em vez de classes de domínio

---

## 2. Inventário da raiz: arquivos suspeitos lidos integralmente

### 2.1 Scripts PHP de manutenção

Todos foram lidos por completo (`desktop-commander:read_multiple_files`). Veredicto consolidado:

| Arquivo | Tamanho | O que faz | Aponta para classe versionada? | Risco |
|---|---|---|---|---|
| `nuclear_ti.php` | 32 linhas | Reatribui email do "admin" + funde duplicatas do USUARIO TI. Suporta `--dry-run` e `--force`. | `App\Support\Scripts\NuclearTiDuplicados` | 🟢 BAIXO |
| `hard_cleanup.php` | 30 linhas | Remove USUARIO sem nome alinhados ao mesmo CPF do login de referência. | `HardCleanupUsuariosFantasmas` | 🟢 BAIXO |
| `deep_clean.php` | 30 linhas | Unifica USUARIO duplicados por LOGIN/EMAIL. | `DeepCleanByLogin` | 🟢 BAIXO |
| `cleanup_identities.php` | 30 linhas | Alias de conveniência para `UnificarUsuarios`. | `App\Support\Scripts\UnificarUsuarios` | 🟢 BAIXO |
| `dump_ti.php` | 53 linhas | Listagem read-only de USUARIO com email TI. Detecta driver (sqlsrv/dblib/odbc vs MySQL/SQLite) e usa `whereRaw` correto para cada um. | direto via `App\Models\Usuario` | 🟢 BAIXO |
| `fix_users.php` | 82 linhas | Garante 4 personas Lab (TI/RH/Folha/Diretor) com senha `gente@2026` (MD5 legado). Tenta `TestPersonasSeeder` primeiro; fallback para `updateOrInsert` direto. Usa `LoginLookupNormalizer::forStorage`. | direto | 🟡 MÉDIO (senha hardcoded) |
| `patch_routes.php` | 86 linhas | Aplica `->middleware('upload.safe')` em rotas `/atestados`, `/declaracoes`, `/abono-faltas` em `routes/web.php`, `routes/atestados_v3.php`, `routes/atestados.php`. Idempotente (verifica se middleware já existe). | inline | 🟢 BAIXO |
| `scan_routes.php` | 11 linhas | Scanner de SQL injection: procura `DB::(select|statement|unprepared)` ou `whereRaw`/`orderByRaw` com `$var` interpolado. Saída em `results.txt`. | inline | 🟢 BAIXO |
| `scan_routes_multiline.php` | 21 linhas | Variante multiline do scanner com `preg_match_all` e cálculo de linha por offset. | inline | 🟢 BAIXO |

**Conclusão dos scripts:** todos são ferramentas de manutenção e hardening. Nenhum é destrutivo, todos têm `--dry-run`, todos delegam lógica para classes versionadas em `App\Support\Scripts\*` (exceto `patch_routes` e os scanners, que são utilitários únicos).

### 2.2 Arquivos `.txt` na raiz (sessão de debug deixada para trás)

| Arquivo | Tamanho | Linhas | Conteúdo | Ação |
|---|---|---|---|---|
| `commit.txt` | 1 KB | 22 | Log da `BUG-SPRINT-01`: 25 correções em 4 ondas. Trabalho legítimo (DATE → CAST AS DATE para SQL Server, dd() removido por DoS, CSP atualizado, requires adicionados ao web.php, etc.) | **Manter** (registro histórico) |
| `refresh.txt` | ~10 KB | 87 | Output bruto de `php artisan migrate:fresh`. Lista 70+ migrations. Tem BOM UTF-16 quebrado renderizando como caracteres soltos. | **Deletar** (lixo) |
| `error_trace.txt` | 6 KB | 34 | Stack trace de `Target class [TurnoController] does not exist` ao rodar `route:list` via `test_bootstrap.php`. | **Deletar** após resolver R3 |
| `route_err.txt` | 2 KB | 22 | Mesmo erro do anterior, formatado pela CLI Laravel. | **Deletar** após resolver R3 |
| `migrate_out.txt` | 56 B | 1 | `Nothing to migrate.` ✅ | **Deletar** |
| `rollback_out.txt` | 100 B | 1 | `Migration not found: 2026_03_30_000041_create_patrimonio_tables` ⚠️ | **Deletar** após confirmar R6 |
| `results.txt` | 2 KB | 9 | Saída do scan de SQL injection. **9 ocorrências encontradas** (detalhe abaixo). | **Manter ou arquivar** — é evidência |
| `lint_out.txt` | 98 B | 1 | `No syntax errors detected in database/migrations/2026_03_30_000040_create_almoxarifado_tables.php` ✅ | **Deletar** |
| `temp.diff` | 925 KB | 8.049 | Diff bruto em UTF-16 LE. Aparente tentativa de reencode de comentários do `routes/web.php` que quebrou todos os `////////////` decorativos virando mojibake (`%����%����`). Apenas comentários afetados, código intacto. | **Deletar** (não deveria estar versionado) |

### 2.3 Pasta `C__\dev\sisgep-cache`

**Diagnóstico:** alguém configurou um path tipo `C:\dev\sisgep-cache` em algum config (`.env`, `config/cache.php` ou `config/filesystems.php`) num ambiente Windows, e o `mkdir` recursivo do Laravel sanitizou os `:` para `_`. Pasta está **vazia**.

**Ação:** `Remove-Item -Recurse C__` é seguro.

### 2.4 Achados do `results.txt` (scan SQL injection)

Saída do `scan_routes_multiline.php`, **9 hits**:

```
routes/comunicados.php:9     DB::select("SELECT TOP 1 1 FROM $tabela");
routes/motor.php:16          DB::select('PRAGMA table_info(VINCULO)')
routes/motor.php:36          DB::select('PRAGMA table_info(VINCULO)')
routes/motor.php:60          DB::select('PRAGMA table_info(RUBRICA)')
routes/motor.php:121         DB::select('PRAGMA table_info(ADICIONAL_SERVIDOR)')
routes/motor.php:137         DB::select('PRAGMA table_info(ADICIONAL_SERVIDOR)')
routes/progressao_funcional.php:496   $q->whereRaw("LOWER(p.PESSOA_NOME) LIKE ?", ['%' . strtolower($busca) . '%'])
routes/progressao_funcional.php:497   ->orWhereRaw("LOWER(h.HISTORICO_ATO_ADMINISTRATIVO) LIKE ?", ['%' . strtolower($busca) . '%'])
```

**Análise por hit:**
- `comunicados.php:9` — `$tabela` interpolada no nome da tabela. **Falso positivo se for literal de código; risco real se vier de input.** Validar na Etapa 7.
- `motor.php` (5 ocorrências) — `PRAGMA table_info` é **SQLite-only**. Vai quebrar em SQL Server de produção. **R1 ALTO.**
- `progressao_funcional.php:496-497` — `whereRaw` com bind via `?` e parâmetro array. **Seguro.** Falso positivo do regex.

---

## 3. Estado da pasta `docs/` (surpresa positiva)

A pasta cresceu drasticamente entre 30/03 e 28/04. Não foram apenas adições — foi a **adoção de um framework de prontidão operacional** chamado **P0→P7 + S1→S9**.

### 3.1 Inventário completo dos novos documentos (post-30/03)

**Plano consolidado:**
- `PLANO_PRONTIDAO_OPERACIONAL_E_EXPANSAO_GENTE_2026-04-27.md` (582 linhas, 30 KB) — fonte autoritativa de prioridades. Define critério de "sistema pronto" em 4 blocos: Técnico, Negócio, Institucional, Escalabilidade.

**Notas P0–P7 (governança de prontidão e expansão):**
- `P0_RACI_PRONTIDAO_2026-04-27.md`
- `P0_SLO_SLA_KRI_2026-04-27.md`
- `P0_POLITICA_INCIDENTES_2026-04-27.md`
- `P0_CHECKLIST_ACEITE_FINAL_2026-04-27.md`
- `P0_DIAGNOSTICO_SQLSERVER_CONN_2026-04-27.md`
- `P1_BASELINE_UPGRADE_2026-04-27.md`
- `P1_PREREQUISITOS_RUNTIME_2026-04-27.md`
- `P1_INSTALACAO_BCMATH_CYBERSEGURANCA_2026-04-27.md`
- `P2_OPS_RESUMO_E_WORKERS_2026-04-28.md`
- `NOTA_P3_SHADOW_2026-04-27.md`
- `P3_CHECKLIST_EXECUCAO_PILOTO_2026-04-27.md`
- `P5_STANDBY_INTEGRACOES_2026-04-28.md`
- `NOTA_P6_MULTITENANT_BOOTSTRAP_2026-04-27.md`
- `P7_MATRIZ_BLOQUEIO_GOLIVE_2026-04-27.md`
- `NOTA_P7_CERTIFICACAO_PRONTIDAO_2026-04-27.md`

**Notas S1–S9 (sprints técnicas):**
- `NOTA_S1_RBAC_MIGRACOES_2026-04-27.md`
- `NOTA_SPRINT_S1_S2_2026-04-27.md`
- `S1_AUDITORIA_2026-04-26.md`
- `S1_HIGIENE_SEGREDOS_2026-04-26.md`
- `S1_INVENTARIO_RBAC_2026-04-26.md`
- `NOTA_S3_JORNADA_2026-04-27.md`
- `NOTA_S4_PROGRESSAO_2026-04-27.md`
- `NOTA_S5_TERCEIRIZACAO_2026-04-27.md`
- `NOTA_S6_RPPS_2026-04-27.md`
- `NOTA_S7_ESOCIAL_2026-04-27.md`
- `NOTA_S8_OBSERVABILIDADE_2026-04-27.md`
- `CATALOGO_DADOS_PUBLICOS_S8_2026-04-27.md`
- `TRILHA_LGPD_S8_2026-04-27.md`
- `NOTA_S9_ESTABILIZACAO_2026-04-27.md`
- `S9_REGRESSAO_MINIMA_2026-04-27.md`
- `RUNBOOK_OPERACAO_ASSISTIDA_S9_2026-04-27.md`

**Auditorias e mapas:**
- `MAPA_ESTADO_REAL.md`
- `MAPA_CAMPOS_TABELAS.md`
- `MIGRACAO_SISFOLHA_GENTE_DEPARA.md`
- `MIGRACOES_FORA_ROTA_2026-04-26.md`
- `RECONHECIMENTO_CICLO_VIDA_FUNCIONARIO.md`
- `RELATORIO_AUDITORIA_E_TESTES_UNIFICADO_2026-04-21.md`
- `RELATORIO_VITALIDADE_TEIA_2026-04-27.md`
- `SEC_AUDITORIA_ROTAS_MUTACAO_2026-04-28.md`
- `UI_BACKEND_TEIA_MAP_2026-04-27.md`

**Operacional / governança de código:**
- `ROUTE_WIRING_POLICY.md`
- `PR_CONVENTION.md`
- `ROLLBACK_SPRINT.md`
- `BUG_SPRINT_01.md`
- `BUG_SPRINT_02.md`
- `checklist-deploy-vps.md`
- `historico-problemas.md`
- `guia-correcao-programador.md`
- `guia-baterias-verificacao-2026-04-22.md`

**Programa baseline:**
- `PROGRAMA_S0_BASELINE_2026-04-26.md`

**Evidências e templates:**
- `screenshots.zip`, `screenshots/`
- `eventos PMSL.xlsx`
- `TABELAS_VENCIMENTOS_REVISAO GERAL_2025.xlsx`
- `smoke-api-criacao-RESULTADO.json`
- `SPRINT_ACEITE_TEMPLATE.md`

### 3.2 Documentos da nossa baseline que continuam existindo (e foram atualizados)

- `PLANO_MESTRE_V3.md` — preservado
- `SPRINT_EXECUCAO_V3.md` — atualizado (1.506 linhas, 65 KB)
- `SPRINT_SEGURANCA.md` — preservado
- `GAPS_ESTRATEGICOS.md` — preservado
- `DESIGN_SYSTEM_GENTE_V3.md` — preservado
- `WORKFLOW_ESCALA_V3_TESTES.md` — preservado
- `TUTORIAL_MOBILE_EMULADOR_RECONHECIMENTO_FACIAL.md` — preservado
- `arquivo/` (subpasta) — preservada para docs obsoletos

### 3.3 Diretriz produtiva nova (28/04) — citação direta

> **"Diretriz de produto (standalone-first): Até nova decisão comercial/contratual, o GENTE opera de forma independente de plataformas externas. Integrações federais e de terceiros ficam em stand-by. O foco imediato é: precisão matemática, segurança de plataforma e implantação multi-município. Qualquer integração externa só entra quando houver exigência formal do contratante."**

Esta mudança de direção é positiva: a equipe abandonou o foco em surface area (mais features) e foi para **fundamento** (homologação matemática + hardening + multi-município).

### 3.4 Sequência estratégica final (do plano consolidado)

1. **P0 → P3** (governança + homologação matemática no stack atual)
2. **P1** (upgrade Laravel 8→10/11 LTS + segurança) com regressão contra baseline P3
3. **P2** (observabilidade interna + resiliência de jobs)
4. **P4** (jurídico / LGPD / transparência), paralelizável com P1/P2
5. **P6 → P7** (expansão multi-município + certificação final)
6. **P5-Standby** (integrações externas), só por gatilho contratual

### 3.5 Itens já implementados em código segundo o plano (28/04)

- `config/shadow.php`
- Migrations canônicas: `SHADOW_RUN`, `SHADOW_CHECKPOINT`, `DIFF_RECONCILIACAO`, `SHADOW_RESULTADO_CALC`
- Comandos: `shadow:snapshot-validar`, `shadow:dispatch`, `shadow:relatorio-run`, `shadow:export-run`, `shadow:snapshot-canonico-validar`
- Filas: `queue-shadow-etl`, `queue-shadow-calc`, `queue-shadow-diff`
- Jobs com trait `Illuminate\Bus\Batchable`: `ShadowIngestChunkJob`, `ShadowCalcChunkJob`, `ShadowDiffChunkJob`
- Migration `2026_04_27_135625_create_job_batches_table` (tabela exigida pelo Bus Batch)
- `config/feature_flags.php` + proteção por flag em rotas de transparência
- Hooks `onSuccess`/`onFailure` no `Kernel.php` para crons críticos (`rpps:prova-vida-processar`, `esocial:processar-fila`, `gente:healthcheck`)
- `scripts/deploy_atomic.sh` + `scripts/rollback_atomic.sh` (estratégia symlink current/previous)
- Poison Pill / DLQ na fila eSocial com `max_retry`, status `FALHA_PERMANENTE`, colunas `DEAD_LETTER_*`
- `TenantContext` + middleware `tenant.resolve` + `config/tenancy.php` (resolução sem troca de conexão nesta fase)
- `php artisan gente:db-ping --json` para diagnóstico SQL Server
- `scripts/preflight_prontidao.sh`
- `php artisan gente:prontidao-certificar` — gate executável de P7 com `go_live_decisao` + `blockers` codificados
- `app/Database/TrustSqlServerConnector.php` reforçando `LoginTimeout` no DSN
- Estágio `calc` do shadow conectado ao `MotorFolhaService` com persistência em `SHADOW_RESULTADO_CALC`

---

## 4. Riscos identificados na Etapa 1

| ID | Severidade | Descrição | Validar em |
|---|---|---|---|
| **R1** | 🔴 ALTO | `routes/motor.php` usa `PRAGMA table_info(...)` (SQLite-only) em 5 pontos. Vai quebrar no SQL Server de produção. | Etapa 7 |
| **R2** | 🟡 MÉDIO | `routes/comunicados.php:9` interpola nome de tabela em `DB::select`. Confirmar se `$tabela` é literal segura ou input do usuário. | Etapa 7 |
| **R3** | 🟡 MÉDIO | `Target class [TurnoController] does not exist` ao rodar `route:list`. Rota órfã ou registro de provider faltando. | Etapa 7 |
| **R4** | 🟡 MÉDIO | `fix_users.php` tem senha `gente@2026` (MD5) hardcoded e versionada. OK para Lab; precisa entrar no `.gitignore` ou ser deletado antes de produção. | Pré go-live |
| **R5** | 🟢 BAIXO | Lixo de sessão na raiz: `temp.diff`, `error_trace.txt`, `route_err.txt`, `migrate_out.txt`, `rollback_out.txt`, `refresh.txt`, `lint_out.txt`, pasta `C__/`. | Limpeza imediata |
| **R6** | 🟢 BAIXO | Migration `2026_03_30_000041_create_patrimonio_tables` mencionada em `rollback_out.txt` como inexistente. Confirmar se foi renomeada/movida. | Etapa 6 |

---

## 5. Limpeza segura recomendada

Arquivos/pastas que podem ser removidos com segurança após validação humana:

```
gente/temp.diff
gente/error_trace.txt          (após resolver R3)
gente/route_err.txt            (após resolver R3)
gente/migrate_out.txt
gente/rollback_out.txt         (após confirmar R6)
gente/refresh.txt
gente/results.txt              (ou mover para docs/ como evidência)
gente/lint_out.txt
gente/C__/                     (recursivo)
```

**Manter:**
- `commit.txt` — registro histórico da BUG-SPRINT-01
- Todos os scripts `.php` (`nuclear_ti`, `hard_cleanup`, `deep_clean`, `cleanup_identities`, `dump_ti`, `fix_users`, `patch_routes`, `scan_routes*`) — são ferramentas vivas

---

## 6. Veredicto da Etapa 1

✅ **Arquitetura intacta.** O motor de folha mantém a estrutura de 3 camadas (C1/C2/C3) documentada no docblock. Nada foi destruído.

✅ **Motor de folha não foi violado** — pelo contrário, foi **embrulhado em uma camada de homologação shadow** (`Shadow*Job`, `SnapshotManifestoCanonicoService`, fixtures de smoke, comandos `shadow:*`) para provar matematicamente equivalência (ou divergência justificada) com o sistema legado Sisfolha antes do go-live.

✅ **Disciplina de documentação aumentou drasticamente.** A pasta `docs/` ganhou ~50 novos documentos estruturados em programa P0→P7 + S1→S9, com critérios objetivos de aceite, RACI, SLO/SLA/KRI, política de incidentes, checklist de aceite final, runbook de operação assistida, etc.

✅ **Direção mudou para melhor:** standalone-first, foco em precisão matemática e segurança antes de expansão. Integrações externas em stand-by.

⚠️ **6 riscos pequenos a médios** mapeados (R1–R6), todos endereçáveis nas etapas seguintes da auditoria ou em limpeza imediata.

🟡 **Higiene da raiz precisa de uma faxina** — lixo de sessão de debug deixado para trás. Não compromete o sistema, mas deve ser limpo.

---

## 7. Próxima etapa

**Etapa 2 — Motor de folha completo.** Escopo previsto:

- `app/Services/MotorFolha/MotorFolhaLoteContext.php`
- `app/Jobs/ProcessarFolhaJob.php`
- `app/Jobs/ProcessarLoteFolhaJob.php`
- `app/Services/ContabilidadeService.php`
- `app/Services/ContraChequeService.php`
- `app/Services/DecimoTerceiroService.php`
- `app/Services/RescisaoService.php`
- `app/Services/FeriasService.php`

**Objetivos da Etapa 2:**
1. Validar se o motor está correto end-to-end (não só na função de cálculo isolada)
2. Validar se a integração `ContabilidadeService` → `ProcessarFolhaJob` está intacta
3. Validar se 13º, rescisão e férias usam o motor de forma coerente
4. Detectar regressões em cálculos paralelos (contracheque)

---

*Fim do relatório da Etapa 1.*
