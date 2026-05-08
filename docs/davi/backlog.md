Visão de águia, pae. Você está com o mindset corretíssimo de quem entrega software de verdade: não adianta ter um cofre impenetrável se a casa ainda não tem teto para mostrar para o cliente. Colocar essas features no backlog para focar no fluxo principal do GENTE v3 é a decisão tática perfeita agora.

Preparei abaixo o resumo técnico e as minhas sugestões arquiteturais já formatados. Você pode copiar este bloco inteiro e colar direto no seu Obsidian ou documentação oficial do projeto.
📄 DOC: Arquitetura de Segurança de Alto Nível (Backlog - Visão Global/TI)

Contexto: Planejamento de estratégias de segurança na camada de aplicação (Zero Trust) para proteger o acesso privilegiado (Visão Onisciente/Bypass de Lotação) da equipe de TI e Gestores Globais, mitigando riscos de vazamento de credenciais e ataques internos, sem dependência inicial de VPN.

Status Atual: Mapeado para o BACKLOG. O foco atual é a entrega do MVP funcional (Motor de Regras PCCV, Escalas, etc.) para validação com os contratantes.
📌 1. Just-In-Time (JIT) Access & Sudo Mode (Aprimorado)

Conceito: O acesso global não é contínuo. Requer elevação de privilégio temporária.
Diretrizes acordadas:

    Tempo Flexível: 15 minutos é muito curto para o fluxo de trabalho da TI. O tempo de expiração da sessão Sudo deve ser ajustável (ex: 1 hora, ou renovável com atividade).

    UX Transparente: O frontend (Vue) deve exibir um cronômetro visual indicando o tempo restante do modo Sudo, evitando que a sessão caia no meio de uma operação crítica.

    Credencial Segregada: A senha de elevação não deve ser a mesma do login diário.

📌 2. Princípio dos Quatro Olhos (Maker-Checker)

Conceito: Nenhuma ação destrutiva ou de extração massiva pode ser feita por um único humano de forma unilateral.
Diretrizes acordadas:

    Implementar um fluxo de "Aprovação Pendente". O Admin A solicita/executa, o sistema retém a ação, e o Admin B recebe uma notificação para auditar e aprovar a execução final. Mitiga ataques de insiders e coerção.

📌 3. Context-Aware Middleware (Filtro Comportamental)

Conceito: Analisar as variáveis da requisição, não apenas o token.
Diretrizes acordadas:

    Geo-Velocity (Aprovado): Bloqueio automático de sessões que apresentam saltos geográficos impossíveis em curto espaço de tempo (ex: login em São Luís e ação sudo em São Paulo 10 minutos depois).

    User-Agent (Condicional): Mudanças abruptas de navegador/SO devem ser avaliadas junto com uma janela de tempo, para não punir usuários legítimos que apenas trocaram de máquina.

    IP Whitelist (Pausado): Validação estrita de IP ficará suspensa até a definição final da infraestrutura de rede e futura adoção de VPN pelos contratantes.

📌 4. Roteamento Fantasma & Autenticação Física (Futuro)

    Roteamento Fantasma (Security by Obscurity): Rotas de super admin retornarão 404 Not Found em vez de 403 Forbidden para usuários sem a flag de TI, invisibilizando a superfície de ataque para scanners automatizados.

    WebAuthn: Estudar futura substituição da senha de Sudo por chaves físicas (YubiKey) ou biometria.

💡 Sugestões Técnicas do Coach (Para considerar na implementação futura)

    Sudo Mode com PIN ou TOTP: Em vez de fazer o TI decorar duas senhas longas (a de login e a de Sudo), use a integração com Google Authenticator (TOTP) ou um "Master PIN" de 6 dígitos exclusivamente para ativar o Sudo Mode. É mais rápido de digitar, melhora a UX e é extremamente seguro se atrelado a um hardware.

    Comece pelo Fantasma: Quando for puxar isso do backlog, comece pelo Roteamento Fantasma (trocar 403 por 404). É uma alteração de 5 linhas no Handler de exceções do Laravel, não afeta a usabilidade da TI em nada e derruba 99% dos bots de varredura automatizada.

    Auditoria do Maker-Checker: O fluxo de dupla aprovação (Quatro Olhos) gera um engajamento político muito bom com os clientes (Prefeitura). Mostrar a eles que "nem o desenvolvedor chefe consegue apagar o banco sozinho" vende muita confiança.

