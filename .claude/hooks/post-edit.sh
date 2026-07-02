#!/usr/bin/env bash
# PostToolUse hook — gate di qualità write-time.
# Dopo ogni Edit/Write, formatta/lint il file toccato secondo lo stack.
# Exit code 2 = blocca e rimanda feedback all'agente; 0 = ok.
#
# NOTA: il formato dello stdin dell'hook può variare per versione di Claude Code.
# Qui estraiamo il path con python3 dalla chiave tool_input.file_path; se la tua
# versione usa una chiave diversa, adatta la riga FILE_PATH qui sotto.

set -uo pipefail

INPUT="$(cat)"
FILE_PATH="$(printf '%s' "$INPUT" | python3 -c "import sys,json;
try:
    d=json.load(sys.stdin); ti=d.get('tool_input',{})
    print(ti.get('file_path') or ti.get('path') or '')
except Exception:
    print('')" 2>/dev/null)"

# Niente path → non blocchiamo.
[ -z "$FILE_PATH" ] && exit 0
[ ! -f "$FILE_PATH" ] && exit 0

fail() { echo "[quality-gate] $1" >&2; exit 2; }

case "$FILE_PATH" in
  backend/*.php)
    if [ -x "backend/vendor/bin/pint" ]; then
      (cd backend && ./vendor/bin/pint --test "${FILE_PATH#backend/}") \
        || fail "Pint: il file non rispetta lo stile. Esegui 'cd backend && ./vendor/bin/pint ${FILE_PATH#backend/}' e correggi."
    fi
    ;;
  frontend/*.ts|frontend/*.tsx|frontend/*.js|frontend/*.jsx)
    # blocco rapido: console.log lasciati nel codice
    if grep -nE 'console\.(log|debug)' "$FILE_PATH" >/dev/null 2>&1; then
      fail "Trovato console.log/debug in $FILE_PATH — rimuovilo prima di considerare completo."
    fi
    if [ -f "frontend/package.json" ] && [ -d "frontend/node_modules" ]; then
      (cd frontend && npx eslint "${FILE_PATH#frontend/}") \
        || fail "ESLint: errori in $FILE_PATH. Correggili prima di proseguire."
    fi
    ;;
esac

exit 0
