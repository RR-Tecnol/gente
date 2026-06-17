---
tags:
  - gente/plano
  - gente/prontidao-operacional
  - gente/expansao
  - rrtecnol/gente
status: "plano final consolidado"
updated_at: 2026-04-27
revisao_sinc_repositorio: "2026-04-28 — fase 3: P2 ops-resumo, workers script, P4 transparencia.php, P5 conectores, certificação + job_batches; §16 estado técnico"
escopo:
  - "Prontidão operacional plena para Prefeitura de São Luís"
  - "Base arquitetural para expansão multi-município"
related:
  - "[[PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26]]"
  - "[[../arquitetura/arquitetura-atual-gente-2026-04-27]]"
  - "[[../auditorias/auditoria-parametrizacao-rh-rpps-semad-2026-04-26]]"
  - "[[../auditorias/BRAIN_REGRAS_SEMAD_SLZ_JORNADA_2026-04-26]]"
  - "[[../auditorias/BRAIN_AUDITORIA_GERAL_PRODUCAO_2026-04-26]]"
---

# PLANO DE PRONTIDÃO OPERACIONAL E EXPANSÃO — GENTE (2026-04-27)

> **Uso no repositório:** este ficheiro é a **fonte consolidada** de prioridades de implementação para o projeto GENTE. Notas técnicas (`NOTA_P*`, `P*_…`) continuam a existir como detalhamento; quando houver dúvida de ordem ou de macro-sprint, prevalece este plano.

## 1) Objetivo deste plano

Levar o GENTE do estado “tecnicamente estabilizado” para “operação pública plena em São Luís”, com governança, segurança, conformidade e capacidade de replicação para outros municípios sem refatoração disruptiva.

## 1.1 Diretriz de produto (standalone-first)

Até nova decisão comercial/contratual, o GENTE opera de forma independente de plataformas externas.

- Integrações federais e de terceiros ficam em **stand-by**.
- O foco imediato é: **precisão matemática**, **segurança de plataforma** e **implantação multi-município**.
- Qualquer integração externa só entra quando houver exigência formal do contratante.

## 2) Premissas obrigatórias (São Luís)

1. A referência normativa local e os pareceres institucionais de São Luís prevalecem sobre benchmark externo.
2. O que for público deve respeitar LAI/LC 131/LGPD e os limites da PGM.
3. RPPS/IPAM, SEMAD, SEMFAZ, CGM e SI participam como gates de aceite.
4. Toda mudança deve manter rastreabilidade técnica + funcional + documental.
5. Homologação matemática de folha ocorre antes de modernização estrutural de framework.
6. Integrações externas são opcionais e ativadas somente por demanda contratual formal.

## 3) Critério de “sistema pronto” (decreto interno)

O sistema só é declarado pronto quando os quatro blocos abaixo estiverem em verde:

- **Bloco A — Técnico:** zero bloqueador crítico, SLOs mínimos atendidos, rollback testado.
- **Bloco B — Negócio:** folha, jornada, RPPS e terceirização homologados com dados reais de competência.
- **Bloco C — Institucional:** pareceres/aceites formais anexados (PGM/IPAM/SEMFAZ/CGM/SI).
- **Bloco D — Escalabilidade:** arquitetura e operação aptas para tenancy/multi-município.

---

## 4) Sequência estratégica final

A ordem foi ajustada para reduzir risco de diagnóstico falso e acelerar prontidão sem erro:

1. **P0 -> P3** (governança + homologação matemática no stack atual)
2. **P1** (upgrade LTS e segurança) com regressão contra baseline de P3
3. **P2** (observabilidade operacional interna e resiliência de jobs)
4. **P4** (jurídico/LGPD/transparência), paralelizável com P1/P2
5. **P6 -> P7** (expansão e certificação final)
6. **P5-Standby** (integrações externas), só por gatilho contratual

---

## 5) Macro-sprints de execução

## 5.0) Execução iniciada (2026-04-27)

Sprint ativa: **P0 + P3 (scaffolding técnico)**.

Artefatos já gerados no repositório GENTE (`gente/docs`):
- `P0_RACI_PRONTIDAO_2026-04-27.md`
- `P0_SLO_SLA_KRI_2026-04-27.md`
- `P0_POLITICA_INCIDENTES_2026-04-27.md`
- `P0_CHECKLIST_ACEITE_FINAL_2026-04-27.md`

