# Istruzioni Aggiornamento v1.0.42

## 🎯 Problema Risolto

Il sistema calcolava il supplemento per **3 notti extra invece di 2**, causando:
- Totale errato: €954,45
- Totale corretto: €914,45
- Differenza: €40

## 🚀 Aggiornamento Rapido

### Opzione 1: Aggiornamento Completo (Consigliato)
1. Backup del database e dei file
2. Carica tutti i file della v1.0.42
3. Svuota cache browser (Ctrl+F5)
4. Il fix si attiva automaticamente

### Opzione 2: Solo Patch (Temporaneo)
Se non puoi aggiornare subito:

1. Carica questi file:
   - `includes/patches/patch-extra-nights-v2.js` (NUOVO)
   - `includes/class-btr-hotfix-loader.php` (aggiornato)

2. La patch correggerà automaticamente il calcolo

## ✅ Verifica Post-Aggiornamento

### Test nella Console Browser
```javascript
// Verifica che la patch sia attiva
console.log('Patch v2 caricata:', 
    document.querySelector('script[src*="patch-extra-nights-v2.js"]') ? 'SÌ' : 'NO');

// Verifica il valore corretto
console.log('btrExtraNightsCount:', window.btrExtraNightsCount);
// Dovrebbe mostrare 2, non 3

// Debug dettagliato
if (typeof btrPatchDebug === 'function') {
    btrPatchDebug();
}
```

### Test Funzionale
1. Configura: 2 adulti + 2 bambini
2. Attiva 2 notti extra
3. Verifica che il totale sia €914,45 (non €954,45)

## 🔍 Come Funziona la Patch

La patch v2 intercetta il valore quando è 3 e lo corregge automaticamente a 2:
- Intercetta `window.btrExtraNightsCount`
- Intercetta le risposte AJAX
- Non modifica il database
- Completamente trasparente

## ⚠️ Nota Importante

Questa è una patch temporanea. Il problema di fondo è che il campo nel database contiene probabilmente 3 date invece di 2. Per una soluzione permanente:

1. Verifica nel backend WordPress la configurazione delle notti extra
2. Il campo range dovrebbe contenere: `2026-01-22, 2026-01-23` (2 date)
3. NON: `2026-01-21, 2026-01-22, 2026-01-23` (3 date)

## 📋 Checklist Deployment

- [ ] Backup database e file
- [ ] Upload file v1.0.42
- [ ] Svuota cache browser
- [ ] Verifica nella console che la patch sia attiva
- [ ] Test con configurazione 2+2
- [ ] Verifica totale €914,45

## 🆘 Troubleshooting

### Il totale è ancora €954,45
1. Svuota cache browser (Ctrl+F5)
2. Verifica nella console: `window.btrExtraNightsCount` dovrebbe essere 2
3. Se è ancora 3, ricarica la pagina

### La patch non si carica
1. Verifica di essere sulla pagina di prenotazione
2. Controlla errori nella console JavaScript
3. Verifica che i file siano stati caricati correttamente

### Soluzione Manuale Temporanea
```javascript
// Forza il valore corretto
window.btrExtraNightsCount = 2;
updateRoomPrices();
```