EM Organograma deve ser possivel digitar o nome de um novo setor para evitar que fique com nome de sem diretoria. verificar como que faremos isso seguindo semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.


padronizar o funcionamento da escala Médica para escala de trabalho, escala médica que é o ideal para gestão de escala com o modelo kanban que temos, vamos espelhar o funcionamento. verificar também como isso está na nossa teia de aranha e se isso está devidamente conectado e seguindo semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.

ajustar para o dia da escala que ja passou não possa ser clicada para gerar um troca, atualmente quando cria da pra tela de troca inves de ser justamente um resumo do ponto eletronico dessa pessoa desse dia.

verificar se substituição de plantão está correto, o funcionamento era para que quando fosse solicitado uma troca ela so sai de pendente para aguardado solicitação, dai vai chegar uma notificação + as descrições da substituição para quem foi chamado para substituir que é algo que devemos melhorar, a substituição está com dados rasos para quem for susbstituir, não diz a hora, o dia e outras informações chaves. logo devemos criar um perfil de médico, colocar nele a Plantões Extras ele ter um " minhas susbstituições pedentes" onde ele vai aprovar essa substituição, caso ele esteja com horas pendentes ou ele esteja em debito com o sistema, aparece isso informando ele, devemos ver como funciona isso na secretária de são luís para que a gente siga o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe seguindo o sobreaviso, se alguém estiver em sobreaviso ela obrigatoriamente tem que trocar de plantão, ela so tem que confirmar isso mas caso ela não confirme e ou não vá isso deve ser descontado então isso entra na nossa teia de aranha para que o sistema fique completo.o fluxo é o rh recebe a solicitaçao de uma troca de escala, ele pode tanto alterar em escalas ou abrir uma novo chamado em substituição, fica a criteiro dele de escolher uma pessoa que ja esta sobreaviso ou qualquer outra pessoa, o substituto ele recebe a solicitação, se for fora de sobre aviso ele pode aceitar ou recusar se for uma pessoas sobreaviso não tem o que ela fazer ja vai ta lá a escala dela alterada(recebendo notifcação no email e no sistema) ou seja fica a criterio do RH buscar alguém para fazer isso.


temos que verificar se férias e licenças causa impacto na nossa teia de aranha do sistema para ver se ela está alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.

ver como que banco de causa impacto na nossa teia de aranha do sistema para ver se ela está alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.


ver como Hora Extra e Plantão Extra impacto na nossa teia de aranha do sistema para ver se ela está alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.


ver como Autocadastro impacto na nossa teia de aranha do sistema para ver se ela está alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.

verificar se Acumulação de Cargos e Cargos e Salários está em sua lógica de uso + lógica de código + conectividade entre abas estão, e se estão alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe.

em Gestão de Benefícios está sem front end completo para essa área, temos que desenvolve-la por completo.

em Contratos e Vínculos o meu cargo está como Data de Admissão—
CargoCarregando...
Setor—
Unidade—
Regime JurídicoEstatutário
PrevidênciaRPPS — IPAM São Luís
Matrícula—
CPF—
PIS/PASEP—
se percebermos isso nunca poderia acontecer no sistema, um perfil que não tivesse todos os dados que teoricamente são obrigatórios não preenchidos temos que verificar também está em sua lógica de uso + lógica de código + conectividade entre abas estão, e se estão alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe. ele deve-se está conectado a teia de aranha.

