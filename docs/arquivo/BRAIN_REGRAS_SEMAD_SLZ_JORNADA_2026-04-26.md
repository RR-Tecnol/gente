# BRAIN — Regras de negócio SEMAD / São Luís, jornada e conformidade com o GENTE

> **Espelho no repositório Git.** A documentação canónica de produto deste BRAIN fica no **cérebro Obsidian** (vault `Obsidian-Brain-v6`):  
> `…/_Global/PROJETOS/RRTECNOL/GENTE/auditorias/BRAIN_REGRAS_SEMAD_SLZ_JORNADA_2026-04-26.md` (caminho absoluto local: `~/brain/Obsidian-Brain-v6/...`).  
> Trabalhe no Obsidian; alinhe este ficheiro em *commit* para o *team* e CI. *Links:* [Plano de implementação](PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26.md) (também espelhado em `GENTE/planos/` no Obsidian).

**Data:** 2026-04-26
**Âmbito:** Secretaria Municipal de Administração (SEMAD) e módulos de jornada, escalas, sobreaviso, substituições, declarações e navegação (links).
**Objetivo:** Documentar o que o projeto **já implementa**, o que foi **definido verbalmente** neste fórum, **lacunas** e um **plano de implementação** unificado.

---

## 1) Enquadramento institucional (diretriz de produto)

| Diretriz | Significado no GENTE |
|----------|----------------------|
| Sistema calibrado para **São Luís** e para a realidade da **administração pública municipal** | Nomenclaturas, fluxos (gestor, secretaria, unidade) e relatórios devem refletir essa realidade; integrações *genéricas* (ex.: TCE-MA, transparência) são esperadas. |
| Foco na **SEMAD** como eixo de **Secretaria de Administração** | Cadastros centrais, contratos administrativos, pessoal, frequência e folha convergem para o “núcleo” administrativo descrito na [teia modular](BRAIN_ATUALIZACAO_TEIA_MODULAR_2026-04-26.md). |

**Estado no repositório:** não existe um documento único de “constituição SEMAD” no código; a regra acima fica **registrada aqui** como fonte de verdade de produto até haver `docs/SEMAD.md` ou regra de Cursor, se o time quiser.

---

## 2) Gestão de Declarações (modelo + aprovação / rejeição)

### 2.1 Regra de negócio (o que pediu)

- **Criar declaração** que vire **modelo** no sistema (template reutilizável).
- **Aprovar e rejeitar** pedidos (workflow de gestão).

### 2.2 O que o GENTE faz hoje (evidência no código)

- **Colaborador / requerimentos:** rota `declaracoes-requerimentos` → `DeclaracoesRequerimentosView.vue`
  - Consome `GET/POST /api/v3/declaracoes` (fluxo de **requerimentos** do servidor).
- **RH / gestão:** rota `gestao-declaracoes` → `GestaoDeclaracoesView.vue`
  - Consome `GET /api/v3/rh/declaracoes` e `PATCH /api/v3/rh/declaracoes/{id}` com `status` e `obs` (aprovação/rejeição com observação).

**Sidebar:** em “Minha área” o item chama-se **“Declarações”** (`/declaracoes-requerimentos`); em “Recursos Humanos / Financeiro” existe **“Gestão de Declarações”** (`/gestao-declaracoes`). O **breadcrumb** do layout usa `routeMap`: rótulos distintos **“Declarações e Requerimentos”** vs **“Gestão de Declarações”** — coerente com papéis diferentes.

### 2.3 Lacunas / risco

- A **conversão explícita** “texto solto → modelo oficial da SEMAD” pode estar **parcial** se o backend de `rh/declaracoes` não persistir `template_id` ou versão; isso exige leitura do endpoint em `web.php` / rotas RH no ambiente alvo.
- **Recomendação:** checklist de homologação: criar requerimento → aparecer na gestão → aprovar → PDF/HTML gerado com número/ano.

**Conformidade:** **parcial** — as duas frentes (pedido vs gestão) existem; falta prova documentada de “modelo SEMAD” versionado se isso for obrigatório legalmente.

---

## 3) Choque de escala (mesma pessoa em dois lugares ao mesmo tempo)

### 3.1 Regra de negócio

- Impedir ou **alertar** quando o **mesmo funcionário** estiver escalado em **dois conflitos incompatíveis** no **mesmo período** (mesma competência / mesmos dias e turnos que se sobreponham, ou duas escalas no mesmo mês em setores diferentes, conforme política interna).

### 3.2 O que o GENTE faz hoje

