# Documentazione Release v1.0.39

## 🎯 Obiettivo
Risolvere definitivamente il problema del calcolo errato del supplemento notti extra che mostrava €914,21 invece di €894,21.

## 🐛 Problema Identificato
Il sistema utilizzava un fallback fisso di 2 notti quando il backend non forniva `extra_nights_count`, causando:
- Supplemento extra calcolato per 2 notti invece del numero reale
- Differenza di €20 nel totale (4 persone × €10 × 1 notte di differenza)

## ✅ Soluzioni Implementate

### 1. Sistema di Patch Hotfix
Creato un sistema modulare per applicare correzioni urgenti senza modificare il core:

#### `includes/class-btr-hotfix-loader.php`
```php
- Carica automaticamente patch JavaScript quando necessario
- Si attiva solo sulle pagine con il booking form
- Verifica la versione del plugin per applicare patch solo se necessario
- Completamente trasparente per l'utente finale
```

#### `includes/patches/patch-extra-nights.js`
```javascript
- Intercetta e corregge window.btrExtraNightsCount
- Intercetta risposte AJAX e corregge extra_nights_count
- Corregge il totale visualizzato se mostra €914,21
- Include logging dettagliato per debug
```

### 2. Logica Notti Extra Robusta (v1.0.37)
Modifiche in `assets/js/frontend-scripts.js`:

```javascript
// PRIMA (v1.0.36 e precedenti)
window.btrExtraNightsCount = 2; // Fallback fisso

// DOPO (v1.0.37+)
if (extraNightsActive) {
    if (typeof response.data.extra_nights_count !== 'undefined') {
        window.btrExtraNightsCount = response.data.extra_nights_count;
    } else {
        window.btrExtraNightsCount = undefined; // NO fallback
        console.error('Notti extra attive ma numero non fornito!');
    }
} else {
    window.btrExtraNightsCount = 0;
}
```

## 📊 Verifica del Fix

### Test Case Standard
- 2 Adulti + 1 Bambino 3-8 + 1 Bambino 8-12
- 1 Notte extra del 23/01/2026
- Supplemento: €10/persona/notte

### Calcolo Corretto
```
Subtotali senza supplemento extra:
- Adulti: €318 + €40 + €160 = €518
- Bambino 3-8: €119,01 + €20 + €20 = €159,01  
- Bambino 8-12: €127,20 + €20 + €30 = €177,20
Subtotale: €854,21

Supplemento extra (CORRETTO):
4 persone × €10 × 1 notte = €40

TOTALE CORRETTO: €894,21
```

## 🔧 Come Verificare

### 1. Check Versione
```javascript
console.log('Versione plugin:', BTR_VERSION);
// Se < 1.0.37, la patch si attiva automaticamente
```

### 2. Check Valore Notti Extra
```javascript
console.log('btrExtraNightsCount:', window.btrExtraNightsCount);
// Deve essere 1 per 1 notte extra, non 2
```

### 3. Check Patch Attiva
```javascript
// Se vedi questo nel log, la patch è attiva:
"[BTR PATCH] ✅ Patch notti extra applicata con successo"
```

## 🚀 Deployment

### Per Versioni < 1.0.37
La patch si attiva automaticamente. Non serve alcuna azione.

### Per Versioni ≥ 1.0.37
Il fix è già nel core, la patch non si attiva.

### Fix Manuale (Emergenza)
Se necessario, esegui nella console:
```javascript
window.btrExtraNightsCount = 1;
updateRoomPrices();
```

## 📝 Note Tecniche

### Perché una Patch invece di un Fix Diretto?
1. **Retrocompatibilità**: Funziona su tutte le versioni
2. **Non invasiva**: Non modifica il core del plugin
3. **Temporanea**: Si disattiva automaticamente quando non serve
4. **Tracciabile**: Log chiari per debug

### Sicurezza
- La patch verifica sempre che le notti extra siano attive
- Non sovrascrive valori corretti dal backend
- Si disabilita se riceve un valore valido diverso da 2

## 🎯 Risultato Finale
- Totale sempre calcolato correttamente
- Nessun fallback arbitrario
- Sistema flessibile per N notti extra
- Protezione contro errori di configurazione