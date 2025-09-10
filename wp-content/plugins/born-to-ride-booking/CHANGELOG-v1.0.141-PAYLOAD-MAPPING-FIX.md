# CHANGELOG v1.0.141 - Correzione Mapping Payload AJAX

## 📋 Problemi Identificati e Risolti

### 🔧 **1. Duplicazione Dati Camere**
**Problema**: Il payload inviava due set di dati per le camere:
- `rooms[0][variation_id] = 0` (errato, causava errore nel loop)  
- `camere` con `variation_id = 36567` (corretto)

**Soluzione**: 
- Modificato `class-btr-preventivi.php:89-103`
- Priorità a `camere` che contiene i dati completi
- Fallback a `rooms` solo se `camere` è vuoto
- Log di debug per tracciare quale fonte viene usata

### 🎯 **2. Mapping Campi Corretto**
**Problema**: Vari campi del payload non venivano mappati correttamente:
- `extra_nights_quantity` → Non mappato (diventava 0)
- `dates_extra_night` → Ignorato, usava dati pacchetto
- `assigned_adults` → Valore 0 nel payload (errato)

**Soluzione**:
- Aggiunto mapping completo righe 305-325
- `extra_nights_quantity` calcolato da `extra_nights_enabled`
- `dates_extra_night` ha priorità sui dati pacchetto
- `assigned_adults` calcolato correttamente da adulti disponibili

### 📅 **3. Date Notti Extra**
**Problema**: 
- Payload: `dates_extra_night = "23 Gennaio 2026"`
- Salvato: Date diverse recuperate dal pacchetto

**Soluzione**:
- Modificato righe 673-737
- Priorità 1: Campo `dates_extra_night` dal payload
- Supporto conversione formati italiani ("23 Gennaio 2026" → "2026-01-23")
- Fallback ai dati pacchetto solo se payload vuoto

### 👥 **4. Adulti Assegnati**
**Problema**: `assigned_adults = 0` nel payload causava calcoli errati

**Soluzione**:
- Modificato righe 226-246
- Se `assigned_adults` presente e > 0, usa quello
- Altrimenti calcola da slot disponibili - bambini assegnati
- Fallback al numero adulti del preventivo se calcolo dà 0
- Log di debug per tracciare il calcolo

### 💰 **5. Preservazione Totali Payload**
**Soluzione**: Continua a usare i totali dal payload (correzione v1.0.137)
- `pricing_totale_camere` 
- `pricing_totale_generale`
- `pricing_totale_costi_extra`

## 📝 **Modifiche ai File**

### `class-btr-preventivi.php`
- **Righe 89-103**: Gestione unificata camere con priorità
- **Righe 226-246**: Calcolo corretto adulti assegnati  
- **Righe 297-298**: Salvataggio `assigned_adults` e `assigned_infants`
- **Righe 305-325**: Mapping completo campi payload
- **Righe 356-362**: Log di debug migliorato
- **Righe 673-737**: Gestione date notti extra dal payload

### `born-to-ride-booking.php`
- **Riga 5**: Aggiornamento versione a 1.0.141

## 🧪 **Test**
Creato `tests/debug-payload-mapping-v141.php` per verificare:
- ✅ Variation_id corretto (36567 invece di 0)
- ✅ Extra_nights_quantity corretto (1 invece di 0)  
- ✅ Assigned_adults corretto (2 invece di 0)
- ✅ Date notti extra dal payload
- ✅ Preservazione totali

## 🎯 **Risultato**
✅ **Frontend Authority Preservata**: I dati calcolati dal frontend vengono salvati fedelmente nel database, eliminando discrepanze e "allucinazioni" di dati

## 📊 **Before/After**

| Campo | Prima v1.0.140 | Dopo v1.0.141 | ✅ |
|-------|----------------|----------------|-----|
| `variation_id` | 0 → 36567 (incoerente) | 36567 | ✅ |
| `totale_camera` | 131.3 → 628.3 (ricalcolato) | 131.3 (preservato) | ✅ |  
| `assigned_adults` | 0 (errato) | 2 (corretto) | ✅ |
| `extra_nights_quantity` | 0 (non mappato) | 1 (da flag) | ✅ |
| `btr_extra_night_date` | Date pacchetto | "2026-01-23" (payload) | ✅ |

**Strategia**: Frontend calcola → Backend salva (no ricalcoli)