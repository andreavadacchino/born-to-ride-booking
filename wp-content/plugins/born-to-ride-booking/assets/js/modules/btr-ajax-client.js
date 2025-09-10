/**
 * BTR AJAX Client v3.0 - Comunicazione con backend
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR AJAX Client Class
     */
    class BTRAjaxClient {
        
        constructor() {
            this.version = '3.0.0';
            this.endpoints = {
                calculate: 'btr_v3_calculate',
                save_state: 'btr_v3_save_state',
                validate: 'btr_v3_validate',
                create_preventivo: 'btr_create_preventivo',
                legacy_calculate: 'btr_calculate_preventivo'
            };
            this.retryAttempts = 3;
            this.retryDelay = 1000;
            this.cache = new Map();
            this.pendingRequests = new Map();
            
            this.init();
        }
        
        /**
         * Inizializza il client
         */
        init() {
            // Setup request interceptors
            this.setupInterceptors();
            
            // Setup cache cleanup
            setInterval(() => this.cleanupCache(), 5 * 60 * 1000); // 5 minuti
            
            console.log('BTR AJAX Client v3.0 initialized');
        }
        
        /**
         * Setup interceptors per debugging
         */
        setupInterceptors() {
            const self = this;
            
            // Log all AJAX requests
            $(document).ajaxSend(function(event, xhr, settings) {
                if (settings.data && settings.data.includes('btr_')) {
                    console.log('[AJAX OUT]', settings.data);
                }
            });
            
            // Log all AJAX responses
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.data && settings.data.includes('btr_')) {
                    console.log('[AJAX IN]', xhr.responseText);
                }
            });
        }
        
        /**
         * Calcolo v3 con Unified Calculator
         */
        async calculateV3(payload, options = {}) {
            const cacheKey = this.generateCacheKey('calculate', payload);
            
            // Check cache
            if (!options.skipCache && this.cache.has(cacheKey)) {
                console.log('[AJAX] Using cached calculation');
                return this.cache.get(cacheKey);
            }
            
            // Check pending requests
            if (this.pendingRequests.has(cacheKey)) {
                console.log('[AJAX] Waiting for pending calculation');
                return await this.pendingRequests.get(cacheKey);
            }
            
            const requestPromise = this.makeRequest({
                action: this.endpoints.calculate,
                payload: JSON.stringify(payload),
                nonce: window.btr_ajax.nonce
            }, options);
            
            this.pendingRequests.set(cacheKey, requestPromise);
            
            try {
                const result = await requestPromise;
                
                // Cache successful results
                if (result.success) {
                    this.cache.set(cacheKey, result, Date.now() + (5 * 60 * 1000)); // 5 min cache
                }
                
                this.pendingRequests.delete(cacheKey);
                return result;
                
            } catch (error) {
                this.pendingRequests.delete(cacheKey);
                throw error;
            }
        }
        
        /**
         * Salvataggio stato v3
         */
        async saveStateV3(stateData, options = {}) {
            return await this.makeRequest({
                action: this.endpoints.save_state,
                state_data: JSON.stringify(stateData),
                nonce: window.btr_ajax.nonce
            }, options);
        }
        
        /**
         * Validazione v3
         */
        async validateV3(payload, options = {}) {
            return await this.makeRequest({
                action: this.endpoints.validate,
                payload: JSON.stringify(payload),
                nonce: window.btr_ajax.nonce
            }, options);
        }
        
        /**
         * Creazione preventivo (legacy)
         */
        async createPreventivo(formData, options = {}) {
            // Fallback per compatibilità
            return await this.makeRequest({
                action: this.endpoints.create_preventivo,
                ...formData,
                nonce: window.btr_ajax.nonce
            }, {
                ...options,
                skipRetry: true // No retry per operazioni critiche
            });
        }
        
        /**
         * Calcolo legacy (fallback)
         */
        async calculateLegacy(payload, options = {}) {
            console.warn('[AJAX] Using legacy calculation endpoint');
            return await this.makeRequest({
                action: this.endpoints.legacy_calculate,
                payload: JSON.stringify(payload),
                nonce: window.btr_ajax.nonce
            }, options);
        }
        
        /**
         * Richiesta AJAX con retry logic
         */
        async makeRequest(data, options = {}) {
            const {
                retryAttempts = this.retryAttempts,
                retryDelay = this.retryDelay,
                timeout = 15000,
                skipRetry = false
            } = options;
            
            const maxAttempts = skipRetry ? 1 : retryAttempts;
            
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                try {
                    const response = await this.performRequest(data, timeout);
                    
                    if (!response.success && attempt < maxAttempts) {
                        console.warn(`[AJAX] Request failed (attempt ${attempt}/${maxAttempts}):`, response);
                        await this.sleep(retryDelay * attempt);
                        continue;
                    }
                    
                    return response;
                    
                } catch (error) {
                    if (attempt < maxAttempts) {
                        console.warn(`[AJAX] Network error (attempt ${attempt}/${maxAttempts}):`, error);
                        await this.sleep(retryDelay * attempt);
                        continue;
                    }
                    
                    throw this.createAjaxError(error, data);
                }
            }
        }
        
        /**
         * Esegue richiesta AJAX
         */
        performRequest(data, timeout) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: window.btr_ajax.url,
                    type: 'POST',
                    data: data,
                    timeout: timeout,
                    success: function(response) {
                        try {
                            const parsed = typeof response === 'string' ? JSON.parse(response) : response;
                            resolve(parsed);
                        } catch (e) {
                            reject(new Error('Invalid JSON response: ' + response));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error(`AJAX Error: ${status} - ${error}`));
                    }
                });
            });
        }
        
        /**
         * Genera chiave cache
         */
        generateCacheKey(action, data) {
            const serialized = typeof data === 'object' ? JSON.stringify(data) : data;
            return `${action}_${this.hashString(serialized)}`;
        }
        
        /**
         * Hash semplice per cache keys
         */
        hashString(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }
            return Math.abs(hash).toString(36);
        }
        
        /**
         * Cleanup cache scadute
         */
        cleanupCache() {
            const now = Date.now();
            for (const [key, { timestamp }] of this.cache.entries()) {
                if (timestamp && now > timestamp) {
                    this.cache.delete(key);
                }
            }
        }
        
        /**
         * Crea errore AJAX strutturato
         */
        createAjaxError(error, requestData) {
            return {
                type: 'ajax_error',
                message: error.message,
                requestData: requestData,
                timestamp: Date.now()
            };
        }
        
        /**
         * Sleep utility
         */
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        /**
         * Reset cache
         */
        clearCache() {
            this.cache.clear();
            this.pendingRequests.clear();
            console.log('[AJAX] Cache cleared');
        }
        
        /**
         * Debug info
         */
        getDebugInfo() {
            return {
                version: this.version,
                cacheSize: this.cache.size,
                pendingRequests: this.pendingRequests.size,
                endpoints: this.endpoints
            };
        }
    }
    
    // Export globalmente
    window.BTRAjaxClient = BTRAjaxClient;
    
    // Auto-inizializzazione se jQuery disponibile
    if (window.jQuery) {
        $(document).ready(function() {
            if (!window.btrAjaxClient) {
                window.btrAjaxClient = new BTRAjaxClient();
            }
        });
    }
    
})(window.jQuery || window.$, window, document);