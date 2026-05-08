#!/usr/bin/env bash
set -euo pipefail

# Preflight técnico para gate de go-live.
# Uso:
#   ./scripts/preflight_prontidao.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

echo "== Preflight de Prontidão GENTE =="
echo "Diretório: ${ROOT_DIR}"
echo

echo "[1/5] Versão do PHP"
php -v | sed -n '1,1p'
echo

echo "[2/5] Extensões críticas"
if php -m | rg -i '^bcmath$' >/dev/null 2>&1; then
  echo "OK: bcmath carregada"
else
  echo "FAIL: bcmath ausente"
  if command -v rpm >/dev/null 2>&1; then
    echo "Pacotes RPM relacionados (se houver):"
    rpm -qa 'php*bcmath*' 2>/dev/null || true
  fi
  echo "Sugestão (Fedora): sudo dnf install -y php-bcmath && sudo systemctl restart php-fpm"
  echo "Segurança: ver docs/P1_INSTALACAO_BCMATH_CYBERSEGURANCA_2026-04-27.md"
fi
echo

echo "[3/5] Healthcheck rápido (skip-db)"
php artisan gente:healthcheck --json --skip-db || true
echo

echo "[4/5] Certificação de prontidão (skip-db)"
php artisan gente:prontidao-certificar --json --skip-db || true
echo

echo "[5/5] Ping do banco + certificação com banco (opcional; falha rápida se SQL Server estiver fora)"
php artisan gente:db-ping --json || true
php artisan gente:prontidao-certificar --json || true
echo

echo "Fim do preflight."

if ! php -m | rg -i '^bcmath$' >/dev/null 2>&1; then
  exit 1
fi

