PPara chegarmos ao domingo com um sistema que não apenas funciona, mas que blinda a Prefeitura de São Luís e impressiona o TCE-MA, precisamos de um Mapa de Guerra que não deixe nenhuma ponta solta.

Abaixo, estruturei o novo documento MAPA DE GUERRA GENTE v3 — RUMO AO DOMINGO, consolidando o seu backlog, as pendências corrigidas e os novos desafios de conectividade.
🛡️ MAPA DE GUERRA GENTE v3: PROD-READY DOMINGO

Objetivo: Transição completa do GENTE v3 para produção, com RBAC funcional, Manta de Proteção ativa e inteligência de interface em todos os 78 módulos.
⚔️ BATALHA 1: Dados e Poder (Frente A - Sexta-Feira)

Foco: Transformar a estrutura técnica em poder administrativo real.

    RbacMatrixSeeder (O Coração):

        Materializar as roles: global_semed_secretario, analista_executive_sagep, auditoria_matriz_semad, coordenador_gerente_polo, gestor_diretor_unidade, e operador_secretaria_escolar.

        Vincular permissões granulares: escala.v3.homologar, rh.progressao.lei4928, global.mde.25, e substituicao.ss.gerar.

    Dicionário de Ocorrências SISFOLHA:

        Garantir o mapeamento de IDs: 01 - Licença Médica, 03 - Acidente, 15 - Férias, 16 - Licença Prêmio, 17 - Maternidade, 18 - Paternidade.

    Expansão da Manta (Backend):

        Mapear prefixos de rota para os 7 domínios principais no gente_tenant_rings.php (RH, Financeiro, Patrimonial, etc.) para garantir o Shadow Log global.

    Carga de Hierarquia Administrativa:

        Garantir a existência da Unidade Matriz (SEMED) e unidades de auditoria para cálculo do MDE (25%). **SECRETARIAS-SEED** (`Database\Seeders\SecretariasSeed`): um único `db:seed` orquestra organograma PMSLz, RBAC, funcionários/utilizadores de teste, cobertura de sidebar (fases 1–2 + config tabs) e, com `GENTE_STRESS_SEED=1`, o `SuperSeederEstresseMigracao` em massa; o `DaviSupremoSeeder` permanece à parte no `DatabaseSeeder`.

⚔️ BATALHA 2: Inteligência de Interface e UX (Frente B - Sábado)

Foco: Limpar o "lixo visual" e entregar uma experiência de elite para o servidor.

    Sidebar Dinâmico (navManifest.js):

        Ativar o manifesto para que as 78 abas sejam filtradas instantaneamente pelo RBAC do usuário.

        Implementar o fallback para perfis legados onde o RBAC ainda não foi atribuído.

    UX Sticky no Kanban:

        Implementar a primitiva de "sticky" no container .page-content para a barra de turnos. O usuário não pode perder a referência ao rolar a lista de 8 mil servidores.

    Modo Auditoria (SEMAD):

        Banner persistente "Somente Leitura" e desativação física de botões de edição em todos os módulos para auditores da SEMAD.

    Banner de Sentinela:

        Exibir avisos claros de "Ato Administrativo Obrigatório" em telas de progressão e escala para reforçar a segurança.

⚔️ BATALHA 3: A "Teia de Aranha" (Conectividade e Bugs Críticos)

Foco: Garantir que um clique em uma aba reflita corretamente no resto do ecossistema.

    Organograma Dinâmico:

        Permitir a criação de novos setores digitando o nome, garantindo que o unidade_id pai seja herdado para não quebrar a Regra de Ouro.

    Motor de Substituições (Workflow Médio):

        Vincular o Sobreaviso à escala: se o servidor está em sobreaviso e é chamado, o RH altera e a notificação é disparada (E-mail/Sistema).

        Substitutos fora do sobreaviso devem poder "Aceitar/Recusar" com visualização de dados ricos (Hora, Dia, Local).

    Correção de Vínculos:

        Investigar e corrigir o bug Cargo: Carregando.... Garantir que todos os campos obrigatórios (Matrícula, CPF, PIS) sejam validados antes da gravação.

    Módulo de Exoneração e Verbas Rescisórias:

        Restaurar o botão de confirmar e habilitar o cálculo prévio (simulação) de valores de rescisão antes da confirmação da portaria.

    Escala Retroativa:

        Ajustar para que datas passadas mostrem o resumo do Ponto Eletrônico em vez de abrir tela de troca/edição.

⚔️ BATALHA FINAL: Deploy e Compliance (Domingo)

