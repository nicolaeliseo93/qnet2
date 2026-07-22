# Rules — UI/UX (shadcn/ui + Tailwind 4)

> Caricare quando il task tocca: stile, design system, responsive, accessibilità.
> Presuppone il CORE in `CLAUDE.md`.

## 1. Design system = unica fonte di verità

I componenti in `components/ui/` sono l'**unica** base di stile. Le schermate li compongono, non li duplicano.
- Varianti via `class-variance-authority` (cva).
- Merge classi con `tailwind-merge` (`cn()`), mai concatenazione manuale conflittuale.
- Icone: `lucide`. Toast: `sonner`. Tema dark: `next-themes` + CSS variables (non colori hard-coded).
- Prima di creare un componente, verifica se esiste già in `components/ui/`. Qualsiasi elemento usato in 2+ schermate → estrai con props/slot.

## 1-bis. Scala di superfici (vincolante)

Ogni superficie viene da un token di `index.css`, mai da un colore hard-coded o da un grigio Tailwind (`bg-slate-100`, `bg-white`, `#fff`).

**Superficie contenitore** (scala monotona, si sale di un rung per volta):

| Rung | Token | Uso | L light → dark |
|---|---|---|---|
| 1 | `bg-background` | pagina/body e contenitori a piena pagina (incluso `SheetContent`, `DialogContent`: mini-pagine che ospitano card, separate dallo scrim) | 81 → 4 |
| 2 | `bg-surface` | superficie intermedia: pannello contenitore che ospita card (es. work panel), toolbar, header di dialog | 90 → 16 |
| 3 | `bg-card` / `bg-popover` | componente in rilievo (card, dropdown, tooltip) | 100 → 23 |
| 4 | `border-border` / `border-input` / `border-field-border` | hairline, percettibile su tutte e tre le superfici | 73 → 38 |

**L'ampiezza del gradino si misura in rapporto di contrasto, non in punti di lightness.** Due superfici ampie adiacenti sotto ~1.2:1 non si distinguono; **soglia vincolante: ogni coppia adiacente della scala ≥ 1.25:1** (`background`/`surface` e `surface`/`card`). I punti di lightness ingannano ai due estremi: in light 91→95→100 valeva 1.10/1.12, in dark 8→11→14 valeva 1.07/1.08 — cioè tre piani indistinguibili. I valori attuali misurano **1.26 / 1.27** in light e **1.29 / 1.26** in dark. Chi tocca la scala ricalcola i rapporti, non guarda a occhio.

**Tinta** (`bg-muted`, `bg-accent` e le loro diluizioni `bg-muted/40`): NON è un rung. È un velo applicato *sopra* la superficie che la ospita — hover/zebra di riga, hover del `Button` `outline`, skeleton, blocchi sfumati dentro una card — e sta **oltre l'estremo** della scala, mai in mezzo a due rung (light `--muted` 79 e `--accent` 76, sotto il body; dark `--muted` 31, sopra la card). Con body e card distanti solo 1.61:1 in light, una tinta parcheggiata "in mezzo" coinciderebbe con `--surface`: per questo è un token separato, e per questo si sposta insieme alla scala. Le griglie AG Grid rimescolano `--border` e `--muted` verso la propria superficie (`color-mix`, vedi `data-table-theme.ts`): reticolo e hover restano derivati dal token, mai hard-coded.

Regole: **un componente non può avere la stessa superficie del contenitore su cui poggia** (card su card, pannello `bg-background` dentro una pagina `bg-background`): sali di un rung. `bg-muted` non si usa come superficie di un contenitore a piena area. Un token hairline (`bg-border`) non si usa come riempimento di zona. Se una superficie sembra invisibile, si corregge la scala in `index.css`, non con una patch locale sulla schermata. Ogni cambio di superficie richiede la riverifica del contrasto testo (AA 4.5:1 normale, 3:1 large/UI): la scala attuale ha costretto `--foreground` a 26 e `--muted-foreground` a 34 in light (73 in dark) per tenere AA sulla tinta.

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
