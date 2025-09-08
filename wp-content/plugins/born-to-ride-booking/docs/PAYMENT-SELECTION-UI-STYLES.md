# Payment Selection UI Styles Documentation

## Overview
La pagina di selezione metodi di pagamento ora supporta due stili CSS configurabili:
- **Modern**: Design vivace con animazioni dinamiche (default)
- **Minimal**: Design pulito con animazioni soft e sottili

## Come Cambiare Stile

### Metodo 1: Script di Attivazione (Raccomandato)
1. Accedi come amministratore
2. Visita: `/activate-minimal-css.php`
3. Seleziona lo stile desiderato
4. Clicca sul pulsante di attivazione

### Metodo 2: Database Direct
```sql
-- Attiva stile minimale
UPDATE wp_options SET option_value = 'minimal' WHERE option_name = 'btr_payment_selection_css_style';

-- Torna allo stile moderno
UPDATE wp_options SET option_value = 'modern' WHERE option_name = 'btr_payment_selection_css_style';
```

## Caratteristiche degli Stili

### Stile Moderno (Default)
- **File CSS**: `payment-selection-modern.css`
- **File JS**: `payment-selection-modern.js`
- **Caratteristiche**:
  - Animazioni vivaci (pulse, slideIn)
  - Colori brillanti e gradienti
  - Ombre pronunciate
  - Icone emoji integrate
  - Effetti hover elaborati

### Stile Minimale
- **File CSS**: `payment-selection-minimal.css`
- **File JS**: `payment-selection-minimal.js`
- **Caratteristiche**:
  - Animazioni soft e fluide
  - Palette colori ridotta e raffinata
  - Ombre sottili per profondità
  - Focus su leggibilità e chiarezza
  - Performance ottimizzate
  - Supporto dark mode
  - Accessibilità migliorata

## Animazioni Soft nel Design Minimale

### CSS Animations
```css
/* Fade In - Entrata morbida */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Slide Down - Apertura fluida */
@keyframes slideDown {
  from { 
    opacity: 0; 
    max-height: 0;
    transform: translateY(-10px);
  }
  to { 
    opacity: 1; 
    max-height: 1000px;
    transform: translateY(0);
  }
}

/* Soft Pulse - Attenzione sottile */
@keyframes softPulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(0, 151, 197, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(0, 151, 197, 0);
  }
}
```

### JavaScript Enhancements
- Transizioni fluide tra stati
- Animazioni di feedback per interazioni utente
- Smooth scroll per navigazione
- Loading states con spinner minimali
- Success animations prima del redirect

## Variabili CSS Personalizzabili

### Colori Primari
```css
--btr-primary: #0097c5;
--btr-primary-soft: #4db8d9;
--btr-primary-pale: #e8f4f9;
--btr-primary-hover: #007ba3;
```

### Spaziatura
```css
--btr-space-xs: 0.5rem;
--btr-space-sm: 1rem;
--btr-space-md: 1.5rem;
--btr-space-lg: 2rem;
--btr-space-xl: 3rem;
```

### Transizioni
```css
--btr-transition-fast: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
--btr-transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
--btr-transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
```

## Accessibilità

Il design minimale include miglioramenti per l'accessibilità:
- ARIA attributes completi
- Keyboard navigation (Tab, Enter, Space, Arrow keys)
- Focus visible states
- Screen reader support
- High contrast mode support
- Reduced motion preferences

## Performance

Lo stile minimale è ottimizzato per:
- Caricamento più veloce (CSS più leggero)
- Animazioni GPU-accelerate
- Resource hints (preload/prefetch)
- Lazy loading delle configurazioni

## Responsive Design

Entrambi gli stili sono completamente responsive con breakpoints a:
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

## Dark Mode

Lo stile minimale include supporto automatico per dark mode:
```css
@media (prefers-color-scheme: dark) {
  /* Colori invertiti automaticamente */
}
```

## Estendere gli Stili

Per personalizzare ulteriormente:
1. Crea un child theme
2. Aggiungi CSS custom che sovrascrive le variabili
3. Esempio:
```css
.btr-payment-selection-page {
  --btr-primary: #your-color;
  --btr-space-md: 2rem;
}
```

## Troubleshooting

### Lo stile non cambia
1. Svuota la cache del browser
2. Svuota la cache di WordPress
3. Verifica che l'opzione sia salvata nel database

### Animazioni non fluide
1. Verifica che JavaScript sia caricato
2. Controlla la console per errori
3. Disabilita altri plugin che potrebbero interferire

### Dark mode non funziona
Il dark mode richiede un browser moderno che supporti `prefers-color-scheme`

---

**Versione**: 1.0.102 | **Data**: Gennaio 2025