# Born to Ride - Unified Design System

## Panoramica

Il **Unified Design System** è un framework CSS completo basato sullo stile del riepilogo preventivo, progettato per fornire un'esperienza utente coerente e professionale in tutta l'applicazione Born to Ride.

## Caratteristiche Principali

### 1. Design Pulito e Professionale
- Layout basato su card bianche con ombre sottili
- Spaziatura consistente e gerarchie visive chiare
- Icone SVG blu per identificare le sezioni
- Badge e etichette colorate per stati e informazioni

### 2. Sistema di Colori
```css
--btr-primary: #0097c5;        /* Blu principale */
--btr-success: #10b981;        /* Verde successo */
--btr-warning: #f59e0b;        /* Giallo avviso */
--btr-danger: #ef4444;         /* Rosso errore */
--btr-gray-[50-900];           /* Scala di grigi */
```

### 3. Componenti Disponibili

#### Cards e Sezioni
```html
<div class="btr-section-card">
    <div class="btr-section-header">
        <svg class="btr-section-icon">...</svg>
        <h2 class="btr-section-title">Titolo Sezione</h2>
        <span class="btr-section-badge">Badge</span>
    </div>
    <div class="btr-section-content">
        <!-- Contenuto -->
    </div>
</div>
```

#### Buttons
```html
<button class="btr-btn btr-btn-primary">Primary</button>
<button class="btr-btn btr-btn-secondary">Secondary</button>
<button class="btr-btn btr-btn-success">Success</button>
<button class="btr-btn btr-btn-danger">Danger</button>
```

#### Tabelle
```html
<table class="btr-data-table">
    <thead>
        <tr>
            <th>Colonna 1</th>
            <th>Colonna 2</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Dato 1</td>
            <td>Dato 2</td>
        </tr>
    </tbody>
</table>
```

#### Forms
```html
<div class="btr-form-group">
    <label class="btr-form-label">Label</label>
    <input type="text" class="btr-form-control">
</div>
```

### 4. Animazioni

Il sistema include diverse animazioni fluide:

- `btr-fade-in` - Dissolvenza in entrata
- `btr-fade-in-up` - Dissolvenza dal basso
- `btr-slide-in` - Scivolamento
- `btr-scale-in` - Ingrandimento
- `btr-pulse` - Pulsazione
- `btr-bounce` - Rimbalzo

#### Utilizzo Animazioni
```html
<div class="btr-section-card btr-fade-in-up">
    <!-- Contenuto animato -->
</div>
```

### 5. Effetti Hover

- `btr-hover-lift` - Solleva l'elemento all'hover
- `btr-hover-glow` - Aggiunge un bagliore
- `btr-hover-scale` - Ingrandisce leggermente

### 6. Utility Classes

#### Spacing
- `btr-mt-[1-5]` - Margin top
- `btr-mb-[1-5]` - Margin bottom
- `btr-p-[1-5]` - Padding

#### Flexbox
- `btr-flex` - Display flex
- `btr-items-center` - Align items center
- `btr-justify-between` - Justify content between

#### Text
- `btr-text-center` - Text align center
- `btr-text-primary` - Colore primario
- `btr-text-muted` - Testo attenuato

### 7. Responsive Design

Il sistema è completamente responsive con breakpoint a 768px:

```css
@media (max-width: 768px) {
    /* Stili mobile */
}
```

## Implementazione

### 1. Attivazione
Visita `/set-riepilogo-as-default.php` per attivare lo stile come predefinito.

### 2. Inclusione CSS
```html
<link rel="stylesheet" href="/wp-content/plugins/born-to-ride-booking/assets/css/btr-unified-design-system.css">
```

### 3. Struttura HTML Base
```html
<div class="btr-app btr-riepilogo-container">
    <!-- Il tuo contenuto qui -->
</div>
```

## Best Practices

1. **Usa sempre il prefisso `btr-`** per evitare conflitti con altri CSS
2. **Combina le utility classes** per ottenere il layout desiderato
3. **Usa le animazioni con parsimonia** per non sovraccaricare l'esperienza
4. **Mantieni la coerenza** utilizzando i componenti predefiniti
5. **Testa sempre su mobile** per garantire la responsività

## Esempi di Implementazione

### Card con Info Grid
```html
<div class="btr-section-card btr-fade-in-up">
    <div class="btr-section-header">
        <svg class="btr-section-icon">...</svg>
        <h2 class="btr-section-title">Dettagli Viaggio</h2>
    </div>
    <div class="btr-info-grid">
        <div class="btr-info-item">
            <span class="btr-info-label">Date</span>
            <span class="btr-info-value">10-17 Agosto 2025</span>
        </div>
        <div class="btr-info-item">
            <span class="btr-info-label">Partecipanti</span>
            <span class="btr-info-value">2 Adulti + 1 Bambino</span>
        </div>
    </div>
</div>
```

### Form con Validazione
```html
<form class="btr-form">
    <div class="btr-form-group">
        <label class="btr-form-label">Email</label>
        <input type="email" class="btr-form-control" required>
    </div>
    <button type="submit" class="btr-btn btr-btn-primary btr-hover-lift">
        Invia
    </button>
</form>
```

## Manutenzione

Per aggiornare o estendere il design system:

1. Modifica `/assets/css/btr-unified-design-system.css`
2. Mantieni la struttura delle sezioni esistenti
3. Aggiungi nuove variabili CSS in `:root`
4. Documenta nuovi componenti in questo file
5. Testa su tutti i dispositivi prima del rilascio

## Supporto

Per domande o problemi con il design system, contatta il team di sviluppo.