/**
 * BTR Booking App v3.0 - Entry Point Modulare
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR Booking App Class - Orchestratore principale
     */
    class BTRBookingApp {
        
        constructor() {
            this.version = '3.0.0';
            this.modules = {};
            this.config = {};
            this.isInitialized = false;
            this.featureFlags = {};
            this.performanceMetrics = {
                startTime: Date.now(),
                moduleLoadTimes: {},
                initializationTime: null
            };
            
            this.init();
        }
        
        /**
         * Inizializza l'applicazione
         */
        async init() {
            console.log('🚀 BTR Booking App v3.0 initializing...');
            
            try {
                // 1. Carica configurazione
                await this.loadConfiguration();
                
                // 2. Setup feature flags
                this.setupFeatureFlags();
                
                // 3. Carica moduli core
                await this.loadCoreModules();
                
                // 4. Inizializza moduli in ordine
                await this.initializeModules();
                
                // 5. Setup integrazione legacy se necessaria
                this.setupLegacyIntegration();
                
                // 6. Bind eventi applicazione
                this.bindAppEvents();
                
                // 7. Performance tracking
                this.trackPerformance();
                
                this.isInitialized = true;
                this.performanceMetrics.initializationTime = Date.now() - this.performanceMetrics.startTime;
                
                console.log('✅ BTR Booking App initialized successfully in', this.performanceMetrics.initializationTime + 'ms');
                
                // Trigger evento inizializzazione completata
                $(document).trigger('btr.app.initialized', this);
                
            } catch (error) {
                console.error('❌ BTR Booking App initialization failed:', error);
                this.handleInitializationError(error);
            }
        }
        
        /**
         * Carica configurazione
         */
        async loadConfiguration() {
            // Configurazione da variabili globali WordPress
            this.config = {
                // Feature flags
                v3Enabled: window.btr_features?.v3_enabled !== false,
                legacyFallback: window.btr_features?.legacy_fallback !== false,
                debugMode: window.btr_features?.debug_mode === true,
                performanceTracking: window.btr_features?.performance_tracking !== false,
                
                // AJAX settings
                ajaxUrl: window.btr_ajax?.url || '/wp-admin/admin-ajax.php',
                nonce: window.btr_ajax?.nonce || '',
                timeout: window.btr_ajax?.timeout || 15000,
                
                // UI settings
                autoSave: window.btr_config?.auto_save !== false,
                realTimeValidation: window.btr_config?.real_time_validation !== false,
                animationsEnabled: window.btr_config?.animations !== false,
                
                // Calculator settings
                calculationDelay: window.btr_config?.calculation_delay || 300,
                cacheCalculations: window.btr_config?.cache_calculations !== false,
                
                // Form settings
                stepValidation: window.btr_config?.step_validation !== false,
                progressIndicators: window.btr_config?.progress_indicators !== false,
                
                // Performance settings
                lazyLoading: window.btr_config?.lazy_loading !== false,
                virtualScrolling: window.btr_config?.virtual_scrolling !== false,
                
                // Error handling
                showDetailedErrors: window.btr_features?.detailed_errors === true,
                errorReporting: window.btr_features?.error_reporting !== false
            };
            
            console.log('[CONFIG] Configuration loaded:', this.config);
        }
        
        /**
         * Setup feature flags
         */
        setupFeatureFlags() {
            this.featureFlags = {
                // v3 Features
                unifiedCalculator: this.config.v3Enabled && window.btr_features?.unified_calculator !== false,
                stateManagerV3: this.config.v3Enabled && window.btr_features?.state_manager_v3 !== false,
                ajaxV3: this.config.v3Enabled && window.btr_features?.ajax_v3 !== false,
                validationV3: this.config.v3Enabled && window.btr_features?.validation_v3 !== false,
                
                // UI Features
                modernComponents: window.btr_features?.modern_components !== false,
                responsiveDesign: window.btr_features?.responsive_design !== false,
                accessibility: window.btr_features?.accessibility !== false,
                
                // Performance Features
                lazyComponents: window.btr_features?.lazy_components !== false,
                cacheOptimization: window.btr_features?.cache_optimization !== false,
                debouncing: window.btr_features?.debouncing !== false,
                
                // Development Features
                devTools: this.config.debugMode && window.btr_features?.dev_tools === true,
                performanceMonitoring: this.config.performanceTracking,
                detailedLogging: this.config.debugMode
            };
            
            // Imposta feature flags globalmente per i moduli
            window.btrFeatureFlags = this.featureFlags;
            
            console.log('[FEATURES] Feature flags configured:', this.featureFlags);
        }
        
        /**
         * Carica moduli core
         */
        async loadCoreModules() {
            const moduleLoadStart = Date.now();
            
            try {
                // 1. State Manager (sempre necessario)
                if (window.BTRStateManager) {
                    console.log('[MODULES] Loading State Manager v3.0...');
                    const start = Date.now();
                    this.modules.stateManager = new window.BTRStateManager();
                    this.performanceMetrics.moduleLoadTimes.stateManager = Date.now() - start;
                } else {
                    throw new Error('BTRStateManager not found - ensure btr-state-manager.js is loaded');
                }
                
                // 2. AJAX Client
                if (window.BTRAjaxClient && this.featureFlags.ajaxV3) {
                    console.log('[MODULES] Loading AJAX Client v3.0...');
                    const start = Date.now();
                    this.modules.ajaxClient = new window.BTRAjaxClient();
                    this.performanceMetrics.moduleLoadTimes.ajaxClient = Date.now() - start;
                } else {
                    console.warn('[MODULES] AJAX Client v3 not available, using legacy AJAX');
                    this.modules.ajaxClient = this.createLegacyAjaxClient();
                }
                
                // 3. Calculator v3
                if (window.BTRCalculatorV3 && this.featureFlags.unifiedCalculator) {
                    console.log('[MODULES] Loading Calculator v3.0...');
                    const start = Date.now();
                    this.modules.calculator = new window.BTRCalculatorV3(
                        this.modules.stateManager,
                        this.modules.ajaxClient
                    );
                    this.performanceMetrics.moduleLoadTimes.calculator = Date.now() - start;
                } else {
                    console.warn('[MODULES] Calculator v3 not available, using legacy calculator');
                    this.modules.calculator = this.createLegacyCalculator();
                }
                
                // 4. Validation Module
                if (window.BTRValidation && this.featureFlags.validationV3) {
                    console.log('[MODULES] Loading Validation Module v3.0...');
                    const start = Date.now();
                    this.modules.validation = new window.BTRValidation(this.modules.stateManager);
                    this.performanceMetrics.moduleLoadTimes.validation = Date.now() - start;
                }
                
                // 5. Form Handler
                if (window.BTRFormHandler) {
                    console.log('[MODULES] Loading Form Handler v3.0...');
                    const start = Date.now();
                    this.modules.formHandler = new window.BTRFormHandler(
                        this.modules.stateManager,
                        this.modules.calculator
                    );
                    this.performanceMetrics.moduleLoadTimes.formHandler = Date.now() - start;
                }
                
                // 6. UI Components (lazy load se abilitato)
                if (window.BTRUIComponents) {
                    if (this.featureFlags.lazyComponents) {
                        console.log('[MODULES] Scheduling UI Components for lazy loading...');
                        this.scheduleUIComponentsLoad();
                    } else {
                        console.log('[MODULES] Loading UI Components v3.0...');
                        const start = Date.now();
                        this.modules.uiComponents = new window.BTRUIComponents(this.modules.stateManager);
                        this.performanceMetrics.moduleLoadTimes.uiComponents = Date.now() - start;
                    }
                }
                
                const totalLoadTime = Date.now() - moduleLoadStart;
                console.log(`[MODULES] Core modules loaded in ${totalLoadTime}ms`);
                
            } catch (error) {
                console.error('[MODULES] Failed to load core modules:', error);
                throw error;
            }
        }
        
        /**
         * Inizializza moduli in ordine
         */
        async initializeModules() {
            const initOrder = [
                'stateManager',
                'ajaxClient', 
                'validation',
                'calculator',
                'formHandler',
                'uiComponents'
            ];
            
            for (const moduleName of initOrder) {
                const module = this.modules[moduleName];
                if (module && typeof module.init === 'function' && !module.isInitialized) {
                    try {
                        console.log(`[INIT] Initializing ${moduleName}...`);
                        await module.init();
                        module.isInitialized = true;
                    } catch (error) {
                        console.error(`[INIT] Failed to initialize ${moduleName}:`, error);
                        
                        // Non-critical modules possono fallire
                        if (['uiComponents', 'validation'].includes(moduleName)) {
                            console.warn(`[INIT] Continuing without ${moduleName}`);
                            continue;
                        } else {
                            throw error;
                        }
                    }
                }
            }
        }
        
        /**
         * Setup integrazione legacy
         */
        setupLegacyIntegration() {
            if (!this.config.legacyFallback) {
                console.log('[LEGACY] Legacy integration disabled');
                return;
            }
            
            // Mantieni compatibilità con codice legacy
            if (window.btrBookingState) {
                console.log('[LEGACY] Migrating legacy state to v3...');
                this.migrateLegacyState(window.btrBookingState);
            }
            
            // Esponi API legacy
            this.exposeLegacyAPI();
            
            console.log('[LEGACY] Legacy integration setup completed');
        }
        
        /**
         * Migra stato legacy a v3
         */
        migrateLegacyState(legacyState) {
            const v3State = {};
            
            // Mappatura campi legacy -> v3
            const fieldMap = {
                'totale_camere': 'totale_camere',
                'prezzo_base_per_persona': 'prezzo_base_per_persona',
                'num_adults': 'num_adults',
                'num_children': 'num_children',
                'num_neonati': 'num_neonati',
                'notti': 'notti',
                'extra_nights': 'extra_nights',
                'costi_extra': 'costi_extra',
                'totale_costi_extra': 'totale_costi_extra',
                'totale_generale': 'totale_generale'
            };
            
            Object.entries(fieldMap).forEach(([legacyKey, v3Key]) => {
                if (legacyState[legacyKey] !== undefined) {
                    v3State[v3Key] = legacyState[legacyKey];
                }
            });
            
            // Aggiorna State Manager v3
            this.modules.stateManager.updateState(v3State);
            
            console.log('[LEGACY] State migrated:', v3State);
        }
        
        /**
         * Espone API legacy per compatibilità
         */
        exposeLegacyAPI() {
            // Mantieni window.btrBookingState come proxy
            window.btrBookingState = new Proxy({}, {
                get: (target, prop) => {
                    return this.modules.stateManager.getState(prop);
                },
                set: (target, prop, value) => {
                    this.modules.stateManager.updateState({ [prop]: value });
                    return true;
                }
            });
            
            // Funzioni legacy
            window.btrFormatPrice = (amount, decimals = 2, showCurrency = true) => {
                return this.formatPrice(amount, showCurrency);
            };
            
            window.btrParsePrice = (priceString) => {
                return this.parsePrice(priceString);
            };
            
            // Trigger calcolo legacy
            window.btrTriggerCalculation = (reason = 'legacy') => {
                if (this.modules.calculator && this.modules.calculator.triggerCalculation) {
                    this.modules.calculator.triggerCalculation(reason);
                }
            };
        }
        
        /**
         * Bind eventi applicazione
         */
        bindAppEvents() {
            const self = this;
            
            // Gestione errori globali
            window.addEventListener('error', (event) => {
                self.handleGlobalError(event.error, event);
            });
            
            window.addEventListener('unhandledrejection', (event) => {
                self.handleGlobalError(event.reason, event);
            });
            
            // Performance monitoring
            if (this.featureFlags.performanceMonitoring) {
                this.setupPerformanceMonitoring();
            }
            
            // Debug tools
            if (this.featureFlags.devTools) {
                this.setupDebugTools();
            }
            
            // Page visibility per ottimizzazioni
            document.addEventListener('visibilitychange', () => {
                self.handleVisibilityChange();
            });
        }
        
        /**
         * Setup performance monitoring
         */
        setupPerformanceMonitoring() {
            // Monitor eventi State Manager
            this.modules.stateManager.on('state.updated', (data) => {
                if (this.config.debugMode) {
                    console.log('[PERF] State update:', data);
                }
            });
            
            // Monitor calcoli
            if (this.modules.calculator) {
                this.modules.calculator.stateManager.on('calculation.completed', (data) => {
                    const duration = data.timestamp - (data.startTime || data.timestamp);
                    console.log(`[PERF] Calculation completed in ${duration}ms`);
                });
            }
            
            // Performance observer se supportato
            if ('PerformanceObserver' in window) {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach((entry) => {
                        if (entry.name.includes('btr')) {
                            console.log(`[PERF] ${entry.name}: ${entry.duration}ms`);
                        }
                    });
                });
                
                observer.observe({ entryTypes: ['measure'] });
            }
        }
        
        /**
         * Setup debug tools
         */
        setupDebugTools() {
            // Debug console commands
            window.btrDebug = {
                app: this,
                state: () => this.modules.stateManager.getDebugInfo(),
                modules: () => Object.keys(this.modules),
                performance: () => this.performanceMetrics,
                config: () => this.config,
                features: () => this.featureFlags,
                
                // Test functions
                triggerCalculation: () => this.modules.calculator?.triggerCalculation('debug'),
                validateForm: () => this.modules.formHandler?.validateEntireForm(),
                clearCache: () => this.modules.ajaxClient?.clearCache(),
                resetState: () => this.modules.stateManager?.reset()
            };
            
            console.log('🛠️ Debug tools available at window.btrDebug');
        }
        
        /**
         * Gestisce errori globali
         */
        handleGlobalError(error, event) {
            console.error('[ERROR] Global error caught:', error);
            
            const errorInfo = {
                message: error.message || 'Unknown error',
                stack: error.stack,
                timestamp: Date.now(),
                url: event?.filename || window.location.href,
                line: event?.lineno,
                column: event?.colno,
                userAgent: navigator.userAgent,
                modules: Object.keys(this.modules),
                config: this.config
            };
            
            // Report errore se abilitato
            if (this.config.errorReporting) {
                this.reportError(errorInfo);
            }
            
            // Mostra errore user-friendly
            if (this.modules.uiComponents) {
                this.modules.uiComponents.showNotification(
                    'Si è verificato un errore. Ricarica la pagina se il problema persiste.',
                    'error',
                    10000
                );
            }
        }
        
        /**
         * Report errore al backend
         */
        async reportError(errorInfo) {
            try {
                await this.modules.ajaxClient.makeRequest({
                    action: 'btr_report_error',
                    error_info: JSON.stringify(errorInfo),
                    nonce: this.config.nonce
                });
            } catch (reportError) {
                console.warn('[ERROR] Failed to report error:', reportError);
            }
        }
        
        /**
         * Gestisce cambio visibilità pagina
         */
        handleVisibilityChange() {
            if (document.hidden) {
                // Pagina nascosta - pausa operazioni non critiche
                if (this.modules.calculator) {
                    this.modules.calculator.pause?.();
                }
            } else {
                // Pagina visibile - riprendi operazioni
                if (this.modules.calculator) {
                    this.modules.calculator.resume?.();
                }
            }
        }
        
        /**
         * Crea AJAX client legacy
         */
        createLegacyAjaxClient() {
            return {
                calculateV3: async (payload) => {
                    return await this.legacyAjaxRequest('btr_calculate_preventivo', { payload: JSON.stringify(payload) });
                },
                createPreventivo: async (data) => {
                    return await this.legacyAjaxRequest('btr_create_preventivo', data);
                },
                makeRequest: async (data) => {
                    return await this.legacyAjaxRequest(data.action, data);
                }
            };
        }
        
        /**
         * Crea calculator legacy
         */
        createLegacyCalculator() {
            return {
                triggerCalculation: (reason) => {
                    console.log('[LEGACY] Triggering legacy calculation:', reason);
                    // Fallback al sistema legacy se disponibile
                    if (window.btrBookingState?.validateWithUnifiedCalculator) {
                        window.btrBookingState.validateWithUnifiedCalculator();
                    }
                }
            };
        }
        
        /**
         * AJAX request legacy
         */
        legacyAjaxRequest(action, data) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: this.config.nonce,
                        ...data
                    },
                    timeout: this.config.timeout,
                    success: (response) => {
                        try {
                            const parsed = typeof response === 'string' ? JSON.parse(response) : response;
                            resolve(parsed);
                        } catch (e) {
                            reject(new Error('Invalid JSON response'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX Error: ${status} - ${error}`));
                    }
                });
            });
        }
        
        /**
         * Schedule lazy loading UI Components
         */
        scheduleUIComponentsLoad() {
            // Carica UI Components quando necessario
            const loadUIComponents = () => {
                if (!this.modules.uiComponents && window.BTRUIComponents) {
                    console.log('[MODULES] Loading UI Components (lazy)...');
                    const start = Date.now();
                    this.modules.uiComponents = new window.BTRUIComponents(this.modules.stateManager);
                    this.performanceMetrics.moduleLoadTimes.uiComponents = Date.now() - start;
                }
            };
            
            // Triggers per lazy loading
            $(document).one('mouseover keydown touchstart', loadUIComponents);
            setTimeout(loadUIComponents, 2000); // Fallback dopo 2 secondi
        }
        
        /**
         * Track performance
         */
        trackPerformance() {
            if (this.featureFlags.performanceMonitoring) {
                // Salva metriche in sessione per analisi
                try {
                    sessionStorage.setItem('btr_performance_metrics', JSON.stringify(this.performanceMetrics));
                } catch (e) {
                    console.warn('[PERF] Could not save performance metrics to session storage');
                }
            }
        }
        
        /**
         * Gestisce errore inizializzazione
         */
        handleInitializationError(error) {
            // Fallback al sistema legacy se possibile
            if (this.config.legacyFallback && window.btrBookingState) {
                console.warn('[INIT] Falling back to legacy system due to initialization error');
                
                // Mostra warning utente
                if (window.jQuery && window.jQuery.fn.length > 0) {
                    $('body').prepend(`
                        <div class="btr-legacy-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px; border-radius: 4px;">
                            <strong>Attenzione:</strong> Il sistema è in modalità compatibilità. Alcune funzionalità potrebbero essere limitate.
                        </div>
                    `);
                }
                
                return; // Don't throw, let legacy system handle
            }
            
            // Mostra errore critico
            const errorMessage = this.config.showDetailedErrors ? error.message : 'Errore di inizializzazione del sistema';
            
            if (window.jQuery && window.jQuery.fn.length > 0) {
                $('body').prepend(`
                    <div class="btr-error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px; border-radius: 4px;">
                        <strong>Errore:</strong> ${errorMessage}
                        <br><small>Ricarica la pagina o contatta l'assistenza se il problema persiste.</small>
                    </div>
                `);
            }
        }
        
        /**
         * Utility per formattare prezzi
         */
        formatPrice(amount, showCurrency = true) {
            const num = parseFloat(amount) || 0;
            const formatted = num.toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            return showCurrency ? `€ ${formatted}` : formatted;
        }
        
        /**
         * Utility per parsare prezzi
         */
        parsePrice(priceString) {
            if (typeof priceString !== 'string') return 0;
            
            let cleaned = priceString.replace(/[€\s]/g, '');
            
            if (cleaned.includes(',')) {
                const parts = cleaned.split(',');
                if (parts.length === 2 && parts[1].length <= 2) {
                    const integerPart = parts[0].replace(/\./g, '');
                    return parseFloat(integerPart + '.' + parts[1]) || 0;
                }
            }
            
            const dotIndex = cleaned.lastIndexOf('.');
            if (dotIndex > -1 && cleaned.length - dotIndex <= 3) {
                const beforeDot = cleaned.substring(0, dotIndex).replace(/\./g, '');
                const afterDot = cleaned.substring(dotIndex + 1);
                cleaned = beforeDot + '.' + afterDot;
            } else {
                cleaned = cleaned.replace(/\./g, '');
            }
            
            return parseFloat(cleaned) || 0;
        }
        
        /**
         * API pubblica per accesso ai moduli
         */
        getModule(moduleName) {
            return this.modules[moduleName];
        }
        
        /**
         * Status check
         */
        getStatus() {
            return {
                version: this.version,
                isInitialized: this.isInitialized,
                modules: Object.keys(this.modules).reduce((acc, key) => {
                    acc[key] = {
                        loaded: !!this.modules[key],
                        initialized: this.modules[key]?.isInitialized || false
                    };
                    return acc;
                }, {}),
                performance: this.performanceMetrics,
                config: this.config,
                featureFlags: this.featureFlags
            };
        }
    }
    
    // Auto-inizializzazione quando DOM è pronto
    $(document).ready(function() {
        // Controlla se deve essere inizializzato
        if ($('#btr-booking-form, .btr-preventivo-form, [data-btr-app]').length > 0) {
            console.log('📋 BTR Booking form detected, initializing app...');
            window.btrApp = new BTRBookingApp();
        }
    });
    
    // Export globale
    window.BTRBookingApp = BTRBookingApp;
    
})(window.jQuery || window.$, window, document);