1. **Validação na criação de detalhe de escala (Laravel “clássico”):**
   - `DetalheEscalaCreateRequest` aplica a regra `HorariosConflitantes` sobre `detalheEscalaItens` **dentro da mesma `ESCALA_ID`** (compara `TURNO` com `whereBetween` em horários) — **não** é prova de “dois setores” automaticamente; é conflito de **turno sobreposto** na mesma escala.
2. **Alertas pós-montagem:** `DetalheEscalaAlerta` (model) implementa, entre outros:
   - `conflito_escala`: mesmo `FUNCIONARIO_ID` em **outra escala** com a **mesma competência** (excluindo a escala atual) — alinhado a “duas escalas no mesmo período”.
   - `conflito_escala_datas_turno`: colisão **mesmo dia + mesmo turno** entre escalas diferentes.
   - Alertas de **carga horária** mensal vs esperada por lotação.

### 3.3 Lacunas

- O fluxo **v3** (matriz, rotas em `web.php`, `POST /api/v3/escalas/.../salvar`) precisa **reusar** as mesmas regras que o `Eloquent`/`FormRequest` antigo; se a API v3 grava **sem** passar por `DetalheEscalaCreateRequest`, pode existir **bypass** de conflito.
- `HorariosConflitantes` usa `whereBetween` de forma **simplificada** — turnos noturnos ou que cruzam meia-noite podem precisar de regra adicional (já há lógica de +24h em módulos de plantão, mas não necessariamente na regra antiga).

**Conformidade:** **parcial** — regra de “duas escalas na mesma competência” e “mesmo dia/turno” existe no motor de **alertas**; **validação estrita na API v3** precisa ser **explicitamente** amarrada.

---

## 4) Sobreaviso (regime, 24h, remuneração 1/3 da hora normal)

### 4.1 Regra de negócio (o que pediu)

- Fora do horário normal, o servidor permanece **à disposição** (aguardando chamado).
- **Jornada máxima** de **24 horas** nesse regime.
- **Remuneração:** **1/3** do valor da **hora normal** (adicional de sobreaviso, distinto de hora extra “efetiva” em muitos regimes).

### 4.2 O que o GENTE faz hoje (código)

- Tela `EscalaSobreavisoView.vue` e APIs em `plantoes_sobreaviso.php` / `web.php`: leitura de tabelas `SOBREAVISO`, `ESCALA_SOBREAVISO`, `ACIONAMENTO` / `ACIONAMENTO_SOBREAVISO`, com **duração** calculada a partir de janelas de hora (inclui cruzar meia-noite em alguns cálculos).
- **Não** foi localizada, em busca estática no repositório, uma fórmula **explícita** do tipo `valor_hora_normal * (1/3) * horas_sobreaviso` dedicada ao sobreaviso (há cálculo de **hora extra** com **percentual** em `hora_extra.php`, o que é outro instituto).
- O **1/3** aparece em contexto de **férias / constitucional** em `ExoneracaoView` e `exoneracao.php`, **não** como adicional de sobreaviso.

### 4.3 Conclusão

**Conformidade:** **não atendida** quanto à regra “**1/3 da hora normal**” e **teto 24h** como **regra de motor** unificada. O módulo parece **operacional/registral**; a **regra salarial** provavelmente exige parâmetro em **Motor de Folha / evento** e amarração com eSocial, se aplicável.

**Plano (resumo):**
1) Parametrizar **percentual ou fator 1/3** para evento de sobreaviso.
2) Validar **janela máxima 24h** no cadastro de período de sobreaviso.
3) Teste de integração: sobreaviso → **HORA_EXTRA** ou rubrica específica → holerite.

---

## 5) Escalas de trabalho: Kanban, paridade com “escala médica”, replicação mês anterior

### 5.1 Regra de negócio (o que pediu)

- **Escalas gerais** e **escalas médicas** devem se comportar de forma **análoga** no quesito **usabilidade** (ex.: **Kanban** / arrastar turnos).
- Deve ser possível **replicar** a escala de um **mês anterior** para o mês corrente **antes** de gravar, para o gestor **editar** no rascunho.

### 5.2 O que o GENTE faz hoje

- **Escala geral (visão operacional):** `EscalaTrabalhoView.vue` — grade mensal com células por dia; botão para **“Escala médica”** leva a `/escala-matriz-v3` (`irParaEscalaMedica`).
- **Matriz / escalas “médicas” (título da UI):** `MatrizEscalaView.vue` — **paleta de turnos** com **drag-and-drop** para preencher a grade (experiência tipo **Kanban**). Inclui cards de listagem e export PDF, “Nova” escala, **Salvar**.
- **APIs:** criação de escala e competência em `web.php` (`POST /escalas` etc.); persistência em bloco com `POST .../salvar` na matriz.
- **Replicação de mês anterior:** a busca no repositório por `replic`, `anterior`, `copiar` na `MatrizEscalaView` **não** revelou fluxo de **“importar mês anterior → rascunho”**; só há comentário de limpeza de “grade anterior” no script. **Gap funcional** em relação ao pedido.

