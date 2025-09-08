/**
 * Dynamic Summary Panel - Sistema per gestire il pannello di riepilogo dinamicamente
 * 
 * Gestisce lo spostamento del pannello .btr-summary-box al fondo del form
 * e lo mantiene aggiornato con tutti i costi extra selezionati
 * 
 * @version 1.0.179
 * @since 2025-01-09
 * @updated 2025-01-23 - Fix parsing formato italiano (€1.039,90)
 */

(function($) {
    'use strict';
    
    console.log('[DYNAMIC SUMMARY] Inizializzazione pannello dinamico...');
    
    // Stato del pannello
    let panelState = {
        originalParent: null,
        isMovedToBottom: false,
        extraCosts: {},
        baseTotal: 0,
        finalTotal: 0
    };
    
    // Funzione per calcolare i costi extra totali (supporta valori negativi per riduzioni)
    function calculateExtraCosts() {
        let totalExtra = 0;
        panelState.extraCosts = {}; // Reset costi extra con struttura {nome: {total: importo, count: quantità, unitPrice: prezzo_unitario}}
        
        // Cerca tutti i checkbox dei costi extra nel form anagrafici
        $('input[type="checkbox"][name*="costi_extra"]:checked').each(function() {
            const $this = $(this);
            const checkboxName = $this.attr('name');
            
            // Estrai l'indice e lo slug dal nome del checkbox
            // Formato: anagrafici[index][costi_extra][slug]
            const matches = checkboxName.match(/anagrafici\[(\d+)\]\[costi_extra\]\[([^\]]+)\]/);
            if (!matches) return;
            
            const index = matches[1];
            const slug = matches[2];
            
            let importo = 0;
            
            // Metodo 1: Prova prima con data-importo (più efficiente)
            if ($this.data('importo') !== undefined) {
                importo = parseFloat($this.data('importo')) || 0;
                console.log('[DYNAMIC SUMMARY] Importo da data-importo:', importo, 'per', slug);
            }
            
            // Metodo 2: Se non trovato, cerca il campo hidden
            if (importo === 0) {
                const hiddenSelector = `input[type="hidden"][name="anagrafici[${index}][costi_extra_dettagliate][${slug}][importo]"]`;
                const $hiddenField = $(hiddenSelector);
                
                if ($hiddenField.length) {
                    importo = parseFloat($hiddenField.val()) || 0;
                    console.log('[DYNAMIC SUMMARY] Importo da hidden field:', importo, 'per', slug);
                }
            }
            
            // L'importo può essere già negativo (riduzioni)
            if (importo !== 0) {
                // Pulisci il nome per la visualizzazione
                let cleanName = slug.replace(/[-_]/g, ' ');
                cleanName = cleanName.replace(/\b\w/g, l => l.toUpperCase());
                
                // Se è già presente, incrementa quantità e somma (per costi multipli dello stesso tipo)
                if (panelState.extraCosts[cleanName]) {
                    panelState.extraCosts[cleanName].total += importo;
                    panelState.extraCosts[cleanName].count += 1;
                } else {
                    panelState.extraCosts[cleanName] = {
                        total: importo,
                        count: 1,
                        unitPrice: importo
                    };
                }
                totalExtra += importo;
                
                console.log('[DYNAMIC SUMMARY] Costo extra aggiunto:', cleanName, 'Importo:', importo, 'Count:', panelState.extraCosts[cleanName].count, 'Totale parziale:', totalExtra);
            }
        });
        
        // Cerca anche checkbox generici con pattern noti
        $('input[type="checkbox"]:checked').each(function() {
            const $this = $(this);
            const name = $this.attr('name') || '';
            
            // Pattern per culla, animale domestico, etc.
            if (name.includes('culla') || name.includes('animale') || 
                name.includes('extra') || name.includes('supplemento')) {
                
                // Se non già contato sopra
                if (!$this.data('cost-slug')) {
                    let cost = parseFloat($this.data('cost') || $this.data('price') || $this.data('importo') || 0);
                    
                    // Se non ha attributo cost, prova a dedurlo dal nome
                    if (cost === 0) {
                        if (name.includes('culla')) cost = 15;
                        else if (name.includes('animale')) cost = 10;
                    }
                    
                    if (cost > 0 && !panelState.extraCosts[name]) {
                        panelState.extraCosts[name] = {
                            total: cost,
                            count: 1,
                            unitPrice: cost
                        };
                        totalExtra += cost;
                    }
                }
            }
        });
        
        // Cerca select dei costi extra (supporta valori negativi)
        $('.btr-extra-cost-select, select[data-cost-slug]').each(function() {
            const $select = $(this);
            const selectedOption = $select.find('option:selected');
            const cost = parseFloat(selectedOption.data('cost') || selectedOption.data('price') || selectedOption.data('importo') || 0);
            if (cost !== 0) {
                const name = $select.data('cost-slug') || $select.data('name') || $select.attr('name');
                panelState.extraCosts[name] = {
                    total: cost,
                    count: 1,
                    unitPrice: cost
                };
                totalExtra += cost;
            }
        });
        
        // Cerca input number per quantità (supporta costi unitari negativi)
        $('.btr-extra-cost-quantity, input[type="number"][data-unit-cost]').each(function() {
            const $input = $(this);
            const quantity = parseInt($input.val() || 0);
            const unitCost = parseFloat($input.data('unit-cost') || $input.data('importo-unitario') || 0);
            if (quantity !== 0 && unitCost !== 0) {
                const name = $input.data('cost-slug') || $input.data('name') || $input.attr('name');
                const totalCost = quantity * unitCost;
                panelState.extraCosts[name] = {
                    total: totalCost,
                    count: quantity,
                    unitPrice: unitCost
                };
                totalExtra += totalCost;
            }
        });
        
        console.log('[DYNAMIC SUMMARY] Costi extra calcolati:', panelState.extraCosts, 'Totale:', totalExtra);
        return totalExtra;
    }
    
    // Funzione per aggiornare il pannello di riepilogo
    function updateSummaryPanel() {
        const $panel = $('.btr-summary-box');
        if (!$panel.length) {
            console.warn('[DYNAMIC SUMMARY] Pannello non trovato');
            return;
        }
        
        // Calcola costi extra
        const extraCostsTotal = calculateExtraCosts();
        
        // v1.0.178 - Corretto: Salva il totale base solo se non ancora salvato (non ricalcolare se già presente)
        // Se non abbiamo ancora salvato il totale base (prima volta che calcoliamo)
        if (panelState.baseTotal === 0) {
            // CORREZIONE: Cerca prima nel DOM elementi che contengono "Totale Camere"
            let baseTotalFound = false;
            
            // Metodo 1: Cerca "Totale Camere" nel pannello summary
            $('.btr-summary-box, .btr-summary-moved').find('*').each(function() {
                const text = $(this).text();
                if ((text.includes('Totale Camere') || text.includes('TOTALE CAMERE')) && !baseTotalFound) {
                    const match = text.match(/€?\s*(\d+(?:[.,]\d+)?)/);
                    if (match) {
                        panelState.baseTotal = parseFloat(match[1].replace(',', '.'));
                        baseTotalFound = true;
                        console.log('[DYNAMIC SUMMARY] Totale base camere dal DOM "Totale Camere":', panelState.baseTotal);
                        return false; // Break
                    }
                }
            });
            
            // v1.0.178 - Corretto: Non sottrarre mai i costi extra dal totale per calcolare il base
            // Metodo 2: Fallback su #btr-total-price-visual (usa il valore così com'è)
            if (!baseTotalFound) {
                const currentTotalText = $('#btr-total-price-visual').text();
                // FIX v1.0.179: Corretto parsing formato italiano (€1.039,90 → 1039.90)
                const currentTotal = parseFloat(
                    currentTotalText
                        .replace(/[^0-9.,]/g, '')  // Rimuove tutto tranne numeri, virgole e punti
                        .replace(/\./g, '')         // IMPORTANTE: Rimuove i punti (separatori migliaia italiani)
                        .replace(',', '.')          // Converte virgola decimale italiana in punto
                ) || 0;
                
                if (currentTotal > 0) {
                    // v1.0.178 - IMPORTANTE: Usa il totale corrente come base SENZA sottrazioni
                    // Il totale visuale dovrebbe essere il costo delle camere quando viene chiamato per la prima volta
                    panelState.baseTotal = currentTotal;
                    console.log('[DYNAMIC SUMMARY] v1.0.178 - Totale base camere salvato:', panelState.baseTotal);
                    baseTotalFound = true;
                }
            }
            
            // Metodo 3: Ultimo fallback - calcola dalle camere se disponibile
            if (!baseTotalFound && window.roomsData) {
                let calculatedTotal = 0;
                window.roomsData.forEach(room => {
                    if (room.quantita > 0) {
                        const basePrice = (room.prezzo_per_persona || 0) * (room.capacity || 2);
                        const supplement = (room.supplemento || 0) * (room.capacity || 2);
                        const nights = 2; // Numero base notti
                        calculatedTotal += (basePrice + supplement) * room.quantita * nights;
                    }
                });
                
                if (calculatedTotal > 0) {
                    panelState.baseTotal = calculatedTotal;
                    console.log('[DYNAMIC SUMMARY] Totale base calcolato dalle camere:', panelState.baseTotal);
                }
            }
        }
        
        // Calcola il nuovo totale: totale camere + costi extra (che possono essere negativi)
        // FIX v1.0.227: Calcola totale assicurazioni separatamente
        const insuranceTotal = parseFloat($("#btr-summary-insurance-total").text().replace(/[^0-9.,]/g, "").replace(/\./g, "").replace(",", ".")) || 0;
        console.log("[DYNAMIC SUMMARY] Totale assicurazioni:", insuranceTotal);
        panelState.finalTotal = panelState.baseTotal + insuranceTotal + extraCostsTotal;
        
        // *** SINCRONIZZA CON JSON STATE MANAGER ***
        if (window.btrBookingState) {
            // Aggiorna lo stato centralizzato con i dati del pannello
            window.btrBookingState.updateTotalesCamere(panelState.baseTotal);
            
            // Sincronizza i costi extra - RESET prima di aggiornare
            window.btrBookingState.costi_extra = {};
            for (const [name, cost] of Object.entries(panelState.extraCosts)) {
                const slug = name.toLowerCase().replace(/[\s\-\/\(\)]/g, '_');
                window.btrBookingState.setCostoExtra(slug, cost, name);
            }
            
            console.log('[DYNAMIC SUMMARY] State Manager sincronizzato:', window.btrBookingState.getPayloadData());
        }
        
        // Aggiorna il display del totale con formattazione italiana (separatore migliaia)
        // v1.0.180: Aggiunto separatore migliaia per formato italiano (€1.279,90)
        const formatPrice = (price) => {
            // Converti in stringa con 2 decimali
            const parts = price.toFixed(2).split('.');
            // Aggiungi separatore migliaia
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            // Unisci con virgola come separatore decimale
            return parts.join(',');
        };
        
        $('#btr-total-price-visual').html('€' + formatPrice(panelState.finalTotal));
        
        // Aggiungi sezione costi extra se esistono (incluse riduzioni)
        if (extraCostsTotal !== 0) {
            let extraCostsHtml = '';
            if ($('#btr-extra-costs-section').length === 0) {
                extraCostsHtml = `
                    <hr class="btr-summary-divider" />
                    <div id="btr-extra-costs-section">
                        <div class="btr-summary-header">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0097c5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8M12 8v8"></path></svg>
                            <span class="btr-summary-label">Costi Extra e Riduzioni:</span>
                        </div>
                        <div id="btr-extra-costs-list" class="btr-room-list" style="margin-left:2.5em;"></div>
                    </div>
                `;
                $('.btr-summary-rooms').after(extraCostsHtml);
            }
            
            // Aggiorna la lista dei costi extra con quantità e giustificazione (supporta valori negativi)
            let costsList = [];
            for (const [name, costData] of Object.entries(panelState.extraCosts)) {
                // Il nome è già pulito dalla funzione calculateExtraCosts
                let displayName = name;
                
                // Gestione speciale per alcuni nomi comuni (se necessario)
                if (displayName.toLowerCase().includes('culla')) {
                    displayName = 'Culla per Neonati';
                } else if (displayName.toLowerCase().includes('animale')) {
                    displayName = 'Animale Domestico';
                } else if (displayName.toLowerCase().includes('skipass')) {
                    displayName = 'No Skipass (Riduzione)';
                }
                
                // Estrai i dati dalla struttura {total, count, unitPrice}
                const total = costData.total || costData; // Fallback per compatibilità
                const count = costData.count || 1;
                const unitPrice = costData.unitPrice || total;
                
                const isReduction = total < 0;
                const formattedTotal = (isReduction ? '-€' : '€') + Math.abs(total).toFixed(2).replace('.', ',');
                
                // Crea la stringa con quantità per giustificare il prezzo
                let costDisplay = '';
                    const formattedUnitPrice = (isReduction ? '-€' : '€') + Math.abs(unitPrice).toFixed(2).replace('.', ',');
                    costDisplay = `${count}x ${displayName} (${formattedUnitPrice} cad.): <strong>${formattedTotal}</strong>`;
               
                
                const cssClass = isReduction ? 'style="color: #dc3545;"' : 'style="color: #28a745;"';
                costsList.push(`<span>${costDisplay}</span>`);
            }
            $('#btr-extra-costs-list').html(costsList.join('<br>'));
        }
        
        console.log('[DYNAMIC SUMMARY] Pannello aggiornato. Base:', panelState.baseTotal, 'Extra:', extraCostsTotal, 'Totale:', panelState.finalTotal);
    }
    
    // Funzione per spostare il pannello al fondo
    function movePanelToBottom() {
        const $panel = $('.btr-summary-box');
        const $proceedButton = $('#btr-proceed');
        
        if (!$panel.length || !$proceedButton.length) {
            console.warn('[DYNAMIC SUMMARY] Pannello o pulsante non trovati');
            return;
        }
        
        // Salva il parent originale se non già salvato
        if (!panelState.originalParent) {
            panelState.originalParent = $panel.parent();
        }
        
        // Clona il pannello per mantenere gli event handlers
        const $clonedPanel = $panel.clone(true, true);
        
        // Rimuovi il pannello originale
        $panel.remove();
        
        // Trova il wrapper del pulsante e inserisci PRIMA di esso
        const $buttonWrapper = $proceedButton.closest('.tilt-button-wrap, .form-group, .btr-form-actions, div');
        
        // Inserisci il pannello clonato PRIMA del wrapper del pulsante
        $clonedPanel.insertBefore($buttonWrapper);
        
        // Aggiungi classe per indicare che è stato spostato
        $clonedPanel.addClass('btr-summary-moved');
        
        // Aggiungi stili per evidenziare il pannello spostato
        if (!$('#btr-summary-moved-styles').length) {
            $('head').append(`
                <style id="btr-summary-moved-styles">
                    .btr-summary-moved {
                        margin: 2em 0;
                        animation: slideIn 0.5s ease-out;
                        position: sticky;
                        bottom: 20px;
                        z-index: 100;
                        background: #fff !important;
                        max-width: 100% !important;
                    }
                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateY(-20px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    .btr-summary-moved .btr-summary-price {
                        background: linear-gradient(135deg, #0097c5 0%, #006a8e 100%);
                        box-shadow: 0 4px 15px rgba(0, 151, 197, 0.3);
                    }
                </style>
            `);
        }
        
        // Salva il totale base delle camere PRIMA di spostare il pannello
        // Questo è importante per preservare il totale delle camere
        if (panelState.baseTotal === 0) {
            const currentTotalText = $clonedPanel.find('#btr-total-price-visual').text();
            // FIX v1.0.179: Corretto parsing formato italiano (€1.039,90 → 1039.90)
            const currentTotal = parseFloat(
                currentTotalText
                    .replace(/[^0-9.,]/g, '')  // Rimuove tutto tranne numeri, virgole e punti
                    .replace(/\./g, '')         // IMPORTANTE: Rimuove i punti (separatori migliaia italiani)
                    .replace(',', '.')          // Converte virgola decimale italiana in punto
            ) || 0;
            if (currentTotal > 0) {
                panelState.baseTotal = currentTotal;
                console.log('[DYNAMIC SUMMARY] Totale base camere salvato al momento dello spostamento:', panelState.baseTotal);
            }
        }
        
        panelState.isMovedToBottom = true;
        console.log('[DYNAMIC SUMMARY] Pannello spostato al fondo PRIMA del pulsante #btr-proceed');
        
        // Aggiorna il pannello con i costi attuali
        updateSummaryPanel();
    }
    
    // Inizializzazione quando il DOM è pronto
    $(document).ready(function() {
        console.log('[DYNAMIC SUMMARY] DOM pronto, configurazione event handlers...');
        
        // Aggiungi logging dettagliato per debug valori negativi
        window.btrDebugExtraCosts = function() {
            console.log('[DEBUG] Analisi costi extra nel DOM:');
            $('input[type="checkbox"][data-cost-slug], input[type="checkbox"][data-importo]').each(function() {
                const $this = $(this);
                console.log('Campo:', $this.attr('name'), {
                    checked: $this.is(':checked'),
                    'data-importo': $this.data('importo'),
                    'data-cost': $this.data('cost'),
                    'data-price': $this.data('price'),
                    'data-reduction': $this.data('reduction'),
                    'data-negative': $this.data('negative'),
                    'value': $this.val()
                });
            });
        };
        
        // Intercetta il click sul pulsante procedi/genera partecipanti
        $(document).on('click', '#btr-generate-participants, #btr-proceed', function(e) {
            console.log('[DYNAMIC SUMMARY] Click su procedi rilevato');
            
            // Sposta il pannello se non già spostato
            if (!panelState.isMovedToBottom) {
                movePanelToBottom();
            }
        });
        
        // Monitora i cambiamenti nei costi extra con selettori più specifici
        $(document).on('change', 'input[type="checkbox"][data-cost-slug], input[type="checkbox"][data-importo], .btr-extra-cost-checkbox, .btr-extra-cost-radio, .btr-extra-cost-select, .btr-extra-cost-quantity', function() {
            console.log('[DYNAMIC SUMMARY] Costo extra modificato (specifico)');
            setTimeout(updateSummaryPanel, 100); // Piccolo delay per assicurarsi che il DOM sia aggiornato
        });
        
        // Monitora anche input generici che potrebbero essere costi extra
        $(document).on('change', 'input[name*="culla"], input[name*="animale"], input[name*="extra"], input[name*="supplemento"], input[name*="assicurazione"], input[name*="skipass"]', function() {
            console.log('[DYNAMIC SUMMARY] Possibile costo extra modificato (generico)');
            setTimeout(updateSummaryPanel, 100);
        });
        
        // Monitora i cambiamenti nei form anagrafici
        $(document).on('change', '.btr-anagrafico-form input, .btr-anagrafico-form select', function() {
            const $input = $(this);
            const fieldName = $input.attr('name') || '';
            
            // Verifica se è un campo relativo a costi extra
            if (fieldName.includes('culla') || fieldName.includes('animale') || 
                fieldName.includes('extra') || fieldName.includes('supplemento') ||
                fieldName.includes('assicurazione') || fieldName.includes('skipass')) {
                
                console.log('[DYNAMIC SUMMARY] Campo costo extra nei form anagrafici modificato:', fieldName);
                
                // Determina il costo in base al tipo di campo
                let cost = 0;
                if ($input.is(':checkbox:checked')) {
                    cost = parseFloat($input.data('cost') || $input.data('price') || 0);
                    
                    // Se non ha data-cost, cerca di determinarlo dal nome
                    if (cost === 0) {
                        if (fieldName.includes('culla')) cost = 15;
                        else if (fieldName.includes('animale')) cost = 10;
                    }
                    
                    if (cost > 0) {
                        panelState.extraCosts[fieldName] = cost;
                    }
                } else if ($input.is(':checkbox:not(:checked)')) {
                    // Rimuovi il costo se deselezionato
                    delete panelState.extraCosts[fieldName];
                }
                
                updateSummaryPanel();
            }
        });
        
        // Aggiungi supporto per quando la pagina cambia step nel form multi-step
        $(document).on('btr:step:changed', function(e, stepData) {
            console.log('[DYNAMIC SUMMARY] Step cambiato:', stepData);
            
            // Se siamo passati alla fase anagrafici, sposta il pannello
            if (stepData && (stepData.step === 'anagrafici' || stepData.step === 'participants')) {
                if (!panelState.isMovedToBottom) {
                    setTimeout(movePanelToBottom, 100);
                }
            }
        });
        
        // Osserva le mutazioni del DOM per rilevare quando vengono aggiunti nuovi elementi
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Verifica se sono stati aggiunti form anagrafici
                    const hasAnagraficiForm = Array.from(mutation.addedNodes).some(node => {
                        return node.nodeType === 1 && (
                            node.classList?.contains('btr-anagrafico-form') ||
                            node.querySelector?.('.btr-anagrafico-form')
                        );
                    });
                    
                    if (hasAnagraficiForm) {
                        console.log('[DYNAMIC SUMMARY] Form anagrafici rilevato, spostamento pannello...');
                        setTimeout(movePanelToBottom, 500);
                    }
                }
            });
        });
        
        // Osserva il container principale del form
        const formContainer = document.querySelector('#btr-booking-form, .btr-booking-container, .wpb_wrapper');
        if (formContainer) {
            observer.observe(formContainer, {
                childList: true,
                subtree: true
            });
        }
        
        // Calcola e mostra i costi iniziali se presenti
        const initialExtraCosts = calculateExtraCosts();
        if (initialExtraCosts > 0) {
            updateSummaryPanel();
        }
    });
    
    // Esponi le funzioni per debug
    window.btrDynamicSummary = {
        movePanelToBottom: movePanelToBottom,
        updateSummaryPanel: updateSummaryPanel,
        calculateExtraCosts: calculateExtraCosts,
        getState: () => panelState
    };
    
})(jQuery);