Andamento P3 já iniciado no código (ambiente GENTE):
- Configuração criada: `config/shadow.php`.
- Migrations canônicas criadas: `SHADOW_RUN`, `SHADOW_CHECKPOINT`, `DIFF_RECONCILIACAO`.
- Migrations canônicas ampliadas: `SHADOW_RESULTADO_CALC` (resultado real por CPF no run).
- Comandos criados: `shadow:snapshot-validar`, `shadow:dispatch` e `shadow:relatorio-run`.
- Filas e jobs em batch criados: `queue-shadow-etl`, `queue-shadow-calc`, `queue-shadow-diff`.
- Estágio `calc` já conectado ao `MotorFolhaService` com persistência de saída em `SHADOW_RESULTADO_CALC`.
- Nota técnica registrada: `gente/docs/NOTA_P3_SHADOW_2026-04-27.md`.

Amarrações operacionais complementares já aplicadas no código:
- **Feature Flags (P4):** `config/feature_flags.php` + proteção por flag nas rotas de transparência (`dossie-terceirizacao`, `observabilidade-integracoes`, `catalogo-dados`).
- **Observabilidade de crons críticos (P2):** hooks `onSuccess`/`onFailure` em `Kernel.php` para `rpps:prova-vida-processar`, `esocial:processar-fila` e `gente:healthcheck`.
- **Deploy atômico/rollback (DevOps):** scripts `scripts/deploy_atomic.sh` e `scripts/rollback_atomic.sh` com estratégia de symlink (`current`/`previous`).
- **Resiliência de fila eSocial (P2/P5-standby):** Poison Pill/DLQ com `max_retry` configurável, status `FALHA_PERMANENTE` e colunas `DEAD_LETTER_*`.
- **Fechamento binário P3 por RUN_ID:** `shadow:relatorio-run --persistir` atualiza `SHADOW_RUN` com totais e status de aceite.
- **Healthcheck de prontidão ampliado:** valida PHP mínimo, extensão BCMath e presença das tabelas de homologação matemática.
- **Risco identificado em execução real:** runtime atual sem BCMath carregado; pré-requisito formalizado em `gente/docs/P1_PREREQUISITOS_RUNTIME_2026-04-27.md` para desbloqueio completo de P3/P1.
- **Bootstrap P6 iniciado em código:** `TenantContext` + middleware `tenant.resolve` + `config/tenancy.php` (resolução sem troca de conexão nesta fase).
- **Piloto P6 em execução:** `tenant.resolve` aplicado às rotas de transparência e testes de resolução (subdomínio/header) adicionados para validar isolamento de contexto.
- **Estabilidade de bootstrap reforçada:** correção preventiva de redeclaração em helpers `ensure*FromRoutes` com `function_exists` nos módulos legados críticos.
- **Gate executável de P7 criado:** comando `gente:prontidao-certificar` para certificação técnica pass/fail de prontidão.
- **Isolamento cross-tenant reforçado:** teste de não-vazamento de `TenantContext` entre requisições adicionado ao piloto P6.
- **P7 com matriz objetiva de bloqueio:** certificação agora retorna `go_live_decisao` + `blockers` codificados para gate binário de produção.
- **Préflight técnico operacionalizado:** script `scripts/preflight_prontidao.sh` criado para evidenciar desbloqueio de runtime (incluindo BCMath) antes do gate final.

### Complemento (sincronia com o repositório, 2026-04-27)

Itens adicionais já presentes no código e na documentação, **alinhados a este plano** e ao §15 (sem substituir as linhas acima):

