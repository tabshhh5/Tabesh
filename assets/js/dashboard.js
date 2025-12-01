/**
 * Tabesh User Dashboard JavaScript
 *
 * Handles tab switching, form navigation, file uploads, and order management
 * in a SPA-like interface without page reloads.
 *
 * @package Tabesh
 * @since 1.2.0
 */

(function($) {
    'use strict';

    // Dashboard state
    const DashboardState = {
        currentTab: null,
        currentOrderId: null,
        formStep: 1,
        totalSteps: 12,
        paperTypes: {},
        isLoading: false
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        initDashboard();
    });

    /**
     * Initialize Dashboard
     */
    function initDashboard() {
        const $dashboard = $('.tabesh-dashboard');
        if (!$dashboard.length) return;

        // Get initial state from data attributes
        DashboardState.currentTab = $dashboard.data('default-tab') || 'order-form';

        // Initialize components
        initThemeToggle();
        initTabNavigation();
        initOrderForm();
        initUploadManager();
        initUserOrders();
        initModals();
        initHelpTips();
        initUrlHistory();

        // Load paper types from global data
        if (typeof tabeshData !== 'undefined' && tabeshData.settings) {
            DashboardState.paperTypes = tabeshData.settings.paperTypes || {};
        }
    }

    /**
     * Theme Toggle
     */
    function initThemeToggle() {
        const $toggle = $('#theme-toggle');
        const $dashboard = $('.tabesh-dashboard');

        // Load saved theme
        const savedTheme = localStorage.getItem('tabesh-dashboard-theme') || 'light';
        $dashboard.attr('data-theme', savedTheme);

        $toggle.on('click', function() {
            const currentTheme = $dashboard.attr('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            $dashboard.attr('data-theme', newTheme);
            localStorage.setItem('tabesh-dashboard-theme', newTheme);
        });
    }

    /**
     * Tab Navigation
     */
    function initTabNavigation() {
        const $tabs = $('.tab-button');
        const $contents = $('.tab-content');

        $tabs.on('click', function() {
            const tabId = $(this).data('tab');
            switchTab(tabId);
        });

        // Handle go-to buttons
        $(document).on('click', '.go-to-order-form', function() {
            switchTab('order-form');
        });
    }

    /**
     * Switch Tab
     */
    function switchTab(tabId) {
        const $tabs = $('.tab-button');
        const $contents = $('.tab-content');

        // Update tab buttons
        $tabs.removeClass('active').attr('aria-selected', 'false');
        $tabs.filter(`[data-tab="${tabId}"]`).addClass('active').attr('aria-selected', 'true');

        // Update tab contents
        $contents.removeClass('active');
        $(`#tab-content-${tabId}`).addClass('active');

        // Update state
        DashboardState.currentTab = tabId;

        // Update URL
        updateUrl({ tab: tabId });

        // Trigger tab-specific initialization
        if (tabId === 'upload-manager') {
            loadUploadOrders();
        }
    }

    /**
     * URL History Management
     */
    function initUrlHistory() {
        // Handle browser back/forward
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.tab) {
                switchTab(e.state.tab);
            }
        });

        // Check URL on load
        const params = new URLSearchParams(window.location.search);
        const tabFromUrl = params.get('tab');
        if (tabFromUrl && ['order-form', 'upload-manager', 'user-orders'].includes(tabFromUrl)) {
            switchTab(tabFromUrl);
        }
    }

    /**
     * Update URL without reload
     */
    function updateUrl(params) {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        history.pushState(params, '', url);
    }

    /**
     * Order Form Initialization
     */
    function initOrderForm() {
        const $form = $('#dashboard-order-form');
        if (!$form.length) return;

        // Step navigation
        $('#dashboard-prev-btn').on('click', function() {
            navigateStep(-1);
        });

        $('#dashboard-next-btn').on('click', function() {
            navigateStep(1);
        });

        // Calculate button
        $('#dashboard-calculate-btn').on('click', calculatePrice);

        // Edit button (go back to form)
        $('#dashboard-edit-order-btn').on('click', function() {
            $('#dashboard-price-result').slideUp();
            $form.slideDown();
            navigateToStep(1);
        });

        // Submit order button
        $('#dashboard-submit-order-btn').on('click', submitOrder);

        // Paper type change handler
        $('#dashboard_paper_type').on('change', function() {
            updatePaperWeights($(this).val());
        });

        // License type change handler
        $('#dashboard_license_type').on('change', function() {
            const val = $(this).val();
            if (val === 'دارم') {
                $('#dashboard_license_upload').slideDown();
            } else {
                $('#dashboard_license_upload').slideUp();
            }
        });
    }

    /**
     * Navigate Form Step
     */
    function navigateStep(direction) {
        const newStep = DashboardState.formStep + direction;
        
        if (newStep < 1 || newStep > DashboardState.totalSteps) return;

        // Validate current step before proceeding
        if (direction > 0 && !validateStep(DashboardState.formStep)) {
            return;
        }

        navigateToStep(newStep);
    }

    /**
     * Navigate to Specific Step
     */
    function navigateToStep(step) {
        DashboardState.formStep = step;

        // Update steps visibility
        $('.form-step').removeClass('active');
        $(`.form-step[data-step="${step}"]`).addClass('active');

        // Update navigation buttons
        $('#dashboard-prev-btn').toggle(step > 1);
        $('#dashboard-next-btn').toggle(step < DashboardState.totalSteps);
        $('#dashboard-calculate-btn').toggle(step === DashboardState.totalSteps);

        // Update progress
        const progress = (step / DashboardState.totalSteps) * 100;
        $('#form-progress-fill').css('width', `${progress}%`);
        $('#form-progress-text').text(`مرحله ${step} از ${DashboardState.totalSteps}`);
    }

    /**
     * Validate Current Step
     */
    function validateStep(step) {
        const $step = $(`.form-step[data-step="${step}"]`);
        const $required = $step.find('[required]');
        let isValid = true;

        $required.each(function() {
            const $field = $(this);
            if (!$field.val()) {
                isValid = false;
                $field.addClass('error');
                showToast('لطفاً فیلدهای الزامی را پر کنید', 'error');
            } else {
                $field.removeClass('error');
            }
        });

        return isValid;
    }

    /**
     * Update Paper Weights Dropdown
     */
    function updatePaperWeights(paperType) {
        const $weightSelect = $('#dashboard_paper_weight');
        $weightSelect.empty();

        if (!paperType || !DashboardState.paperTypes[paperType]) {
            $weightSelect.append('<option value="">ابتدا نوع کاغذ را انتخاب کنید</option>');
            return;
        }

        const weights = DashboardState.paperTypes[paperType];
        $weightSelect.append('<option value="">انتخاب کنید...</option>');
        
        if (Array.isArray(weights)) {
            weights.forEach(function(weight) {
                $weightSelect.append(`<option value="${weight}">${weight} گرم</option>`);
            });
        }
    }

    /**
     * Calculate Price
     */
    function calculatePrice() {
        if (!validateStep(DashboardState.formStep)) return;

        showLoading();

        const formData = collectFormData();

        $.ajax({
            url: tabeshDashboardData.restUrl + '/calculate-price',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    displayPriceResult(response.data);
                } else {
                    showToast(response.message || 'خطا در محاسبه قیمت', 'error');
                }
            },
            error: function(xhr) {
                hideLoading();
                const message = xhr.responseJSON?.message || 'خطا در برقراری ارتباط با سرور';
                showToast(message, 'error');
            }
        });
    }

    /**
     * Collect Form Data
     */
    function collectFormData() {
        const $form = $('#dashboard-order-form');
        const extras = [];
        
        $form.find('input[name="extras[]"]:checked').each(function() {
            extras.push($(this).val());
        });

        return {
            book_title: $('#dashboard_book_title').val(),
            book_size: $('#dashboard_book_size').val(),
            paper_type: $('#dashboard_paper_type').val(),
            paper_weight: $('#dashboard_paper_weight').val(),
            print_type: $('#dashboard_print_type').val(),
            page_count_bw: parseInt($('#dashboard_page_count_bw').val()) || 0,
            page_count_color: parseInt($('#dashboard_page_count_color').val()) || 0,
            quantity: parseInt($('#dashboard_quantity').val()) || 10,
            binding_type: $('#dashboard_binding_type').val(),
            license_type: $('#dashboard_license_type').val(),
            cover_paper_weight: $('#dashboard_cover_paper_weight').val(),
            lamination_type: $('#dashboard_lamination_type').val(),
            extras: extras,
            notes: $('#dashboard_notes').val()
        };
    }

    /**
     * Display Price Result
     */
    function displayPriceResult(data) {
        $('#dashboard-price-per-book').text(formatNumber(data.price_per_book) + ' تومان');
        $('#dashboard-price-quantity').text(data.quantity + ' جلد');
        $('#dashboard-price-subtotal').text(formatNumber(data.subtotal) + ' تومان');

        // Extras
        if (data.breakdown && data.breakdown.options_cost > 0) {
            $('#dashboard-extras-row').show();
            $('#dashboard-price-extras').text(formatNumber(data.breakdown.options_cost) + ' تومان');
        } else {
            $('#dashboard-extras-row').hide();
        }

        // Discount
        if (data.discount_amount > 0) {
            $('#dashboard-discount-row').show();
            $('#dashboard-price-discount').text(formatNumber(data.discount_amount) + '- تومان');
        } else {
            $('#dashboard-discount-row').hide();
        }

        // Total
        $('#dashboard-price-total').text(formatNumber(data.total_price) + ' تومان');

        // Show result, hide form
        $('#dashboard-order-form').slideUp();
        $('#dashboard-price-result').slideDown();
    }

    /**
     * Submit Order
     */
    function submitOrder() {
        showLoading();

        const formData = collectFormData();

        $.ajax({
            url: tabeshDashboardData.restUrl + '/submit-order',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showToast('سفارش با موفقیت ثبت شد', 'success');
                    
                    // Reset form
                    $('#dashboard-order-form')[0].reset();
                    navigateToStep(1);
                    $('#dashboard-price-result').hide();
                    $('#dashboard-order-form').show();

                    // Switch to upload manager
                    setTimeout(function() {
                        switchTab('upload-manager');
                    }, 1500);
                } else {
                    showToast(response.message || 'خطا در ثبت سفارش', 'error');
                }
            },
            error: function(xhr) {
                hideLoading();
                const message = xhr.responseJSON?.message || 'خطا در برقراری ارتباط با سرور';
                showToast(message, 'error');
            }
        });
    }

    /**
     * Upload Manager Initialization
     */
    function initUploadManager() {
        // Search functionality
        let searchTimeout;
        $('#upload-search-input').on('input', function() {
            const query = $(this).val();
            $('#upload-search-clear').toggle(query.length > 0);
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                loadUploadOrders(query);
            }, 300);
        });

        $('#upload-search-clear').on('click', function() {
            $('#upload-search-input').val('');
            $(this).hide();
            loadUploadOrders();
        });

        // Back button in detail panel
        $('#upload-panel-back').on('click', function() {
            $('#upload-detail-panel').slideUp();
            $('.upload-orders-section').slideDown();
        });

        // File upload handlers
        initFileUploadHandlers();
    }

    /**
     * Load Upload Orders
     */
    function loadUploadOrders(search = '') {
        const $container = $('#upload-orders-container');
        
        $container.html('<div class="loading-state"><div class="loading-spinner"><div class="spinner"></div></div><p>در حال بارگذاری...</p></div>');

        $.ajax({
            url: tabeshDashboardData.restUrl + '/search-orders',
            method: 'GET',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce
            },
            data: {
                search: search,
                per_page: 5
            },
            success: function(response) {
                if (response.success && response.orders.length > 0) {
                    renderUploadOrders(response.orders);
                    $('#upload-order-count').text(`${response.total} سفارش`);
                    $('#upload-load-more').toggle(response.has_more);
                    $('#upload-empty-state').hide();
                } else {
                    $container.empty();
                    $('#upload-empty-state').show();
                    $('#upload-order-count').text('');
                    $('#upload-load-more').hide();
                }
            },
            error: function() {
                $container.html('<div class="error-state"><p>خطا در بارگذاری سفارشات</p></div>');
            }
        });
    }

    /**
     * Render Upload Orders
     */
    function renderUploadOrders(orders) {
        const $container = $('#upload-orders-container');
        $container.empty();

        orders.forEach(function(order) {
            const statusClass = getUploadStatusClass(order.upload_status);
            const $card = $(`
                <div class="order-card upload-order-card" data-order-id="${order.id}">
                    <div class="order-card-header">
                        <div class="order-title-row">
                            <h4 class="order-book-title">📖 ${escapeHtml(order.book_title)}</h4>
                            <span class="order-number">#${order.order_number}</span>
                        </div>
                        <span class="upload-status ${statusClass}">
                            ${order.upload_status.is_complete ? '✓ آپلود کامل' : '⏳ در انتظار آپلود'}
                        </span>
                    </div>
                    <div class="order-card-body">
                        <div class="order-quick-info">
                            <div class="info-item">
                                <span class="info-icon">📄</span>
                                <span class="info-text">${order.page_count} صفحه</span>
                            </div>
                            <div class="info-item">
                                <span class="info-icon">📚</span>
                                <span class="info-text">${order.quantity} نسخه</span>
                            </div>
                            <div class="info-item">
                                <span class="info-icon">📐</span>
                                <span class="info-text">${order.book_size}</span>
                            </div>
                        </div>
                        <div class="file-counts">
                            <span class="file-count ${order.file_counts.text > 0 ? 'has-file' : ''}">
                                📄 متن: ${order.file_counts.text}
                            </span>
                            <span class="file-count ${order.file_counts.cover > 0 ? 'has-file' : ''}">
                                🖼️ جلد: ${order.file_counts.cover}
                            </span>
                            <span class="file-count">
                                📑 مدارک: ${order.file_counts.documents}
                            </span>
                        </div>
                        <div class="order-card-footer">
                            <button class="btn btn-primary btn-upload-files" data-order-id="${order.id}">
                                <span class="btn-icon">📤</span>
                                مدیریت فایل‌ها
                            </button>
                        </div>
                    </div>
                </div>
            `);

            $container.append($card);
        });

        // Bind click handlers
        $('.btn-upload-files').on('click', function() {
            const orderId = $(this).data('order-id');
            openUploadPanel(orderId);
        });
    }

    /**
     * Get Upload Status Class
     */
    function getUploadStatusClass(status) {
        if (status.is_complete) return 'status-complete';
        if (status.has_files) return 'status-partial';
        return 'status-pending';
    }

    /**
     * Open Upload Panel
     */
    function openUploadPanel(orderId) {
        DashboardState.currentOrderId = orderId;
        
        // Show panel, hide list
        $('.upload-orders-section').slideUp();
        $('#upload-detail-panel').slideDown();

        // Load order details and files
        loadOrderFiles(orderId);
    }

    /**
     * Load Order Files
     */
    function loadOrderFiles(orderId) {
        $.ajax({
            url: tabeshDashboardData.restUrl + '/order-files/' + orderId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderOrderFiles(response.files);
                    updateUploadStepper(response.files);
                }
            },
            error: function() {
                showToast('خطا در بارگذاری فایل‌ها', 'error');
            }
        });
    }

    /**
     * Render Order Files
     */
    function renderOrderFiles(files) {
        ['text', 'cover', 'documents'].forEach(function(type) {
            const $list = $(`#${type}-files-list`);
            $list.empty();

            const typeFiles = files[type] || [];
            typeFiles.forEach(function(file) {
                const $fileItem = $(`
                    <div class="file-item" data-file-id="${file.id}">
                        <div class="file-info">
                            <span class="file-icon">${getFileIcon(file.type)}</span>
                            <div class="file-details">
                                <span class="file-name">${escapeHtml(file.filename)}</span>
                                <span class="file-meta">${file.size_formatted} - ${file.upload_date_formatted}</span>
                            </div>
                        </div>
                        <div class="file-status status-${file.status}">
                            ${getStatusLabel(file.status)}
                        </div>
                    </div>
                `);
                $list.append($fileItem);
            });
        });
    }

    /**
     * Update Upload Stepper
     */
    function updateUploadStepper(files) {
        ['text', 'cover', 'documents'].forEach(function(type) {
            const $step = $(`.upload-stepper .stepper-step[data-type="${type}"]`);
            const hasFiles = files[type] && files[type].length > 0;
            $step.toggleClass('completed', hasFiles);
        });
    }

    /**
     * Initialize File Upload Handlers
     */
    function initFileUploadHandlers() {
        // File input change
        $(document).on('change', '.file-input', function() {
            const file = this.files[0];
            const type = $(this).data('type');
            
            if (file) {
                uploadFile(file, type);
            }
        });

        // Drag and drop
        $(document).on('dragover', '.upload-zone', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        });

        $(document).on('dragleave', '.upload-zone', function() {
            $(this).removeClass('dragover');
        });

        $(document).on('drop', '.upload-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            
            const file = e.originalEvent.dataTransfer.files[0];
            const type = $(this).data('type');
            
            if (file) {
                uploadFile(file, type);
            }
        });
    }

    /**
     * Upload File
     */
    function uploadFile(file, type) {
        if (!DashboardState.currentOrderId) {
            showToast('لطفاً ابتدا یک سفارش انتخاب کنید', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('order_id', DashboardState.currentOrderId);
        formData.append('file_type', type);

        const $progress = $(`#${type}-progress`);
        const $fill = $(`#${type}-fill`);
        const $percent = $(`#${type}-percent`);
        const $filename = $(`#${type}-filename`);

        $filename.text(file.name);
        $progress.show();

        $.ajax({
            url: tabeshDashboardData.restUrl + '/upload-file',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce
            },
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $fill.css('width', percent + '%');
                        $percent.text(percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                $progress.hide();
                if (response.success) {
                    showToast('فایل با موفقیت آپلود شد', 'success');
                    loadOrderFiles(DashboardState.currentOrderId);
                } else {
                    showToast(response.message || 'خطا در آپلود فایل', 'error');
                }
            },
            error: function(xhr) {
                $progress.hide();
                const message = xhr.responseJSON?.message || 'خطا در آپلود فایل';
                showToast(message, 'error');
            }
        });
    }

    /**
     * User Orders Initialization
     */
    function initUserOrders() {
        // Search functionality
        let searchTimeout;
        $('#orders-search-input').on('input', function() {
            const query = $(this).val();
            $('#orders-search-clear').toggle(query.length > 0);
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchUserOrders(query);
            }, 300);
        });

        $('#orders-search-clear').on('click', function() {
            $('#orders-search-input').val('');
            $(this).hide();
            $('#orders-search-results').hide();
        });

        // Click on order card to show details (scoped to orders container)
        // Using event delegation on #user-orders-content to reduce scope
        $('#user-orders-content').on('click', '.order-card:not(.upload-order-card)', function(e) {
            // If clicking on buttons, do not trigger card click
            if (!$(e.target).closest('.btn, button, a').length) {
                const orderId = $(this).data('order-id');
                if (orderId) {
                    showOrderDetails(orderId);
                }
            }
        });

        // Details button (scoped to user orders content)
        $('#user-orders-content').on('click', '.btn-details', function(e) {
            e.stopPropagation();
            const orderId = $(this).data('order-id');
            showOrderDetails(orderId);
        });

        // Support button (scoped to user orders content)
        $('#user-orders-content').on('click', '.btn-support', function(e) {
            e.stopPropagation();
            const orderNumber = $(this).data('order-number');
            const bookTitle = $(this).data('book-title');
            showSupportModal(orderNumber, bookTitle);
        });
    }

    /**
     * Search User Orders
     */
    function searchUserOrders(query) {
        if (query.length < 2) {
            $('#orders-search-results').hide();
            return;
        }

        $.ajax({
            url: tabeshDashboardData.restUrl + '/user-orders/search',
            method: 'GET',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce
            },
            data: { q: query },
            success: function(response) {
                if (response.orders && response.orders.length > 0) {
                    renderSearchResults(response.orders);
                } else {
                    $('#orders-search-results .search-results-content').html('<p class="no-results">نتیجه‌ای یافت نشد</p>');
                    $('#orders-search-results').show();
                }
            }
        });
    }

    /**
     * Render Search Results
     */
    function renderSearchResults(orders) {
        const $content = $('#orders-search-results .search-results-content');
        $content.empty();

        orders.forEach(function(order) {
            const $item = $(`
                <div class="search-result-item" data-order-id="${order.id}">
                    <div class="result-title">${escapeHtml(order.book_title)}</div>
                    <div class="result-meta">#${order.order_number} - ${order.status_label}</div>
                </div>
            `);
            $content.append($item);
        });

        // Bind click
        $content.find('.search-result-item').on('click', function() {
            const orderId = $(this).data('order-id');
            showOrderDetails(orderId);
            $('#orders-search-results').hide();
        });

        $('#orders-search-results').show();
    }

    /**
     * Show Order Details
     */
    function showOrderDetails(orderId) {
        showLoading();

        $.ajax({
            url: tabeshDashboardData.restUrl + '/user-orders/' + orderId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': tabeshDashboardData.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.order) {
                    renderOrderDetailsModal(response.order);
                    $('#order-details-modal').fadeIn(200);
                } else {
                    showToast('سفارش یافت نشد', 'error');
                }
            },
            error: function() {
                hideLoading();
                // Fallback: If API is not available, extract data from DOM
                const $card = $(`.order-card[data-order-id="${orderId}"]`);
                if ($card.length) {
                    const orderData = extractOrderDataFromCard($card);
                    renderOrderDetailsModal(orderData);
                    $('#order-details-modal').fadeIn(200);
                } else {
                    showToast('خطا در بارگذاری جزئیات سفارش', 'error');
                }
            }
        });
    }

    /**
     * Extract Order Data from Card (Fallback)
     * Note: This fallback extracts basic data from DOM when API fails
     */
    function extractOrderDataFromCard($card) {
        // Extract order number - remove # prefix
        const orderNumberText = $card.find('.order-number').text();
        const orderNumber = orderNumberText.replace(/^#/, '').trim();
        
        // Extract book title - remove emoji and clean up
        const bookTitleText = $card.find('.order-book-title').text();
        const bookTitle = bookTitleText.replace(/^[\s\uD83D\uDCD6\u{1F4D6}]*/, '').trim();
        
        // Extract status label
        const statusLabel = $card.find('.order-status').text().trim();
        
        // Extract quick info items by examining each info-item
        const quickInfo = {
            page_count: null,
            quantity: null,
            total_price: null,
            book_size: null
        };
        
        $card.find('.info-item').each(function(index) {
            const text = $(this).find('.info-text').text().trim();
            // Use index-based extraction as fallback (order: pages, copies, size, price)
            if (index === 0 || text.includes('صفحه')) {
                quickInfo.page_count = text;
            } else if (index === 1 || text.includes('نسخه')) {
                quickInfo.quantity = text;
            } else if (index === 3 || text.includes('تومان')) {
                quickInfo.total_price = text;
            } else if (index === 2) {
                quickInfo.book_size = text;
            }
        });
        
        // Extract order date
        const orderDateText = $card.find('.order-date').text();
        const orderDate = orderDateText.replace(/^[\s\uD83D\uDCC5\u{1F4C5}]*/, '').trim();
        
        return {
            order_number: orderNumber,
            book_title: bookTitle,
            status_label: statusLabel,
            page_count_total: quickInfo.page_count || 'نامشخص',
            page_count_bw: null,
            page_count_color: null,
            quantity: quickInfo.quantity || 'نامشخص',
            book_size: quickInfo.book_size || 'نامشخص',
            total_price: quickInfo.total_price || 'نامشخص',
            created_at: orderDate,
            paper_type: 'نامشخص',
            paper_weight: null,
            print_type: 'نامشخص',
            binding_type: 'نامشخص',
            lamination_type: 'نامشخص',
            extras: [],
            is_fallback: true
        };
    }

    /**
     * Render Order Details Modal
     */
    function renderOrderDetailsModal(order) {
        const $body = $('#order-modal-body');
        $('#order-modal-title').text(`جزئیات سفارش #${order.order_number}`);

        const extrasHtml = order.extras && order.extras.length > 0 
            ? order.extras.map(e => `<span class="extra-tag">${escapeHtml(e)}</span>`).join(' ')
            : 'ندارد';

        // Handle fallback data (less detailed)
        const isFallback = order.is_fallback;
        
        // Format total price (handle both number and string formats)
        let totalPriceDisplay = order.total_price;
        if (typeof totalPriceDisplay === 'number') {
            totalPriceDisplay = formatNumber(totalPriceDisplay) + ' تومان';
        } else if (typeof totalPriceDisplay === 'string' && !totalPriceDisplay.includes('تومان')) {
            totalPriceDisplay = totalPriceDisplay + ' تومان';
        }

        // Page count display
        let pageCountDisplay = order.page_count_total;
        if (!isFallback && order.page_count_bw !== undefined && order.page_count_color !== undefined) {
            pageCountDisplay = `${order.page_count_total} صفحه (${order.page_count_bw} سیاه‌وسفید + ${order.page_count_color} رنگی)`;
        }

        // Quantity display
        let quantityDisplay = order.quantity;
        if (typeof quantityDisplay === 'number' || (typeof quantityDisplay === 'string' && !quantityDisplay.includes('نسخه'))) {
            quantityDisplay = quantityDisplay + ' نسخه';
        }

        // Paper type display
        let paperDisplay = order.paper_type || 'نامشخص';
        if (order.paper_weight) {
            paperDisplay = `${order.paper_type} - ${order.paper_weight} گرم`;
        }

        $body.html(`
            <div class="order-details-content">
                <div class="detail-section">
                    <h4>اطلاعات کتاب</h4>
                    <div class="detail-row">
                        <span class="detail-label">عنوان:</span>
                        <span class="detail-value">${escapeHtml(order.book_title)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">قطع:</span>
                        <span class="detail-value">${order.book_size || 'نامشخص'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">نوع کاغذ:</span>
                        <span class="detail-value">${paperDisplay}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">نوع چاپ:</span>
                        <span class="detail-value">${order.print_type || 'نامشخص'}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>مشخصات سفارش</h4>
                    <div class="detail-row">
                        <span class="detail-label">تعداد صفحات:</span>
                        <span class="detail-value">${pageCountDisplay}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">تیراژ:</span>
                        <span class="detail-value">${quantityDisplay}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">صحافی:</span>
                        <span class="detail-value">${order.binding_type || 'نامشخص'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">سلفون:</span>
                        <span class="detail-value">${order.lamination_type || 'نامشخص'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">خدمات اضافی:</span>
                        <span class="detail-value">${extrasHtml}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>اطلاعات مالی</h4>
                    <div class="detail-row total">
                        <span class="detail-label">مبلغ کل:</span>
                        <span class="detail-value">${totalPriceDisplay}</span>
                    </div>
                </div>
            </div>
        `);
    }

    /**
     * Show Support Modal
     */
    function showSupportModal(orderNumber, bookTitle) {
        $('#support-order-info').html(`
            <div class="order-info-card">
                <p><strong>شماره سفارش:</strong> #${orderNumber}</p>
                <p><strong>عنوان کتاب:</strong> ${escapeHtml(bookTitle)}</p>
            </div>
        `);
        $('#support-modal').show();
    }

    /**
     * Initialize Modals
     */
    function initModals() {
        // Close modal on overlay click
        $(document).on('click', '.modal-overlay', function() {
            $(this).closest('.order-modal, .support-modal, .help-modal').hide();
        });

        // Close buttons
        $('#order-modal-close').on('click', function() {
            $('#order-details-modal').hide();
        });

        $('#support-modal-close').on('click', function() {
            $('#support-modal').hide();
        });

        $('#help-modal-close').on('click', function() {
            $('#help-modal').hide();
        });

        // Help toggle
        $('#help-toggle').on('click', function() {
            $('#help-modal').show();
        });

        // Close on escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.order-modal, .support-modal, .help-modal').hide();
            }
        });
    }

    /**
     * Initialize Help Tips
     */
    function initHelpTips() {
        $(document).on('click', '.help-tip', function() {
            const tip = $(this).data('tip');
            if (tip) {
                showToast(tip, 'info');
            }
        });
    }

    /**
     * Show Loading Overlay
     */
    function showLoading() {
        DashboardState.isLoading = true;
        $('#loading-overlay').show();
    }

    /**
     * Hide Loading Overlay
     */
    function hideLoading() {
        DashboardState.isLoading = false;
        $('#loading-overlay').hide();
    }

    /**
     * Show Toast Notification
     */
    function showToast(message, type = 'info') {
        const $container = $('#toast-container');
        const $toast = $(`
            <div class="toast ${type}">
                <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
                <span class="toast-message">${escapeHtml(message)}</span>
            </div>
        `);

        $container.append($toast);

        setTimeout(function() {
            $toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Format Number with Persian Thousands Separator
     */
    function formatNumber(num) {
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get File Icon
     */
    function getFileIcon(type) {
        const icons = {
            'pdf': '📄',
            'jpg': '🖼️',
            'jpeg': '🖼️',
            'png': '🖼️',
            'psd': '🎨',
            'doc': '📝',
            'docx': '📝'
        };
        return icons[type.toLowerCase()] || '📎';
    }

    /**
     * Get Status Label
     */
    function getStatusLabel(status) {
        const labels = {
            'pending': '⏳ در انتظار بررسی',
            'approved': '✓ تایید شده',
            'rejected': '✕ نیاز به اصلاح'
        };
        return labels[status] || status;
    }

})(jQuery);
