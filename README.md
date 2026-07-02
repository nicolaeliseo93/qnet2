# AI Agent OS

Questo repository contiene un sistema operativo locale per agenti AI.

## Da Dove Iniziare

- Bootstrap primario: [AGENTS.md](AGENTS.md)
- Shim specifico per Claude: [CLAUDE.md](CLAUDE.md)
- Shim specifico per Codex: [CODEX.md](CODEX.md)
- Bootstrap manuale copia/incolla: [PROMPT-START.md](PROMPT-START.md)

## Standard Obbligatori (bootstrap)

- [AI Rules](standards/ai-rules.md)
- [Orchestration Rules](standards/orchestration.md)
- [Routing Matrix](standards/routing-matrix.md)
- [Handoff Protocol](standards/handoff-protocol.md)

## Standard Secondari

Richiamati come obbligatori dai singoli agenti:

- [Architecture](standards/architecture.md)
- [Coding Standards](standards/coding-standards.md)
- [Security Standards](standards/security-standards.md)
- [Quality Gates](standards/quality-gates.md)
- [Decision Making](standards/decision-making.md)
- [Product Rules](standards/product-rules.md)
- [Company Values](standards/company-values.md)

## Directory Agenti

- `agents/`

## Directory Standard

- `standards/`

## Directory Template

- `templates/`

## Come Funziona

1. Bootstrap da `AGENTS.md`
2. Routing della richiesta
3. Caricamento dei soli agenti rilevanti
4. Esecuzione nell'ordine definito
5. Chiusura di ogni fase con un handoff strutturato

## Nota Pratica

Alcuni strumenti leggono automaticamente file convenzionali come `AGENTS.md` o `CLAUDE.md`.

Se uno strumento non lo fa, usa il testo di bootstrap in `PROMPT-START.md`.
