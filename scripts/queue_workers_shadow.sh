#!/usr/bin/env bash
# P3/§15.8 — worker local/homolog para filas shadow (Laravel queue:work).
# Uso: a partir de gente/:  ./scripts/queue_workers_shadow.sh
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"
exec php artisan queue:work --sleep=1 --tries=3 \
  --queue=queue-shadow-etl,queue-shadow-calc,queue-shadow-diff,default
