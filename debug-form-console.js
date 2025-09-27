// ISTRUZIONI: Copia e incolla questo codice nella console del browser mentre sei sulla pagina anagrafici

console.log('=== DEBUG FORM ANAGRAFICI ===');

// Test 1: Verifica presenza form
var $form = jQuery('#btr-anagrafici-form, .btr-form');
console.log('1. Form trovato:', $form.length > 0 ? 'SI' : 'NO');
console.log('   - Numero elementi:', $form.length);
console.log('   - Tag:', $form.prop('tagName'));
console.log('   - ID:', $form.attr('id'));
console.log('   - Classi:', $form.attr('class'));

// Test 2: Verifica campi nel form
if ($form.length > 0) {
    console.log('\n2. Campi nel form:');
    console.log('   - Input totali:', $form.find('input').length);
    console.log('   - Input hidden:', $form.find('input[type="hidden"]').length);
    console.log('   - Input text:', $form.find('input[type="text"]').length);
    console.log('   - Select:', $form.find('select').length);
    console.log('   - Textarea:', $form.find('textarea').length);
    
    // Mostra i campi hidden
    console.log('\n3. Campi hidden:');
    $form.find('input[type="hidden"]').each(function() {
        console.log('   -', this.name, '=', jQuery(this).val());
    });
    
    // Test serializzazione
    console.log('\n4. Test serializzazione:');
    var serialized = $form.serialize();
    console.log('   - Lunghezza dati:', serialized.length);
    console.log('   - Dati (primi 500 char):', serialized.substring(0, 500));
    
    // Verifica se ci sono campi con valori
    console.log('\n5. Campi compilati:');
    var campiCompilati = 0;
    $form.find('input[type!="hidden"], select, textarea').each(function() {
        if (jQuery(this).val()) {
            console.log('   -', this.name, '=', jQuery(this).val());
            campiCompilati++;
        }
    });
    console.log('   Totale campi compilati:', campiCompilati);
    
    // Test invio AJAX
    console.log('\n6. Test chiamata AJAX:');
    console.log('   - ajax_url:', typeof btr_anagrafici !== 'undefined' ? btr_anagrafici.ajax_url : 'NON DEFINITO');
    console.log('   - nonce:', typeof btr_anagrafici !== 'undefined' ? btr_anagrafici.nonce : 'NON DEFINITO');
    
} else {
    console.log('\n❌ ERRORE: Nessun form trovato con selettore "#btr-anagrafici-form, .btr-form"');
    console.log('Form disponibili nella pagina:');
    jQuery('form').each(function(i) {
        console.log('   - Form', i+1, ': id="' + jQuery(this).attr('id') + '", class="' + jQuery(this).attr('class') + '"');
    });
}

console.log('\n=== FINE DEBUG ===');