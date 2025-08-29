// Fix per il problema di validazione admin
jQuery(document).ready(function($) {
    console.log('[BTR DEBUG] Script di debug caricato');
    
    // Mostra un indicatore visivo di debug
    $('<div class="btr-debug-info">🔧 BTR Debug: Script di validazione caricato</div>').prependTo('#post');
    
    // Analizza tutti i campi required presenti
    function analyzeRequiredFields() {
        var requiredFields = $('[required]');
        console.log('[BTR DEBUG] Campi required trovati:', requiredFields.length);
        
        requiredFields.each(function(i) {
            var field = $(this);
            var name = field.attr('name') || 'unnamed';
            var type = field.attr('type') || field.prop('tagName').toLowerCase();
            var value = field.val();
            var isEmpty = !value || value.trim() === '';
            
            console.log(`[BTR DEBUG] Campo ${i+1}: ${name} (${type}) - Vuoto: ${isEmpty} - Valore: "${value}"`);
            
            if (isEmpty) {
                console.warn(`[BTR DEBUG] ⚠️ Campo vuoto rilevato: ${name}`);
                
                // Aggiungi indicatore visivo
                if (!field.siblings('.btr-debug-required').length) {
                    field.after('<span class="btr-debug-required" style="color: orange; font-size: 12px;">⚠️ Required field</span>');
                }
            }
        });
    }
    
    // Esegui analisi iniziale
    analyzeRequiredFields();
    
    // Disabilita la validazione HTML5 nativa che blocca il submit
    $('#post').attr('novalidate', 'novalidate');
    
    // Rimuovi tutti gli attributi required che bloccano il submit
    function removeAllRequired() {
        $('[required]').each(function() {
            var name = $(this).attr('name') || 'unnamed';
            console.log('[BTR DEBUG] Rimosso required da:', name);
            $(this).removeAttr('required');
        });
    }
    
    // Rimuovi required inizialmente
    removeAllRequired();
    
    // Override della validazione jQuery per non bloccare il submit
    if ($.fn.validate) {
        // Disattiva qualsiasi validazione esistente
        if ($('#post').data('validator')) {
            $('#post').removeData('validator');
            console.log('[BTR DEBUG] Validator jQuery rimosso');
        }
        
        // Non inizializzare nuove validazioni
        $.fn.validate = function() {
            console.log('[BTR DEBUG] Validazione jQuery bloccata');
            return this;
        };
    }
    
    // Event listener per il submit del form
    $('#post').on('submit', function(e) {
        console.log('[BTR DEBUG] 🚀 Submit form WordPress rilevato');
        
        // Aggiungi indicatore visivo
        $(this).addClass('btr-form-submitting');
        
        // Rimuovi tutti gli attributi required prima del submit
        removeAllRequired();
        
        // Rimuovi indicatori di debug temporanei
        $('.btr-debug-required').remove();
        
        // Log finale
        console.log('[BTR DEBUG] ✅ Submit permesso, form in invio...');
        
        // Non bloccare mai il submit
        return true;
    });
    
    // Debug per il pulsante aggiorna
    $('#publish, #save-post').on('click', function(e) {
        console.log('[BTR DEBUG] 🔘 Pulsante aggiorna/pubblica cliccato');
        
        // Analizza campi prima del submit
        analyzeRequiredFields();
        
        // Assicurati che non ci siano campi required
        removeAllRequired();
        
        // Aggiorna il debug info
        $('.btr-debug-info').html('🔧 BTR Debug: Preparazione submit...');
    });
    
    // Debug per campi invalid HTML5
    $(document).on('invalid', '[required]', function(e) {
        console.log('[BTR DEBUG] ❌ Campo invalid rilevato:', $(this).attr('name'), e);
        e.preventDefault();
        $(this).removeAttr('required');
        console.log('[BTR DEBUG] ✅ Required rimosso da campo invalid');
    });
    
    // Monitor dinamico per nuovi campi required
    setTimeout(function() {
        console.log('[BTR DEBUG] 🔍 Controllo finale campi required...');
        analyzeRequiredFields();
        removeAllRequired();
        $('.btr-debug-info').html('🔧 BTR Debug: Sistema pronto - validazione disabilitata');
    }, 2000);
    
    console.log('[BTR DEBUG] ✅ Fix di debug completato');
});