### 5.3 Diferença “escala médica” vs “escala normal” (como o sistema trata)

| Aspeto | Escala “médica” (Matriz) | Escala geral (Escala de Trabalho) |
|--------|--------------------------|-----------------------------------|
| UI | Hero “Escalas Médicas”, modo alternável para “Funcionários gerais”, DnD de turnos | Grade mensal por funcionário, foco leitura/preenchimento amplo |
| Dados | Normalmente amarrado a `ESCALA` + `DETALHE_ESCALA` + `TIPO_ESCALA_ID` (quando usado) | `GET /api/v3/escala-trabalho` agregando dias |
| Regras de negócio | Podem reutilizar mesmos alertas de `DetalheEscalaAlerta` se o save passar pelos mesmos modelos | Idem, dependendo da rota usada |

**Conformidade:** **parcial** — a **matriz** já tem **UX tipo Kanban**; a **tela geral** é mais “planilha”. **Replicação mês a mês em rascunho** **não** está implementada de forma visível.

**Plano:**
1) Endpoint: `GET /api/v3/escalas/{id}/clonar-para?competencia=YYYY-MM` retornando **grade em memória** (ou escala `rascunho`).
2) UI: botão “Carregar do mês anterior” na `MatrizEscalaView` + estado **não salvo**.
3) (Opcional) Unificar **uma** tela com toggle “Vista planilha / Vista matriz” para paridade total.

---

## 6) Substituições de plantão — ordem: substituto aceita **antes** do gestor

### 6.1 Regra de negócio (o que pediu)

1. Solicitação (substituído → substituto).
2. **Substituto aceita** (ou recusa) **antes**.
3. Só então a solicitação **sobe** para o **painel do gestor** (aprovação final).

### 6.2 O que o GENTE faz hoje

- Tabela `SUBSTITUICAO_ESCALA` com status (ex.: `pendente` / `aprovada` / `recusada` — com compatibilidade numérica legada).
- `POST /api/v3/substituicoes` grava **pendente**; notificação com `NOTIFICACAO_URL` = `/substituicoes` para vários atores.
- `PUT /api/v3/substituicoes/{id}` aceita transição de status **sem** distinção de “quem” decide (não há checagem de perfil de substituto vs gestor no trecho lido).
- `SubstituicoesView.vue` mostra **pendentes** com botões **Aprovar / Recusar** na **mesma** tela acessível a perfis `admin`, `rh`, `gestor` (meta da rota).

**Conformidade:** **não atendida** — o fluxo é em **um único nível** de “decisão”; **não há** estados do tipo `aguardando_substituto` → `aguardando_gestor` **nem** tela separada mínima para o substituto (ex.: em “Minha área”).

**Plano:**
1) Modelo de status: `rascunho` | `pendente_substituto` | `recusada_substituto` | `pendente_gestor` | `aprovada` | `recusada_gestor`.
2) `PUT` com **regras por papel** (policies ou checagem de `Auth::id()` = usuário do substituto).
3) Notificações: link para **aceite** do substituto **antes** de notificar o gestor.
4) Portal do gestor / fila: listar só `pendente_gestor`.

---

## 7) Bug de navegação: “link de cima” vs cadastros, tela branca no autocadastro

### 7.1 Sintomas prováveis

- **Breadcrumb (topo)** mostra o **rótulo** de uma rota, mas o utilizador clica noutro **link** (ex.: notificação, e-mail, ou link copiado) e obtém **tela branca** ou **página vazia**.
- Confusão entre **Autocadastro (gestão)** ` /autocadastro-gestao` e o **formulário público** ` /autocadastro/{token}`.

### 7.2 Evidência técnica

- Rota **pública** do formulário: `path: '/autocadastro/:token'` no `router/index.js` + Laravel `Route::get('/autocadastro/{token}')` devolvendo `view('v3.app')` **fora** do grupo autenticado.
- API: `url("/autocadastro/{$token}")` no `gerar-link` — depende de **`APP_URL` e de path base** (subpasta, proxy reverso).
- Cópia local na `AutocadastroGestaoView.vue`: `window.location.origin + '/autocadastro/' + token` — se o app estiver publicado em **`/gente`**, o link **deve** ser `origin + '/gente/autocadastro/...'`. Se `origin` for só o host **sem** subpasta, o **token abre 404/SPA vazio** = **tela branca** típica.

### 7.3 Plano de correção (ordem sugerida)

