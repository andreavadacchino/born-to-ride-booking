/**
 * Date Range Picker per gestione pacchetti
 * 
 * Utilizza flatpickr con rangePlugin per creare intervalli continui
 * di date con validazione e preview in tempo reale
 * 
 * @since 1.0.15
 */

import flatpickr from 'flatpickr';
import rangePlugin from 'flatpickr/dist/plugins/rangePlugin';
import { Italian } from 'flatpickr/dist/l10n/it';

/**
 * Classe principale per il Date Range Picker
 */
class BTRDateRangePicker {
    /**
     * @param {string} selector Selettore CSS per l'input
     * @param {Object} options Opzioni di configurazione
     */
    constructor(selector, options = {}) {
        this.element = document.querySelector(selector);
        if (!this.element) {
            console.error('BTRDateRangePicker: Element not found:', selector);
            return;
        }
        
        this.options = {
            minDate: 'today',
            maxDate: new Date().fp_incr(365), // 1 anno nel futuro
            locale: Italian,
            dateFormat: 'Y-m-d',
            enableTime: false,
            mode: 'range',
            showMonths: 2,
            onReady: this.onReady.bind(this),
            onChange: this.onChange.bind(this),
            ...options
        };
        
        this.packageId = this.element.dataset.packageId;
        this.previewContainer = null;
        this.validationContainer = null;
        
        this.init();
    }
    
    /**
     * Inizializzazione del picker
     */
    init() {
        // Setup UI containers
        this.setupUIContainers();
        
        // Configurazione flatpickr con range plugin
        this.picker = flatpickr(this.element, {
            ...this.options,
            plugins: [
                new rangePlugin({
                    input: this.element.dataset.rangeEndInput || '#date-range-end'
                })
            ]
        });
        
        // Event listeners aggiuntivi
        this.bindEvents();
        
        console.log('BTRDateRangePicker initialized for package:', this.packageId);
    }
    
    /**
     * Setup dei container UI per preview e validazione
     */
    setupUIContainers() {
        const wrapper = this.element.parentNode;
        
        // Container per validazione
        this.validationContainer = document.createElement('div');
        this.validationContainer.className = 'btr-date-validation';
        this.validationContainer.style.cssText = `
            margin-top: 8px;
            font-size: 13px;
            min-height: 20px;
        `;
        
        // Container per preview
        this.previewContainer = document.createElement('div');
        this.previewContainer.className = 'btr-date-preview';
        this.previewContainer.style.cssText = `
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            display: none;
        `;
        
        wrapper.appendChild(this.validationContainer);
        wrapper.appendChild(this.previewContainer);
    }
    
    /**
     * Binding eventi aggiuntivi
     */
    bindEvents() {
        // Salvataggio automatico quando range valido
        this.element.addEventListener('change', () => {
            const range = this.getSelectedRange();
            if (range && this.isValidRange(range.start, range.end)) {
                this.autoSave(range);
            }
        });
        
        // Gestione tasti rapidi
        this.element.addEventListener('keydown', this.handleKeyboard.bind(this));
    }
    
    /**
     * Callback quando picker è pronto
     */
    onReady() {
        this.showValidationMessage(__('Select start and end dates for the package', 'born-to-ride-booking'), 'info');
        
        // Carica range esistenti se disponibili
        this.loadExistingRanges();
    }
    
    /**
     * Callback quando le date cambiano
     * @param {Date[]} selectedDates Array delle date selezionate
     */
    onChange(selectedDates) {
        if (selectedDates.length === 0) {
            this.clearPreview();
            this.showValidationMessage(__('Select start and end dates', 'born-to-ride-booking'), 'info');
            return;
        }
        
        if (selectedDates.length === 1) {
            this.showValidationMessage(__('Select end date', 'born-to-ride-booking'), 'info');
            return;
        }
        
        if (selectedDates.length === 2) {
            const [startDate, endDate] = selectedDates;
            this.validateAndPreviewRange(startDate, endDate);
        }
    }
    
