#!/usr/bin/env bash
# SessionStart hook — MEMORIA PERSISTENTE (carico), versione standalone.
# Ricostruisce il contratto "memory-persistence" di ECC SENZA il suo state-store
# SQLite / metriche ECC2 / continuous-learning. Zero dipendenze esterne.
#
# Cosa fa: a ogni nuova sessione (e per ogni teammate che parte) inietta nel
# contesto il file di memoria viva `docs/HANDOFF.md` (limitato) + l'indice delle
# spec in `docs/specs/`. Così il modello parte SEMPRE grounded → contromisura
# diretta al context-rot.
#
# Opt-out:   MEMORY_CONTEXT=off
# Limite:    MEMORY_MAX_CHARS (default 6000) per non gonfiare il contesto.

set -uo pipefail
cat >/dev/null 2>&1 || true   # drena lo stdin dell'hook (ignorato qui)

[ "${MEMORY_CONTEXT:-on}" = "off" ] && exit 0

MAX="${MEMORY_MAX_CHARS:-6000}"
ROOT="${CLAUDE_PROJECT_DIR:-.}"
HANDOFF="$ROOT/docs/HANDOFF.md"
SPECS_DIR="$ROOT/docs/specs"

emitted=0

if [ -f "$HANDOFF" ]; then
  echo "=== MEMORIA DI PROGETTO — docs/HANDOFF.md (stato corrente) ==="
  head -c "$MAX" "$HANDOFF"
  echo ""
  echo "=== fine HANDOFF (tronca a ${MAX} char; apri il file per il resto) ==="
  emitted=1
fi

if [ -d "$SPECS_DIR" ]; then
  echo ""
  echo "=== SPEC DISPONIBILI — docs/specs/ ==="
  found=0
  for f in "$SPECS_DIR"/*.md "$SPECS_DIR"/*.xml; do
    [ -f "$f" ] || continue
    echo "- ${f#$ROOT/}"
    found=1
  done
  [ "$found" = "0" ] && echo "(nessuna spec ancora — crea la prima da docs/specs/templates/)"
  emitted=1
fi

[ "$emitted" = "0" ] && echo "(memoria vuota: crea docs/HANDOFF.md per persistere il contesto tra sessioni)"
exit 0
