# S1 — Continuação RBAC + Migrations canônicas (2026-04-27)

## Entregas

- `routes/rpps.php`
  - rotas de mutação críticas com `middleware('perfil:ADMINISTRADOR,Administrador,GESTOR')`
  - `ensureRppsProvaVidaTables()` em modo estrito (sem DDL em runtime)
- `routes/esocial.php`
  - rotas de mutação críticas com `middleware('perfil:ADMINISTRADOR,Administrador,GESTOR')`
- migration canônica criada:
  - `database/migrations/2026_04_27_123000_create_rpps_prova_vida_tables.php`

## Resultado

- Avanço de S1.1 (RBAC fino) e S1.4 (migrações canônicas) em módulos críticos de RPPS/eSocial.
- `composer run check-routes` permanece sem colisões.


## Bloco adicional (benefícios/afastamentos)

- `routes/beneficios.php`
  - `ensureBeneficioTablesFromRoutes()` em modo estrito (sem DDL runtime)
  - mutações protegidas com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/afastamentos_v3.php`
  - `ensureAnexoAfastamentoTableV3()` em modo estrito (sem DDL runtime)
  - mutações principais protegidas com `perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR`
- migrations canônicas novas:
  - `2026_04_27_124000_create_beneficio_tables.php`
  - `2026_04_27_124100_create_anexo_afastamento_table.php`


## Bloco adicional 2 (medicina admin / SST / treinamentos)

- `routes/medicina_admin.php`
  - `ensureExameOcupacionalTableFromRoutes()` em modo estrito
  - mutações críticas protegidas com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/seguranca_trabalho.php`
  - `ensureSegurancaTrabalhoTablesFromRoutes()` em modo estrito
  - mutações administrativas (`CAT`, entrega EPI, laudos) com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/treinamentos.php`
  - `ensureTreinamentoTablesFromRoutes()` em modo estrito
  - mutações de cursos (create/update) com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- migrations canônicas novas:
  - `2026_04_27_125000_create_exame_ocupacional_table.php`
  - `2026_04_27_125100_create_seguranca_trabalho_tables.php`
  - `2026_04_27_125200_create_treinamento_tables.php`


## Bloco adicional 3 (medicina/cnab/parâmetros/pesquisa)

- `routes/medicina.php`
  - `ensureAgendamentoExameTableFromRoutes()` em modo estrito
  - `POST /medicina/agendar` com `perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR`
- `routes/cnab.php`
  - `ensureCnabRemessaTableFromRoutes()` em modo estrito
  - `POST /cnab/gerar` com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/parametros_financeiros_v3.php`
  - `ensureParametroFinanceiroTableFromRoutes()` em modo estrito
  - mutações com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/pesquisa.php`
  - `ensurePesquisaRhTablesFromRoutes()` em modo estrito
  - criação/resultados com perfil administrativo; resposta com perfil servidor/admin
- migrations canônicas novas:
  - `2026_04_27_130000_create_agendamento_exame_table.php`
  - `2026_04_27_130100_create_cnab_remessa_table.php`
  - `2026_04_27_130200_create_parametro_financeiro_table.php`
  - `2026_04_27_130300_create_pesquisa_rh_tables.php`


## Bloco final residual (escala + avaliação)

- `routes/api_v3_auth_part2.php`
  - removido `Schema::create` runtime para `ESCALA_SNAPSHOT`/`ESCALA_EVENTO`
  - `POST /escalas/{id}/salvar` protegido com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- `routes/avaliacao_desempenho.php`
  - `ensureTabelasAvaliacao()` em modo estrito (sem DDL runtime)
  - `POST /avaliacoes` protegido com `perfil:ADMINISTRADOR,Administrador,GESTOR`
- migrations canônicas novas:
  - `2026_04_27_131000_create_escala_snapshot_evento_tables.php`
  - `2026_04_27_131100_create_avaliacao_desempenho_tables.php`

Com este bloco, a varredura por `Schema::create` em `routes/*.php` ficou zerada no repositório.

## Endurecimento adicional de boot de rotas

- Aplicado `if (!function_exists(...))` nos helpers `ensure*FromRoutes` dos módulos:
  - `parametros_financeiros_v3`, `cnab`, `medicina`, `treinamentos`,
    `seguranca_trabalho`, `medicina_admin`, `beneficios`, `pesquisa`.
- Objetivo: evitar erro fatal de redeclaração em cenários de bootstrap duplicado das rotas.
