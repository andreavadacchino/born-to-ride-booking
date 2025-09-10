# Documentazione Sistema Costi Extra "Per Notte"

## Data: 20 Gennaio 2025
## Versione Plugin: 1.0.82+

## Riepilogo

Il sistema dei costi extra è stato aggiornato per fornire una gestione più chiara e intuitiva delle opzioni di calcolo, in particolare per i costi "per notte".

## Come Funziona

### Opzioni di Calcolo

Il sistema supporta 4 modalità di calcolo per i costi extra:

1. **Costo fisso**: Nessuna checkbox selezionata
   - Il costo viene applicato una sola volta per l'intero soggiorno
   - Esempio: €15 per servizio di transfer

2. **Per persona (totale soggiorno)**: Solo checkbox "Per persona" selezionata
   - Il costo viene moltiplicato per il numero di persone
   - Applicato una sola volta per tutto il soggiorno
   - Esempio: €20 per persona per assicurazione viaggio

3. **Per soggiorno (tutte le notti)**: Solo checkbox "Per notte" selezionata
   - Il costo viene moltiplicato per il numero di notti
   - Applicato una sola volta per tutto il gruppo
   - Esempio: €50 per notte per servizio extra camera

4. **Per persona per notte**: Entrambe le checkbox selezionate
   - Il costo viene moltiplicato per persone × notti
   - Esempio: €10 per persona per notte per animale domestico

### Interfaccia Admin

L'interfaccia admin ora mostra:
- Checkbox "Per persona" con tooltip esplicativo
- Checkbox "Per notte" (rinominato da "Per durata") con tooltip esplicativo
- Indicatore dinamico che mostra il tipo di calcolo risultante
- Aggiornamento in tempo reale quando si cambiano le checkbox

### Esempio: Animale Domestico

Il costo extra "Animale Domestico" è configurato con:
- ✓ Per persona
- ✓ Per notte
- = Per persona per notte

Questo significa che se il costo è €10:
- 1 persona con animale per 3 notti = €10 × 1 × 3 = €30
- 2 persone con animale per 3 notti = €10 × 2 × 3 = €60

## File Modificati

1. `/templates/admin/metabox-pacchetto-tab/extra-costs.php`
   - Aggiornata interfaccia con indicatori dinamici
   - Rinominata label "Per durata" in "Per notte"
   - Aggiunti tooltip esplicativi
   - Aggiunto JavaScript per aggiornamento dinamico

2. `/assets/js/admin-extra-costs-dynamic.js`
   - Aggiornato template per nuovi elementi
   - Gestione indicatori dinamici
   - Supporto per reindexing dopo drag & drop

## Note Tecniche

- I valori nel database rimangono invariati (`moltiplica_persone` e `moltiplica_durata`)
- La logica di calcolo backend non è stata modificata
- Solo l'interfaccia utente è stata migliorata per maggiore chiarezza
- Retrocompatibile con configurazioni esistenti

## Test Eseguiti

1. Verificato che "Animale Domestico" mostra correttamente "10€ per notte"
2. Testato aggiornamento dinamico degli indicatori
3. Verificato funzionamento drag & drop
4. Testata aggiunta nuovi elementi con template aggiornato