# Implementazione Etichette Dinamiche per Partecipanti e Camere

## Panoramica
Questo documento descrive l'implementazione del sistema di etichette dinamiche per i partecipanti e le camere nel plugin Born to Ride Booking. Le etichette precedentemente statiche nel codice JavaScript sono ora recuperate dinamicamente dal pannello di amministrazione di WordPress.

## Problema Risolto
In precedenza, le etichette erano hardcoded nel file JavaScript:

### Etichette Partecipanti:
- "partecipante" (riga 3517)
- "Adulto" (righe 3528 e 3537)
- "Bambino" (righe 2367 e 2393)
- "Neonati" (riga 3511)

### Etichette Camere:
- "Adulto:" (riga 1574)
- "Bambini X-Y anni:" (righe 1498, 1512, 1526, 1540)
- "Neonati:" (riga 1551)

Questo rendeva impossibile personalizzare le etichette senza modificare il codice sorgente.

## Soluzione Implementata

### 1. Sistema di Configurazione
Il sistema di configurazione delle etichette nel plugin:

**File:** `includes/class-btr-admin-interface.php`
- Sezione "Etichette Personalizzate" nel pannello admin
- Campi per configurare:
  - `btr_label_adult_singular` - Etichetta Adulto (singolare)
  - `btr_label_adult_plural` - Etichetta Adulti (plurale)
  - `btr_label_child_singular` - Etichetta Bambino (singolare)
  - `btr_label_child_plural` - Etichetta Bambini (plurale)
  - `btr_label_participant` - Etichetta Partecipante
  - `btr_label_infant_singular` - Etichetta Neonato (singolare) **[NUOVO]**
  - `btr_label_infant_plural` - Etichetta Neonati (plurale) **[NUOVO]**

**Nota**: Le etichette per le fasce d'età dei bambini (es. "3-5 anni", "6-7 anni") vengono configurate nel pannello di amministrazione del pacchetto, nella tab "Persone".

### 2. Localizzazione JavaScript
**File:** `includes/class-btr-shortcodes.php` (riga 128)
```php
wp_localize_script('btr-booking-form-js', 'btr_booking_form', [
    // ...
    'labels' => array(
        'adult_singular'  => get_option('btr_label_adult_singular', 'Adulto'),
        'adult_plural'    => get_option('btr_label_adult_plural', 'Adulti'),
        'child_singular'  => get_option('btr_label_child_singular', 'Bambino'),
        'child_plural'    => get_option('btr_label_child_plural', 'Bambini'),
        'participant'     => get_option('btr_label_participant', 'Partecipante'),
        'infant_singular' => get_option('btr_label_infant_singular', 'Neonato'),
        'infant_plural'   => get_option('btr_label_infant_plural', 'Neonati'),
    ),
]);
```

### 3. Modifiche al Frontend JavaScript
**File:** `assets/js/frontend-scripts.js`

#### Modifiche Partecipanti:
```javascript
// Etichetta Partecipante (riga 3517)
// PRIMA: let posizione = '<strong>'+ordinali[i] + '</strong> partecipante'
// DOPO:  let posizione = '<strong>'+ordinali[i] + '</strong> ' + (btr_booking_form.labels.participant || 'partecipante')

// Etichetta Adulto (riga 3528)
// PRIMA: { count: numAdults, label: 'Adulto' }
// DOPO:  { count: numAdults, label: btr_booking_form.labels.adult_singular || 'Adulto' }

// Etichetta Bambino (righe 2367, 2393)
// PRIMA: const labelPersone = `${data.count}x Bambino ${labelChildF3}`
// DOPO:  const labelPersone = `${data.count}x ${btr_booking_form.labels.child_singular} ${labelChildF3}`

// Etichetta Neonati (riga 3511)
// PRIMA: html += `<p class="info-neonati"><strong>Neonati:</strong>`
// DOPO:  html += `<p class="info-neonati"><strong>${btr_booking_form.labels.infant_plural || 'Neonati'}:</strong>`
```

