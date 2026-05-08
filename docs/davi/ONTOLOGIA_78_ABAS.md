# Fase 10B — Matriz de rastreabilidade: abas (navManifest) → Vue → API → dados

Fonte da lista de rotas: [`resources/gente-v3/src/navigation/navManifest.js`](../../resources/gente-v3/src/navigation/navManifest.js) (`NAV_MANIFEST`).  
Router: [`resources/gente-v3/src/router/index.js`](../../resources/gente-v3/src/router/index.js).  
Orquestrador de seeds: [`database/seeders/SecretariasSeed.php`](../../database/seeders/SecretariasSeed.php).

**Contagem:** 81 entradas `type: 'item'`; 2 com `sidebar: false` (`/funcionario/:id`, `/escala-matriz-v3`). **Itens com link na sidebar:** 79 (documentação histórica fala em ~78 — mesma ordem de grandeza).

**Legenda — classificação (`Cls`)**

| Valor | Significado |
|--------|-------------|
| `seed_ok` | Há massa explícita em `SidebarCoverageSeeder`, `SystemPhase2CoverageSeeder`, `ConfigTabsCoverageSeeder`, `ErpFiscalCoverageSeeder`, `OrganogramaPMSLz` / `FuncionariosPMSLz` / `Feriados2026` ou `PcaspSagres` de forma que a API tende a devolver listas não vazias após `db:seed`. |
| `stub` | API real, mas dados “de governo” são placeholders (ex.: XML eSocial, exportações). |
| `vazio` | API existe; o seed não garante linhas específicas para todos os filtros da tela (pode listar vazio em cenários limpos ou só herdar dados indirectos). |
| `import` | Enriquecimento realístico depende de importação (ex.: SISFOLHA 8A), não do `SecretariasSeed` só. |

**Legenda — blocos de seed (`S`)**

| Sigla | Seeder |
|--------|--------|
| **SB** | `SidebarCoverageSeeder` |
| **P2** | `SystemPhase2CoverageSeeder` |
| **CFG** | `ConfigTabsCoverageSeeder` |
| **ORG** | `OrganogramaPMSLzSeeder` + `FuncionariosPMSLzSeeder` + `UsuariosPMSLzSeeder` + núcleo RBAC |
| **FOL** | `FOLHA` / `DETALHE_FOLHA` via SB |
| **PC** | `PcaspSeeder` + `SagresDeParaSeeder` |
| **ERP** | `ErpFiscalCoverageSeeder` |
| **STR** | `SuperSeederEstresseMigracao` (opcional; volume, não regra por aba) |

---

## Matriz (sidebar visível)

