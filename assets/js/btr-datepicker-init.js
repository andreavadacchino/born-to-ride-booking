/**
 * Born to Ride - Date Picker Initialization
 * 
 * @package Born_To_Ride_Booking
 */

(function($) {
    'use strict';
    
    // Initialize date picker on all date inputs
    function initializeDatePickers() {
        // Find all date inputs in the booking forms
        $('input[type="date"]').each(function() {
            const $input = $(this);
            
            // Skip if already initialized
            if ($input.data('btrDatePicker')) {
                return;
            }
            
            // Get input attributes
            const minDate = $input.attr('min');
            const maxDate = $input.attr('max');
            const required = $input.prop('required');
            
            // Determine placeholder based on context
            let placeholder = 'Seleziona una data';
            if ($input.attr('id') && $input.attr('id').includes('nascita')) {
                placeholder = 'Data di nascita';
            } else if ($input.attr('name') && $input.attr('name').includes('nascita')) {
                placeholder = 'Data di nascita';
            }
            
            // Initialize date picker with Italian settings
            $input.btrDatePicker({
                format: 'dd/mm/yyyy',
                language: 'it',
                placeholder: placeholder,
                todayButton: true,
                clearButton: !required,
                autoClose: true,
                minDate: minDate ? new Date(minDate) : null,
                maxDate: maxDate ? new Date(maxDate) : null,
                onSelect: function(date) {
                    // Trigger change event for validation
                    $input.trigger('change');
                    
                    // If this is a birth date field, trigger age validation
                    if ($input.attr('name') && $input.attr('name').includes('[data_nascita]')) {
                        // Extract the participant index
                        const match = $input.attr('name').match(/anagrafici\[(\d+)\]/);
                        if (match && match[1]) {
                            const index = match[1];
                            // Trigger age validation
                            $(document).trigger('btr:validate-age', [index, date]);
                        }
                    }
                }
            });
        });
    }
    
    // Initialize on document ready
    $(document).ready(function() {
        initializeDatePickers();
    });
    
    // Re-initialize when new content is added dynamically
    $(document).on('btr:content-updated', function() {
        initializeDatePickers();
    });
    
    // Re-initialize after AJAX calls
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if this is a BTR-related AJAX call
        if (settings.url && settings.url.includes('btr_')) {
            setTimeout(function() {
                initializeDatePickers();
            }, 100);
        }
    });
    
    // Monitor DOM changes for dynamically added date inputs
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            let shouldInitialize = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node contains date inputs
                            if ($(node).is('input[type="date"]') || $(node).find('input[type="date"]').length > 0) {
                                shouldInitialize = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldInitialize) {
                setTimeout(initializeDatePickers, 50);
            }
        });
        
        // Start observing the document body for changes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Special handling for date range restrictions
    $(document).on('btr:set-date-restrictions', function(e, data) {
        if (data.inputId && data.minDate) {
            const $input = $('#' + data.inputId);
            const picker = $input.data('btrDatePicker');
            
            if (picker) {
                picker.options.minDate = new Date(data.minDate);
                if (data.maxDate) {
                    picker.options.maxDate = new Date(data.maxDate);
                }
                picker.renderCalendar();
            }
        }
    });
    
})(jQuery);