em Progressão Funcional temos que mudar o design dos botões que aceitam ou recusama progressão funcional de um servidor todos os botões dessa área estão bugados e errados. temos que verificar também está em sua lógica de uso + lógica de código + conectividade entre abas estão, e se estão alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe. ele deve-se está conectado a teia de aranha. o sistema está tão blindado que o fato do seed está incompleto(caso não tenhamos corrigido ainda) quando eu aplico uma progressão acontece "⚠️ Ato administrativo é obrigatório (ou informe autorizacao_id válida)." isso ta certo porque mesmo que a pessoa ganhe acesso ao sistema(um hacker) ele não consegue fazer uma mudança no sistema por não ter todos os dados e regras do sistema para operar, dando tempo de investigarmos e sanitizarmos o ataque, por isso a sentinela é importante.


verificar se Exoneração e Verbas Rescisórias segue a lógica de uso + lógica de código + conectividade entre abas estão, e se estão alinhada com o semed, as leis e o funcionamento da secretária de são luís sempre visando em ser melhor do que ja existe. ele deve-se está conectado a teia de aranha. quando eu tentei registrar uma exoneração o botão de confirmar não apareceu, não sei se é por conta de que eu não inseri um numero de portaria válido pro sistema, também vi que tem o recalcular, presumo que é o valor da recisão, porém durante regristramento de uma exoneração não aparece o valor calculado, isso deve ser investigado também, gostaria de entender mais como está o atual Exoneração e Verbas Rescisórias.



BACKLOG — Escala de Trabalho (UX Sticky da barra de turnos)

- Problema: a barra "Arraste os turnos" não acompanha a rolagem vertical do Kanban como o header fixo ("Escalas"). Ela só reaparece quando o scroll retorna para cima.
- Impacto: em listas grandes (milhares de linhas), o usuário perde referência dos turnos e aumenta risco operacional de erro.
- Evidências de debug (30/04/2026): o scroll principal ocorre no container `.page-content`; tentativas com sticky local + fixação manual na view não reproduziram o comportamento desejado de forma estável.
- Decisão: pausar esta correção nesta sprint e manter no backlog para solução arquitetural única no layout, reaproveitando a mesma estratégia de fixação da topbar.
- Próximos passos técnicos:
  1) extrair uma primitive de "sticky in `.page-content`" no layout;
  2) aplicar na barra de turnos sem lógica manual por tela;
  3) validar em macro, setor e cenários com Sudo ativo;
  4) garantir ausência de regressão em drag-and-drop, workflow e performance.


Opção B: O Painel Executivo (Dashboard de Lotação e Furo de Escala)

    O Contexto: Com a escala rodando e os afastamentos injetados, nós temos um volume de dados riquíssimo. O Secretário de Educação não vai abrir escola por escola; ele quer ver o cenário macro.

    O Desafio: Criar uma rota agregadora que calcule em tempo real: "Temos 3.000 professores escalados, 150 de Licença Médica, 40 de Férias. Nosso déficit operacional hoje é de X professores na região Y."

    A Entrega: Uma aba de Dashboard com indicadores (KPIs) usando o banco de dados robusto que acabamos de modelar, entregando na mão da gestão a ferramenta definitiva para tomada de decisão e remanejamento de substitutos.


📋 Backlog: Estratégia de Deploy de Dados (Prod-Ready)
1. Seeder de Configuração RBAC (Fase 3A)

    O que é: Materializar o arquivo rbac_matrix.v1.yaml no banco de dados de produção.

    Por que é crítico: Sem ele, nenhum usuário conseguirá logar ou terá permissão para visualizar o Kanban, pois os perfis (analista_sagep, gestor_unidade) não existirão fisicamente.

    Status: Aguardando conclusão da Fase 3A.

2. Mapeamento de Ocorrências SISFOLHA (Fase 2 - Refinamento)

    O que é: Garantir que a tabela TABELA_GENERICA (ID=5) em produção contenha exatamente os códigos que o SISFOLHA espera (01 - Licença Médica, 15 - Férias, etc.).

    Análise necessária: Verificar se os IDs do ambiente de desenvolvimento batem com os IDs reais do banco de dados oficial da Prefeitura.

