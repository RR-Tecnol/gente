# Catálogo: `Schema::create` em rotas (S1.4)

Objetivo: migrar criação de tabelas para **migrations** canónicas em `database/migrations/`, com revisão e backup.

> Lista gerada em 2026-04-26 por grepping `routes/`. Conferir no código antes de alterar.

## Passo 2026-04-27 (reestruturação, não migration nova)

Foi feita **higiene de boot**: o DDL deixou de executar no `require` do ficheiro (evita `Schema::hasTable` ao correr `php artisan route:list` / CI sem tocar tudo). A lógica de criação condicional passou para funções `ensure*FromRoutes()` chamadas **só** nos handlers (ex.: `ensureTreinamentoTablesFromRoutes`, `ensurePesquisaRhTablesFromRoutes`). **Ferramentas ainda a migrar** para tabela 100% via `database/migrations/`: ações listadas abaixo, por prioridade de negócio.

| Ficheiro | Tabelas (Schema::create) |
|----------|-------------------------|
| `routes/api_v3_auth_part2.php` | `ESCALA_SNAPSHOT`, `ESCALA_EVENTO` |
| `routes/afastamentos_v3.php` | `ANEXO_AFASTAMENTO` |
| `routes/avaliacao_desempenho.php` | `AVALIACAO_DESEMPENHO`, `AVALIACAO_CRITERIO` |
| `routes/cnab.php` | `CNAB_REMESSA` |
| `routes/feriados_v3.php` | `calendar_overrides` (ver também migration 2026_04_26) |
| `routes/pesquisa.php` | `PESQUISA`, `PESQUISA_PERGUNTA`, `PESQUISA_RESPOSTA` |
| `routes/treinamentos.php` | `TREINAMENTO`, `TREINAMENTO_INSCRICAO` |
| `routes/seguranca_trabalho.php` | `EPI_REGISTRO`, `ACIDENTE_TRABALHO`, `LAUDO_SST` |
| `routes/parametros_financeiros_v3.php` | `PARAMETRO_FINANCEIRO` |
| `routes/medicina_admin.php` | `EXAME_OCUPACIONAL` |
| `routes/medicina.php` | `AGENDAMENTO_EXAME` |
| `routes/beneficios.php` | `BENEFICIO`, `FUNCIONARIO_BENEFICIO` |

## Plano (próximos passos)

1. Priorizar tabelas com maior risco (concorrência, dados pessoais, folha).
2. Para cada tabela: `php artisan make:migration` alinhada ao `Schema::create` existente; opção A) `create` só se não existir, opção B) `Schema::hasTable` no código de rota a permanecer até remoção em release posterior.
3. Remover `Schema::create` da rota após validação em homolog.
4. Documentar a sprint que removeu a última ocorrência.

## Outros `Schema::` em rotas

- Procurar `Schema::` além de `create` (alter, drop) com `rg "Schema::" gente/routes/`.
