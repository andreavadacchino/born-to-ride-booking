# 🔧 Fix jQuery Scope e Classe Selected - Form Anagrafici

## 📅 Data: 6 Settembre 2025
## 🔢 Versione: 1.0.233

## 🎯 Problemi Risolti

### 1. Errore: `updateCardErrorStatus is not defined`

**Problema**: La funzione `updateCardErrorStatus` era definita dentro un IIFE ma veniva chiamata da event handler esterni, causando l'errore `ReferenceError: updateCardErrorStatus is not defined`.

**Causa**: Problema di scope JavaScript - la funzione era inaccessibile dall'esterno dell'IIFE.

**Soluzione Applicata (righe 5076-5092)**:
```javascript
// FIX CRITICO v1.0.233: Funzioni globali per gestione errori schede
// DEVONO essere globali perché chiamate dai room-button event handlers
function updateCardErrorStatus($card) {
    // Se non ci sono più campi con errore nella scheda, rimuovi la classe btr-missing
    if (jQuery($card).find('.has-error').length === 0) {
        jQuery($card).removeClass('btr-missing');
        // Aggiorna anche il contatore delle persone mancanti se presente
        updateMissingCounter();
    }
}

function updateMissingCounter() {
    if (jQuery('.mancanti').length) {
        const missingCount = jQuery('.btr-person-card.btr-missing').length;
        jQuery('.mancanti').text(missingCount);
    }
}
```

### 2. Errore: `$ is not a function`

**Problema**: Le funzioni globali usavano `$` che non era disponibile nello scope globale.

**Causa**: `$` è disponibile solo dentro l'IIFE, non globalmente.

**Soluzione**: Sostituito tutti i `$` con `jQuery` nelle funzioni globali:
- Riga 5080: `jQuery($card).find('.has-error')`
- Riga 5081: `jQuery($card).removeClass('btr-missing')`
- Righe 5088-5090: Tutti i selettori jQuery

### 3. Bug: Classe `selected` non applicata ai pulsanti camera

**Problema**: Quando si cliccava su un pulsante camera, la classe `selected` non veniva applicata visivamente.

**Causa**: Il `.filter()` alla riga 4472 trovava i pulsanti corretti ma non applicava `.addClass('selected')`.

**Soluzione Applicata (riga 4471)**:
```javascript
// PRIMA (ERRATO):
$(this).addClass('selected'); // Applicava alla card, non al button
$buttons.filter(function(){ return ($(this).data('room-id') || '').toString().trim() === selectedRoom; });

// DOPO (CORRETTO):
$buttons.filter(function(){ return ($(this).data('room-id') || '').toString().trim() === selectedRoom; }).addClass('selected');
```

## 📋 File Modificato

- **File**: `templates/admin/btr-form-anagrafici.php`
- **Righe Modificate**:
  - 5076-5092: Aggiunto funzioni globali con jQuery
  - 5105-5122: Rimosse funzioni duplicate dall'IIFE
  - 4471-4472: Fix applicazione classe selected

## ✅ Risultati

- ✅ Nessun errore `updateCardErrorStatus is not defined` quando si clicca sui pulsanti
- ✅ Nessun errore `$ is not a function` nelle funzioni globali
- ✅ La classe `selected` viene applicata correttamente ai pulsanti camera
- ✅ La selezione visiva delle camere funziona come previsto

## 🔍 Testing

Per verificare i fix:
1. Aprire la pagina di inserimento anagrafici
2. Cliccare sui pulsanti di selezione camera
3. Verificare che non ci siano errori in console
4. Verificare che il pulsante selezionato abbia la classe `selected` (evidenziato visivamente)

## 📝 Note Tecniche

- Le funzioni devono essere globali perché chiamate da event handler definiti in scope diversi
- Usare sempre `jQuery` invece di `$` nelle funzioni globali
- Il wrapping con `jQuery($card)` gestisce sia oggetti jQuery che elementi DOM nativi
- La logica di sincronizzazione con `updateRoomButtons()` mantiene coerenza tra UI e dati

## ⚠️ Attenzione

- Non spostare le funzioni dentro l'IIFE o si ripresenterà l'errore di scope
- Non usare `$` nelle funzioni globali
- Mantenere la logica del filter con `.addClass('selected')` concatenato

---

**Sviluppatore**: Claude Code
**Revisione**: v1.0.233
**Status**: ✅ Testato e Funzionante