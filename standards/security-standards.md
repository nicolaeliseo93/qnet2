# Security Standards

## Purpose

Definire i requisiti minimi di sicurezza per tutti i progetti.

---

# Trust Nothing

Nessun dato proveniente dal frontend deve essere considerato attendibile.

Tutte le validazioni devono essere replicate lato backend.

---

# Authorization First

Ogni endpoint deve verificare autorizzazioni e permessi.

---

# Principle Of Least Privilege

Utenti e sistemi devono avere esclusivamente i permessi necessari.

---

# Secrets Management

È vietato salvare:

- Password
- Token
- API Key
- Secret

all'interno del repository.

---

# Logging

Non registrare mai:

- Password
- Token
- Dati sensibili
- Informazioni personali non necessarie

---

# Input Validation

Tutti gli input devono essere validati.

---

# Database Security

Utilizzare sempre query parametrizzate e ORM.

Evitare concatenazioni SQL manuali.

---

# Dependency Security

Aggiornare regolarmente dipendenze e framework.

Verificare vulnerabilità note.
