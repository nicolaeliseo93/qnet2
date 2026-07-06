# Rules — UI/UX (shadcn/ui + Tailwind 4)

> Caricare quando il task tocca: stile, design system, responsive, accessibilità.
> Presuppone il CORE in `CLAUDE.md`.

## 1. Design system = unica fonte di verità

I componenti in `components/ui/` sono l'**unica** base di stile. Le schermate li compongono, non li duplicano.
- Varianti via `class-variance-authority` (cva).
- Merge classi con `tailwind-merge` (`cn()`), mai concatenazione manuale conflittuale.
- Icone: `lucide`. Toast: `sonner`. Tema dark: `next-themes` + CSS variables (non colori hard-coded).
- Prima di creare un componente, verifica se esiste già in `components/ui/`. Qualsiasi elemento usato in 2+ schermate → estrai con props/slot.

## 2. Sizing scale (default compatti)

> **Preferenza cliente (vincolante).** Il committente di questo progetto **preferisce
> le UI piccole e compatte**: niente elementi giganti. A parità di scelta, prendi
> **l'estremo più piccolo** della scala. Tab/chip/toolbar → `text-xs`, padding minimi
> (`px-2.5 py-1`), icone `size-3.5`. Ingrandisci solo quando la leggibilità (WCAG,
> target tap ≥ 24px) o una CTA primaria lo richiedono davvero.

| Elemento | Default | Note |
|---|---|---|
| Testo body | `text-sm` / `text-base` | `text-base` solo per contenuti principali |
| Heading pagina | `text-xl` / `text-2xl` | mai oltre `text-3xl` |
| Heading card | `text-base font-semibold` | compatto |
| Padding card | `p-3` / `p-4` | `p-6` solo card grandi |
| Padding bottone | `px-3 py-1.5` | `px-4 py-2` solo CTA primari |
| Tab / chip | `text-xs px-2.5 py-1`, icona `size-3.5` | strip compatta, mai tab giganti |
| Gap griglia | `gap-3` / `gap-4` | `gap-6` solo sezioni separate |
| Radius / ombra | `rounded-lg` / `shadow-sm` | `shadow-md` solo su hover/focus |

## 3. Responsive (mobile-first)

- Parti dal layout mobile, poi `sm:` / `md:` / `lg:`.
- **Mai altezze fisse** (`h-[500px]`): usa `min-h-` / `max-h-` / `h-auto`. **Mai larghezze fisse** su container: `w-full max-w-{size}`.
- Verifica mentale a **375px / 768px / 1024px**: niente scrollbar orizzontale, niente sovrapposizioni, `truncate`/`line-clamp` sui testi lunghi, `overflow-auto` consapevole, z-index corretti.

## 4. Accessibilità (WCAG)

- Contrasto sufficiente; non comunicare lo stato col solo colore.
- Elementi interattivi raggiungibili da tastiera, `focus-visible` evidente.
- `aria-*` e `label` corretti su form e controlli; usa gli elementi semantici giusti (`button`, non `div` cliccabile).
- Componenti shadcn/Radix sono accessibili di base: non disabilitarne il comportamento (focus trap nei dialog, ecc.).

## 5. Checklist pre-commit UI

- [ ] A 375px: nessuna scrollbar orizzontale, testo leggibile, target tap raggiungibili
- [ ] A 768px: layout si adatta, nessuna sovrapposizione
- [ ] A 1024px+: spazi proporzionati, nessun componente troppo largo
- [ ] Overflow gestito, z-index corretto, focus da tastiera visibile
- [ ] Riuso di `components/ui/` invece di duplicare stile

## 6. Anti-pattern UI (Problema → Mitigazione)

### 6.1 Componenti sovradimensionati e non responsive
**Problema.** L'AI crea componenti visivamente troppo grandi (card, menu, testi), con sovrapposizioni, scrollbar buggate, pulsanti accavallati.

**Mitigazione obbligatoria.**
- **Mobile-first:** parti dal layout mobile, poi `sm:`/`md:`/`lg:`. Default **compatti** (vedi tabella §2).
- **Mai** altezze fisse (`h-[500px]`) → `min-h-`/`max-h-`/`h-auto`. **Mai** larghezze fisse su container → `w-full max-w-{size}`.
- Verifica a **375 / 768 / 1024**: niente scrollbar orizzontale, niente sovrapposizioni, `truncate`/`line-clamp` sui testi lunghi, `overflow-auto` consapevole, z-index corretti.

### 6.2 Mancata riusabilità dei componenti
**Problema.** L'AI crea componenti simili-ma-diversi per ogni schermata invece di riutilizzarli.

**Mitigazione obbligatoria.**
- Prima di creare un componente, **verifica** se esiste già in `components/ui/`.
- Qualsiasi elemento usato in **2+ schermate** → estrai con **props/slot** (varianti via `cva`), non duplicare.
- `components/ui/` è l'**unica fonte di verità** dello stile di base: le schermate compongono, non duplicano (vedi §1).