- **Batches Laravel (`job_batches`):** migration `2026_04_27_135625_create_job_batches_table` (tabela exigida por `Illuminate\Bus\Batch` usada em `shadow:dispatch`). Rodar `php artisan migrate` após puxar o branch.
- **Jobs Shadow e `Bus::batch()`:** `ShadowIngestChunkJob`, `ShadowCalcChunkJob` e `ShadowDiffChunkJob` com trait `Illuminate\Bus\Batchable` (sem isso o dispatch de pipeline falhava com `withBatchId()`).
- **Conexão SQL Server e operação no host vs Docker:** `config/database.php` com `login_timeout` numérico; `app/Database/TrustSqlServerConnector.php` reforçando `LoginTimeout` no DSN; `DB_LOGIN_TIMEOUT` em `.env.example`; nota de diagnóstico `gente/docs/P0_DIAGNOSTICO_SQLSERVER_CONN_2026-04-27.md` (ex.: `DB_HOST=127.0.0.1` no host com porta `1433` publicada, vs `DB_HOST=sqlserver` dentro da rede do Compose).
- **Comando de diagnóstico:** `php artisan gente:db-ping --json` (teste curto de PDO, auditável, útil antes de `migrate` / certificação com DB).
- **Preflight:** `scripts/preflight_prontidao.sh` passou a chamar `gente:db-ping` no passo de certificação com banco, além de healthcheck e certificação.
- **Smoke mínimo P3 (não substitui competência real):** fixture versionada `gente/tests/fixtures/shadow_pilot_minimal/2026-04/` para validar `shadow:snapshot-validar` → `shadow:dispatch` → `shadow:relatorio-run` com zero divergência crítica; descrito também em `gente/docs/P3_CHECKLIST_EXECUCAO_PILOTO_2026-04-27.md` e no runbook `gente/docs/RUNBOOK_OPERACAO_ASSISTIDA_S9_2026-04-27.md` (secção *Smoke do pipeline Shadow*).
- **P1 / runtime (documentação):** registro de instalação e matriz de risco de pacote `php-bcmath` (Fedora) em `gente/docs/P1_INSTALACAO_BCMATH_CYBERSEGURANCA_2026-04-27.md` (complementa `P1_PREREQUISITOS_RUNTIME_2026-04-27.md`).

**Próximo passo de implementação (revisado em 2026-04-28):** o **esqueleto técnico** de diff por rubrica, export e validação de manifesto canónico (§15) está em curso no repositório — ver *Fase 2* abaixo. O que ainda **fecha** a homologação de negócio: **competência piloto com dados reais**; **congelar snapshot** com checklist institucional; orquestrar **workers** nas filas `queue-shadow-etl`, `queue-shadow-calc` e `queue-shadow-diff` em homologação; e **atas/aceites** formais. O `calc` em shadow **já está** ligado ao `MotorFolhaService` com escrita em `SHADOW_RESULTADO_CALC`.

> **Texto original (mantido para histórico):** “Próximo passo de implementação: conectar estágio `calc` ao `MotorFolhaService` em modo shadow e executar competência piloto com diff por rubrica + líquido.”  
> *Interpretação pós-sincronia: a parte do `calc` já estava atendida no repositório; o saldo pendente concentra-se no piloto com snapshot real, diff por rubrica e evidências de aceite.*

### Fase 2 (em curso no repositório, 2026-04-28) — início de execução alinhada ao plano e ao §15

- **Diff por rubrica (além do líquido):** tabela `DIFF_RECONCILIACAO` com colunas `RUBRICA_CODIGO`, `RUBRICA_TIPO`, `AGREGACAO` (`liquido`|`rubrica`); `shadow:dispatch` aceita, opcionalmente, `rubricas_legado.csv` e `rubricas_gente.csv` (devem existir juntos) com chaves `cpf`, `rubrica_codigo`, `rubrica_tipo`, `valor`; idempotência de `diff_ok` ajustada por CPF+escopo (líquido vs rubrica). Fixture de smoke actualizada: `gente/tests/fixtures/shadow_pilot_minimal/2026-04/`.
- **Export §15.10 (artefactos mínimos):** comando `php artisan shadow:export-run {run_id}` gera `diff_resultado.csv`, `diff_sumario.json` e rascunho `ata_justificativas.md` (em `storage/app/shadow-exports/{run_id}/`, ignorada pelo git).
- **Validação de snapshot canónico (§15.4–15.6, gate parcial):** `php artisan shadow:snapshot-canonico-validar {competencia}` (serviço `App\Services\Shadow\SnapshotManifestoCanonicoService`) valida `manifest.json` (campos `competencia`, `schema_version`, `gerado_em`, `fonte_legacy` + lista `arquivos` ou `files` com `path`, `sha256`, contagem de linhas). *O modo mínimo legado (`shadow:snapshot-validar` com quatro ficheiros) continua para smoke rápido; o canónico é o gate de entrada de competência real congelada.*
- **Pendente para fechar a sprint P3 de homologação:** 1) competência piloto com **dados reais** (extração/adesão institucional); 2) **orquestrar workers** nas filas shadow em homologação; 3) completar validadores §15.6 (tipagem por coluna, N-para-1 no diff) conforme carga; 4) congelar baseline assinada para P1 (regressão).


