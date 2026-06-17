# PLANO IMPLEMENTAÇÃO BRAIN SEMAD — Execução Linear Segura (2026-04-26)

> **Objetivo deste documento:** servir como roteiro único de execução, em ordem linear, para evoluir o GENTE sem quebra de produção, com gates técnicos e funcionais entre sprints.
>
> **Regra de ouro:** nenhuma etapa avança sem cumprir os critérios de aceite da etapa anterior.

> **Atualização de governança (2026-04-27):** este documento permanece como histórico canônico da execução S0..S9. A execução corrente de prontidão operacional e expansão segue o plano complementar `PLANO_PRONTIDAO_OPERACIONAL_E_EXPANSAO_GENTE_2026-04-27.md` (modo standalone-first).

---

## 0) Fontes consolidadas (base deste plano)

Este plano integra os pontos úteis e convergentes dos documentos:

- `routes-audit-2026-04-26.md`
- `BRAIN_REGRAS_SEMAD_SLZ_JORNADA_2026-04-26.md`
- `BRAIN_AUDITORIA_GERAL_PRODUCAO_2026-04-26.md`
- `deep-researach-pesqusias-gente.md`
- `auditoria-parametrizacao-rh-rpps-semad-2026-04-26` (Brain/Obsidian)
- `arquitetura-as-is-vs-to-be-sao-luis-2026-04-26` (Brain/Obsidian)
- `revisao-plano-anuenio-cargo-esocial-2026-04-26` (Brain/Obsidian)
- Relatório estratégico curado (pesquisa externa + síntese técnica, 2026): decisões provisórias sobre Dossiê/terceirização, IPAM/prova de vida, COMPREV/Dataprev, eSocial, EFD-Reinf, Gov.br preposto e benchmarks — **sempre** subordinado a fonte primária e a PGM/IPAM/SEMFAZ/CGM (ver seção 14).

---

## 1) Princípios operacionais obrigatórios

1. **Linearidade com gates:** executar Sprint N+1 somente após aceite formal da Sprint N.
2. **Mudança pequena e verificável:** cada PR deve conter um objetivo único e smoke test associado.
3. **Sem regressão de rotas:** nenhuma rota nova de negócio entra em `web.php`.
4. **Configuração > código:** regras legais parametrizadas com vigência temporal sempre que possível.
5. **Segregação de domínios:** V1 (estatutário), V2 (terceirização), V3 (RPPS) sem mistura de motores.
6. **Segurança e auditabilidade primeiro:** hardening antecede integrações sensíveis.
7. **Rollback previsto:** toda etapa deve declarar procedimento de reversão.

---

## 2) Estado atual consolidado (foto 2026-04-26)

### 2.1 Já concluído
- Fase F rota/monólito (núcleo):
  - F.0 e F.1 concluídas (auditoria + duplicidades P0 removidas).
  - F.2 concluída (fachada modular com `api_v3_auth_part1.php`, `api_v3_auth_part2.php`, `api_v3_web_part1.php`, `api_v3_autocadastro_public_legacy.php` e `web.php` enxuto).
  - `GET /api/v3/ponto` unificada e nomeada (`api.v3.ponto.mes`).
  - F.4 piloto concluído (`->name(...)` em rota crítica).
- Documento de curadoria de pesquisa criado:
  - `deep-researach-pesqusias-gente.md`.
