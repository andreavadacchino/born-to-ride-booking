# Logica Notti Extra - Implementazione Robusta

## Problema Originale
Il sistema usava un fallback statico di 2 notti quando il backend non forniva il numero di notti extra, causando:
- Totale errato: €914,21 invece di €894,21
- Supplemento extra calcolato per 2 notti invece di 1: €40 invece di €20

## Soluzione Implementata

### 1. Gestione Risposta AJAX (righe 1391-1420)
```javascript
// Prima verifica se le notti extra sono attive
const extraNightsActive = response.data.extra_night === true || response.data.has_extra_nights === true;

if (extraNightsActive) {
    if (typeof response.data.extra_nights_count !== 'undefined') {
        window.btrExtraNightsCount = parseInt(response.data.extra_nights_count, 10) || 0;
    } else {
        // ERRORE CRITICO - non usiamo fallback
        window.btrExtraNightsCount = undefined;
        console.error('[BTR] ❌ ERRORE CRITICO: Notti extra attive ma numero non fornito!');
    }
} else {
    window.btrExtraNightsCount = 0; // Non attive
}
```

### 2. Calcolo Supplemento (righe 2239-2249)
```javascript
if (extraNightFlag && extraNightPP > 0) {
    if (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) {
        extraNightDays = window.btrExtraNightsCount;
    } else if (window.btrExtraNightsCount === undefined) {
        // Non calcolare il supplemento se non sappiamo quante notti
        console.error('[BTR CALC] ❌ ERRORE: Impossibile calcolare supplemento');
        extraNightDays = 0;
    }
}
```

### 3. Visualizzazione Riepilogo
Per la visualizzazione manteniamo un default di 1 solo per non mostrare "undefined" all'utente, ma questo non influenza i calcoli.

## Comportamenti per Scenario

| Scenario | Backend Response | window.btrExtraNightsCount | Supplemento Calcolato |
|----------|-----------------|---------------------------|---------------------|
| Notti extra attive (1 notte) | `extra_night: true, extra_nights_count: 1` | 1 | €40 (4×€10×1) |
| Notti extra attive (3 notti) | `extra_night: true, extra_nights_count: 3` | 3 | €120 (4×€10×3) |
| Notti extra attive, numero mancante | `extra_night: true, extra_nights_count: undefined` | undefined | €0 + errore log |
| Notti extra non attive | `extra_night: false` | 0 | €0 |

## Vantaggi

1. **Nessun fallback arbitrario**: Evita calcoli errati basati su assunzioni
2. **Errori tracciabili**: Log chiari quando il backend non fornisce dati completi
3. **Protezione totali**: Meglio non calcolare che calcolare male
4. **Flessibilità**: Supporta qualsiasi numero di notti extra

## Testing

Usa il file `tests/test-extra-nights-logic.php` per verificare tutti gli scenari.

## Note per il Backend

Il backend DEVE sempre fornire `extra_nights_count` quando `extra_night` è true, altrimenti il sistema non può calcolare correttamente il supplemento.