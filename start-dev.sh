#!/usr/bin/env bash
# Organismo vivo local: API HTTP + worker de fila num único comando.
# Uso: ./start-dev.sh   (a partir da pasta gente/)
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

cleanup() {
  if [[ -n "${SERVE_PID:-}" ]] && kill -0 "$SERVE_PID" 2>/dev/null; then
    kill "$SERVE_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT INT TERM

php artisan serve --host=127.0.0.1 --port=8081 &
SERVE_PID=$!

echo "[start-dev] artisan serve em http://127.0.0.1:8081 (pid $SERVE_PID)"
echo "[start-dev] queue:work em primeiro plano (Ctrl+C encerra o worker e o serve)"
exec php artisan queue:work
