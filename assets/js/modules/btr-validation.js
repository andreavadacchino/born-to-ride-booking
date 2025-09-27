/**
 * BTR Validation Module - Validazione frontend avanzata
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR Validation Class
     */
    class BTRValidation {
        
        constructor(stateManager) {
            this.version = '3.0.0';
            this.stateManager = stateManager;
            this.rules = {};
            this.customValidators = {};
            this.errorMessages = {};
            this.realTimeValidation = true;
            this.validationDelay = 300;
            
            this.init();
        }
        
        /**
         * Inizializza il validation module
         */
        init() {
            this.setupDefaultRules();
            this.setupDefaultMessages();
            this.setupCustomValidators();
            this.bindValidationEvents();
            
            console.log('BTR Validation v3.0 initialized');
        }
        
        /**
         * Setup regole di validazione predefinite
         */
        setupDefaultRules() {
            this.rules = {
                // Step 1 - Partecipanti e Camere
                num_adults: {
                    required: true,
                    min: 1,
                    max: 20,
                    type: 'number'
                },
                num_children: {
                    required: false,
                    min: 0,
                    max: 10,
                    type: 'number'
                },
                num_neonati: {
                    required: false,
                    min: 0,
                    max: 5,
                    type: 'number'
                },
                
                // Età bambini dinamica
                'child_age_*': {
                    required: true,
                    type: 'select'
                },
                
                // Camere
                'room_*_type': {
                    required: true,
                    type: 'select'
                },
                
                // Step 2 - Dati Anagrafici
                'anagrafico_0_name': {
                    required: true,
                    minLength: 2,
                    maxLength: 50,
                    type: 'text',
                    pattern: /^[a-zA-ZÀ-ÿ\s'.-]+$/
                },
                'anagrafico_0_surname': {
                    required: true,
                    minLength: 2,
                    maxLength: 50,
                    type: 'text',
                    pattern: /^[a-zA-ZÀ-ÿ\s'.-]+$/
                },
                'anagrafico_0_email': {
                    required: true,
                    type: 'email',
                    maxLength: 100
                },
                'anagrafico_0_phone': {
                    required: true,
                    type: 'phone',
                    minLength: 8,
                    maxLength: 15
                },
                'anagrafico_0_birth_date': {
                    required: true,
                    type: 'date',
                    custom: 'minimumAge'
                },
                
                // Codice fiscale (opzionale)
                'anagrafico_0_fiscal_code': {
                    required: false,
                    type: 'fiscal_code',
                    custom: 'validFiscalCode'
                },
                
                // Altri partecipanti
                'anagrafico_*_name': {
                    required: true,
                    minLength: 2,
                    maxLength: 50,
                    type: 'text',
                    pattern: /^[a-zA-ZÀ-ÿ\s'.-]+$/
                },
                'anagrafico_*_surname': {
                    required: true,
                    minLength: 2,
                    maxLength: 50,
                    type: 'text',
                    pattern: /^[a-zA-ZÀ-ÿ\s'.-]+$/
                },
                
                // Step 3 - Condizioni
                privacy_accepted: {
                    required: true,
                    type: 'checkbox',
                    custom: 'mustBeTrue'
                },
                terms_accepted: {
                    required: true,
                    type: 'checkbox',
                    custom: 'mustBeTrue'
                }
            };
        }
        
        /**
         * Setup messaggi errore predefiniti
         */
        setupDefaultMessages() {
            this.errorMessages = {
                required: 'Questo campo è obbligatorio',
                min: 'Il valore deve essere almeno {min}',
                max: 'Il valore non può superare {max}',
                minLength: 'Inserisci almeno {minLength} caratteri',
                maxLength: 'Non puoi inserire più di {maxLength} caratteri',
                email: 'Inserisci un indirizzo email valido',
                phone: 'Inserisci un numero di telefono valido',
                date: 'Inserisci una data valida',
                number: 'Inserisci un numero valido',
                pattern: 'Il formato inserito non è valido',
                fiscal_code: 'Il codice fiscale inserito non è valido',
                minimumAge: 'Devi avere almeno 18 anni',
                mustBeTrue: 'Devi accettare per continuare',
                
                // Messaggi specifici per campi
                num_adults: 'Inserisci il numero di adulti (minimo 1)',
                num_children: 'Numero bambini non valido',
                'anagrafico_0_name': 'Inserisci il tuo nome',
                'anagrafico_0_surname': 'Inserisci il tuo cognome',
                'anagrafico_0_email': 'Inserisci la tua email',
                'anagrafico_0_phone': 'Inserisci il tuo numero di telefono'
            };
        }
        
        /**
         * Setup validatori personalizzati
         */
        setupCustomValidators() {
            this.customValidators = {
                // Età minima 18 anni
                minimumAge: (value) => {
                    const birthDate = new Date(value);
                    const today = new Date();
                    const age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }
                    
                    return age >= 18;
                },
                
                // Codice fiscale italiano
                validFiscalCode: (value) => {
                    if (!value) return true; // Opzionale
                    
                    const cf = value.toUpperCase().trim();
                    if (cf.length !== 16) return false;
                    
                    // Pattern basic CF italiano
                    const pattern = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/;
                    return pattern.test(cf);
                },
                
                // Checkbox deve essere true
                mustBeTrue: (value) => {
                    return value === true || value === 'true' || value === '1';
                },
                
                // Telefono italiano
                validItalianPhone: (value) => {
                    const phone = value.replace(/\s/g, '');
                    // Pattern telefono italiano (+39, 39, o diretto)
                    const pattern = /^(\+39|39)?[0-9]{8,11}$/;
                    return pattern.test(phone);
                }
            };
        }
        
        /**
         * Bind eventi validazione
         */
        bindValidationEvents() {
            const self = this;
            
            if (this.realTimeValidation) {
                // Validazione real-time con debounce
                $(document).on('blur', 'input, select, textarea', function() {
                    const $field = $(this);
                    setTimeout(() => {
                        self.validateField($field);
                    }, self.validationDelay);
                });
                
                // Validazione immediata per checkbox/radio
                $(document).on('change', 'input[type="checkbox"], input[type="radio"]', function() {
                    self.validateField($(this));
                });
            }
            
            // Validazione on-demand dal State Manager
            this.stateManager.on('validation.request', (data) => {
                self.validateData(data.data, data.step);
            });
        }
        
        /**
         * Valida singolo campo
         */
        validateField($field) {
            const fieldName = $field.attr('name');
            const fieldValue = this.getFieldValue($field);
            
            if (!fieldName) return true;
            
            const result = this.validateSingleField(fieldName, fieldValue, $field);
            
            // Aggiorna UI
            this.updateFieldUI($field, result);
            
            return result.isValid;
        }
        
        /**
         * Valida singolo campo con regole
         */
        validateSingleField(fieldName, value, $field = null) {
            const result = {
                isValid: true,
                errors: [],
                warnings: []
            };
            
            // Ottieni regole per questo campo
            const rules = this.getRulesForField(fieldName);
            if (!rules) {
                return result; // Nessuna regola = valido
            }
            
            // Validazione required
            if (rules.required && this.isEmpty(value)) {
                result.isValid = false;
                result.errors.push(this.getMessage(fieldName, 'required'));
                return result; // Stop qui se required fallisce
            }
            
            // Skip altre validazioni se campo vuoto e non required
            if (this.isEmpty(value) && !rules.required) {
                return result;
            }
            
            // Validazione type-specific
            if (rules.type) {
                const typeValid = this.validateType(value, rules.type, $field);
                if (!typeValid.isValid) {
                    result.isValid = false;
                    result.errors.push(...typeValid.errors);
                }
            }
            
            // Validazione lunghezza
            if (rules.minLength && value.length < rules.minLength) {
                result.isValid = false;
                result.errors.push(this.getMessage(fieldName, 'minLength', { minLength: rules.minLength }));
            }
            
            if (rules.maxLength && value.length > rules.maxLength) {
                result.isValid = false;
                result.errors.push(this.getMessage(fieldName, 'maxLength', { maxLength: rules.maxLength }));
            }
            
            // Validazione range numerico
            if (rules.type === 'number') {
                const numValue = parseFloat(value);
                
                if (rules.min !== undefined && numValue < rules.min) {
                    result.isValid = false;
                    result.errors.push(this.getMessage(fieldName, 'min', { min: rules.min }));
                }
                
                if (rules.max !== undefined && numValue > rules.max) {
                    result.isValid = false;
                    result.errors.push(this.getMessage(fieldName, 'max', { max: rules.max }));
                }
            }
            
            // Validazione pattern
            if (rules.pattern && !rules.pattern.test(value)) {
                result.isValid = false;
                result.errors.push(this.getMessage(fieldName, 'pattern'));
            }
            
            // Validatori personalizzati
            if (rules.custom && this.customValidators[rules.custom]) {
                const customValid = this.customValidators[rules.custom](value, $field);
                if (!customValid) {
                    result.isValid = false;
                    result.errors.push(this.getMessage(fieldName, rules.custom));
                }
            }
            
            return result;
        }
        
        /**
         * Valida tipo specifico
         */
        validateType(value, type, $field) {
            const result = { isValid: true, errors: [] };
            
            switch (type) {
                case 'email':
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(value)) {
                        result.isValid = false;
                        result.errors.push(this.getMessage($field?.attr('name'), 'email'));
                    }
                    break;
                    
                case 'phone':
                    // Usa validatore personalizzato se disponibile
                    if (this.customValidators.validItalianPhone) {
                        if (!this.customValidators.validItalianPhone(value)) {
                            result.isValid = false;
                            result.errors.push(this.getMessage($field?.attr('name'), 'phone'));
                        }
                    } else {
                        // Fallback basic
                        const phonePattern = /^[\d\s\+\-\(\)]{8,}$/;
                        if (!phonePattern.test(value)) {
                            result.isValid = false;
                            result.errors.push(this.getMessage($field?.attr('name'), 'phone'));
                        }
                    }
                    break;
                    
                case 'date':
                    const date = new Date(value);
                    if (isNaN(date.getTime())) {
                        result.isValid = false;
                        result.errors.push(this.getMessage($field?.attr('name'), 'date'));
                    }
                    break;
                    
                case 'number':
                    if (isNaN(parseFloat(value))) {
                        result.isValid = false;
                        result.errors.push(this.getMessage($field?.attr('name'), 'number'));
                    }
                    break;
                    
                case 'select':
                    if ($field && $field.is('select') && !$field.find(`option[value="${value}"]`).length) {
                        result.isValid = false;
                        result.errors.push(this.getMessage($field.attr('name'), 'required'));
                    }
                    break;
                    
                case 'checkbox':
                    // Checkbox validation handled by custom validators
                    break;
                    
                case 'fiscal_code':
                    if (this.customValidators.validFiscalCode) {
                        if (!this.customValidators.validFiscalCode(value)) {
                            result.isValid = false;
                            result.errors.push(this.getMessage($field?.attr('name'), 'fiscal_code'));
                        }
                    }
                    break;
            }
            
            return result;
        }
        
        /**
         * Valida set di dati
         */
        validateData(data, step = 'all') {
            const results = {
                isValid: true,
                errors: {},
                warnings: {},
                summary: {
                    totalFields: 0,
                    validFields: 0,
                    errorFields: 0,
                    warningFields: 0
                }
            };
            
            // Filtra campi per step se specificato
            const fieldsToValidate = this.getFieldsForStep(data, step);
            
            Object.entries(fieldsToValidate).forEach(([fieldName, value]) => {
                results.summary.totalFields++;
                
                const fieldResult = this.validateSingleField(fieldName, value);
                
                if (fieldResult.isValid) {
                    results.summary.validFields++;
                } else {
                    results.isValid = false;
                    results.errors[fieldName] = fieldResult.errors;
                    results.summary.errorFields++;
                }
                
                if (fieldResult.warnings.length > 0) {
                    results.warnings[fieldName] = fieldResult.warnings;
                    results.summary.warningFields++;
                }
            });
            
            console.log('[VALIDATION] Results:', results);
            return results;
        }
        
        /**
         * Ottieni regole per campo specifico
         */
        getRulesForField(fieldName) {
            // Match esatto
            if (this.rules[fieldName]) {
                return this.rules[fieldName];
            }
            
            // Match con wildcard
            for (const pattern in this.rules) {
                if (pattern.includes('*')) {
                    const regex = new RegExp('^' + pattern.replace('*', '\\d+') + '$');
                    if (regex.test(fieldName)) {
                        return this.rules[pattern];
                    }
                }
            }
            
            return null;
        }
        
        /**
         * Ottieni campi per step specifico
         */
        getFieldsForStep(data, step) {
            if (step === 'all') {
                return data;
            }
            
            const stepFields = {
                1: ['num_adults', 'num_children', 'num_neonati', /^child_age_/, /^room_.*_type$/],
                2: [/^anagrafico_.*_(name|surname|email|phone|birth_date|fiscal_code)$/],
                3: ['privacy_accepted', 'terms_accepted', 'newsletter_accepted']
            };
            
            const fieldsForStep = stepFields[step] || [];
            const result = {};
            
            Object.entries(data).forEach(([key, value]) => {
                const shouldInclude = fieldsForStep.some(pattern => {
                    if (typeof pattern === 'string') {
                        return pattern === key;
                    } else if (pattern instanceof RegExp) {
                        return pattern.test(key);
                    }
                    return false;
                });
                
                if (shouldInclude) {
                    result[key] = value;
                }
            });
            
            return result;
        }
        
        /**
         * Ottieni valore campo
         */
        getFieldValue($field) {
            if ($field.is(':checkbox')) {
                return $field.is(':checked');
            } else if ($field.is(':radio')) {
                return $field.is(':checked') ? $field.val() : null;
            } else {
                return $field.val();
            }
        }
        
        /**
         * Check se valore è vuoto
         */
        isEmpty(value) {
            return value === null || value === undefined || value === '' || 
                   (Array.isArray(value) && value.length === 0);
        }
        
        /**
         * Ottieni messaggio errore
         */
        getMessage(fieldName, ruleType, params = {}) {
            // Messaggio specifico per campo
            if (this.errorMessages[fieldName]) {
                return this.errorMessages[fieldName];
            }
            
            // Messaggio generico per tipo regola
            let message = this.errorMessages[ruleType] || 'Valore non valido';
            
            // Sostituisci parametri
            Object.entries(params).forEach(([key, value]) => {
                message = message.replace(`{${key}}`, value);
            });
            
            return message;
        }
        
        /**
         * Aggiorna UI campo
         */
        updateFieldUI($field, result) {
            const $wrapper = $field.closest('.form-field, .field-wrapper, .form-group');
            
            // Rimuovi classi precedenti
            $wrapper.removeClass('has-error has-warning has-success');
            $wrapper.find('.field-error, .field-warning').remove();
            
            if (!result.isValid && result.errors.length > 0) {
                // Errore
                $wrapper.addClass('has-error');
                const errorHtml = `<div class="field-error">${result.errors.join('<br>')}</div>`;
                $wrapper.append(errorHtml);
            } else if (result.warnings && result.warnings.length > 0) {
                // Warning
                $wrapper.addClass('has-warning');
                const warningHtml = `<div class="field-warning">${result.warnings.join('<br>')}</div>`;
                $wrapper.append(warningHtml);
            } else if (!this.isEmpty(this.getFieldValue($field))) {
                // Successo
                $wrapper.addClass('has-success');
            }
        }
        
        /**
         * Aggiungi regola personalizzata
         */
        addRule(fieldName, rules) {
            this.rules[fieldName] = { ...this.rules[fieldName], ...rules };
        }
        
        /**
         * Aggiungi validatore personalizzato
         */
        addValidator(name, validator) {
            this.customValidators[name] = validator;
        }
        
        /**
         * Aggiungi messaggio personalizzato
         */
        addMessage(key, message) {
            this.errorMessages[key] = message;
        }
        
        /**
         * Reset validazione
         */
        reset() {
            $('.has-error, .has-warning, .has-success').removeClass('has-error has-warning has-success');
            $('.field-error, .field-warning').remove();
        }
        
        /**
         * Debug info
         */
        getDebugInfo() {
            return {
                version: this.version,
                rulesCount: Object.keys(this.rules).length,
                validatorsCount: Object.keys(this.customValidators).length,
                messagesCount: Object.keys(this.errorMessages).length,
                realTimeValidation: this.realTimeValidation
            };
        }
    }
    
    // Export globalmente
    window.BTRValidation = BTRValidation;
    
})(window.jQuery || window.$, window, document);