3. Dicionário de Turnos e Regras de Escala

    O que é: Seeder com os turnos padrão da rede (M, V, N, I, F, SO) e suas respectivas cargas horárias.

    Por que é crítico: Para evitar que cada escola crie seu próprio "Turno Manhã" com horários diferentes, o que quebraria o cálculo de produtividade global.

4. Carga de Hierarquia Administrativa (Regra de Ouro)

    O que é: Script de migração/seed que garante a existência da "Unidade Matriz" (SEMED) e das "Unidades de Auditoria" (SEMAD).

    Por que é crítico: Garante que a árvore do multi-tenant tenha uma raiz sólida para o cálculo dos 25% do MDE.

🔍 Próxima análise de backfill:

Além desses, temos que analisar se as Tabelas Salariais da Lei 4.928/2008 precisam entrar como um seeder de configuração. Se o GENTE for calcular impacto financeiro futuramente, os valores de Vencimento Base por Classe/Nível precisam estar lá.



Segue o **relatório de viabilidade técnica e audit de impacto** cruzando o [`backlog.md`](gente/docs/davi/backlog.md), o início do [`BUSINESS_RULES.md`](gente/docs/davi/BUSINESS_RULES.md) e o estado provável do código (incluindo correções já feitas na “Batalha 3” / Teia).

---

## Critérios de priorização

- **P0 — Bloqueia independência operacional:** sem isso o sistema não cumpre decisão nem rastreio jurídico (MDE, lotação, RBAC em produção).
- **P1 — Bloqueia integração SISFOLHA / folha:** IDs e domínios divergentes geram erro silencioso ou retrabalho massivo.
- **P2 — Governança e UX de confiança:** aumenta adesão e reduz risco político, mas o core já “funciona” sem isso.

---

## 1. Domínio estrutural (organograma e hierarquia)

### 1.1 Regra de ouro: setores sem `unidade_id`

**Viabilidade:** alta no backend; média no produto (exige disciplina de cadastro).

- **Já alinhado ao decreto / BUSINESS_RULES:** “nenhum setor sem unidade pai” é condição necessária para **ratear despesa por unidade** e, na ponta, para **MDE (25%)** não virar chute.
- **Implementação recente (reduz regressão):** `PUT` de setor deixa de persistir `UNIDADE_ID = 0`; criação com nome novo de unidade passa por **confirmação explícita** no modal (evita “Sem Diretoria” por clique acidental).
- **Lacuna restante:** hierarquia **Secretaria > Superintendência > Diretoria > Setor** (Decreto 60.385/2024) — se o modelo de BD for só `UNIDADE` + `SETOR` plano, “diretoria” pode estar **semântica** no nome, não na chave estrangeira. **Risco:** MDE e relatórios executivos agrupam mal se “unidade” não for o mesmo conceito contábil que o TCE espera.

**Recomendação:** P0 para **validação de modelo** (tabela ou metadados de tipo de nó) + seed de “raiz SEMED / auditoria SEMAD” (já citado no backlog como Fase 3A).

### 1.2 RBAC, seeders e `TABELA_GENERICA` (SISFOLHA)

**Viabilidade:** alta com processo; **risco alto** se depender de auto-incremento igual entre ambientes.

- **Problema:** em SQL Server / ambientes distintos, **ID numérico ≠ código de negócio**. O SISFOLHA espera **códigos estáveis** (ex.: `01` LM, `15` FR); o GENTE deve amarrar por **`codigo` + domínio**, não por “linha 5”.
- **Estratégia deploy (Fase 3A):** materializar `rbac_matrix.v1.yaml` é P0 para **login e escopo**; para SISFOLHA, P1 com **seeder idempotente** (`upsert` por código), **tabela de mapeamento** GENTE↔SISFOLHA, ou **views** que expõem os códigos oficiais.
- **Regressão:** qualquer `where id = 5` em código ou relatório quebra ao migrar.

---

## 2. Domínio operacional (escalas e substituições)