- **S0 (2026-04-26, execução no repo):** baseline de rotas (`docs/arquivo/baseline-routes-2026-04-26.json`), notas `docs/PROGRAMA_S0_BASELINE_2026-04-26.md`, template de PR/aceite/rollback, política de wiring (`ROUTE_WIRING_POLICY.md`).
- **F.3 / S2.1 (ferramenta local):** `gente/scripts/check_route_duplicates.php` + `composer run check-routes` (colisão `METHOD+URI`). *CI remoto ainda exige runner com banco acessível — vários ficheiros de rota tinham efeito colateral no `require`; ver S1 2026-04-27.*
- **S1 (mínimo técnico 2026-04-27):** documentação S1.2–S1.3; S1.4 — remoção de DDL no *load* de vários ficheiros em `routes/` (funções `ensure*FromRoutes`); `calendar_overrides` com erro explícito se faltar `migrate` (`feriados_v3.php`). `route:list` volta a ser leve. Matriz de **perfil** por módulo continua em `S1_INVENTARIO_RBAC` (não exige todo o domínio `api/v3` com `perfil:` nesta fase).
- **S2 (2026-04-27):** gate de duplicidade + `ROUTE_WIRING_POLICY.md` mantidos; dependência S1 (mínimo) atendida para aceite documental de S2.
- **S3 fase 1 (2026-04-27):** parâmetros de jornada em `config` + tabela `JORNADA_REGRA_PARAM`; regra 1/3 e teto 24h no acionamento de sobreaviso; comando + schedule de *preview* banco de horas; ver `docs/NOTA_S3_JORNADA_2026-04-27.md`. **Aceite completo S3** (cruzamento com folha, DSR automático, casos reais homologados) ainda requer ciclos com SEMAD/SEMFAZ.
- **S4 fase 1 (2026-04-27):** autorização nominal em progressão/promoção (`PROGRESSAO_AUTORIZACAO`), endpoint de autorização (`POST /api/v3/progressao-funcional/autorizar/{id}`), aplicação com `autorizacao_id` (ou ato implícito retrocompatível) e consumo da autorização na trilha; ver `docs/NOTA_S4_PROGRESSAO_2026-04-27.md`.
- **S5 fase 1 (2026-04-27):** transparência de terceirização com CPF mascarado na exportação e endpoint de dossiê (`GET /api/v3/transparencia/dossie-terceirizacao`); ver `docs/NOTA_S5_TERCEIRIZACAO_2026-04-27.md`.
- **S6 fase 1 (2026-04-27):** prova de vida RPPS/IPAM com inicialização idempotente por competência (`POST /api/v3/rpps/prova-vida/inicializar`) + comando agendado (`rpps:prova-vida-processar --inicializar`); ver `docs/NOTA_S6_RPPS_2026-04-27.md`.
- **S7 fase 1 (2026-04-27):** pipeline eSocial com enfileiramento, idempotência e retry/backoff (`POST /api/v3/esocial/eventos/{id}/enfileirar` + comando `esocial:processar-fila`); ver `docs/NOTA_S7_ESOCIAL_2026-04-27.md`.
- **S8 fase 1 (2026-04-27):** endpoint de observabilidade pública (`GET /api/v3/transparencia/observabilidade-integracoes`) com indicadores agregados de transparência/eSocial/RPPS; ver `docs/NOTA_S8_OBSERVABILIDADE_2026-04-27.md`.
- **S9 fase 1 (2026-04-27):** estabilização operacional com `gente:healthcheck`, scheduler diário, roteiro de regressão e runbook de operação assistida; ver `docs/NOTA_S9_ESTABILIZACAO_2026-04-27.md`.
- **S1 continuação (2026-04-27):** RBAC aplicado em mutações críticas RPPS/eSocial + migration canônica de prova de vida (`2026_04_27_123000_create_rpps_prova_vida_tables.php`) e remoção de DDL em runtime nesse fluxo; ver `docs/NOTA_S1_RBAC_MIGRACOES_2026-04-27.md`.

### 2.2 Em aberto crítico
- **RBAC fino (continuação de S1.1):** expandir `middleware('perfil:…')` para os demais domínios críticos (RPPS/eSocial/benefícios/afastamentos/medicina/SST/treinamentos/cnab/parâmetros/pesquisa já cobertos neste ciclo) com matriz aprovada pela SEMAD (ver `docs/S1_INVENTARIO_RBAC_2026-04-26.md`).
- **Migrations canónicas (continuação de S1.4):** substituir gradualmente `ensure*FromRoutes()` por migrations e remover DDL dos handlers (RPPS prova de vida + benefícios + anexo de afastamento + exame ocupacional + SST + treinamentos + agendamento/cnab/parâmetros/pesquisa já migrados neste ciclo).
- **S3 continuação:** integrar `ApuracaoPontoService` / holerite com `JornadaRegraParametros`; DSR e glosa automática; testes de homologação com competência real.
- **S4 continuação:** ligar anuênio ao motor de folha e exigir autorização explícita por perfil/política (retrocompatibilidade atual mantém `ato` implícito quando não houver `autorizacao_id`).
- **S5 continuação:** perfis RBAC de terceirização + workflow de evidências contratuais (NF/CND/FGTS/GPS) + identidade preposto em homolog.
- **S6 continuação:** bloqueio de folha acoplado ao motor de benefícios + regras extraordinárias (domiciliar/procurador) validadas com IPAM.
- **S7 continuação:** integração externa real eSocial/Reinf/COMPREV com credenciais homolog, XSD completo e retorno oficial.
- **S8 fase 2 (2026-04-27):** catálogo público versionado (`GET /api/v3/transparencia/catalogo-dados`) + documentação de trilha LGPD/evidências; ver `docs/CATALOGO_DADOS_PUBLICOS_S8_2026-04-27.md` e `docs/TRILHA_LGPD_S8_2026-04-27.md`.
- **S9 fase 2 (2026-04-27):** healthcheck com modo `--skip-db` validado para operação assistida em ambientes sem DB estável; pendente apenas aceite institucional formal de encerramento.
- V2 (terceirização) e V3 (RPPS) ainda sem cobertura mínima para “RPM completo”.
- Automação **GitHub Actions** do `check-routes` **opcional** após SQL no runner; localmente `composer run check-routes` com DB ativo.

