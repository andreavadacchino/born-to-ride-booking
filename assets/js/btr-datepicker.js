/**
 * Born to Ride - Modern Date Picker Component
 * 
 * @package Born_To_Ride_Booking
 */

(function($) {
    'use strict';

    class BTRDatePicker {
        constructor(element, options = {}) {
            this.$element = $(element);
            this.options = $.extend({
                format: 'dd/mm/yyyy',
                language: 'it',
                minDate: null,
                maxDate: null,
                disabledDates: [],
                startDate: null,
                endDate: null,
                onSelect: null,
                onShow: null,
                onHide: null,
                placeholder: 'Seleziona una data',
                todayButton: true,
                clearButton: true,
                autoClose: true,
                monthNames: ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
                            'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'],
                monthNamesShort: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 
                                 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
                dayNames: ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'],
                dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'],
                dayNamesMin: ['D', 'L', 'M', 'M', 'G', 'V', 'S']
            }, options);
            
            this.currentDate = new Date();
            this.selectedDate = null;
            this.viewDate = new Date();
            
            this.init();
        }
        
        init() {
            // Hide original input
            this.$element.attr('type', 'hidden');
            
            // Create wrapper
            this.$wrapper = $('<div class="btr-datepicker-wrapper"></div>');
            this.$element.wrap(this.$wrapper);
            
            // Create visible input (permettiamo digitazione manuale)
            this.$input = $('<input type="text" class="btr-datepicker-input">');
            this.$input.attr({
                'placeholder': this.options.placeholder || 'gg/mm/aaaa',
                'autocomplete': 'off',
                'maxlength': '10'
            });
            
            // Create calendar icon
            this.$icon = $(`
                <svg class="btr-datepicker-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            `);
            
            // Create calendar dropdown
            this.$calendar = $('<div class="btr-calendar-dropdown"></div>');
            this.$overlay = $('<div class="btr-calendar-overlay"></div>');
            
            // Append elements
            this.$element.parent().append(this.$input);
            this.$element.parent().append(this.$icon);
            $('body').append(this.$calendar);
            $('body').append(this.$overlay);
            
            // Set initial value if exists
            if (this.$element.val()) {
                this.setDate(this.parseDate(this.$element.val()));
            }
            
            // Bind events
            this.bindEvents();
            
            // Build calendar
            this.buildCalendar();
        }
        
        bindEvents() {
            // Input focus - mostra calendario
            this.$input.on('focus', (e) => {
                e.stopPropagation();
                this.show();
            });
            
            // Icon click - mostra calendario
            this.$icon.on('click', (e) => {
                e.stopPropagation();
                this.$input.focus();
            });
            
            // Input manuale della data
            let typingTimer;
            this.$input.on('input', (e) => {
                const value = this.$input.val();
                
                // Formatta automaticamente mentre si digita
                let formatted = value.replace(/\D/g, ''); // Rimuovi non-numeri
                if (formatted.length >= 2) {
                    formatted = formatted.substring(0, 2) + '/' + formatted.substring(2);
                }
                if (formatted.length >= 5) {
                    formatted = formatted.substring(0, 5) + '/' + formatted.substring(5, 9);
                }
                
                // Aggiorna solo se cambiato per evitare loop
                if (formatted !== value) {
                    this.$input.val(formatted);
                }
                
                // Clear typing timer
                clearTimeout(typingTimer);
                
                // Valida dopo che l'utente smette di digitare
                typingTimer = setTimeout(() => {
                    this.validateManualInput(formatted);
                }, 500);
            });
            
            // Gestione tasti speciali
            this.$input.on('keydown', (e) => {
                // Enter per confermare
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.validateManualInput(this.$input.val());
                    this.hide();
                }
                // Escape per chiudere
                else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hide();
                }
                // Tab mantiene comportamento default ma chiude calendario
                else if (e.key === 'Tab') {
                    this.hide();
                }
            });
            
            // Blur - valida input
            this.$input.on('blur', (e) => {
                // Delay per permettere click sul calendario
                setTimeout(() => {
                    if (!this.$calendar.is(':hover')) {
                        this.validateManualInput(this.$input.val());
                        this.hide();
                    }
                }, 200);
            });
            
            // Document click (close calendar)
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.btr-datepicker-wrapper').length && 
                    !$(e.target).closest('.btr-calendar-dropdown').length) {
                    this.hide();
                }
            });
            
            // Overlay click (mobile)
            this.$overlay.on('click', () => {
                this.hide();
            });
            
            // Window resize
            $(window).on('resize', () => {
                if (this.$calendar.hasClass('show')) {
                    this.positionCalendar();
                }
            });
        }
        
        buildCalendar() {
            this.$calendar.html(`
                <div class="btr-calendar-header">
                    <div class="btr-calendar-selectors">
                        <select class="btr-month-select">
                            ${this.options.monthNames.map((month, i) => 
                                `<option value="${i}">${month}</option>`
                            ).join('')}
                        </select>
                        <select class="btr-year-select">
                            <!-- Years will be populated dynamically -->
                        </select>
                    </div>
                    <div class="btr-calendar-nav">
                        <button class="btr-prev-month" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button class="btr-next-month" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="btr-calendar-grid"></div>
                <div class="btr-calendar-footer">
                    <div class="btr-calendar-quick-select">
                        ${this.options.todayButton ? '<button class="btr-calendar-quick-btn btr-today-btn" type="button">Oggi</button>' : ''}
                    </div>
                    ${this.options.clearButton ? '<button class="btr-calendar-clear-btn" type="button">Cancella</button>' : ''}
                </div>
            `);
            
            // Month/Year selector - Not needed anymore with dropdowns
            // this.$monthYearSelector = $(`
            //     <div class="btr-month-year-selector">
            //         <div class="btr-selector-header">
            //             <h4 class="btr-selector-title">Seleziona mese e anno</h4>
            //             <button class="btr-selector-close" type="button">
            //                 <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            //                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            //                 </svg>
            //             </button>
            //         </div>
            //         <div class="btr-selector-content"></div>
            //     </div>
            // `);
            // this.$calendar.append(this.$monthYearSelector);
            
            // Bind calendar events
            this.bindCalendarEvents();
        }
        
        bindCalendarEvents() {
            // Navigation buttons
            this.$calendar.on('click', '.btr-prev-month', () => {
                this.changeMonth(-1);
            });
            
            this.$calendar.on('click', '.btr-next-month', () => {
                this.changeMonth(1);
            });
            
            // Calendar title click - Not needed anymore with dropdowns
            // this.$calendar.on('click', '.btr-calendar-title', () => {
            //     this.showMonthYearSelector();
            // });
            
            // Month dropdown change
            this.$calendar.on('change', '.btr-month-select', (e) => {
                const month = parseInt($(e.target).val());
                this.viewDate.setMonth(month);
                this.renderCalendar();
            });
            
            // Year dropdown change
            this.$calendar.on('change', '.btr-year-select', (e) => {
                const year = parseInt($(e.target).val());
                this.viewDate.setFullYear(year);
                this.renderCalendar();
            });
            
            // Day click
            this.$calendar.on('click', '.btr-calendar-day:not(.disabled)', (e) => {
                const date = new Date($(e.target).data('date'));
                this.selectDate(date);
            });
            
            // Today button
            this.$calendar.on('click', '.btr-today-btn', () => {
                this.selectDate(new Date());
            });
            
            // Clear button
            this.$calendar.on('click', '.btr-calendar-clear-btn', () => {
                this.clear();
            });
            
            // Month/year selector close - Not needed anymore with dropdowns
            // this.$calendar.on('click', '.btr-selector-close', () => {
            //     this.hideMonthYearSelector();
            // });
        }
        
        renderCalendar() {
            const year = this.viewDate.getFullYear();
            const month = this.viewDate.getMonth();
            
            // Update month dropdown
            this.$calendar.find('.btr-month-select').val(month);
            
            // Update year dropdown
            const $yearSelect = this.$calendar.find('.btr-year-select');
            if ($yearSelect.find('option').length === 0) {
                // Populate year dropdown if empty
                const currentYear = new Date().getFullYear();
                const startYear = currentYear - 100;
                const endYear = currentYear + 10;
                
                for (let y = endYear; y >= startYear; y--) {
                    $yearSelect.append(`<option value="${y}">${y}</option>`);
                }
            }
            $yearSelect.val(year);
            
            // Update title (hidden since we use dropdowns)
            this.$calendar.find('.btr-calendar-title').text(
                `${this.options.monthNames[month]} ${year}`
            );
            
            // Clear grid
            const $grid = this.$calendar.find('.btr-calendar-grid');
            $grid.empty();
            
            // Add weekday headers
            for (let i = 0; i < 7; i++) {
                $grid.append(`<div class="btr-calendar-weekday">${this.options.dayNamesMin[i]}</div>`);
            }
            
            // Get first day of month
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const prevLastDay = new Date(year, month, 0);
            
            // Calculate starting day
            let startingDay = firstDay.getDay();
            
            // Add previous month days
            for (let i = startingDay - 1; i >= 0; i--) {
                const day = prevLastDay.getDate() - i;
                const date = new Date(year, month - 1, day);
                $grid.append(this.createDayElement(date, true));
            }
            
            // Add current month days
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const date = new Date(year, month, day);
                $grid.append(this.createDayElement(date, false));
            }
            
            // Add next month days
            const remainingDays = 42 - $grid.children('.btr-calendar-day').length;
            for (let day = 1; day <= remainingDays; day++) {
                const date = new Date(year, month + 1, day);
                $grid.append(this.createDayElement(date, true));
            }
            
            // Update navigation buttons
            this.updateNavigationButtons();
        }
        
        createDayElement(date, otherMonth) {
            const $day = $('<div class="btr-calendar-day"></div>');
            $day.text(date.getDate());
            $day.data('date', date.toISOString());
            
            if (otherMonth) {
                $day.addClass('other-month');
            }
            
            // Check if today
            if (this.isSameDay(date, new Date())) {
                $day.addClass('today');
            }
            
            // Check if selected
            if (this.selectedDate && this.isSameDay(date, this.selectedDate)) {
                $day.addClass('selected');
            }
            
            // Check if disabled
            if (this.isDateDisabled(date)) {
                $day.addClass('disabled');
            }
            
            return $day;
        }
        
        isDateDisabled(date) {
            // Check min date
            if (this.options.minDate && date < this.options.minDate) {
                return true;
            }
            
            // Check max date
            if (this.options.maxDate && date > this.options.maxDate) {
                return true;
            }
            
            // Check disabled dates
            for (let disabledDate of this.options.disabledDates) {
                if (this.isSameDay(date, disabledDate)) {
                    return true;
                }
            }
            
            return false;
        }
        
        updateNavigationButtons() {
            const year = this.viewDate.getFullYear();
            const month = this.viewDate.getMonth();
            
            // Check prev month
            if (this.options.minDate) {
                const prevMonth = new Date(year, month - 1, 1);
                const minMonth = new Date(this.options.minDate.getFullYear(), this.options.minDate.getMonth(), 1);
                this.$calendar.find('.btr-prev-month').prop('disabled', prevMonth < minMonth);
            }
            
            // Check next month
            if (this.options.maxDate) {
                const nextMonth = new Date(year, month + 1, 1);
                const maxMonth = new Date(this.options.maxDate.getFullYear(), this.options.maxDate.getMonth(), 1);
                this.$calendar.find('.btr-next-month').prop('disabled', nextMonth > maxMonth);
            }
        }
        
        showMonthYearSelector() {
            const currentYear = this.viewDate.getFullYear();
            const currentMonth = this.viewDate.getMonth();
            
            let content = '<div class="btr-months-grid">';
            for (let i = 0; i < 12; i++) {
                const selected = i === currentMonth ? 'selected' : '';
                content += `<button class="btr-month-option ${selected}" data-month="${i}">${this.options.monthNamesShort[i]}</button>`;
            }
            content += '</div>';
            
            content += '<div class="btr-years-grid">';
            for (let year = currentYear - 5; year <= currentYear + 5; year++) {
                const selected = year === currentYear ? 'selected' : '';
                content += `<button class="btr-year-option ${selected}" data-year="${year}">${year}</button>`;
            }
            content += '</div>';
            
            this.$monthYearSelector.find('.btr-selector-content').html(content);
            this.$monthYearSelector.addClass('show');
            
            // Bind selector events
            this.$monthYearSelector.off('click', '.btr-month-option');
            this.$monthYearSelector.on('click', '.btr-month-option', (e) => {
                const month = parseInt($(e.target).data('month'));
                this.viewDate.setMonth(month);
                this.hideMonthYearSelector();
                this.renderCalendar();
            });
            
            this.$monthYearSelector.off('click', '.btr-year-option');
            this.$monthYearSelector.on('click', '.btr-year-option', (e) => {
                const year = parseInt($(e.target).data('year'));
                this.viewDate.setFullYear(year);
                this.hideMonthYearSelector();
                this.renderCalendar();
            });
        }
        
        hideMonthYearSelector() {
            this.$monthYearSelector.removeClass('show');
        }
        
        changeMonth(direction) {
            this.viewDate.setMonth(this.viewDate.getMonth() + direction);
            this.renderCalendar();
        }
        
        selectDate(date) {
            this.selectedDate = date;
            this.viewDate = new Date(date);
            
            // Update hidden input
            this.$element.val(this.formatDate(date, 'yyyy-mm-dd'));
            
            // Update visible input
            this.$input.val(this.formatDate(date));
            this.$input.addClass('has-value');
            
            // Trigger change event
            this.$element.trigger('change');
            
            // Callback
            if (this.options.onSelect) {
                this.options.onSelect.call(this, date);
            }
            
            // Auto close
            if (this.options.autoClose) {
                this.hide();
            } else {
                this.renderCalendar();
            }
        }
        
        setDate(date) {
            if (date) {
                this.selectedDate = date;
                this.viewDate = new Date(date);
                this.$input.val(this.formatDate(date));
                this.$input.addClass('has-value');
                this.$element.val(this.formatDate(date, 'yyyy-mm-dd'));
            }
        }
        
        clear() {
            this.selectedDate = null;
            this.$element.val('');
            this.$input.val('');
            this.$input.removeClass('has-value');
            this.$element.trigger('change');
            this.hide();
        }
        
        show() {
            if (this.$calendar.hasClass('show')) return;
            
            this.renderCalendar();
            this.positionCalendar();
            this.$calendar.addClass('show');
            
            if (window.innerWidth <= 480) {
                this.$overlay.addClass('show');
            }
            
            if (this.options.onShow) {
                this.options.onShow.call(this);
            }
        }
        
        hide() {
            this.$calendar.removeClass('show');
            this.$overlay.removeClass('show');
            
            if (this.options.onHide) {
                this.options.onHide.call(this);
            }
        }
        
        toggle() {
            if (this.$calendar.hasClass('show')) {
                this.hide();
            } else {
                this.show();
            }
        }
        
        positionCalendar() {
            if (window.innerWidth <= 480) return;
            
            const inputOffset = this.$input.offset();
            const inputHeight = this.$input.outerHeight();
            const calendarHeight = this.$calendar.outerHeight();
            const windowHeight = $(window).height();
            const scrollTop = $(window).scrollTop();
            
            let top = inputOffset.top + inputHeight + 8;
            
            // Check if calendar would go below viewport
            if (top + calendarHeight > scrollTop + windowHeight) {
                // Position above input
                top = inputOffset.top - calendarHeight - 8;
            }
            
            this.$calendar.css({
                top: top,
                left: inputOffset.left
            });
        }
        
        formatDate(date, format) {
            format = format || this.options.format;
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            return format
                .replace('yyyy', year)
                .replace('mm', month)
                .replace('dd', day);
        }
        
        parseDate(dateString) {
            // Assume ISO format (yyyy-mm-dd)
            const parts = dateString.split('-');
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }
        
        validateManualInput(value) {
            // Rimuovi spazi
            value = value.trim();
            
            // Se vuoto, pulisci
            if (!value || value === '//' || value.replace(/\D/g, '').length === 0) {
                this.clear();
                return;
            }
            
            // Controlla formato gg/mm/aaaa
            const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
            const match = value.match(dateRegex);
            
            if (!match) {
                // Formato non valido - evidenzia errore
                this.$input.addClass('invalid');
                return;
            }
            
            const day = parseInt(match[1], 10);
            const month = parseInt(match[2], 10);
            const year = parseInt(match[3], 10);
            
            // Valida ranges
            if (month < 1 || month > 12) {
                this.$input.addClass('invalid');
                return;
            }
            
            // Crea data e verifica validità
            const date = new Date(year, month - 1, day);
            
            // Verifica che la data sia valida (es. non 31/02/2023)
            if (date.getDate() !== day || date.getMonth() !== month - 1 || date.getFullYear() !== year) {
                this.$input.addClass('invalid');
                return;
            }
            
            // Verifica vincoli min/max
            if (this.isDateDisabled(date)) {
                this.$input.addClass('invalid');
                return;
            }
            
            // Data valida - aggiorna
            this.$input.removeClass('invalid');
            this.selectDate(date);
            
            // Aggiorna vista calendario alla data inserita
            this.viewDate = new Date(date);
            if (this.$calendar.hasClass('show')) {
                this.renderCalendar();
            }
        }
        
        isSameDay(date1, date2) {
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }
        
        destroy() {
            this.$input.remove();
            this.$icon.remove();
            this.$calendar.remove();
            this.$overlay.remove();
            this.$element.unwrap();
            this.$element.attr('type', 'date');
            this.$element.removeData('btrDatePicker');
        }
    }
    
    // jQuery plugin
    $.fn.btrDatePicker = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('btrDatePicker');
            
            if (!instance) {
                instance = new BTRDatePicker(this, options);
                $this.data('btrDatePicker', instance);
            }
            
            if (typeof options === 'string' && instance[options]) {
                return instance[options]();
            }
        });
    };
    
})(jQuery);