### 2.1 Padronizar “Escala Médica” → “Escala de Trabalho”

**Viabilidade:** média (refatoração de componentes e contratos de API), **alto valor**.

- **Abordagem genérica:** extrair **primitive Kanban** (toolbar sticky, workflow, células, afastamentos, macro paginada) para um composable ou layout partilhado; variar só **fonte de turnos**, **regras PCCV** e **endpoint**.
- **Risco de regressão:** `escala-trabalho` já tem workflow, Sudo, SEMAD, PCCV — espelhar sem testes e2e aumenta bugs em **drag-and-drop** e **status da escala**.

### 2.2 Substituições: notificações “rasas” + sobreaviso + desconto

**Viabilidade:** enriquecer payload — **alta** (já há campos na API se existirem colunas). **Desconto automático por não confirmação** — **baixa/média** (exige política de folha, tabela de lançamentos, acordo com RH jurídico).

- **Hora / dia / local:** dia e turno já existem no fluxo; horário/local dependem de **persistência** e de **UI** consistentes; notificações no backend estão ligadas ao fluxo `POST /substituicoes`, não ao simples `POST escala-trabalho` — **lacuna de produto** para alterações só de grade (ex.: SO).
- **Sobreaviso “obrigatório” + penalidade:** é **regra de negócio + financeiro** (não só notificação). Viável como: estado `confirmada` / prazo / job noturno que gera **evento de folha** ou **débito em banco de horas** — P1/P2 conforme definição SEMED.

### 2.3 Bloqueio retroativo → resumo de ponto

**Viabilidade:** **implementada** no sentido “clique em dia passado não abre edição”: modal de leitura + `GET /ponto` com `funcionario_id` sob escopo + deep link para Ponto. **Risco residual:** permissões, caches e “utilizador sem escopo no setor” (403 tratado na UI).

---

## 3. Domínio RH e ciclo de vida (PCCV e rescisões)

### 3.1 Progressão funcional (aceite/recusa + ato administrativo)

**Diagnóstico alinhado ao backlog:** mensagem **“Ato administrativo é obrigatório…”** é **comportamento desejado da sentinela**, não bug de segurança — o que pode estar “bugado” é **UX** (botões sem estado claro, sem explicação, ou sem caminho para anexar `autorizacao_id` / portaria).

**Viabilidade:**
- **Backend:** obrigatoriedade do ato no **servidor** (já coerente com “hacker não caneta”).
- **Frontend:** P1 — desabilitar com tooltip, wizard “documento → preview → confirmar”, ligação ao dossiê digital.

**Regressão:** endurecer só no SPA sem reforço na API reintroduz risco.

### 3.2 Exoneração (botão confirmar + preview)

**Estado:** preview falho deixava `calculo` nulo → botão **sempre desabilitado** (parecia “sumido”). **Correção:** feedback de erro + portaria mínima para carimbo + mensagens no `registrar`.

**Lacuna:** auditoria “RH viu os valores antes da baixa” — P1 se precisar de **PDF/versão congelada** ou segunda etapa “homologar rescisão”.

### 3.3 Vínculos / “Carregando…” eterno

**Correção:** fallback e erro limpam o placeholder e mostram banner — **não substitui** validação de negócio “matrícula/PIS obrigatórios para perfil X”: isso é **P1** (regra por perfil + bloqueio de ações sensíveis até completar cadastro na Teia).

---

## 4. Domínio segurança e TI (Sudo e Zero Trust)

| Tema | Viabilidade | Risco de regressão |
|------|-------------|-------------------|
| **Sudo PIN/TOTP + TTL configurável** | Alta | Sessão a cair a meio de operação; precisa contador no SPA + renovação segura |
| **Roteamento fantasma (404 vs 403)** | Alta (“poucas linhas” no handler) | **Médio:** clientes que parseiam 403 para “sem permissão” passam a ver 404 — ajustar SPA e logs |
| **Maker-checker** | Média | Novas tabelas (`pending_action`, aprovador, expiração), filas, notificações; impacto em **todas** ações destrutivas definidas |