---

## 3) Meta de programa (macro)

### Meta técnica
Transformar o GENTE em monólito modular auditável, com rotas governadas, segurança efetiva e evolução previsível.

### Meta de negócio
Consolidar:
- **V1** (servidores ativos) com regras de jornada/folha conformes.
- **V2** (terceirização) com segregação jurídica e transparência.
- **V3** (RPPS/IPAM) com trilha mínima previdenciária.

### Critério de conclusão do programa
- Todas as sprints S0..S9 concluídas com aceite.
- Zero bloqueador crítico **técnico** aberto no repositório (pendências institucionais devem constar como gate de aceite).
- Checklist de produção (seção 12) em 100%.

---

## 4) Arquitetura alvo obrigatória (TO-BE pragmático)

1. **Monólito modular (não microsserviços agora).**
2. **`web.php` como wiring** (grupos + requires), sem closures de negócio longas.
3. **Domínios separados:**
   - V1: RH/Folha/Jornada;
   - V2: Terceirização;
   - V3: RPPS.
4. **RBAC backend obrigatório por domínio e operação.**
5. **Audit trail estruturado para mutações críticas.**
6. **Scheduler idempotente e reentrante para regras temporais.**
7. **Parâmetros com vigência temporal para regras legais.**

---

## 5) Estratégia de execução linear (Sprints, Etapas e Subetapas)

## S0 — Congelamento, baseline e segurança de execução
**Objetivo:** preparar terreno para execução longa sem drift.

### S0.1 Baseline documental e técnico
- Consolidar links e versão canônica dos documentos no Brain/repo.
- Snapshot inicial de rotas (`route:list`) e estado de módulos críticos.

### S0.2 Regras de operação do programa
- Definir convenção de PR por escopo único.
- Definir template de “Aceite de Sprint” com checklist.

### S0.3 Rollback framework
- Definir procedimento padrão de rollback por sprint (documentado).

**Aceite S0:**
- Baseline gerado, checklist padrão aprovado, procedimento de rollback publicado.

---

## S1 — Hardening crítico (GO/NO-GO técnico)
**Objetivo:** remover bloqueadores de segurança/governança antes de evolução funcional pesada.

### S1.1 RBAC efetivo backend
- Aplicar autorização por perfil/escopo nas rotas críticas por domínio.
- Garantir 403 em acessos indevidos.

### S1.2 Auditoria aplicada
- Confirmar middleware de auditoria ativo nas mutações críticas.
- Validar gravação de trilha para operações-chave.

### S1.3 Higiene de segredos e repositório
- Eliminar exposição de credenciais e artefatos indevidos.
- Revisar política de `.gitignore` e arquivos sensíveis.

### S1.4 Migrações fora de rota (plano)
- Catalogar `Schema::create` em rotas e planejar migração para migrations canônicas.

**Aceite S1:**
- RBAC mínimo efetivo nas áreas críticas.
- Audit trail validada em fluxo real.
- Sem segredos sensíveis em arquivos versionados.
- Plano de migração canônica aprovado.

---

## S2 — Governança de rotas definitiva (fechar F.3)
**Objetivo:** impedir regressão estrutural de duplicidade e crescimento desordenado.

### S2.1 Gate anti-colisão
- CI falha em duplicidade `METHOD+URI`.

### S2.2 Política anti-closure de negócio
- Proibir rotas de negócio em closure no fluxo novo.

### S2.3 Budget e monitoramento do arquivo central
- Definir orçamento e métrica contínua de crescimento do arquivo de wiring.

**Aceite S2:**
- Pipeline bloqueia duplicidade de rota.
- Política anti-closure ativa e aplicada em PR.

---

## S3 — V1 Jornada/Frequência/Folha (núcleo legal-operacional)
**Objetivo:** fechar lacunas de regra de tempo e cálculo mais sensíveis.

### S3.1 Parâmetros legais com vigência
- Tolerância ponto, tetos de jornada, fatores HE, decaimento banco.

### S3.2 Sobreaviso
- Regra 24h + rubrica 1/3 + conversão ao ser convocado.