## Sprint P0 — Governança de prontidão (2 semanas)
**Meta:** travar critérios objetivos de go/no-go e operação contínua.

### Entregas
- Matriz de RACI de operação (SEMAD, IPAM, TI, fornecedor).
- Catálogo oficial de SLO/SLA/KRI com metas e limiares.
- Política de incidentes (P1/P2/P3) + comunicação institucional.
- Checklist único de aceite final com assinatura dos responsáveis.

### Aceite
- Documento de go-live aprovado por gestão técnica e negócio.

---

## Sprint P3 — Homologação matemática + Shadow Deployment (3 a 4 semanas)
**Meta:** provar matematicamente equivalência (ou divergência justificada) por competência fechada.

### Entregas
- UAT com SEMAD (jornada/folha/progressão) e IPAM (prova de vida/contribuições).
- Shadow run por competência com dados reais.
- Ferramenta de conciliação por servidor, rubrica e líquido.
- Snapshot determinístico versionado da competência (dump/seed congelado) para replay idêntico em P1.
- Ata de justificativas matemáticas para divergências não críticas.
- Casos de borda obrigatórios: múltiplos vínculos, teto constitucional, pensão judicial combinada com falta injustificada.

### Aceite
- Baseline matemática homologada e congelada para regressão.

---

## Sprint P1 — Plataforma LTS e segurança do core (3 a 5 semanas, pós-P3)
**Meta:** eliminar risco de EOL e endurecer segurança sem alterar regra de negócio.

### Entregas
- Upgrade Laravel 8 -> 10 LTS (ou 11 conforme janela) e PHP alvo.
- Adequações de middleware, exception handler e casts.
- Adoção de padrão decimal seguro para cálculo financeiro (BCMath em regras sensíveis).
- Estratégia de segredos (vault/secret manager) e rotação.
- Fallback resiliente de segredos (cache local criptografado com TTL curto) para evitar SPOF operacional.

### Aceite
- Regressão matemática batendo baseline P3.
- Taxa de sobrevivência de mutantes (Infection) no MotorFolhaService abaixo do limiar acordado (referência: < 10%).
- Sem vulnerabilidades críticas abertas de plataforma.

---

## Sprint P2 — Confiabilidade operacional e observabilidade interna (2 a 3 semanas)
**Meta:** impedir falhas silenciosas e reduzir MTTR no modo standalone.

### Entregas
- Painel operacional para filas e jobs críticos internos.
- Alertas de poison-pill, backlog, retry anômalo e indisponibilidade de jobs.
- Telemetria mínima por domínio (folha, RPPS, transparência, scheduler).
- Exercício de caos controlado para validar resposta operacional.
- Simulação formal de poison pill com encaminhamento para Dead Letter Queue sem travar jobs subsequentes.
- Hooks de monitoramento no Scheduler (`onSuccess`/`onFailure`) com alerta imediato para operação (webhook/canal oficial) e trilha persistida de execução.
- Alinhamento prévio com SI/infra para whitelisting de rotinas ETL e testes de estresse (evitar falsos positivos de defesa ativa).

### Aceite
- Operação detecta, classifica e corrige falhas no tempo acordado.

---

## Sprint P4 — Conformidade jurídica e transparência pública (2 semanas, paralelizável)
**Meta:** blindar publicação pública e governança de dados.

### Entregas
- Parecer PGM com escopo exato de campos públicos.
- Política de dados públicos versionada (owner, revisão, trilha).
- Revisão LGPD de serialização nos endpoints públicos.
- Feature flags por coluna para transparência pública (go-live sem bloqueio caso parecer PGM atrase).
- Implementação de toggles de publicação em `config/transparencia.php` (ou ferramenta equivalente) com trilha de alteração por ambiente.
- Protocolo de resposta a controle externo.

