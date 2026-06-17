# Programa GENTE — Baseline S0 (2026-04-26)

Registro de artefatos do **S0.1** do plano `PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26.md`.

## Snapshot de rotas

| Artefato | Descrição |
|----------|-----------|
| `gente/docs/arquivo/baseline-routes-2026-04-26.json` | Saída de `php artisan route:list --columns=method,uri,name --json` (regenerar após mudanças grandes) |

**Regenerar:**

```bash
cd gente
php artisan route:list --columns=method,uri,name --json 2>/dev/null \
  > docs/arquivo/baseline-routes-$(date +%F).json
```

## Orçamento do wiring (`S2.3` — referência)

Medição pontual 2026-04-26 (não bloqueia PR sozinha; comparar com baseline em revisões de arquitetura):

- `routes/web.php`: **1668** linhas no snapshot 2026-04-26 (re-medir com `wc -l routes/web.php` ao atualizar o baseline).

## Fontes canônicas do plano (repo)

- `gente/docs/arquivo/PLANO_IMPLEMENTACAO_BRAIN_SEMAD_2026-04-26.md` (cópia espelhada no Obsidian em `GENTE/planos/`)

## Procedimentos S0.2 e S0.3

- PR / aceite: `gente/docs/PR_CONVENTION.md`, `gente/docs/SPRINT_ACEITE_TEMPLATE.md`
- Rollback: `gente/docs/ROLLBACK_SPRINT.md`


Atualização 2026-04-27: baseline route JSON regenerado após S4 fase 1 (tamanho: 88281 bytes).
