/**
 * BTR State Manager - Gestione stato frontend con State Machine
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($) {
    'use strict';

    /**
     * BTR State Manager Class
     */
    class BTRStateManager {
        
        constructor() {
            this.version = '3.0.0';
            this.state = this.getInitialState();
            this.history = [];
            this.listeners = {};
            this.validationRules = {};
            this.isDirty = false;
            
            this.init();
        }
        
        /**
         * Inizializza il manager
         */
        init() {
            // Carica stato da sessione se esiste
            this.loadFromSession();
            
            // Setup auto-save
            this.setupAutoSave();
            
            // Setup error recovery
            this.setupErrorRecovery();
            
            // Bind eventi form
            this.bindFormEvents();
            
            // Inizializza validazione
            this.initValidation();
            
            console.log('BTR State Manager v3.0 initialized');
        }
        
        /**
         * Stato iniziale
         */
        getInitialState() {
            return {
                // Step corrente
                currentStep: 1,
                maxStep: 4,
                
                // Dati pacchetto
                package: {
                    id: null,
                    title: '',
                    duration: 7,
                    prices: {}
                },
                
                // Date viaggio
                dates: {
                    checkin: '',
                    checkout: '',
                    nights: 0,
                    extraNights: 0
                },
                
                // Camere e occupanti
                rooms: [],
                
                // Anagrafici
                anagrafici: [],
                
                // Costi extra
                extraCosts: {},
                
                // Riepilogo calcoli
                calculation: {
                    basePrice: 0,
                    extraNights: 0,
                    extraCosts: 0,
                    discounts: 0,
                    total: 0,
                    breakdown: [],
                    lastCalculated: null
                },
                
                // Pagamento
                payment: {
                    method: 'full',
                    depositPercent: 30,
                    groupPayment: false,
                    participants: []
                },
                
                // Metadata
                meta: {
                    sessionId: this.generateSessionId(),
                    createdAt: new Date().toISOString(),
                    updatedAt: new Date().toISOString(),
                    userAgent: navigator.userAgent,
                    language: navigator.language
                },
                
                // Validation
                validation: {
                    errors: {},
                    warnings: {},
                    isValid: false
                }
            };
        }
        
        /**
         * Genera ID sessione univoco
         */
        generateSessionId() {
            return 'btr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        /**
         * Set stato con validazione
         */
        setState(path, value, options = {}) {
            const oldValue = this.getState(path);
            
            // Se uguale, skip
            if (JSON.stringify(oldValue) === JSON.stringify(value)) {
                return;
            }
            
            // Salva history
            this.history.push({
                timestamp: Date.now(),
                path: path,
                oldValue: oldValue,
                newValue: value,
                action: options.action || 'update'
            });
            
            // Aggiorna stato
            this._setNestedValue(this.state, path, value);
            
            // Marca come dirty
            this.isDirty = true;
            
            // Aggiorna timestamp
            this.state.meta.updatedAt = new Date().toISOString();
            
            // Valida se richiesto
            if (options.validate !== false) {
                this.validatePath(path);
            }
            
            // Trigger listeners
            this.triggerListeners(path, value, oldValue);
            
            // Auto-save se abilitato
            if (options.autoSave !== false) {
                this.saveToSession();
            }
            
            // Log in debug
            if (window.BTR_DEBUG) {
                console.log('State updated:', path, value);
            }
        }
        
        /**
         * Get stato
         */
        getState(path = null) {
            if (!path) {
                return this.state;
            }
            
            return this._getNestedValue(this.state, path);
        }
        
        /**
         * Helper per set nested value
         */
        _setNestedValue(obj, path, value) {
            const keys = path.split('.');
            const lastKey = keys.pop();
            const target = keys.reduce((o, k) => o[k] = o[k] || {}, obj);
            target[lastKey] = value;
        }
        
        /**
         * Helper per get nested value
         */
        _getNestedValue(obj, path) {
            return path.split('.').reduce((o, k) => o ? o[k] : undefined, obj);
        }
        
        /**
         * Aggiungi listener per cambiamenti
         */
        on(path, callback) {
            if (!this.listeners[path]) {
                this.listeners[path] = [];
            }
            this.listeners[path].push(callback);
            
            // Return unsubscribe function
            return () => {
                const index = this.listeners[path].indexOf(callback);
                if (index > -1) {
                    this.listeners[path].splice(index, 1);
                }
            };
        }
        
        /**
         * Trigger listeners
         */
        triggerListeners(path, newValue, oldValue) {
            // Trigger exact path listeners
            if (this.listeners[path]) {
                this.listeners[path].forEach(callback => {
                    callback(newValue, oldValue, path);
                });
            }
            
            // Trigger wildcard listeners
            Object.keys(this.listeners).forEach(listenerPath => {
                if (listenerPath.includes('*')) {
                    const regex = new RegExp('^' + listenerPath.replace('*', '.*') + '$');
                    if (regex.test(path)) {
                        this.listeners[listenerPath].forEach(callback => {
                            callback(newValue, oldValue, path);
                        });
                    }
                }
            });
        }
        
        /**
         * Salva stato in session storage
         */
        saveToSession() {
            try {
                const stateToSave = {
                    ...this.state,
                    meta: {
                        ...this.state.meta,
                        lastSaved: new Date().toISOString()
                    }
                };
                
                sessionStorage.setItem('btr_state', JSON.stringify(stateToSave));
                this.isDirty = false;
                
                if (window.BTR_DEBUG) {
                    console.log('State saved to session');
                }
            } catch (e) {
                console.error('Failed to save state:', e);
            }
        }
        
        /**
         * Carica stato da session storage
         */
        loadFromSession() {
            try {
                const savedState = sessionStorage.getItem('btr_state');
                
                if (savedState) {
                    const parsed = JSON.parse(savedState);
                    
                    // Verifica se non troppo vecchio (24 ore)
                    const age = Date.now() - new Date(parsed.meta.createdAt).getTime();
                    if (age < 24 * 60 * 60 * 1000) {
                        this.state = {
                            ...this.getInitialState(),
                            ...parsed,
                            meta: {
                                ...parsed.meta,
                                restored: true,
                                restoredAt: new Date().toISOString()
                            }
                        };
                        
                        console.log('State restored from session');
                        return true;
                    }
                }
            } catch (e) {
                console.error('Failed to load state:', e);
            }
            
            return false;
        }
        
        /**
         * Setup auto-save
         */
        setupAutoSave() {
            // Auto-save ogni 30 secondi se dirty
            setInterval(() => {
                if (this.isDirty) {
                    this.saveToSession();
                }
            }, 30000);
            
            // Save on page unload
            window.addEventListener('beforeunload', () => {
                if (this.isDirty) {
                    this.saveToSession();
                }
            });
        }
        
        /**
         * Setup error recovery
         */
        setupErrorRecovery() {
            window.addEventListener('error', (e) => {
                console.error('Global error caught:', e);
                
                // Salva stato di emergenza
                try {
                    const emergencyState = {
                        ...this.state,
                        meta: {
                            ...this.state.meta,
                            error: {
                                message: e.message,
                                stack: e.error ? e.error.stack : '',
                                timestamp: new Date().toISOString()
                            }
                        }
                    };
                    
                    localStorage.setItem('btr_emergency_state', JSON.stringify(emergencyState));
                } catch (err) {
                    console.error('Failed to save emergency state:', err);
                }
            });
        }
        
        /**
         * Bind eventi form
         */
        bindFormEvents() {
            // Date change
            $(document).on('change', '#checkin_date, #checkout_date', (e) => {
                const checkin = $('#checkin_date').val();
                const checkout = $('#checkout_date').val();
                
                if (checkin && checkout) {
                    const nights = this.calculateNights(checkin, checkout);
                    
                    this.setState('dates', {
                        checkin: checkin,
                        checkout: checkout,
                        nights: nights,
                        extraNights: Math.max(0, nights - (this.state.package.duration || 7))
                    });
                    
                    // Trigger ricalcolo
                    this.recalculate();
                }
            });
            
            // Room changes
            $(document).on('change', '.room-adults, .room-children', (e) => {
                this.updateRoomsFromForm();
                this.recalculate();
            });
            
            // Extra costs
            $(document).on('change', '.extra-cost-checkbox', (e) => {
                const extraId = $(e.target).data('extra-id');
                const checked = $(e.target).is(':checked');
                
                this.setState(`extraCosts.${extraId}`, checked);
                this.recalculate();
            });
            
            // Anagrafici changes
            $(document).on('change', '.anagrafico-field', (e) => {
                this.updateAnagraficiFromForm();
            });
        }
        
        /**
         * Calcola notti
         */
        calculateNights(checkin, checkout) {
            const start = new Date(checkin);
            const end = new Date(checkout);
            const diff = end - start;
            return Math.floor(diff / (1000 * 60 * 60 * 24));
        }
        
        /**
         * Aggiorna rooms da form
         */
        updateRoomsFromForm() {
            const rooms = [];
            
            $('.room-configuration').each(function(index) {
                const adults = parseInt($(this).find('.room-adults').val()) || 0;
                const children = [];
                
                $(this).find('.child-age-select').each(function() {
                    const age = parseInt($(this).val());
                    if (age >= 0) {
                        children.push({ age: age });
                    }
                });
                
                rooms.push({
                    id: index + 1,
                    adults: adults,
                    children: children
                });
            });
            
            this.setState('rooms', rooms);
        }
        
        /**
         * Aggiorna anagrafici da form
         */
        updateAnagraficiFromForm() {
            const anagrafici = [];
            
            $('.anagrafico-form').each(function(index) {
                const anagrafico = {
                    id: index + 1,
                    nome: $(this).find('[name*="nome"]').val(),
                    cognome: $(this).find('[name*="cognome"]').val(),
                    data_nascita: $(this).find('[name*="data_nascita"]').val(),
                    documento_tipo: $(this).find('[name*="documento_tipo"]').val(),
                    documento_numero: $(this).find('[name*="documento_numero"]').val(),
                    email: $(this).find('[name*="email"]').val(),
                    telefono: $(this).find('[name*="telefono"]').val()
                };
                
                anagrafici.push(anagrafico);
            });
            
            this.setState('anagrafici', anagrafici);
        }
        
        /**
         * Ricalcola prezzi
         */
        async recalculate() {
            // Skip se dati insufficienti
            if (!this.state.package.id || !this.state.dates.checkin || !this.state.dates.checkout) {
                return;
            }
            
            // Prepara payload
            const payload = {
                package_id: this.state.package.id,
                checkin: this.state.dates.checkin,
                checkout: this.state.dates.checkout,
                rooms: this.state.rooms,
                extra_costs: this.state.extraCosts
            };
            
            try {
                // Chiama calculator via AJAX
                const response = await $.ajax({
                    url: btr_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'btr_calculate_price',
                        nonce: btr_ajax.nonce,
                        data: JSON.stringify(payload)
                    }
                });
                
                if (response.success) {
                    // Aggiorna stato con risultato
                    this.setState('calculation', {
                        ...response.data,
                        lastCalculated: new Date().toISOString()
                    });
                    
                    // Trigger evento
                    $(document).trigger('btr:calculation:complete', [response.data]);
                    
                    // Aggiorna UI
                    this.updatePriceDisplay(response.data);
                }
                
            } catch (error) {
                console.error('Calculation failed:', error);
                
                // Set error state
                this.setState('validation.errors.calculation', 'Errore nel calcolo del prezzo');
            }
        }
        
        /**
         * Aggiorna display prezzi
         */
        updatePriceDisplay(calculation) {
            // Aggiorna totale
            $('.price-total').text('€' + calculation.total.toFixed(2));
            
            // Aggiorna breakdown
            if (calculation.breakdown && calculation.breakdown.length > 0) {
                let breakdownHtml = '<ul class="price-breakdown">';
                
                calculation.breakdown.forEach(item => {
                    breakdownHtml += `<li>${item.type}: €${item.amount.toFixed(2)}</li>`;
                });
                
                breakdownHtml += '</ul>';
                $('.price-breakdown-container').html(breakdownHtml);
            }
        }
        
        /**
         * Inizializza validazione
         */
        initValidation() {
            // Regole validazione per step 1
            this.validationRules.step1 = {
                'package.id': {
                    required: true,
                    message: 'Seleziona un pacchetto'
                },
                'dates.checkin': {
                    required: true,
                    date: true,
                    message: 'Data check-in richiesta'
                },
                'dates.checkout': {
                    required: true,
                    date: true,
                    after: 'dates.checkin',
                    message: 'Data check-out richiesta'
                },
                'rooms': {
                    minLength: 1,
                    message: 'Almeno una camera richiesta'
                }
            };
            
            // Regole per step 2 (anagrafici)
            this.validationRules.step2 = {
                'anagrafici': {
                    custom: (value) => {
                        // Verifica che ci siano anagrafici per tutti
                        const totalPeople = this.getTotalPeople();
                        return value.length === totalPeople;
                    },
                    message: 'Completa tutti i dati anagrafici'
                }
            };
            
            // Regole per step 3 (pagamento)
            this.validationRules.step3 = {
                'payment.method': {
                    required: true,
                    in: ['full', 'deposit', 'group'],
                    message: 'Seleziona metodo di pagamento'
                }
            };
        }
        
        /**
         * Valida un path specifico
         */
        validatePath(path) {
            const step = this.state.currentStep;
            const rules = this.validationRules[`step${step}`];
            
            if (!rules || !rules[path]) {
                return true;
            }
            
            const value = this.getState(path);
            const rule = rules[path];
            let isValid = true;
            let message = '';
            
            // Required
            if (rule.required && !value) {
                isValid = false;
                message = rule.message || 'Campo richiesto';
            }
            
            // Custom validation
            if (rule.custom && !rule.custom(value)) {
                isValid = false;
                message = rule.message || 'Valore non valido';
            }
            
            // Update validation state
            if (!isValid) {
                this.setState(`validation.errors.${path}`, message, { validate: false });
            } else {
                // Clear error
                const errors = {...this.state.validation.errors};
                delete errors[path];
                this.setState('validation.errors', errors, { validate: false });
            }
            
            return isValid;
        }
        
        /**
         * Valida step corrente
         */
        validateCurrentStep() {
            const step = this.state.currentStep;
            const rules = this.validationRules[`step${step}`];
            
            if (!rules) {
                return true;
            }
            
            let isValid = true;
            
            Object.keys(rules).forEach(path => {
                if (!this.validatePath(path)) {
                    isValid = false;
                }
            });
            
            this.setState('validation.isValid', isValid, { validate: false });
            
            return isValid;
        }
        
        /**
         * Conta persone totali
         */
        getTotalPeople() {
            let total = 0;
            
            this.state.rooms.forEach(room => {
                total += room.adults;
                total += room.children.length;
            });
            
            return total;
        }
        
        /**
         * Naviga a step
         */
        goToStep(step) {
            // Valida step corrente prima di muoversi
            if (step > this.state.currentStep) {
                if (!this.validateCurrentStep()) {
                    alert('Completa tutti i campi richiesti');
                    return false;
                }
            }
            
            // Aggiorna step
            this.setState('currentStep', step);
            
            // Trigger evento
            $(document).trigger('btr:step:change', [step]);
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
            
            return true;
        }
        
        /**
         * Reset stato
         */
        reset() {
            this.state = this.getInitialState();
            this.history = [];
            this.isDirty = false;
            sessionStorage.removeItem('btr_state');
            
            console.log('State reset');
        }
        
        /**
         * Export stato per debug
         */
        exportState() {
            return {
                state: this.state,
                history: this.history.slice(-50), // Ultimi 50 eventi
                validation: this.state.validation,
                metadata: {
                    version: this.version,
                    exported: new Date().toISOString()
                }
            };
        }
        
        /**
         * Import stato (per testing)
         */
        importState(data) {
            if (data.state) {
                this.state = data.state;
                this.saveToSession();
                console.log('State imported');
            }
        }
    }
    
    // Inizializza e rendi globale
    window.BTRStateManager = BTRStateManager;
    
    // Auto-init on document ready
    $(document).ready(function() {
        window.btrStateManager = new BTRStateManager();
        
        // Expose per debug
        if (window.BTR_DEBUG) {
            window.btrState = window.btrStateManager;
        }
    });
    
})(jQuery);