**Prioridade estratégica:** fantasma + auditoria primeiro; maker-checker como **onda 2** com escopo fechado (ex.: exclusão em massa, exportação sensível).

---

## 5. Visão estratégica — dashboard executivo e MDE

**Fórmula desejada:** \( V_{MDE} = 0{,}25 \times (T_{municipais} + T_{transferidos}) \) — no texto do backlog/LOM isso liga-se ao **Art. 139** e à **segregação** de despesas de educação.

**Viabilidade da rota agregadora:**
- **Tecnicamente alta** se existirem **fontes únicas**: lotação ativa → `unidade_id` → classificação MDE; afastamentos/férias por código SISFOLHA; escala do dia.
- **P0 de dados:** sem **unidade** e **código de ocorrência** estáveis, o KPI é **marketing**, não controle.

**Proposta de rota (conceito):** `GET /api/v3/dashboard/operacional` com parâmetros `data`, `regiao?`, agregações pré-calculadas em SQL (ou materialized view refresh incremental) para não derrubar o OLTP.

---

## Matriz “o que impede independência vs SISFOLHA”

| Item | Independência (operar SEMED) | Integração SISFOLHA |
|------|------------------------------|---------------------|
| Hierarquia + `unidade_id` | **P0** | **P0** (base de rateio) |
| RBAC Fase 3A em produção | **P0** | P2 |
| `TABELA_GENERICA` / ocorrências por **código** | P1 | **P0** |
| Seeder turnos canónicos (M,V,N,…) | P1 | P1 |
| Substituição rica + sobreaviso penal | P2 | P1 |
| Dashboard déficit + MDE | P1 | P1 |
| Sudo + fantasma + quatro olhos | P2 (TI) | P2 |

---

## Síntese do arquiteto (alinhamento à tua narrativa)

- **“Furo” do organograma:** a regra de ouro não é só UX — é **pré-requisito de responsabilização** e de **MDE**; a criação on-the-fly com confirmação aproxima o sistema do Art. 136 / expansão da rede, desde que o **tipo de unidade** no modelo contábil esteja explícito.
- **Sobreaviso “carrasco”:** viável como **produto + folha**; não como só mais um `NOTIFICACAO_BODY` — exige **estado**, **prazo** e **motor de encargos** acordado com RH/jurídico.
- **Progressão “travada”:** a sentinela do ato administrativo é **vitória de desenho**; o trabalho pendente é **tornar o fluxo óbvio** para o utilizador legítimo, sem enfraquecer o backend.

---

## Checklist executável — Teia de Aranha / GENTE v3

Legenda: **P0** bloqueia operação ou integração crítica · **P1** alto impacto · **P2** governança/UX. Owners: **BE** backend · **FE** frontend · **DBA** dados/migração · **RJ** RH + jurídico (regra de negócio) · **TI** infra/segurança.

Marcar `[ ]` → `[x]` quando concluído (e opcionalmente data + PR na mesma linha).

### A. Deploy, dados e SISFOLHA

| ID | P | Owner | Tarefa | Aceite (definição de pronto) |
|----|---|-------|--------|------------------------------|
| BL-TEA-001 | P0 | DBA+BE | Correr `RbacMatrixSeeder` / Fase 3A em **staging** espelho de prod | Utilizadores teste com slugs; `/api/auth/me` com `rbac_permission_slugs` não vazio para perfis canónicos |
| BL-TEA-002 | P0 | DBA+BE | Inventariar **código** vs **ID** em `TABELA_GENERICA` (ocorrências SISFOLHA) | Documento ou migration: nenhuma regra de negócio depende de `WHERE id = N` sem mapeamento oficial |
| BL-TEA-003 | P1 | DBA+BE | Seeder **idempotente** de ocorrências (upsert por código `01`, `15`, …) | Diff entre dev/staging/prod só em dados voláteis, não em códigos oficiais |
| BL-TEA-004 | P1 | DBA+BE | Seeder de **turnos canónicos** (M,V,N,I,F,SO) + carga horária | Tabela `TURNO` (ou equivalente) alinhada à rede; documentado em `BUSINESS_RULES` ou runbook |
| BL-TEA-005 | P0 | DBA+BE | Seed/migration **hierarquia matriz** (SEMED raiz, unidades auditoria SEMAD) | Árvore mínima criada; nenhum setor novo sem caminho para unidade válida |
| BL-TEA-006 | P1 | DBA+RJ | Backfill **Tabela salarial Lei 4.928/2008** (seeder ou import) | Progressão/rescisão conseguem ler vencimento base por classe/ref. em ambiente limpo |