#### Modifiche Visualizzazione Camere:
```javascript
// Etichetta Adulto nelle camere (riga 1574)
// PRIMA: <span class="btr-label-price">${btr_booking_form.labels.adult_singular}</span>:
// DOPO:  (già dinamica)

// Etichette Bambini nelle camere (righe 1498, 1512, 1526, 1540)
// PRIMA: <span class="btr-discount-label btr-label-price">Bambini ${labelChildF1}:</span>
// DOPO:  <span class="btr-discount-label btr-label-price">${btr_booking_form.labels.child_plural || 'Bambini'} ${labelChildF1}:</span>

// Etichetta Neonati nelle camere (riga 1551)
// PRIMA: <span class="btr-label-price">Neonati:</span>
// DOPO:  <span class="btr-label-price">${btr_booking_form.labels.infant_plural || 'Neonati'}:</span>
```

## Come Utilizzare

### Per gli Amministratori
1. Accedere al pannello WordPress
2. Navigare a **Born to Ride → Impostazioni**
3. Scorrere fino alla sezione **Etichette Personalizzate**
4. Modificare le etichette desiderate
5. Fare clic su **Salva modifiche**

Le nuove etichette verranno immediatamente applicate in tutto il frontend.

### Per gli Sviluppatori

#### Aggiungere Nuove Etichette
1. **Aggiungi il campo nel pannello admin** (`class-btr-admin-interface.php`):
```php
add_settings_field(
    'btr_label_nuova_etichetta',
    __( 'Nuova Etichetta', 'born-to-ride-booking' ),
    array( $this, 'render_label_nuova_etichetta' ),
    'btr-booking',
    'btr_booking_labels_section'
);
```

2. **Registra l'opzione**:
```php
register_setting( 'btr_booking_settings_group', 'btr_label_nuova_etichetta', array( $this, 'sanitize_text_field' ) );
```

3. **Aggiungi il metodo di rendering**:
```php
public function render_label_nuova_etichetta() {
    $value = get_option( 'btr_label_nuova_etichetta', 'Valore Default' );
    ?>
    <input type="text" name="btr_label_nuova_etichetta" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
    <?php
}
```

4. **Aggiungi alla localizzazione** (`class-btr-shortcodes.php`):
```php
'labels' => array(
    // ... altre etichette ...
    'nuova_etichetta' => get_option('btr_label_nuova_etichetta', 'Valore Default'),
),
```

5. **Usa nel JavaScript**:
```javascript
const etichetta = btr_booking_form.labels.nuova_etichetta || 'Valore Default';
```

## Testing
È disponibile un file di test completo per verificare il funzionamento delle etichette dinamiche:
- **Percorso:** `tests/test-dynamic-labels.php`
- **URL:** `wp-admin/admin.php?page=test-dynamic-labels` (dopo l'attivazione)

Il test verifica:
- La configurazione corrente delle etichette
- La corretta localizzazione JavaScript
- La presenza delle opzioni nel database
- La registrazione dello script

## Vantaggi
1. **Flessibilità**: Le etichette possono essere modificate senza toccare il codice
2. **Internazionalizzazione**: Facilita la traduzione e l'adattamento per diversi mercati
3. **Manutenibilità**: Riduce la necessità di modifiche al codice per personalizzazioni comuni
4. **Consistenza**: Le etichette sono centralizzate e gestite da un unico punto

## Note di Compatibilità
- Le modifiche sono retrocompatibili: se un'etichetta non è configurata, viene usato il valore di default
- Non sono necessarie migrazioni del database: il sistema usa i valori di default se le opzioni non esistono
- Il sistema è compatibile con tutte le versioni di WordPress che supportano `get_option()` e `wp_localize_script()`

## Versione
Implementato nella versione 1.0.20 del plugin Born to Ride Booking.