/**
 * Sistema dinamico categorie child - Supporto Frontend
 * 
 * Sostituisce le configurazioni hardcoded F1-F4 con categorie dinamiche
 * configurabili dall'admin
 * 
 * @since 1.0.15
 */

(function($) {
    'use strict';

    // Configurazione globale per categorie child dinamiche
    window.BTRDynamicChildCategories = {
        
        /**
         * Ottiene configurazione delle categorie child
         */
        getConfig: function() {
            return window.btrChildCategories || {
                categories: {},
                labels: {},
                ageRanges: {}
            };
        },

        /**
         * Ottiene tutte le categorie abilitate
         */
        getEnabledCategories: function() {
            const config = this.getConfig();
            const categories = [];
            
            for (const id in config.categories) {
                if (config.categories[id].enabled) {
                    categories.push(config.categories[id]);
                }
            }
            
            // Ordina per campo 'order'
            return categories.sort((a, b) => (a.order || 999) - (b.order || 999));
        },

        /**
         * Ottiene etichetta per una categoria
         */
        getLabel: function(categoryId, fallback) {
            const config = this.getConfig();
            return config.labels[categoryId] || fallback || 'Bambino';
        },

        /**
         * Calcola prezzo child dinamico
         */
        calculateChildPrice: function(categoryId, adultPrice) {
            const config = this.getConfig();
            const category = config.categories[categoryId];
            
            if (!category) {
                return adultPrice;
            }
            
            let childPrice = adultPrice;
            
            switch (category.discount_type) {
                case 'percentage':
                    const discount = (adultPrice * category.discount_value) / 100;
                    childPrice = Math.max(0, adultPrice - discount);
                    break;
                case 'fixed':
                    childPrice = Math.max(0, adultPrice - category.discount_value);
                    break;
                case 'absolute':
                    childPrice = category.discount_value;
                    break;
            }
            
            return childPrice;
        },

        /**
         * Genera selettori per campi child basati su configurazione dinamica
         */
        generateChildSelectors: function() {
            const categories = this.getEnabledCategories();
            const selectors = {};
            
            categories.forEach(function(category) {
                selectors[category.id] = '#btr_num_child_' + category.id;
            });
            
            return selectors;
        },

        /**
         * Conta totale bambini dalle categorie attive
         */
        countTotalChildren: function() {
            const categories = this.getEnabledCategories();
            let total = 0;
            
            categories.forEach(function(category) {
                const $field = $('#btr_num_child_' + category.id);
                if ($field.length) {
                    total += parseInt($field.val(), 10) || 0;
                }
            });
            
            return total;
        },

        /**
         * Ottiene array di conteggi per tutte le categorie
         */
        getChildCounts: function() {
            const categories = this.getEnabledCategories();
            const counts = {};
            
            categories.forEach(function(category) {
                const $field = $('#btr_num_child_' + category.id);
                counts[category.id] = $field.length ? parseInt($field.val(), 10) || 0 : 0;
            });
            
            return counts;
        },

        /**
         * Genera parametri AJAX per categorie child
         */
        generateAjaxParams: function() {
            const categories = this.getEnabledCategories();
            const params = {};
            
            categories.forEach(function(category) {
                const $field = $('#btr_num_child_' + category.id);
                params['num_child_' + category.id] = $field.length ? parseInt($field.val(), 10) || 0 : 0;
            });
            
            return params;
        },

        /**
         * Genera HTML per bottoni assignment bambini
         */
        generateChildAssignmentButtons: function(icona_child) {
            const categories = this.getEnabledCategories();
            const counts = this.getChildCounts();
            let html = '';
            
            categories.forEach(function(category) {
                const count = counts[category.id];
                if (count > 0) {
                    html += `<div class="child-group child-group-${category.id}">`;
                    html += `<div class="child-group-label">`;
                    html += `${icona_child} <strong>${category.label}</strong>`;
                    html += `</div>`;
                    
                    for (let i = 0; i < count; i++) {
                        html += `<label class="assignment-button" data-person-type="child">`;
                        html += `<input type="radio" name="assignment_${category.id}_${i}" value="" data-person-id="${category.id}-${i}">`;
                        html += `<span class="assignment-text">Bambino ${i + 1}</span>`;
                        html += `</label>`;
                    }
                    
                    html += `</div>`;
                }
            });
            
            return html;
        },

        /**
         * Genera display prezzi per categorie child
         */
        generateChildPriceDisplay: function(roomData) {
            const categories = this.getEnabledCategories();
            const counts = this.getChildCounts();
            let html = '';
            
            categories.forEach(function(category) {
                const count = counts[category.id];
                if (count > 0) {
                    const rawPrice = parseFloat(roomData['price_child_' + category.id]) || roomData.price_adult;
                    const childPrice = BTRDynamicChildCategories.calculateChildPrice(category.id, roomData.price_adult);
                    
                    html += `<div class="btr-price-item child-price-${category.id}">`;
                    html += `<span class="btr-discount-label btr-label-price">Bambini ${category.label}:</span>`;
                    html += `<span class="btr-price-amount">€${childPrice.toFixed(2)} x ${count}</span>`;
                    html += `<span class="btr-price-total">(€${(childPrice * count).toFixed(2)})</span>`;
                    html += `</div>`;
                }
            });
            
            return html;
        },

        /**
         * Processa assignment dei bambini per il calcolo prezzi
         */
        processChildAssignments: function($room) {
            const categories = this.getEnabledCategories();
            const assignments = {};
            
            // Inizializza contatori
            categories.forEach(function(category) {
                assignments[category.id] = 0;
            });
            
            // Conta assignment per ogni categoria
            $room.find('input[name^="assignment_"]:checked').each(function() {
                const personId = $(this).data('person-id');
                if (personId) {
                    const categoryId = personId.split('-')[0];
                    if (assignments.hasOwnProperty(categoryId)) {
                        assignments[categoryId]++;
                    }
                }
            });
            
            return assignments;
        },

        /**
         * Genera riepilogo per categoria child nel summary
         */
        generateChildSummary: function(assignments, roomType, supplementoPP) {
            const categories = this.getEnabledCategories();
            let html = '';
            
            categories.forEach(function(category) {
                const count = assignments[category.id] || 0;
                if (count > 0) {
                    const pricePerChild = BTRDynamicChildCategories.calculateChildPrice(category.id, 0); // Sarà calcolato dinamicamente
                    const total = count * pricePerChild;
                    
                    html += `<tr class="btr-price-row">`;
                    html += `<td>${count}x Bambino ${category.label}</td>`;
                    html += `<td class="camera-type">${roomType}</td>`;
                    html += `<td class="btr-price-cell">€${pricePerChild.toFixed(2)}</td>`;
                    html += `<td class="btr-price-cell">€${total.toFixed(2)}</td>`;
                    html += `</tr>`;
                    
                    // Riga notte extra se presente
                    if (supplementoPP > 0) {
                        const extraNightPrice = supplementoPP / 2; // Metà prezzo per bambini
                        html += `<tr class="btr-price-row extra-night">`;
                        html += `<td>${count}x Notte Extra ${category.label}</td>`;
                        html += `<td class="camera-type">${roomType}</td>`;
                        html += `<td class="btr-price-cell">€${extraNightPrice.toFixed(2)}</td>`;
                        html += `<td class="btr-price-cell">€${(extraNightPrice * count).toFixed(2)}</td>`;
                        html += `</tr>`;
                    }
                }
            });
            
            return html;
        },

        /**
         * Valida che tutti i bambini siano assegnati
         */
        validateChildAssignments: function() {
            const counts = this.getChildCounts();
            const categories = this.getEnabledCategories();
            let totalExpected = 0;
            let totalAssigned = 0;
            
            categories.forEach(function(category) {
                totalExpected += counts[category.id] || 0;
            });
            
            $('.btr-room-option input[name^="assignment_"]:checked').each(function() {
                const personId = $(this).data('person-id');
                if (personId && personId.includes('-')) {
                    totalAssigned++;
                }
            });
            
            return totalExpected === totalAssigned;
        },

        /**
         * Inizializza sistema quando DOM è pronto
         */
        init: function() {
            // Verifica se le configurazioni sono disponibili
            if (!window.btrChildCategories) {
                console.warn('BTR Dynamic Child Categories: Configurazioni non trovate');
                return;
            }
            
            console.log('BTR Dynamic Child Categories: Sistema inizializzato', this.getConfig());
            
            // Sostituisce funzioni globali esistenti se presenti
            if (window.getChildLabel) {
                window.getChildLabel = this.getLabel.bind(this);
            }
            
            // Aggiunge metodi globali per compatibilità
            window.BTRDynamicChildCategories = this;
        }
    };

    /**
     * Funzioni di compatibilità per sostituire quelle hardcoded
     */
    
    // Sostituisce getChildLabel esistente
    window.getDynamicChildLabel = function(categoryId, fallback) {
        return window.BTRDynamicChildCategories.getLabel(categoryId, fallback);
    };
    
    // Funzione per ottenere price child dinamico (sostituisce getChildPrice)
    window.getDynamicChildPrice = function(categoryId, adultPrice) {
        return window.BTRDynamicChildCategories.calculateChildPrice(categoryId, adultPrice);
    };

    // Inizializza quando DOM è pronto
    $(document).ready(function() {
        window.BTRDynamicChildCategories.init();
    });

})(jQuery);