| Rota | Componente Vue | Endpoints API (principais) | Tabelas / domínio (alto nível) | Cls | S |
|------|----------------|----------------------------|----------------------------------|------|---|
| `/dashboard` | `HomeView.vue` | `GET /api/v3/dashboard` | Agregados diversos sobre `FUNCIONARIO`, folha, etc. | seed_ok | ORG, P2, SB |
| `/dashboard-executivo` | `DashboardExecutivoView.vue` | `GET /api/v3/dashboard/operacional` | KPIs agregados (escala, MDE, servidores) | seed_ok | ORG, SB, STR? |
| `/meu-perfil` | `MeuPerfilView.vue` | `GET/PUT /api/v3/perfil` | `USUARIO`, `PESSOA`, vínculos | seed_ok | ORG |
| `/ponto` | `PontoEletronicoView.vue` | `GET/POST /api/v3/ponto`, `ponto/config`, `heatmap-risco` | `REGISTRO_PONTO`, `PONTO_CONFIG_*`, `JORNADA_LEDGER` | seed_ok | SB |
| `/meus-holerites` | `ContraChequeView.vue` | `GET /api/v3/meus-holerites`, PDFs | `DETALHE_FOLHA`, `FOLHA` | seed_ok | SB, FOL |
| `/ferias-licencas` | `FeriasLicencasView.vue` | `GET/POST /api/v3/ferias`, `afastamentos` | `FERIAS`, `AFASTAMENTO` | seed_ok | P2 |
| `/banco-horas` | `BancoHorasView.vue` | `GET /api/v3/banco-horas`, `equipe` | `BANCO_HORAS`, `LOTACAO` | seed_ok | SB |
| `/declaracoes-requerimentos` | `DeclaracoesRequerimentosView.vue` | `GET/POST /api/v3/declaracoes` | `DECLARACAO` | seed_ok | SB |
| `/progressao-funcional` | `ProgressaoFuncionalView.vue` | `GET /api/v3/servidor/progressao` | Progressão / carreira | seed_ok | ORG, P2 |
| `/minhas-substituicoes` | `MinhasSubstituicoesView.vue` | `GET/PUT /api/v3/substituicoes/minhas` | `SUBSTITUICAO` | seed_ok | SB |
| `/portal-gestor` | `PortalGestorView.vue` | `GET/POST /api/v3/gestor`, `avaliacoes` | Pendências, equipe | seed_ok | P2, SB |
| `/organograma` | `OrganogramaView.vue` | `GET/PUT/POST/DELETE /api/v3/organograma/*` | `UNIDADE`, `SETOR`, `LOTACAO` | seed_ok | ORG, SB |
| `/escala-trabalho` | `EscalaTrabalhoView.vue` | `GET/POST /api/v3/escala-trabalho`, `substituicoes`, `ponto` | `ESCALA`, `DETALHE_ESCALA`, `TURNO` | seed_ok | SB |
| `/substituicoes` | `SubstituicoesView.vue` | `GET/POST /api/v3/substituicoes`, `escalas` | `SUBSTITUICAO_ESCALA`, `SUBSTITUICAO` | seed_ok | SB |
| `/escala-sobreaviso` | `EscalaSobreavisoView.vue` | `GET/POST /api/v3/sobreaviso` | Plantão sobreaviso | seed_ok | P2 |
| `/hora-extra` | `HoraExtraView.vue` | `GET/POST /api/v3/hora-extra` | `HORA_EXTRA` | seed_ok | SB |
| `/plantoes-extras` | `PlantoesExtrasView.vue` | `GET/POST /api/v3/plantoes-extras` | `PLANTAO_EXTRA` | seed_ok | SB |
| `/funcionarios` | `FuncionariosView.vue` | `GET/POST/PUT/DELETE /api/v3/funcionarios` | `FUNCIONARIO`, `PESSOA`, `LOTACAO` | seed_ok | ORG, STR? |
| `/autocadastro-gestao` | `AutocadastroGestaoView.vue` | `/api/v3/autocadastro/*` | Tokens autocadastro | vazio | P2 parcial |
| `/cargos-salarios` | `CargosSalariosView.vue` | `GET/POST /api/v3/cargos`, `funcoes` | Cargos, funções | seed_ok | ORG, P2 |
| `/contratos-vinculos` | `ContratosVinculosView.vue` | `GET /api/v3/contratos` | Contratos RH | seed_ok | P2 |
| `/progressao-admin` | `ProgressaoAdminView.vue` | `/api/v3/progressao-funcional/*`, `cargos`, `setores` | Progressão admin | seed_ok | P2 |
| `/exoneracao` | `ExoneracaoView.vue` | `/api/v3/exoneracao/*`, `rescisao` | Rescisão / exoneração | vazio | P2 parcial |
| `/pss` | `PSSView.vue` | `/api/v3/pss/editais` | PSS / editais | vazio | P2 |
| `/estagiarios` | `EstagiariosView.vue` | `/api/v3/estagiarios` | Estagiários | vazio | P2 |
| `/terceirizados` | `TerceirizadosView.vue` | `/api/v3/terceirizados/*` | Empresas, postos | seed_ok | P2 |
| `/acumulacao-cargos` | `AcumulacaoView.vue` | `GET/POST /api/v3/acumulacao` | Acumulação | vazio | P2 |
| `/diarias` | `DiariasView.vue` | `/api/v3/diarias` | Diárias | seed_ok | P2 |
| `/avaliacao-gestor` | `AvaliacaoGestorView.vue` | `GET/POST /api/v3/avaliacoes` | Avaliações | seed_ok | P2 |
| `/beneficios` | `BeneficiosView.vue` | `/api/v3/beneficios` | Benefícios servidor | seed_ok | P2 |
| `/treinamentos-admin` | `TreinamentosAdminView.vue` | `/api/v3/treinamentos-admin/*` | Cursos, inscrições admin | seed_ok | P2 |
| `/medicina-admin` | `MedicinaAdminView.vue` | `/api/v3/medicina-admin/*` | Exames SESMT admin | seed_ok | P2 |
| `/seguranca-admin` | `SegurancaAdminView.vue` | `/api/v3/seguranca-admin/*` | EPIs, laudos, incidentes | seed_ok | P2 |
| `/faltas-atrasos` | `FaltasAtrasosView.vue` | `secretarias`, `abonos-gestao` | Abonos / faltas | seed_ok | P2, SB |
| `/abono-faltas` | `AbonoFaltasView.vue` | `/api/v3/abono-faltas` | Abono (legado paralelo a gestão) | seed_ok | P2 |
| `/atestados-medicos` | `AtestadosMedicosView.vue` | `/api/v3/atestados` | Atestados | seed_ok | P2 |
| `/medicina-trabalho` | `MedicinaTrabalhoView.vue` | `/api/v3/medicina` | Medicina ocupacional | seed_ok | P2 |
| `/seguranca-trabalho` | `SegurancaTrabalhoView.vue` | `/api/v3/seguranca/*` | EPIs, incidentes | seed_ok | P2 |
| `/folha-pagamento` | `FolhaPagamentoView.vue` | `/api/v3/folhas/*`, `rubricas` | `FOLHA`, `DETALHE_FOLHA`, lançamentos | seed_ok | SB, P2 |
| `/consignacao` | `ConsignacaoView.vue` | `/api/v3/consignacao/*` | `CONSIG_*` | seed_ok | P2 |
| `/consignatarias` | `ConsignatariasView.vue` | `/api/v3/consignatarias/*` | `CONSIGNATARIA`, remessas | seed_ok | P2 |
| `/verba-indenizatoria` | `VerbaIndenizatoriaView.vue` | `/api/v3/verba-indenizatoria/*` | Verbas | vazio | P2 parcial |
| `/beneficios-admin` | `BeneficiosAdminView.vue` | `/api/v3/beneficios/catalogo`, relatórios | Catálogo benefícios | seed_ok | P2 |
| `/rpps` | `RPPSView.vue` | `/api/v3/rpps/*` | RPPS / IPAM | vazio | P2 parcial |
| `/remessa-cnab` | `RemessaCnabView.vue` | `/api/v3/cnab/*`, `folhas` | CNAB, folhas | vazio | FOL |
| `/gestao-declaracoes` | `GestaoDeclaracoesView.vue` | `/api/v3/rh/declaracoes`, `modelos` | Declarações RH | vazio | P2 |
| `/oss` | `OssView.vue` | `GET /api/v3/oss` | OSS / indicadores saúde | vazio | P2 |
| `/compras` | `ComprasView.vue` | `/api/v3/compras/*` | Processos, pedidos | seed_ok | P2 |
| `/almoxarifado` | `AlmoxarifadoView.vue` | `/api/v3/almoxarifado/*` | Itens, movimentações | seed_ok | P2 |
| `/patrimonio` | `PatrimonioView.vue` | `/api/v3/patrimonio/*` | Bens, depreciação | seed_ok | P2 |
| `/contratos-admin` | `ContratosAdminView.vue` | `/api/v3/contratos-admin/*` | Contratos administrativos | seed_ok | P2 |
| `/frotas` | `FrotasView.vue` | `/api/v3/frotas/*` | Veículos, saídas | seed_ok | P2 |
| `/esocial` | `ESocialView.vue` | `/api/v3/esocial/*` | `ESOCIAL_EVENTO` | stub | P2 |
| `/sagres-tce` | `SagresView.vue` | `/api/v3/sagres/*` | `SAGRES_EXPORTACAO`, de-para | stub | PC, P2 |
| `/transparencia` | `TransparenciaView.vue` | `POST /api/v3/transparencia/exportar` | `TRANSPARENCIA_EXPORTACAO` | stub | P2 |
| `/avaliacao-desempenho` | `AvaliacaoDesempenhoView.vue` | `/api/v3/avaliacoes` | Avaliações | seed_ok | P2 |
| `/treinamentos` | `TreinamentosView.vue` | `/api/v3/treinamentos/*` | Treinamentos | seed_ok | P2 |
| `/pesquisa-satisfacao` | `PesquisaSatisfacaoView.vue` | `/api/v3/pesquisas` | Pesquisas | seed_ok | P2 |
| `/pesquisa-admin` | `PesquisaAdminView.vue` | `/api/v3/pesquisas/admin` | Pesquisas admin | seed_ok | P2 |
| `/agenda` | `AgendaView.vue` | `GET/POST /api/v3/agenda` | `AGENDA` | seed_ok | P2 |
| `/comunicados` | `ComunicadosView.vue` | `/api/v3/comunicados` | Comunicados | seed_ok | P2 |
| `/notificacoes` | `NotificacoesView.vue` | `/api/v3/notificacoes` | Notificações | seed_ok | P2 |
| `/ouvidoria` | `OuvidoriaView.vue` | `GET/POST /api/v3/ouvidoria` | Ouvidoria | seed_ok | P2 |
| `/ouvidoria-admin` | `OuvidoriaAdminView.vue` | `/api/v3/ouvidoria/admin` | Painel ouvidoria | seed_ok | P2 |
| `/relatorios` | `RelatoriosView.vue` | `/api/v3/relatorios/*` | Vários relatórios SQL | seed_ok | ORG, SB |
| `/configuracoes` | `ConfiguracoesView.vue` | `admin/vinculos`, `ponto/config`, `funcionarios` | Config mista | seed_ok | CFG, SB |
| `/configuracao-sistema` | `ConfiguracaoSistemaView.vue` | `configuracoes/api`, `vinculos`, `rubricas` | Motor folha | seed_ok | ORG, CFG |
| `/parametros-financeiros` | `ParametroFinanceiroView.vue` | `/api/v3/parametros-financeiros` | Parâmetros | vazio | CFG |
| `/vinculos` | `VinculosView.vue` | `/api/v3/vinculos` | `VINCULO` | seed_ok | ORG, CFG |
| `/turnos` | `TurnosView.vue` | `/api/v3/turnos` | `TURNO` | seed_ok | CFG, SB |
| `/feriados` | `FeriadosView.vue` | `/api/v3/feriados/manager` | Feriados | seed_ok | CFG, `Feriados2026Seeder` |
| `/tabelas-auxiliares` | `TabelasAuxiliaresView.vue` | `/api/v3/tabelas/*` | `BANCO`, `UF`, `CIDADE`, etc. | seed_ok | CFG |
| `/eventos-folha` | `EventosView.vue` | `/api/v3/eventos` | Eventos folha | seed_ok | CFG |
| `/orcamento` | `OrcamentoView.vue` | `/api/v3/orcamento/*` | `ORCAMENTO_PPA`, `ORCAMENTO_LOA`, … | seed_ok | SB, ERP |
| `/execucao-despesa` | `ExecucaoDespesaView.vue` | `/api/v3/empenho` | `EMPENHO`, `LIQUIDACAO` | seed_ok | SB, ERP |
| `/contabilidade` | `ContabilidadeView.vue` | `balancete`, `pcasp`, `lancamentos` | `PCASP_CONTA`, `LANCAMENTO_CONTABIL` | seed_ok | PC, ERP |
| `/tesouraria` | `TesourariaView.vue` | `/api/v3/tesouraria/*` | Contas, fluxo (pode cruzar `CONTA_BANCARIA`) | seed_ok | SB |
| `/receita-municipal` | `ReceitaMunicipalView.vue` | `/api/v3/receita/*` | `RECEITA_LANCAMENTO`, dívida ativa | seed_ok | SB, ERP |
| `/controle-externo` | `ControleExternoView.vue` | `controle-externo/envios`, `sagres`, `siconfi` | RREO, RGF, SICONFI | stub | P2 |

