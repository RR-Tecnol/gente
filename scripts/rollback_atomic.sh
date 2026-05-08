#!/usr/bin/env bash
set -euo pipefail

# Rollback atômico por symlink.
# Uso:
#   ./scripts/rollback_atomic.sh /var/www/gente

APP_ROOT="${1:-}"

if [[ -z "${APP_ROOT}" ]]; then
  echo "Uso: $0 <app_root>"
  exit 1
fi

CURRENT_LINK="${APP_ROOT}/current"
PREVIOUS_LINK="${APP_ROOT}/previous"

if [[ ! -L "${PREVIOUS_LINK}" ]]; then
  echo "Sem release anterior em ${PREVIOUS_LINK}"
  exit 1
fi

PREV_TARGET="$(readlink -f "${PREVIOUS_LINK}")"
if [[ ! -d "${PREV_TARGET}" ]]; then
  echo "Release anterior inválido: ${PREV_TARGET}"
  exit 1
fi

ln -sfn "${PREV_TARGET}" "${CURRENT_LINK}"
echo "Rollback atômico aplicado: ${CURRENT_LINK} -> ${PREV_TARGET}"

