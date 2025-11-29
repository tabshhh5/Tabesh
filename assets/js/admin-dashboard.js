/**
 * Tabesh Admin Dashboard - Super Panel JavaScript
 * Handles search, filters, status updates, and animations
 * TradingView/MetaTrader inspired functionality
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Helper function to safely construct REST URLs
    function buildRestUrl(base, endpoint) {
        const cleanBase = base.replace(/\/+$/, '');
        const cleanEndpoint = endpoint.replace(/^\/+/, '');
        return cleanBase + '/' + cleanEndpoint;
    }

    // Status to tab category mapping
    const STATUS_TAB_CATEGORY_MAP = {
        'cancelled': 'cancelled',
        'completed': 'archived',
        'processing': 'processing,current',
        'pending': 'current',
        'confirmed': 'current',
        'ready': 'current'
    };

    // Admin Dashboard Controller
    const AdminDashboard = {
        // Configuration
        config: {
            searchDelay: 500,
            animationDuration: 300,
            toastDuration: 3000,
            resultsPerPage: 20,
        },

        // State
        state: {
            searchTimer: null,
            currentTheme: 'light',
            searchQuery: '',
            isLoading: false,
            expandedOrderId: null,
            currentPage: 1,
            totalPages: 1,
            activeOrderTab: 'current',
            filters: {
                status: '',
                customer: '',
                priceMin: '',
                priceMax: '',
                sortBy: 'newest',
            },
        },

        /**
         * Initialize the dashboard
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.loadTheme();
            this.initializeOrderRows();
            this.initializeOrderTabs();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$dashboard = $('.tabesh-admin-dashboard');
            this.$searchInput = $('.global-search-input');
            this.$ordersTable = $('.orders-table');
            this.$ordersBody = $('.orders-table tbody');
            this.$themeToggle = $('.theme-toggle-btn');
            this.$filterStatus = $('#filter-status');
            this.$filterSort = $('#filter-sort');
            this.$filterReset = $('.filter-reset-btn');
            this.$ordersTabs = $('.orders-tab');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Search functionality
            this.$searchInput.on('input', this.handleSearch.bind(this));
            $('.search-btn').on('click', this.performSearchNow.bind(this));

            // Theme toggle
            this.$themeToggle.on('click', this.toggleTheme.bind(this));

            // Order row click - expand/collapse
            $(document).on('click', '.orders-table tbody tr.order-row', this.toggleOrderDetails.bind(this));

            // Orders tab switching (for filtering by category)
            $(document).on('click', '.orders-tab', this.switchOrdersTab.bind(this));

            // Tab switching (for order details tabs)
            $(document).on('click', '.details-tab', this.switchTab.bind(this));

            // Status update
            $(document).on('click', '.status-update-btn', this.updateStatus.bind(this));

            // Substep checkbox
            $(document).on('change', '.substep-checkbox', this.handleSubstepToggle.bind(this));

            // File download
            $(document).on('click', '.file-download-btn', this.handleFileDownload.bind(this));

            // Filter changes
            this.$filterStatus.on('change', this.applyFilters.bind(this));
            this.$filterSort.on('change', this.applyFilters.bind(this));
            this.$filterReset.on('click', this.resetFilters.bind(this));

            // Edit form
            $(document).on('click', '.edit-save-btn', this.saveOrderEdit.bind(this));
            $(document).on('click', '.edit-cancel-btn', this.cancelOrderEdit.bind(this));

            // Modal close
            $(document).on('click', '.modal-close-btn', this.closeModal.bind(this));

            // Pagination
            $(document).on('click', '.pagination-btn:not(:disabled)', this.handlePagination.bind(this));

            // Prevent row click when interacting with controls
            $(document).on('click', '.details-tabs, .details-tab-content, .status-update-btn, .file-download-btn', function(e) {
                e.stopPropagation();
            });

            // Keyboard navigation
            $(document).on('keydown', this.handleKeyboard.bind(this));
        },

        /**
         * Initialize order rows data
         */
        initializeOrderRows: function() {
            this.$ordersBody.find('tr.order-row').each(function() {
                $(this).data('initialized', true);
            });
        },

        /**
         * Initialize order tabs - show only current orders by default
         */
        initializeOrderTabs: function() {
            this.state.activeOrderTab = 'current';
            this.filterOrdersByTab('current');
        },

        /**
         * Switch orders tab (filter by category)
         */
        switchOrdersTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            
            // Update tab states
            $('.orders-tab').removeClass('active');
            $tab.addClass('active');
            
            // Store active tab
            this.state.activeOrderTab = tabId;
            
            // Filter orders by tab
            this.filterOrdersByTab(tabId);
            
            // Reset status filter when switching tabs
            if (this.$filterStatus.length) {
                this.$filterStatus.val('');
                this.state.filters.status = '';
            }
        },

        /**
         * Filter orders by tab category
         */
        filterOrdersByTab: function(tabId) {
            const self = this;
            let visibleCount = 0;
            
            // Hide all expanded details first
            this.$ordersBody.find('.order-details-row.visible').removeClass('visible');
            this.$ordersBody.find('tr.order-row.expanded').removeClass('expanded');
            this.state.expandedOrderId = null;
            
            // Filter rows based on tab
            this.$ordersBody.find('tr.order-row').each(function() {
                const $row = $(this);
                const $detailsRow = $row.next('.order-details-row');
                const tabCategory = $row.data('tab-category') || '';
                
                // Check if the order belongs to the selected tab (comma-separated categories)
                const categories = tabCategory.split(',').map(function(cat) { return cat.trim(); });
                const shouldShow = categories.includes(tabId);
                
                if (shouldShow) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                    $detailsRow.hide().removeClass('visible');
                }
            });
            
            // Update row numbers for visible rows
            this.updateVisibleRowNumbers();
            
            // Show/hide no results message
            this.handleNoOrdersMessage(visibleCount);
        },

        /**
         * Update row numbers for visible order rows
         */
        updateVisibleRowNumbers: function() {
            let rowNum = 0;
            this.$ordersBody.find('tr.order-row:visible').each(function() {
                rowNum++;
                $(this).find('.row-number').text(rowNum);
            });
        },

        /**
         * Handle no orders message display
         */
        handleNoOrdersMessage: function(visibleCount) {
            const $wrapper = $('.orders-table-wrapper');
            let $noResults = $wrapper.find('.no-orders-tab-message');
            
            if (visibleCount === 0) {
                const noOrdersText = (typeof tabeshAdminData !== 'undefined' && tabeshAdminData.strings && tabeshAdminData.strings.noOrdersInTab)
                    ? tabeshAdminData.strings.noOrdersInTab
                    : 'سفارشی در این بخش وجود ندارد';
                    
                if (!$noResults.length) {
                    $noResults = $('<div class="no-orders-tab-message">' +
                        '<div class="no-orders-icon">📭</div>' +
                        '<p class="no-orders-text">' + noOrdersText + '</p>' +
                    '</div>');
                    $wrapper.append($noResults);
                }
                $noResults.show();
                this.$ordersTable.hide();
            } else {
                $noResults.hide();
                this.$ordersTable.show();
            }
        },

        /**
         * Handle search input
         */
        handleSearch: function(e) {
            const query = $(e.target).val().trim();
            
            if (this.state.searchTimer) {
                clearTimeout(this.state.searchTimer);
            }

            this.state.searchTimer = setTimeout(() => {
                this.performSearch(query);
            }, this.config.searchDelay);
        },

        /**
         * Perform search immediately
         */
        performSearchNow: function() {
            const query = this.$searchInput.val().trim();
            this.performSearch(query);
        },

        /**
         * Perform search via AJAX
         */
        performSearch: function(query) {
            this.state.searchQuery = query;
            this.state.currentPage = 1;

            if (!query && !this.hasActiveFilters()) {
                // No search query and no filters - show all
                this.$ordersBody.find('tr').show();
                $('.search-results-info').removeClass('visible');
                return;
            }

            this.showLoading('در حال جستجو...');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'admin/search-orders'),
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: {
                    query: query,
                    status: this.state.filters.status,
                    sort_by: this.state.filters.sortBy,
                    price_min: this.state.filters.priceMin,
                    price_max: this.state.filters.priceMax,
                    page: this.state.currentPage,
                    per_page: this.config.resultsPerPage,
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.renderSearchResults(response.data);
                    } else {
                        this.showToast('خطا در جستجو: ' + response.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    console.error('Search error:', error);
                    
                    // Fallback to client-side filtering
                    this.clientSideFilter(query);
                }
            });
        },

        /**
         * Client-side filtering fallback
         */
        clientSideFilter: function(query) {
            const queryLower = query.toLowerCase();
            const activeTab = this.state.activeOrderTab;
            let visibleCount = 0;

            this.$ordersBody.find('tr.order-row').each(function() {
                const $row = $(this);
                const tabCategory = $row.data('tab-category') || '';
                const categories = tabCategory.split(',').map(function(cat) { return cat.trim(); });
                
                // First check if the row belongs to the active tab
                if (!categories.includes(activeTab)) {
                    $row.hide();
                    $row.next('.order-details-row').hide();
                    return;
                }
                
                const searchableText = [
                    $row.data('order-number'),
                    $row.data('book-title'),
                    $row.data('customer-name'),
                    $row.data('customer-phone'),
                    $row.data('province'),
                    $row.data('book-size'),
                    $row.data('user-id'),
                ].join(' ').toLowerCase();

                if (!query || searchableText.includes(queryLower)) {
                    $row.show();
                    $row.next('.order-details-row').hide();
                    visibleCount++;
                } else {
                    $row.hide();
                    $row.next('.order-details-row').hide();
                }
            });

            this.updateVisibleRowNumbers();
            this.updateSearchCount(visibleCount);
            this.handleNoOrdersMessage(visibleCount);
        },

        /**
         * Render search results
         */
        renderSearchResults: function(data) {
            // Update order rows visibility or rebuild table
            if (data.orders && data.orders.length > 0) {
                this.updateOrdersTable(data.orders);
            } else {
                this.showNoResults();
            }

            this.updateSearchCount(data.total || 0);
            this.updatePagination(data.total_pages || 1, data.current_page || 1);
        },

        /**
         * Update orders table with new data
         */
        updateOrdersTable: function(orders) {
            // For now, just filter existing rows based on order IDs
            const orderIds = orders.map(o => o.id);
            
            this.$ordersBody.find('tr.order-row').each(function() {
                const $row = $(this);
                const orderId = parseInt($row.data('order-id'));
                
                if (orderIds.includes(orderId)) {
                    $row.show();
                } else {
                    $row.hide();
                    $row.next('.order-details-row').hide();
                }
            });
        },

        /**
         * Show no results message
         */
        showNoResults: function() {
            this.$ordersBody.find('tr').hide();
            
            if (!$('.no-results-row').length) {
                this.$ordersBody.append(`
                    <tr class="no-results-row">
                        <td colspan="12" style="text-align: center; padding: 60px 20px;">
                            <div style="font-size: 50px; margin-bottom: 15px;">🔍</div>
                            <p style="font-size: 18px; color: var(--admin-text-secondary);">نتیجه‌ای یافت نشد</p>
                        </td>
                    </tr>
                `);
            } else {
                $('.no-results-row').show();
            }
        },

        /**
         * Update search count display
         */
        updateSearchCount: function(count) {
            const $info = $('.search-results-info');
            
            if (this.state.searchQuery || this.hasActiveFilters()) {
                const countText = count === 0 ? 'نتیجه‌ای یافت نشد' : 
                                 count === 1 ? '۱ نتیجه یافت شد' :
                                 this.toPersianNumber(count) + ' نتیجه یافت شد';
                
                $info.find('.results-count').text(countText);
                $info.addClass('visible');
            } else {
                $info.removeClass('visible');
            }
        },

        /**
         * Check if any filters are active
         */
        hasActiveFilters: function() {
            return this.state.filters.status || 
                   this.state.filters.priceMin || 
                   this.state.filters.priceMax;
        },

        /**
         * Apply filters
         */
        applyFilters: function() {
            this.state.filters.status = this.$filterStatus.val();
            this.state.filters.sortBy = this.$filterSort.val();
            
            this.performSearch(this.state.searchQuery);
        },

        /**
         * Reset filters
         */
        resetFilters: function() {
            this.$filterStatus.val('');
            this.$filterSort.val('newest');
            this.state.filters = {
                status: '',
                customer: '',
                priceMin: '',
                priceMax: '',
                sortBy: 'newest',
            };
            
            this.$searchInput.val('');
            this.state.searchQuery = '';
            
            // Re-apply tab filtering (show only orders in active tab)
            this.filterOrdersByTab(this.state.activeOrderTab);
            
            $('.no-results-row').remove();
            $('.search-results-info').removeClass('visible');
        },

        /**
         * Toggle order details row
         */
        toggleOrderDetails: function(e) {
            const $row = $(e.currentTarget);
            const $detailsRow = $row.next('.order-details-row');
            const orderId = $row.data('order-id');

            if ($detailsRow.hasClass('visible')) {
                // Collapse
                $detailsRow.removeClass('visible');
                $row.removeClass('expanded');
                this.state.expandedOrderId = null;
            } else {
                // Collapse any other expanded rows first
                this.$ordersBody.find('.order-details-row.visible').removeClass('visible');
                this.$ordersBody.find('tr.order-row.expanded').removeClass('expanded');

                // Expand this row
                $detailsRow.addClass('visible');
                $row.addClass('expanded');
                this.state.expandedOrderId = orderId;

                // Load order details if not already loaded
                if (!$detailsRow.data('loaded')) {
                    this.loadOrderDetails(orderId, $detailsRow);
                }

                // Smooth scroll to row
                $('html, body').animate({
                    scrollTop: $row.offset().top - 100
                }, this.config.animationDuration);
            }
        },

        /**
         * Load order details via AJAX
         */
        loadOrderDetails: function(orderId, $detailsRow) {
            const $content = $detailsRow.find('.order-details-content');
            $content.html('<div class="loading-content" style="padding: 40px; text-align: center;"><div class="loading-spinner"></div><div class="loading-text">در حال بارگذاری...</div></div>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'admin/order-details/' + orderId),
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: (response) => {
                    if (response.success) {
                        $content.html(response.data.html);
                        $detailsRow.data('loaded', true);
                        
                        // Initialize the first tab as active
                        $content.find('.details-tab:first').addClass('active');
                        $content.find('.details-tab-content:first').addClass('active');
                    } else {
                        $content.html('<div style="padding: 40px; text-align: center; color: var(--admin-error);">خطا در بارگذاری اطلاعات</div>');
                    }
                },
                error: () => {
                    $content.html('<div style="padding: 40px; text-align: center; color: var(--admin-error);">خطا در برقراری ارتباط با سرور</div>');
                }
            });
        },

        /**
         * Switch tab
         */
        switchTab: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            const $container = $tab.closest('.order-details-content');

            // Update tab states
            $container.find('.details-tab').removeClass('active');
            $tab.addClass('active');

            // Update content states
            $container.find('.details-tab-content').removeClass('active');
            $container.find('.details-tab-content[data-tab="' + tabId + '"]').addClass('active');
        },

        /**
         * Update order status
         */
        updateStatus: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const $container = $btn.closest('.status-update-container');
            const $select = $container.find('.status-select');
            const orderId = $container.data('order-id');
            const newStatus = $select.val();

            if (!newStatus) {
                this.showToast('لطفاً وضعیت جدید را انتخاب کنید', 'warning');
                return;
            }

            if (!confirm('آیا از تغییر وضعیت این سفارش اطمینان دارید؟')) {
                return;
            }

            this.showLoading('در حال به‌روزرسانی...');
            $btn.prop('disabled', true);

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'staff/update-status'),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: JSON.stringify({
                    order_id: orderId,
                    status: newStatus
                }),
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        this.showToast('وضعیت با موفقیت تغییر کرد', 'success');
                        this.updateStatusInUI(orderId, newStatus);
                        $select.val('');
                    } else {
                        this.showToast('خطا: ' + response.message, 'error');
                    }
                },
                error: (xhr) => {
                    this.hideLoading();
                    let errorMsg = 'خطا در برقراری ارتباط با سرور';
                    if (xhr.status === 403) {
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
         * Update status in UI
         */
        updateStatusInUI: function(orderId, newStatus) {
            const statusLabels = {
                'pending': 'در انتظار',
                'confirmed': 'تایید شده',
                'processing': 'در حال چاپ',
                'ready': 'آماده',
                'completed': 'تحویل شده',
                'cancelled': 'لغو شده'
            };

            // Update in table row
            const $row = $('tr.order-row[data-order-id="' + orderId + '"]');
            const $badge = $row.find('.status-badge');
            
            $badge.attr('class', 'status-badge status-' + newStatus)
                  .text(statusLabels[newStatus] || newStatus);

            // Add pulse animation
            $badge.addClass('pulse');
            setTimeout(() => {
                $badge.removeClass('pulse');
            }, 500);

            // Update progress bar based on status
            const progressMap = {
                'pending': 10,
                'confirmed': 25,
                'processing': 50,
                'ready': 80,
                'completed': 100,
                'cancelled': 0
            };

            const $progressBar = $row.find('.progress-bar-fill');
            if ($progressBar.length) {
                $progressBar.css('width', progressMap[newStatus] + '%');
            }

            // Update data-status attribute
            $row.attr('data-status', newStatus);

            // Update data-tab-category based on new status using the mapping constant
            const newTabCategory = STATUS_TAB_CATEGORY_MAP[newStatus] || 'current';
            $row.data('tab-category', newTabCategory).attr('data-tab-category', newTabCategory);

            // Update tab counts and re-apply current tab filter
            this.updateTabCounts();
            this.filterOrdersByTab(this.state.activeOrderTab);
        },

        /**
         * Update tab counts based on current order rows
         */
        updateTabCounts: function() {
            const counts = { current: 0, processing: 0, archived: 0, cancelled: 0 };
            
            this.$ordersBody.find('tr.order-row').each(function() {
                const rawCategory = $(this).data('tab-category');
                const categories = String(rawCategory || '').split(',');
                categories.forEach(function(cat) {
                    cat = cat.trim();
                    if (cat in counts) {
                        counts[cat]++;
                    }
                });
            });
            
            // Update tab count badges
            $('.orders-tab').each(function() {
                const tab = $(this).data('tab');
                $(this).find('.tab-count').text(counts[tab] || 0);
            });
        },

        /**
         * Handle substep toggle
         */
        handleSubstepToggle: function(e) {
            e.stopPropagation();

            const $checkbox = $(e.currentTarget);
            const substepId = $checkbox.data('substep-id');
            const isCompleted = $checkbox.is(':checked');

            this.updateSubstep(substepId, isCompleted);
        },

        /**
         * Update substep via API
         */
        updateSubstep: function(substepId, isCompleted) {
            this.showLoading('در حال بهروزرسانی...');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'print-substeps/update'),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: JSON.stringify({
                    substep_id: substepId,
                    is_completed: isCompleted
                }),
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        this.showToast(response.message || 'بهروزرسانی انجام شد', 'success');
                        
                        // Update UI
                        const $item = $('.substep-item[data-substep-id="' + substepId + '"]');
                        if (isCompleted) {
                            $item.addClass('completed');
                            if (!$item.find('.substep-badge').length) {
                                $item.append('<span class="substep-badge">✓ انجام شد</span>');
                            }
                        } else {
                            $item.removeClass('completed');
                            $item.find('.substep-badge').remove();
                        }

                        // Update progress
                        if (response.data && response.data.progress !== undefined) {
                            $item.closest('.print-substeps-container').find('.progress-badge')
                                .text(response.data.progress + '% تکمیل شده');
                        }

                        // If all completed, reload
                        if (response.data && response.data.all_completed) {
                            this.showToast('تمام مراحل چاپ تکمیل شد!', 'success');
                            setTimeout(() => location.reload(), 2000);
                        }
                    } else {
                        this.showToast('خطا: ' + response.message, 'error');
                        // Revert checkbox
                        $('.substep-checkbox[data-substep-id="' + substepId + '"]').prop('checked', !isCompleted);
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showToast('خطا در برقراری ارتباط با سرور', 'error');
                    // Revert checkbox
                    $('.substep-checkbox[data-substep-id="' + substepId + '"]').prop('checked', !isCompleted);
                }
            });
        },

        /**
         * Handle file download
         * Uses hidden iframe method to download without leaving the page
         */
        handleFileDownload: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const fileId = $btn.data('file-id');
            const originalText = $btn.html();

            // Disable button and show loading state
            $btn.prop('disabled', true).html('⏳ در حال دانلود...');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'files/generate-token'),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: JSON.stringify({
                    file_id: fileId
                }),
                success: (response) => {
                    if (response.success && response.download_url) {
                        // Use hidden iframe for download without leaving the page
                        const $iframe = $('<iframe>', {
                            style: 'display:none',
                            src: response.download_url
                        }).appendTo('body');

                        // Cleanup iframe and re-enable button after sufficient time for download to start
                        // The timeout is set to 3 seconds to accommodate typical server response times
                        // for initiating the download stream. The iframe is just a trigger - 
                        // the actual download continues in the browser's download manager.
                        const downloadInitTimeout = 3000;
                        setTimeout(() => {
                            $iframe.remove();
                            $btn.prop('disabled', false).html(originalText);
                            this.showToast('دانلود شروع شد', 'success');
                        }, downloadInitTimeout);
                    } else {
                        $btn.prop('disabled', false).html(originalText);
                        this.showToast(response.message || 'خطا در ایجاد لینک دانلود', 'error');
                    }
                },
                error: () => {
                    $btn.prop('disabled', false).html(originalText);
                    this.showToast('خطا در برقراری ارتباط با سرور', 'error');
                }
            });
        },

        /**
         * Save order edit
         */
        saveOrderEdit: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const $form = $btn.closest('.edit-form-grid');
            const orderId = $form.data('order-id');

            const formData = {};
            $form.find('.edit-input, .edit-select, .edit-textarea').each(function() {
                const $input = $(this);
                formData[$input.attr('name')] = $input.val();
            });

            this.showLoading('در حال ذخیره...');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'admin/update-order/' + orderId),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: JSON.stringify(formData),
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        this.showToast('اطلاعات با موفقیت ذخیره شد', 'success');
                        // Refresh the page to show updated data
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.showToast('خطا: ' + response.message, 'error');
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showToast('خطا در برقراری ارتباط با سرور', 'error');
                }
            });
        },

        /**
         * Cancel order edit
         */
        cancelOrderEdit: function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (confirm('آیا از لغو تغییرات اطمینان دارید؟')) {
                location.reload();
            }
        },

        /**
         * Handle pagination
         */
        handlePagination: function(e) {
            const $btn = $(e.currentTarget);
            const page = $btn.data('page');

            if (page) {
                this.state.currentPage = page;
                this.performSearch(this.state.searchQuery);
            }
        },

        /**
         * Update pagination UI
         */
        updatePagination: function(totalPages, currentPage) {
            this.state.totalPages = totalPages;
            this.state.currentPage = currentPage;

            const $container = $('.pagination-container');
            if (totalPages <= 1) {
                $container.hide();
                return;
            }

            let html = '';
            
            // Previous button
            html += '<button class="pagination-btn" data-page="' + (currentPage - 1) + '" ' + 
                    (currentPage === 1 ? 'disabled' : '') + '>قبلی</button>';

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += '<button class="pagination-btn ' + (i === currentPage ? 'active' : '') + 
                            '" data-page="' + i + '">' + this.toPersianNumber(i) + '</button>';
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += '<span style="padding: 0 10px;">...</span>';
                }
            }

            // Next button
            html += '<button class="pagination-btn" data-page="' + (currentPage + 1) + '" ' + 
                    (currentPage === totalPages ? 'disabled' : '') + '>بعدی</button>';

            $container.html(html).show();
        },

        /**
         * Handle keyboard navigation
         */
        handleKeyboard: function(e) {
            // Escape key - close modal or collapse expanded row
            if (e.key === 'Escape') {
                if ($('.fullscreen-modal.visible').length) {
                    this.closeModal();
                } else if (this.state.expandedOrderId) {
                    const $row = $('tr.order-row[data-order-id="' + this.state.expandedOrderId + '"]');
                    $row.trigger('click');
                }
            }

            // Enter key in search - perform search
            if (e.key === 'Enter' && $(e.target).hasClass('global-search-input')) {
                this.performSearchNow();
            }
        },

        /**
         * Toggle theme
         */
        toggleTheme: function() {
            this.state.currentTheme = this.state.currentTheme === 'light' ? 'dark' : 'light';
            this.$dashboard.attr('data-theme', this.state.currentTheme);
            localStorage.setItem('tabesh-admin-theme', this.state.currentTheme);

            const icon = this.state.currentTheme === 'dark' ? '☀️' : '🌙';
            const text = this.state.currentTheme === 'dark' ? 'حالت روشن' : 'حالت تاریک';
            this.$themeToggle.html(icon + ' <span>' + text + '</span>');
        },

        /**
         * Load saved theme
         */
        loadTheme: function() {
            const savedTheme = localStorage.getItem('tabesh-admin-theme') || 'light';
            this.state.currentTheme = savedTheme;
            this.$dashboard.attr('data-theme', savedTheme);

            const icon = savedTheme === 'dark' ? '☀️' : '🌙';
            const text = savedTheme === 'dark' ? 'حالت روشن' : 'حالت تاریک';
            this.$themeToggle.html(icon + ' <span>' + text + '</span>');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.fullscreen-modal').removeClass('visible');
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            if (!$('.loading-overlay').length) {
                $('body').append(
                    '<div class="loading-overlay">' +
                        '<div class="loading-content">' +
                            '<div class="loading-spinner"></div>' +
                            '<div class="loading-text">' + (message || 'در حال بارگذاری...') + '</div>' +
                        '</div>' +
                    '</div>'
                );
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
        showToast: function(message, type) {
            $('.toast-notification').remove();

            const $toast = $('<div class="toast-notification ' + (type || 'info') + '">' + message + '</div>');
            $('body').append($toast);

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
        if ($('.tabesh-admin-dashboard').length > 0) {
            try {
                AdminDashboard.init();
                if (typeof tabeshAdminData !== 'undefined' && tabeshAdminData.debug) {
                    console.log('Tabesh Admin Dashboard: Initialized');
                }
            } catch (error) {
                console.error('Tabesh Admin Dashboard: Init error:', error);
            }
        }
    });

})(jQuery);
