# Strategia Riorganizzazione Flusso Prodotti WooCommerce - Born to Ride Booking

## Analisi Situazione Attuale

### Flusso Attuale
1. **Form Anagrafici** (`btr-form-anagrafici.php`): Raccoglie tutti i dati dettagliati
2. **Conversione al Checkout**: Crea prodotti WooCommerce aggregati
3. **Problema**: I dettagli si perdono, totali non corrispondono al custom summary

### Struttura Dati nel Form Anagrafici

```php
// Per ogni partecipante:
$anagrafici[$index] = [
    // Dati personali
    'nome', 'cognome', 'email', ...
    
    // Classificazione
    'tipo_persona' => 'adulto|bambino|neonato',
    'fascia' => 'adulto|bambini_f1|f2|f3|f4|neonato',
    
    // Camera assegnata
    'camera' => 'room_id',
    'camera_tipo' => 'matrimoniale|doppia|tripla|quadrupla',
    
    // Assicurazioni selezionate
    'assicurazioni' => ['slug' => '1|0'],
    'assicurazioni_dettagliate' => [
        'slug' => [
            'product_id', 'descrizione', 'importo', 'tipo_importo'
        ]
    ],
    
    // Costi extra selezionati
    'costi_extra' => ['slug' => '1|0'],
    'costi_extra_dettagliate' => [
        'slug' => [
            'product_id', 'descrizione', 'importo', 
            'moltiplica_persone', 'moltiplica_durata'
        ]
    ]
];
```

## Strategia di Riorganizzazione

### 1. Prodotti Dettagliati per Partecipante

Invece di aggregare per categoria, creare prodotti individuali:

```php
// PRIMA (attuale):
"Adulti - Prezzo Base" x 2 = €636,00

// DOPO (proposto):
"Mario Rossi - Camera Doppia A - Pacchetto Base" = €318,00
"Anna Bianchi - Camera Doppia A - Pacchetto Base" = €318,00
```

### 2. Struttura Prodotti Proposta

#### A. Prodotto Base Partecipante
```php
[
    'name' => "{Nome} {Cognome} - {Tipo Camera} - Pacchetto Base",
    'price' => prezzo_base + supplemento_camera,
    'meta' => [
        'tipo' => 'partecipante_base',
        'partecipante_id' => $index,
        'nome_completo' => "{Nome} {Cognome}",
        'categoria' => 'adulto|bambino_f1|...',
        'camera' => 'Doppia A',
        'prezzo_base' => 318.00,
        'supplemento' => 0.00
    ]
]
```

#### B. Prodotto Assicurazione
```php
[
    'name' => "{Nome} {Cognome} - {Descrizione Assicurazione}",
    'price' => importo_assicurazione,
    'meta' => [
        'tipo' => 'assicurazione',
        'partecipante_id' => $index,
        'nome_completo' => "{Nome} {Cognome}",
        'assicurazione_slug' => 'rc-skipass',
        'descrizione' => 'Assicurazione RC Skipass'
    ]
]
```

#### C. Prodotto Costo Extra
```php
[
    'name' => "{Nome} {Cognome} - {Descrizione Extra}",
    'price' => importo_calcolato,
    'meta' => [
        'tipo' => 'costo_extra',
        'partecipante_id' => $index,
        'nome_completo' => "{Nome} {Cognome}",
        'extra_slug' => 'no-skipass',
        'descrizione' => 'No Skipass',
        'importo_originale' => -35.00
    ]
]
```

#### D. Prodotto Notte Extra
```php
[
    'name' => "{Nome} {Cognome} - Notti Extra ({num} notti)",
    'price' => prezzo_notte_extra * num_notti,
    'meta' => [
        'tipo' => 'notte_extra',
        'partecipante_id' => $index,
        'nome_completo' => "{Nome} {Cognome}",
        'num_notti' => 1,
        'prezzo_per_notte' => 62.00
    ]
]
```

### 3. Implementazione Tecnica

#### Fase 1: Modifica `add_detailed_cart_items()`

```php
public function add_detailed_cart_items($preventivo_id, $anagrafici_data) {
    // Loop per ogni partecipante invece che per categoria
    foreach ($anagrafici_data as $index => $partecipante) {
        $nome_completo = $partecipante['nome'] . ' ' . $partecipante['cognome'];
        $camera_info = $this->get_camera_info($partecipante);
        
        // 1. Prodotto base partecipante
        $this->add_partecipante_base_product(
            $preventivo_id, 
            $partecipante, 
            $camera_info
        );
        
        // 2. Assicurazioni individuali
        $this->add_assicurazioni_products(
            $preventivo_id,
            $partecipante
        );
        
        // 3. Costi extra individuali
        $this->add_costi_extra_products(
            $preventivo_id,
            $partecipante
        );
        
        // 4. Notti extra se applicabili
        $this->add_notti_extra_product(
            $preventivo_id,
            $partecipante,
            $camera_info
        );
    }
}
```

#### Fase 2: Funzioni Helper

