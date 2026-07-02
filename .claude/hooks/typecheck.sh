#!/usr/bin/env bash
# Stop hook — gate TYPECHECK frontend (il pezzo che ESLint NON copre e che
# `vite build` NON esegue). Gira `tsc --noEmit` sul progetto frontend a fine
# turno. Exit 2 = rimanda gli errori di tipo all'agente; 0 = pulito/non applicabile.
#
# Batch a fine turno (non per-edit) per non rallentare ogni Edit.
# Opt-out: TYPECHECK_GATE=off

set -uo pipefail
cat >/dev/null 2>&1 || true

[ "${TYPECHECK_GATE:-on}" = "off" ] && exit 0

ROOT="${CLAUDE_PROJECT_DIR:-.}"
FE="$ROOT/frontend"

# Applicabile solo se esiste un frontend TS con dipendenze installate.
[ -f "$FE/tsconfig.json" ] || exit 0
[ -d "$FE/node_modules" ] || exit 0

# Preferisci il tsc locale; fallback a npx.
if [ -x "$FE/node_modules/.bin/tsc" ]; then
  TSC="$FE/node_modules/.bin/tsc"
else
  TSC="npx --no-install tsc"
fi

OUT="$(cd "$FE" && $TSC --noEmit --pretty false 2>&1)"
STATUS=$?

if [ $STATUS -ne 0 ]; then
  echo "[typecheck] tsc --noEmit ha trovato errori di tipo nel frontend:" >&2
  printf '%s\n' "$OUT" | head -40 >&2
  echo "[typecheck] Correggi i tipi prima di considerare il task completo (vite build NON li intercetta)." >&2
  exit 2
fi

exit 0
