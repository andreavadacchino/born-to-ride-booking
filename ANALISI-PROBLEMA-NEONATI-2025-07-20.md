# Analisi Problema Neonati - v1.0.88

## Problema Identificato

I neonati venivano mostrati erroneamente come "Bambino 6-12 anni" nel checkout summary.

## Causa del Problema

### 1. Flusso dei Dati
- Nella prima fase di prenotazione, viene salvato `_num_neonati` con il numero totale di neonati
- I dati anagrafici vengono inizializzati vuoti per tutti i partecipanti
- Nel form anagrafici, quando manca la fascia per un partecipante, viene applicato un fallback

### 2. Logica di Fallback Errata
Nel file `btr-form-anagrafici.php` (linea 990-998), quando un partecipante non aveva una fascia definita:
```php
// Fallback: se l'indice supera il numero di adulti, assegna fascia in base all'ordine
if ( $child_fascia === '' && $index >= intval( $num_adults ) ) {
    $child_index = $index - intval( $num_adults );
    $fasce_disponibili = ['f1', 'f2', 'f3', 'f4'];
    $child_fascia = $fasce_disponibili[$child_index % count($fasce_disponibili)];
}
```

### 3. Esempio del Problema
Con questa configurazione:
- 2 adulti
- 1 bambino  
- 1 neonato

Il neonato (indice 3) veniva processato così:
- `$child_index = 3 - 2 = 1`
- `$fasce_disponibili[1] = 'f2'` (che corrisponde a "8-12 anni")

## Soluzione Implementata

Aggiunta logica specifica per identificare i neonati PRIMA del fallback:

```php
// Check se il partecipante è un neonato basandosi sul numero totale di neonati
$num_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
if ($num_neonati > 0 && $child_fascia === '') {
    // Calcola se questo indice potrebbe essere un neonato
    $total_partecipanti_paganti = intval($num_adults) + intval($num_children);
    
    // Se l'indice è oltre i partecipanti paganti, potrebbe essere un neonato
    if ($index >= $total_partecipanti_paganti && $index < ($total_partecipanti_paganti + $num_neonati)) {
        $child_fascia = 'neonato';
    }
}
```

## Come Funziona la Soluzione

1. **Recupera il numero di neonati** dal preventivo (`_num_neonati`)
2. **Calcola i partecipanti paganti** (adulti + bambini)
3. **Verifica l'indice del partecipante**:
   - Se è >= partecipanti paganti
   - E < (partecipanti paganti + neonati)
   - Allora è un neonato
4. **Assegna la fascia 'neonato'** prima che il fallback possa assegnare una fascia bambino

## Esempio Corretto

Con la stessa configurazione:
- 2 adulti (indici 0-1)
- 1 bambino (indice 2)
- 1 neonato (indice 3)

Il neonato viene identificato:
- `total_partecipanti_paganti = 2 + 1 = 3`
- `index (3) >= 3` ✓
- `index (3) < (3 + 1)` ✓
- Risultato: `$child_fascia = 'neonato'`

## Visualizzazione nel Checkout

Con questa correzione, il checkout summary mostra correttamente:
- Primo partecipante (Adulto)
- Secondo partecipante (Adulto)
- Terzo partecipante (Bambino 3-8 anni)
- **Quarto partecipante (Neonato)** ← Corretto!

## File Modificati

- `/templates/admin/btr-form-anagrafici.php` - Aggiunta logica identificazione neonati
- `/CHANGELOG.md` - Documentata la correzione