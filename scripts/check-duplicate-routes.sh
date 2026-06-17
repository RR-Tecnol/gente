#!/usr/bin/env bash
# Fase F.3.1 — colisões METHOD+URI a partir de `php artisan route:list --json`
# Nota: o Laravel regista a última rota por URI+method; a lista final pode NÃO
# mostrar duas linhas para a mesma URI. Duplicatas em ficheiro exigem audit ao código
# (grep) ou o doc routes-audit-*.md. Isto ainda assinalha se o *resolver* tiver
# entradas repetidas anómalas.
# Uso: na raiz do app Laravel (gente/): ./scripts/check-duplicate-routes.sh
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="${TMPDIR:-/tmp}/rl-$$.json"
php artisan route:list --path=api/v3 --json 2>/dev/null > "$OUT" || {
  echo "Falha ao gerar route:list --json"
  exit 2
}
python3 - "$OUT" <<'PY' || exit 1
import json, sys
from collections import Counter
path = sys.argv[1]
rows = json.load(open(path, encoding="utf-8"))
if not isinstance(rows, list):
    print("Formato inesperado: esperado array JSON", file=sys.stderr)
    sys.exit(2)
keys = []
for r in rows:
    m = (r.get("method") or "").strip()
    u = (r.get("uri") or "").lstrip("/")
    if m:
        keys.append((m, u))
c = Counter(keys)
dups = sorted([(k, n) for k, n in c.items() if n > 1], key=lambda x: (x[0][1], x[0][0]))
if dups:
    print("Colisões em api/v3 (método normalizado + uri):", file=sys.stderr)
    for (method, uri), n in dups:
        print(f"  {n}x  {method}  {uri}", file=sys.stderr)
    sys.exit(1)
print("Nenhuma colisão reportada (GET|HEAD contado como GET|HEAD; ver route:list se precisar pormenor).")
PY
rm -f "$OUT"
exit 0
