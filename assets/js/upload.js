/**
 * Tabesh Upload Manager JavaScript
 * Modern file upload and management interface
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Check if tabeshUploadData is available
    if (typeof tabeshUploadData === 'undefined') {
        console.error('Tabesh Upload Manager: Configuration data not available');
        return;
    }

    /**
     * Upload Manager Module
     */
    var UploadManager = {
        // Configuration
        config: {
            restUrl: tabeshUploadData.restUrl,
            nonce: tabeshUploadData.nonce,
            maxFileSizes: tabeshUploadData.maxFileSizes,
            allowedTypes: tabeshUploadData.allowedTypes,
            strings: tabeshUploadData.strings
        },

        // State
        state: {
            currentOrderId: null,
            currentFileType: null,
            searchTimeout: null,
            searchPage: 1,
            isSearching: false
        },

        /**
         * Initialize the upload manager
         */
        init: function() {
            this.bindEvents();
            this.initTheme();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Theme toggle
            $('#theme-toggle').on('click', function() {
                self.toggleTheme();
            });

            // Search functionality
            $('#upload-search-input').on('input', function() {
                self.handleSearchInput($(this).val());
            });

            $('#search-clear').on('click', function() {
                self.clearSearch();
            });

            $('#search-load-more').on('click', function() {
                self.loadMoreResults();
            });

            // Order card click - open modal
            $(document).on('click', '.order-card, .btn-view-order', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var orderId = $(this).data('order-id') || $(this).closest('.order-card').data('order-id');
                self.openOrderModal(orderId);
            });

            // Modal controls
            $('#modal-close, #modal-back, .modal-overlay').on('click', function() {
                self.closeOrderModal();
            });

            $('.modal-container').on('click', function(e) {
                e.stopPropagation();
            });

            // Upload button click
            $(document).on('click', '.btn-upload', function() {
                var fileType = $(this).data('type');
                var orderId = $(this).data('order');
                self.openUploadModal(orderId, fileType);
            });

            // Upload modal controls
            $('#upload-modal-close').on('click', function() {
                self.closeUploadModal();
            });

            $('#upload-modal .modal-overlay').on('click', function() {
                self.closeUploadModal();
            });

            // Drag and drop
            var $dropzone = $('#upload-dropzone');
            
            $dropzone.on('click', function() {
                $('#upload-file-input').click();
            });

            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $dropzone.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $dropzone.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileSelect(files[0]);
                }
            });

            $('#upload-file-input').on('change', function() {
                if (this.files.length > 0) {
                    self.handleFileSelect(this.files[0]);
                }
            });

            // Download button click
            $(document).on('click', '.btn-download', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var fileId = $(this).data('file-id');
                self.downloadFile(fileId);
            });

            // Search result click
            $(document).on('click', '.search-result-item', function() {
                var orderId = $(this).data('order-id');
                self.hideSearchResults();
                self.openOrderModal(orderId);
            });

            // Click outside search results to close
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    self.hideSearchResults();
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Escape key closes modals
                if (e.key === 'Escape') {
                    if ($('#upload-modal').is(':visible')) {
                        self.closeUploadModal();
                    } else if ($('#order-modal').is(':visible')) {
                        self.closeOrderModal();
                    }
                }
            });

            // Breadcrumb link
            $(document).on('click', '.breadcrumb-link', function(e) {
                e.preventDefault();
                self.closeOrderModal();
            });
        },

        /**
         * Initialize theme from localStorage
         */
        initTheme: function() {
            var savedTheme = localStorage.getItem('tabesh_upload_theme') || 'light';
            $('.tabesh-upload-manager').attr('data-theme', savedTheme);
        },

        /**
         * Toggle between light and dark themes
         */
        toggleTheme: function() {
            var $container = $('.tabesh-upload-manager');
            var currentTheme = $container.attr('data-theme');
            var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            $container.attr('data-theme', newTheme);
            localStorage.setItem('tabesh_upload_theme', newTheme);
        },

        /**
         * Handle search input
         */
        handleSearchInput: function(query) {
            var self = this;
            
            // Clear previous timeout
            if (this.state.searchTimeout) {
                clearTimeout(this.state.searchTimeout);
            }

            // Show/hide clear button
            $('#search-clear').toggle(query.length > 0);

            if (query.length < 2) {
                this.hideSearchResults();
                return;
            }

            // Reset pagination
            this.state.searchPage = 1;

            // Debounce search
            this.state.searchTimeout = setTimeout(function() {
                self.performSearch(query);
            }, 300);
        },

        /**
         * Perform search API call
         */
        performSearch: function(query) {
            var self = this;

            if (this.state.isSearching) return;
            this.state.isSearching = true;

            $('#search-results').show();
            $('.results-loading').show();
            $('.results-count').text('');

            $.ajax({
                url: this.config.restUrl + '/search-orders',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                data: {
                    q: query,
                    page: this.state.searchPage,
                    per_page: 3
                },
                success: function(response) {
                    self.state.isSearching = false;
                    $('.results-loading').hide();

                    if (response.success) {
                        self.renderSearchResults(response.orders, response.total, self.state.searchPage === 1);
                        
                        // Show load more if there are more results
                        var hasMore = (self.state.searchPage * 3) < response.total;
                        $('#search-load-more').toggle(hasMore);
                    }
                },
                error: function() {
                    self.state.isSearching = false;
                    $('.results-loading').hide();
                    self.showToast('error', self.config.strings.networkError);
                }
            });
        },

        /**
         * Render search results
         */
        renderSearchResults: function(orders, total, replace) {
            var $list = $('.search-results-list');
            
            if (replace) {
                $list.empty();
            }

            if (orders.length === 0 && replace) {
                $list.html('<div class="search-no-results">' + this.config.strings.noResults + '</div>');
                $('.results-count').text('');
                return;
            }

            // Update count
            var countText = total + ' ' + (total === 1 ? 'نتیجه' : 'نتیجه');
            $('.results-count').text(countText);

            // Render items
            orders.forEach(function(order) {
                var html = '<div class="search-result-item" data-order-id="' + order.id + '">' +
                    '<div class="result-title">' +
                    '<span class="result-icon">📖</span>' +
                    '<strong>' + self.escapeHtml(order.book_title) + '</strong>' +
                    '<span class="result-order-number">#' + order.order_number + '</span>' +
                    '</div>' +
                    '<div class="result-meta">' +
                    '<span>' + order.book_size + '</span>' +
                    '<span>' + order.quantity + ' نسخه</span>' +
                    '<span class="badge status-' + order.status + '">' + order.status_label + '</span>' +
                    '</div>' +
                    '</div>';
                $list.append(html);
            });
        },

        /**
         * Load more search results
         */
        loadMoreResults: function() {
            this.state.searchPage++;
            var query = $('#upload-search-input').val();
            this.performSearch(query);
        },

        /**
         * Hide search results
         */
        hideSearchResults: function() {
            $('#search-results').hide();
            this.state.searchPage = 1;
        },

        /**
         * Clear search
         */
        clearSearch: function() {
            $('#upload-search-input').val('');
            $('#search-clear').hide();
            this.hideSearchResults();
        },

        /**
         * Open order details modal
         */
        openOrderModal: function(orderId) {
            var self = this;
            this.state.currentOrderId = orderId;

            $('#order-modal').show();
            $('body').css('overflow', 'hidden');

            // Show loading state
            $('#modal-body').html(
                '<div class="loading-state">' +
                '<div class="spinner"></div>' +
                '<p>' + this.config.strings.loading + '</p>' +
                '</div>'
            );

            // Load order details
            this.loadOrderDetails(orderId);
        },

        /**
         * Load order details
         */
        loadOrderDetails: function(orderId) {
            var self = this;

            // First get order info from the card if available
            var $card = $('.order-card[data-order-id="' + orderId + '"]');
            var orderData = {
                order_id: orderId,
                order_number: $card.find('.order-number').text().replace('#', '') || orderId,
                book_title: $card.find('.card-title').text().trim() || 'سفارش #' + orderId,
                book_size: $card.find('.info-item:eq(0) .info-value').text() || '',
                page_count: $card.find('.info-item:eq(1) .info-value').text() || '',
                quantity: $card.find('.info-item:eq(2) .info-value').text() || '',
                status: $card.find('.badge:first').attr('class').replace('badge status-', '') || 'pending',
                status_label: $card.find('.badge:first').text() || 'در انتظار'
            };

            // Update modal header
            $('#modal-title').text('سفارش #' + orderData.order_number);
            $('#breadcrumb-order').text(orderData.book_title);

            // Render order details template
            var template = $('#order-details-template').html();
            var html = this.renderTemplate(template, orderData);
            $('#modal-body').html(html);

            // Load files for this order
            this.loadOrderFiles(orderId);
        },

        /**
         * Load order files
         */
        loadOrderFiles: function(orderId) {
            var self = this;

            $.ajax({
                url: this.config.restUrl + '/order-files/' + orderId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderOrderFiles(response.files);
                        self.updateProgressStepper(response.files);
                    }
                },
                error: function() {
                    self.showToast('error', self.config.strings.networkError);
                }
            });
        },

        /**
         * Render order files
         */
        renderOrderFiles: function(files) {
            var self = this;
            var filesByType = {
                text: [],
                cover: [],
                documents: []
            };

            // Group files by type
            files.forEach(function(file) {
                var type = file.file_type;
                if (filesByType[type]) {
                    filesByType[type].push(file);
                }
            });

            // Render each category
            Object.keys(filesByType).forEach(function(type) {
                var $list = $('.files-list[data-type="' + type + '"]');
                $list.empty();

                if (filesByType[type].length === 0) {
                    $list.html($('#empty-files-template').html());
                } else {
                    filesByType[type].forEach(function(file) {
                        var fileIcon = self.getFileIcon(file.stored_filename);
                        var data = $.extend({}, file, {
                            file_icon: fileIcon
                        });
                        var template = $('#file-item-template').html();
                        var html = self.renderTemplate(template, data);
                        $list.append(html);
                    });
                }
            });
        },

        /**
         * Update progress stepper based on files
         */
        updateProgressStepper: function(files) {
            var filesByType = {
                text: { count: 0, approved: 0 },
                cover: { count: 0, approved: 0 },
                documents: { count: 0, approved: 0 }
            };

            files.forEach(function(file) {
                var type = file.file_type;
                if (filesByType[type]) {
                    filesByType[type].count++;
                    if (file.status === 'approved') {
                        filesByType[type].approved++;
                    }
                }
            });

            Object.keys(filesByType).forEach(function(type) {
                var $step = $('.step[data-step="' + type + '"]');
                var data = filesByType[type];
                
                $step.removeClass('completed current pending');
                
                if (data.approved > 0) {
                    $step.addClass('completed');
                    $step.find('.step-status').text('✅ تایید شده');
                } else if (data.count > 0) {
                    $step.addClass('current');
                    $step.find('.step-status').text('⏳ در انتظار بررسی');
                } else {
                    $step.addClass('pending');
                    $step.find('.step-status').text('📤 آپلود نشده');
                }
            });
        },

        /**
         * Close order modal
         */
        closeOrderModal: function() {
            $('#order-modal').hide();
            $('body').css('overflow', '');
            this.state.currentOrderId = null;
        },

        /**
         * Open upload modal
         */
        openUploadModal: function(orderId, fileType) {
            this.state.currentOrderId = orderId;
            this.state.currentFileType = fileType;

            // Update modal title
            var typeLabel = this.config.strings.fileTypes[fileType] || fileType;
            $('#upload-modal-title').text('آپلود ' + typeLabel);

            // Update dropzone info
            var allowedTypes = this.config.allowedTypes[fileType] || [];
            var maxSize = this.config.maxFileSizes[fileType] || 10485760;
            var info = 'فرمت‌های مجاز: ' + allowedTypes.join(', ').toUpperCase() + 
                       ' | حداکثر حجم: ' + this.formatFileSize(maxSize);
            $('#dropzone-info').text(info);

            // Reset upload state
            $('#upload-progress-container').hide();
            $('#upload-dropzone').show();
            $('#upload-file-input').val('');

            // Show modal
            $('#upload-modal').show();
        },

        /**
         * Close upload modal
         */
        closeUploadModal: function() {
            $('#upload-modal').hide();
            this.state.currentFileType = null;
        },

        /**
         * Handle file selection
         */
        handleFileSelect: function(file) {
            var self = this;

            // Validate file
            var validation = this.validateFile(file);
            if (!validation.valid) {
                this.showToast('error', validation.message);
                return;
            }

            // Show progress
            $('#upload-dropzone').hide();
            $('#upload-progress-container').show();
            $('#upload-file-name').text(file.name);
            $('#upload-file-size').text(this.formatFileSize(file.size));
            $('#upload-progress-fill').css('width', '0%');
            $('#upload-progress-percent').text('0%');
            $('#upload-progress-status').text(this.config.strings.uploading);

            // Upload file
            this.uploadFile(file);
        },

        /**
         * Validate file before upload
         */
        validateFile: function(file) {
            var type = this.state.currentFileType;
            var allowedTypes = this.config.allowedTypes[type] || [];
            var maxSize = this.config.maxFileSizes[type] || 10485760;

            // Check extension
            var ext = file.name.split('.').pop().toLowerCase();
            if (allowedTypes.indexOf(ext) === -1) {
                return {
                    valid: false,
                    message: 'فرمت فایل مجاز نیست. فرمت‌های مجاز: ' + allowedTypes.join(', ').toUpperCase()
                };
            }

            // Check size
            if (file.size > maxSize) {
                return {
                    valid: false,
                    message: 'حجم فایل بیش از حد مجاز است. حداکثر: ' + this.formatFileSize(maxSize)
                };
            }

            return { valid: true };
        },

        /**
         * Upload file to server
         */
        uploadFile: function(file) {
            var self = this;
            var formData = new FormData();

            formData.append('file', file);
            formData.append('order_id', this.state.currentOrderId);
            formData.append('file_type', this.state.currentFileType);

            $.ajax({
                url: this.config.restUrl + '/upload-file',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            $('#upload-progress-fill').css('width', percent + '%');
                            $('#upload-progress-percent').text(percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        $('#upload-progress-status').text('✅ ' + self.config.strings.uploadSuccess);
                        self.showToast('success', response.message);

                        // Reload files after short delay
                        setTimeout(function() {
                            self.closeUploadModal();
                            self.loadOrderFiles(self.state.currentOrderId);
                            
                            // Update the card progress if visible
                            self.updateCardProgress(self.state.currentOrderId);
                        }, 1000);
                    } else {
                        $('#upload-progress-status').text('❌ ' + response.message);
                        self.showToast('error', response.message);
                    }
                },
                error: function(xhr) {
                    var message = self.config.strings.uploadError;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $('#upload-progress-status').text('❌ ' + message);
                    self.showToast('error', message);
                }
            });
        },

        /**
         * Update card progress after upload
         */
        updateCardProgress: function(orderId) {
            // Trigger a page reload to refresh the data
            // In a more advanced implementation, we would update the DOM directly
            var $card = $('.order-card[data-order-id="' + orderId + '"]');
            if ($card.length) {
                var filesValue = $card.find('.info-item.highlight .info-value');
                var current = parseInt(filesValue.text().split('/')[0]) || 0;
                filesValue.text((current + 1) + '/3');
                
                // Update progress bar
                var progress = Math.min(100, ((current + 1) / 3) * 100);
                $card.find('.card-progress-bar .progress-fill').css('width', progress + '%');
            }
        },

        /**
         * Download file
         */
        downloadFile: function(fileId) {
            var self = this;

            // First generate a download token
            $.ajax({
                url: this.config.restUrl + '/generate-download-token/' + fileId,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Open download URL in new tab
                        window.open(response.download_url, '_blank');
                    } else {
                        self.showToast('error', response.message);
                    }
                },
                error: function() {
                    self.showToast('error', self.config.strings.networkError);
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(type, message, title) {
            var icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            var $toast = $('<div class="toast toast-' + type + '">' +
                '<span class="toast-icon">' + icons[type] + '</span>' +
                '<div class="toast-content">' +
                (title ? '<div class="toast-title">' + title + '</div>' : '') +
                '<div class="toast-message">' + message + '</div>' +
                '</div>' +
                '<button class="toast-close">✕</button>' +
                '</div>');

            $('#toast-container').append($toast);

            // Auto remove after 5 seconds
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual close
            $toast.find('.toast-close').on('click', function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            $('#loading-message').text(message || 'لطفاً منتظر بمانید...');
            $('#loading-overlay').show();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#loading-overlay').hide();
        },

        /**
         * Render template with data
         */
        renderTemplate: function(template, data) {
            var result = template;
            
            // Replace simple placeholders {{key}}
            Object.keys(data).forEach(function(key) {
                var regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
                result = result.replace(regex, data[key] || '');
            });

            // Handle conditional sections {{#key}}...{{/key}}
            Object.keys(data).forEach(function(key) {
                var regex = new RegExp('\\{\\{#' + key + '\\}\\}([\\s\\S]*?)\\{\\{\\/' + key + '\\}\\}', 'g');
                if (data[key]) {
                    result = result.replace(regex, '$1');
                } else {
                    result = result.replace(regex, '');
                }
            });

            return result;
        },

        /**
         * Get file icon based on extension
         */
        getFileIcon: function(filename) {
            var ext = filename.split('.').pop().toLowerCase();
            var icons = {
                pdf: '📄',
                doc: '📝',
                docx: '📝',
                jpg: '🖼️',
                jpeg: '🖼️',
                png: '🖼️',
                psd: '🎨'
            };
            return icons[ext] || '📁';
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 بایت';
            var k = 1024;
            var sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Reference self for callbacks
    var self = UploadManager;

    // Initialize when document is ready
    $(document).ready(function() {
        UploadManager.init();
    });

    // Expose to global scope
    window.TabeshUploadManager = UploadManager;

})(jQuery);
