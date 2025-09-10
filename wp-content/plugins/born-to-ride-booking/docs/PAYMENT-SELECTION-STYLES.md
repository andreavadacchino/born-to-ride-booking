# Payment Selection Page - Sistema di Stili

## Panoramica

La pagina di selezione del metodo di pagamento supporta tre stili CSS configurabili per adattarsi a diverse esigenze di design e branding.

## Stili Disponibili

### 1. Modern (Default)
- **Caratteristiche**: Design originale con emoji, animazioni vivaci, colori brillanti
- **File CSS**: `payment-selection-modern.css`
- **File JS**: `payment-selection-modern.js`
- **Template**: `payment-selection-page.php`
- **Quando usarlo**: Per un'esperienza utente moderna e coinvolgente

### 2. Minimal
- **Caratteristiche**: Design pulito, animazioni soft, focus sulla leggibilità
- **File CSS**: `payment-selection-minimal.css`
- **File JS**: `payment-selection-minimal.js`
- **Template**: `payment-selection-page.php`
- **Quando usarlo**: Per un approccio più professionale e sobrio

### 3. Unified
- **Caratteristiche**: Design integrato con il flusso di prenotazione Born to Ride
- **File CSS**: `payment-selection-unified.css`
- **File JS**: `payment-selection-minimal.js` (riutilizzato)
- **Template**: `payment-selection-page-unified.php`
- **Quando usarlo**: Per mantenere coerenza visiva con il resto del sistema

## Design System Unified

### Colori
```css
--btr-primary: #0097c5;
--btr-primary-dark: #0073aa;
--btr-primary-light: #f0f8ff;
--btr-success: #28a745;
--btr-warning: #f0ad4e;
--btr-danger: #dc3545;
```

### Misure Standard
- Border radius: 10px
- Transizioni: 0.3s ease
- Spacing: 5px, 10px, 20px, 30px, 40px
- Font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto

### Icone SVG
Lo stile unified utilizza icone SVG inline invece di emoji:
- Pagamento completo: Icona carta di credito
- Caparra + Saldo: Checkmark circolare
- Pagamento di gruppo: Icona gruppo persone
- Info/Warning: Icone informative contestuali

## Come Cambiare Stile

### Via Script PHP
```bash
# Attiva stile unified
php activate-unified-css.php

# Attiva stile minimal
php activate-minimal-css.php

# Torna allo stile modern
php activate-modern-css.php
```

### Programmaticamente
```php
// Imposta lo stile desiderato
update_option('btr_payment_selection_css_style', 'unified'); // o 'minimal' o 'modern'

// Pulisci la cache
wp_cache_flush();
```

## Struttura File

```
wp-content/plugins/born-to-ride-booking/
├── assets/
│   ├── css/
│   │   ├── payment-selection-modern.css
│   │   ├── payment-selection-minimal.css
│   │   └── payment-selection-unified.css
│   └── js/
│       ├── payment-selection-modern.js
│       └── payment-selection-minimal.js
├── templates/
│   ├── payment-selection-page.php
│   └── payment-selection-page-unified.php
└── includes/
    └── class-btr-payment-selection-shortcode.php
```

## Personalizzazione

### Modificare i Colori
Tutti gli stili utilizzano CSS variables che possono essere sovrascritte:

```css
.btr-payment-selection-page {
  --btr-primary: #your-color;
  --btr-border-radius: 8px;
}
```

### Aggiungere un Nuovo Stile
1. Crea un nuovo file CSS in `assets/css/`
2. (Opzionale) Crea un nuovo file JS in `assets/js/`
3. (Opzionale) Crea un nuovo template in `templates/`
4. Aggiorna il shortcode per riconoscere il nuovo stile

## Best Practices

1. **Coerenza**: Mantieni lo stesso stile in tutto il flusso di prenotazione
2. **Performance**: Gli stili sono ottimizzati con preload e resource hints
3. **Accessibilità**: Tutti gli stili seguono le linee guida WCAG 2.1
4. **Responsive**: Testati su dispositivi mobili e desktop
5. **Print**: Includono stili ottimizzati per la stampa

## Supporto

Per problemi o domande sugli stili della pagina di selezione pagamento, consultare il CHANGELOG.md o contattare il team di sviluppo.