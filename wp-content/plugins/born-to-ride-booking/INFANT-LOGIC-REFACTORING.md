# Refactoring Logica Neonati - v1.0.88

## Riassunto delle Modifiche

### Problema Originale
I neonati venivano mostrati come "Bambino 6-12" nel checkout summary invece di "Neonato". Inoltre, la logica dei "phantom infants" creava partecipanti fantasma che non occupavano posti nelle camere.

### Soluzione Implementata

#### 1. Rimozione Logica Phantom Infants
- **Rimossi tutti i controlli `is_phantom_infant`** dal template `btr-form-anagrafici.php`
- I neonati sono ora trattati come partecipanti reali che occupano un posto nella camera
- Eliminata la creazione automatica di partecipanti fantasma per i neonati

#### 2. Gestione Corretta del Tipo Partecipante
- **Aggiunto campo hidden `tipo_persona`** per tracciare correttamente il tipo di partecipante:
  ```php
  $tipo_persona = '';
  if ($is_infant) {
      $tipo_persona = 'neonato';
  } elseif ($is_child) {
      $tipo_persona = 'bambino';
  } else {
      $tipo_persona = 'adulto';
  }
  ```

#### 3. Restrizioni per Neonati Mantenute
I neonati hanno le seguenti limitazioni:
- **Nessuna assicurazione**: La sezione assicurazioni non viene mostrata per `$child_fascia === 'neonato'`
- **Nessun costo extra**: La sezione costi extra non viene mostrata per `$child_fascia === 'neonato'`
- **RC Skipass nascosta automaticamente**: Logica specifica per nascondere RC Skipass ai neonati

#### 4. Validazione JavaScript Aggiornata
- Rimossa la logica che permetteva ai neonati fantasma di non avere una camera assegnata
- Ora tutti i partecipanti (inclusi i neonati) devono avere una camera assegnata

#### 5. Visualizzazione Corretta nel Checkout
Il checkout summary ora mostra correttamente:
- **Adulto**
- **Bambino 3-8 anni** (f1)
- **Bambino 8-12 anni** (f2)  
- **Bambino 12-14 anni** (f3)
- **Bambino 14-15 anni** (f4)
- **Neonato**

## File Modificati

### `/templates/admin/btr-form-anagrafici.php`
- Rimossa creazione phantom infants (linee 536-588)
- Rimossi controlli `is_phantom_infant` per nome/cognome
- Rimossa logica condizionale per assegnazione camera
- Aggiunto campo hidden `tipo_persona`
- Aggiornate condizioni per mostrare assicurazioni e costi extra
- Aggiornata validazione JavaScript

### `/includes/blocks/btr-checkout-summary/block.php`
- Già conteneva la logica corretta per visualizzare i tipi di partecipante
- Filtra correttamente i neonati duplicati mantenendo quelli validi

## Comportamento Atteso

1. **Durante la compilazione del form**:
   - I neonati devono compilare nome, cognome e selezionare una camera
   - Non vedono opzioni per assicurazioni o costi extra
   - RC Skipass viene nascosta automaticamente

2. **Nel checkout summary**:
   - I neonati appaiono come "Nome Cognome (Neonato)"
   - Viene mostrata la camera assegnata
   - Non hanno costi aggiuntivi

3. **Nel calcolo prezzi**:
   - I neonati occupano un posto camera ma non hanno costi
   - Non influenzano il totale del preventivo

## Note Importanti

- I neonati ora sono partecipanti reali che occupano spazio nelle camere
- La logica di conteggio posti camera deve considerare anche i neonati
- I neonati non possono avere assicurazioni o costi extra
- Il sistema mantiene la retrocompatibilità con preventivi esistenti