1. **Configuração:** definir `APP_URL` = URL **pública exata** (com path base se houver). No front, `import.meta.env.BASE_URL` (Vite) alinhado à mesma subpasta.
2. **Gerador de link:** unificar:
   - `return ['url' => rtrim(config('app.url'), '/') . '/autocadastro/' . $token, ...]`
   - e na Vue, **`const base = import.meta.env.BASE_URL` ou endpoint que devolva a URL** para não duplicar lógica.
3. **Notificações:** `NOTIFICACAO_URL` deve ser **rota v3** completa ou relativa coerente com o **history** base (`createWebHistory(import.meta.env.BASE_URL)` se existir; hoje o router usa default — **verificar** se produção usa subdiretório).
4. **Teste:** abrir o link em aba anónima, verificar se `v3.app` carrega e se `GET /api/v3/autocadastro/{token}` responde 200.
5. **Breadcrumb:** não é `router-link`; o problema de “tela branca” **não** é o breadcrumb em si, mas **URLs absolutas** inconsistentes.

---

## 8) Matriz de conformidade (resumo executivo)

| Tema | Pedido SEMAD / SLZ | Conforme no GENTE? | Observação |
|------|--------------------|--------------------|------------|
| Contexto administrativo / SEMAD | Explícito | ⚙️ Documental | Registrado neste BRAIN |
| Gestão de declarações (modelo + aprovar/rejeitar) | Sim | **Parcial** | Duas rotas; validar “modelo” legal |
| Choque de escala (mesma pessoa, dois lugas) | Sim | **Parcial** | Alertas Eloquent; validar v3 / APIs |
| Sobreaviso 24h + 1/3 hora | Sim | **Não** | Falta fórmula e teto no motor |
| Escalas: UX Kanban + replicar mês anterior (pré-salvar) | Sim | **Parcial** | Kanban na matriz; **sem** replicação visível |
| Substituição: substituto antes do gestor | Sim | **Não** | Fluxo single-step |
| Links autocadastro / tela branca | Corrigir | **Plano** | `APP_URL` + base Vite + uma fonte de URL |
| Inativar no cadastro de funcionários | “Confirmar inativação” | **Efeito parcial** | Vê secção 11; **não** dispara exoneração automática |
| Arquitetura de rotas (monólito → módulos) | Refatoração | **Híbrida** | Vê **secção 12** — `web.php` ainda muito grande; ficheiros extraídos ajudam, mas há riscos |

---

## 9) Plano de implementação consolidado (fases sugeridas)

**Fase A — Rápida (config / DX)**
- Ajuste `APP_URL` + `BASE_URL` + testes de link de autocadastro; revisar notificações com path.

**Fase B — Jornada (regras)**
- Parametrização sobreaviso 1/3 e limite 24h; amarrar a folha ou tabela de evento.
- API v3 de escala: validação de conflito alinhada a `DetalheEscalaAlerta` / `HorariosConflitantes`.

**Fase C — UX escala**
- “Replicar competência anterior” com escala `rascunho` e edição antes de publicar.
- (Opcional) alinhar UX de `EscalaTrabalhoView` com DnD da matriz.

**Fase D — Substituições**
- Máquina de estados + telas/perfis (substituto vs gestor) + notificações ordenadas.

**Fase E — Documentação**
- Extrair regras deste BRAIN para `docs/SEMAD_JORNADA.md` se o time aprovar (ficheiro separado, fora de `arquivo/`, se quiserem “doc oficial”).

**Fase F — Rotas (pós “monólito”)**
- **P0–P1:** secção **12.11** (eliminar duplicados *canónicos*; agregador `api_v3.php`); automação e CI em **12.10**; arquitectura alvo em **12.8**.
- Manter o `web.php` só como *wiring* (logins, CSRF, `require` do agregador v3), nunca *closures* de negócio novas; política de PR em **12.9**.

---

## 10) Referências internas (ficheiros tocados na auditoria)

- `gente/app/Rules/DetalheEscala/HorariosConflitantes.php`
- `gente/app/Models/DetalheEscalaAlerta.php`
- `gente/app/Http/Requests/DetalheEscala/DetalheEscalaCreateRequest.php`
- `gente/resources/gente-v3/src/views/escala/MatrizEscalaView.vue`
- `gente/resources/gente-v3/src/views/escala/EscalaTrabalhoView.vue`
- `gente/resources/gente-v3/src/views/escala/SubstituicoesView.vue`
- `gente/routes/web.php` (substituições, escalas, autocadastro, view `v3.app`)
- `gente/routes/plantoes_sobreaviso.php`, `gente/routes/hora_extra.php`
- `gente/resources/gente-v3/src/views/rh/GestaoDeclaracoesView.vue` / `DeclaracoesRequerimentosView.vue`
- `gente/resources/gente-v3/src/layouts/DashboardLayout.vue` (`routeMap`, itens de menu)
- `gente/resources/gente-v3/src/views/rh/FuncionariosView.vue` (modal inativar; `DELETE` com `FUNCIONARIO_DATA_FIM`)
- `gente/routes/exoneracao.php` (registo formal e `RESCISAO_CALCULO` vs. simples data fim)
- `gente/app/Providers/RouteServiceProvider.php` (carrega `web.php` + `api.php`)

