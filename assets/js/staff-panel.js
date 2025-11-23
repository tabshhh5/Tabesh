/**
 * Tabesh Staff Panel - Modern UI JavaScript
 * Handles search, status updates, theme toggle, and animations
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Helper function to safely construct REST URLs without double slashes
    function buildRestUrl(base, endpoint) {
        const cleanBase = base.replace(/\/+$/, ''); // Remove trailing slashes
        const cleanEndpoint = endpoint.replace(/^\/+/, ''); // Remove leading slashes
        return cleanBase + '/' + cleanEndpoint;
    }

    // Staff Panel Controller
    const StaffPanel = {
        // Configuration
        config: {
            searchDelay: 500,
            animationDuration: 300,
            toastDuration: 3000,
            searchResultsPerPage: 3,
        },

        // State
        state: {
            searchTimer: null,
            currentTheme: 'light',
            searchPage: 1,
            searchQuery: '',
            isLoading: false,
            allOrders: [],
        },

        /**
         * Initialize the staff panel
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.loadTheme();
            this.initializeOrders();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$body = $('body');
            this.$panel = $('.tabesh-staff-panel');
            this.$searchInput = $('.search-input');
            this.$ordersGrid = $('.tabesh-orders-grid');
            this.$themeToggle = $('.theme-toggle-btn');
            this.$orderCards = $('.tabesh-staff-order-card');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Search functionality
            this.$searchInput.on('input', this.handleSearch.bind(this));
            
            // Theme toggle
            this.$themeToggle.on('click', this.toggleTheme.bind(this));
            
            // Card expand/collapse
            $(document).on('click', '.order-card-header', this.toggleCard.bind(this));
            
            // Status stepper clicks
            $(document).on('click', '.stepper-step', this.handleStepperClick.bind(this));
            
            // Status update button
            $(document).on('click', '.status-update-btn', this.updateStatus.bind(this));
            
            // Load more button
            $(document).on('click', '.load-more-btn', this.loadMoreResults.bind(this));
            
            // Logout button
            $('.logout-btn').on('click', this.handleLogout.bind(this));
            
            // Prevent card collapse when interacting with controls
            $(document).on('click', '.status-update-section, .stepper-step, .status-update-btn', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Initialize orders data
         */
        initializeOrders: function() {
            const self = this;
            this.$orderCards.each(function() {
                const $card = $(this);
                const orderData = {
                    id: $card.data('order-id'),
                    number: $card.data('order-number'),
                    title: $card.data('book-title'),
                    size: $card.data('book-size'),
                    status: $card.data('status'),
                    customer: $card.data('customer-name'),
                    $element: $card
                };
                self.state.allOrders.push(orderData);
            });
        },

        /**
         * Handle search input
         */
        handleSearch: function(e) {
            const query = $(e.target).val().trim();
            
            // Clear previous timer
            if (this.state.searchTimer) {
                clearTimeout(this.state.searchTimer);
            }

            // Set new timer
            this.state.searchTimer = setTimeout(() => {
                this.performSearch(query);
            }, this.config.searchDelay);
        },

        /**
         * Perform search
         */
        performSearch: function(query) {
            this.state.searchQuery = query;
            this.state.searchPage = 1;
            
            if (!query) {
                // Show all orders
                this.displayOrders(this.state.allOrders);
                $('.search-results-info').hide();
                return;
            }

            // Filter orders
            const filteredOrders = this.state.allOrders.filter(order => {
                const searchableText = [
                    order.number,
                    order.title,
                    order.size,
                    order.customer
                ].join(' ').toLowerCase();
                
                return searchableText.includes(query.toLowerCase());
            });

            // Sort by relevance (exact matches first)
            filteredOrders.sort((a, b) => {
                const aRelevance = this.calculateRelevance(a, query);
                const bRelevance = this.calculateRelevance(b, query);
                return bRelevance - aRelevance;
            });

            this.displayOrders(filteredOrders.slice(0, this.config.searchResultsPerPage));
            this.updateSearchCount(filteredOrders.length, filteredOrders.length > this.config.searchResultsPerPage);
            
            // Store filtered results for "load more"
            this.state.filteredOrders = filteredOrders;
        },

        /**
         * Calculate search relevance score
         */
        calculateRelevance: function(orderData, query) {
            let score = 0;
            const queryLower = query.toLowerCase();
            
            // Exact match in order number (highest priority)
            if (orderData.number && orderData.number.toLowerCase().includes(queryLower)) {
                score += 100;
            }
            
            // Match in book title
            if (orderData.title && orderData.title.toLowerCase().includes(queryLower)) {
                score += 50;
            }
            
            // Match in size
            if (orderData.size && orderData.size.toLowerCase().includes(queryLower)) {
                score += 30;
            }
            
            // Match in customer name
            if (orderData.customer && orderData.customer.toLowerCase().includes(queryLower)) {
                score += 40;
            }
            
            return score;
        },

        /**
         * Display filtered orders
         */
        displayOrders: function(orders) {
            // Hide all orders first
            this.$orderCards.hide();
            
            // Show filtered orders
            orders.forEach(order => {
                order.$element.show();
            });

            // Show "no results" message if needed
            if (orders.length === 0) {
                if ($('.no-search-results').length === 0) {
                    this.$ordersGrid.append(`
                        <div class="no-search-results" style="grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                            <div style="font-size: 60px; color: var(--text-tertiary); margin-bottom: 15px;">🔍</div>
                            <p style="font-size: 18px; color: var(--text-secondary);">نتیجه‌ای یافت نشد</p>
                            <p style="font-size: 14px; color: var(--text-tertiary); margin-top: 10px;">لطفاً عبارت دیگری را جستجو کنید</p>
                        </div>
                    `);
                }
            } else {
                $('.no-search-results').remove();
            }
        },

        /**
         * Update search count display
         */
        updateSearchCount: function(count, hasMore) {
            $('.search-results-info').empty().hide();
            $('.load-more-container').hide();
            
            if (this.state.searchQuery) {
                const countText = count === 0 ? 'نتیجه‌ای یافت نشد' : 
                                 count === 1 ? '۱ نتیجه یافت شد' :
                                 `${this.toPersianNumber(count)} نتیجه یافت شد`;
                
                $('.search-results-info').html(`
                    <span class="results-count">${countText}</span>
                `).show();

                if (hasMore) {
                    $('.load-more-container').show();
                }
            }
        },

        /**
         * Load more search results
         */
        loadMoreResults: function() {
            if (!this.state.filteredOrders) {
                return;
            }
            
            this.state.searchPage++;
            const start = (this.state.searchPage - 1) * this.config.searchResultsPerPage;
            const end = start + this.config.searchResultsPerPage;
            
            // Show next batch
            const nextBatch = this.state.filteredOrders.slice(start, end);
            nextBatch.forEach(order => {
                order.$element.show();
            });

            // Update load more button
            if (end >= this.state.filteredOrders.length) {
                $('.load-more-container').hide();
            }
        },

        /**
         * Toggle order card expand/collapse
         */
        toggleCard: function(e) {
            const $card = $(e.currentTarget).closest('.tabesh-staff-order-card');
            const isExpanded = $card.hasClass('expanded');
            
            // Collapse all cards first
            $('.tabesh-staff-order-card').removeClass('expanded');
            
            // Expand clicked card if it wasn't expanded
            if (!isExpanded) {
                $card.addClass('expanded');
                
                // Smooth scroll to card
                $('html, body').animate({
                    scrollTop: $card.offset().top - 100
                }, this.config.animationDuration);
            }
        },

        /**
         * Handle stepper click
         */
        handleStepperClick: function(e) {
            e.stopPropagation();
            const $step = $(e.currentTarget);
            const newStatus = $step.data('status');
            const $card = $step.closest('.tabesh-staff-order-card');
            const $select = $card.find('.status-update-select');
            
            // Set select value
            $select.val(newStatus);
            
            // Highlight the step temporarily
            $step.addClass('pulse');
            setTimeout(() => {
                $step.removeClass('pulse');
            }, 500);
        },

        /**
         * Update order status
         */
        updateStatus: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.tabesh-staff-order-card');
            const orderId = $card.data('order-id');
            const $select = $card.find('.status-update-select');
            const newStatus = $select.val();
            
            if (!newStatus) {
                this.showToast('لطفاً وضعیت جدید را انتخاب کنید', 'error');
                return;
            }

            // Confirm action
            if (!confirm('آیا از تغییر وضعیت این سفارش اطمینان دارید؟')) {
                return;
            }

            // Show loading
            this.showLoading('در حال به‌روزرسانی...');
            $btn.prop('disabled', true);

            // Send AJAX request
            $.ajax({
                url: buildRestUrl(tabeshData.restUrl, 'staff/update-status'),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshData.nonce);
                },
                data: JSON.stringify({
                    order_id: orderId,
                    status: newStatus
                }),
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showToast('وضعیت با موفقیت تغییر کرد', 'success');
                        
                        // Update UI without page reload
                        this.updateCardStatus($card, newStatus);
                        
                        // Reset select
                        $select.val('');
                    } else {
                        this.showToast('خطا: ' + response.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    console.error('Status update error:', error);
                    
                    let errorMsg = 'خطا در برقراری ارتباط با سرور';
                    if (xhr.status === 0) {
                        errorMsg = 'اتصال اینترنت را بررسی کنید';
                    } else if (xhr.status === 403) {
                        errorMsg = 'شما مجوز انجام این عملیات را ندارید';
                    }
                    
                    this.showToast(errorMsg, 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Update card status in UI
         */
        updateCardStatus: function($card, newStatus) {
            const statusLabels = {
                'pending': 'در انتظار بررسی',
                'confirmed': 'تایید شده',
                'processing': 'در حال چاپ',
                'ready': 'آماده تحویل',
                'completed': 'تحویل داده شده',
                'cancelled': 'لغو شده'
            };

            // Update status badge
            const $badge = $card.find('.status-badge');
            $badge.attr('class', 'status-badge status-' + newStatus)
                  .attr('data-status', newStatus)
                  .text(statusLabels[newStatus] || newStatus);

            // Update stepper
            this.updateStepper($card, newStatus);

            // Add animation
            $badge.addClass('pulse');
            setTimeout(() => {
                $badge.removeClass('pulse');
            }, 500);
        },

        /**
         * Update stepper visualization
         */
        updateStepper: function($card, newStatus) {
            const statusOrder = ['pending', 'confirmed', 'processing', 'ready', 'completed'];
            const currentIndex = statusOrder.indexOf(newStatus);
            
            $card.find('.stepper-step').each(function(index) {
                const $step = $(this);
                $step.removeClass('active completed');
                
                if (index < currentIndex) {
                    $step.addClass('completed');
                } else if (index === currentIndex) {
                    $step.addClass('active');
                }
            });
        },

        /**
         * Toggle theme (light/dark)
         */
        toggleTheme: function() {
            this.state.currentTheme = this.state.currentTheme === 'light' ? 'dark' : 'light';
            this.$body.attr('data-theme', this.state.currentTheme);
            localStorage.setItem('tabesh-staff-theme', this.state.currentTheme);
            
            // Update button icon
            const icon = this.state.currentTheme === 'dark' ? '☀️' : '🌙';
            this.$themeToggle.html(icon + ' <span>' + (this.state.currentTheme === 'dark' ? 'حالت روشن' : 'حالت تاریک') + '</span>');
        },

        /**
         * Load saved theme
         */
        loadTheme: function() {
            const savedTheme = localStorage.getItem('tabesh-staff-theme') || 'light';
            this.state.currentTheme = savedTheme;
            this.$body.attr('data-theme', savedTheme);
            
            const icon = savedTheme === 'dark' ? '☀️' : '🌙';
            this.$themeToggle.html(icon + ' <span>' + (savedTheme === 'dark' ? 'حالت روشن' : 'حالت تاریک') + '</span>');
        },

        /**
         * Handle logout
         */
        handleLogout: function(e) {
            e.preventDefault();
            if (confirm('آیا می‌خواهید از حساب کاربری خود خارج شوید؟')) {
                window.location.href = tabeshData.logoutUrl;
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message = 'در حال بارگذاری...') {
            if ($('.loading-overlay').length === 0) {
                $('body').append(`
                    <div class="loading-overlay">
                        <div class="loading-content">
                            <div class="loading-spinner"></div>
                            <div class="loading-text">${message}</div>
                        </div>
                    </div>
                `);
            }
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('.loading-overlay').fadeOut(this.config.animationDuration, function() {
                $(this).remove();
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'success') {
            // Remove existing toasts
            $('.message-toast').remove();
            
            // Create new toast
            const $toast = $(`
                <div class="message-toast ${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($toast);
            
            // Auto remove after duration
            setTimeout(() => {
                $toast.fadeOut(this.config.animationDuration, function() {
                    $(this).remove();
                });
            }, this.config.toastDuration);
        },

        /**
         * Convert to Persian numbers
         */
        toPersianNumber: function(num) {
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return String(num).replace(/\d/g, x => persianDigits[x]);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.tabesh-staff-panel').length > 0) {
            StaffPanel.init();
        }
    });

})(jQuery);
