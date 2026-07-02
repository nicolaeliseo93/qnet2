# Decision Making

## Purpose

Questo documento definisce come prendere decisioni tecniche, architetturali e di prodotto.

---

# Decision Hierarchy

Quando esistono più soluzioni possibili, seguire questo ordine:

1. Correttezza
2. Sicurezza
3. Manutenibilità
4. Semplicità
5. Performance
6. Innovazione

---

# Simplicity First

A parità di risultato, scegliere sempre la soluzione più semplice.

---

# Prefer Known Solutions

Preferire tecnologie e pattern già conosciuti dal team rispetto a soluzioni sperimentali.

---

# Avoid Premature Abstraction

Non introdurre astrazioni fino a quando non esiste una reale necessità.

---

# Minimize Dependencies

Ogni dipendenza aggiunta rappresenta un rischio.

Prima di introdurre una nuova libreria verificare:

- Utilità reale
- Maturità
- Community
- Manutenibilità

---

# Reuse Before Build

Prima di creare una nuova soluzione verificare se ne esiste già una all'interno del progetto.

---

# Document Important Decisions

Ogni decisione architetturale significativa deve essere registrata tramite ADR, usando il template `templates/architecture-decision.md`.