### Aceite
- Checklist jurídico/LGPD assinado e anexado.

---

## Sprint P6 — Expansão multi-município (4 semanas)
**Meta:** preparar produto para escalar além de São Luís com isolamento controlado.

### Entregas
- Blueprint tenancy híbrido (shared para pequenos; banco isolado para grandes).
- Tenant resolution no middleware antes de qualquer query.
- Parametrização por ente (normas, rubricas, calendário, regras).
- MDM mínimo: `PESSOA` SSOT + vínculos por tenant/vertical.
- Suíte de testes de isolamento cross-tenant (tentativa de leitura lateral deve retornar 404/403).

### Aceite
- POC de “Município B” sem regressão em São Luís.

---

## Sprint P7 — Certificação de prontidão (1 a 2 semanas)
**Meta:** decreto interno “pronto para operar”.

### Entregas
- Relatório final de prontidão técnico/negócio/institucional.
- Ata de go-live + plano de operação contínua 90 dias.
- Baseline final de arquitetura, riscos e backlog residual.

### Aceite
- Comitê aprova formalmente a entrada em produção.

---

## Sprint P5-Standby — Integrações externas sob demanda contratual
**Meta:** manter trilha técnica preparada, sem ativação operacional.

### Entregas
- Catálogo de conectores externos com status `desativado por padrão`.
- Contrato de interface + feature flag para ativação futura.
- Runbook de ativação sob demanda (pré-requisitos, risco, rollback).

### Aceite
- Modo standalone íntegro com integrações desligadas.

---

## 6) Especificação técnica obrigatória (upgrade LTS)

### 6.1 Breaking changes que podem afetar matemática
- Coerções implícitas float->int e operações com `%`/bitwise sob PHP 8.1+.
- Serialização de float e precisão (`serialize_precision`) com impacto em payload JSON.
- Mudanças de comportamento de `round()` em bordas numéricas.
- Casts e serialização de datas no Eloquent/Laravel 9/10.

### 6.2 Regras de implementação
- Proibir `float` em cálculo financeiro sensível do motor.
- Usar `BCMath` para operações monetárias e percentuais de alto impacto.
- Declarar `decimal`/casts explicitamente nos modelos.
- Preservar formato de data esperado no contrato API.

### 6.3 Refatorações estruturais mínimas
- Atualização de middlewares e aliases no Kernel conforme versão alvo.
- Revisão do Exception Handler e contratos de debug/registro de erro.
- Atualização das suítes de testes com Fakes modernos do Laravel.

### 6.4 Automação segura (Rector/Shift/Infection)
- Priorizar automação local (AST) com revisão humana obrigatória.
- Nunca aplicar mutação automática sem suíte de testes e comparação de baseline.
- Executar Mutation Testing (Infection) no MotorFolhaService e serviços correlatos antes do cutover.

### 6.5 Rollback do upgrade
- Deploy atômico (release por diretório + symlink).
- Health checks de API e jobs pós-switch.
- Rollback imediato por reversão de symlink em falha.

---

## 7) Shadow Deployment e conciliação matemática (P3)

## 7.1 Pipeline ETL cego
- Extração: snapshot de competência fechada no legado (entradas + saídas).
- Transformação: normalização de IDs, rubricas e estruturas de cálculo.
- Carga: injeção em staging GENTE sem efeitos colaterais externos.
- Extração híbrida recomendada: conexão read-only no legado + congelamento em snapshot imutável para replay determinístico.
- Cegueira por camadas: aplicação (events/observers), comunicação (mail/sms/push/cnab), infraestrutura (endpoints/credenciais externas desativados).

## 7.2 Diff Engine (regras de comparação)
- Comparação por servidor (chave funcional), rubrica e líquido.
- Mapeamento de diferenças estruturais (agrupamentos N-para-1) antes da validação financeira.
- Classificação automática: tolerável, justificável, crítica.

## 7.3 Thresholds de aceite
- Divergência tolerável de arredondamento: limiar institucional explícito (ex.: <= R$ 0,03) com regra formal.
- Divergência justificável: correção de bug/regra legada com evidência e assinatura.
- Divergência crítica: bloqueia go-live e reabre ciclo técnico.