---

## 11) Inativar funcionário (modal “Confirmar Inativação”) e ligação a Exoneração / Rescisão

### 11.1 O que acontece no sistema ao confirmar a inativação

O botão **Confirmar Inativação** na listagem de funcionários chama, no front (`FuncionariosView.vue`), o método `inativar`, que envia:

- `DELETE /api/v3/funcionarios/{id}` com corpo JSON `{ "FUNCIONARIO_DATA_FIM": "<data escolhida>" }` (a “data de desligamento” do modal).

No backend (`routes/web.php`, rota comentada como inativação / soft delete), o processamento **faz o seguinte**:

1. **Funcionário:** preenche `FUNCIONARIO_DATA_FIM` com a data informada (ou a data de hoje se vier vazio). Pode ainda preencher `FUNCIONARIO_TIPO_SAIDA` se o pedido enviar esse campo (o modal atual **não** envia motivo; só a data).
2. **Lotação:** todas as lotações ainda abertas (`LOTACAO_DATA_FIM` nula) desse funcionário passam a ter `LOTACAO_DATA_FIM` = **a mesma data** de `FUNCIONARIO_DATA_FIM`, fechando o histórico de lotação.

Efeitos práticos em cascata: o servidor deixa de entrar em listagens que filtram “ativos” (tipicamente `FUNCIONARIO_DATA_FIM` nula ou data futura) e a lotação ativa deixa de existir. **Não** há, nesta rota, criação automática de registo em folha, eSocial, ou ficheiro de rescisão.

### 11.2 Isto está ligado ao módulo **Exoneração e Verbas Rescisórias**?

**Não, de forma automática.** São dois caminhos distintos no código:

| Aspeto | Inativar (`DELETE /api/v3/funcionarios/{id}`) | **Registrar exoneração** (`POST /api/v3/exoneracao/registrar` em `exoneracao.php`) |
|--------|------------------------------|----------------------------------------------------------------------------------|
| Tabela / registo central | Só `FUNCIONARIO` + `LOTACAO` | Cria linha em **`RESCISAO_CALCULO`**, cálculo de verbas, log de auditoria em canal `security` |
| Campos em `FUNCIONARIO` | Em geral `FUNCIONARIO_DATA_FIM` (e `FUNCIONARIO_TIPO_SAIDA` se enviado) | Também `FUNCIONARIO_MOTIVO_SAIDA`, `FUNCIONARIO_DATA_EXONERACAO`, `FUNCIONARIO_PORTARIA_SAIDA`, `FUNCIONARIO_STATUS_RESCISORIO`, etc. |
| Folha / rescisão | Não gera cálculo rescisório | Sim — previsto para “elegíveis a folha rescisória” e histórico |

**Conclusão de produto (SEMAD):** “Inativar” a partir do cadastro é um **encerramento administrativo simples** (data fim + fecho de lotação). A **exoneração formal** com portaria, motivo, **cálculo** e rasto em **`RESCISAO_CALCULO`** deve ser feita (ou reforçada) pelo módulo **Exoneração e Verbas Rescisórias**. Se a organização exige um único fluxo, a **recomendação** é: após inativação, **redirecionar o RH** para o ecrã de exoneração *ou* evoluir a API de `DELETE` para opcionalmente chamar a mesma lógica de `exoneracao/registrar` (com confirmação e motivo).

Já existe **reativação** de funcionário: `PATCH /api/v3/funcionarios/{id}/reativar` limpa `FUNCIONARIO_DATA_FIM` e `FUNCIONARIO_TIPO_SAIDA` (útil se a inativação foi engano).

### 11.3 Se “Confirmar Inativação” aparenta não funcionar (debug rápido)

- Confirmar no separador **Rede** se o `DELETE` devolve **2xx** e o corpo contém a data. Erros comuns: **CSRF/419**, sessão expirada, ou payload sem `FUNCIONARIO_DATA_FIM` conforme o esperado pelo backend.
- A rota está no grupo `api/v3` com o mesmo **middleware** que o resto do RH (o cliente axios deve enviar **cookies** / cabeçalhos como nas outras ações que já funcionam).

### 11.4 Nota — aba “Funções” (cadastros de apoio)