    /**
     * Validazione e preview del range selezionato
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     */
    validateAndPreviewRange(startDate, endDate) {
        try {
            // Validazione base
            this.validateDateRange(startDate, endDate);
            
            // Generazione preview
            const continuousDates = this.generateContinuousDates(startDate, endDate);
            this.showPreview(startDate, endDate, continuousDates);
            
            // Messaggio di successo
            this.showValidationMessage(
                sprintf(__('Valid range: %d days from %s to %s', 'born-to-ride-booking'), 
                    continuousDates.length,
                    this.formatDate(startDate),
                    this.formatDate(endDate)
                ), 
                'success'
            );
            
            // Trigger evento custom per estensioni
            this.triggerRangeValidated(startDate, endDate, continuousDates);
            
        } catch (error) {
            this.showValidationMessage(error.message, 'error');
            this.clearPreview();
        }
    }
    
    /**
     * Validazione del range di date
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @throws {Error} Se il range non è valido
     */
    validateDateRange(startDate, endDate) {
        // Verifica ordine logico
        if (endDate < startDate) {
            throw new Error(__('End date cannot be before start date', 'born-to-ride-booking'));
        }
        
        // Verifica data nel passato
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (startDate < today) {
            throw new Error(__('Start date cannot be in the past', 'born-to-ride-booking'));
        }
        
        // Verifica range massimo
        const maxRangeDays = parseInt(this.options.maxRangeDays || 365);
        const rangeDays = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        
        if (rangeDays > maxRangeDays) {
            throw new Error(
                sprintf(__('Date range cannot exceed %d days', 'born-to-ride-booking'), maxRangeDays)
            );
        }
    }
    
    /**
     * Generazione array di date continue
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @return {Array} Array di oggetti data
     */
    generateContinuousDates(startDate, endDate) {
        const dates = [];
        const current = new Date(startDate);
        let dayIndex = 0;
        
        while (current <= endDate) {
            dates.push({
                date: new Date(current),
                dateString: this.formatDateForDB(current),
                dayIndex: dayIndex,
                dayName: current.toLocaleDateString('it-IT', { weekday: 'long' }),
                isWeekend: current.getDay() === 0 || current.getDay() === 6
            });
            
            current.setDate(current.getDate() + 1);
            dayIndex++;
        }
        
        return dates;
    }
    
    /**
     * Mostra preview del range selezionato
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @param {Array} continuousDates Array delle date continue
     */
    showPreview(startDate, endDate, continuousDates) {
        const weekendCount = continuousDates.filter(d => d.isWeekend).length;
        const weekdayCount = continuousDates.length - weekendCount;
        
        this.previewContainer.innerHTML = `
            <div class="btr-preview-header">
                <h4 style="margin: 0 0 8px 0; color: #2c3e50;">
                    ${__('Date Range Preview', 'born-to-ride-booking')}
                </h4>
                <div class="btr-preview-stats" style="display: flex; gap: 20px; margin-bottom: 12px;">
                    <span><strong>${__('Total days:', 'born-to-ride-booking')}</strong> ${continuousDates.length}</span>
                    <span><strong>${__('Weekdays:', 'born-to-ride-booking')}</strong> ${weekdayCount}</span>
                    <span><strong>${__('Weekends:', 'born-to-ride-booking')}</strong> ${weekendCount}</span>
                </div>
            </div>
            
            <div class="btr-preview-dates" style="max-height: 200px; overflow-y: auto;">
                ${this.generateDatesHTML(continuousDates)}
            </div>
            
            <div class="btr-preview-actions" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #dee2e6;">
                <button type="button" class="button button-primary" onclick="btrDateRangePicker.saveRange()">
                    ${__('Save Date Range', 'born-to-ride-booking')}
                </button>
                <button type="button" class="button button-secondary" onclick="btrDateRangePicker.clearRange()">
                    ${__('Clear Selection', 'born-to-ride-booking')}
                </button>
            </div>
        `;
        
        this.previewContainer.style.display = 'block';
    }
    
