/**
 * Modern Payment Selection JavaScript
 * Born to Ride Booking v1.0.99
 * 
 * Progressive enhancement for payment selection page
 */

(function($) {
    'use strict';

    // Utility function per debouncing
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Payment Selection Manager
    class PaymentSelectionManager {
        constructor() {
            this.form = $('#btr-payment-plan-selection');
            this.paymentOptions = $('.btr-payment-option');
            this.depositConfig = $('#deposit-config');
            this.groupConfig = $('#group-payment-config');
            this.depositSlider = $('#deposit_percentage');
            this.totalAmount = parseFloat(this.form.data('total') || window.btrPaymentData?.totalAmount || 0);
            this.totalParticipants = parseInt(this.form.data('participants') || window.btrPaymentData?.totalParticipants || 0);
            this.quotaPerPerson = this.totalParticipants > 0 ? this.totalAmount / this.totalParticipants : 0;
            
            // Lazy loading flags
            this.depositConfigLoaded = false;
            this.groupConfigLoaded = false;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeAnimations();
            // Rimuovi inizializzazione eager del deposito
            // this.updateDepositDisplay(); // Ora fatto in lazy loading
            this.enhanceAccessibility();
        }

        bindEvents() {
            // Payment plan selection
            $('input[name="payment_plan"]').on('change', this.handlePlanChange.bind(this));
            
            // Deposit slider con debouncing per performance
            this.depositSlider.on('input', debounce(this.handleDepositChange.bind(this), 300));
            
            // Group payment participants
            $('.participant-checkbox').on('change', this.handleParticipantToggle.bind(this));
            $('.participant-shares').on('input', debounce(this.handleSharesChange.bind(this), 200));
            
            // Form submission
            this.form.on('submit', this.handleFormSubmit.bind(this));
            
            // Smooth scroll for focus
            $('input, select, textarea').on('focus', function() {
                const $this = $(this);
                const offset = $this.offset().top - 100;
                if (offset < $(window).scrollTop()) {
                    $('html, body').animate({ scrollTop: offset }, 300);
                }
            });
        }

        handlePlanChange(e) {
            const selectedPlan = $(e.target).val();
            
            // Update visual state
            this.paymentOptions.removeClass('selected');
            $(e.target).closest('.btr-payment-option').addClass('selected');
            
            // Animate configurations
            this.animateConfigurationChange(selectedPlan);
            
            // Track selection
            this.trackEvent('Payment Plan Selected', { plan: selectedPlan });
        }

        animateConfigurationChange(plan) {
            const configs = {
                'deposit_balance': this.depositConfig,
                'group_split': this.groupConfig
            };
            
            // Hide all configs first
            $('.deposit-config, .group-payment-config').slideUp(300);
            
            // Show selected config with lazy loading
            if (plan === 'deposit_balance' && !this.depositConfigLoaded) {
                this.lazyLoadDepositConfig(() => {
                    this.showConfig(configs[plan]);
                });
            } else if (plan === 'group_split' && !this.groupConfigLoaded) {
                this.lazyLoadGroupConfig(() => {
                    this.showConfig(configs[plan]);
                });
            } else if (configs[plan]) {
                this.showConfig(configs[plan]);
            }
        }
        
        showConfig(configElement) {
            setTimeout(() => {
                configElement.slideDown(400, 'easeOutCubic');
                
                // Focus on first input in the configuration
                const firstInput = configElement.find('input:not([type="hidden"]):first');
                if (firstInput.length) {
                    firstInput.focus();
                }
            }, 320);
        }
        
        lazyLoadDepositConfig(callback) {
            // Se già caricato, esegui callback
            if (this.depositConfigLoaded) {
                callback();
                return;
            }
            
            // Inizializza slider e display solo quando necessario
            this.updateDepositDisplay();
            this.updateSliderProgress(parseInt(this.depositSlider.val()));
            this.depositConfigLoaded = true;
            
            if (callback) callback();
        }
        
        lazyLoadGroupConfig(callback) {
            // Se già caricato, esegui callback
            if (this.groupConfigLoaded) {
                callback();
                return;
            }
            
            // Inizializza calcoli gruppo solo quando necessario
            this.updateGroupTotals();
            this.groupConfigLoaded = true;
            
            if (callback) callback();
        }

        handleDepositChange(e) {
            const percentage = parseInt($(e.target).val());
            const deposit = this.totalAmount * percentage / 100;
            const balance = this.totalAmount - deposit;
            
            // Update display with animation
            this.animateValue('.deposit-value', percentage + '%');
            this.animateValue('.deposit-amount', this.formatPrice(deposit));
            this.animateValue('.balance-amount', this.formatPrice(balance));
            
            // Update aria attributes
            $(e.target).attr('aria-valuenow', percentage);
            
            // Visual feedback on slider
            this.updateSliderProgress(percentage);
        }

        updateSliderProgress(percentage) {
            const slider = this.depositSlider[0];
            if (slider) {
                const progress = (percentage - slider.min) / (slider.max - slider.min) * 100;
                slider.style.background = `linear-gradient(to right, var(--btr-primary) ${progress}%, var(--btr-border) ${progress}%)`;
            }
        }

        handleParticipantToggle(e) {
            const checkbox = $(e.target);
            const index = checkbox.data('index');
            const sharesInput = $(`#shares_${index}`);
            const row = checkbox.closest('tr');
            
            if (checkbox.is(':checked')) {
                sharesInput.prop('disabled', false);
                row.addClass('selected');
                this.animateParticipantSelection(row, true);
            } else {
                sharesInput.prop('disabled', true).val(1);
                row.removeClass('selected');
                this.animateParticipantSelection(row, false);
            }
            
            this.updateGroupTotals();
        }

        animateParticipantSelection(row, isSelected) {
            if (isSelected) {
                row.css({ opacity: 0.5, transform: 'scale(0.98)' })
                   .animate({ opacity: 1 }, 300)
                   .css({ transform: 'scale(1)' });
            } else {
                row.css({ opacity: 1 })
                   .animate({ opacity: 0.8 }, 200)
                   .css({ transform: 'scale(1)' });
            }
        }

        handleSharesChange(e) {
            const input = $(e.target);
            const shares = parseInt(input.val()) || 0;
            const amount = shares * this.quotaPerPerson;
            const amountCell = input.closest('tr').find('.participant-amount strong');
            
            this.animateValue(amountCell, this.formatPrice(amount));
            this.updateGroupTotals();
        }

        updateGroupTotals() {
            let totalShares = 0;
            let totalAmount = 0;
            let selectedCount = 0;
            
            $('.participant-checkbox:checked').each((index, checkbox) => {
                selectedCount++;
                const participantIndex = $(checkbox).data('index');
                const shares = parseInt($(`#shares_${participantIndex}`).val()) || 0;
                totalShares += shares;
                totalAmount += shares * this.quotaPerPerson;
            });
            
            // Animate totals update
            this.animateValue('.total-shares', totalShares);
            this.animateValue('.total-amount', this.formatPrice(totalAmount));
            this.animateValue('.selected-participants', selectedCount);
            
            // Update warning message
            this.updateSharesWarning(selectedCount, totalShares);
        }

        updateSharesWarning(selectedCount, totalShares) {
            const warningEl = $('#shares-warning');
            const warningText = warningEl.find('.warning-text');
            
            if (selectedCount > 0) {
                if (totalShares < this.totalParticipants) {
                    warningText.text(`Attenzione: sono state assegnate solo ${totalShares} quote su ${this.totalParticipants} partecipanti totali.`);
                    this.showWarning(warningEl);
                } else if (totalShares > this.totalParticipants) {
                    warningText.text(`Attenzione: sono state assegnate ${totalShares} quote ma ci sono solo ${this.totalParticipants} partecipanti.`);
                    this.showWarning(warningEl);
                } else {
                    this.hideWarning(warningEl);
                }
            } else {
                this.hideWarning(warningEl);
            }
        }

        showWarning(element) {
            element.slideDown(300).addClass('shake');
            setTimeout(() => element.removeClass('shake'), 500);
        }

        hideWarning(element) {
            element.slideUp(300);
        }

        handleFormSubmit(e) {
            e.preventDefault();
            
            const selectedPlan = $('input[name="payment_plan"]:checked').val();
            
            // Validate based on selected plan
            if (!this.validateForm(selectedPlan)) {
                return false;
            }
            
            // Show loading state
            this.showLoadingState();
            
            // Submit form via AJAX
            this.submitForm();
        }

        validateForm(plan) {
            if (plan === 'group_split') {
                const selectedParticipants = $('.participant-checkbox:checked').length;
                
                if (selectedParticipants === 0) {
                    this.showAlert('Seleziona almeno un partecipante per il pagamento di gruppo.', 'error');
                    return false;
                }
                
                // Check total shares
                let totalShares = 0;
                $('.participant-checkbox:checked').each((index, checkbox) => {
                    const participantIndex = $(checkbox).data('index');
                    totalShares += parseInt($(`#shares_${participantIndex}`).val()) || 0;
                });
                
                if (totalShares !== this.totalParticipants) {
                    const message = `Le quote assegnate (${totalShares}) non corrispondono al numero totale di partecipanti (${this.totalParticipants}). Vuoi continuare comunque?`;
                    return confirm(message);
                }
            }
            
            return true;
        }

        showLoadingState() {
            const submitBtn = this.form.find('button[type="submit"]');
            submitBtn.prop('disabled', true)
                     .html('<span class="spinner"></span> Elaborazione...')
                     .addClass('loading');
            
            // Add loading overlay
            $('body').append(`
                <div class="btr-loading-overlay">
                    <div class="spinner-container">
                        <div class="spinner"></div>
                        <p>Elaborazione in corso...</p>
                    </div>
                </div>
            `);
            
            $('.btr-loading-overlay').fadeIn(200);
        }

        hideLoadingState() {
            const submitBtn = this.form.find('button[type="submit"]');
            submitBtn.prop('disabled', false)
                     .html('Procedi al Checkout')
                     .removeClass('loading');
            
            $('.btr-loading-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }

        submitForm() {
            const formData = this.form.serialize();
            
            $.ajax({
                url: this.form.attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: this.handleSubmitSuccess.bind(this),
                error: this.handleSubmitError.bind(this)
            });
        }

        handleSubmitSuccess(response) {
            if (response.success) {
                this.trackEvent('Payment Plan Created', { 
                    plan: $('input[name="payment_plan"]:checked').val() 
                });
                
                // Redirect with animation
                this.showSuccessMessage('Piano di pagamento creato con successo!');
                
                setTimeout(() => {
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        window.location.href = window.btrPaymentData?.checkoutUrl || '/checkout/';
                    }
                }, 1000);
            } else {
                this.hideLoadingState();
                this.showAlert(response.data.message || 'Si è verificato un errore', 'error');
            }
        }

        handleSubmitError(xhr, status, error) {
            this.hideLoadingState();
            this.showAlert('Errore di connessione. Riprova.', 'error');
            console.error('Form submission error:', error);
        }

        showSuccessMessage(message) {
            const successOverlay = $(`
                <div class="btr-success-overlay">
                    <div class="success-content">
                        <div class="success-icon">✓</div>
                        <h3>${message}</h3>
                        <p>Reindirizzamento in corso...</p>
                    </div>
                </div>
            `);
            
            $('body').append(successOverlay);
            successOverlay.fadeIn(300);
        }

        showAlert(message, type = 'info') {
            const alertClass = type === 'error' ? 'btr-notification-error' : 'btr-notification-info';
            const alert = $(`
                <div class="btr-notification ${alertClass}">
                    ${message}
                </div>
            `);
            
            $('body').append(alert);
            alert.fadeIn(300);
            
            setTimeout(() => {
                alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        formatPrice(amount) {
            return new Intl.NumberFormat('it-IT', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }

        animateValue(element, value) {
            const $element = $(element);
            $element.fadeOut(100, function() {
                $(this).text(value).fadeIn(100);
            });
        }

        updateDepositDisplay() {
            const percentage = parseInt(this.depositSlider.val());
            this.handleDepositChange({ target: this.depositSlider[0] });
        }

        initializeAnimations() {
            // Add intersection observer for scroll animations
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animated');
                        }
                    });
                }, { threshold: 0.1 });
                
                document.querySelectorAll('.btr-payment-option, .summary-section').forEach(el => {
                    observer.observe(el);
                });
            }
        }

        enhanceAccessibility() {
            // Add keyboard navigation for payment options
            this.paymentOptions.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
                }
            });
            
            // Announce changes to screen readers
            $('<div class="sr-only" aria-live="polite" aria-atomic="true" id="payment-announcer"></div>')
                .appendTo('body');
        }

        trackEvent(eventName, data) {
            // Analytics tracking
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, data);
            }
            
            // Console logging for debugging
            console.log('Track Event:', eventName, data);
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#btr-payment-plan-selection').length) {
            window.paymentManager = new PaymentSelectionManager();
        }
    });

    // Add smooth easing
    $.extend($.easing, {
        easeOutCubic: function(x, t, b, c, d) {
            return c * ((t = t / d - 1) * t * t + 1) + b;
        }
    });

})(jQuery);