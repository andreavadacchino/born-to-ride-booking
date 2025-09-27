/**
 * BTR Security Enhancement
 * Aggiunge protezioni lato client per prevenire attacchi automatizzati
 * 
 * @since 1.0.216
 */

(function($) {
    'use strict';
    
    // Honeypot e timing protection
    function addSecurityFields() {
        // Aggiungi honeypot field (nascosto con CSS)
        var honeypotHtml = '<div style="position:absolute;left:-9999px;visibility:hidden;">' +
                          '<input type="text" name="btr_honeypot" value="" tabindex="-1" autocomplete="off">' +
                          '<input type="text" name="calc_honeypot" value="" tabindex="-1" autocomplete="off">' +
                          '</div>';
        
        // Form timing field
        var formTimeHtml = '<input type="hidden" name="btr_form_time" value="' + Math.floor(Date.now() / 1000) + '">';
        
        // Aggiungi ai form BTR
        $('.btr-booking-form, form[data-btr-form]').each(function() {
            var $form = $(this);
            if (!$form.find('input[name="btr_honeypot"]').length) {
                $form.prepend(honeypotHtml + formTimeHtml);
            }
        });
    }
    
    // Rate limiting lato client
    var lastSubmissionTime = 0;
    var submissionCount = 0;
    var RATE_LIMIT_WINDOW = 60000; // 1 minuto
    var MAX_SUBMISSIONS = 3; // Massimo 3 submission per minuto
    
    function checkRateLimit() {
        var currentTime = Date.now();
        
        // Reset counter se è passato il window
        if (currentTime - lastSubmissionTime > RATE_LIMIT_WINDOW) {
            submissionCount = 0;
        }
        
        // Controlla limite
        if (submissionCount >= MAX_SUBMISSIONS) {
            return false;
        }
        
        return true;
    }
    
    // Enhanced form validation
    function validateSecureForm($form) {
        // Verifica honeypot
        var honeypotValue = $form.find('input[name="btr_honeypot"]').val();
        if (honeypotValue !== '') {
            console.warn('[BTR Security] Honeypot triggered');
            return false;
        }
        
        // Verifica timing
        var formStartTime = parseInt($form.find('input[name="btr_form_time"]').val());
        var currentTime = Math.floor(Date.now() / 1000);
        if (currentTime - formStartTime < 2) {
            alert('Si prega di compilare il form con calma.');
            return false;
        }
        
        // Verifica rate limiting
        if (!checkRateLimit()) {
            alert('Troppe richieste. Riprova tra qualche minuto.');
            return false;
        }
        
        return true;
    }
    
    // Protect AJAX calls
    function secureAjaxCall(options) {
        // Aggiungi security headers
        if (!options.data) {
            options.data = {};
        }
        
        // Aggiungi nonce se disponibile
        if (typeof btr_ajax_security !== 'undefined') {
            options.data.nonce = btr_ajax_security.nonce;
            options.data.btr_form_time = btr_ajax_security.form_time;
        }
        
        // Rate limiting check
        if (!checkRateLimit()) {
            console.warn('[BTR Security] Rate limit exceeded');
            return false;
        }
        
        // Log submission
        submissionCount++;
        lastSubmissionTime = Date.now();
        
        return $.ajax(options);
    }
    
    // Input sanitization helpers
    function sanitizeNumericInput($input) {
        $input.on('input', function() {
            var value = $(this).val();
            var sanitized = value.replace(/[^0-9.,]/g, '');
            
            // Limita lunghezza per prevenire overflow
            if (sanitized.length > 10) {
                sanitized = sanitized.substring(0, 10);
            }
            
            if (value !== sanitized) {
                $(this).val(sanitized);
            }
        });
    }
    
    function sanitizeTextInput($input) {
        $input.on('input', function() {
            var value = $(this).val();
            
            // Rimuovi caratteri potenzialmente pericolosi
            var sanitized = value.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            sanitized = sanitized.replace(/javascript:/gi, '');
            sanitized = sanitized.replace(/on\w+\s*=/gi, '');
            
            // Limita lunghezza
            if (sanitized.length > 250) {
                sanitized = sanitized.substring(0, 250);
            }
            
            if (value !== sanitized) {
                $(this).val(sanitized);
            }
        });
    }
    
    // Enhanced email validation
    function validateEmail(email) {
        var re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        if (!re.test(email)) {
            return false;
        }
        
        // Blocca domini temporaneli comuni
        var tempDomains = [
            'tempmail.org', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org'
        ];
        
        var domain = email.split('@')[1].toLowerCase();
        if (tempDomains.indexOf(domain) !== -1) {
            return false;
        }
        
        return true;
    }
    
    // Initialize security enhancements
    $(document).ready(function() {
        // Aggiungi security fields
        addSecurityFields();
        
        // Applica sanitization agli input
        $('input[type="number"], input[data-type="numeric"]').each(function() {
            sanitizeNumericInput($(this));
        });
        
        $('input[type="text"], input[type="email"], textarea').each(function() {
            sanitizeTextInput($(this));
        });
        
        // Intercetta form submission
        $(document).on('submit', '.btr-booking-form, form[data-btr-form]', function(e) {
            if (!validateSecureForm($(this))) {
                e.preventDefault();
                return false;
            }
        });
        
        // Enhanced email validation
        $(document).on('blur', 'input[type="email"]', function() {
            var email = $(this).val();
            if (email && !validateEmail(email)) {
                $(this).addClass('error');
                // Mostra messaggio di errore se esiste
                var $errorMsg = $(this).siblings('.email-error');
                if ($errorMsg.length === 0) {
                    $errorMsg = $('<span class="email-error" style="color:red;font-size:12px;">Email non valida</span>');
                    $(this).after($errorMsg);
                }
                $errorMsg.show();
            } else {
                $(this).removeClass('error');
                $(this).siblings('.email-error').hide();
            }
        });
    });
    
    // Expose secure AJAX function globally
    window.btrSecureAjax = secureAjaxCall;
    
    // Monitor for suspicious activity
    var suspiciousActivity = {
        rapidClicks: 0,
        lastClick: 0
    };
    
    $(document).on('click', function() {
        var currentTime = Date.now();
        if (currentTime - suspiciousActivity.lastClick < 100) {
            suspiciousActivity.rapidClicks++;
            
            // Se troppi click rapidi, blocca temporaneamente
            if (suspiciousActivity.rapidClicks > 10) {
                $('form button[type="submit"]').prop('disabled', true);
                setTimeout(function() {
                    $('form button[type="submit"]').prop('disabled', false);
                    suspiciousActivity.rapidClicks = 0;
                }, 2000);
            }
        } else {
            suspiciousActivity.rapidClicks = 0;
        }
        suspiciousActivity.lastClick = currentTime;
    });
    
})(jQuery);