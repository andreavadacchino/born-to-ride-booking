/**
 * BTR UI Components - Componenti UI riusabili e ottimizzati
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR UI Components Class
     */
    class BTRUIComponents {
        
        constructor(stateManager) {
            this.version = '3.0.0';
            this.stateManager = stateManager;
            this.components = {};
            this.observers = [];
            this.lazyLoadQueue = [];
            this.intersectionObserver = null;
            
            this.init();
        }
        
        /**
         * Inizializza UI Components
         */
        init() {
            this.setupIntersectionObserver();
            this.initializeComponents();
            this.bindUIEvents();
            
            console.log('BTR UI Components v3.0 initialized');
        }
        
        /**
         * Setup Intersection Observer per lazy loading
         */
        setupIntersectionObserver() {
            if ('IntersectionObserver' in window) {
                this.intersectionObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadComponent(entry.target);
                            this.intersectionObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px'
                });
            }
        }
        
        /**
         * Inizializza componenti UI
         */
        initializeComponents() {
            // Tooltips
            this.initTooltips();
            
            // Input masks
            this.initInputMasks();
            
            // Price displays
            this.initPriceDisplays();
            
            // Progress indicators
            this.initProgressIndicators();
            
            // Collapsible sections
            this.initCollapsibleSections();
            
            // Virtual scrolling per liste lunghe
            this.initVirtualScrolling();
            
            // Loading states
            this.initLoadingStates();
            
            // Notification system
            this.initNotifications();
        }
        
        /**
         * Bind eventi UI generali
         */
        bindUIEvents() {
            const self = this;
            
            // Gestione responsive
            $(window).on('resize', this.debounce(() => {
                self.handleResize();
            }, 250));
            
            // Focus management
            $(document).on('keydown', function(e) {
                if (e.key === 'Tab') {
                    self.manageFocus(e);
                }
            });
            
            // Accessibilità keyboard
            this.setupKeyboardNavigation();
            
            // Update UI quando stato cambia
            this.stateManager.on('state.updated', (data) => {
                self.updateComponentsWithState(data);
            });
        }
        
        /**
         * Inizializza tooltips
         */
        initTooltips() {
            const $tooltips = $('.btr-tooltip, [data-tooltip]');
            
            $tooltips.each(function() {
                const $element = $(this);
                const tooltipText = $element.data('tooltip') || $element.attr('title');
                
                if (tooltipText) {
                    // Rimuovi title per evitare tooltip browser nativo
                    $element.removeAttr('title');
                    
                    // Crea tooltip personalizzato
                    $element.on('mouseenter focus', function() {
                        this.showTooltip($element, tooltipText);
                    }.bind(this));
                    
                    $element.on('mouseleave blur', function() {
                        this.hideTooltip();
                    }.bind(this));
                }
            }.bind(this));
        }
        
        /**
         * Mostra tooltip
         */
        showTooltip($element, text) {
            this.hideTooltip(); // Nascondi eventuali tooltip aperti
            
            const $tooltip = $(`<div class="btr-tooltip-popup">${text}</div>`);
            $('body').append($tooltip);
            
            // Posiziona tooltip
            const elementRect = $element[0].getBoundingClientRect();
            const tooltipRect = $tooltip[0].getBoundingClientRect();
            
            let top = elementRect.bottom + window.scrollY + 5;
            let left = elementRect.left + window.scrollX + (elementRect.width / 2) - (tooltipRect.width / 2);
            
            // Adjust per viewport
            if (left < 10) left = 10;
            if (left + tooltipRect.width > window.innerWidth - 10) {
                left = window.innerWidth - tooltipRect.width - 10;
            }
            
            if (top + tooltipRect.height > window.innerHeight + window.scrollY - 10) {
                top = elementRect.top + window.scrollY - tooltipRect.height - 5;
                $tooltip.addClass('tooltip-above');
            }
            
            $tooltip.css({ top: top, left: left }).fadeIn(200);
        }
        
        /**
         * Nascondi tooltip
         */
        hideTooltip() {
            $('.btr-tooltip-popup').fadeOut(200, function() {
                $(this).remove();
            });
        }
        
        /**
         * Inizializza input masks
         */
        initInputMasks() {
            // Telefono italiano
            $('input[type="tel"], input[name*="phone"]').each(function() {
                const $input = $(this);
                
                $input.on('input', function() {
                    let value = $input.val().replace(/\D/g, '');
                    
                    // Format telefono italiano
                    if (value.length > 0) {
                        if (value.length <= 3) {
                            value = value;
                        } else if (value.length <= 6) {
                            value = value.slice(0, 3) + ' ' + value.slice(3);
                        } else if (value.length <= 10) {
                            value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6);
                        } else {
                            value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
                        }
                    }
                    
                    $input.val(value);
                });
            });
            
            // Codice fiscale
            $('input[name*="fiscal_code"]').each(function() {
                const $input = $(this);
                
                $input.on('input', function() {
                    let value = $input.val().toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (value.length > 16) value = value.slice(0, 16);
                    $input.val(value);
                });
            });
            
            // Prezzi con formato italiano
            $('input[type="number"][data-format="price"]').each(function() {
                const $input = $(this);
                
                $input.on('blur', function() {
                    const value = parseFloat($input.val()) || 0;
                    $input.val(value.toFixed(2));
                });
            });
        }
        
        /**
         * Inizializza display prezzi
         */
        initPriceDisplays() {
            const self = this;
            
            $('.price-display, .btr-price').each(function() {
                const $element = $(this);
                const price = parseFloat($element.data('price') || $element.text().replace(/[€\s]/g, '')) || 0;
                
                // Format iniziale
                self.updatePriceDisplay($element, price);
                
                // Animazione se prezzo cambia
                $element.data('original-price', price);
            });
        }
        
        /**
         * Aggiorna display prezzo
         */
        updatePriceDisplay($element, newPrice, animate = false) {
            const formattedPrice = this.formatPrice(newPrice);
            const oldPrice = parseFloat($element.data('original-price')) || 0;
            
            if (animate && oldPrice !== newPrice) {
                // Animazione cambio prezzo
                $element.addClass('price-changing');
                
                setTimeout(() => {
                    $element.text(formattedPrice).removeClass('price-changing');
                }, 150);
                
                // Evidenzia differenza
                if (newPrice > oldPrice) {
                    $element.addClass('price-increased').removeClass('price-decreased');
                } else if (newPrice < oldPrice) {
                    $element.addClass('price-decreased').removeClass('price-increased');
                }
                
                setTimeout(() => {
                    $element.removeClass('price-increased price-decreased');
                }, 2000);
            } else {
                $element.text(formattedPrice);
            }
            
            $element.data('original-price', newPrice);
        }
        
        /**
         * Inizializza indicatori progresso
         */
        initProgressIndicators() {
            $('.progress-bar, .btr-progress').each(function() {
                const $progress = $(this);
                const value = parseFloat($progress.data('value')) || 0;
                const max = parseFloat($progress.data('max')) || 100;
                
                this.updateProgressBar($progress, value, max);
            }.bind(this));
        }
        
        /**
         * Aggiorna barra progresso
         */
        updateProgressBar($progress, value, max = 100, animate = true) {
            const percentage = Math.min(Math.max((value / max) * 100, 0), 100);
            const $fill = $progress.find('.progress-fill');
            
            if ($fill.length === 0) {
                $progress.html(`<div class="progress-fill" style="width: 0%"></div>`);
                $fill = $progress.find('.progress-fill');
            }
            
            if (animate) {
                $fill.animate({ width: percentage + '%' }, 500);
            } else {
                $fill.css('width', percentage + '%');
            }
            
            // Aggiorna aria attributes per accessibilità
            $progress.attr({
                'aria-valuenow': value,
                'aria-valuemax': max,
                'aria-valuemmin': 0
            });
        }
        
        /**
         * Inizializza sezioni collassabili
         */
        initCollapsibleSections() {
            $('.btr-collapsible').each(function() {
                const $section = $(this);
                const $trigger = $section.find('.collapsible-trigger');
                const $content = $section.find('.collapsible-content');
                const isOpen = $section.hasClass('open');
                
                // Setup iniziale
                if (!isOpen) {
                    $content.hide();
                }
                
                $trigger.on('click', function(e) {
                    e.preventDefault();
                    this.toggleCollapsible($section);
                }.bind(this));
                
                // Keyboard accessibility
                $trigger.on('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.toggleCollapsible($section);
                    }
                }.bind(this));
            }.bind(this));
        }
        
        /**
         * Toggle sezione collassabile
         */
        toggleCollapsible($section) {
            const $content = $section.find('.collapsible-content');
            const $trigger = $section.find('.collapsible-trigger');
            const isOpen = $section.hasClass('open');
            
            if (isOpen) {
                $content.slideUp(300);
                $section.removeClass('open');
                $trigger.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(300);
                $section.addClass('open');
                $trigger.attr('aria-expanded', 'true');
            }
        }
        
        /**
         * Inizializza virtual scrolling per liste lunghe
         */
        initVirtualScrolling() {
            $('.virtual-scroll-container').each(function() {
                const $container = $(this);
                const itemHeight = parseInt($container.data('item-height')) || 50;
                const items = $container.data('items') || [];
                
                if (items.length > 100) {
                    this.setupVirtualScrolling($container, items, itemHeight);
                }
            }.bind(this));
        }
        
        /**
         * Setup virtual scrolling
         */
        setupVirtualScrolling($container, items, itemHeight) {
            const containerHeight = $container.height();
            const visibleCount = Math.ceil(containerHeight / itemHeight) + 5; // Buffer
            const totalHeight = items.length * itemHeight;
            
            let scrollTop = 0;
            let startIndex = 0;
            let endIndex = Math.min(visibleCount, items.length);
            
            // Crea container virtuale
            $container.html(`
                <div class="virtual-scroll-spacer" style="height: ${totalHeight}px;">
                    <div class="virtual-scroll-viewport"></div>
                </div>
            `);
            
            const $viewport = $container.find('.virtual-scroll-viewport');
            
            const renderItems = () => {
                const visibleItems = items.slice(startIndex, endIndex);
                let html = '';
                
                visibleItems.forEach((item, index) => {
                    const actualIndex = startIndex + index;
                    html += this.renderVirtualItem(item, actualIndex, itemHeight);
                });
                
                $viewport.html(html).css('transform', `translateY(${startIndex * itemHeight}px)`);
            };
            
            $container.on('scroll', () => {
                scrollTop = $container.scrollTop();
                startIndex = Math.floor(scrollTop / itemHeight);
                endIndex = Math.min(startIndex + visibleCount, items.length);
                
                renderItems();
            });
            
            // Render iniziale
            renderItems();
        }
        
        /**
         * Render item virtuale
         */
        renderVirtualItem(item, index, height) {
            return `
                <div class="virtual-item" data-index="${index}" style="height: ${height}px;">
                    <div class="item-content">
                        ${typeof item === 'string' ? item : JSON.stringify(item)}
                    </div>
                </div>
            `;
        }
        
        /**
         * Inizializza stati loading
         */
        initLoadingStates() {
            // Loading buttons
            $('.btn-loading').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true)
                    .addClass('loading')
                    .data('original-text', originalText)
                    .html('<span class="spinner"></span> Caricamento...');
            });
            
            // Loading overlays
            $('.loading-overlay').hide();
        }
        
        /**
         * Mostra loading
         */
        showLoading($element, message = 'Caricamento...') {
            const $loading = $element.find('.loading-overlay');
            
            if ($loading.length === 0) {
                $element.append(`
                    <div class="loading-overlay">
                        <div class="loading-spinner"></div>
                        <div class="loading-message">${message}</div>
                    </div>
                `);
            } else {
                $loading.find('.loading-message').text(message);
            }
            
            $element.addClass('is-loading');
            $element.find('.loading-overlay').show();
        }
        
        /**
         * Nascondi loading
         */
        hideLoading($element) {
            $element.removeClass('is-loading');
            $element.find('.loading-overlay').hide();
            
            // Reset loading buttons
            $element.find('.btn-loading.loading').each(function() {
                const $btn = $(this);
                const originalText = $btn.data('original-text');
                
                $btn.prop('disabled', false)
                    .removeClass('loading')
                    .html(originalText);
            });
        }
        
        /**
         * Inizializza sistema notifiche
         */
        initNotifications() {
            // Crea container notifiche se non esiste
            if ($('.btr-notifications').length === 0) {
                $('body').append('<div class="btr-notifications"></div>');
            }
        }
        
        /**
         * Mostra notifica
         */
        showNotification(message, type = 'info', duration = 5000) {
            if (typeof window.showNotification === 'function') {
                return window.showNotification(message, type, duration, true);
            }
            const $container = $('.btr-notifications');
            const notificationId = 'notification_' + Date.now();
            
            const $notification = $(`
                <div class="notification notification-${type}" id="${notificationId}">
                    <div class="notification-content">
                        <span class="notification-message">${message}</span>
                        <button class="notification-close" aria-label="Chiudi">&times;</button>
                    </div>
                </div>
            `);
            
            $container.append($notification);
            
            // Animazione entrata
            setTimeout(() => {
                $notification.addClass('show');
            }, 10);
            
            // Auto-remove
            if (duration > 0) {
                setTimeout(() => {
                    this.hideNotification(notificationId);
                }, duration);
            }
            
            // Click per chiudere
            $notification.find('.notification-close').on('click', () => {
                this.hideNotification(notificationId);
            });
            
            return notificationId;
        }
        
        /**
         * Nascondi notifica
         */
        hideNotification(notificationId) {
            const $notification = $(`#${notificationId}`);
            
            $notification.addClass('hide');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }
        
        /**
         * Setup navigazione keyboard
         */
        setupKeyboardNavigation() {
            // Focus trap per modal
            $(document).on('keydown', '.modal.active', function(e) {
                if (e.key === 'Escape') {
                    $(this).removeClass('active');
                }
                
                // Tab trapping
                const focusableElements = $(this).find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const firstElement = focusableElements.first();
                const lastElement = focusableElements.last();
                
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement[0]) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement[0]) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
            });
        }
        
        /**
         * Gestisce focus management
         */
        manageFocus(e) {
            // Skip invisible elements
            const focusableElements = $('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
            
            if (focusableElements.length === 0) return;
            
            const currentIndex = focusableElements.index(document.activeElement);
            
            if (e.shiftKey) {
                // Shift + Tab (backward)
                if (currentIndex <= 0) {
                    e.preventDefault();
                    focusableElements.last().focus();
                }
            } else {
                // Tab (forward)
                if (currentIndex >= focusableElements.length - 1) {
                    e.preventDefault();
                    focusableElements.first().focus();
                }
            }
        }
        
        /**
         * Aggiorna componenti con stato
         */
        updateComponentsWithState(stateData) {
            // Aggiorna prezzi
            if (stateData.totale_generale !== undefined) {
                $('.price-display[data-bind="totale_generale"]').each(function() {
                    this.updatePriceDisplay($(this), stateData.totale_generale, true);
                }.bind(this));
            }
            
            // Aggiorna progress bars
            Object.entries(stateData).forEach(([key, value]) => {
                $(`.progress-bar[data-bind="${key}"]`).each(function() {
                    const max = parseFloat($(this).data('max')) || 100;
                    this.updateProgressBar($(this), value, max);
                }.bind(this));
            }.bind(this));
        }
        
        /**
         * Gestisce resize
         */
        handleResize() {
            // Ricalcola tooltip positions
            $('.btr-tooltip-popup').remove();
            
            // Aggiorna virtual scrolling
            $('.virtual-scroll-container').each(function() {
                // Re-calculate visible items
                const $container = $(this);
                if ($container.data('virtual-scrolling')) {
                    // Trigger recalculation
                    $container.trigger('scroll');
                }
            });
        }
        
        /**
         * Load componente lazy
         */
        loadComponent(element) {
            const $element = $(element);
            const componentType = $element.data('component');
            const componentData = $element.data('component-data') || {};
            
            console.log('[UI] Loading lazy component:', componentType);
            
            switch (componentType) {
                case 'price-calculator':
                    this.loadPriceCalculator($element, componentData);
                    break;
                case 'room-selector':
                    this.loadRoomSelector($element, componentData);
                    break;
                case 'participant-form':
                    this.loadParticipantForm($element, componentData);
                    break;
            }
        }
        
        /**
         * Formatta prezzo
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
         * Debounce utility
         */
        debounce(func, wait) {
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
        
        /**
         * Pulisci componenti
         */
        cleanup() {
            // Remove event listeners
            this.observers.forEach(observer => {
                if (observer.disconnect) observer.disconnect();
            });
            
            // Clear timeouts
            $('.btr-tooltip-popup').remove();
            
            // Clear intersection observer
            if (this.intersectionObserver) {
                this.intersectionObserver.disconnect();
            }
        }
        
        /**
         * Debug info
         */
        getDebugInfo() {
            return {
                version: this.version,
                componentsCount: Object.keys(this.components).length,
                observersCount: this.observers.length,
                lazyLoadQueueLength: this.lazyLoadQueue.length
            };
        }
    }
    
    // Export globalmente
    window.BTRUIComponents = BTRUIComponents;
    
})(window.jQuery || window.$, window, document);
