# Mapa UI ↔ backend (GENTE v3) — `UI_BACKEND_TEIA_MAP_2026-04-27.md`

Documento extraído do código em `gente/resources/gente-v3/`. Uso: roteiro de QA, auditoria de segregação por domínio e cruzamento com `gente:auditar-rotas-mutacao` / middleware Laravel.

---

## 1. Inventário exaustivo do sidebar (`DashboardLayout.vue` → `ALL_NAV_ITEMS`)

Coluna **Perfis:** valores do array `roles` no item; **vazio** = qualquer utilizador autenticado. A filtragem efectiva é `itemVisivel()` + `userRoleLevel()` no mesmo ficheiro.

| Seção | Rótulo (label) | Rota (`to`) | Perfis permitidos (`roles`) |
|-------|----------------|------------|-----------------------------|
| Visão Geral | Dashboard | `/dashboard` | — (todos) |
| Minha Área | Meu Perfil | `/meu-perfil` | — (todos) |
| Minha Área | Ponto Eletrônico | `/ponto` | — (todos) |
| Minha Área | Meus Holerites | `/meus-holerites` | — (todos) |
| Minha Área | Férias e Licenças | `/ferias-licencas` | — (todos) |
| Minha Área | Banco de Horas | `/banco-horas` | — (todos) |
| Minha Área | Declarações | `/declaracoes-requerimentos` | — (todos) |
| Minha Área | Minha Progressão | `/progressao-funcional` | — (todos) |
| Minha Equipe | Portal do Gestor | `/portal-gestor` | `admin`, `rh`, `gestor` |
| Minha Equipe | Organograma | `/organograma` | `admin`, `rh`, `gestor` |
| Minha Equipe | Escalas | `/escala-trabalho` | `admin`, `rh`, `gestor` |
| Minha Equipe | Substituições | `/substituicoes` | `admin`, `rh`, `gestor` |
| Minha Equipe | Sobreaviso | `/escala-sobreaviso` | `admin`, `rh`, `gestor` |
| Minha Equipe | Hora Extra | `/hora-extra` | `admin`, `rh`, `gestor` |
| Minha Equipe | Plantões Extras | `/plantoes-extras` | `admin`, `rh`, `gestor` |
| Recursos Humanos | Funcionários | `/funcionarios` | `admin`, `rh` |
| Recursos Humanos | Autocadastro | `/autocadastro-gestao` | `admin`, `rh` |
| Recursos Humanos | Cargos e Salários | `/cargos-salarios` | `admin`, `rh` |
| Recursos Humanos | Contratos e Vínculos | `/contratos-vinculos` | `admin`, `rh` |
| Recursos Humanos | Gerir Progressões | `/progressao-admin` | `admin`, `rh` |
| Recursos Humanos | Exoneração / Rescisão | `/exoneracao` | `admin`, `rh` |
| Recursos Humanos | PSS / Concurso | `/pss` | `admin`, `rh` |
| Recursos Humanos | Estagiários | `/estagiarios` | `admin`, `rh` |
| Recursos Humanos | Terceirizados | `/terceirizados` | `admin`, `rh` |
| Recursos Humanos | Acumulação de Cargos | `/acumulacao-cargos` | `admin`, `rh` |
| Recursos Humanos | Diárias | `/diarias` | `admin`, `rh` |
| Recursos Humanos | Avaliações da Equipe | `/avaliacao-gestor` | `admin`, `rh`, `gestor` |
| Recursos Humanos | Gestão de Benefícios | `/beneficios` | `admin`, `rh` |
| Recursos Humanos | Gestão de Treinamentos | `/treinamentos-admin` | `admin`, `rh` |
| Recursos Humanos | Gestão SESMT (Med. | `/medicina-admin` | `admin`, `rh`, `sesmt` |
| Recursos Humanos | Gestão SESMT (Seg. | `/seguranca-admin` | `admin`, `rh`, `sesmt` |
| Frequência | Faltas e Atrasos | `/faltas-atrasos` | `admin`, `rh` |
| Frequência | Abono de Faltas | `/abono-faltas` | `admin`, `rh` |
| Frequência | Atestados Médicos | `/atestados-medicos` | `admin`, `rh` |
| Saúde Ocupacional | Medicina do Trabalho | `/medicina-trabalho` | `admin`, `rh` |
| Saúde Ocupacional | Segurança do Trabalho | `/seguranca-trabalho` | `admin`, `rh` |
| Financeiro e Folha | Folha de Pagamento | `/folha-pagamento` | `admin`, `rh` |
| Financeiro e Folha | Consignações | `/consignacao` | `admin`, `rh` |
| Financeiro e Folha | Consignatárias | `/consignatarias` | `admin` |
| Financeiro e Folha | Verbas Indenizatórias | `/verba-indenizatoria` | `admin`, `rh` |
| Financeiro e Folha | Benefícios | `/beneficios` | `admin`, `rh` |
| Financeiro e Folha | RPPS / IPAM | `/rpps` | `admin`, `rh` |
| Financeiro e Folha | Remessa CNAB | `/remessa-cnab` | `admin`, `rh` |
| Financeiro e Folha | Gestão de Declarações | `/gestao-declaracoes` | `admin`, `rh` |
| Saúde | Monitor OSS | `/oss` | `admin` |
| Administrativo | Compras e Licitações | `/compras` | `admin` |
| Administrativo | Almoxarifado | `/almoxarifado` | `admin` |
| Administrativo | Patrimônio | `/patrimonio` | `admin` |
| Administrativo | Contratos | `/contratos-admin` | `admin` |
| Administrativo | Frotas | `/frotas` | `admin` |
| Compliance | eSocial | `/esocial` | `admin`, `rh` |
| Compliance | SAGRES / TCE-MA | `/sagres-tce` | `admin`, `rh` |
| Compliance | Transparência Pública | `/transparencia` | `admin`, `rh` |
| Desenvolvimento | Avaliação de Desempenho | `/avaliacao-desempenho` | `admin`, `rh` |
| Desenvolvimento | Treinamentos | `/treinamentos` | `admin`, `rh` |
| Desenvolvimento | Pesquisa de Satisfação | `/pesquisa-satisfacao` | `admin`, `rh` |
| Desenvolvimento | Gerenciar Pesquisas | `/pesquisa-admin` | `admin`, `rh` |
| Comunicação | Agenda | `/agenda` | — (todos) |
| Comunicação | Comunicados | `/comunicados` | — (todos) |
| Comunicação | Notificações | `/notificacoes` | — (todos) |
| Comunicação | Ouvidoria | `/ouvidoria` | — (todos) |
| Comunicação | Painel Ouvidoria | `/ouvidoria-admin` | `admin`, `rh` |
| Comunicação | Relatórios | `/relatorios` | `admin`, `rh` |
| Configurações | Configurações Gerais | `/configuracoes` | `admin` |
| Configurações | Motor de Folha | `/configuracao-sistema` | `admin` |
| Configurações | Parâmetros Financeiros | `/parametros-financeiros` | `admin` |
| Configurações | Vínculos | `/vinculos` | `admin` |
| Configurações | Turnos | `/turnos` | `admin` |
| Configurações | Feriados e Folgas | `/feriados` | `admin` |
| Configurações | Tabelas Auxiliares | `/tabelas-auxiliares` | `admin` |
| Configurações | Eventos de Folha | `/eventos-folha` | `admin` |
| ERP / Fiscal | Orçamento (PPA/LOA) | `/orcamento` | `admin` |
| ERP / Fiscal | Execução da Despesa | `/execucao-despesa` | `admin` |
| ERP / Fiscal | Contabilidade (PCASP) | `/contabilidade` | `admin` |
| ERP / Fiscal | Tesouraria | `/tesouraria` | `admin` |
| ERP / Fiscal | Receita Municipal | `/receita-municipal` | `admin` |
| ERP / Fiscal | Controle Externo | `/controle-externo` | `admin` |

**Total:** 14 secções (linhas `type: 'section'`) e **78** entradas `type: 'item'`.

**Rodapé — duplicado `/beneficios`:** no `ALL_NAV_ITEMS` existem dois itens com o mesmo `to: '/beneficios'`: *Gestão de Benefícios* (bloco RH, ícone `zap`) e *Benefícios* (bloco Financeiro e Folha, ícone `gift`). No `router/index.js` a rota `path: 'beneficios'` está registada **duas vezes**; a **última** definição prevalece e aponta para `BeneficiosAdminView.vue` (sobrescrevendo a primeira, `BeneficiosView.vue`). O QA deve tratar isso como **uma rota, dois rótulos no menu**.

---

## 2. Mapa do Vue Router (`router/index.js`)

Paths são filhos de `/` com `DashboardLayout` (URL absoluta = `/<path>`). `meta.roles` vazio = rota acessível a qualquer autenticado, sujeita ao `beforeEach` e `hasAccess()`.

| Path | Nome da rota (`name`) | Componente (ficheiro `.vue`) | Meta roles (front) |
|------|------------------------|-----------------------------|--------------------|
| `dashboard` | `Dashboard` | `views/dashboard/HomeView.vue` | — |
| `meu-perfil` | `MeuPerfil` | `views/rh/MeuPerfilView.vue` | — |
| `ponto` | `PontoEletronico` | `views/ponto/PontoEletronicoView.vue` | — |
| `meus-holerites` | `MeusHolerites` | `views/folha/ContraChequeView.vue` | — |
| `comunicados` | `Comunicados` | `views/ComunicadosView.vue` | — |
| `agenda` | `Agenda` | `views/AgendaView.vue` | — |
| `notificacoes` | `Notificacoes` | `views/NotificacoesView.vue` | — |
| `declaracoes-requerimentos` | `DeclaracoesRequerimentos` | `views/rh/DeclaracoesRequerimentosView.vue` | — |
| `atestados-medicos` | `AtestadosMedicos` | `views/ponto/AtestadosMedicosView.vue` | — |
| `ferias-licencas` | `FeriasLicencas` | `views/rh/FeriasLicencasView.vue` | — |
| `ouvidoria` | `Ouvidoria` | `views/OuvidoriaView.vue` | — |
| `banco-horas` | `BancoHoras` | `views/ponto/BancoHorasView.vue` | — |
| `portal-gestor` | `PortalGestor` | `views/rh/PortalGestorView.vue` | `admin`, `rh`, `gestor` |
| `organograma` | `Organograma` | `views/rh/OrganogramaView.vue` | `admin`, `rh`, `gestor` |
| `escala-trabalho` | `EscalaTrabalho` | `views/escala/EscalaTrabalhoView.vue` | `admin`, `rh`, `gestor` |
| `escala-matriz-v3` | `EscalaMatriz` | `views/escala/MatrizEscalaView.vue` | `admin`, `rh`, `gestor` |
| `substituicoes` | `Substituicoes` | `views/escala/SubstituicoesView.vue` | `admin`, `rh`, `gestor` |
| `escala-sobreaviso` | `EscalaSobreaviso` | `views/ponto/EscalaSobreavisoView.vue` | `admin`, `rh`, `gestor` |
| `plantoes-extras` | `PlantoesExtras` | `views/ponto/PlantoesExtrasView.vue` | `admin`, `rh`, `gestor` |
| `funcionarios` | `Funcionarios` | `views/rh/FuncionariosView.vue` | `admin`, `rh` |
| `autocadastro-gestao` | `AutocadastroGestao` | `views/rh/AutocadastroGestaoView.vue` | `admin`, `rh` |
| `funcionario/:id` | `PerfilFuncionario` | `views/rh/PerfilFuncionarioView.vue` | `admin`, `rh`, `gestor` |
| `relatorios` | `Relatorios` | `views/relatorios/RelatoriosView.vue` | `admin`, `rh` |
| `abono-faltas` | `AbonoFaltas` | `views/ponto/AbonoFaltasView.vue` | `admin`, `rh` |
| `faltas-atrasos` | `FaltasAtrasos` | `views/ponto/FaltasAtrasosView.vue` | `admin`, `rh` |
| `folha-pagamento` | `FolhaPagamento` | `views/financeiro/FolhaPagamentoView.vue` | `admin`, `rh` |
| `remessa-cnab` | `RemessaCnab` | `views/financeiro/RemessaCnabView.vue` | `admin`, `rh` |
| `cargos-salarios` | `CargosSalarios` | `views/rh/CargosSalariosView.vue` | `admin`, `rh` |
| `progressao-funcional` | `ProgressaoFuncional` | `views/rh/ProgressaoFuncionalView.vue` | `admin`, `rh` |
| `progressao-admin` | `ProgressaoAdmin` | `views/rh/ProgressaoAdminView.vue` | `admin`, `rh` |
| `exoneracao` | `Exoneracao` | `views/rh/ExoneracaoView.vue` | `admin`, `rh` |
| `hora-extra` | `HoraExtra` | `views/rh/HoraExtraView.vue` | `admin`, `rh`, `gestor` |
| `verba-indenizatoria` | `VerbaIndenizatoria` | `views/rh/VerbaIndenizatoriaView.vue` | `admin`, `rh` |
| `consignacao` | `Consignacao` | `views/rh/ConsignacaoView.vue` | `admin`, `rh` |
| `consignatarias` | `Consignatarias` | `views/rh/ConsignatariasView.vue` | `admin` |
| `esocial` | `ESocial` | `views/rh/ESocialView.vue` | `admin`, `rh` |
| `rpps` | `RPPS` | `views/rh/RPPSView.vue` | `admin`, `rh` |
| `diarias` | `Diarias` | `views/rh/DiariasView.vue` | `admin`, `rh` |
| `estagiarios` | `Estagiarios` | `views/rh/EstagiariosView.vue` | `admin`, `rh` |
| `sagres-tce` | `SagresTce` | `views/financeiro/SagresView.vue` | `admin`, `rh` |
| `acumulacao-cargos` | `AcumulacaoCargos` | `views/rh/AcumulacaoView.vue` | `admin`, `rh` |
| `transparencia` | `Transparencia` | `views/rh/TransparenciaView.vue` | `admin`, `rh` |
| `pss` | `PSS` | `views/rh/PSSView.vue` | `admin`, `rh` |
| `terceirizados` | `Terceirizados` | `views/rh/TerceirizadosView.vue` | `admin`, `rh` |
| `medicina-trabalho` | `MedicinaTrabalho` | `views/rh/MedicinaTrabalhoView.vue` | `admin`, `rh` |
| `beneficios` (1.ª) | `Beneficios` | `views/rh/BeneficiosView.vue` | `admin`, `rh` |
| `beneficios` (2.ª) | `Beneficios` | `views/rh/BeneficiosAdminView.vue` | `admin`, `rh` |
| `contratos-vinculos` | `ContratosVinculos` | `views/rh/ContratosVinculosView.vue` | `admin`, `rh` |
| `avaliacao-desempenho` | `AvaliacaoDesempenho` | `views/rh/AvaliacaoDesempenhoView.vue` | `admin`, `rh` |
| `avaliacao-gestor` | `AvaliacaoGestor` | `views/rh/AvaliacaoGestorView.vue` | `admin`, `rh`, `gestor` |
| `medicina-admin` | `MedicinaAdmin` | `views/rh/MedicinaAdminView.vue` | `admin`, `rh` |
| `seguranca-admin` | `SegurancaAdmin` | `views/rh/SegurancaAdminView.vue` | `admin`, `rh`, `sesmt` |
| `treinamentos` | `Treinamentos` | `views/rh/TreinamentosView.vue` | `admin`, `rh` |
| `treinamentos-admin` | `TreinamentosAdmin` | `views/rh/TreinamentosAdminView.vue` | `admin`, `rh` |
| `seguranca-trabalho` | `SegurancaTrabalho` | `views/rh/SegurancaTrabalhoView.vue` | `admin`, `rh` |
| `pesquisa-satisfacao` | `PesquisaSatisfacao` | `views/rh/PesquisaSatisfacaoView.vue` | `admin`, `rh` |
| `pesquisa-admin` | `PesquisaAdmin` | `views/rh/PesquisaAdminView.vue` | `admin`, `rh` |
| `gestao-declaracoes` | `GestaoDeclaracoes` | `views/rh/GestaoDeclaracoesView.vue` | `admin`, `rh` |
| `ouvidoria-admin` | `OuvidoriaAdmin` | `views/rh/OuvidoriaAdminView.vue` | `admin`, `rh` |
| `configuracoes` | `Configuracoes` | `views/config/ConfiguracoesView.vue` | `admin` |
| `configuracao-sistema` | `ConfiguracaoSistema` | `views/config/ConfiguracaoSistemaView.vue` | `admin` |
| `parametros-financeiros` | `ParametrosFinanceiros` | `views/config/ParametroFinanceiroView.vue` | `admin` |
| `tabelas-auxiliares` | `TabelasAuxiliares` | `views/config/TabelasAuxiliaresView.vue` | `admin` |
| `turnos` | `Turnos` | `views/config/TurnosView.vue` | `admin` |
| `feriados` | `Feriados` | `views/config/FeriadosView.vue` | `admin` |
| `vinculos` | `Vinculos` | `views/config/VinculosView.vue` | `admin` |
| `eventos-folha` | `EventosFolha` | `views/config/EventosView.vue` | `admin` |
| `orcamento` | `Orcamento` | `views/financeiro/OrcamentoView.vue` | `admin` |
| `execucao-despesa` | `ExecucaoDespesa` | `views/financeiro/ExecucaoDespesaView.vue` | `admin` |
| `contabilidade` | `Contabilidade` | `views/financeiro/ContabilidadeView.vue` | `admin` |
| `compras` | `Compras` | `views/administrativo/ComprasView.vue` | `admin` |
| `almoxarifado` | `Almoxarifado` | `views/administrativo/AlmoxarifadoView.vue` | `admin` |
| `patrimonio` | `Patrimonio` | `views/administrativo/PatrimonioView.vue` | `admin` |
| `contratos-admin` | `ContratosAdmin` | `views/administrativo/ContratosAdminView.vue` | `admin` |
| `frotas` | `Frotas` | `views/administrativo/FrotasView.vue` | `admin` |
| `tesouraria` | `Tesouraria` | `views/financeiro/TesourariaView.vue` | `admin` |
| `receita-municipal` | `ReceitaMunicipal` | `views/financeiro/ReceitaMunicipalView.vue` | `admin` |
| `controle-externo` | `ControleExterno` | `views/financeiro/ControleExternoView.vue` | `admin` |
| `oss` | `Oss` | `views/saude/OssView.vue` | `admin` |

**Aliases / redirects:** `holerites` → `meus-holerites`; `escala` → `escala-trabalho`; `folha` → `folha-pagamento`.

**Rota no router sem entrada no `ALL_NAV_ITEMS` (sidebar):** `escala-matriz-v3` → `MatrizEscalaView.vue` (acesso `admin` \| `rh` \| `gestor`). Navegação directa por URL ou atalhos noutro sítio.

**Comportamento do guard:** se `hasAccess` falha, o utilizador é enviado para o **Dashboard** (não 403). O RBAC de interface **não substitui** o controlo de API no Laravel.

---

## 3. A teia de aranha (consumo de API no front-end)

Formato pedido: **Ficheiro `.vue` → endpoints** (`/api/v3/...` ou `api` legado). Extraído com grep às expressões `api.get|post|put|patch|delete` nas views alvo. Ficheiros `* - Copia.vue` são duplicatas de backup; a **fonte de verdade** é o ficheiro sem sufixo.

### V1 — Folha / jornada (e holerite do servidor)

| Ficheiro | Endpoints consumidos (prefixo `/api/v3` salvo indicação) |
|----------|----------------------------------------------------------|
| `views/financeiro/FolhaPagamentoView.vue` | `GET /folhas`, `GET /secretarias`, `GET /folhas/consistencia/{comp}`, `GET /folha/por-secretaria?...`, `POST /folhas/calcular`, `GET /folhas/{id}/detalhes`, `GET /folhas/{id}/lancamentos`, `GET /rubricas?camada=3`, `GET /folhas/{competencia}/piso-salarial`, `POST /folhas/{id}/lancamentos`, `DELETE /folhas/{id}/lancamentos/{lancId}`, `POST /folhas/calcular-proventos`, `POST /folhas/{id}/confirmar` |
| `views/ponto/PontoEletronicoView.vue` | `POST /ponto/reset-dia-teste`, `POST /ponto/registro`, `GET /ponto/config`, `GET /ponto?competencia=`, `GET /ponto/heatmap-risco?competencia=`, `POST /ponto/reconciliacao/sugerir` |
| `views/ponto/BancoHorasView.vue` | `GET /banco-horas`, `GET /ponto?competencia=`, `GET /banco-horas/equipe?...`, `POST /banco-horas/equipe/notificacao-operacional`, `GET /banco-horas/equipe/impacto-escala?...`, `GET /banco-horas/equipe/notificacao-operacional?...` |
| `views/folha/ContraChequeView.vue` | `GET /meus-holerites` |

**Leitura de domínio:** Folha concentra-se em `/folhas`, `/folha/`, `/rubricas`. Jornada/ponto em `/ponto/*` e `/banco-horas/*`. Holerite do trabalhador em `/meus-holerites` (não confundir com cálculo administrativo de folha).

### V2 — Terceirizados

| Ficheiro | Endpoints consumidos |
|----------|----------------------|
| `views/rh/TerceirizadosView.vue` | `GET /terceirizados/empresas`, `GET /terceirizados/postos`, `POST /terceirizados/empresas`, `POST /terceirizados/postos/{id}/checklist` |

### V3 — RPPS

| Ficheiro | Endpoints consumidos |
|----------|----------------------|
| `views/rh/RPPSView.vue` | `GET /rpps/dashboard?competencia=`, `GET /rpps/beneficiarios`, `POST /rpps/calcular`, `POST /rpps/exportar-cadprev` |

**Autenticação de sessão:** o cliente `axios` (`plugins/axios`) segue o cookie de sessão; `useAuthStore().fetchUser()` usa `GET /api/auth/me` (fora de `/api/v3`, mas na mesma “teia” do browser).

---

## 4. Resumo de segurança visual (UI vs backend)

- **Não** existe `hasRole('x')` como string única no projeto: o padrão é `meta.roles` no **router** + `hasAccess()` + getters no **`store/auth.js`** (`isAdmin`, `isRH`, `isGestor`).
- **Sidebar:** visibilidade por `itemVisivel` / `userRoleLevel` em `DashboardLayout.vue` — itens de menu podem **ocultar-se** sem que o endpoint correspondente deixe de existir; validação real é no **Laravel** (middleware `auth`, `audit`, policies).
- **Router:** falha de perfil redireciona para **Dashboard** — não gera 403; um utilizador que adivinhe a URL ainda **pode** receber 200 ou 401/403 consoante o **backend** (a UI não é cerca de segurança).
- **Vistas com `v-if` baseado em role / admin (exemplos do repositório):**
  - `BancoHorasView.vue` — `isAdmin` (computed de `authStore.isAdmin`) para opções de equipa / notificação.
  - `PontoEletronicoView.vue` — `authStore.isAdmin` para `podeAlternarVisao`.
  - `ConfiguracoesView.vue` — secção Ponto e colunas “Jornada Esp.” só com `isAdmin` / `isGestor` conforme o bloco.
  - `ComunicadosView.vue` — acções de CRUD alargadas com `isAdmin` (base `USUARIO_ADMIN` + perfil), distinto de `isAdmin` do Pinia; **há duas definições de “admin” na app** (ver código antes de auditar “paridade” com a API).
- **Recomendação de auditoria (Gemini / QA):** para cada botão/aba `v-if`, mapear o `api.*` que ele dispara e cruzar com a rota Laravel; **esconder na UI** não implica rota inacessível por `curl` ou outra tela.

**Desalinhamentos sidebar vs `router` (verificar em QA):**

| Rota | `ALL_NAV_ITEMS` (perfis) | `router/index.js` `meta.roles` |
|------|--------------------------|----------------------------------|
| `/progressao-funcional` | Visível a **todos** autenticados (`roles: []`) | `['admin', 'rh']` — **utilizador comum é redirecionado ao Dashboard** ao abrir a URL |
| `/atestados-medicos` | Só `admin`, `rh` na secção Frequência | **Sem** `meta.roles` — tecnicamente a rota é para **qualquer** autenticado (se souber a URL) |

*Nota: corrigir ou alinhar estes três sítios (menu, guard, API) se a regra de negócio o exigir.*

---

*Gerado a partir de `DashboardLayout.vue`, `router/index.js` e greps `api.(get|post|...)` nas views V1–V3 acima, em 2026-04-27.*