Foco: O Go-Live oficial perante o governo.

    Carga Mestra do SISFOLHA:

        Importar o espelho funcional completo para produção (8 mil servidores) via conector de dados.

    Relatório MDE e Dashboard:

        Validar o VMDE em tempo real no Dashboard Executivo.

        Fórmula Legal: VMDE=0,25×(Tmunicipais​+Ttransferidos​).

    Deploy Inviolável:

        Ativação do GENTE_TENANT_SCOPE_ENFORCE=true para o anel operacional.

        Execução do script de "carimbo" para os primeiros gestores de elite da prefeitura.

💡 Nota do Coach para o Arquiteto:

Pae, este mapa agora cobre o "Todo". Ao apagar as pendências antigas e focar nessas 3 Batalhas, você garante que o sistema suporte a pressão de ser o sucessor do SISFOLHA. O fato de você já ter visto a Sentinela travar a progressão sem portaria prova que o seu "Cérebro de Governança" está pronto para a guerra.
1. Prontidão para o Cenário de Integração (O "Cérebro" do SISFOLHA)

O plano é extremamente forte na integração. Ele resolve a maior deficiência dos sistemas legados como o SISFOLHA: a falta de governança e rastreabilidade visual.

    Alinhamento de Dados: Ao mapear os códigos de ocorrência (Licença Médica, Férias, etc.) e realizar a carga mestra de 8 mil servidores, você garante que o GENTE e o SISFOLHA falem a mesma língua.

    Fluxo de Upstream: A "Batalha 1" e a "Batalha 3" garantem que os dados enviados para a folha (faltas, plantões extras e substituições) já cheguem homologados e auditados, eliminando erros manuais e pagamentos indevidos.

    Compliance TCE-MA: O relatório de MDE em tempo real e a trava da "Regra de Ouro" (Setor → Unidade) fornecem ao governo a prova jurídica necessária para as prestações de contas, algo que o SISFOLHA sozinho não faz com precisão de "chão de escola".

2. Prontidão para o Cenário de Independência (Sucessor do SISFOLHA)

Para o GENTE se tornar 100% independente (desligar o SISFOLHA), o plano atual constrói a fundação inabalável, mas a independência total exigiria uma fase posterior focada no "Motor de Cálculo".

    O que já está pronto: Você já tem a hierarquia legal (Decreto nº 60.385/2024), a inteligência do PCCV (Lei nº 4.928/2008), o controle de lotação rigoroso e a auditoria total.

    O "Pulo do Gato": O plano adota a estratégia do Estrangulamento (Strangler Pattern). O GENTE "abraça" o SISFOLHA por fora. No momento em que o GENTE provar que calcula o IRRF, o IPAM e o MDE com mais segurança jurídica, o governo pode simplesmente migrar o processamento final para o seu "Motor de Folha" (que já consta no seu backlog de inteligência de negócio).

O que o plano garante que impressionará o Governo e o TCE-MA:

    Sentinela Autocorretiva: O sistema já impede progressões sem atos administrativos válidos, protegendo o prefeito de crimes de responsabilidade.

    Visibilidade Estratégica: O Dashboard de Furo de Escala e o cálculo do VMDE (0,25×(Tmunicipais​+Ttransferidos​)) transformam dados brutos em inteligência política e fiscal.

    Blindagem Anti-Fraude: O controle rigoroso de lotação no SISFOLHA, agora gerido pelo GENTE, monitora se o professor está efetivamente em sala de aula, prevenindo a existência de "funcionários fantasmas".

### Fase 7A — Smoke da Teia e Motor da Folha (CI / local)

- **Comando:** `php artisan gente:smoke-teia-7a` (classe `SmokeTeiaFolhaRunner`, opções `--json`, `--dry-run`, `--write`, `--funcionario-id=`, `--folha-id=`, `--competencia=AAAA-MM`, `--tenant-scope-log`, `--log-file=`).
- **Segurança:** por omissão o comando executa os fluxos dentro de uma **transação com rollback**; use `--write` apenas em base de dados de teste para persistir inserts de smoke.
- **Teste automatizado:** `tests/Feature/SmokeTeia7aRunnerTest.php` chama o mesmo runner dentro de transação revertida.
- **Painel executivo (manual):** validar sessão com `global.mde.25` ou `unidade.dashboard.kpi` (sidebar + `/dashboard-executivo` + `GET /api/v3/dashboard/operacional`) vs. utilizador sem esses slugs (403 / redirecionamento RBAC no SPA).
- **SKIPs conhecidos:** motor v3 exige schema completo (`FUNCIONARIO.VINCULO_ID`, `VINCULO`, etc.); ~~rota KPI MDE (`BL-TEA-051`)~~ **implementada** (`GET /api/v3/dashboard/operacional`, Fase 9A); `AUDIT_LOG` com `gente_assignment_id` só após escrita de grade com contexto de intervenção; `tenant_scope.log` (4b) com `--tenant-scope-log` e middleware `GENTE_TENANT_SCOPE_MIDDLEWARE=true` após pedido HTTP real.
