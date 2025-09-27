# Born to Ride Booking · Style Guide (v1.0)

Questa guida raccoglie i pattern visivi e i token di design introdotti nel restyling delle pagine di pagamento. L’obiettivo è garantire coerenza quando il team estende o aggiorna l’interfaccia del plugin.

## 1. Fondamenta

### Palette Colori

| Token | Hex | Uso principale |
| --- | --- | --- |
| `--btr-primary` | `#0A7BE4` | CTA principali, badge di stato positivi |
| `--btr-primary-dark` | `#005C99` | Hover CTA, testo enfatizzato su sfondo chiaro |
| `--btr-primary-light` | `#E3F2FF` | Sfondo hero, highlight delicati |
| `--btr-success` | `#22C55E` | Messaggi/indicatori success |
| `--btr-warning` | `#F59E0B` | Avvisi, label “da completare” |
| `--btr-danger` | `#EF4444` | Errori, convalide negative |
| `--btr-gray-900` | `#0F172A` | Titoli, testo principale |
| `--btr-gray-700` | `#334155` | Testo secondario |
| `--btr-gray-200` | `#E2E8F0` | Bordi, separatori |
| `--btr-background` | `linear-gradient(180deg, #F1F5F9 0%, #E2E8F0 100%)` | Sfondo macro delle pagine |

> Suggerimento: centralizzare questi token in un file SCSS/CSS (es. `:root`) per mantenere un’unica fonte di verità.

### Tipografia

- **Font principale**: `Archivo`, fallback `-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`.
- **Titoli H1/H2**: letter-spacing `-0.02em`, peso `600/700`.
- **Label & badge**: uppercase + tracking `0.05–0.08em` per CTA, pill, piccoli blocchi.
- **Body copy**: font-size base `16px`, line-height `1.6`.

### Spaziatura & Raggi

- **Card & sezioni**: border-radius `18–24px`, padding orizzontale `1.6–2.4rem`.
- **Pill / CTA**: border-radius `999px`.
- **Griglie responsive**: `grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))` per riepiloghi; `auto-fit, minmax(280px,1fr)` per liste di card.

## 2. Componenti Chiave

### 2.1 Hero Header (Pagamento di gruppo)

```html
<section class="btr-group-hero">
  <div class="btr-hero-content">
    <span class="btr-hero-badge">Pagamento di gruppo</span>
    <h1>Born to Ride Weekend</h1>
    <p class="btr-hero-dates">24 Gennaio 2026 · 2 giorni</p>
    <div class="btr-hero-meta">…</div>
  </div>
</section>
```

- Gradient sofisticato (`rgba(14,165,233,0.14) → white`) + overlay radiale.
- Badge con uppercase e background semi-trasparente.
- I meta item riutilizzano card bianche con `box-shadow` interno subtle (`inset 0 0 0 1px rgba(255,255,255,0.35)`).

### 2.2 Metriche / Summary Card

```html
<article class="btr-summary-card btr-summary-card--amount">
  <span class="btr-summary-label">Totale prenotazione</span>
  <span class="btr-summary-value">€649,40</span>
  <span class="btr-summary-subtext">4 partecipanti · 2 paganti</span>
</article>
```

- Bordi laterali colorati (primary/success/warning) per differenziare tipologie.
- Ombra morbida `0 12px 28px rgba(15, 23, 42, 0.06)`.
- Label uppercase con tracking `0.05em`.

### 2.3 Breakdown Lista

- `display: grid; gap: 0.75rem`.
- Divider `border-bottom: 1px solid rgba(226, 232, 240, 0.8)`.
- Allineamento `space-between` per label/valore.

### 2.4 CTA Primaria (Paga adesso)

Markup riutilizzabile progettato per ricordare `btn-primary-cta` admin:

```html
<button type="submit" class="btr-submit-button">
  <svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true">
    <path ... />
  </svg>
  Paga €649,40 adesso
</button>
```

Linee guida:
- **Gradient**: `linear-gradient(135deg, var(--btr-primary) → var(--btr-primary-dark))`.
- **Dimensioni**: padding verticale ≥ `1rem`; `display: inline-flex` per affiancare icone.
- **Ombra**: `0 18px 34px rgba(10,123,228,0.28)` con hover più pronunciato.
- **Testo**: uppercase + letter-spacing `0.05em`.

### 2.5 Pill / Badge

```html
<span class="btr-hero-badge">Pagamento di gruppo</span>
<span class="btr-trust-item">🔐 Standard PSD2</span>
```

- Border-radius `999px`, padding `0.35rem 0.85rem`.
- Palette: primary per badge, grigi per pill di contesto.

### 2.6 Carte informative / moduli

- Box bianchi con border subtle `rgba(226,232,240,0.9)` e shadow `0 20px 40px rgba(15,23,42,0.08)`.
- `btr-payment-method-option`: stato selezionato = border primary + shadow soft; hover = cambiare border e background (light).

### 2.7 Feedback form

- Component `.btr-form-feedback` per messaggi di successo/errore inline (colori success/danger).
- Loading spinner riutilizzabile (`border: 3px solid rgba(10,123,228,0.25)` etc.).

## 3. Token riutilizzabili (SASS suggeriti)

```scss
:root {
  --btr-font-sans: 'Archivo', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --btr-primary: #0A7BE4;
  --btr-primary-dark: #005C99;
  --btr-primary-light: #E3F2FF;
  --btr-success: #22C55E;
  --btr-warning: #F59E0B;
  --btr-danger: #EF4444;
  --btr-gray-900: #0F172A;
  --btr-gray-700: #334155;
  --btr-gray-200: #E2E8F0;
}

@mixin card-elevated {
  background: #fff;
  border-radius: 20px;
  border: 1px solid rgba(15,23,42,0.08);
  box-shadow: 0 20px 40px rgba(15,23,42,0.08);
}

@mixin pill($bg, $fg) {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  background: $bg;
  color: $fg;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.05em;
}
```

## 4. Pattern comportamentali

- **Griglie responsive**: usare `auto-fit` per evitare card troppo strette.<br>
- **Hover**: micro-traslazione `translateY(-2px)` e incremento della shadow.
- **Animazioni**: gradient hero + progress bar con `width` animata (transition `0.35s`).
- **Icone**: preferire SVG inline (`stroke="currentColor"`) per avere colori ereditati.

## 5. Consigli d’implementazione

1. **Centralizzare i token** in un file (es. `assets/css/_tokens.css`) e importarlo ovunque serva per avere consistenza.
2. **Componentizzare**: estrarre snippet ripetuti (hero, summary card, CTA) in partial PHP o blocchi Gutenberg personalizzati.
3. **Test contrasto**: verificare AA/AAA su CTA e badge (il blu scelto è compliant su sfondo bianco >4.5:1).
4. **Stati**: prevedere classi `.is-disabled`, `.is-loading`, `.is-selected` per CTA e card; utilizzare icone/spin per feedback.
5. **Documentazione in aggiornamento**: ogni nuova pagina dovrebbe referenziare questa guida e, se emergono nuove palette/componenti, aggiornarla.

---

> **Next step raccomandato:** creare un file SCSS condiviso con i mixin e i token descritti sopra, includerlo nel build del plugin e migrare gradualmente gli stili inline dei template verso classi centralizzate.
