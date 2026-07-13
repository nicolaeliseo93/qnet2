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

FINDING="$(python3 - "$FILE_PATH" <<'PY'
import re
import sys

try:
    text = open(sys.argv[1], encoding='utf-8', errors='replace').read()
except OSError:
    sys.exit(0)

# Forme inequivocabili: qualunque occorrenza e' un segreto, senza eccezioni.
HARD_PATTERNS = [
    (re.compile(r'sk-[A-Za-z0-9]{16,}'), 'chiave in formato sk-*'),
    (re.compile(r'AKIA[0-9A-Z]{16}'), 'AWS access key id'),
    (re.compile(r'-----BEGIN [A-Z ]*PRIVATE KEY-----'), 'blocco di chiave privata'),
]

# Assegnazione di un letterale a una chiave dal nome sospetto.
ASSIGNMENT = re.compile(
    r'(API[_-]?KEY|SECRET|PASSWORD|TOKEN|PRIVATE[_-]?KEY)\s*[:=]\s*["\']([^"\'\s]{8,})["\']',
    re.IGNORECASE,
)

# Soglia oltre la quale anche una stringa di sole lettere e' trattata come segreto.
ALPHABETIC_SECRET_MIN_LENGTH = 16


def line_of(offset: int) -> int:
    return text.count('\n', 0, offset) + 1


for pattern, label in HARD_PATTERNS:
    match = pattern.search(text)
    if match:
        print(f'{label}, riga {line_of(match.start())}')
        sys.exit(1)

for match in ASSIGNMENT.finditer(text):
    key, value = match.group(1), match.group(2)
    # Una credenziale reale porta cifre o simboli, oppure e' lunga. Una stringa di
    # sole lettere e corta e' una etichetta di interfaccia (i18n: `password: 'Password'`):
    # trattarla come segreto bloccherebbe ogni modifica ai dizionari di traduzione.
    if value.isalpha() and len(value) < ALPHABETIC_SECRET_MIN_LENGTH:
        continue
    print(f"'{key}' assegna un valore letterale, riga {line_of(match.start())}")
    sys.exit(1)

sys.exit(0)
PY
)"

if [ -n "$FINDING" ]; then
  fail "Possibile segreto in chiaro in $FILE_PATH ($FINDING). Spostalo in .env (gitignored) e referenzialo via config()/import.meta.env."
fi

exit 0
