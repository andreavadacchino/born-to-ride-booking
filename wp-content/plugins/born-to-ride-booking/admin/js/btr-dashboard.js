/**
 * BTR Dashboard JavaScript - Modern React-inspired UI (2025)
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initDashboard();
    });
    
    /**
     * Initialize dashboard components
     */
    function initDashboard() {
        // Initialize tab navigation
        initTabNavigation();
        
        // Add smooth animations to stat cards
        initStatCards();
        
        // Initialize any datepickers
        initDatepickers();
        
        // Add tooltips to elements with data-tooltip attribute
        initTooltips();
        
        // Add scroll animations
        initScrollAnimations();
        
        // Add ripple effect to buttons
        //initRippleEffect();
        
        // Add hover effects
        initHoverEffects();
    }
    
    /**
     * Initialize tab navigation with smooth transitions
     */
    function initTabNavigation() {
        const $tabLinks = $('.btr-tabs-nav a');
        const $tabPanes = $('.btr-tab-pane');
        const $tabIndicator = $('<span class="btr-tab-indicator"></span>');
        
        // Add tab indicator
        $('.btr-tabs-nav').append($tabIndicator);
        
        // Set initial position of indicator
        updateTabIndicator($('.btr-tabs-nav li.active a'));
        
        $tabLinks.on('click', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            const target = $this.attr('href');
            
            // Update active tab
            $tabLinks.parent().removeClass('active');
            $this.parent().addClass('active');
            
            // Update tab indicator position with animation
            updateTabIndicator($this);
            
            // Hide all tab panes with animation
            $tabPanes.removeClass('active');
            $tabPanes.css({
                'opacity': 0,
                'transform': 'translateY(10px)'
            });
            
            // Show selected tab pane with animation
            setTimeout(function() {
                $tabPanes.hide();
                $(target).show();
                
                setTimeout(function() {
                    $(target).addClass('active').css({
                        'opacity': 1,
                        'transform': 'translateY(0)'
                    });
                }, 50);
            }, 200);
        });
        
        // Function to update tab indicator position
        function updateTabIndicator($activeTab) {
            if (!$activeTab.length) return;
            
            const tabPosition = $activeTab.position();
            
            $tabIndicator.css({
                'left': tabPosition.left,
                'width': $activeTab.outerWidth()
            });
        }
        
        // Update indicator position on window resize
        $(window).on('resize', function() {
            updateTabIndicator($('.btr-tabs-nav li.active a'));
        });
    }
    
    /**
     * Initialize stat cards with modern animations
     */
    function initStatCards() {
        const $statCards = $('.btr-stat-card');
        
        // Add intersection observer for animation on scroll
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const $card = $(entry.target);
                        const delay = $card.index() * 100;
                        
                        setTimeout(() => {
                            $card.addClass('animated');
                            
                            // Animate the number
                            const $value = $card.find('.btr-stat-value');
                            const finalValue = $value.text();
                            const isPrice = finalValue.includes('€');
                            
                            let numValue = parseFloat(finalValue.replace(/[^0-9.,]/g, '').replace(',', '.'));
                            
                            $value.text(isPrice ? '€0' : '0');
                            
                            $({counter: 0}).animate({counter: numValue}, {
                                duration: 1000,
                                easing: 'easeOutQuart',
                                step: function() {
                                    if (isPrice) {
                                        $value.text('€' + formatNumber(this.counter.toFixed(2), 2, ',', '.'));
                                    } else {
                                        $value.text(Math.floor(this.counter));
                                    }
                                },
                                complete: function() {
                                    $value.text(finalValue);
                                }
                            });
                        }, delay);
                        
                        // Unobserve after animation
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            $statCards.each(function() {
                observer.observe(this);
            });
        } else {
            // Fallback for browsers without IntersectionObserver
            $statCards.addClass('animated');
        }
    }
    
    /**
     * Initialize any date pickers used in the dashboard
     */
    function initDatepickers() {
        if ($.fn.datepicker) {
            $('.btr-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                showAnim: 'fadeIn',
                beforeShow: function(input, inst) {
                    // Add custom class for styling
                    setTimeout(function() {
                        inst.dpDiv.addClass('btr-datepicker-modern');
                    }, 10);
                }
            });
        }
    }
    
    /**
     * Initialize tooltips with modern animations
     */
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            const $this = $(this);
            
            $this.on('mouseenter', function() {
                const tooltipText = $this.data('tooltip');
                const tooltip = $('<div class="btr-tooltip"><div class="btr-tooltip-content">' + tooltipText + '</div></div>');
                
                $('body').append(tooltip);
                
                const position = $this.offset();
                const tooltipWidth = tooltip.outerWidth();
                const tooltipHeight = tooltip.outerHeight();
                
                // Position the tooltip
                tooltip.css({
                    top: position.top - tooltipHeight - 10,
                    left: position.left + ($this.outerWidth() / 2) - (tooltipWidth / 2)
                });
                
                // Ensure tooltip is within viewport
                const rightEdge = tooltip.offset().left + tooltipWidth;
                const windowWidth = $(window).width();
                
                if (rightEdge > windowWidth) {
                    tooltip.css('left', windowWidth - tooltipWidth - 10);
                }
                
                if (tooltip.offset().left < 10) {
                    tooltip.css('left', 10);
                }
                
                // Animate in
                setTimeout(function() {
                    tooltip.addClass('btr-tooltip-visible');
                }, 10);
            });
            
            $this.on('mouseleave', function() {
                $('.btr-tooltip').removeClass('btr-tooltip-visible');
                setTimeout(function() {
                    $('.btr-tooltip').remove();
                }, 300);
            });
        });
    }
    
    /**
     * Initialize scroll animations for elements
     */
    function initScrollAnimations() {
        if ('IntersectionObserver' in window) {
            const animatedElements = $('.btr-dashboard-main, .btr-dashboard-sidebar, .btr-sidebar-widget');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('btr-animated');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            animatedElements.each(function() {
                observer.observe(this);
            });
        }
    }
    
    /**
     * Add ripple effect to buttons
     */
    function initRippleEffect() {
        $(document).on('mousedown', '.button, .btr-tabs-nav a, .btr-quick-links a', function(e) {
            const $this = $(this);
            
            // Remove any existing ripples
            $this.find('.btr-ripple').remove();
            
            // Create ripple element
            const $ripple = $('<span class="btr-ripple"></span>');
            $this.append($ripple);
            
            // Get position relative to button
            const posX = e.pageX - $this.offset().left;
            const posY = e.pageY - $this.offset().top;
            
            // Calculate ripple size (larger of width or height * 2)
            const size = Math.max($this.outerWidth(), $this.outerHeight()) * 2;
            
            // Position and animate ripple
            $ripple.css({
                top: posY - (size / 2),
                left: posX - (size / 2),
                width: size,
                height: size
            }).addClass('btr-animate-ripple');
            
            // Remove ripple after animation
            setTimeout(function() {
                $ripple.remove();
            }, 600);
        });
    }
    
    /**
     * Add hover effects to elements
     */
    function initHoverEffects() {
        // Add hover effect to table rows
        $('.btr-recent-items-table tbody tr').on('mouseenter', function() {
            $(this).addClass('btr-row-hover');
            $(this).prevAll().addClass('btr-row-dim');
            $(this).nextAll().addClass('btr-row-dim');
        }).on('mouseleave', function() {
            $('.btr-recent-items-table tbody tr').removeClass('btr-row-hover btr-row-dim');
        });
        
        // Add hover effect to stat cards
        $('.btr-stat-card').on('mouseenter', function() {
            $(this).addClass('btr-card-hover');
            $(this).siblings().addClass('btr-card-dim');
        }).on('mouseleave', function() {
            $('.btr-stat-card').removeClass('btr-card-hover btr-card-dim');
        });
    }
    
    /**
     * Refresh dashboard data via AJAX
     * 
     * @param {string} timeRange - Time range for data (today, week, month)
     */
    function refreshDashboardData(timeRange) {
        // Add loading state
        const $dashboard = $('.btr-dashboard-wrap');
        $dashboard.addClass('is-loading');
        
        // Add loading overlay
        const $overlay = $('<div class="btr-loading-overlay"><div class="btr-loading-spinner"></div></div>');
        $dashboard.append($overlay);
        
        // Fade in overlay
        setTimeout(function() {
            $overlay.addClass('visible');
        }, 10);
        
        $.ajax({
            url: btrDashVars.ajaxUrl,
            type: 'POST',
            data: {
                action: 'btr_refresh_dashboard',
                nonce: btrDashVars.nonce,
                time_range: timeRange
            },
            success: function(response) {
                if (response.success) {
                    // Update statistics with new data
                    updateDashboardStats(response.data.stats);
                    
                    // Update recent bookings
                    if (response.data.recent_bookings) {
                        updateRecentBookings(response.data.recent_bookings);
                    }
                    
                    // Update recent quotes
                    if (response.data.recent_quotes) {
                        updateRecentQuotes(response.data.recent_quotes);
                    }
                }
            },
            complete: function() {
                // Fade out overlay
                $overlay.removeClass('visible');
                
                // Remove overlay after animation
                setTimeout(function() {
                    $overlay.remove();
                    $dashboard.removeClass('is-loading');
                }, 300);
            }
        });
    }
    
    /**
     * Update dashboard statistics with new data
     * 
     * @param {Object} stats - Statistics data object
     */
    function updateDashboardStats(stats) {
        // Update each stat card with animation
        Object.keys(stats).forEach(function(key, index) {
            const $statCard = $('.btr-stat-card[data-stat="' + key + '"]');
            const $statValue = $statCard.find('.btr-stat-value');
            
            if ($statValue.length) {
                const currentValue = parseFloat($statValue.text().replace(/[^0-9.-]+/g, '').replace(',', '.'));
                const newValue = stats[key];
                
                // Add highlight effect
                setTimeout(function() {
                    $statCard.addClass('btr-stat-updating');
                    
                    // Animate the value change
                    $({value: currentValue}).animate({value: newValue}, {
                        duration: 800,
                        easing: 'easeOutQuart',
                        step: function() {
                            if (key === 'valore') {
                                $statValue.text('€' + formatNumber(this.value.toFixed(2), 2, ',', '.'));
                            } else {
                                $statValue.text(Math.floor(this.value));
                            }
                        },
                        complete: function() {
                            if (key === 'valore') {
                                $statValue.text('€' + formatNumber(newValue.toFixed(2), 2, ',', '.'));
                            } else {
                                $statValue.text(newValue);
                            }
                            
                            // Remove highlight effect
                            setTimeout(function() {
                                $statCard.removeClass('btr-stat-updating');
                            }, 300);
                        }
                    });
                }, index * 100);
            }
        });
    }
    
    /**
     * Update recent bookings table with new data
     * 
     * @param {Array} bookings - Array of booking objects
     */
    function updateRecentBookings(bookings) {
        const $tabPane = $('#btr-recent-bookings');
        const $table = $tabPane.find('.btr-recent-items-table tbody');
        
        if (!$table.length) {
            return;
        }
        
        // Clear existing rows with fade out
        $table.find('tr').addClass('btr-row-fadeout');
        
        setTimeout(function() {
            $table.empty();
            
            if (bookings.length === 0) {
                // No bookings, show message
                $tabPane.find('.btr-recent-items-table').hide();
                $tabPane.append(
                    '<div class="btr-no-items">' +
                    '<p>' + btrDashVars.labels.noBookings + '</p>' +
                    '</div>'
                );
                return;
            }
            
            // Make sure table is visible
            $tabPane.find('.btr-recent-items-table').show();
            $tabPane.find('.btr-no-items').remove();
            
            // Add new rows with fade in
            bookings.forEach(function(booking, index) {
                const row = $('<tr class="btr-row-fadein"></tr>').css({
                    'animation-delay': (index * 50) + 'ms'
                }).html(
                    '<td>#' + booking.id + '</td>' +
                    '<td>' + booking.date + '</td>' +
                    '<td>' + booking.customer + '</td>' +
                    '<td>' + booking.package + '</td>' +
                    '<td><span class="btr-status btr-status-' + booking.status + '">' + booking.status_label + '</span></td>' +
                    '<td>€' + formatNumber(booking.value, 2, ',', '.') + '</td>' +
                    '<td><a href="' + booking.view_url + '" class="button button-small">' + btrDashVars.labels.details + '</a></td>'
                );
                    
                $table.append(row);
                
                // Initialize hover effects for new rows
                initHoverEffects();
            });
        }, 300);
    }
    
    /**
     * Update recent quotes table with new data
     * 
     * @param {Array} quotes - Array of quote objects
     */
    function updateRecentQuotes(quotes) {
        const $tabPane = $('#btr-recent-quotes');
        const $table = $tabPane.find('.btr-recent-items-table tbody');
        
        if (!$table.length) {
            return;
        }
        
        // Clear existing rows with fade out
        $table.find('tr').addClass('btr-row-fadeout');
        
        setTimeout(function() {
            $table.empty();
            
            if (quotes.length === 0) {
                // No quotes, show message
                $tabPane.find('.btr-recent-items-table').hide();
                $tabPane.append(
                    '<div class="btr-no-items">' +
                    '<p>' + btrDashVars.labels.noQuotes + '</p>' +
                    '</div>'
                );
                return;
            }
            
            // Make sure table is visible
            $tabPane.find('.btr-recent-items-table').show();
            $tabPane.find('.btr-no-items').remove();
            
            // Add new rows with fade in
            quotes.forEach(function(quote, index) {
                const row = $('<tr class="btr-row-fadein"></tr>').css({
                    'animation-delay': (index * 50) + 'ms'
                }).html(
                    '<td>#' + quote.id + '</td>' +
                    '<td>' + quote.date + '</td>' +
                    '<td>' + quote.customer + '</td>' +
                    '<td>' + quote.package + '</td>' +
                    '<td><span class="btr-status btr-status-' + quote.status + '">' + quote.status_label + '</span></td>' +
                    '<td>€' + formatNumber(quote.value, 2, ',', '.') + '</td>' +
                    '<td><a href="' + quote.view_url + '" class="button button-small">' + btrDashVars.labels.details + '</a></td>'
                );
                    
                $table.append(row);
                
                // Initialize hover effects for new rows
                initHoverEffects();
            });
        }, 300);
    }

    
    /**
     * Format number with thousands separator and decimal point
     * 
     * @param {number} number - Number to format
     * @param {number} decimals - Number of decimal places
     * @param {string} decPoint - Decimal point character
     * @param {string} thousandsSep - Thousands separator character
     * @return {string} Formatted number
     */
    function formatNumber(number, decimals, decPoint, thousandsSep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number;
        var prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
        var sep = (typeof thousandsSep === 'undefined') ? ',' : thousandsSep;
        var dec = (typeof decPoint === 'undefined') ? '.' : decPoint;
        var s = '';

        var toFixedFix = function (n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };

        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }

        return s.join(dec);
    }
    
    // Add easing functions if not available
    if ($.easing.easeOutQuart === undefined) {
        $.easing.easeOutQuart = function(x) {
            return 1 - Math.pow(1 - x, 4);
        };
    }
    
})(jQuery);