### B. Organograma e MDE

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-010 | P0 | RJ+BE | Validar modelo **Decreto 60.385** (níveis Secretaria→Setor) vs BD atual | Decisão escrita: campos/tipos de nó suficientes para rateio MDE ou plano de extensão de schema |
| BL-TEA-011 | P1 | BE | Constraint ou job: **alertar** setores com `UNIDADE_ID` inválido / órfãos legados | Relatório ou painel interno lista exceções; PUT já rejeita `0` (código entregue) |
| BL-TEA-012 | P2 | FE | UX: criar unidade on-the-fly com texto de conformidade SEMED (já há confirmação) | Tooltip/help alinhado ao `BUSINESS_RULES` |

### C. Escalas, substituições, sobreaviso

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-020 | P1 | FE+BE | **Primitive Kanban** partilhada Escala Médica ↔ Escala Trabalho (composable/layout) | Mesma barra de turnos/workflow/células; e2e smoke em ambas |
| BL-TEA-021 | P2 | FE | Sticky barra de turnos via **layout** `.page-content` (ver secção BACKLOG sticky acima) | Scroll longo mantém legenda de turnos; sem regressão DnD |
| BL-TEA-022 | P1 | BE | Notificação rica: **corpo** com data, turno, local, solicitante (template) | `NOTIFICACAO_BODY` ou payload JSON legível pelo SPA Minhas Substituições |
| BL-TEA-023 | P1 | BE+FE | Fluxo **sobreaviso**: escala alterada + notificação + estado `confirmada` / prazo | RJ assina fluxo; critérios de aceite/recusa documentados |
| BL-TEA-024 | P2 | RJ+BE | **Desconto / folha** se sobreaviso não confirmado ou falta | Evento em fila ou tabela de lançamentos; integração com folha definida |
| BL-TEA-025 | P1 | FE | Perfil médico: **Plantões Extras** + **Minhas substituições pendentes** com saldo/débito | Rotas + RBAC; empty states e bloqueios conforme RJ |
| BL-TEA-026 | P2 | BE | `POST escala-trabalho` em turno SO: opcionalmente **eco** notificação ou fila única de “alertas escala” | Decisão única documentada (evitar duplicar com substituição) |

### D. RH, vínculos, progressão, exoneração

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-030 | P1 | BE+RJ | Regra **cadastro mínimo** (matrícula/CPF/PIS) por perfil antes de ações sensíveis | API retorna 422 claro; FE mostra gating na Teia |
| BL-TEA-031 | P1 | FE | **Progressão:** redesign aceite/recusa + fluxo ato/`autorizacao_id`/portaria | Nenhum botão “morto”; sentinela mantida no BE |
| BL-TEA-032 | P1 | BE | Garantir `autorizacao_id` / documento na **API** de progressão (revalidar) | Teste automatizado ou manual script com caso negativo |
| BL-TEA-033 | P2 | FE+BE | **Exoneração:** PDF ou snapshot do preview para auditoria RH | Opcional P2; ou “homologar rescisão” em 2 passos |
| BL-TEA-034 | P2 | FE | Contratos: quando dados ausentes, **CTA** “Completar cadastro” com deep link | Melhora além do banner de fallback já entregue |

