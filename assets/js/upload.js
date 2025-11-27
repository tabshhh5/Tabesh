/**
 * Tabesh Upload Manager JavaScript
 *
 * Handles file upload, live search, progress tracking, and UI interactions.
 * Modern ES6+ implementation with smooth animations.
 *
 * @package Tabesh
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Upload Manager Module
     */
    const TabeshUploadManager = {
        // State
        currentPage: 1,
        hasMore: true,
        isLoading: false,
        currentOrderId: null,
        searchTimeout: null,
        orders: [],

        // DOM Elements
        elements: {},

        /**
         * Initialize the upload manager
         */
        init: function() {
            // Check if we have the required data
            if (typeof tabeshUploadData === 'undefined') {
                console.error('TabeshUploadManager: tabeshUploadData is not defined');
                return;
            }

            this.cacheElements();
            this.bindEvents();
            this.initTheme();
            this.loadOrders();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements = {
                container: $('.tabesh-upload-manager'),
                themeToggle: $('#theme-toggle'),
                searchInput: $('#order-search-input'),
                searchClear: $('#search-clear-btn'),
                ordersContainer: $('#orders-container'),
                loadMoreBtn: $('#load-more-btn'),
                loadMoreContainer: $('#load-more-container'),
                emptyState: $('#empty-state'),
                noResultsState: $('#no-results-state'),
                orderCount: $('#order-count'),
                modal: $('#order-detail-modal'),
                modalBody: $('#modal-body'),
                toastContainer: $('#toast-container'),
                loadingOverlay: $('#loading-overlay')
            };
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Theme toggle
            this.elements.themeToggle.on('click', () => this.toggleTheme());

            // Search input with debounce
            this.elements.searchInput.on('input', function() {
                const query = $(this).val().trim();
                self.elements.searchClear.toggle(query.length > 0);
                
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(() => {
                    self.currentPage = 1;
                    self.orders = [];
                    self.loadOrders(query);
                }, 300);
            });

            // Clear search
            this.elements.searchClear.on('click', () => {
                this.elements.searchInput.val('').trigger('input');
            });

            // Load more
            this.elements.loadMoreBtn.on('click', () => {
                if (!this.isLoading && this.hasMore) {
                    this.currentPage++;
                    this.loadOrders(this.elements.searchInput.val().trim(), true);
                }
            });

            // Order card click
            $(document).on('click', '.order-card', function() {
                const orderId = $(this).data('order-id');
                self.openOrderDetail(orderId);
            });

            // Modal close
            $(document).on('click', '#modal-close-btn, #modal-back-btn, .modal-overlay', () => {
                this.closeModal();
            });

            // File input change
            $(document).on('change', '.file-input', function() {
                const file = this.files[0];
                const fileType = $(this).data('type');
                
                if (file) {
                    self.uploadFile(file, fileType);
                }
            });

            // Drag and drop
            $(document).on('dragover dragenter', '.upload-zone', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });

            $(document).on('dragleave drop', '.upload-zone', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            });

            $(document).on('drop', '.upload-zone', function(e) {
                e.preventDefault();
                const files = e.originalEvent.dataTransfer.files;
                const fileType = $(this).data('type');
                
                if (files.length > 0) {
                    self.uploadFile(files[0], fileType);
                }
            });

            // Add more button
            $(document).on('click', '.btn-add-more', function() {
                const fileType = $(this).attr('id').replace('-add-more-btn', '');
                $(`#${fileType}-file-input`).click();
            });

            // Download file
            $(document).on('click', '.download-btn', function(e) {
                e.stopPropagation();
                const fileId = $(this).data('file-id');
                self.downloadFile(fileId);
            });

            // Keyboard navigation
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.elements.modal.is(':visible')) {
                    this.closeModal();
                }
            });
        },

        /**
         * Initialize theme from localStorage
         */
        initTheme: function() {
            const savedTheme = localStorage.getItem('tabesh-upload-theme') || 'light';
            this.elements.container.attr('data-theme', savedTheme);
        },

        /**
         * Toggle between light and dark theme
         */
        toggleTheme: function() {
            const container = this.elements.container;
            const currentTheme = container.attr('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            container.attr('data-theme', newTheme);
            localStorage.setItem('tabesh-upload-theme', newTheme);
        },

        /**
         * Load orders from server
         */
        loadOrders: function(search = '', append = false) {
            if (this.isLoading) return;

            this.isLoading = true;
            
            if (!append) {
                this.showLoading();
            } else {
                this.elements.loadMoreBtn.prop('disabled', true).text(tabeshUploadData.strings.loading);
            }

            const params = {
                search: search,
                page: this.currentPage,
                per_page: 3
            };

            $.ajax({
                url: this.buildUrl('search-orders'),
                method: 'GET',
                data: params,
                headers: {
                    'X-WP-Nonce': tabeshUploadData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        if (append) {
                            this.orders = [...this.orders, ...response.orders];
                        } else {
                            this.orders = response.orders;
                        }
                        
                        this.hasMore = response.has_more;
                        this.renderOrders(append);
                        this.updateOrderCount(response.total);
                    } else {
                        this.showToast('error', tabeshUploadData.strings.uploadError, response.message);
                    }
                },
                error: (xhr) => {
                    console.error('Load orders error:', xhr);
                    this.showToast('error', tabeshUploadData.strings.networkError);
                },
                complete: () => {
                    this.isLoading = false;
                    this.elements.loadMoreBtn
                        .prop('disabled', false)
                        .html(`<span class="btn-icon">📄</span> ${tabeshUploadData.strings.loadMore}`);
                }
            });
        },

        /**
         * Render orders list
         */
        renderOrders: function(append = false) {
            const container = this.elements.ordersContainer;
            
            if (this.orders.length === 0) {
                container.empty();
                const searchQuery = this.elements.searchInput.val().trim();
                
                if (searchQuery) {
                    this.elements.noResultsState.show();
                    this.elements.emptyState.hide();
                } else {
                    this.elements.emptyState.show();
                    this.elements.noResultsState.hide();
                }
                
                this.elements.loadMoreContainer.hide();
                return;
            }

            this.elements.emptyState.hide();
            this.elements.noResultsState.hide();

            if (!append) {
                container.empty();
            }

            this.orders.slice(append ? this.orders.length - 3 : 0).forEach(order => {
                container.append(this.renderOrderCard(order));
            });

            // Show/hide load more
            this.elements.loadMoreContainer.toggle(this.hasMore);
        },

        /**
         * Render single order card
         */
        renderOrderCard: function(order) {
            const statusClass = order.upload_status.is_complete ? 'uploaded' : 'pending-upload';
            const statusLabel = order.upload_status.label;
            const progress = order.upload_status.progress;

            return `
                <div class="order-card" data-order-id="${order.id}">
                    <div class="order-card-header">
                        <div class="order-card-title">
                            <h3>📖 ${this.escapeHtml(order.book_title)}</h3>
                            <span class="order-number">#${this.escapeHtml(order.order_number)}</span>
                        </div>
                        <span class="order-status ${statusClass}">${statusLabel}</span>
                    </div>
                    
                    <div class="order-info-grid">
                        <div class="order-info-item">
                            <span class="info-icon">📄</span>
                            <span class="info-value">${order.page_count}</span>
                            <span class="info-label">${tabeshUploadData.strings.text}</span>
                        </div>
                        <div class="order-info-item">
                            <span class="info-icon">📚</span>
                            <span class="info-value">${order.quantity}</span>
                            <span class="info-label">تیراژ</span>
                        </div>
                        <div class="order-info-item">
                            <span class="info-icon">📐</span>
                            <span class="info-value">${this.escapeHtml(order.book_size)}</span>
                            <span class="info-label">قطع</span>
                        </div>
                    </div>
                    
                    <div class="upload-progress-bar">
                        <div class="progress-fill" style="width: ${progress}%"></div>
                    </div>
                    <div class="progress-label">
                        <span>وضعیت آپلود</span>
                        <span>${progress}%</span>
                    </div>
                </div>
            `;
        },

        /**
         * Update order count display
         */
        updateOrderCount: function(total) {
            this.elements.orderCount.text(`${total} سفارش`);
        },

        /**
         * Open order detail modal
         */
        openOrderDetail: function(orderId) {
            this.currentOrderId = orderId;
            this.showLoadingOverlay();

            // Find order in cached list
            const order = this.orders.find(o => o.id === orderId);
            if (order) {
                $('#breadcrumb-order-number').text(`#${order.order_number}`);
            }

            // Load order files
            $.ajax({
                url: this.buildUrl(`order-files/${orderId}`),
                method: 'GET',
                headers: {
                    'X-WP-Nonce': tabeshUploadData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderOrderDetail(order, response.files);
                        this.elements.modal.show();
                    } else {
                        this.showToast('error', 'خطا', response.message);
                    }
                },
                error: (xhr) => {
                    console.error('Load order files error:', xhr);
                    this.showToast('error', tabeshUploadData.strings.networkError);
                },
                complete: () => {
                    this.hideLoadingOverlay();
                }
            });
        },

        /**
         * Render order detail view
         */
        renderOrderDetail: function(order, files) {
            // Update order info section
            const orderInfoHtml = `
                <div class="order-info-header">
                    <div>
                        <h2>📖 ${this.escapeHtml(order.book_title)}</h2>
                        <span class="order-number">#${this.escapeHtml(order.order_number)}</span>
                    </div>
                </div>
                <div class="order-info-details">
                    <div class="detail-item">
                        <div class="label">تعداد صفحات</div>
                        <div class="value">${order.page_count}</div>
                    </div>
                    <div class="detail-item">
                        <div class="label">تیراژ</div>
                        <div class="value">${order.quantity} نسخه</div>
                    </div>
                    <div class="detail-item">
                        <div class="label">قطع کتاب</div>
                        <div class="value">${this.escapeHtml(order.book_size)}</div>
                    </div>
                </div>
            `;
            $('#order-info-section').html(orderInfoHtml);

            // Update file info displays
            $('#text-page-count').text(order.page_count);
            $('#text-book-size').text(order.book_size);
            $('#cover-book-size').text(order.book_size);

            // Update stepper status
            this.updateStepper(files);

            // Render file lists
            this.renderFileList('text', files.text);
            this.renderFileList('cover', files.cover);
            this.renderFileList('documents', files.documents);
        },

        /**
         * Update stepper status
         */
        updateStepper: function(files) {
            const types = ['text', 'cover', 'documents'];
            let lastCompleted = -1;

            types.forEach((type, index) => {
                const step = $(`.stepper-step[data-type="${type}"]`);
                const connector = step.next('.stepper-connector');
                const hasFiles = files[type] && files[type].length > 0;

                step.removeClass('active completed');
                connector.removeClass('completed');

                if (hasFiles) {
                    step.addClass('completed');
                    connector.addClass('completed');
                    lastCompleted = index;
                }
            });

            // Mark next step as active
            if (lastCompleted < types.length - 1) {
                $(`.stepper-step[data-type="${types[lastCompleted + 1]}"]`).addClass('active');
            }
        },

        /**
         * Render file list for a category
         */
        renderFileList: function(type, files) {
            const container = $(`#${type}-files-list`);
            const addMoreBtn = $(`#${type}-add-more-btn`);
            
            container.empty();

            if (!files || files.length === 0) {
                addMoreBtn.hide();
                return;
            }

            files.forEach(file => {
                container.append(this.renderFileItem(file));
            });

            addMoreBtn.show();
        },

        /**
         * Render single file item
         */
        renderFileItem: function(file) {
            const fileIcon = this.getFileIcon(file.type);
            
            return `
                <div class="file-item" data-file-id="${file.id}">
                    <div class="file-item-icon">${fileIcon}</div>
                    <div class="file-item-info">
                        <div class="file-item-name">${this.escapeHtml(file.filename)}</div>
                        <div class="file-item-meta">
                            <span>${file.size_formatted}</span>
                            <span>${file.upload_date_formatted}</span>
                            ${file.version > 1 ? `<span class="file-version-badge">v${file.version}</span>` : ''}
                        </div>
                    </div>
                    <div class="file-item-actions">
                        <button class="file-action-btn download-btn" data-file-id="${file.id}" title="دانلود">
                            ⬇️
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Get file icon based on type
         */
        getFileIcon: function(type) {
            const icons = {
                pdf: '📄',
                jpg: '🖼️',
                jpeg: '🖼️',
                png: '🖼️',
                psd: '🎨',
                doc: '📝',
                docx: '📝'
            };
            return icons[type] || '📎';
        },

        /**
         * Upload file
         */
        uploadFile: function(file, fileType) {
            // Validate file size
            const maxSize = tabeshUploadData.maxSizes[fileType] || 10485760;
            if (file.size > maxSize) {
                this.showToast('error', 'خطا', `حجم فایل بیش از حد مجاز است. حداکثر: ${this.formatFileSize(maxSize)}`);
                return;
            }

            // Validate file type
            const allowedTypes = tabeshUploadData.allowedTypes[fileType] || [];
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!allowedTypes.includes(fileExt)) {
                this.showToast('error', 'خطا', `فرمت فایل مجاز نیست. فرمت‌های مجاز: ${allowedTypes.join(', ')}`);
                return;
            }

            // Show progress
            const progressContainer = $(`#${fileType}-upload-progress`);
            const progressFill = $(`#${fileType}-progress-fill`);
            const progressPercent = $(`#${fileType}-progress-percent`);
            const progressFilename = $(`#${fileType}-progress-filename`);
            const progressEta = $(`#${fileType}-progress-eta`);

            progressContainer.show();
            progressFilename.text(file.name);
            progressFill.css('width', '0%');
            progressPercent.text('0%');

            const formData = new FormData();
            formData.append('file', file);
            formData.append('order_id', this.currentOrderId);
            formData.append('file_type', fileType);

            const startTime = Date.now();

            $.ajax({
                url: this.buildUrl('upload-file'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': tabeshUploadData.nonce
                },
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressFill.css('width', `${percent}%`);
                            progressPercent.text(`${percent}%`);

                            // Calculate ETA
                            const elapsed = (Date.now() - startTime) / 1000;
                            if (percent > 0) {
                                const remaining = (elapsed / percent) * (100 - percent);
                                progressEta.text(`زمان باقیمانده: ${Math.ceil(remaining)} ثانیه`);
                            }
                        }
                    }, false);

                    return xhr;
                },
                success: (response) => {
                    progressContainer.hide();

                    if (response.success) {
                        this.showToast('success', 'موفق', tabeshUploadData.strings.uploadSuccess);
                        
                        // Refresh file list
                        this.refreshOrderFiles();
                        
                        // Update orders list
                        this.refreshOrdersList();
                    } else {
                        this.showToast('error', 'خطا', response.message);
                    }
                },
                error: (xhr) => {
                    progressContainer.hide();
                    console.error('Upload error:', xhr);
                    
                    let errorMessage = tabeshUploadData.strings.uploadError;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    this.showToast('error', 'خطا', errorMessage);
                }
            });
        },

        /**
         * Download file
         */
        downloadFile: function(fileId) {
            // Generate download token and redirect
            $.ajax({
                url: this.buildUrl(`generate-download-token/${fileId}`),
                method: 'POST',
                headers: {
                    'X-WP-Nonce': tabeshUploadData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Open download URL in new tab
                        window.open(response.download_url, '_blank');
                    } else {
                        this.showToast('error', 'خطا', response.message);
                    }
                },
                error: (xhr) => {
                    console.error('Download error:', xhr);
                    this.showToast('error', tabeshUploadData.strings.networkError);
                }
            });
        },

        /**
         * Refresh order files after upload
         */
        refreshOrderFiles: function() {
            if (!this.currentOrderId) return;

            $.ajax({
                url: this.buildUrl(`order-files/${this.currentOrderId}`),
                method: 'GET',
                headers: {
                    'X-WP-Nonce': tabeshUploadData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStepper(response.files);
                        this.renderFileList('text', response.files.text);
                        this.renderFileList('cover', response.files.cover);
                        this.renderFileList('documents', response.files.documents);
                    }
                }
            });
        },

        /**
         * Refresh orders list
         */
        refreshOrdersList: function() {
            this.currentPage = 1;
            this.loadOrders(this.elements.searchInput.val().trim());
        },

        /**
         * Close modal
         */
        closeModal: function() {
            this.elements.modal.hide();
            this.currentOrderId = null;
        },

        /**
         * Show loading state in orders container
         */
        showLoading: function() {
            this.elements.ordersContainer.html(`
                <div class="loading-state">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                    <p>${tabeshUploadData.strings.loading}</p>
                </div>
            `);
        },

        /**
         * Show loading overlay
         */
        showLoadingOverlay: function() {
            this.elements.loadingOverlay.show();
        },

        /**
         * Hide loading overlay
         */
        hideLoadingOverlay: function() {
            this.elements.loadingOverlay.hide();
        },

        /**
         * Show toast notification
         */
        showToast: function(type, title, message = '') {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️'
            };

            const toast = $(`
                <div class="toast toast-${type}">
                    <span class="toast-icon">${icons[type]}</span>
                    <div class="toast-content">
                        <div class="toast-title">${this.escapeHtml(title)}</div>
                        ${message ? `<div class="toast-message">${this.escapeHtml(message)}</div>` : ''}
                    </div>
                    <button class="toast-close">✕</button>
                </div>
            `);

            this.elements.toastContainer.append(toast);

            // Close button
            toast.find('.toast-close').on('click', () => {
                toast.remove();
            });

            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.fadeOut(300, () => toast.remove());
            }, 5000);
        },

        /**
         * Build API URL
         */
        buildUrl: function(endpoint) {
            const base = tabeshUploadData.restUrl.replace(/\/+$/, '');
            return `${base}/${endpoint}`;
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize if we're on the upload manager page
        if ($('.tabesh-upload-manager').length > 0) {
            TabeshUploadManager.init();
        }
    });

    // Expose to global scope
    window.TabeshUploadManager = TabeshUploadManager;

})(jQuery);
