# Fixture Shadow — smoke E2E (dados 100% fictícios)

Pasta: `2026-04/`

- **3 servidores** (`servidores.csv`) com CPFs inventados.
- **Líquidos** e **rubricas** alinhados entre `resultado_legado` / `resultado_gente` e pares de rubricas, para o diff concluir sem `FALHA_SISTEMICA_CRITICA` (com o mesmo limiar P3).
- `manifest.json` **canónico** (hashes SHA-256 e contagens) + `metadata.json` com `limiar_divergencia`.

Uso (a partir de `gente/`):

```bash
FIX=tests/fixtures/shadow_smoke_e2e/2026-04
php artisan shadow:snapshot-validar 2026-04 --snapshot-dir=$FIX
php artisan shadow:snapshot-canonico-validar 2026-04 --snapshot-dir=$FIX
# Com DB: php artisan shadow:dispatch 2026-04 --snapshot-dir=$FIX --etapa=todas
```

Não contém PII real.
