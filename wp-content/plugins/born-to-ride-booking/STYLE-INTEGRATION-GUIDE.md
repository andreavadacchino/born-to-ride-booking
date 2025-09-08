# 🎨 Guida Integrazione Stili - Sistema di Prenotazione Born to Ride

## 📋 Overview

Questa guida descrive come integrare correttamente gli stili nel sistema di prenotazione Born to Ride, mantenendo coerenza visiva e riutilizzando i componenti esistenti.

---

## 🎯 Design System Esistente

### Variabili CSS Globali
```css
/* File: assets/css/btr-global-variables.css */
:root {
  /* Colori Primari */
  --btr-primary: #0097c5;
  --btr-primary-dark: #0087b3;
  --btr-primary-light: #e3f5fb;
  
  /* Colori Secondari */
  --btr-success: #28a745;
  --btr-warning: #ff6b6b;
  --btr-danger: #dc3545;
  --btr-info: #17a2b8;
  
  /* Neutri */
  --btr-gray-100: #f8f9fa;
  --btr-gray-200: #e9ecef;
  --btr-gray-300: #dee2e6;
  --btr-gray-400: #ced4da;
  --btr-gray-500: #adb5bd;
  --btr-gray-600: #6c757d;
  --btr-gray-700: #495057;
  --btr-gray-800: #343a40;
  --btr-gray-900: #212529;
  
  /* Spacing */
  --btr-spacing-xs: 0.25rem;
  --btr-spacing-sm: 0.5rem;
  --btr-spacing-md: 1rem;
  --btr-spacing-lg: 1.5rem;
  --btr-spacing-xl: 3rem;
  
  /* Typography */
  --btr-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --btr-font-size-base: 16px;
  --btr-line-height-base: 1.5;
  
  /* Borders & Radius */
  --btr-border-radius: 8px;
  --btr-border-radius-sm: 4px;
  --btr-border-radius-lg: 12px;
  --btr-border-color: #e0e0e0;
  
  /* Shadows */
  --btr-shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
  --btr-shadow: 0 2px 10px rgba(0,0,0,0.1);
  --btr-shadow-lg: 0 4px 20px rgba(0,0,0,0.15);
  
  /* Transitions */
  --btr-transition: all 0.3s ease;
}
```

### Componenti CSS Riutilizzabili

#### 1. Card Component
```css
/* Pattern standard per card */
.btr-card {
  background: white;
  border-radius: var(--btr-border-radius);
  padding: var(--btr-spacing-lg);
  box-shadow: var(--btr-shadow);
  border: 1px solid var(--btr-border-color);
  transition: var(--btr-transition);
}

.btr-card:hover {
  box-shadow: var(--btr-shadow-lg);
  transform: translateY(-2px);
}

.btr-card-header {
  border-bottom: 2px solid var(--btr-primary);
  padding-bottom: var(--btr-spacing-md);
  margin-bottom: var(--btr-spacing-lg);
}

.btr-card-title {
  font-size: 1.5rem;
  color: var(--btr-gray-900);
  font-weight: 600;
}
```

#### 2. Button Styles
```css
/* Bottoni standard */
.btr-btn {
  padding: 12px 24px;
  border-radius: var(--btr-border-radius-sm);
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: var(--btr-transition);
  text-decoration: none;
  display: inline-block;
  font-size: var(--btr-font-size-base);
}

.btr-btn-primary {
  background: var(--btr-primary);
  color: white;
}

.btr-btn-primary:hover {
  background: var(--btr-primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 151, 197, 0.3);
}

.btr-btn-secondary {
  background: var(--btr-gray-600);
  color: white;
}

.btr-btn-success {
  background: var(--btr-success);
  color: white;
}

.btr-btn-lg {
  padding: 16px 32px;
  font-size: 1.125rem;
}

.btr-btn-block {
  display: block;
  width: 100%;
}
```

#### 3. Form Elements
```css
/* Input standard */
.btr-form-control {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid var(--btr-gray-400);
  border-radius: var(--btr-border-radius-sm);
  font-size: var(--btr-font-size-base);
  transition: var(--btr-transition);
}

.btr-form-control:focus {
  outline: none;
  border-color: var(--btr-primary);
  box-shadow: 0 0 0 3px rgba(0, 151, 197, 0.1);
}

.btr-form-label {
  display: block;
  margin-bottom: var(--btr-spacing-sm);
  font-weight: 500;
  color: var(--btr-gray-700);
}

.btr-form-group {
  margin-bottom: var(--btr-spacing-lg);
}
```

#### 4. Progress Indicators
```css
/* Progress steps utilizzati nel sistema */
.btr-progress-steps {
  display: flex;
  justify-content: space-between;
  list-style: none;
  padding: 0;
  margin: 0 0 var(--btr-spacing-xl);
}

.btr-progress-step {
  flex: 1;
  text-align: center;
  position: relative;
}

.btr-progress-step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--btr-gray-300);
  color: white;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  margin-bottom: var(--btr-spacing-sm);
}

.btr-progress-step.active .btr-progress-step-number {
  background: var(--btr-primary);
}

.btr-progress-step.completed .btr-progress-step-number {
  background: var(--btr-success);
}
```

