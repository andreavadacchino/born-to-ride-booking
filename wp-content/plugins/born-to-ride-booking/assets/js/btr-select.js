/**
 * Born to Ride - Modern Select Component
 * 
 * @package Born_To_Ride_Booking
 */

(function($) {
    'use strict';

    class BTRSelect {
        constructor(element, options = {}) {
            this.$element = $(element);
            this.$container = this.$element.closest('.btr-custom-select');
            
            this.options = $.extend({
                searchable: true,
                placeholder: 'Seleziona un\'opzione',
                searchPlaceholder: 'Cerca...',
                noResults: 'Nessun risultato trovato',
                onChange: null,
                onOpen: null,
                onClose: null,
                mobileBreakpoint: 480,
                closeOnSelect: true,
                allowClear: false
            }, options);
            
            this.isOpen = false;
            this.selectedValue = null;
            this.selectedText = null;
            this.highlightedIndex = -1;
            this.filteredOptions = [];
            
            this.init();
        }
        
        init() {
            // Check if already initialized
            if (this.$container.data('btrSelect')) {
                return;
            }
            
            // Build dropdown structure if not exists
            if (!this.$container.find('.btr-select-dropdown').length) {
                this.buildDropdown();
            }
            
            // Cache elements
            this.$display = this.$container.find('.btr-province-display, .btr-select-display');
            this.$dropdown = this.$container.find('.btr-select-dropdown');
            this.$search = this.$dropdown.find('.btr-province-search, .btr-select-search');
            this.$optionsContainer = this.$dropdown.find('.btr-select-options-scroll');
            this.$options = this.$dropdown.find('.btr-select-option');
            
            // Make display focusable if not already
            if (!this.$display.attr('tabindex')) {
                this.$display.attr('tabindex', '0');
            }
            
            // Initialize filtered options
            this.filteredOptions = this.$options;
            
            // Create overlay for mobile
            this.$overlay = $('<div class="btr-select-overlay"></div>');
            $('body').append(this.$overlay);
            
            // Set initial value
            const currentValue = this.$display.val() || this.$display.data('value');
            if (currentValue) {
                this.setValue(currentValue, false);
            }
            
            // Bind events
            this.bindEvents();
            
            // Store instance
            this.$container.data('btrSelect', this);
        }
        
        buildDropdown() {
            // This method would build the dropdown structure from scratch
            // For now, we assume the HTML structure is already in place
        }
        
        bindEvents() {
            // Display click
            this.$display.on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
            
            // Display keyboard
            this.$display.on('keydown', (e) => {
                this.handleDisplayKeydown(e);
            });
            
            // Option click
            this.$container.on('click', '.btr-select-option:not(.disabled)', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $option = $(e.currentTarget);
                this.selectOption($option);
            });
            
            // Search input
            if (this.options.searchable && this.$search.length) {
                this.$search.on('input', (e) => {
                    this.filterOptions(e.target.value);
                });
                
                this.$search.on('keydown', (e) => {
                    this.handleSearchKeydown(e);
                });
            }
            
            // Document click (close dropdown)
            $(document).on('click.btrSelect', (e) => {
                if (!$(e.target).closest('.btr-custom-select').is(this.$container)) {
                    this.close();
                }
            });
            
            // Global keydown handler for when dropdown is open
            $(document).on('keydown.btrSelect', (e) => {
                if (this.isOpen && !$(e.target).is(this.$search)) {
                    // Handle keyboard navigation when dropdown is open
                    switch (e.key) {
                        case 'ArrowDown':
                            e.preventDefault();
                            this.highlightNext();
                            break;
                        case 'ArrowUp':
                            e.preventDefault();
                            this.highlightPrev();
                            break;
                        case 'Enter':
                            e.preventDefault();
                            if (this.highlightedIndex >= 0) {
                                const $highlighted = this.filteredOptions.eq(this.highlightedIndex);
                                this.selectOption($highlighted);
                            }
                            break;
                        case 'Escape':
                            e.preventDefault();
                            this.close();
                            this.$display.focus();
                            break;
                        default:
                            // Type-ahead functionality
                            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                                e.preventDefault();
                                this.typeAhead(e.key);
                            }
                            break;
                    }
                }
            });
            
            // Overlay click (mobile)
            this.$overlay.on('click', () => {
                this.close();
            });
            
            // Window resize
            $(window).on('resize.btrSelect', () => {
                if (this.isOpen) {
                    this.positionDropdown();
                }
            });
            
            // Prevent form submission on Enter in search
            this.$search.on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                }
            });
        }
        
        handleDisplayKeydown(e) {
            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                    } else if (this.highlightedIndex >= 0) {
                        const $highlighted = this.filteredOptions.eq(this.highlightedIndex);
                        this.selectOption($highlighted);
                    }
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                        // Highlight first option after opening
                        setTimeout(() => {
                            this.highlightedIndex = -1;
                            this.highlightNext();
                        }, 100);
                    } else {
                        this.highlightNext();
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (!this.isOpen) {
                        this.open();
                        // Highlight last option after opening
                        setTimeout(() => {
                            this.highlightedIndex = this.filteredOptions.length;
                            this.highlightPrev();
                        }, 100);
                    } else {
                        this.highlightPrev();
                    }
                    break;
                case 'Escape':
                    if (this.isOpen) {
                        this.close();
                        this.$display.focus();
                    }
                    break;
                case 'Tab':
                    // Allow tab to work normally but close dropdown
                    if (this.isOpen) {
                        this.close();
                    }
                    break;
            }
        }
        
        handleSearchKeydown(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.highlightNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.highlightPrev();
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (this.highlightedIndex >= 0) {
                        const $highlighted = this.filteredOptions.eq(this.highlightedIndex);
                        this.selectOption($highlighted);
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    this.close();
                    this.$display.focus();
                    break;
            }
        }
        
        open() {
            if (this.isOpen) return;
            
            this.isOpen = true;
            this.$container.addClass('active');
            this.$dropdown.show(); // Remove display: none
            
            // Add show class after a small delay for animation
            setTimeout(() => {
                this.$dropdown.addClass('show');
            }, 10);
            
            // Mobile handling
            if (window.innerWidth <= this.options.mobileBreakpoint) {
                this.$overlay.addClass('show');
                $('body').css('overflow', 'hidden');
            } else {
                this.positionDropdown();
            }
            
            // Focus search if available
            if (this.options.searchable && this.$search.length) {
                setTimeout(() => {
                    this.$search.focus();
                }, 100);
            }
            
            // Reset search
            this.$search.val('');
            this.filterOptions('');
            
            // Scroll to selected option
            this.scrollToSelected();
            
            // Callback
            if (this.options.onOpen) {
                this.options.onOpen.call(this);
            }
        }
        
        close() {
            if (!this.isOpen) return;
            
            this.isOpen = false;
            this.$container.removeClass('active');
            this.$dropdown.removeClass('show');
            this.$overlay.removeClass('show');
            $('body').css('overflow', '');
            
            // Hide dropdown after animation
            setTimeout(() => {
                this.$dropdown.hide();
            }, 300);
            
            // Reset highlight
            this.highlightedIndex = -1;
            this.$options.removeClass('highlighted');
            
            // Callback
            if (this.options.onClose) {
                this.options.onClose.call(this);
            }
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        selectOption($option) {
            const value = $option.data('value');
            const text = $option.text().trim();
            
            this.setValue(value, true);
            
            if (this.options.closeOnSelect) {
                this.close();
                this.$display.focus();
            }
        }
        
        setValue(value, triggerChange = true) {
            // Find option
            const $option = this.$options.filter(`[data-value="${value}"]`);
            if (!$option.length) return;
            
            // Update display
            const text = $option.text().trim();
            this.$display.val(text);
            this.$display.addClass('has-value');
            
            // Update selected state
            this.$options.removeClass('selected');
            $option.addClass('selected');
            
            // Store values
            this.selectedValue = value;
            this.selectedText = text;
            
            // Update hidden input if exists
            const $hidden = this.$container.find('input[type="hidden"]');
            if ($hidden.length) {
                $hidden.val(value);
            }
            
            // Trigger change
            if (triggerChange) {
                this.$display.trigger('change');
                
                if (this.options.onChange) {
                    this.options.onChange.call(this, value, text);
                }
                
                // Special handling for province select
                if (value === 'ESTERO' && this.$container.hasClass('provincia-residenza')) {
                    $('#paese-estero-residenza').show();
                } else if (this.$container.hasClass('provincia-residenza')) {
                    $('#paese-estero-residenza').hide();
                }
            }
        }
        
        filterOptions(query) {
            query = query.toLowerCase().trim();
            
            if (!query) {
                this.$options.show();
                this.filteredOptions = this.$options;
            } else {
                this.$options.each((i, el) => {
                    const $option = $(el);
                    const text = $option.text().toLowerCase();
                    const value = $option.data('value').toString().toLowerCase();
                    
                    if (text.includes(query) || value.includes(query)) {
                        $option.show();
                    } else {
                        $option.hide();
                    }
                });
                
                this.filteredOptions = this.$options.filter(':visible');
            }
            
            // Show/hide no results message
            if (this.filteredOptions.length === 0) {
                if (!this.$optionsContainer.find('.btr-select-no-results').length) {
                    this.$optionsContainer.append(
                        `<div class="btr-select-no-results">${this.options.noResults}</div>`
                    );
                }
            } else {
                this.$optionsContainer.find('.btr-select-no-results').remove();
            }
            
            // Reset highlight
            this.highlightedIndex = -1;
            this.$options.removeClass('highlighted');
        }
        
        highlightNext() {
            if (this.filteredOptions.length === 0) return;
            
            this.highlightedIndex++;
            if (this.highlightedIndex >= this.filteredOptions.length) {
                this.highlightedIndex = 0;
            }
            
            this.updateHighlight();
        }
        
        highlightPrev() {
            if (this.filteredOptions.length === 0) return;
            
            this.highlightedIndex--;
            if (this.highlightedIndex < 0) {
                this.highlightedIndex = this.filteredOptions.length - 1;
            }
            
            this.updateHighlight();
        }
        
        updateHighlight() {
            this.$options.removeClass('highlighted');
            
            if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredOptions.length) {
                const $highlighted = this.filteredOptions.eq(this.highlightedIndex);
                $highlighted.addClass('highlighted');
                this.scrollToOption($highlighted);
            }
        }
        
        scrollToSelected() {
            const $selected = this.$options.filter('.selected');
            if ($selected.length) {
                this.scrollToOption($selected);
            }
        }
        
        scrollToOption($option) {
            const container = this.$optionsContainer[0];
            const option = $option[0];
            
            const containerTop = container.scrollTop;
            const containerBottom = containerTop + container.clientHeight;
            const optionTop = option.offsetTop;
            const optionBottom = optionTop + option.clientHeight;
            
            if (optionTop < containerTop) {
                container.scrollTop = optionTop;
            } else if (optionBottom > containerBottom) {
                container.scrollTop = optionBottom - container.clientHeight;
            }
        }
        
        positionDropdown() {
            if (window.innerWidth <= this.options.mobileBreakpoint) return;
            
            const containerRect = this.$container[0].getBoundingClientRect();
            const dropdownHeight = this.$dropdown.outerHeight();
            const windowHeight = $(window).height();
            const scrollTop = $(window).scrollTop();
            
            // Check if dropdown would go below viewport
            if (containerRect.bottom + dropdownHeight > windowHeight) {
                // Position above input
                this.$dropdown.css({
                    top: 'auto',
                    bottom: '100%',
                    marginBottom: '8px',
                    marginTop: '0'
                });
            } else {
                // Position below input (default)
                this.$dropdown.css({
                    top: '100%',
                    bottom: 'auto',
                    marginTop: '8px',
                    marginBottom: '0'
                });
            }
        }
        
        typeAhead(char) {
            const searchChar = char.toLowerCase();
            let found = false;
            
            // Start from current position
            const startIndex = this.highlightedIndex + 1;
            
            // Search from current position to end
            for (let i = startIndex; i < this.filteredOptions.length; i++) {
                const text = this.filteredOptions.eq(i).text().toLowerCase();
                if (text.startsWith(searchChar)) {
                    this.highlightedIndex = i;
                    found = true;
                    break;
                }
            }
            
            // If not found, search from beginning to current position
            if (!found) {
                for (let i = 0; i < startIndex && i < this.filteredOptions.length; i++) {
                    const text = this.filteredOptions.eq(i).text().toLowerCase();
                    if (text.startsWith(searchChar)) {
                        this.highlightedIndex = i;
                        found = true;
                        break;
                    }
                }
            }
            
            if (found) {
                this.updateHighlight();
            }
        }
        
        destroy() {
            // Unbind events
            this.$display.off('click keydown');
            this.$container.off('click');
            this.$search.off('input keydown keypress');
            $(document).off('click.btrSelect keydown.btrSelect');
            $(window).off('resize.btrSelect');
            this.$overlay.off('click');
            
            // Remove overlay
            this.$overlay.remove();
            
            // Remove instance
            this.$container.removeData('btrSelect');
        }
    }
    
    // jQuery plugin
    $.fn.btrSelect = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('btrSelect');
            
            if (!instance) {
                instance = new BTRSelect(this, options);
            }
            
            if (typeof options === 'string' && instance[options]) {
                return instance[options]();
            }
        });
    };
    
    // Auto-initialize on ready
    $(document).ready(function() {
        $('.btr-custom-select').btrSelect();
    });
    
    // Re-initialize on dynamic content
    $(document).on('btr:content-updated', function() {
        $('.btr-custom-select').each(function() {
            if (!$(this).data('btrSelect')) {
                $(this).btrSelect();
            }
        });
    });
    
})(jQuery);