Foi referido no projeto: **não** existe, no backend analisado, rota de **reativar** para a entidade **“Funções”** da mesma forma que para **funcionários** e **cargos**; a aba **Funções** pode continuar a mostrar só **Inativar** até existir **endpoint** simétrico. Isto **não** se confunde com a reativação de **funcionário** acima.

---

## 12) Evolução da arquitetura de rotas: do monólito à organização actual

### 12.1 O que se costumava ter (referência)

Historicamente, um único ficheiro com **todas** as rotas (muitas vezes o antigo padrão “tudo no `web.php`” / variante que o time chamou de *app.php monolítico*) oferece: **um só sítio para ler**, mas dificulta *merge* em equipa, revisão, testes e separação de API vs legado. No GENTE actual, **já não** é esse cenário puro, mas ainda há **muito peso** no ficheiro central.

### 12.2 Como o projecto está hoje (estado real no repositório)

| Aspeto | Situação |
|--------|----------|
| **Entrada** | `RouteServiceProvider` regista `routes/api.php` com prefixo e middleware `api`, e `routes/web.php` com middleware `web` (padrão Laravel 8+). |
| **API “REST” leve** | `routes/api.php` é **pequeno** (ex.: ponto do terminal com Bearer) — a maior parte do produto **não** vive aí. |
| **`web.php`** | Ficheiro **muito grande** (ordem de **~7500 linhas** neste ramo), contendo: rotas de **login**, **CSRF cookie**, bloco `api/auth`, grupos `api/v3` com e **sem** `auth`, muitas closures **inline**, e ainda dezenas de `require __DIR__ . '/….php'` para módulos (padrão comentado *ARQ-01*, ex.: `funcionarios.php`, `folha.php`, `exoneracao.php`, `plantoes_sobreaviso.php`, módulos ERP, etc.). |
| **Ficheiros extraídos** | Dezenas de ficheiros `routes/*.php` (70+) — bons **limites de domínio** (ex.: `exoneracao.php`, `escala_trabalho.php`, `autocadastro_admin.php`), alinhados à ideia de *módulos* e à **teia** (RH, folha, administrativo, SESMT, etc.). |
| **GEV / Vue 3** | Comentário explícito no topo de `web.php`: o front legado Vue 2 foi descontinuado; o **gente-v3 (SPA)** consome **`/api/v3/*`**. |
| **Mobile ponto (JWT)** | `ponto_app.php` carregado num `Route::prefix('api/v3')->middleware(['web'])` **só** — *sem* `auth` de sessão, coerente com o desenho documentado na auditoria (intencional). |
| **Métrica aproximada (auditoria 2026-04)** | Só no `web.php` há **centenas** de ocorrências `Route::(get|post|put|patch|delete)(` (ordem de **670+**); no conjunto `routes/*.php` o **total** de definições `Route::` dispersa-se por muitas dezenas de ficheiros — sinal de que o *split* avançou, mas o **ficheiro central ainda concentra demasiada lógica**. |

**Conclusão intermédia:** a refacção **não** foi só “juntar tudo noutro sítio”: houve **ganhos reais** (módulos com nome, separação ponto móvel, *prefix* único v3, comentários de intenção). O lado **imprudente** é o **híbrido incompleto**: `web.php` continua a ser um **monólito partido** — a maior parte do código de rotas **ainda está lá**, e a **ordem** e **duplicação** de `GET`/`POST` para o **mesmo URI** (ficheiro incluído *depois* de uma closure inline) pode **sobrepor** o que o programador pensa ser a “fonte canónica”.

### 12.3 Está melhor do que antes? O que é recuperável?

| Melhor (do que o monólito puro) | Ainda a recuperar / apertar |
|----------------------------------|----------------------------|
| Módulos por ficheiro (`routes/*.php`) fáceis de abrir em *review* | Mover **mais** blocos de fechos inline do `web.php` para ficheiros por domínio até o central ficar **só** com grupos, `require` e rotas *globais* mínimas |
| Prefixo claro `api/v3` + SPA + auth documentado | Script ou CI: `php artisan route:list --path=api/v3` e deteção de **duplicados** (mesmo método+URI) |
| `api.php` separa um fluxo *stateless* (terminal) do resto | *Route model binding* e *Controllers* em vez de closures muito longas, onde fizer sentido, para alinhar com regras e testes |
| Blocos dev `isLocal()` isolados (menos risco em prod se bem configurado) | Garantir que ficheiros `require` estão **todos** documentados num *index* (tabela: módulo → ficheiro → prefixo) |

O que “dá para recuperar” com **baixo risco** é, sobretudo, **governança**: inventário de rotas, eliminar duplicados, e **uma regra de equipa**: *novo endpoint v3* → ficheiro do domínio correspondente, **não** mais 200 linhas novas no `web.php` sem necessidade.

