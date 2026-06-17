#!/usr/bin/env bash
set -euo pipefail

# Deploy atômico por symlink.
# Uso:
#   ./scripts/deploy_atomic.sh /var/www/gente /var/www/gente/releases/20260427_150000

APP_ROOT="${1:-}"
NEW_RELEASE="${2:-}"

if [[ -z "${APP_ROOT}" || -z "${NEW_RELEASE}" ]]; then
  echo "Uso: $0 <app_root> <new_release_path>"
  exit 1
fi

if [[ ! -d "${NEW_RELEASE}" ]]; then
  echo "Release não encontrado: ${NEW_RELEASE}"
  exit 1
fi

CURRENT_LINK="${APP_ROOT}/current"
PREVIOUS_LINK="${APP_ROOT}/previous"

mkdir -p "${APP_ROOT}"

if [[ -L "${CURRENT_LINK}" ]]; then
  PREV_TARGET="$(readlink -f "${CURRENT_LINK}")"
  ln -sfn "${PREV_TARGET}" "${PREVIOUS_LINK}"
fi

ln -sfn "${NEW_RELEASE}" "${CURRENT_LINK}"
echo "Deploy atômico aplicado: ${CURRENT_LINK} -> ${NEW_RELEASE}"

