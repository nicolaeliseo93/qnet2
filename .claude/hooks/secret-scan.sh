#!/usr/bin/env bash
# PostToolUse hook — secret scanner write-time.
# Blocca (exit 2) se il file appena scritto contiene un segreto in chiaro.
# Pattern volutamente conservativi: meglio un falso positivo che una key committata.

set -uo pipefail

INPUT="$(cat)"
FILE_PATH="$(printf '%s' "$INPUT" | python3 -c "import sys,json;
try:
    d=json.load(sys.stdin); ti=d.get('tool_input',{})
    print(ti.get('file_path') or ti.get('path') or '')
except Exception:
    print('')" 2>/dev/null)"

[ -z "$FILE_PATH" ] && exit 0
[ ! -f "$FILE_PATH" ] && exit 0

# Non scansionare gli esempi/template.
case "$FILE_PATH" in
  *.env.example|*.md|*.lock) exit 0 ;;
esac

fail() { echo "[secret-scan] $1" >&2; exit 2; }

# .env reale non deve mai essere scritto nel repo tracciato.
case "$FILE_PATH" in
  *.env) fail "Stai scrivendo un file .env. I segreti non vanno nel repo: usa .env (gitignored) localmente e .env.example senza valori." ;;
esac

# Pattern di segreti in chiaro.
PATTERNS='(API[_-]?KEY|SECRET|PASSWORD|TOKEN|PRIVATE[_-]?KEY)\s*[:=]\s*["'"'"'][^"'"'"' ]{8,}|sk-[A-Za-z0-9]{16,}|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY-----'

if grep -nEi "$PATTERNS" "$FILE_PATH" >/dev/null 2>&1; then
  fail "Possibile segreto in chiaro in $FILE_PATH. Spostalo in .env (gitignored) e referenzialo via config()/import.meta.env."
fi

exit 0
