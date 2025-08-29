# FIX CONTEGGIO BAMBINI - v1.0.167

## 🚨 Problema Critico Risolto

### Descrizione del Problema
Il sistema salvava **2 bambini invece di 3** quando venivano selezionati:
- 1 bambino fascia f1 (3-6 anni)
- 1 bambino fascia f2 (6-8 anni)  
- 1 bambino fascia f3 (8-10 anni)
- **Totale reale: 3 bambini**
- **Salvato erroneamente: 2 bambini**

### Screenshot del Problema
Nel riepilogo preventivo appariva:
- Header: "3 partecipanti + 1 neonato" ✅ (corretto)
- Dettaglio: "2 adulti + **1 bambini** + 1 neonato" ❌ (errato - doveva essere 3 bambini)

## 🔍 Causa del Problema

Il codice usava il valore `num_children` inviato dal frontend JavaScript invece di calcolare la somma delle fasce età:

```php
// PRIMA (errato)
$num_children = isset($_POST['num_children']) ? intval($_POST['num_children']) : 0;
// Veniva usato direttamente questo valore errato (2 invece di 3)
```

Il problema era duplice:
1. **Frontend JavaScript**: Calcolava male `num_children`
2. **Backend PHP**: Non verificava/correggeva il valore ricevuto

## ✅ Soluzione Implementata

### 1. Correzione Immediata (linee 123-133)
```php
// DOPO (corretto) - v1.0.167
$children_from_categories = $participants_children_f1 + $participants_children_f2 + 
                           $participants_children_f3 + $participants_children_f4;

if ($children_from_categories > 0) {
    // Usa SEMPRE il totale dalle fasce età se disponibile
    $num_children_corrected = $children_from_categories;
    if ($num_children != $num_children_corrected) {
        error_log("[BTR v1.0.167] CORREZIONE num_children: POST dice {$num_children}, ma somma fasce = {$num_children_corrected}");
    }
    $num_children = $num_children_corrected;
}
```

### 2. Rimozione Logica Errata (linee 458-465)
Rimossa la logica che ricalcolava i bambini dalle camere assegnate (era imprecisa):
```php
// RIMOSSO - era fonte di errori
// $total_children_calculated dalle camere...

// ORA - usa direttamente il valore corretto
$final_num_children = $num_children; // già corretto sopra
```

## 📊 Impatto della Correzione

### Prima del Fix:
- Payload: `participants_children_f1: 1, f2: 1, f3: 1` = 3 bambini
- Salvato in DB: `_num_children: 2` ❌
- Display: "2 adulti + 1 bambini + 1 neonato" ❌

### Dopo il Fix:
- Payload: `participants_children_f1: 1, f2: 1, f3: 1` = 3 bambini
- Salvato in DB: `_num_children: 3` ✅
- Display: "2 adulti + 3 bambini + 1 neonato" ✅

## 🧪 Testing

### Test File
`tests/test-children-count-fix.php` - Verifica automatica del fix

### Test Manuale
1. Crea nuovo preventivo con:
   - 2 adulti
   - 1 bambino f1 (3-6 anni)
   - 1 bambino f2 (6-8 anni)
   - 1 bambino f3 (8-10 anni)
   - 1 neonato

2. Verifica nel riepilogo:
   - Deve mostrare "5 partecipanti + 1 neonato"
   - Deve mostrare "2 adulti + 3 bambini + 1 neonato"

3. Controlla i meta nel database:
   ```sql
   SELECT meta_key, meta_value 
   FROM wp_postmeta 
   WHERE post_id = [ID_PREVENTIVO] 
   AND meta_key IN ('_num_adults', '_num_children', '_num_neonati');
   ```

## 📝 Note Tecniche

- **File modificato**: `includes/class-btr-preventivi.php`
- **Versione**: 1.0.167
- **Data fix**: 12 Agosto 2025
- **Backward compatibility**: ✅ Mantiene compatibilità
- **Performance impact**: Nessuno (stesso numero di operazioni)

## ⚠️ Raccomandazioni

1. **Frontend JavaScript**: Andrebbe sistemato anche il calcolo nel file `frontend-scripts.js` per evitare discrepanze
2. **Validazione**: Aggiungere validazione più robusta dei dati in ingresso
3. **Testing**: Testare con diverse combinazioni di fasce età
4. **Monitoring**: Monitorare i log per eventuali discrepanze future

## 🔄 Rollback (se necessario)

Per tornare alla versione precedente:
1. Ripristina `class-btr-preventivi.php` dalla v1.0.166
2. Cambia versione plugin a 1.0.166

---

**Autore**: Claude Code  
**Review**: Richiesta dall'utente tramite `/sc:troubleshoot`  
**Status**: ✅ RISOLTO