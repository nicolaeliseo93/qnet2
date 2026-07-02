# Frontend

Applicazione **React (TypeScript + Vite)**. Owner: Frontend Agent.

Tutto il codice client-side vive esclusivamente qui. Nessun codice backend in questa cartella.
Le chiamate HTTP passano sempre da un **API Layer** dedicato, mai direttamente nei componenti UI.

## Stato

- ✅ React **19** + TypeScript + Vite **7** installato
- ✅ **Skeleton pulito**: rimossa la demo Vite (asset, CSS e contenuti di esempio). `App.tsx` minimale, `index.css` con solo reset di base
- ✅ Build e lint verificati (`npm run build`, `npm run lint`)
- ✅ Layer applicativo documentato per lo starter
- ✅ Tooling frontend previsto nello starter: **Animate UI** per motion e **shadcn/ui Chart** per data visualization

## Stack

React · TypeScript · Vite · React Router · TanStack Query · React Hook Form · Zod · Tailwind CSS · shadcn/ui · Animate UI · shadcn/ui Chart · Recharts · Axios

> Riferimento: [`../standards/architecture.md`](../standards/architecture.md).

## Layering (vedi `standards/architecture.md`)

```
Page → Feature → Component → UI Component
```

- **Page**: composizione schermata, niente business logic
- **Feature**: query, mutation, form, stato della feature
- **Component**: riutilizzabili, piccoli, testabili
- **Motion**: Animate UI come scelta preferita per componenti animati
- **Charts**: shadcn/ui Chart come layer di composizione sopra Recharts

## Setup locale

Prerequisiti: Node.js 20+ (testato su 22), npm 10+.

```bash
cd frontend
npm install
npm run dev     # http://localhost:5173
```

Altri comandi:

```bash
npm run build   # build di produzione in dist/
npm run lint    # ESLint
```

## Testing

Vitest · React Testing Library
