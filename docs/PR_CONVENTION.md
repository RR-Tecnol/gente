# Convenção de pull request — programa GENTE (SEMAD)

Alinhada ao `PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26` (S0.2).

## Regras

1. **Um objetivo por PR** — facilita review, rollback e `bisect`.
2. **PR pequeno** — preferir divisão a um diff de milhares de linhas.
3. **Checklist** — preencher `SPRINT_ACEITE_TEMPLATE.md` no corpo do PR (ou link para a seção de aceite no plano).
4. **Testes mínimos** — indicar o que rodou (ex.: `composer run check-routes`, `phpunit`, smoke manual em homolog).
5. **Rotas** — novas rotas de negócio em arquivos `routes/*.php` incluídos por `web.php` (não lógica longa em closure em `web.php`).

## Título sugerido

`[Sprint Sn] Área: descrição curta` — exemplo: `[S1] Security: reforçar CheckPerfil em folha V3`