### 12.4 Coerência com a proposta de valor do GENTE (O que a app se propõe a ser)

O GENTE posiciona-se como **núcleo de RH, folha e teia operacional** para a administração pública (SEMAD, conformidade, integrações, escalas, folha, etc.). **Coerente com isso** nesta arquitectura:

- **Módulos nomeados** por função pública (exoneração, CNAB, eSocial, SAGRES, ponto) — bons *bounded contexts* em ficheiro.
- **Uma fachada API** para o SPA (`/api/v3`) — padrão moderno.
- **Auditoria** e **auth** no grupo autenticado (middleware `audit` no grupo v3) — alinhado a *governança*.

**Menos coerente** com a mesma proposta, se o monólito do `web.php` crescer sem teto: **a teia fica opaca** — o próximo programador **não** vê, num único sítio, o que afecta ponto, folha ou rescisão, e *bugs de ordem de rota* minam a confiança nos dados. Por isso o relatório trata a refacção como **direcção certa, execução incompleta** — e recomenda a **Fase F** (acima) como dívida técnica *explicitamente* alimentada nesta documentação.

### 12.5 Referência técnica mínima (ficheiros)

- `gente/app/Providers/RouteServiceProvider.php` — o que o Laravel carrega.
- `gente/routes/web.php` — nó central; grupos `api/v3` e *requires*.
- `gente/routes/api.php` — subconjunto mínimo `api/*` (não confundir com a massa v3).
- Dezenas de `gente/routes/<modulo>.php` — lógica por domínio.
- `gente/routes/funcionarios.php` — regra explícita no ficheiro: *não* reabrir `Route::prefix` (herda contexto do `web.php`).

*(Contagens e lista de `require` sujeitas a evolução do repositório — rever no ramo em uso.)*

### 12.6 Estudo aprofundado — duplicidade real vs “ilusão” no `route:list`

O comando `php artisan route:list` **não** lista duas linhas para o *mesmo* par método+URI ganhado no fim: o *router* do Laravel **substitui** a rota anterior. Por isso **duplicidade no código** pode **parecer inexistente** na listagem, embora exista **código morto** e risco de **decisão errada** (alguém edita o ficheiro “errado”).

**Evidências concretas no repositório (caminho `api/v3` implícito no grupo):**

| Rota (path relativo ao prefixo) | Onde está definida | Nota |
|---------------------------------|---------------------|------|
| `GET/POST /plantoes-extras` | `web.php` **e** `plantoes_sobreaviso.php` | Duas implementações; **ganha a última registada** no *bootstrap* (depende da ordem de `require` e de blocos mais abaixo no mesmo ficheiro). |
| `GET/POST /escala-trabalho` | *Closures* longas no `web.php` **e** ficheiro `escala_trabalho.php` | Risco de manter lógica divergente em dois sítios; o último *wins*. |
| `GET /funcionarios` (e outras) | `funcionarios.php` (carregado cedo) + novamente no `web.php` mais abaixo | A lista “paginada” do RH pode ser **sobrescrita** por outra *closure* se o programador **não** perceber a ordem. |

Isto explica *bugs* intermitentes ou a sensação de “a correção não pega” — a correção foi feita no ficheiro **que deixou de ser canónico**.

**Implicação:** a política de equipa não pode ser “confiar no `route:list`”; tem de ser “**um URI, um ficheiro dono, uma ordem conhecida**”.

### 12.7 Causas estruturais (porque o `web.php` continua a crescer e duplicar)

1. **Grupos aninhados imensos** — o grupo `api/v3` com `auth` concentra *milhares* de linhas de *closures*; qualquer *feature* nova tende a ser colada aí.
2. **Requires no meio do ficheiro** — `require` de `funcionarios.php` **antes** de muitas outras `Route::` no mesmo `group` cria a ilusão de “módulo fechado”, mas o ficheiro principal continua a declarar as mesmas URIs.
3. **Falta de fachada** — não há um `routes/api_v3.php` que seja o **único** a ser incluído no `web.php` com uma **lista ordenada e documentada** de módulos (A→B→C), reduzindo surpresa de *override*.
4. **Sem convenção** — ficheiros `*_v3.php` coexistem com rotas v3 soltas no `web.php` sem regra *escrita* de onde nascem rotas novas.
5. **Closures** — dificultam *unit test* da “rota” e incentivam *copy-paste* entre ficheiros.

### 12.8 Arquitectura alvo recomendada (estável, auditável, sem crescimento do “monólito”)

