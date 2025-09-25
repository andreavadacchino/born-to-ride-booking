jQuery(document).ready(function ($) {
    const form = $('#btr-anagrafici-form');
    
    if (form.length) {
        form.on('submit', function (e) {
            e.preventDefault();
            
            const responseElement = $('#btr-anagrafici-response');
            // Aggiorna i campi nascosti con i totali del riepilogo
            const roomTotalField = $('#btr-summary-room-total-field');
            const insuranceTotalField = $('#btr-summary-insurance-total-field');
            const extraTotalField = $('#btr-summary-extra-total-field');
            const grandTotalField = $('#btr-summary-grand-total-field');

            const roomTotalEl = $('#btr-summary-package-price');
            const insuranceTotalEl = $('#btr-summary-insurance-total');
            const extraTotalEl = $('#btr-summary-extra-total');
            const grandTotalEl = $('#btr-summary-grand-total');

            const parseMoney = text => {
                if (!text) { return 0; }
                return parseFloat(
                    text
                        .toString()
                        .replace(/\s|€|&nbsp;/g, '')
                        .replace(/\./g, '')
                        .replace(',', '.')
                ) || 0;
            };

            if (roomTotalField.length && roomTotalEl.length) {
                roomTotalField.val(parseMoney(roomTotalEl.text()));
            }
            if (insuranceTotalField.length && insuranceTotalEl.length) {
                insuranceTotalField.val(parseMoney(insuranceTotalEl.text()));
            }
            if (extraTotalField.length && extraTotalEl.length) {
                extraTotalField.val(parseMoney(extraTotalEl.text()));
            }
            if (grandTotalField.length && grandTotalEl.length) {
                grandTotalField.val(parseMoney(grandTotalEl.text()));
            }

            const formData = $(this).serialize(); // Serializza il form
            
            // Aggiungiamo l'action e il nonce al form serializzato
            const ajaxData = formData + 
                '&action=btr_save_anagrafici' + 
                '&btr_update_anagrafici_nonce_field=' + btr_anagrafici.nonce;
            
            console.log('Dati inviati:', ajaxData);
            
            $.ajax({
                url: btr_anagrafici.ajax_url,
                method: 'POST',
                data: ajaxData,
                beforeSend: function () {
                    responseElement.html('<p style="color: blue;">' + (btr_anagrafici.loading_message || 'Caricamento in corso...') + '</p>');
                },
                success: function (response) {
                    console.log('Response:', response);
                    if (response.success) {
                        responseElement.html('<p style="color: green;">' + response.data.message + '</p>');
                        // Se c'è un redirect_url lo utilizziamo
                        if (response.data && response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        }
                    } else {
                        console.error('Errore del server:', response);
                        responseElement.html(
                            '<p style="color: red;">' + (response.data && response.data.message ? response.data.message : 'Errore generico durante il salvataggio.') + '</p>'
                        );
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Errore AJAX:', error);
                    console.error('Status:', xhr.status);
                    console.error('Response:', xhr.responseText);
                    responseElement.html('<p style="color: red;">Errore nella comunicazione con il server. Riprova più tardi.</p>');
                }
            });
        });
    }
    
    // Countdown timer
    if (form.length && form.data('remaining-time') > 0) {
        const timerElement = $('#btr-countdown-timer');
        let remainingTime = parseInt(form.data('remaining-time'), 10);
        
        function updateCountdown() {
            if (remainingTime <= 0) {
                timerElement.text(btr_anagrafici.tempo_scaduto);
                form.hide();
                return;
            }
            
            const hours = Math.floor(remainingTime / 3600);
            const minutes = Math.floor((remainingTime % 3600) / 60);
            const seconds = remainingTime % 60;
            
            timerElement.text(`${hours}h ${minutes}m ${seconds}s`);
            remainingTime--;
            
            setTimeout(updateCountdown, 1000);
        }
        
        updateCountdown();
    }
});