### S3.3 DSR e banco de horas
- Glosa automática por falta injustificada.
- Decaimento de créditos por janela temporal.

### S3.4 Scheduler resiliente
- Jobs idempotentes, reentrantes, sem sobreposição concorrente.

**Aceite S3:**
- Casos de teste de jornada/sobreaviso/banco/DSR aprovados.
- Sem divergência entre cálculo esperado e folha.

---

## S4 — V1 Carreira/Anuênio/Progressão (governança remuneratória)
**Objetivo:** implementar fluxo remuneratório auditável e juridicamente defensável.

### S4.1 Anuênio com autorização
- Detectar pendência automaticamente.
- Simular impacto LRF.
- Aplicar somente com autorização nominal e trilha.

### S4.2 Progressão horizontal/vertical
- Ajustes de regras e prevenção de passivo não controlado.

### S4.3 Integração com camadas de folha
- Garantir coerência entre adicional permanente e cálculo mensal.

**Aceite S4:**
- Fluxo “detectar -> simular -> autorizar -> aplicar” funcionando ponta a ponta.
- Auditoria rastreável por autor/data/fundamento.

---

## S5 — V2 Terceirização (segregação jurídica e operação)
**Objetivo:** consolidar gestão de terceirizados sem risco de subordinação indevida.

### S5.1 Perfis e responsabilidades
- `preposto`, `gestor_contrato`, `fiscal_tecnico`, `fiscal_administrativo`.

### S5.2 Limites de domínio
- Bloquear dependências indevidas V2 -> V1 (governança arquitetural).

### S5.3 Saúde ocupacional e acesso físico
- Fluxo documental (ASO/PCMSO/PGR) e bloqueios de acesso por validade.

### S5.4 Transparência terceirização (“Dossiê”)
- **Gate institucional:** confirmar com **PGM** número/ementa da lei municipal vigente e conjunto exato de campos publicáveis (não implementar lista agressiva sem parecer escrito).
- Portal/aba pública: metadados de contrato + alocação conforme lei local; **exportação** em formatos abertos (ex.: CSV; ODT opcional).
- **Face pública:** colunas alinhadas ao mínimo legal acordado (ex.: nome, função/cargo declarado, razão social empregadora, identificação do contrato administrativo, órgão/lotação) — sem remuneração individualizada nem CPF integral na API sem autenticação.
- **Serialização:** ofuscação/mascaramento de CPF e demais dados sensíveis na **camada backend** (Resources/policies), não só no front.
- **Workflow de evidências:** upload de NF e documentos (CND trabalhista, FGTS, GPS etc.) vinculado a contrato e medição, com trilha de quem aprovou.

### S5.5 Identidade do preposto (Gov.br e contingência)
- **Preferencial:** OIDC Login Único (Gov.br) com **PKCE** no SPA; ambiente de homologação antes de produção.
- **Vínculo PJ:** quando disponível no ecossistema federado, validar relação CPF↔CNPJ da contratada (escopos e APIs conforme manual vigente do provedor — não fixar nomes de escopo/API até leitura da documentação oficial).
- **Contingência:** segundo fator (ex.: TOTP) ou fluxo manual habilitado por ofício/cadastro controlado, para indisponibilidade do provedor ou exclusão digital; política de ativação definida com segurança da informação.

**Aceite S5:**
- Fluxo de fiscalização e preposto funcional.
- Trilhas de auditoria e segregação de responsabilidades validadas.
- Transparência: export e API pública conforme **parecer PGM** (ou registro explícito de pendência jurídica com data-limite).
- Identidade: fluxo preferencial Gov.br em homolog **ou** contingência aprovada e documentada pela SI.

---

## S6 — V3 RPPS/IPAM (núcleo mínimo previdenciário)
**Objetivo:** sair do estado embrionário e cumprir mínimo operacional previdenciário.

### S6.1 Prova de vida (regra local vigente — IPAM)
- Parametrizar a partir da **portaria/normativo IPAM** vigente (ex.: ciclo anual, recadastramento ampliado se aplicável, procedimentos ordinário vs extraordinário).
- **Máquina de estados** explícita no domínio V3: pendente → em análise → regular / bloqueado, com datas e motivo.
- **Scheduler:** transições automáticas (ex.: marcar pendência no início da janela regulamentar) com jobs **idempotentes** e sem corrida entre workers.
- **Bloqueio de folha:** quando a norma previr, integrar com motor de folha/benefício apenas após regra confirmada com IPAM (evitar bloqueio “por código” divergente do conselho).
- **Canais:** atendimento presencial com anexo e validação por servidor; trilha de auditoria (quem, quando, documento).
- **Evolução aspiracional:** prova por biovalidação/ Gov.br **após** adesão institucional do ente e definição de público elegível — não tratar como P0 sem termo de adesão e desenho de fallback.