| Camada | Função | Comentário |
|--------|--------|------------|
| **`web.php` enxuto** (meta: **&lt; 400 linhas** alvo de médio prazo) | Só: `web` *middleware*, login, `csrf-cookie`, `view('v3.app')` onde aplicável, **um** `Route::prefix('api/v3')` (ou dois: com/sem *auth*), e **só** `require` de agregadores | Zeros *closures* de negócio longas aqui. |
| **`routes/api_v3.php`** (novo agregador) | Declara a ordem: `auth` *sanctum-like* via *session* como hoje, `ponto_app`, depois módulos por *domínio* | A ordem fica num **único sítio visível**; comentário no topo: “A ordem importa: último gana”. |
| **Um ficheiro por domínio** (já parcialmente feito) | `plantoes_sobreaviso.php`, `escala_trabalho.php`, `exoneracao.php`, etc. | **Nunca** a mesma URI no `web.php` depois de incluir o ficheiro do domínio. |
| **Evolutivo: Controllers** | `app/Http/Controllers/Api/V3/...` a substituir *closures* de >80 linhas | *Policy*, *FormRequest*, testes de *HTTP* com `$this->getJson()`. |
| **Nomes de rota** | `->name('api.v3.escala-trabalho.index')` | Facilita *refactor* e *grep*; evita *hardcode* de paths em notificações. |

**Regra de ouro:** o `web.php` **não** recebe rotas de negócio de `/api/v3/*` — apenas *wiring*.

### 12.9 Política explícita anti-duplicidade (regras de PR)

1. **Definição** — *Duplicado* = a mesma combinação HTTP (GET/POST/PUT/PATCH/DELETE) + *path* relativo (após o prefixo `api/v3`) declarada em **mais do que um** ficheiro ou duas vezes no mesmo.
2. **Antes do merge** — o autor confirma que o path **não** aparece noutro ficheiro (`rg "Route::(get|post).*'/" routes/` ou script do §12.10).
3. **Canónica** — se houver *legacy* e *novo*, o *legacy* apaga-se no mesmo PR ou abre *ticket* P0.
4. **Ordem** — ficheiro agregador documenta: “*módulos de folha* → *RH* → *escalas*…”. *Requires* de domínio que definem o mesmo *namespace* de URL devem ficar **juntos** para *review* fácil.

### 12.10 Automação sugerida (local + CI)

1. **Script estático (rápido)** — varrer `routes/*.php` + o excerto relevante de `web.php` e **extrair** pares (`METHOD`, `uri`), reportar colisões (ignorar parâmetros nomeados idênticos: `{id}` = `{id}`).
2. **Teste *feature* opcional** — ficheiro Pest/PHPUnit que carrega a app e, para *paths* *críticos* (`/api/v3/funcionarios`, `plantoes-extras`, `escala-trabalho`), *assert* o *controller* ou a *action* *closure* nómada esperada.
3. **CI** — *fail* o *pipeline* se o script (1) encontrar colisão **ou** se o `web.php` exceder o *budget* de linhas (teto a subir gradualmente).
4. **Documento vivo** — gerar a partir de `php artisan route:list --json` (quando o CLI é estável) uma **tabela por módulo** para *onboarding*.

### 12.11 O que fazer com o código hoje (priorização)

| Prioridade | Acção | Benefício |
|------------|--------|-----------|
| **P0** | Para `/plantoes-extras` e `/escala-trabalho` e `GET/POST /api/v3/funcionarios`: decidir o **ficheiro dono**; **remover** a duplicata no outro sítio (ou reexportar *require* de um *single* sítio). | Elimina ambiguidade; reduz bugs e confusão. |
| **P1** | Criar `api_v3.php` e mover o bloco *apenas* de *requires* para ele; o `web.php` reduz a ~20–30 `require` no máximo. | Crescimento controlado; ordem visível. |
| **P2** | Teto de *LOC* no `web.php` + *script* anti-colisão no CI. | Impede regresso. |
| **P3** | *Controllers* por módulo para rotas muito usadas. | Testes, *IDE* *navigate*, reutilização. |
ítica de PR
### 12.12 Coerência com o *BRAIN* e com a *teia* GENTE (fecho do estudo)
ítica de PR scisão, ponto, escala) exigem **uma** implementação *HTTP* conhecida; duplicar rotas no mesmo *prefix* é **dívida de integridade** — não é só *style*.  ítica de PR
- A *teia modular* documentada noutros ficheiros BRAIN **só** se sustenta se os *endpoints* forem **rastreáveis**; por isso este estudo integra a **Fase F** (plano) com passos *mensuráveis* (P0–P3).

---

*Fim do relatório BRAIN — manter alinhado às auditorias e à teia modular; atualizar após cada sprint que mexer em jornada ou SEMAD.*