---

## 🔄 Integrazione Stili Payment Selection

### Step 1: Unificare le Variabili CSS
```css
/* payment-selection-unified.css */
/* Importa variabili globali */
@import 'btr-global-variables.css';

/* Override solo dove necessario */
.btr-payment-selection-page {
  /* Usa variabili esistenti invece di ridefinirle */
  background: var(--btr-gray-100);
  font-family: var(--btr-font-family);
}
```

### Step 2: Adattare i Componenti
```css
/* Converti componenti custom in pattern standard */

/* PRIMA (custom) */
.btr-payment-option {
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 25px;
}

/* DOPO (standard) */
.btr-payment-option {
  @extend .btr-card; /* Se usi SASS */
  /* O applica classi direttamente nel markup */
}
```

### Step 3: Mantenere Consistenza Icons
```html
<!-- Usa iconografia consistente -->
<!-- Sistema esistente usa Font Awesome -->
<i class="fas fa-credit-card"></i> <!-- Pagamento completo -->
<i class="fas fa-percentage"></i> <!-- Caparra -->
<i class="fas fa-users"></i> <!-- Gruppo -->
```

---

## 📦 Struttura File CSS Consigliata

```
assets/css/
├── btr-global-variables.css      # Variabili globali
├── btr-base.css                   # Reset e typography base
├── btr-components.css             # Componenti riutilizzabili
├── btr-utilities.css              # Classi utility
├── modules/
│   ├── payment-selection.css     # Stili specifici payment
│   ├── checkout-summary.css      # Stili checkout
│   ├── anagrafici-form.css       # Stili form anagrafici
│   └── admin-dashboard.css       # Stili admin
└── btr-main.css                   # Import di tutti i file
```

---

## 🎯 Best Practices

### 1. Naming Convention
```css
/* Usa prefisso btr- per evitare conflitti */
.btr-component-name { }
.btr-component-name__element { }
.btr-component-name--modifier { }
```

### 2. Responsive Design
```css
/* Mobile First approach */
.btr-component {
  /* Mobile styles */
}

@media (min-width: 768px) {
  .btr-component {
    /* Tablet styles */
  }
}

@media (min-width: 1024px) {
  .btr-component {
    /* Desktop styles */
  }
}
```

### 3. Accessibilità
```css
/* Focus states chiari */
.btr-interactive-element:focus {
  outline: 2px solid var(--btr-primary);
  outline-offset: 2px;
}

/* Contrasti adeguati */
.btr-text-on-primary {
  color: white; /* Contrasto >4.5:1 con sfondo primary */
}
```

### 4. Performance
```css
/* Usa transform invece di position per animazioni */
.btr-animated {
  transition: transform 0.3s ease;
}

.btr-animated:hover {
  transform: translateY(-2px);
}
```

---

## 🔧 Implementazione nel Payment System

### 1. Update payment-selection-page.php
```php
<!-- Usa classi standard invece di custom -->
<div class="btr-payment-selection-page">
  <div class="btr-container">
    
    <!-- Progress indicator con stili standard -->
    <ul class="btr-progress-steps">
      <li class="btr-progress-step completed">
        <span class="btr-progress-step-number">1</span>
        <span class="btr-progress-step-label">Dati Anagrafici</span>
      </li>
      <!-- ... -->
    </ul>
    
    <!-- Card per opzioni pagamento -->
    <div class="btr-card">
      <div class="btr-card-header">
        <h2 class="btr-card-title">Seleziona metodo di pagamento</h2>
      </div>
      
      <div class="btr-payment-options">
        <!-- Opzioni con stili consistenti -->
      </div>
    </div>
    
  </div>
</div>
```

### 2. JavaScript Integration
```javascript
// Usa classi CSS per stati invece di inline styles
element.classList.add('btr-is-loading');
element.classList.remove('btr-is-loading');

// Animazioni via CSS, non JS
element.classList.add('btr-fade-in');
```

---

## 📱 Mobile Optimization

### Touch-Friendly Interfaces
```css
/* Minimum 44px touch targets */
.btr-touch-target {
  min-height: 44px;
  min-width: 44px;
}

/* Spacing per mobile */
.btr-mobile-spacing {
  padding: var(--btr-spacing-md);
}

@media (min-width: 768px) {
  .btr-mobile-spacing {
    padding: var(--btr-spacing-lg);
  }
}
```

---

## 🚀 Migration Checklist

- [ ] Audit stili esistenti nel sistema
- [ ] Identificare componenti comuni
- [ ] Creare/aggiornare file variabili globali
- [ ] Convertire stili custom in pattern standard
- [ ] Testare su tutti i breakpoint
- [ ] Validare accessibilità
- [ ] Ottimizzare performance
- [ ] Documentare nuovi componenti
- [ ] Update style guide

---

## 📚 Risorse

- [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- [WooCommerce Style Guide](https://woocommerce.github.io/woocommerce-admin/#/components)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [CSS Performance Best Practices](https://web.dev/css-performance/)