    /**
     * Genera HTML per la lista delle date
     * @param {Array} dates Array delle date
     * @return {string} HTML generato
     */
    generateDatesHTML(dates) {
        return dates.map(dateInfo => {
            const cssClass = dateInfo.isWeekend ? 'btr-weekend' : 'btr-weekday';
            return `
                <div class="btr-date-item ${cssClass}" style="
                    display: inline-block; 
                    margin: 2px; 
                    padding: 4px 8px; 
                    background: ${dateInfo.isWeekend ? '#fff3cd' : '#d4edda'};
                    border: 1px solid ${dateInfo.isWeekend ? '#ffeaa7' : '#c3e6cb'};
                    border-radius: 3px;
                    font-size: 12px;
                ">
                    ${this.formatDate(dateInfo.date)} <em>(${dateInfo.dayName})</em>
                </div>
            `;
        }).join('');
    }
    
    /**
     * Mostra messaggio di validazione
     * @param {string} message Messaggio da mostrare
     * @param {string} type Tipo: 'success', 'error', 'info', 'warning'
     */
    showValidationMessage(message, type = 'info') {
        const colors = {
            success: { bg: '#d4edda', border: '#c3e6cb', text: '#155724' },
            error: { bg: '#f8d7da', border: '#f5c6cb', text: '#721c24' },
            warning: { bg: '#fff3cd', border: '#ffeaa7', text: '#856404' },
            info: { bg: '#d1ecf1', border: '#bee5eb', text: '#0c5460' }
        };
        
        const color = colors[type] || colors.info;
        
        this.validationContainer.innerHTML = `
            <div style="
                padding: 8px 12px;
                background: ${color.bg};
                border: 1px solid ${color.border};
                color: ${color.text};
                border-radius: 4px;
                font-weight: ${type === 'error' ? '600' : '400'};
            ">
                ${message}
            </div>
        `;
    }
    
    /**
     * Pulisce preview e validazione
     */
    clearPreview() {
        this.previewContainer.style.display = 'none';
        this.previewContainer.innerHTML = '';
    }
    
    /**
     * Ottiene il range selezionato
     * @return {Object|null} Oggetto con start/end o null
     */
    getSelectedRange() {
        const selectedDates = this.picker.selectedDates;
        if (selectedDates.length === 2) {
            return {
                start: selectedDates[0],
                end: selectedDates[1]
            };
        }
        return null;
    }
    
    /**
     * Verifica se un range è valido
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @return {boolean} True se valido
     */
    isValidRange(startDate, endDate) {
        try {
            this.validateDateRange(startDate, endDate);
            return true;
        } catch (error) {
            return false;
        }
    }
    
    /**
     * Salvataggio automatico del range
     * @param {Object} range Oggetto range con start/end
     */
    async autoSave(range) {
        if (!this.packageId) {
            console.warn('BTRDateRangePicker: No package ID specified for auto-save');
            return;
        }
        
        try {
            const response = await this.saveRangeToServer(range.start, range.end);
            
            if (response.success) {
                this.showValidationMessage(
                    __('Date range saved successfully', 'born-to-ride-booking'), 
                    'success'
                );
                
                // Trigger evento di salvataggio completato
                this.triggerRangeSaved(range, response.data);
            } else {
                throw new Error(response.data || __('Failed to save date range', 'born-to-ride-booking'));
            }
            
        } catch (error) {
            this.showValidationMessage(error.message, 'error');
            console.error('BTRDateRangePicker auto-save error:', error);
        }
    }
    
