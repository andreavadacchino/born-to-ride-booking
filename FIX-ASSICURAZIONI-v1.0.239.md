# FIX v1.0.239 - Assicurazioni non visualizzate nel frontend

## PROBLEMA RISOLTO
- **Sintomo**: Assicurazioni mostrate come "Ass.: €0,00" invece di "Ass.: €5,00"
- **Causa**: Logica assicurazioni annidata dentro condizione `if (empty($meta_results))`
- **Impact**: Quando esistono costi extra individuali, assicurazioni non vengono calcolate

## ANALISI TECNICA
La funzione helper `$get_person_addons` processava le assicurazioni solo quando NON esistevano meta fields individuali per i costi extra:

```php
// PROBLEMA (v1.0.238):
if (empty($meta_results)) {
    // costi extra fallback...
    
    // ❌ Assicurazioni processate SOLO qui
    if (!empty($p['assicurazioni_dettagliate'])) {
        foreach ($p['assicurazioni_dettagliate'] as $slug => $info) {
            $selected = !empty($p['assicurazioni'][$slug]);
            if ($selected) { $sum_ins += floatval($info['importo'] ?? 0); }
        }
    }
}
```

**Risultato**: Con meta fields individuali presenti → `$meta_results` NON vuoto → assicurazioni mai calcolate.

## MODIFICHE APPLICATE

### File: `templates/payment-selection-page-riepilogo-style.php`

**1. Riga 704**: Aggiornato versione da v1.0.238 a v1.0.239
```php
// Helper per costi extra/assicurazioni per persona - v1.0.239
```

**2. Righe 752-758**: Spostata logica assicurazioni fuori da condizione meta_results
```php
// SOLUZIONE (v1.0.239):
if (empty($meta_results)) {
    // solo costi extra fallback...
}

// ✅ FASE 3: Verifica assicurazioni (v1.0.239 - separata da costi extra)
if (!empty($p['assicurazioni_dettagliate']) && is_array($p['assicurazioni_dettagliate'])) {
    foreach ($p['assicurazioni_dettagliate'] as $slug => $info) {
        $selected = !empty($p['assicurazioni'][$slug]);
        if ($selected) { $sum_ins += floatval($info['importo'] ?? 0); }
    }
}
```

## RISULTATO
✅ Assicurazioni ora sempre verificate indipendentemente da costi extra  
✅ Frontend mostra correttamente "Ass.: €5,00" per partecipanti assicurati  
✅ Compatibilità mantenuta con entrambi i sistemi di storage (meta individuali + serializzati)

## DATI PREVENTIVO TESTATO
- **Preventivo ID**: 37512
- **Partecipanti assicurati**: Andrea (€5), Moira (€5), De Daniele (€5)
- **Totale assicurazioni**: €15,00
- **Visualizzazione attesa**: "Ass.: €5,00" per ogni partecipante assicurato

## DATA FIX
14 Settembre 2025