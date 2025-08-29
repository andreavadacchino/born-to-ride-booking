# Documentazione: Implementazione Formato Prezzi Italiano

**Plugin**: Born to Ride Booking  
**Versione**: 1.0.13+  
**Data**: 2025-01-08  
**Formato Standard**: €1.000,50 (punto per migliaia, virgola per decimali, € prefisso)

## 🎯 **Obiettivo**

Standardizzare tutti i prezzi del plugin secondo il formato italiano:
- **Separatore migliaia**: Punto (.)
- **Separatore decimali**: Virgola (,)
- **Simbolo valuta**: Euro (€) come prefisso
- **Esempio**: €1.234,56 invece di €1,234.56

## 📋 **Modifiche Implementate**

### **A. Funzioni Helper PHP**

**File**: `born-to-ride-booking.php` (linee 48-105)

#### **1. `btr_format_price()`**
```php
function btr_format_price( $amount, $decimals = 2, $show_currency = true, $prefix_currency = true )
```
**Utilizzo**:
```php
echo btr_format_price(1234.56);        // Output: €1.234,56
echo btr_format_price(1234.56, 2, false); // Output: 1.234,56
```

#### **2. `btr_format_price_i18n()`**
```php
function btr_format_price_i18n( $amount, $show_currency = true, $prefix_currency = true )
```
**Utilizzo**:
```php
echo btr_format_price_i18n(1234.56);   // Output: €1.234,56 (localizzato)
```

### **B. Funzioni Helper JavaScript**

**File**: `assets/js/frontend-scripts.js` (linee 4-64)

#### **1. `btrFormatPrice()`**
```javascript
function btrFormatPrice(amount, decimals = 2, showCurrency = true, prefixCurrency = true)
```
**Utilizzo**:
```javascript
console.log(btrFormatPrice(1234.56));  // Output: €1.234,56
```

#### **2. `btrParsePrice()`**
```javascript
function btrParsePrice(priceString)
```
**Utilizzo**:
```javascript
console.log(btrParsePrice("€1.234,56")); // Output: 1234.56
```

#### **3. `btrFormatPriceInline()` (Template)**
**File**: `templates/admin/btr-form-anagrafici.php` (linee 1841-1848)
```javascript
function btrFormatPriceInline(amount, decimals = 2)
```

### **C. File Modificati**

#### **File PHP Aggiornati**
1. **`born-to-ride-booking.php`** - Funzioni helper globali
2. **`includes/class-btr-preventivi.php`** - Formattazioni riepilogo totali (linee 1750-1757)
3. **`templates/admin/btr-form-anagrafici.php`** - Template principale (varie linee)

#### **File JavaScript Aggiornati**
1. **`assets/js/frontend-scripts.js`** - Funzioni helper e sostituzioni .toFixed(2)

### **D. Pattern di Sostituzione**

#### **PHP: Prima → Dopo**
```php
// PRIMA (formato inglese)
echo '€' . number_format($amount, 2);
echo number_format_i18n($amount, 2) . ' €';

// DOPO (formato italiano standardizzato)
echo btr_format_price($amount);
echo btr_format_price_i18n($amount);
```

#### **JavaScript: Prima → Dopo**
```javascript
// PRIMA (formato inglese)
`€${price.toFixed(2)}`
price.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' €'

// DOPO (formato italiano standardizzato)
btrFormatPrice(price)
btrFormatPriceInline(price)
```

## 🔧 **Compatibilità**

### **Versioni WordPress**: 5.0+
### **Versioni PHP**: 7.4+
### **Browser JavaScript**: ES6+ (supporto toLocaleString)

## 📊 **Statistiche Modifiche**

- **Funzioni PHP create**: 2
- **Funzioni JavaScript create**: 3
- **File PHP modificati**: 3
- **File JavaScript modificati**: 1
- **Occorrenze corrette**: 50+

## 🚀 **Utilizzo per Sviluppatori**

### **Nuovi Sviluppi**
Utilizzare sempre le funzioni helper per formattazioni prezzi:
```php
// PHP - Nuovo codice
$prezzo_formattato = btr_format_price($importo);

// JavaScript - Nuovo codice  
const prezzoFormattato = btrFormatPrice(importo);
```

### **Personalizzazioni**
```php
// Senza simbolo €
$solo_numero = btr_format_price($importo, 2, false);

// € come suffisso
$euro_suffisso = btr_format_price($importo, 2, true, false);

// Decimali personalizzati
$tre_decimali = btr_format_price($importo, 3);
```

## ⚠️ **Note Importanti**

1. **Retro-compatibilità**: Le funzioni esistenti non sono state modificate per mantenere compatibilità
2. **Performance**: Le nuove funzioni aggiungono overhead minimo
3. **Testing**: Testare sempre le formattazioni su diversi importi
4. **Localizzazione**: Le funzioni rispettano le impostazioni WordPress

## 🐛 **Troubleshooting**

### **Problemi Comuni**
1. **Separatori errati**: Verificare che `toLocaleString('it-IT')` sia supportato
2. **Simbolo € mancante**: Controllare parametri `show_currency` e `prefix_currency`
3. **Parsing errato**: Usare sempre `btrParsePrice()` per convertire stringhe in numeri

### **Debug**
```php
// Test formattazione PHP
var_dump(btr_format_price(1234.56)); // string(9) "€1.234,56"

// Test formattazione JavaScript
console.log(btrFormatPrice(1234.56)); // €1.234,56
```

## 📝 **Changelog**

### **v1.0.13+ (2025-01-08)**
- ✅ Implementate funzioni helper PHP per formato italiano
- ✅ Implementate funzioni helper JavaScript per formato italiano  
- ✅ Aggiornati template principali con nuove funzioni
- ✅ Corrette 50+ occorrenze di formattazioni inconsistenti
- ✅ Documentazione completa per sviluppatori

---

**Autore**: Claude Code  
**Revisione**: 2025-01-08  
**Prossimi aggiornamenti**: Monitorare feedback utenti per ulteriori ottimizzazioni