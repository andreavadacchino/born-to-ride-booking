# Sistema Prezzi per Bambini - Born to Ride Booking

## Panoramica

Il sistema di prezzi per bambini permette di configurare prezzi personalizzati per bambini nelle diverse tipologie di camere, sia nella modalità standard che nella modalità allotment.

## Funzionalità Implementate

### 1. Gestione Allotment
- **File**: `templates/admin/metabox-pacchetto-tab/gestione-allotment-camere.php`
- **Funzionalità**: Aggiunta sezione "Prezzi per Bambini" solo per le notti extra
- **Campi**: Checkbox per abilitazione + campo prezzo per ogni fascia di età
- **Configurazione Globale**: Prezzi globali configurati nel tab "Persone"

### 2. Sistema di Salvataggio
- **File**: `includes/class-btr-child-room-pricing.php`
- **Metodi aggiunti**:
  - `save_extra_allotment_child_pricing()` - Salva prezzi per notti extra
  - `get_extra_allotment_child_pricing()` - Recupera prezzi dalle notti extra
  - `calculate_extra_allotment_child_price()` - Calcola prezzo per bambino nelle notti extra
  - `get_global_child_price()` - Ottiene prezzo globale per bambino
  - `get_global_child_pricing()` - Ottiene tutti i prezzi globali

### 3. Integrazione Backend
- **File**: `includes/class-btr-pacchetti-cpt.php`
- **Modifiche**: Aggiornato salvataggio per includere prezzi globali bambini
- **File**: `templates/admin/metabox-pacchetto-tab/persone.php`
- **Modifiche**: Aggiunta configurazione globale prezzi e visualizzazione slot attivi

## Struttura Dati

### Prezzi Globali
```php
// Metadati del post
'btr_global_child_pricing_f1_enabled' => '1',    // Abilitato per fascia 1
'btr_global_child_pricing_f1' => 25.50,          // Prezzo per fascia 1
'btr_global_child_pricing_f2_enabled' => '0',    // Disabilitato per fascia 2
'btr_global_child_pricing_f2' => 0.00,           // Prezzo per fascia 2
// ... altre fasce
```

### Notti Extra (sovrascrittura prezzi globali)
```php
$extra_allotment_data[$date_key]['child_pricing'] = [
    'f1_enabled' => '1',
    'f1' => 30.00,
    // ... altre fasce
];
```

## Utilizzo Frontend

### Calcolo Prezzi
```php
// Istanzia la classe
$child_pricing = new BTR_Child_Room_Pricing();

// Calcola prezzo per bambino nelle notti extra (usa prezzi specifici o globali)
$child_price = $child_pricing->calculate_extra_allotment_child_price(
    $post_id, 
    $date_key, 
    $category_id, 
    $adult_price
);

// Ottieni prezzo globale per bambino
$global_price = $child_pricing->get_global_child_price(
    $post_id, 
    $category_id, 
    $adult_price
);
```

### Recupero Dati
```php
// Ottieni tutti i prezzi per bambini di un pacchetto
$pricing = $child_pricing->get_package_child_pricing($post_id);

// Ottieni prezzi globali per bambini
$global_pricing = $child_pricing->get_global_child_pricing($post_id);

// Ottieni prezzi per notti extra
$extra_pricing = $child_pricing->get_extra_allotment_child_pricing($post_id, $date_key);
```

## Configurazione Fasce di Età

Il sistema utilizza le fasce di età configurate nel plugin principale:

1. **Fascia 1 (f1)**: Bambini 3-6 anni
2. **Fascia 2 (f2)**: Bambini 6-8 anni  
3. **Fascia 3 (f3)**: Bambini 8-10 anni
4. **Fascia 4 (f4)**: Bambini 11-12 anni

### Fallback
Se il sistema dinamico non è disponibile, vengono utilizzate le categorie predefinite.

## JavaScript Frontend

Il sistema fornisce funzioni JavaScript per il calcolo frontend:

```javascript
// Calcola prezzo per bambino
const childPrice = calculateChildRoomPrice(roomType, categoryId, adultPrice);

// Ottieni display del prezzo
const priceDisplay = getChildRoomPriceDisplay(roomType, categoryId);
```

## Stili CSS

Gli stili per i campi prezzi bambini sono inclusi nel template:

```css
.btr-child-pricing-section {
    margin-top: 15px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}

.btr-child-pricing-row {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px;
    background-color: #fff;
    border-radius: 4px;
    border: 1px solid #ddd;
}
```

## Note di Sviluppo

### Compatibilità
- Compatibile con il sistema esistente di prezzi per bambini
- Non interferisce con le funzionalità esistenti
- Fallback al 50% del prezzo adulto se non configurato

### Sicurezza
- Tutti i dati vengono sanitizzati prima del salvataggio
- Verifica dei permessi utente
- Validazione dei nonce

### Performance
- Dati caricati solo quando necessario
- Caching dei dati per ottimizzare le prestazioni
- Query ottimizzate per il database

## Aggiornamenti Futuri

1. **Interfaccia Utente**: Miglioramento dell'interfaccia admin
2. **Validazione**: Aggiunta validazione più robusta dei prezzi
3. **Reporting**: Sistema di report per analisi prezzi bambini
4. **API**: Endpoint REST per gestione prezzi bambini
5. **Import/Export**: Funzionalità per importare/esportare configurazioni prezzi

## Troubleshooting

### Problemi Comuni

1. **Prezzi non salvati**: Verificare che i campi siano presenti nel form
2. **Calcoli errati**: Controllare che i prezzi siano numeri validi
3. **Interfaccia non visibile**: Verificare che il template sia caricato correttamente

### Debug
Abilitare il logging per debug:
```php
error_log("Debug prezzi bambini: " . print_r($pricing_data, true));
```

## Changelog

### Versione 1.0.16
- Implementazione iniziale del sistema prezzi per bambini
- Supporto per modalità allotment
- Supporto per notti extra
- Interfaccia admin completa
- Sistema di calcolo prezzi
- Documentazione completa 