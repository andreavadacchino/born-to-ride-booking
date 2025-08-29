# ANALISI PROBLEMA REDIRECT CARRELLO

## SINTESI DEL PROBLEMA
Il redirect dalla pagina carrello alla pagina anagrafici non funziona nonostante vari tentativi con approcci diversi.

## ANALISI DETTAGLIATA

### 1. COSA DOVREBBE SUCCEDERE
- Utente accede a `/carrello/`
- Sistema verifica se c'è un preventivo in sessione
- Sistema verifica se anagrafici non sono compilati
- Se entrambe le condizioni sono vere → redirect a `/inserisci-anagrafici/?preventivo_id=XXX`

### 2. COSA SUCCEDE REALMENTE
- Utente accede a `/carrello/`
- La pagina del carrello viene caricata normalmente
- Nessun redirect avviene
- I metodi di redirect non vengono mai chiamati

### 3. PROBLEMI IDENTIFICATI

#### 3.1 `is_cart()` non funziona
- Ritorna sempre `false` in tutti gli hook testati
- Probabilmente perché WooCommerce non ha ancora determinato che siamo nel carrello

#### 3.2 Timing degli hooks
- `template_redirect`: Troppo presto, WooCommerce non sa ancora che siamo nel carrello
- `wp`: Ancora troppo presto
- `woocommerce_before_cart`: Non sembra essere chiamato (forse il tema lo bypassa)
- `wp_footer`: `is_cart()` ancora false
- `wp_head`: Troppo presto per avere accesso completo a WooCommerce

#### 3.3 Possibili interferenze
- Il tema Salient potrebbe avere un suo sistema di routing
- WooCommerce Blocks potrebbe gestire il carrello diversamente
- Altri plugin potrebbero interferire

### 4. EVIDENZE DAI TEST

1. **Sessione funziona**: Il preventivo viene correttamente salvato e recuperato dalla sessione
2. **Condizioni corrette**: I test mostrano che le condizioni per il redirect sono soddisfatte
3. **Hook registrati**: I test confermano che gli hook sono registrati correttamente
4. **Metodi non chiamati**: I log non mostrano l'esecuzione dei metodi di redirect

### 5. IPOTESI SUL PERCHÉ NON FUNZIONA

1. **WooCommerce Blocks**: Il carrello potrebbe usare il nuovo sistema React-based che non triggera gli hook classici
2. **Tema Salient**: Potrebbe avere template custom che bypassano gli hook standard
3. **Caching**: Potrebbe esserci un sistema di cache che previene l'esecuzione del PHP
4. **Routing custom**: WooCommerce potrebbe usare un sistema di routing che non passa per i normali hook WordPress

### 6. SOLUZIONI ALTERNATIVE DA CONSIDERARE

#### Opzione 1: Modifica diretta del template
- Trovare il template del carrello (probabilmente in Salient)
- Aggiungere il controllo direttamente nel template

#### Opzione 2: JavaScript nel carrello
- Aggiungere JS che viene caricato SOLO nel carrello
- Fare la verifica via AJAX e redirect lato client

#### Opzione 3: Shortcode di controllo
- Creare uno shortcode da inserire nella pagina carrello
- Lo shortcode fa il controllo e redirect

#### Opzione 4: Filtro sul contenuto del carrello
- Usare `the_content` filter quando siamo nella pagina con ID 9 (carrello)

#### Opzione 5: WooCommerce notices + auto-redirect
- Mostrare un notice nel carrello
- Redirect automatico dopo X secondi

### 7. RACCOMANDAZIONE

Data la complessità e i tentativi falliti, suggerisco di:

1. **Prima verificare**: 
   - Se il tema usa template custom per il carrello
   - Se WooCommerce Blocks è attivo
   - Se ci sono plugin di cache attivi

2. **Poi implementare**:
   - Una soluzione basata su JavaScript/AJAX che non dipende da `is_cart()`
   - O modificare direttamente il template del carrello

### 8. CODICE DA RIMUOVERE SE SI VUOLE RIPRISTINARE

In `class-btr-preventivi-ordini.php`:
- Righe 18-26: Tutti gli `add_action` per il redirect
- Righe 2355-2465: Metodi `redirect_cart_to_anagrafici()` e `redirect_cart_to_anagrafici_wc()`
- Righe 2588-2629: Metodo `inject_cart_redirect_js()`
- Righe 2637-2703: Metodo `early_cart_detection_redirect()`