---

## Rotas só no router (sidebar: false)

| Rota | Componente | API principal | Cls | S |
|------|------------|---------------|------|---|
| `/funcionario/:id` | `PerfilFuncionarioView.vue` | `/api/v3/funcionarios/{id}/*` | seed_ok | ORG, SB |
| `/escala-matriz-v3` | `MatrizEscalaView.vue` | `/api/v3/escalas/*`, `substituicoes` | seed_ok | SB |

---

## Domínios (resumo cruzado)

| Domínio | Abas (rotas) | Observação |
|---------|--------------|------------|
| 1 — Saúde ocupacional e benefícios | medicina/segurança (admin e servidor), atestados, diárias, benefícios, banco de horas, faltas/abono | P2 + SB; CIDs/EPIs devem usar catálogo mínimo. |
| 2 — ERP operacional / admin | compras, almoxarifado, patrimônio, frotas, contratos-admin, OSS, escalas | P2 forte em patrimônio/frotas/compras. |
| 3 — Folha e previdência | folha, holerites, consignação, consignatárias, CNAB, RPPS, eSocial | eSocial = stub; consignação = P2. |
| 4 — Fiscal / contabilidade | orçamento, execução, contabilidade, tesouraria, receita, controle externo | Invariantes: [FISCAL_SEED_INVARIANTS.md](FISCAL_SEED_INVARIANTS.md); `ErpFiscalCoverageSeeder`. |
| 5 — Comunicação e BI | dashboard, executivo, agenda, comunicados, notificações, ouvidoria, pesquisas, relatórios | Painel executivo depende de agregações + escala. |

---

## Manutenção

- Ao criar uma nova entrada em `NAV_MANIFEST`, actualizar esta matriz e o teste de fumo manual sugerido em `abas-sidebar.md`.
- Para expandir Domínio 4, editar primeiro `FISCAL_SEED_INVARIANTS.md`, depois [`ErpFiscalCoverageSeeder.php`](../../database/seeders/ErpFiscalCoverageSeeder.php) (evitar duplicar lógica em `SystemPhase2CoverageSeeder`).