### E. Segurança, Sudo, Zero Trust

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-040 | P2 | TI+BE | **Roteamento fantasma** (403→404 em rotas sensíveis não-TI) | SPA não depende de 403 nessas rotas; logs internos mantêm diagnóstico |
| BL-TEA-041 | P2 | FE+BE | Sudo: **TTL configurável** + contador regressivo no layout | Config documentada; renovação segura |
| BL-TEA-042 | P2 | TI+BE | Sudo: **PIN ou TOTP** segregado do login | Fluxo de elevação testado; lockout/throttle |
| BL-TEA-043 | P2 | BE+FE | **Maker-checker** (escopo fechado v1: ex. exportação massiva ou delete) | Tabela pendências + notificação aprovador + auditoria |

### F. Dashboard executivo e MDE

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-050 | P1 | BE+RJ | Especificar **fontes** do KPI: escalados, LM, férias, déficit, \(V_{MDE}=0{,}25\times(T_{mun}+T_{transf})\) | **MVP Fase 9A:** fórmula e `nota_fonte` no JSON da rota; T_municipais/T_transferidos **null** até integração contábil; contagem SEMED via `config/gente_executive_dashboard.php` (`mde_unidade_siglas`, default SEMED). Assinatura RJ completa fica Onda 2. |
| BL-TEA-051 | P1 | BE | Rota agregadora `GET /api/v3/dashboard/operacional` (params `data`, `regiao?`) | **Entregue:** `DashboardOperacionalController` + cache TTL `GENTE_EXEC_DASHBOARD_CACHE_TTL` (default 90s); critério de furo alinhado a `escala-saude/furos`; `regiao` só filtra se existir `SETOR.SETOR_REGIAO`. |
| BL-TEA-052 | P1 | FE | Aba **Painel executivo** (KPIs + drill-down escola/região) | **MVP:** `DashboardExecutivoView.vue` + `/dashboard-executivo` + sidebar; CTA “Auditar escalas” → `/escala-trabalho`; timestamp de cache na UI; drill-down geográfico fino fica Onda 2. |

### G. Integrações transversais (Teia)

| ID | P | Owner | Tarefa | Aceite |
|----|---|-------|--------|--------|
| BL-TEA-060 | P1 | BE | Auditoria: movimentação de **lotação** sempre com unidade/setor válidos | Violacao gera bloqueio ou alerta crítico |
| BL-TEA-061 | P2 | BE+FE | Férias/licenças: matriz de impacto na escala + folha (documento) | Lista de eventos que invalidam/atualizam grade |
| BL-TEA-062 | P2 | FE | Benefícios: **MVP front** área Gestão de Benefícios | Navegação + lista + 1 fluxo CRUD crítico |
| BL-TEA-063 | P2 | BE+FE | Acumulação + Cargos/Salários: navegação cruzada e mesma fonte de cargo/salário | Link bi-direcional ou painel “ver na Teia” |
| BL-TEA-070 | P1 | BE+TI | Smoke **Fase 7A** `php artisan gente:smoke-teia-7a` + `SmokeTeiaFolhaRunner` | CI/local: JSON com `pass`, `fail` ou `skip` por fluxo; rollback por omissão; documentado em `docs/davi/pendencias.md`; SKIPs até motor LM↔DETALHE e auditoria assignment (rota `dashboard/operacional` entregue — smoke CLI não a chama). |

### H. Entregas já feitas (referência — não reabrir sem regressão)

- **Organograma:** PUT sem `UNIDADE_ID = 0`; modal com confirmação explícita para criar unidade nova.
- **Substituições:** `POST /substituicoes` com `escala_id` + horário/local quando há cabeçalho de escala; toast se visão macro sem `escala_id`.
- **Escala retroativa:** clique abre modal resumo ponto + `GET /ponto?funcionario_id=` com escopo; deep link para Ponto.
- **Exoneração:** feedback de preview; portaria mínima para confirmar.
- **Contratos/Vínculos:** banner e estado “—” em fallback/erro (não “Carregando…” eterno).

---

*Última atualização do checklist: checklist executável acrescentado ao `backlog.md` (estrutura BL-TEA-xxx).*


