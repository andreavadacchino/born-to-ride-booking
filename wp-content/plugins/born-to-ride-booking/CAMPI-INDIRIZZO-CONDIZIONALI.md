# Gestione Campi Indirizzo Condizionali - v1.0.88

## Requisito

Dal secondo partecipante in poi, nascondere i campi:
- Indirizzo di residenza
- CAP  
- Numero civico

Questi campi devono essere mostrati solo quando si aggiunge un'assicurazione al partecipante, **esclusa RC Skipass**.

## Implementazione

### 1. Struttura HTML
I campi indirizzo hanno già la classe `address-field` e la logica iniziale per essere nascosti:

```php
<div class="btr-field-group w-30 address-field <?php echo ($index === 0 || !empty($persona['assicurazioni'])) ? '' : 'hidden-field'; ?>" data-person-index="<?php echo $index; ?>">
    <label for="btr_indirizzo_residenza_<?php echo $index; ?>"><?php esc_html_e('Indirizzo di residenza', 'born-to-ride-booking'); ?></label>
    <input type="text" id="btr_indirizzo_residenza_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][indirizzo_residenza]" value="<?php echo esc_attr($persona['indirizzo_residenza'] ?? ''); ?>" >
</div>
```

### 2. Logica JavaScript

La logica utilizza la stessa variabile `hasInsuranceRequiringFiscalCode` già usata per il codice fiscale:

```javascript
// Controlla se ha assicurazioni che richiedono dati completi (esclude RC Skipass)
var hasInsuranceRequiringFiscalCode = false;

$('.btr-person-card[data-person-index="' + personIndex + '"] input[name^="anagrafici"][name*="[assicurazioni]"]').each(function() {
    if ($(this).is(':checked')) {
        hasInsurance = true;
        
        // RC Skipass non richiede codice fiscale né indirizzo
        var isRcSkipass = $(this).data('no-fiscal-code') === true;
        if (!isRcSkipass) {
            hasInsuranceRequiringFiscalCode = true;
        }
    }
});

// Show/hide address fields based on insurance selection (excluding RC Skipass)
const addressFields = personCard.find('.address-field');
if (hasInsuranceRequiringFiscalCode) {
    addressFields.removeClass('hidden-field');
} else if (personIndex !== 0) { // Always show for first participant
    addressFields.addClass('hidden-field');
}
```

### 3. Comportamento

#### Primo Partecipante (index = 0)
- **Tutti i campi sempre visibili** per garantire almeno un set completo di dati

#### Dal Secondo Partecipante in poi
- **Campi indirizzo nascosti di default**
- **Mostrati quando**:
  - Si seleziona un'assicurazione diversa da RC Skipass
  - Esempio: Annullamento Viaggio, Medico-Bagaglio, All-Inclusive
- **Rimangono nascosti quando**:
  - Nessuna assicurazione selezionata
  - Solo RC Skipass selezionata

### 4. CSS

```css
.address-field {
    transition: opacity 0.3s ease, max-height 0.3s ease;
    overflow: hidden;
    opacity: 1;
    max-height: 100px;
}

.address-field.hidden-field {
    opacity: 0;
    max-height: 0;
    margin-top: 0;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
}
```

## Vantaggi

1. **Interfaccia più pulita**: Meno campi visibili riduce il carico cognitivo
2. **Compilazione più veloce**: Solo i campi necessari sono visibili
3. **Coerenza**: Stesso comportamento del codice fiscale
4. **Transizioni fluide**: Animazioni CSS per un'esperienza utente migliore

## File Modificati

- `/templates/admin/btr-form-anagrafici.php` - Aggiornata logica JavaScript per gestione campi indirizzo