## 7.4 Artefatos obrigatórios
- Relatório de divergências por lote e por servidor.
- Ata de Justificativa Matemática assinada (SEMAD/SEMFAZ).
- Baseline congelada para regressão pós-upgrade.

---

## 8) Blueprint multi-tenant (P6)

## 8.1 Decisão arquitetural
- Estratégia preferencial: tenancy dedicada via mecanismo robusto de resolução de tenant.
- Objetivo: manter serviços de domínio tenant-agnostic.

## 8.2 Tenant Resolution
- Identificação por subdomínio/header.
- Resolução do tenant no início da pipeline HTTP.
- Injeção do contexto no container antes de qualquer query.
- Purge/rebind seguro de conexão por tenant.

## 8.3 Migrations em escala
- Squash periódico de migrations para reduzir custo operacional.
- Execução paralela controlada por lotes/chunks.
- Lock atômico para evitar concorrência destrutiva.
- Padrão zero-downtime (expand->migrate data->contract) para tabelas críticas.

## 8.4 MDM mínimo
- `PESSOA` como fonte única de identidade.
- Vínculos segmentados por tenant e vertical (V1/V2/V3).
- Proibição de duplicidade sem reconciliação controlada.

---


---

## 9) Estratégia DevOps de deploy atômico e rollback

### 9.1 Padrão operacional obrigatório

- Cada release gera pasta imutável em `/releases/<timestamp_release>`.
- O web server aponta para `/current` (symlink ativo).
- Virada de versão ocorre por troca atômica de symlink (`ln -sfn`).

### 9.2 Fluxo mínimo de deploy

1. Publicar artefato no diretório da nova release.
2. Executar validações pré-switch (healthcheck, migrations seguras, permissões).
3. Trocar `/current` para nova release.
4. Executar smoke pós-switch (API crítica, fila/scheduler, auth).
5. Se falhar: rollback imediato para release anterior com nova troca de symlink.

### 9.3 Critério de aceite DevOps

- [ ] Pipeline de deploy atômico documentado e reexecutável.
- [ ] Simulação de rollback concluída com evidência de tempo de recuperação.
- [ ] Procedimento de emergência conhecido pela operação (plantão e runbook).

### 9.4 Ferramenta de orquestração

- Definir ferramenta oficial (Envoy, Deployer ou pipeline CI com script shell padronizado) e registrar responsável técnico.

## 10) SLO/SLA/KRI mínimos

### SLO
- Disponibilidade >= 99,9% nas rotas críticas em horário comercial.
- p95 <= 500ms nas operações operacionais diárias.

### SLA
- Fechamento de folha no tempo pactuado (meta inicial <= 45 min).

### KRI
- Fila crítica acima do limiar de backlog.
- Dead-letter acima do limiar acordado.
- Rotas de mutação sem middleware de perfil (meta absoluta: 0).
- Falha de scheduler crítico (meta: 0 não tratadas).

---

## 11) Gates institucionais obrigatórios (São Luís)

- **PGM:** dados públicos e base legal.
- **IPAM:** regras RPPS e exceções.
- **SEMFAZ/CGM:** impacto fiscal e trilha de controle.
- **SI/TI:** segurança e continuidade.
- **SEMAD:** aceite final de processos RH/folha.

---

## 12) Backlog residual classificado

### Classe A — Bloqueia decreto “pronto”
- Baseline matemática homologada (P3) e preservada no upgrade (P1).
- Upgrade LTS + segurança de plataforma.
- Homologação matemática folha/RPPS assinada.
- Parecer jurídico formal aplicado na transparência.

### Classe B — Não bloqueia, mas reduz maturidade
- SIEM externo e trilha forense ampliada.
- CQRS/read-side dedicado para portal público em escala.
- Evolução de MDM multi-ente.
- Integrações externas oficiais quando exigidas por contrato.

---

## 13) Checklist final de pronto (Go-Live)

