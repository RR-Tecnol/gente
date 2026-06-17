**Massa de dados (SECRETARIAS-SEED):** um único orquestrador `database/seeders/SecretariasSeed.php` substitui a lista longa no `DatabaseSeeder` (mantém-se à parte só o `DaviSupremoSeeder`). Inclui organograma PMSLz (secretarias de São Luís), funcionários de teste, utilizadores PMSLz, `SidebarCoverageSeeder`, `SystemPhase2CoverageSeeder`, `ErpFiscalCoverageSeeder` (execução despesa + PCASP mínimos, ver `docs/davi/FISCAL_SEED_INVARIANTS.md`), `ConfigTabsCoverageSeeder`, etc. Matriz aba → API → classificação de cobertura: `docs/davi/ONTOLOGIA_78_ABAS.md`. Com `GENTE_STRESS_SEED=1` no ambiente, no final corre `SuperSeederEstresseMigracao` (dezenas de milhares de servidores — só homolog/stress).

**Fase 12 — Homologação RBAC (opt-in):** com `GENTE_SEED_AUDITOR_SUPREMO=1`, o `DaviSupremoSeeder` atribui dois `GENTE_ASSIGNMENT` ao fundador: `auditor_homologacao_ti` (GLOBAL_SEMED, todos os slugs da matriz) e `auditoria_matriz_semad` (GLOBAL_SEMAD), para testar o painel executivo e o modo read-only SEMAD com o *context switcher*. Ver `BUSINESS_RULES` (secção Fase 12). O backlog de performance da folha/CNAB (Fase 13) está em `docs/davi/PERFORMANCE_BACKLOG.md`.

**Cobertura 10B — Saúde e benefícios (opt-in):** com `GENTE_SAUDE_BENEFICIOS_SEED=1`, o `SaudeEBeneficiosCoverageSeeder` corre logo após `FuncionariosPMSLzSeeder` e insere dados mínimos para servidores **SEMED**: `AFASTAMENTO` tipo LM (&gt;15 dias, alinhado ao Kanban), `ATESTADO_MEDICO` com CID **Z73** quando a tabela existir, `CONSIG_CONTRATO` com parcela limitada a **30%** do vencimento-base simulado (`ProgressaoFuncionalElegibilidadeService::getVencBase`) e linhas em `AGENDAMENTO_EXAME` (SESMT). Idempotência via texto `10B_SEED_COVERAGE` em `OBSERVACAO` / contratos. Útil para validar abas como **Atestados Médicos**, **Consignações**, **Gestão SESMT** e escala (alerta &gt;15 dias).

1. Visão Geral e Área Pessoal

    Dashboard (Módulo principal de indicadores)

    Painel executivo (KPIs agregados: servidores activos, taxa de furo de escala no dia, elegíveis SEMED/MDE; RBAC: permissão `global.mde.25` **ou** `unidade.dashboard.kpi`; rota API `GET /api/v3/dashboard/operacional`)

    Meu Perfil

    Ponto Eletrônico

    Meus Holerites

    Férias e Licenças

    Banco de Horas

    Declarações

    Minha Progressão

    Minhas Substituições

2. Gestão de Equipa e Operacional

    Portal do Gestor

    Estrutura Organizacional (Onde vive o nosso Organograma)

    Escalas (O Motor de Escalas que estamos a blindar)

    Substituições

    Sobreaviso

    Hora Extra

    Horas / Plantões Extras (Botão de ação rápida)

**Hora extra vs plantões extras:** *Hora extra* (`HoraExtraView.vue`, API `GET/PATCH` em `/api/v3/hora-extra`) regista **horas extraordinárias já trabalhadas**, com fluxo de aprovação e consolidação em folha (entidade típica `HORA_EXTRA`). *Plantões extras* (`PlantoesExtrasView.vue`, API `/api/v3/plantoes-extras`) tratam de **plantões agendados** ligados à escala/setor (turno extraordinário), entidade `PLANTAO_EXTRA`. Não são duplicados no produto: um é liquidação em horas trabalhadas; o outro é planeamento de cobertura em plantão.

3. Recursos Humanos e Ciclo de Vida

    Funcionários

    Autocadastro

    Cargos e Salários

    Contratos e Vínculos

    Gerir Progressões (Ligado à Lei nº 4.928/2008)

    Exoneração / Rescisão

    PSS / Concurso

    Estagiários

    Terceirizados

    Acumulação de Cargos

4. Gestão Administrativa de Pessoal

    Diárias

    Avaliações da Equipe

    Gestão de Benefícios

    Gestão de Treinamentos

    Gestão SESMT (Med.)

    Gestão SESMT (Seg.)

5. Frequência e Saúde Ocupacional

    Faltas e Atrasos

    Abono de Faltas

    Atestados Médicos

    Medicina do Trabalho

    Segurança do Trabalho

6. Financeiro, Folha e Previdência

    Folha de Pagamento (O core ligado ao SISFOLHA)

    Consignações

    Consignatárias

    Verbas Indenizatórias

    Benefícios

    RPPS / IPAM (Previdência municipal)

    Remessa CNAB

    Gestão de Declarações

7. Administrativo e Patrimonial

    Monitor OSS

    Compras e Licitações

    Almoxarifado

    Patrimônio

    Contratos

    Frotas

8. Compliance e Auditoria

    eSocial

    SAGRES / TCE-MA (Onde os dados do MDE são validados)

    Transparência Pública

9. Desenvolvimento e Comunicação

    Avaliação de Desempenho

    Treinamentos

    Pesquisa de Satisfação

    Gerenciar Pesquisas

    Agenda

    Comunicados

    Notificações

    Ouvidoria

    Painel Ouvidoria

10. Configurações e Inteligência de Negócio

    Relatórios

    Configurações Gerais

    Motor de Folha

    Parâmetros Financeiros

    Vínculos

    Turnos

    Feriados e Folgas

    Tabelas Auxiliares

    Eventos de Folha

11. ERP / Fiscal (O nível estratégico final)

    Orçamento (PPA/LOA)

    Execução da Despesa

    Contabilidade (PCASP)

    Tesouraria

    Receita Municipal

    Controle Externo
