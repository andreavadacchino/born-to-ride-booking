/**
 * Minimal Payment Selection JavaScript
 * Born to Ride Booking v1.0.102
 * 
 * Soft animations and improved interactions
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Cache DOM elements
    const $form = $('#btr-payment-plan-selection');
    const $paymentOptions = $('.btr-payment-option');
    const $paymentInputs = $('input[name="payment_plan"]');
    const $depositConfig = $('#deposit-config');
    const $groupConfig = $('#group-payment-config');
    const $depositSlider = $('#deposit_percentage');
    const $submitBtn = $form.find('button[type="submit"]');
    
    // Get data from template
    const totalAmount = parseFloat($form.data('total')) || 0;
    const totalParticipants = parseInt($form.data('participants')) || 0;
    const quotaPerPerson = totalParticipants > 0 ? totalAmount / totalParticipants : 0;
    
    // Animation configuration
    const animationConfig = {
        duration: 300,
        easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
    };
    
    /**
     * Initialize payment selection
     */
    function initPaymentSelection() {
        // Set initial state
        const selectedOption = $paymentInputs.filter(':checked').val();
        updatePaymentOption(selectedOption);
        
        // Bind events
        bindEvents();
        
        // Initialize tooltips if available
        initTooltips();
        
        // Add accessibility attributes
        enhanceAccessibility();
    }
    
    /**
     * Bind all events
     */
    function bindEvents() {
        // Payment option change
        $paymentInputs.on('change', function() {
            const selectedValue = $(this).val();
            updatePaymentOption(selectedValue);
        });
        
        // Deposit slider
        $depositSlider.on('input', updateDepositValues);
        
        // Group payment participants
        $('.participant-checkbox').on('change', handleParticipantToggle);
        $('.participant-shares').on('input', updateGroupTotals);
        
        // Form submission
        $form.on('submit', handleFormSubmit);
        
        // Smooth scroll to errors
        $(document).on('click', '.error-message', smoothScrollToElement);
        
        // Keyboard navigation
        $paymentOptions.on('keydown', handleKeyboardNavigation);
    }
    
    /**
     * Update payment option UI
     */
    function updatePaymentOption(selectedValue) {
        // Update selected class with animation
        $paymentOptions.each(function() {
            const $option = $(this);
            const isSelected = $option.find('input').val() === selectedValue;
            
            if (isSelected) {
                $option.addClass('selected');
                // Smooth scroll to config if needed
                showConfigSection(selectedValue);
            } else {
                $option.removeClass('selected');
            }
        });
        
        // Hide all configs first
        hideAllConfigs();
        
        // Show relevant config
        if (selectedValue === 'deposit_balance') {
            showDepositConfig();
        } else if (selectedValue === 'group_split') {
            showGroupConfig();
        }
    }
    
    /**
     * Show configuration section with smooth animation
     */
    function showConfigSection(planType) {
        const offset = 100;
        const $target = planType === 'deposit_balance' ? $depositConfig : 
                       planType === 'group_split' ? $groupConfig : null;
        
        if ($target && $target.is(':visible')) {
            setTimeout(() => {
                $('html, body').animate({
                    scrollTop: $target.offset().top - offset
                }, animationConfig.duration);
            }, 400);
        }
    }
    
    /**
     * Hide all configuration sections
     */
    function hideAllConfigs() {
        $depositConfig.slideUp(animationConfig.duration);
        $groupConfig.slideUp(animationConfig.duration);
    }
    
    /**
     * Show deposit configuration
     */
    function showDepositConfig() {
        $depositConfig.slideDown(animationConfig.duration, function() {
            // Update values after animation
            updateDepositValues();
        });
    }
    
    /**
     * Show group payment configuration
     */
    function showGroupConfig() {
        $groupConfig.slideDown(animationConfig.duration, function() {
            // Update totals after animation
            updateGroupTotals();
        });
    }
    
    /**
     * Update deposit values with smooth animation
     */
    function updateDepositValues() {
        const percentage = parseInt($depositSlider.val());
        const deposit = totalAmount * percentage / 100;
        const balance = totalAmount - deposit;
        
        // Update display with animation
        $('.deposit-value').fadeOut(150, function() {
            $(this).text(percentage + '%').fadeIn(150);
        });
        
        $('.deposit-amount').fadeOut(150, function() {
            $(this).text(formatPrice(deposit)).fadeIn(150);
        });
        
        $('.balance-amount').fadeOut(150, function() {
            $(this).text(formatPrice(balance)).fadeIn(150);
        });
        
        // Update ARIA attributes
        $depositSlider.attr('aria-valuenow', percentage);
        
        // Update visual feedback
        updateSliderVisualFeedback(percentage);
    }
    
    /**
     * Update slider visual feedback
     */
    function updateSliderVisualFeedback(percentage) {
        // Create gradient background for filled portion
        const gradient = `linear-gradient(to right, 
            var(--btr-primary) 0%, 
            var(--btr-primary) ${percentage}%, 
            var(--btr-neutral-300) ${percentage}%, 
            var(--btr-neutral-300) 100%)`;
        
        $depositSlider.css('background', gradient);
    }
    
    /**
     * Handle participant checkbox toggle
     */
    function handleParticipantToggle() {
        const $checkbox = $(this);
        const $row = $checkbox.closest('tr');
        const index = $checkbox.data('index');
        const $sharesInput = $('#shares_' + index);
        
        if ($checkbox.is(':checked')) {
            // Enable shares input with animation
            $sharesInput.prop('disabled', false);
            $row.addClass('selected');
            
            // Focus on shares input for better UX
            setTimeout(() => $sharesInput.focus(), 100);
        } else {
            // Disable and reset
            $sharesInput.prop('disabled', true).val(1);
            $row.removeClass('selected');
        }
        
        updateGroupTotals();
    }
    
    /**
     * Update group payment totals
     */
    function updateGroupTotals() {
        let totalShares = 0;
        let totalAmount = 0;
        let selectedCount = 0;
        
        $('.participant-checkbox:checked').each(function() {
            selectedCount++;
            const index = $(this).data('index');
            const shares = parseInt($('#shares_' + index).val()) || 0;
            totalShares += shares;
            totalAmount += shares * quotaPerPerson;
        });
        
        // Update UI with animation
        animateValue($('.total-shares'), totalShares);
        $('.total-amount').fadeOut(150, function() {
            $(this).text(formatPrice(totalAmount)).fadeIn(150);
        });
        
        // Update warnings
        updateGroupWarnings(selectedCount, totalShares);
    }
    
    /**
     * Animate numeric value change
     */
    function animateValue($element, newValue) {
        const currentValue = parseInt($element.text()) || 0;
        const duration = 300;
        const startTime = Date.now();
        
        function update() {
            const now = Date.now();
            const progress = Math.min((now - startTime) / duration, 1);
            const value = Math.floor(currentValue + (newValue - currentValue) * progress);
            
            $element.text(value);
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }
    
    /**
     * Update group payment warnings
     */
    function updateGroupWarnings(selectedCount, totalShares) {
        const $warning = $('#shares-warning');
        const $warningText = $warning.find('.warning-text');
        
        if (selectedCount > 0) {
            if (totalShares < totalParticipants) {
                $warningText.text(
                    `Attenzione: sono state assegnate solo ${totalShares} quote su ${totalParticipants} partecipanti totali.`
                );
                showWarning($warning);
            } else if (totalShares > totalParticipants) {
                $warningText.text(
                    `Attenzione: sono state assegnate ${totalShares} quote ma ci sono solo ${totalParticipants} partecipanti.`
                );
                showWarning($warning);
            } else {
                hideWarning($warning);
            }
        } else {
            hideWarning($warning);
        }
    }
    
    /**
     * Show warning with animation
     */
    function showWarning($warning) {
        if (!$warning.is(':visible')) {
            $warning.slideDown(animationConfig.duration);
        }
    }
    
    /**
     * Hide warning with animation
     */
    function hideWarning($warning) {
        if ($warning.is(':visible')) {
            $warning.slideUp(animationConfig.duration);
        }
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const selectedPlan = $paymentInputs.filter(':checked').val();
        
        // Validate based on plan type
        if (!validatePlan(selectedPlan)) {
            return false;
        }
        
        // Disable submit button
        $submitBtn.prop('disabled', true);
        
        // Add loading state with spinner
        const originalText = $submitBtn.text();
        $submitBtn.html('<span class="spinner"></span> Elaborazione...');
        
        // Submit via AJAX
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    // Success animation before redirect
                    showSuccessAnimation(() => {
                        window.location.href = response.data.redirect_url || window.btrPaymentData.checkoutUrl;
                    });
                } else {
                    showError(response.data.message || 'Si è verificato un errore');
                    resetSubmitButton(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
                showError('Errore di connessione. Riprova.');
                resetSubmitButton(originalText);
            }
        });
    }
    
    /**
     * Validate plan before submission
     */
    function validatePlan(planType) {
        if (planType === 'group_split') {
            const selectedParticipants = $('.participant-checkbox:checked').length;
            
            if (selectedParticipants === 0) {
                showError('Seleziona almeno un partecipante per il pagamento di gruppo.');
                return false;
            }
            
            // Calculate total shares
            let totalShares = 0;
            $('.participant-checkbox:checked').each(function() {
                const index = $(this).data('index');
                totalShares += parseInt($('#shares_' + index).val()) || 0;
            });
            
            if (totalShares !== totalParticipants) {
                return confirm('Le quote assegnate non corrispondono al numero totale di partecipanti. Vuoi continuare comunque?');
            }
        }
        
        return true;
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        // Remove existing errors
        $('.payment-error').remove();
        
        // Create error element
        const $error = $('<div>', {
            class: 'payment-error warning-message',
            html: `<span class="warning-icon">⚠️</span><span>${message}</span>`,
            css: { display: 'none' }
        });
        
        // Insert before form actions
        $('.btr-form-actions').before($error);
        
        // Show with animation
        $error.slideDown(animationConfig.duration);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $error.slideUp(animationConfig.duration, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Show success animation
     */
    function showSuccessAnimation(callback) {
        const $overlay = $('<div>', {
            class: 'success-overlay',
            html: '<div class="success-icon">✓</div><div class="success-message">Reindirizzamento al checkout...</div>',
            css: {
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                background: 'rgba(255, 255, 255, 0.95)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexDirection: 'column',
                zIndex: 9999,
                opacity: 0
            }
        });
        
        $('body').append($overlay);
        
        $overlay.animate({ opacity: 1 }, 300, function() {
            setTimeout(callback, 1000);
        });
    }
    
    /**
     * Reset submit button
     */
    function resetSubmitButton(originalText) {
        $submitBtn.prop('disabled', false).text(originalText);
    }
    
    /**
     * Format price for display
     */
    function formatPrice(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }
    
    /**
     * Smooth scroll to element
     */
    function smoothScrollToElement(e) {
        e.preventDefault();
        const target = $(this).data('target');
        if (target) {
            const $target = $(target);
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100
                }, animationConfig.duration);
            }
        }
    }
    
    /**
     * Handle keyboard navigation
     */
    function handleKeyboardNavigation(e) {
        const $option = $(this);
        const $input = $option.find('input[type="radio"]');
        
        // Space or Enter to select
        if (e.keyCode === 32 || e.keyCode === 13) {
            e.preventDefault();
            $input.prop('checked', true).trigger('change');
        }
        
        // Arrow keys to navigate
        if (e.keyCode === 38 || e.keyCode === 40) {
            e.preventDefault();
            const $options = $('.btr-payment-option');
            const currentIndex = $options.index($option);
            let newIndex;
            
            if (e.keyCode === 38) { // Up
                newIndex = currentIndex - 1;
                if (newIndex < 0) newIndex = $options.length - 1;
            } else { // Down
                newIndex = currentIndex + 1;
                if (newIndex >= $options.length) newIndex = 0;
            }
            
            $options.eq(newIndex).focus();
        }
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Add tooltips for important elements
        $depositSlider.attr('title', 'Trascina per regolare la percentuale di caparra');
        $('.participant-shares').attr('title', 'Numero di quote da pagare');
    }
    
    /**
     * Enhance accessibility
     */
    function enhanceAccessibility() {
        // Add tabindex to payment options
        $paymentOptions.attr('tabindex', '0');
        
        // Add role attributes
        $('.btr-payment-options').attr('role', 'radiogroup');
        $paymentOptions.attr('role', 'radio');
        
        // Update aria-checked on change
        $paymentInputs.on('change', function() {
            $paymentOptions.attr('aria-checked', 'false');
            $(this).closest('.btr-payment-option').attr('aria-checked', 'true');
        });
        
        // Set initial aria-checked
        $paymentOptions.each(function() {
            const isChecked = $(this).find('input').is(':checked');
            $(this).attr('aria-checked', isChecked ? 'true' : 'false');
        });
    }
    
    // Initialize on document ready
    initPaymentSelection();
    
    // Add custom CSS for spinner
    if (!$('#payment-selection-spinner-css').length) {
        $('head').append(`
            <style id="payment-selection-spinner-css">
                .spinner {
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    border-top-color: white;
                    animation: spin 0.8s linear infinite;
                    vertical-align: middle;
                    margin-right: 8px;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                .success-overlay .success-icon {
                    width: 80px;
                    height: 80px;
                    background: #52c41a;
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 48px;
                    margin-bottom: 20px;
                    animation: scaleIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }
                .success-overlay .success-message {
                    font-size: 18px;
                    color: #333;
                    font-weight: 500;
                }
                .participant-row.selected {
                    background: rgba(0, 151, 197, 0.05);
                }
                .payment-error {
                    margin: 20px 0;
                }
            </style>
        `);
    }
});