### S6.2 Compulsória 75 anos
- Rotina automática com workflow formal.

### S6.3 CTC/DTC
- Fluxos distintos, critérios de elegibilidade e restrições.

### S6.4 Modalidades e reajustes
- Base para paridade/integralidade quando aplicável.

**Aceite S6:**
- Casos de uso previdenciários mínimos concluídos com rastreabilidade.

---

## S7 — Conformidade federal (eSocial/Reinf/CADPREV-COMPREV)
**Objetivo:** preparar integração regulatória com robustez operacional.

### S7.1 eSocial (órgão público / RPPS)
- **P0 de desenho** (confirmar com contabilidade/previdência e leiaute vigente): cadeia de empregador (`S-1000`), vínculos e cadastros que alimentem benefícios (incl. instituidor quando exigido pelo modelo), **benefícios RPPS** (`S-2400` e correlatos), **folha de benefícios** (`S-1207` ou equivalente na versão adotada).
- **Pré-validação local:** validação de XML contra **XSD oficial** antes de transmitir; regras de case/tipo e tabelas (ex.: natureza de rubrica) com DE/PARA versionado.
- **Assinatura e ambiente:** certificado e política de ambiente restrito/homologação conforme Serpro; testes de regressão automatizados para cenários-chave (vínculo, óbito, pensão, folha).
- **Dados legados:** auditoria de qualidade (instituidor vs beneficiário, rubricas) antes de primeiro envio em massa.

### S7.2 EFD-Reinf (tomador / terceirização)
- **Escopo mínimo típico** (validar com CGM/SEMFAZ e IN vigente): identificação do contribuinte (`R-1000`), retenções/contribuições sobre serviço tomado (`R-2010` onde aplicável), pagamentos/créditos a PJ (`R-4020` onde aplicável), **fechamento** periódico (`R-2099`); eventos retificadores quando houver retrabalho (`R-2098` etc.).
- **Encadeamento com V2:** aprovação da medição/fiscalização → geração/consolidação dos eventos → transmissão → **vínculo do recibo** ao passo de liquidação/pagamento (não “só contabilidade dias depois” sem amarração sistêmica).
- **Parametrização:** mapa de natureza de rendimento/códigos de retenção por tipo de contrato — responsabilidade compartilhada negócio + fiscal.

### S7.3 COMPREV / CADPREV / Dataprev (system-to-system)
- **Habilitação:** marketplace/portal desenvolvedor, aplicação registrada, credenciais OAuth2, subscrição por CNPJ do RPPS e responsável — **segregação absoluta** homolog vs produção (variáveis de ambiente, segredos, URLs).
- **Estado e consistência:** persistir status retornado (ex.: em exigência, aguardando compensação, compensado); dependências entre fluxos (ex.: pensão aguardando liquidação de aposentadoria) modeladas explicitamente.
- **Resiliência:** filas dedicadas, retry com **backoff** para 5xx/429; **idempotência** na submissão (chave de negócio ou mecanismo admitido pela API — confirmar no manual da API subscrita; não assumir cabeçalho genérico).
- **Observabilidade:** limites de taxa muitas vezes **não** publicados de forma explícita; prever throttling no gateway de saída e métricas de erro por endpoint.

**Aceite S7:**
- Geração e validação de artefatos mínimos por ambiente de homologação.
- Evidência de credenciais **somente** de homolog para testes integrados; nenhum job de dev apontando a produção federal.
- Runbook de erro (exigência, rejeição, timeout) com procedimento de correção.

---

## S8 — Transparência ativa, LGPD aplicada e observabilidade
**Objetivo:** elevar governança pública e confiabilidade operacional.

### S8.1 Publicação responsável de dados
- DPIA ou equivalente para conjunto de dados do Dossiê/terceirização; registro de base legal e finalidade.
- Dados públicos obrigatórios visíveis; dados sensíveis apenas com perfil e **finalidade** documentada (fiscalização, não portal aberto).
- **Performance:** relatórios/exportações massivas com estratégia de materialização/cache ou geração assíncrona (evitar lock e carga simultânea no SQL Server).

### S8.2 Observabilidade/SLO
- Métricas mínimas, saúde sistêmica e monitoramento de latência/erro.

### S8.3 Evidências para controle
- Trilhas e relatórios para auditoria interna/externa.

