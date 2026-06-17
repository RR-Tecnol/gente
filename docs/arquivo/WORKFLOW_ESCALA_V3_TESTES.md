# Workflow de aprovação da Escala v3 — testes e verificação

## Migration

```bash
php artisan migrate
```

Confirma colunas em `ESCALA`: `ESCALA_ENVIADA_*`, `ESCALA_HOMOLOGADA_*`, `ESCALA_MOTIVO_DEVOLUCAO`, `ESCALA_DEVOLVIDA_*` e normalização de `ESCALA_STATUS` legado (`Aberta`/vazio → `RASCUNHO`, `PUBLICADA`/homólogos → `HOMOLOG_SAGEP`).

## API

| Cenário | Como verificar |
|--------|----------------|
| **GET** `/api/v3/escala-trabalho?mes=&ano=&setor_id=` | Resposta inclui `workflow` com `status`, `status_label`, `permissoes`, `pode_editar_grade_sudo` (Sudo + cabeçalho `X-Gente-Global-View`), e (se devolvida) `motivo_devolucao` / `devolvido_por_nome`. |
| **GET** com `carregar_tudo=1` (sem `setor_id`) | `workflow` mínimo (visão macro) com `pode_editar_grade_sudo` e `status: null`. |
| **POST** `/api/v3/escala-trabalho` com grade bloqueada | `422` quando `ESCALA_STATUS` não permite edição **e** não há Sudo válido (mesma regra que `assertPodeEditarGrade(..., $request)`). |
| **POST** intervenção Sudo em grade trancada | Com Sudo: gravação permitida; `AUDIT_LOG`: `ACAO`/`acao` = `ESCALA_INTERVENCAO_SUDO_GRADE`, `evento` = `INTERVENCAO_SUDO_GRADE` (se coluna existir), JSON com `intervencao_sudo_grade`, `escala_status_no_evento`, `competencia`, `setor_id`, `operacao`. |
| **POST** `/api/v3/escala-trabalho/workflow` | Corpo: `mes`, `ano`, `setor_id`, `acao` (`enviar_validacao`, `reenviar_validacao`, `devolver_ajuste`, `homologar`), `motivo_devolucao` obrigatório em `devolver_ajuste`. |
| **Copiar mês** | `POST /api/v3/escala-trabalho/copiar-mes-anterior` falha com a mesma trava se o destino não for editável. |
| **Sudo / visão global** | Cabeçalho `X-Gente-Global-View: true` com utilizador autorizado: transições permitidas; auditoria com `bypass_administrativo: true` no contexto JSON. |

## Frontend (Kanban)

- **Fase 1.5 (Sudo na grade):** se `workflow.pode_editar_grade_sudo === true`, a grade deixa de ser somente leitura mesmo com status homologado/em validação ou em visão macro; UI mostra aviso dourado/raio. **Datas passadas** continuam bloqueadas no POST (sem bypass Sudo na Fase 1.5).
- Sem Sudo: fora de `RASCUNHO` e `DEVOLVIDA_AJUSTE`, grade somente leitura; visão macro continua somente leitura.
- Em `DEVOLVIDA_AJUSTE`: banner fixo no topo com motivo e quem devolveu.
- Botões de workflow visíveis conforme `workflow.permissoes` (na macro, só painel de estado quando Sudo; ações de workflow exigem setor).

## PHPUnit (regras puras)

```bash
cd gente && ./vendor/bin/phpunit tests/Unit/EscalaWorkflowServiceTest.php
```