```php
private function add_partecipante_base_product($preventivo_id, $partecipante, $camera_info) {
    $nome_completo = $partecipante['nome'] . ' ' . $partecipante['cognome'];
    $camera_label = $camera_info['tipo'] . ' ' . $camera_info['lettera'];
    
    // Calcola prezzo base per questo partecipante
    $prezzo_base = $this->calculate_prezzo_base_partecipante(
        $partecipante,
        $camera_info
    );
    
    $product_name = sprintf(
        '%s - Camera %s - Pacchetto Base',
        $nome_completo,
        $camera_label
    );
    
    $this->add_virtual_cart_item(
        $product_name,
        $prezzo_base,
        1,
        [
            'type' => 'partecipante_base',
            'partecipante_index' => $partecipante['index'],
            'nome_completo' => $nome_completo,
            'categoria' => $partecipante['fascia'],
            'camera' => $camera_label,
            'camera_tipo' => $camera_info['tipo'],
            'prezzo_base' => $camera_info['prezzo_pp'],
            'supplemento' => $camera_info['supplemento']
        ]
    );
}
```

### 4. Gestione Sconti/Riduzioni

Per i costi negativi (es. No Skipass -35€):

```php
private function add_costi_extra_products($preventivo_id, $partecipante) {
    if (empty($partecipante['costi_extra'])) {
        return;
    }
    
    foreach ($partecipante['costi_extra'] as $slug => $selected) {
        if (!$selected) continue;
        
        $dettagli = $partecipante['costi_extra_dettagliate'][$slug] ?? [];
        $importo = floatval($dettagli['importo'] ?? 0);
        
        if ($importo < 0) {
            // Sconto: aggiungi come fee WooCommerce
            $this->add_sconto_as_fee(
                $partecipante['nome'] . ' ' . $partecipante['cognome'],
                $dettagli['descrizione'],
                $importo
            );
        } else {
            // Costo positivo: aggiungi come prodotto
            $this->add_virtual_cart_item(
                $partecipante['nome'] . ' ' . $partecipante['cognome'] . ' - ' . $dettagli['descrizione'],
                $importo,
                1,
                ['type' => 'costo_extra', 'extra_slug' => $slug]
            );
        }
    }
}
```

### 5. Visualizzazione nel Carrello/Checkout

Il carrello mostrerà:

```
CARRELLO WOOCOMMERCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Mario Rossi - Camera Doppia A - Pacchetto Base        €318,00
Mario Rossi - Assicurazione RC Skipass                 €15,00
Mario Rossi - No Skipass                              -€35,00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Anna Bianchi - Camera Doppia A - Pacchetto Base       €318,00
Anna Bianchi - Assicurazione Annullamento              €30,00
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TOTALE                                                €574,30
```

### 6. Vantaggi della Nuova Struttura

1. **Trasparenza Totale**: Ogni voce è chiaramente identificabile
2. **Tracciabilità**: Ogni prodotto è collegato a un partecipante specifico
3. **Flessibilità**: Facile aggiungere/rimuovere voci individuali
4. **Coerenza**: Totali sempre allineati con il custom summary
5. **Gestione Ordini**: L'amministratore vede tutti i dettagli nell'ordine

### 7. Fasi di Implementazione

#### Fase 1: Refactoring Base (2-3 giorni)
- Modificare `add_detailed_cart_items()` per creare prodotti individuali
- Creare funzioni helper per ogni tipo di prodotto
- Testare con preventivi esistenti

#### Fase 2: Gestione Sconti (1-2 giorni)
- Implementare sistema fees per sconti individuali
- Garantire persistenza nel checkout
- Testare con vari scenari di sconto

#### Fase 3: Ottimizzazione Display (1-2 giorni)
- Migliorare visualizzazione nel carrello
- Raggruppare visivamente per partecipante
- Aggiungere CSS per chiarezza

#### Fase 4: Testing e Deploy (2-3 giorni)
- Test completi con vari scenari
- Verifica compatibilità WooCommerce Blocks
- Deploy graduale con monitoraggio

## Codice di Esempio

```php
// Esempio completo di implementazione
class BTR_Preventivo_To_Order_V2 extends BTR_Preventivo_To_Order {
    
    public function add_detailed_cart_items($preventivo_id, $anagrafici_data) {
        // Pulisci carrello esistente
        $this->clear_existing_btr_items();
        
        // Recupera dati necessari
        $riepilogo = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
        
        // Processa ogni partecipante
        foreach ($anagrafici_data as $index => $partecipante) {
            $this->process_partecipante($preventivo_id, $partecipante, $index, $camere_selezionate);
        }
        
        // Applica fees per sconti globali
        $this->apply_global_discounts($preventivo_id);
    }
    
    private function process_partecipante($preventivo_id, $partecipante, $index, $camere_selezionate) {
        // Implementazione dettagliata per ogni partecipante
        // ...
    }
}
```

## Conclusione

Questa riorganizzazione garantirà:
- Corrispondenza perfetta tra form anagrafici e checkout
- Massima trasparenza per il cliente
- Facilità di gestione per l'amministratore
- Scalabilità per future funzionalità

Il sistema diventa più modulare e manutenibile, con ogni componente del viaggio chiaramente identificato e prezzato.