**Aceite S8:**
- Painel de monitoramento ativo.
- Política de transparência/LGPD operacionalizada.

---

## S9 — Estabilização final e transição para operação contínua
**Objetivo:** encerrar programa com estabilidade e governança contínua.

### S9.1 Regressão integral
- Rodada completa de testes funcionais e não funcionais.

### S9.2 Operação assistida
- Janela de hiper-care com runbooks de incidentes.

### S9.3 Encerramento formal
- Ata de conclusão com pendências residuais classificadas.

**Aceite S9:**
- Critérios de produção em 100% (seção 12).
- Programa concluído sem bloqueadores críticos.

---

## 6) Dependências entre sprints (ordem obrigatória)

- S1 depende de S0.
- S2 depende de S1.
- S3 depende de S2.
- S4 depende de S3.
- S5 depende de S2 e S3.
- S6 depende de S1, S2 e S4.
- S7 depende de S5 e S6.
- S8 depende de S1, S2 e S7.
- S9 depende de S3..S8.

---

## 7) Matriz de risco e contenção

| Risco | Severidade | Onde trata | Mitigação |
|---|---|---|---|
| Regressão de rota por duplicidade | Alta | S2 | Gate CI obrigatório |
| Acesso indevido por perfil | Alta | S1 | RBAC backend + testes de autorização |
| Cálculo remuneratório indevido | Alta | S3/S4 | Fluxo com simulação + autorização + auditoria |
| Mistura V1/V2 gerando risco trabalhista | Alta | S5 | Segregação de domínio + lint arquitetural |
| Lacuna previdenciária operacional | Alta | S6 | Núcleo RPPS mínimo com workflows |
| Falha em jobs temporais | Média/Alta | S3/S6 | Idempotência, reentrada e checkpoint |
| Exposição indevida de dados | Alta | S8 | Política pública/LGPD e revisão de serialização |
| Divergência código vs norma IPAM (prova de vida/bloqueio) | Alta | S6 | Parametrização com normativo + aceite formal IPAM |
| Implementação sem parecer PGM (campos do Dossiê) | Alta | S5/S8 | Gate jurídico antes de publicar colunas obrigatórias |
| Credenciais federais em ambiente errado | Alta | S7 | Segregação de .env, revisão de deploy, secrets manager |
| Dependência exclusiva Gov.br (SPOF) | Média | S5 | Contingência MFA/manual documentada |
| Rubrica local vs tabela eSocial/Reinf incorreta | Alta | S7/S4 | DE/PARA com contabilidade + validação XSD |

---

## 8) Estratégia de PR, release e rollback

### Política de PR
- Um objetivo por PR.
- PR pequeno e reversível.
- Checklist de aceite anexado no PR.

### Política de release
- Homologação obrigatória por sprint.
- Sem deploy de sprint sem aceite documentado.

### Rollback padrão
- Reversão de artefato da sprint.
- Reexecução de smoke mínimo.
- Registro de incidente e decisão de retomada.

---

## 9) Critérios de qualidade mínimos por sprint

1. Sem erro de sintaxe nos arquivos alterados.
2. Rotas críticas válidas e sem colisão.
3. Logs/auditoria consistentes nos fluxos alterados.
4. Regressão mínima dos módulos impactados executada.
5. Documentação da sprint atualizada antes de marcar “Feito”.

---

## 10) Quadro de execução (status vivo)

