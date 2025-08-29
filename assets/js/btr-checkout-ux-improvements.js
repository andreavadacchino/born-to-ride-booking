/**
 * BTR Checkout UX Improvements v1.0.211
 * Miglioramenti JavaScript per accessibilità e usabilità
 */

(function($) {
    'use strict';

    /**
     * BTR Checkout UX Manager
     * Gestisce tutti i miglioramenti UX del checkout
     */
    class BTRCheckoutUX {
        constructor() {
            this.init();
            this.currentStep = 1;
            this.totalSteps = 3;
            this.formData = {};
        }

        init() {
            this.setupProgressIndicator();
            this.improveAccessibility();
            this.enhanceFormValidation();
            this.addKeyboardNavigation();
            this.improveMobileExperience();
            this.setupAutoSave();
        }

        /**
         * Progress Indicator migliorato
         */
        setupProgressIndicator() {
            // Aggiungi progress bar se non esiste
            if (!$('.btr-progress-container').length) {
                const progressHTML = `
                    <div class="btr-progress-wrapper" role="progressbar" 
                         aria-valuenow="${this.currentStep}" 
                         aria-valuemin="1" 
                         aria-valuemax="${this.totalSteps}"
                         aria-label="Progresso checkout">
                        <div class="btr-progress-container">
                            <div class="btr-progress-bar" style="width: 33%"></div>
                        </div>
                        <div class="btr-progress-steps">
                            <span class="btr-step active">1. Dati personali</span>
                            <span class="btr-step">2. Assicurazioni</span>
                            <span class="btr-step">3. Riepilogo</span>
                        </div>
                    </div>
                `;
                $('.btr-form').prepend(progressHTML);
            }

            this.updateProgress();
        }

        updateProgress() {
            const progress = (this.currentStep / this.totalSteps) * 100;
            $('.btr-progress-bar').css('width', `${progress}%`);
            $('.btr-progress-wrapper').attr('aria-valuenow', this.currentStep);
            
            // Update step indicators
            $('.btr-step').removeClass('active completed');
            for (let i = 1; i <= this.currentStep; i++) {
                $(`.btr-step:nth-child(${i})`).addClass(i < this.currentStep ? 'completed' : 'active');
            }
        }

        /**
         * Miglioramenti Accessibilità
         */
        improveAccessibility() {
            // Aggiungi ARIA labels agli accordion
            $('.btr-person-card').each((index, card) => {
                const $card = $(card);
                const $title = $card.find('.person-title');
                const $content = $card.find('.btr-person-content');
                const cardId = `person-card-${index}`;
                
                $title.attr({
                    'role': 'button',
                    'tabindex': '0',
                    'aria-expanded': $card.hasClass('expanded') ? 'true' : 'false',
                    'aria-controls': `${cardId}-content`,
                    'id': `${cardId}-title`
                });
                
                $content.attr({
                    'id': `${cardId}-content`,
                    'aria-labelledby': `${cardId}-title`,
                    'role': 'region'
                });
            });

            // Aggiungi live region per messaggi
            if (!$('#btr-live-region').length) {
                $('body').append('<div id="btr-live-region" class="btr-sr-only" aria-live="polite" aria-atomic="true"></div>');
            }

            // Migliora labels dei form
            $('input, select, textarea').each((i, field) => {
                const $field = $(field);
                const $label = $field.siblings('label');
                
                if ($label.length && !$field.attr('id')) {
                    const fieldId = `btr-field-${i}`;
                    $field.attr('id', fieldId);
                    $label.attr('for', fieldId);
                }
                
                // Aggiungi aria-required per campi obbligatori
                if ($field.prop('required')) {
                    $field.attr('aria-required', 'true');
                }
            });

            // Skip link per navigazione rapida
            if (!$('.btr-skip-link').length) {
                $('.btr-form').prepend(
                    '<a href="#btr-summary-wrapper" class="btr-skip-link">Vai al riepilogo</a>'
                );
            }
        }

        /**
         * Validazione Form Migliorata
         */
        enhanceFormValidation() {
            const self = this;
            
            // Validazione real-time con debounce
            $('input[required], select[required]').on('blur', function() {
                self.validateField($(this));
            });

            // Validazione al cambio per select
            $('select[required]').on('change', function() {
                self.validateField($(this));
            });

            // Email validation migliorata
            $('input[type="email"]').on('blur', function() {
                const $field = $(this);
                const email = $field.val();
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                
                if (email && !isValid) {
                    self.showFieldError($field, 'Inserisci un indirizzo email valido');
                } else {
                    self.clearFieldError($field);
                }
            });

            // Codice fiscale validation
            $('input[name*="codice_fiscale"]').on('blur', function() {
                const $field = $(this);
                const cf = $field.val().toUpperCase();
                
                if (cf && !self.isValidCodiceFiscale(cf)) {
                    self.showFieldError($field, 'Codice fiscale non valido');
                } else {
                    self.clearFieldError($field);
                }
            });
        }

        validateField($field) {
            const value = $field.val();
            const isRequired = $field.prop('required');
            
            if (isRequired && !value) {
                this.showFieldError($field, 'Questo campo è obbligatorio');
                return false;
            }
            
            this.clearFieldError($field);
            return true;
        }

        showFieldError($field, message) {
            const $group = $field.closest('.btr-field-group');
            
            // Rimuovi errori esistenti
            this.clearFieldError($field);
            
            // Aggiungi classe errore
            $group.addClass('has-error');
            $field.attr('aria-invalid', 'true');
            
            // Aggiungi messaggio errore
            const errorId = `error-${$field.attr('id') || Math.random().toString(36).substr(2, 9)}`;
            const $error = $(`<div class="btr-field-error" id="${errorId}" role="alert">${message}</div>`);
            $field.after($error);
            $field.attr('aria-describedby', errorId);
            
            // Announce to screen readers
            this.announceMessage(message, 'assertive');
        }

        clearFieldError($field) {
            const $group = $field.closest('.btr-field-group');
            $group.removeClass('has-error');
            $field.attr('aria-invalid', 'false');
            $field.removeAttr('aria-describedby');
            $field.siblings('.btr-field-error').remove();
        }

        isValidCodiceFiscale(cf) {
            if (cf.length !== 16) return false;
            
            // Basic pattern check
            const pattern = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/;
            return pattern.test(cf);
        }

        /**
         * Navigazione da Tastiera
         */
        addKeyboardNavigation() {
            // Accordion keyboard navigation
            $('.person-title').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // Room button keyboard navigation
            $('.btr-room-button').attr('tabindex', '0').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // Escape key to close dropdowns
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.btr-dropdown.open').removeClass('open');
                }
            });
        }

        /**
         * Mobile Experience Improvements
         */
        improveMobileExperience() {
            // Detect mobile
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                $('body').addClass('btr-mobile');
                
                // Smooth scroll to errors
                $(document).on('click', '.btr-field-error', function() {
                    const $field = $(this).siblings('input, select');
                    if ($field.length) {
                        $field.focus();
                        $('html, body').animate({
                            scrollTop: $field.offset().top - 100
                        }, 500);
                    }
                });
                
                // Sticky summary on mobile
                this.setupStickySummary();
            }
        }

        setupStickySummary() {
            const $summary = $('.btr-summary-grand-total');
            if (!$summary.length) return;
            
            // Clone summary for sticky footer
            const $stickyTotal = $summary.clone()
                .addClass('btr-sticky-total')
                .attr('aria-hidden', 'true'); // Hide from screen readers (duplicate)
            
            $('body').append($stickyTotal);
            
            // Update sticky total when main total changes
            const observer = new MutationObserver(() => {
                $stickyTotal.find('#btr-summary-grand-total')
                    .text($summary.find('#btr-summary-grand-total').text());
            });
            
            observer.observe($summary[0], { childList: true, subtree: true });
        }

        /**
         * Auto-save functionality
         */
        setupAutoSave() {
            const self = this;
            let saveTimeout;
            
            // Save on input change
            $('input, select, textarea').on('change input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    self.saveFormData();
                }, 2000); // Save dopo 2 secondi di inattività
            });
            
            // Save on room button selection
            $(document).on('click', '.btr-room-button', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    self.saveFormData();
                }, 500); // Save più veloce per selezioni immediate
            });

            // Load saved data on init
            this.loadFormData();
            
            // Save before page unload
            $(window).on('beforeunload', () => {
                this.saveFormData();
            });
        }

        saveFormData() {
            const formData = {};
            
            // Collect all form data
            $('.btr-form').find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                if (name) {
                    if (type === 'checkbox' || type === 'radio') {
                        if ($field.is(':checked')) {
                            formData[name] = $field.val();
                        }
                    } else {
                        formData[name] = $field.val();
                    }
                }
            });
            
            // Collect room selections (custom elements)
            $('.btr-room-button.selected').each(function() {
                const $room = $(this);
                const personIndex = $room.closest('.btr-person-card').find('.person-title').attr('data-person-index') || 
                                   $room.closest('.btr-person-card').index();
                const roomData = {
                    roomId: $room.attr('data-room-id'),
                    roomType: $room.attr('data-room-type'),
                    capacita: $room.attr('data-capacita'),
                    supplemento: $room.attr('data-supplemento')
                };
                formData[`room_selection_${personIndex}`] = JSON.stringify(roomData);
            });
            
            // Save to localStorage
            try {
                localStorage.setItem('btr_checkout_data', JSON.stringify(formData));
                this.showAutoSaveIndicator();
            } catch (e) {
                console.error('Failed to save form data:', e);
            }
        }

        loadFormData() {
            try {
                const savedData = localStorage.getItem('btr_checkout_data');
                if (!savedData) return;
                
                const formData = JSON.parse(savedData);
                
                // Restore form data
                Object.keys(formData).forEach(name => {
                    // Handle room selections
                    if (name.startsWith('room_selection_')) {
                        try {
                            const roomData = JSON.parse(formData[name]);
                            const personIndex = name.replace('room_selection_', '');
                            
                            // Find the person card and room button
                            const $personCard = $(`.btr-person-card`).eq(personIndex);
                            const $roomButton = $personCard.find(`[data-room-id="${roomData.roomId}"]`);
                            
                            if ($roomButton.length) {
                                // Remove selected class from other buttons
                                $personCard.find('.btr-room-button').removeClass('selected');
                                // Add selected class to the saved room
                                $roomButton.addClass('selected');
                            }
                        } catch (e) {
                            console.warn('[BTR UX] Errore ripristino camera:', e);
                        }
                        return;
                    }
                    
                    // Handle regular form fields
                    const $field = $(`[name="${name}"]`);
                    if ($field.length) {
                        const type = $field.attr('type');
                        
                        if (type === 'checkbox' || type === 'radio') {
                            $field.filter(`[value="${formData[name]}"]`).prop('checked', true);
                        } else {
                            $field.val(formData[name]);
                        }
                    }
                });
                
                this.announceMessage('Dati precedenti ripristinati', 'polite');
            } catch (e) {
                console.error('Failed to load saved data:', e);
            }
        }

        showAutoSaveIndicator() {
            // Show save indicator
            if (!$('.btr-autosave-indicator').length) {
                $('body').append('<div class="btr-autosave-indicator">Salvato automaticamente</div>');
            }
            
            const $indicator = $('.btr-autosave-indicator');
            $indicator.addClass('show');
            
            setTimeout(() => {
                $indicator.removeClass('show');
            }, 2000);
        }

        /**
         * Helper function to announce messages to screen readers
         */
        announceMessage(message, priority = 'polite') {
            const $liveRegion = $('#btr-live-region');
            $liveRegion.attr('aria-live', priority).text(message);
            
            // Clear after announcement
            setTimeout(() => {
                $liveRegion.empty();
            }, 1000);
        }

        /**
         * Smooth scroll helper
         */
        smoothScrollTo(target, offset = 100) {
            $('html, body').animate({
                scrollTop: $(target).offset().top - offset
            }, 500);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize on checkout page (btr-anagrafici-form is the correct ID)
        if ($('#btr-anagrafici-form').length || $('.btr-form').length) {
            window.btrCheckoutUX = new BTRCheckoutUX();
            console.log('[BTR UX] Checkout UX miglioramenti inizializzati');
        }
    });

})(jQuery);