# Proposta Patch: conflitto handler `btr_save_anagrafici`

## Sintesi
- Problema: Due handler AJAX registrati sulla stessa action `btr_save_anagrafici` con schema nonce diverso.
  - `includes/class-btr-shortcode-anagrafici.php`: usa `btr_update_anagrafici_nonce` (coerente con il form e con `wp_localize_script`).
  - `includes/class-btr-preventivi.php`: verifica `wp_verify_nonce($_POST['nonce'], 'btr_save_anagrafici')`.
- Rischio: il primo handler che intercetta la richiesta può fallire il nonce e bloccare la risposta.

## Opzione A (consigliata): unificare sull’handler dello shortcode
- Rimuovere in `class-btr-preventivi.php` la registrazione degli hook `wp_ajax[_nopriv]_btr_save_anagrafici` e mantenere solo la gestione in `class-btr-shortcode-anagrafici.php`.
- Motivazione: lo shortcode gestisce UI/nonce/redirect e già ricalcola i totali; è la sorgente di verità del flusso anagrafici.

Diff (indicativo, non applicato):
```
// includes/class-btr-preventivi.php (__construct)
- add_action('wp_ajax_btr_save_anagrafici', [$this, 'save_anagrafici']);
- add_action('wp_ajax_nopriv_btr_save_anagrafici', [$this, 'save_anagrafici']);
```

## Opzione B (fallback): allineare il controllo nonce in `BTR_Preventivi`
- Se occorre mantenere l’handler legacy, aggiornare la verifica per accettare lo stesso nonce del form:
```
// includes/class-btr-preventivi.php::save_anagrafici()
if (
    ! ( isset($_POST['btr_update_anagrafici_nonce_field'])
        && wp_verify_nonce($_POST['btr_update_anagrafici_nonce_field'], 'btr_update_anagrafici_nonce') )
) {
    if ( ! ( isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'btr_update_anagrafici_nonce') ) ) {
        wp_send_json_error(['message' => 'Nonce non valido']);
    }
}
```
- Nota: usare sempre la stessa action `btr_update_anagrafici_nonce` per coerenza con lo shortcode.

## Impatto e rollback
- Nessun impatto sui dati; si riduce ambiguità del flusso.
- Rollback: ripristinare le due righe di `add_action` o il vecchio controllo nonce.

## Test di verifica (manuale)
1) Caricare pagina anagrafici, compilare form, invio → 200 OK e meta `_anagrafici_preventivo` aggiornato.
2) Nonce corrotto → 403 con messaggio errore.
3) Verificare reindirizzo a selezione pagamento/checkout come da risposta.

## Test di integrazione (snippet)
```php
public function test_ajax_save_anagrafici_flow() {
    $preventivo_id = $this->factory->post->create(['post_type' => 'btr_preventivi']);
    $_POST = [
        'action' => 'btr_save_anagrafici',
        'preventivo_id' => $preventivo_id,
        'btr_update_anagrafici_nonce_field' => wp_create_nonce('btr_update_anagrafici_nonce'),
        'anagrafici' => [ [ 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario@example.com' ] ],
    ];
    try {
        do_action('wp_ajax_btr_save_anagrafici');
    } catch (WPAjaxDieStopException $e) {}
    $saved = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
    $this->assertIsArray($saved);
    $this->assertSame('Mario', $saved[0]['nome']);
}
```

## Raccomandazione finale
- Applicare Opzione A per eliminare la duplicazione.
- Se necessario compat, applicare Opzione B come ponte e pianificare la rimozione dell’handler legacy in una minor release.

```
*** End of Patch Proposal
```