| Sprint | Estado | Responsável | Última atualização | Observação |
|---|---|---|---|---|
| S0 | Concluído | (repo) | 2026-04-26 | Baseline de rotas, PR/aceite, rollback, `composer run check-routes` |
| S1 | Concluído (fechamento técnico) | (repo) | 2026-04-27 | RBAC crítico ampliado + DDL removido de routes/*.php + migrations canônicas dos legados |
| S2 | Concluído | (repo) | 2026-04-27 | Anti-duplicidade, política de wiring; dep. S1 mín. OK |
| S3 | Concluído (fase 1) | (repo) | 2026-04-27 | `JORNADA_REGRA_PARAM`, 1/3 + teto 24h sobreaviso, cmd banco, teste unit. |
| S4 | Concluído (fase 1) | (repo) | 2026-04-27 | `PROGRESSAO_AUTORIZACAO`, autorizar->aplicar, trilha de consumo autorização |
| S5 | Concluído (fase 1) | (repo) | 2026-04-27 | Dossiê terceirização + CPF mascarado em transparência |
| S6 | Concluído (fase 1) | (repo) | 2026-04-27 | Prova de vida: inicializar + processar + scheduler mensal |
| S7 | Concluído (fase 1) | (repo) | 2026-04-27 | eSocial: enqueue + retry/backoff + scheduler |
| S8 | Concluído (fase 2) | (repo) | 2026-04-27 | Observabilidade + catálogo de dados públicos + trilha LGPD |
| S9 | Concluído (fase 2) | (repo) | 2026-04-27 | Healthcheck robusto (`--skip-db`) + regressão + runbook assistido |

---

## 11) Definição de pronto (DoD) por etapa

Uma etapa só pode ser encerrada quando houver:
- evidência técnica (resultado de validações),
- evidência funcional (fluxo de negócio testado),
- evidência documental (registro no plano + nota de sprint),
- aceite explícito do responsável de negócio/técnico.

---

## 12) Checklist de produção (gate final obrigatório)

### 12.1 Núcleo segurança e plataforma
- [ ] RBAC backend efetivo nas rotas críticas.
- [ ] Auditoria de mutações críticas validada.
- [ ] Sem duplicidade de rota em CI.
- [ ] Sem regras legais hardcoded sem parâmetro de vigência onde aplicável.
- [ ] Scheduler crítico idempotente/reentrante.
- [ ] Runbook operacional e plano de rollback publicados.

### 12.2 Domínios V1 / V2 / V3
- [ ] V1 estável (jornada/folha/carreira) com casos principais aprovados.
- [ ] V2 mínimo operacional (preposto/fiscais/transparência) aprovado.
- [ ] V3 mínimo operacional (prova de vida/compulsória/CTC-DTC) aprovado.

### 12.3 Transparência terceirização e LGPD (S5/S8)
- [ ] Parecer **PGM** (ou equivalente) sobre campos publicáveis e base legal citável nas telas/relatórios.
- [ ] Aba/portal “Dossiê” ou equivalente com tabela responsiva e exportação (CSV mínimo).
- [ ] Endpoints **públicos** sem token: sem CPF integral, sem remuneração individualizada de terceirizado.
- [ ] Política de transparência/LGPD operacionalizada (incl. revisão de serialização backend).

### 12.4 Conformidade federal (S7)
- [ ] eSocial: pré-validação **XSD** antes de transmissão; cenários P0 homologados no ambiente definido.
- [ ] EFD-Reinf: eventos acordados com CGM/SEMFAZ transmitidos em homolog; `R-2099` (ou fluxo de fechamento vigente) exercitado.
- [ ] COMPREV/Dataprev: fila assíncrona, estado persistido, retry/backoff; credenciais **apenas** homolog nos testes automatizados de integração.
- [ ] Integrações federais prioritárias homologadas no nível definido pelo programa.

### 12.5 Identidade preposto (S5)
- [ ] Fluxo Gov.br em homolog (OIDC + PKCE) **ou** plano explícito de exceção aprovado pela SI com prazo.
- [ ] Contingência (ex.: TOTP / cadastro manual) documentada e restrita a perfis administrativos.

---

## 13) Notas de alinhamento jurídico e institucional

1. Sempre validar referência legal municipal específica antes de fixar obrigação como “mandatória local”.
2. Em caso de conflito entre pesquisa externa e norma local vigente, prevalece a norma local até atualização formal.
3. Itens com dependência de credenciais institucionais (Gov.br/Dataprev/etc.) seguem trilha de habilitação administrativa paralela.
4. **Citação jurisprudencial** (ex.: STF/RE) não substitui parecer da **PGM** para desenho de dados públicos; usar apenas como contexto após checagem da tese vigente e pertinência.
5. Manuais federais (eSocial, Reinf, Login Único, Dataprev) **mudam de versão** — fixar em sprint a versão consultada e o responsável por atualização trimestral.

---

## 14) Complemento: pesquisa estratégica — decisões, benchmarks e lacunas

> Esta secção consolida o relatório de pesquisa estratégica (deep research + curadoria) como **insumo de produto**, não como fonte normativa. O que importa para o GENTE é o que estiver **confirmado** em norma local, parecer institucional ou manual oficial com versão.

### 14.1 Transparência municipal — “Dossiê das terceirizações”
- **Decisão de produto:** separar camada pública (mínimo legal + parecer PGM) de camada fiscal (dados trabalhistas/financeiros com RBAC).
- **Lacuna a fechar:** número e texto consolidado da lei no DOM (evitar citação apenas por “PL” ou edição do DOM sem número da lei).
- **Referência de UX (não jurídica):** capitais com portal tabular e exportação aberta podem inspirar layout e performance; **não** copiar campos que violem o parecer local.

### 14.2 IPAM — prova de vida e evolução Gov.br
- **Obrigatório (desde que na norma):** calendário, estados, bloqueio, exceções (ordinária/extraordinária), evidências e trilha.
- **Aspiracional P2:** biovalidação / dados federados cruzados — depende de adesão, elegibilidade do cidadão e política de inclusão digital.

### 14.3 COMPREV / Dataprev
- **Engenharia:** marketplace, OAuth2, filas, histórico de status, idempotência, backoff, observabilidade.
- **Lacuna:** limites de taxa explícitos costumam ser opacos — tratar com throttling defensivo e acordo operacional com o fornecedor do canal.

### 14.4 eSocial + RPPS
- **Engenharia:** XSD prévio, cadeia de eventos alinhada ao modelo do órgão, qualidade de dados legados (instituidor/beneficiário/rubricas).
- **Lacuna:** P0 exato de eventos por competência deve ser fechado em **mesa** com contabilidade/previdência e documentado no repositório (tabela DE/PARA).

### 14.5 EFD-Reinf (tomador)
- **Engenharia:** vínculo do recibo Reinf ao workflow de aprovação de fatura e à liquidação SEMFAZ.
- **Lacuna:** mapa fiscal completo por tipo de contrato — **CGM/SEMFAZ**.

### 14.6 Identidade federada — preposto
- **Direção:** OIDC Gov.br + PKCE no SPA; validação de vínculo com CNPJ conforme capacidades reais do ecossistema na data da integração.
- **Contingência:** MFA local ou processo manual com registro formal — evitar senha “própria” sem segundo fator para atores externos com dados sensíveis de milhares de trabalhadores.

### 14.7 Benchmarks (Curitiba, BH, Rio — exemplos citados na pesquisa)
- **Uso recomendado:** padrões de dados tabulares, separação servidor/terceirizado, exportação, ausência de salário nominal de terceirizado na face pública quando alinhado ao parecer jurídico.
- **Prioridade:** P2 (refino de arquitetura de informação); não bloqueia S0–S5.

### 14.8 Checklist de prontidão S6–S8 (visão integrada)

| Trilha | Critério objetivo |
|--------|-------------------|
| V3 / IPAM | Estados de prova de vida + jobs + bloqueio alinhados à norma; exceções documentadas |
| Federal | Homolog Dataprev/Serpro/RFB com segredos isolados; XSD eSocial; eventos Reinf acordados |
| V2 / Fiscal | Workflow NF/documentos → fiscal → gatilho Reinf → recibo antes de liquidação |
| Transparência | Aba Dossiê + export; API pública sem dados sensíveis conforme PGM |
| Identidade | Gov.br homolog + contingência aprovada pela SI |

---

## 15) Como usar este plano no próximo prompt de execução

Ao iniciar execução com agente:
1. Informar sprint-alvo (ex.: “Executar S1 completo”).
2. Exigir execução linear por subetapas.
3. Exigir validação e aceite antes de avançar.
4. Exigir atualização do quadro da seção 10 ao final de cada sprint.
5. Bloquear adiantamento de sprint fora da ordem de dependências.

---

## 16) Resumo executivo final

Este plano está organizado para virar uma **grande task de atualização do GENTE** com segurança:
- primeiro hardening e governança,
- depois regras centrais de V1,
- em seguida V2 e V3 com segregação,
- por fim conformidade federal, transparência e estabilização.

A execução linear por sprints com gates reduz risco de quebra sistêmica e evita avanço funcional sobre base instável.

O **complemento da secção 14** amarra decisões da pesquisa estratégica a **gates institucionais** (PGM, IPAM, CGM/SEMFAZ, SI) e a **critérios técnicos verificáveis** (XSD, filas, idempotência, segregação de ambientes), reduzindo surpresa nas sprints S6–S8.

Fecho técnico no repositório: varredura de DDL em rotas concluída, gate de colisão de rotas estável e trilha documental/operacional atualizada.

---

*Fim. Documento preparado para execução linear orientada por sprint, etapa e subetapa.*

---

## Complemento estratégico (2026-04-27)

Para a fase de prontidão operacional plena e expansão multi-município, usar em paralelo o plano complementar no Brain:
- `PLANO_PRONTIDAO_OPERACIONAL_E_EXPANSAO_GENTE_2026-04-27.md`

Esse complemento mantém o presente plano como base de execução técnica e adiciona:
- ordenação de risco (homologação matemática antes de upgrade LTS),
- blueprint de integração federal com DLQ/poison-pill,
- trilha de tenancy/MDM para expansão fora de São Luís.


