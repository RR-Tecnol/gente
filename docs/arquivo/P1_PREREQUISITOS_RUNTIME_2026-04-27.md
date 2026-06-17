# P1 — Pré-requisitos de Runtime para Upgrade e Homologação (2026-04-27)

## Obrigatórios

- PHP >= 8.2 (ambiente atual já atende).
- Extensão `bcmath` habilitada no PHP CLI/FPM.

## Evidência atual

- `gente:healthcheck --json --skip-db` retornou `p1_ext_bcmath: missing`.

## Ação operacional

- Provisionar pacote/extensão de BCMath no host e reiniciar serviço PHP.
- Guia de segurança para essa mudança: `docs/P1_INSTALACAO_BCMATH_CYBERSEGURANCA_2026-04-27.md`.
- Revalidar com:
  - `php -m | rg bcmath`
  - `php artisan gente:healthcheck --json --skip-db`
  - `php artisan gente:prontidao-certificar --json --skip-db`
- Préflight automatizado disponível:
  - `./scripts/preflight_prontidao.sh`

## Risco mitigado

- Evita divergência financeira por operações em float no diff matemático.
- Evita falha de execução em `shadow:dispatch` (etapa diff) e `ShadowDiffChunkJob`.
- Remove blocker `BLOQ-P1-BCMATH` da matriz de go-live.

