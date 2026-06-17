# Política de wiring de rotas (S2.2 / F.3)

## Anti-closure de negócio

- `routes/web.php` deve permanecer como **agregador** (`require` de módulos) e grupos mínimos.
- Rotas de negócio (handlers longos) pertencem a **ficheiros dedicados** em `routes/`, com controllers em `app/Http/Controllers`.

## Anti-duplicidade (S2.1)

- `composer run check-routes` (em `gente/`) — deve terminar com `OK:`.
- **Onde correr:** local ou runner com o mesmo `/.env` que a app. *Atualizado 2026-04-27:* removido DDL no carregamento dos ficheiros de rota de vários módulos (DDL só em `ensure*FromRoutes` ao tratar o pedido); ainda é necessária ligação SQL para o Laravel, mas o `php artisan route:list` deixou de forçar `hasTable` em massa no `require` desses ficheiros.

## Crescimento de `web.php` (S2.3)

- Acompanhar linhas aproximadas (baseline em `docs/PROGRAMA_S0_BASELINE_2026-04-26.md`).
- Novas áreas: preferir novo ficheiro `routes/nome_modulo.php` e um `require` no agregador.
