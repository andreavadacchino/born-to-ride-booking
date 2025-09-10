/**
 * Script per gestire il salvataggio AJAX del pricing mode
 */
jQuery(document).ready(function($) {
    
    // Debug: Verifica che lo script sia caricato
    console.log('🚀 Script admin-pricing-mode.js caricato');
    
    // Gestisce il cambiamento del checkbox pricing mode
    window.savePricingMode = function(checkbox, index, dataKey, postId) {
        const isChecked = checkbox.checked;
        
        console.log('Salvando pricing mode:', {
            dataKey: dataKey,
            postId: postId,
            isChecked: isChecked
        });
        
        // Prepara i dati per AJAX
        const ajaxData = {
            action: 'btr_save_pricing_mode',
            nonce: btrPricingMode.nonce,
            post_id: postId,
            data_key: dataKey,
            pricing_per_room: isChecked ? 1 : 0
        };
        
        // Disabilita il checkbox durante il salvataggio
        checkbox.disabled = true;
        
        // Aggiungi indicatore di caricamento usando jQuery correttamente
        const $label = $(checkbox).closest('label');
        const $span = $label.find('span');
        const originalText = $span.text();
        $span.text(originalText + ' (salvando...)');
        
        // Chiamata AJAX
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : btrPricingMode.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('✅ Risposta AJAX:', response);
                
                if (response.success) {
                    console.log('🎉 Pricing mode salvato con successo!');
                    // Mostra messaggio di successo temporaneo
                    $span.text(originalText + ' ✓').css('color', 'green');
                    setTimeout(function() {
                        $span.text(originalText).css('color', '');
                    }, 2000);
                } else {
                    console.error('❌ Errore nel salvataggio:', response.data);
                    alert('Errore nel salvataggio: ' + (response.data || 'Errore sconosciuto'));
                    // Ripristina lo stato precedente
                    checkbox.checked = !isChecked;
                }
            },
            error: function(xhr, status, error) {
                console.error('💥 Errore AJAX:', error);
                console.error('XHR Response:', xhr.responseText);
                alert('Errore di connessione. Riprova.');
                // Ripristina lo stato precedente
                checkbox.checked = !isChecked;
            },
            complete: function() {
                // Riabilita il checkbox e ripristina il testo
                checkbox.disabled = false;
                $span.text(originalText);
            }
        });
    };
    
    // Aggiorna la funzione togglePricingMode esistente
    window.togglePricingMode = function(checkbox, index) {
        // Previeni interferenze con jQuery Validate
        try {
            // Temporaneamente disabilita la validazione su questo elemento
            $(checkbox).removeClass('error').off('.validate');
        console.log('🔄 togglePricingMode chiamata:', {
            checkbox: checkbox,
            index: index,
            checked: checkbox.checked,
            name: checkbox.name
        });
        
        const isPerRoom = checkbox.checked;
        const label = document.getElementById('btr_pricing_label_' + index);
        const description = document.getElementById('btr_pricing_desc_' + index);
        
        if (isPerRoom) {
            if (label) label.textContent = 'Prezzo per camera (€)';
            if (description) description.textContent = 'Inserisci il prezzo totale per camera che verrà diviso per il numero di persone.';
        } else {
            if (label) label.textContent = 'Prezzo per persona (€)';
            if (description) description.textContent = 'Inserisci il prezzo per persona per le notti extra.';
        }
        
        try {
            // Estrai i dati necessari per il salvataggio
            const nameMatch = checkbox.name.match(/\[([^\]]+)\]/);
            const dataKey = nameMatch ? nameMatch[1] : null;
            const postId = $('#post_ID').val() || $('input[name="post_ID"]').val() || $('#post_id').val();
            
            console.log('📊 Dati estratti:', {
                dataKey: dataKey,
                postId: postId,
                nameMatch: nameMatch,
                fullName: checkbox.name
            });
            
            if (dataKey && postId) {
                savePricingMode(checkbox, index, dataKey, postId);
            } else {
                console.warn('❌ Impossibile determinare data_key o post_id per il salvataggio', {
                    dataKey: dataKey,
                    postId: postId,
                    checkboxName: checkbox.name
                });
            }
        } catch (error) {
            console.error('💥 Errore in togglePricingMode:', error);
        }
    };
    
    // Verifica disponibilità variabili globali
    console.log('🔍 Variabili disponibili:', {
        btrPricingMode: typeof btrPricingMode !== 'undefined' ? btrPricingMode : 'NON DEFINITO',
        ajaxurl: typeof ajaxurl !== 'undefined' ? ajaxurl : 'NON DEFINITO'
    });
});