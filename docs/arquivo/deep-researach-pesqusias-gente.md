# Deep Research — Pesquisas GENTE (curadoria útil)

**Data:** 2026-04-26  
**Origem:** consolidação de pesquisas longas (arquitetura, normativos, RPPS, terceirização, LGPD, eSocial, COMPREV)  
**Objetivo:** separar somente o conteúdo acionável para evolução do GENTE no contexto SEMAD/SEMFAZ/IPAM (São Luís).

---

## 1) O que aproveitar como diretriz arquitetural

### 1.1 Modelo de identidade (manter e formalizar)
- Adotar padrão **Person-First** com `PESSOA` como âncora demográfica.
- Manter motores de regra separados por vertical:
  - **V1**: `FUNCIONARIO` (estatutário/ativo)
  - **V2**: `TERC_*` (terceirização)
  - **V3**: `RPPS_*` (inativos/pensionistas)
- Usar `PESSOA` para reconciliação e analytics, **não** para mesclar regras de folha/regime.

**Decisão prática para o Brain:** reforçar regra de não cruzamento funcional V1↔V2↔V3 como princípio de arquitetura.

### 1.2 Evolução de monólito (escolha técnica)
- Priorizar **Monólito Modular** (DDD + bounded contexts), não microsserviços agora.
- Justificativa: dependências de domínio fortes (folha, ponto, escalas, previdência), menor risco operacional.
- Modularização progressiva por domínios: RH Core, Folha, Jornada/Ponto, Terceirização, RPPS, Transparência.

**Decisão prática para o Brain:** manter estratégia de modularização gradual e adiar split em microsserviços.

### 1.3 Governança de rotas (já alinhada com Fase F)
- Banir criação de rotas novas com closures de negócio.
- Controlar duplicidade de URI por CI (`route:list --json` + script de colisão).
- Meta contínua: `web.php` apenas wiring/require, sem regra de negócio.

**Decisão prática para o Brain:** tratar como política obrigatória de PR.

---

## 2) Regras de negócio que entram no backlog (alto valor)

### 2.1 Jornada/Folha (V1)
- Formalizar parâmetros em configuração com vigência:
  - tolerância ponto (15 min),
  - teto diário/semanal,
  - fator hora extra dia útil/feriado,
  - decaimento banco de horas.
- Sobreaviso:
  - limite 24h por escala,
  - pagamento 1/3 da hora normal,
  - conversão automática para hora efetiva quando convocado.
- DSR:
  - glosa automática por falta injustificada na semana.

### 2.2 Anuênio (correção importante de governança)
- **Não** aplicar anuênio por incremento automático sem autorização final.
- Fluxo recomendado:
  1) cron detecta elegibilidade/pêndencia,
  2) RH simula impacto LRF,
  3) autoridade aplica com trilha auditável.
- Manter cálculo técnico no motor como apoio/estimativa, mas com governança de autorização administrativa.

### 2.3 Progressão, carreira e eSocial
- Modelar natureza/categoria eSocial por cargo/vínculo para reduzir inferência frágil.
- Backlog de conformidade federal:
  - ampliar eventos eSocial necessários para RPPS,
  - abrir trilha para EFD-Reinf na parte de terceirização.

### 2.4 Terceirização (V2)
- Implementar papéis formais:
  - `preposto`, `gestor_contrato`, `fiscal_tecnico`, `fiscal_administrativo`.
- Não permitir subordinação direta no sistema (evitar risco trabalhista).
- Implementar dossiê de terceirização em transparência ativa.
- Crachá/catraca com bloqueio por ASO vencido como módulo específico.

### 2.5 RPPS/IPAM (V3)
- Prioridades mínimas:
  - prova de vida com regra local vigente,
  - compulsória aos 75 anos,
  - CTC/DTC com regras de elegibilidade,
  - modalidade de benefício (paridade/integralidade quando aplicável).

---

## 3) Integrações e conformidade (o que é útil agora)

### 3.1 COMPREV/CADPREV
- Pesquisas indicam caminho por APIs oficiais/marketplace.
- Recomendado tratar por jobs assíncronas + retry/circuit breaker.
- **Ação útil imediata:** preparar arquitetura e contratos de integração; produção depende de credenciais institucionais.

### 3.2 Prova de vida passiva
- Útil como evolução, mas deve respeitar norma local vigente.
- Tratar como modelo híbrido:
  - regra ativa/local obrigatória,
  - passiva como complementar (opt-in por regulação).

### 3.3 Transparência x LGPD
- Publicidade de dados públicos deve prevalecer no que é legalmente exigido.
- Blindar dados sensíveis (saúde, contatos, dados privados não necessários ao controle social).
- Exigir taxonomia de rubricas para evitar vazamento por nomes ambíguos.

---

## 4) Engenharia de confiabilidade (SRE / operação)

- Manter Scheduler Laravel com:
  - `withoutOverlapping`,
  - idempotência,
  - processamento em lotes (`chunkById`),
  - checkpoints por competência.
- Evitar jobs de longa duração sem particionamento (risco de deadlock/timeout).
- Definir SLOs operacionais mínimos:
  - uptime mensal 99,9%,
  - P95 API < 400ms,
  - RTO explícito para recuperação.

---

## 5) Ferramentas recomendadas para o projeto

- **Deptrac** (ou equivalente) para impor fronteiras entre módulos no CI.
- Testes de arquitetura para bloquear dependências indevidas V2→V1.
- Pacotes de auditoria/atividade para trilha append-only em entidades críticas.

---

## 6) O que considerar com cautela (não adotar cegamente)

- Migração SQL Server → PostgreSQL é estratégica, mas de alto risco/custo agora.
- Upgrade Laravel 8 → 11 deve vir após estabilização de regras críticas e cobertura de testes.
- Prova de vida passiva não deve substituir regra local obrigatória sem ato normativo específico.
- Evitar citar lei municipal específica não confirmada; usar base legal já consolidada até validação jurídica.

---

## 7) Must-have / Should-have / Nice-to-have (resumo para decisão)

### Must-have
1. Reforçar segregação de domínios e identidade tripla (V1/V2/V3).
2. Consolidar governança de rotas (anti-duplicidade + sem closures de negócio).
3. Parametrizar regras legais com vigência temporal (sem hardcode).

### Should-have
1. Deptrac no CI para limites de arquitetura.
2. Trilha RPPS mínima (prova de vida + compulsória 75 + CTC/DTC).
3. Dossiê de terceirização com transparência ativa.

### Nice-to-have
1. Integrações passivas ampliadas (Gov.br/SUS/DETRAN) quando normativamente cabível.
2. Painéis analíticos de transparência com réplica/BI.
3. Internacionalização e suporte multi-jurisdição em fase posterior.

---

## 8) Anti-padrões a evitar (registrar no Brain)

1. Misturar estatutário, terceirizado e inativo no mesmo motor de regra.
2. Concentrar regra de negócio em `web.php` e closures extensas.
3. Usar LGPD para ocultar indevidamente dados que devem ser públicos por transparência.

---

## 9) Encaminhamento sugerido para o Brain

- Relacionar este documento com:
  - `PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26.md`
  - `BRAIN_REGRAS_SEMAD_SLZ_JORNADA_2026-04-26.md`
  - `BRAIN_AUDITORIA_GERAL_PRODUCAO_2026-04-26.md`
- Usar este arquivo como **curadoria de pesquisa** (insumo), não como fonte normativa final.
- Toda decisão jurídica definitiva deve ter validação com Procuradoria/Controle interno.

---

*Fim — curadoria Deep Research para uso prático no Brain do GENTE.*