- [ ] Baseline P3 assinada e sem divergência crítica.
- [ ] Snapshot P3 versionado e reexecutável em P1.
- [ ] Upgrade P1 concluído com regressão matemática aprovada.
- [ ] SLO/SLA/KRI monitorados em produção assistida.
- [ ] Simulação de poison pill aprovada (DLQ sem colapso de fila).
- [ ] Scheduler crítico com alerta de falha validado (`onFailure` + evidência).
- [ ] RBAC crítico validado (meta 0 mutação sem perfil).
- [ ] Parecer PGM anexado e aplicado.
- [ ] Feature flags de transparência validadas para go-live sem bloqueio jurídico.
- [ ] Aceites SEMAD/IPAM/SEMFAZ/CGM/SI anexados.
- [ ] Teste de isolamento multi-tenant aprovado (sem vazamento lateral).
- [ ] Rollback validado por simulação real (deploy atômico com symlink).
- [ ] Ata final de comitê registrada.

---

## 14) Prompt para revalidação técnica (Gemini)

"Com base neste plano final consolidado, avalie se a sequência P0->P3->P1->P2/P4->P6->P7 (com P5 em standby) minimiza risco para go-live em São Luís. Aponte lacunas de engenharia, risco de execução, dependências ocultas e critérios adicionais de aceite que aumentem a probabilidade de sucesso sem erros."

---

## 15) Resumo executivo

Este plano consolida, em um único artefato, a estratégia completa para colocar o GENTE em produção com segurança em São Luís no modo independente, preservando rigor matemático, conformidade institucional e capacidade real de expansão multi-município.


---

## 15) Contrato técnico do Snapshot P3 (pronto para implementação)

### 15.1 Objetivo do contrato

Garantir **replay determinístico** entre P3 e P1 (antes/depois do upgrade LTS), com rastreabilidade completa da competência homologada.

### 15.2 Estratégia adotada

- Extração do legado por conexão **read-only**.
- Congelamento em snapshot canônico (arquivo), imutável por competência.
- Processamento no GENTE exclusivamente a partir do snapshot congelado.

### 15.3 Layout de pastas (canônico)

```text
shadow/
  competencia_YYYY_MM/
    manifest.json
    metadata.json
    pessoas.csv
    funcionarios.csv
    vinculos.csv
    rubricas.csv
    eventos_folha.csv
    lancamentos_folha.csv
    afastamentos.csv
    lotacoes.csv
    parametros_legais.csv
    hash/
      pessoas.csv.sha256
      ...
```

### 15.4 Arquivos obrigatórios

- `manifest.json`
  - `competencia`
  - `schema_version`
  - `gerado_em`
  - `fonte_legacy`
  - lista de arquivos com `rows`, `sha256`, `delimiter`, `encoding`
- `metadata.json`
  - parâmetros de execução (`tenant`, ambiente, operador, branch/commit)
  - regra de tolerância em vigor (`limiar_divergencia`)
- CSVs tabulares
  - UTF-8, delimitador `;`, cabeçalho obrigatório
  - datas em `YYYY-MM-DD`
  - monetários como string decimal com `.`

### 15.5 Chaves mínimas por entidade (para reconciliação)

- Pessoa/Servidor: `cpf`, `matricula`, `funcionario_id_legacy`
- Folha: `competencia`, `rubrica_codigo`, `rubrica_tipo`, `valor`
- Resultado final: `valor_bruto`, `valor_descontos`, `valor_liquido`

### 15.6 Regras de validação do snapshot (gate de entrada)

1. Todos os arquivos obrigatórios presentes.
2. Hash SHA-256 de cada arquivo confere com `manifest.json`.
3. `rows` informado no manifest bate com total lido.
4. Tipagem válida por coluna (data, decimal, inteiro, string).
5. CPF/matrícula não nulos para registros elegíveis de cálculo.
6. Competência única e consistente em todos os datasets de folha.

Se qualquer regra falhar: **NO-GO do lote**.

### 15.7 Idempotência e checkpoints

Cada execução deve registrar `run_id` e `idempotency_key`:

- `idempotency_key = competencia + snapshot_sha_global + servidor_cpf`

Checkpoints por servidor:

- `etl_ok`
- `calc_ok`
- `diff_ok`

Reprocesso sempre retoma do último checkpoint válido.

### 15.8 Política de filas (execução em lote)

- `queue-shadow-etl`
- `queue-shadow-calc`
- `queue-shadow-diff`