    /**
     * Salvataggio range sul server via AJAX
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @return {Promise} Promise della richiesta AJAX
     */
    async saveRangeToServer(startDate, endDate) {
        const formData = new FormData();
        formData.append('action', 'btr_save_date_range');
        formData.append('package_id', this.packageId);
        formData.append('start_date', this.formatDateForDB(startDate));
        formData.append('end_date', this.formatDateForDB(endDate));
        formData.append('nonce', window.btrAdmin?.nonce || '');
        
        const response = await fetch(window.ajaxurl, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    /**
     * Caricamento range esistenti
     */
    async loadExistingRanges() {
        if (!this.packageId) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'btr_get_package_date_ranges');
            formData.append('package_id', this.packageId);
            formData.append('nonce', window.btrAdmin?.nonce || '');
            
            const response = await fetch(window.ajaxurl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.data.ranges && data.data.ranges.length > 0) {
                // Mostra il primo range trovato
                const firstRange = data.data.ranges[0];
                this.picker.setDate([firstRange.range_start_date, firstRange.range_end_date]);
            }
            
        } catch (error) {
            console.warn('BTRDateRangePicker: Could not load existing ranges:', error);
        }
    }
    
    /**
     * Gestione tasti rapidi
     * @param {KeyboardEvent} event Evento tastiera
     */
    handleKeyboard(event) {
        if (event.ctrlKey || event.metaKey) {
            switch (event.key) {
                case 's':
                    event.preventDefault();
                    this.saveRange();
                    break;
                case 'c':
                    event.preventDefault();
                    this.clearRange();
                    break;
            }
        }
    }
    
    /**
     * Metodo pubblico per salvare il range corrente
     */
    async saveRange() {
        const range = this.getSelectedRange();
        if (range) {
            await this.autoSave(range);
        } else {
            this.showValidationMessage(__('Please select a valid date range first', 'born-to-ride-booking'), 'warning');
        }
    }
    
    /**
     * Metodo pubblico per pulire la selezione
     */
    clearRange() {
        this.picker.clear();
        this.clearPreview();
        this.showValidationMessage(__('Selection cleared', 'born-to-ride-booking'), 'info');
    }
    
    /**
     * Trigger evento range validato
     * @param {Date} startDate Data inizio
     * @param {Date} endDate Data fine
     * @param {Array} continuousDates Array date continue
     */
    triggerRangeValidated(startDate, endDate, continuousDates) {
        const event = new CustomEvent('btr:dateRangeValidated', {
            detail: {
                startDate,
                endDate,
                continuousDates,
                packageId: this.packageId
            }
        });
        
        document.dispatchEvent(event);
    }
    
    /**
     * Trigger evento range salvato
     * @param {Object} range Range salvato
     * @param {Object} serverResponse Risposta del server
     */
    triggerRangeSaved(range, serverResponse) {
        const event = new CustomEvent('btr:dateRangeSaved', {
            detail: {
                range,
                serverResponse,
                packageId: this.packageId
            }
        });
        
        document.dispatchEvent(event);
    }
    
    /**
     * Formattazione data per visualizzazione
     * @param {Date} date Data da formattare
     * @return {string} Data formattata
     */
    formatDate(date) {
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric'
        });
    }
    
    /**
     * Formattazione data per database
     * @param {Date} date Data da formattare
     * @return {string} Data in formato Y-m-d
     */
    formatDateForDB(date) {
        return date.toISOString().split('T')[0];
    }
    
    /**
     * Distruzione dell'istanza
     */
    destroy() {
        if (this.picker) {
            this.picker.destroy();
        }
        
        if (this.previewContainer) {
            this.previewContainer.remove();
        }
        
        if (this.validationContainer) {
            this.validationContainer.remove();
        }
    }
}

// Funzioni helper globali
window.__ = window.__ || function(text) { return text; };
window.sprintf = window.sprintf || function(format, ...args) {
    return format.replace(/%[sd]/g, () => args.shift());
};

// Istanza globale per backward compatibility
window.btrDateRangePicker = null;

// Inizializzazione automatica
document.addEventListener('DOMContentLoaded', function() {
    const dateRangeInput = document.querySelector('.btr-date-range-picker');
    if (dateRangeInput) {
        window.btrDateRangePicker = new BTRDateRangePicker('.btr-date-range-picker');
    }
});

export default BTRDateRangePicker;