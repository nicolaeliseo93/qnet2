#!/usr/bin/env bash
# Stop hook — MEMORIA PERSISTENTE (salvataggio), versione standalone.
# Non scrive da solo (servirebbe il modello): nudge deterministico a persistere
# lo stato in docs/HANDOFF.md quando la sessione ha prodotto modifiche reali.
# Coppia con session-start.sh: load all'avvio, reminder alla fine.
#
# Euristica: se ci sono modifiche git non committate, ricorda di aggiornare
# HANDOFF + fare il checkpoint. Mai bloccante (exit 0).

set -uo pipefail
cat >/dev/null 2>&1 || true

ROOT="${CLAUDE_PROJECT_DIR:-.}"
cd "$ROOT" 2>/dev/null || exit 0

# Solo in un repo git; altrimenti niente segnale affidabile.
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || exit 0

CHANGED="$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')"
[ "${CHANGED:-0}" -eq 0 ] && exit 0

echo "[memoria] $CHANGED file modificati non committati." >&2
echo "[memoria] Prima di chiudere: aggiorna docs/HANDOFF.md (cosa fatto, cosa verificare, prossimo owner) e fai un git checkpoint a stato verde." >&2
exit 0