Regras:

- chunk de referência: 500 servidores por job (ajustável por benchmark)
- `tries=3` e fallback para `failed_jobs`
- poison pill isolada por servidor (não bloqueia lote inteiro)
- transação atômica por servidor em gravações críticas

### 15.9 Contrato do Diff Matemático

Classificação mínima:

- `APROVADO_EXATO`
- `DIVERGENCIA_TOLERAVEL`
- `DIVERGENCIA_JUSTIFICAVEL`
- `FALHA_SISTEMICA_CRITICA`

Regras:

- normalização estrutural N-para-1 antes do delta financeiro
- cálculo de delta com precisão decimal segura
- emissão de relatório por servidor/rubrica/líquido

### 15.10 Artefatos obrigatórios de saída

- `diff_resultado.csv` (detalhado por servidor/rubrica)
- `diff_sumario.json` (KPIs da execução)
- `ata_justificativas.md` (divergências justificáveis assináveis)
- log operacional com `run_id`, duração, falhas e retries

### 15.11 Critérios binários de aceite da implementação

- [ ] Snapshot da competência validado com hash e schema sem erro.
- [ ] Replay da mesma competência produz resultado idêntico (determinístico).
- [ ] Filas processam lote completo sem colapso global em presença de poison pill.
- [ ] Divergências críticas bloqueiam automaticamente o go-live da competência.
- [ ] Divergências justificáveis geram ata formal para aprovação institucional.
- [ ] Baseline P3 é reaplicável em P1 pós-upgrade sem ambiguidade de dados.

---

## 16) Estado de implementação no repositório (vs. plano)

Este anexo distingue o que **já tem suporte de código** do que **depende de processo, dados reais, contrato ou upgrade de major version** (não cabe “fechar” só com commits).

| Macro | No repo (técnico) | Ainda fora de escopo de código / pendência explícita |
|--------|---------------------|--------------------------------------------------------|
| **P0** | Documentos e checklist em `gente/docs/`; preflight. | Assinaturas, comitê, canal de plantão. |
| **P3** | Shadow (dispatch, diff líquido+rubrica, export, validação canónica parcial, `job_batches`, script de workers, fixture smoke mínimo + **`tests/fixtures/shadow_smoke_e2e/`** com manifest+hashes). | Piloto com **dados reais**, tipagem coluna a coluna §15.6 completa, N-para-1 no diff, UAT institutional. |
| **P1** | BCMath/docs, Trust SQL, baseline preparado no texto. | **Upgrade Laravel/PHP** (LTS), Infection, rotação de segredos em vault. |
| **P2** | Kernel `onSuccess`/`onFailure`, `gente:healthcheck`, **`gente:ops-resumo`** (failed_jobs count/bytes/filas), DLQ eSocial em schema. | “Painel” interactivo, alertas webhook, whitelisting SI, caos formal. |
| **P4** | Feature flags, **`config/transparencia.php`**, `transparencia_catalogo.php`. | Parecer PGM, resposta a controle externo. |
| **P5-Standby** | **`config/p5_connectors.php`**, doc `P5_STANDBY_*`. | Ativação contratual e runbook por conector. |
| **P6** | `tenant.resolve`, testes de contexto, rotas piloto. | Banco por tenant, MDM, POC “Município B”. |
| **P7** | **`gente:prontidao-certificar`** (inclui tabela `job_batches`), matriz de bloqueio. | Ata de comitê, relatório final manuscrito. |
| **DevOps §9** | `deploy_atomic.sh` / `rollback_atomic.sh`. | Orquestrador “oficial”, RTO medido com SI. |
| **§15** | `shadow:snapshot-canonico-validar`, `metadata.json` (limiar) se ficheiro existir, `shadow:export-run`. | Todos ficheiros canónicos (pessoas, lançamentos, etc.) e hashes em pipeline de extração. |
| **SEC** | `gente:auditar-rotas-mutacao` + `docs/SEC_AUDITORIA_ROTAS_MUTACAO_2026-04-28.md` (varredura mutação sem `auth`). | Correcção de rotas reais (ex.: CRUD sob `web` só) e gates de perfil em produção. |

*Última sincronização desta tabela: 2026-04-28.*
