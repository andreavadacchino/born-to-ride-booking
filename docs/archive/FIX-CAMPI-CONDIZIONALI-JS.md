# Fix Campi Condizionali JavaScript - v1.0.88

## Problema Risolto

I campi indirizzo e codice fiscale non si mostravano/nascondevano correttamente quando si selezionavano assicurazioni diverse da RC Skipass.

## Causa del Problema

1. **Errore JavaScript**: La variabile `personCard` non era definita nell'event handler originale
2. **Event Binding**: L'evento non veniva correttamente associato agli elementi dinamici
3. **Logica di Controllo**: La logica per determinare se un'assicurazione richiede i campi era incompleta

## Soluzione Implementata

### 1. Funzione Centralizzata
Creata una funzione `handleInsuranceFieldVisibility()` che gestisce tutta la logica di visibilità:

```javascript
function handleInsuranceFieldVisibility(personIndex) {
    // 1. Trova la card del partecipante
    var personCard = $('.btr-person-card[data-person-index="' + personIndex + '"]');
    
    // 2. Trova i campi da gestire
    var codiceFiscaleField = personCard.find('.codice-fiscale-field');
    var addressFields = personCard.find('.address-field');
    
    // 3. Controlla le assicurazioni selezionate
    var hasInsuranceRequiringFiscalCode = false;
    personCard.find('input[name^="anagrafici"][name*="[assicurazioni]"]:checked').each(function() {
        var isRcSkipass = $(this).data('no-fiscal-code') === true || 
                         $(this).data('rc-skipass') === true;
        if (!isRcSkipass) {
            hasInsuranceRequiringFiscalCode = true;
        }
    });
    
    // 4. Applica la visibilità
    if (personIndex === 0) {
        // Primo partecipante: sempre visibili
        codiceFiscaleField.removeClass('hidden-field');
        addressFields.removeClass('hidden-field');
    } else if (hasInsuranceRequiringFiscalCode) {
        // Altri partecipanti con assicurazioni (non RC): mostra
        codiceFiscaleField.removeClass('hidden-field');
        addressFields.removeClass('hidden-field');
    } else {
        // Altri partecipanti senza assicurazioni o solo RC: nascondi
        codiceFiscaleField.addClass('hidden-field');
        addressFields.addClass('hidden-field');
    }
}
```

### 2. Event Delegation
Utilizzato `$(document).on()` per gestire eventi su elementi dinamici:

```javascript
$(document).on('change', 'input[name^="anagrafici"][name*="[assicurazioni]"]', function() {
    var personIndex = $(this).closest('.btr-person-card').data('person-index');
    handleInsuranceFieldVisibility(personIndex);
});
```

### 3. Inizializzazione
Aggiunta inizializzazione al caricamento della pagina:

```javascript
$(document).ready(function() {
    $('.btr-person-card').each(function() {
        var personIndex = $(this).data('person-index');
        handleInsuranceFieldVisibility(personIndex);
    });
});
```

## Comportamento Finale

### Primo Partecipante (index = 0)
- ✅ Tutti i campi sempre visibili
- ✅ Codice fiscale sempre visibile
- ✅ Campi indirizzo sempre visibili

### Altri Partecipanti (index > 0)
- ✅ Campi nascosti di default
- ✅ Mostrati quando si seleziona un'assicurazione diversa da RC Skipass
- ✅ Nascosti quando:
  - Nessuna assicurazione selezionata
  - Solo RC Skipass selezionata

### Debug Console
Il sistema include log dettagliati per il debug:
- Stato delle assicurazioni per ogni partecipante
- Campi trovati e loro visibilità
- Azioni eseguite (mostra/nascondi)

### 4. Correzione Validazione Base
Aggiornata la funzione `validateAnagrafici()` per non richiedere campi nascosti:

```javascript
// Salta gli input in campi nascosti (address-field e codice-fiscale-field)
if ($fieldGroup.hasClass('hidden-field')) {
    console.log('[BTR DEBUG] Saltando validazione per campo nascosto:', $input.attr('name'));
    return true; // continue
}
```

### 5. Correzione Validazione Dettagliata
Aggiornata la funzione `validateAllData()` (che genera i messaggi di errore specifici) per gestire correttamente i campi condizionali:

```javascript
// NUOVA LOGICA: Salta i campi nascosti
if ($fieldGroup.hasClass('hidden-field')) {
    console.log('[BTR DEBUG] validateAllData - Saltando campo nascosto:', $input.attr('name'));
    return true; // continue
}

// Gestione speciale per campi indirizzo
else if ($input.attr('name') && 
        ($input.attr('name').includes('[indirizzo_residenza]') || 
         $input.attr('name').includes('[numero_civico]') || 
         $input.attr('name').includes('[cap_residenza]'))) {
    
    const isFirstParticipant = index === 0;
    let hasInsuranceRequiringAddress = false;
    
    $card.find('input[name*="[assicurazioni]"]:checked').each(function() {
        const isRcSkipass = $(this).data('no-fiscal-code') === true || 
                           $(this).data('rc-skipass') === true;
        if (!isRcSkipass) {
            hasInsuranceRequiringAddress = true;
        }
    });

    // Solo richiesto se primo partecipante O ha assicurazioni che richiedono indirizzo
    if (!$input.val().trim() && (isFirstParticipant || hasInsuranceRequiringAddress)) {
        // ... errore
    }
}
```

### 6. Sincronizzazione Validazione
Aggiunto trigger automatico di entrambe le funzioni di validazione quando cambia la visibilità:

```javascript
// Rilancia la validazione base
if (typeof validateAnagrafici === 'function') {
    setTimeout(function() {
        validateAnagrafici();
    }, 100);
}

// Rilancia anche validateAllData per aggiornare i messaggi di errore dettagliati
if (typeof validateAllData === 'function') {
    setTimeout(function() {
        validateAllData();
    }, 150);
}
```

## Test della Soluzione

1. **Test Primo Partecipante**: Verificare che tutti i campi siano sempre visibili e richiesti
2. **Test RC Skipass**: Selezionare solo RC Skipass su un partecipante > 0 e verificare:
   - Campi indirizzo e codice fiscale nascosti
   - Validazione non richiede questi campi
   - Nessun alert di campi mancanti
3. **Test Altre Assicurazioni**: Selezionare altre assicurazioni e verificare:
   - Campi indirizzo e codice fiscale visibili
   - Validazione richiede questi campi se vuoti
4. **Test Combinazioni**: Selezionare RC + altra assicurazione e verificare che i campi appaiano e siano richiesti
5. **Test Deselezione**: Deselezionare tutte le assicurazioni e verificare:
   - Campi si nascondono (eccetto primo partecipante)
   - Validazione non li richiede più
6. **Test Validazione Dinamica**: Cambiare assicurazioni e verificare che la validazione si aggiorna in tempo reale

## File Modificati

- `/templates/admin/btr-form-anagrafici.php` - Refactoring completo della gestione JavaScript e validazione