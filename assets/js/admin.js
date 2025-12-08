/**
 * Tabesh Admin JavaScript
 */

(function($) {
    'use strict';

    // Helper function to safely construct REST URLs without double slashes
    function buildRestUrl(base, endpoint) {
        const cleanBase = base.replace(/\/+$/, ''); // Remove trailing slashes
        const cleanEndpoint = endpoint.replace(/^\/+/, ''); // Remove leading slashes
        return cleanBase + '/' + cleanEndpoint;
    }

    // Dynamic Parameter Manager Class
    class TabeshParameterManager {
        constructor() {
            // Animation timing constants
            this.ANIMATION_DURATION = 200; // milliseconds
            this.FOCUS_DELAY = 100; // milliseconds
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.syncHiddenFields();
        }

        bindEvents() {
            const self = this;

            // Add parameter button
            $(document).on('click', '.tabesh-param-add', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $manager = $button.closest('.tabesh-param-manager');
                const $list = $manager.find('.tabesh-param-list');
                const placeholder = $button.data('placeholder') || 'مقدار جدید';

                self.addParameter($list, '', placeholder);
                self.updateCount($manager);
                self.syncHiddenField($manager);
            });

            // Remove parameter button
            $(document).on('click', '.tabesh-param-remove', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $item = $button.closest('.tabesh-param-item');
                const $manager = $item.closest('.tabesh-param-manager');

                // Add fade out animation
                $item.fadeOut(self.ANIMATION_DURATION, function() {
                    $item.remove();
                    self.updateCount($manager);
                    self.syncHiddenField($manager);
                });
            });

            // Update hidden field on input change
            $(document).on('input', '.tabesh-param-input', function() {
                const $input = $(this);
                const $manager = $input.closest('.tabesh-param-manager');
                self.syncHiddenField($manager);
            });

            // Handle Enter key to add new parameter
            $(document).on('keypress', '.tabesh-param-input', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    const $input = $(this);
                    const $manager = $input.closest('.tabesh-param-manager');
                    const $addButton = $manager.find('.tabesh-param-add');
                    $addButton.trigger('click');
                    
                    // Focus on the new input
                    setTimeout(function() {
                        $manager.find('.tabesh-param-input').last().focus();
                    }, self.FOCUS_DELAY);
                }
            });
        }

        addParameter($list, value = '', placeholder = 'مقدار جدید') {
            const $item = $('<div>', {
                'class': 'tabesh-param-item',
                'style': 'display:none;'
            });

            const $input = $('<input>', {
                'type': 'text',
                'class': 'tabesh-param-input',
                'value': value,
                'placeholder': placeholder
            });

            const $removeBtn = $('<button>', {
                'type': 'button',
                'class': 'button tabesh-param-remove',
                'title': 'حذف این پارامتر',
                'html': '×'
            });

            $item.append($input).append($removeBtn);
            $list.append($item);
            
            // Fade in animation
            $item.fadeIn(this.ANIMATION_DURATION);

            return $item;
        }

        syncHiddenField($manager) {
            const $hidden = $manager.find('.tabesh-param-hidden');
            const values = [];

            $manager.find('.tabesh-param-input').each(function() {
                const val = $(this).val().trim();
                if (val) {
                    values.push(val);
                }
            });

            // Store as comma-separated string for PHP processing
            $hidden.val(values.join(', '));
        }

        syncHiddenFields() {
            const self = this;
            $('.tabesh-param-manager').each(function() {
                self.syncHiddenField($(this));
            });
        }

        updateCount($manager) {
            const count = $manager.find('.tabesh-param-item').length;
            $manager.find('.param-count').text(count);
        }
    }

    // Settings Tabs
    class TabeshSettingsTabs {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.handleArrayFields();
        }

        bindEvents() {
            $('.nav-tab').on('click', (e) => {
                e.preventDefault();
                const target = $(e.currentTarget).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(e.currentTarget).addClass('nav-tab-active');
                
                $('.tabesh-tab-content').removeClass('active');
                $(target).addClass('active');
            });
        }

        handleArrayFields() {
            // NOTE: Form validation and logging only - NO JSON conversion here
            // PHP will handle all JSON encoding to prevent double-encoding issues
            
            // Add live parameter counting for textarea fields (like paper_types)
            const updateParamCount = ($field) => {
                const value = $field.val().trim();
                const $count = $field.closest('td').find('.param-count');
                
                if (value) {
                    const description = $field.closest('td').find('.description').text();
                    if ($field.attr('id') === 'paper_types') {
                        // Count valid lines for paper_types
                        const lines = value.split('\n').filter(line => {
                            const trimmed = line.trim();
                            return trimmed && trimmed.includes('=');
                        });
                        $count.text(lines.length);
                    } else if (description.includes('key=value') || description.includes('نام=مقدار')) {
                        // Count valid lines for key=value fields
                        const lines = value.split('\n').filter(line => {
                            const trimmed = line.trim();
                            return trimmed && trimmed.includes('=');
                        });
                        $count.text(lines.length);
                    } else {
                        // Count comma-separated items
                        const items = value.split(',').map(item => item.trim()).filter(item => item);
                        $count.text(items.length);
                    }
                } else {
                    $count.text('0');
                }
            };
            
            // Add event listeners for live counting on textarea fields
            $('.tabesh-admin-settings textarea:not(.tabesh-param-hidden)').on('input', function() {
                updateParamCount($(this));
            });
            
            // Form submit validation
            $('form').on('submit', function(e) {
                console.log('Tabesh: Form submission started');

                // Validate pricing configuration fields (key=value format)
                const pricingFields = [
                    'pricing_book_sizes', 'pricing_paper_types', 
                    'pricing_lamination_costs', 'pricing_binding_costs', 
                    'pricing_options_costs', 'pricing_quantity_discounts'
                ];

                pricingFields.forEach(fieldName => {
                    const $field = $(`#${fieldName}`);
                    if ($field.length) {
                        const value = $field.val().trim();
                        if (value) {
                            // Just validate format - PHP will handle JSON conversion
                            const lines = value.split('\n').filter(line => line.trim());
                            let validLines = 0;
                            lines.forEach(line => {
                                if (line.includes('=')) {
                                    const equalIndex = line.indexOf('=');
                                    const key = line.substring(0, equalIndex).trim();
                                    const val = line.substring(equalIndex + 1).trim();
                                    if (key && val !== undefined && val !== '') {
                                        validLines++;
                                    }
                                }
                            });
                            console.log(`Tabesh: ${fieldName} has ${validLines} valid entries (will be processed by PHP)`);
                        } else {
                            console.log(`Tabesh: ${fieldName} is empty, will skip save`);
                        }
                    }
                });

                // Validate paper_types field format (special format: type=weight1,weight2,weight3)
                const $paperTypesField = $('#paper_types');
                if ($paperTypesField.length) {
                    const value = $paperTypesField.val().trim();
                    if (value) {
                        const lines = value.split('\n').filter(line => line.trim());
                        let validLines = 0;
                        lines.forEach(line => {
                            if (line.includes('=')) {
                                const equalIndex = line.indexOf('=');
                                const key = line.substring(0, equalIndex).trim();
                                const val = line.substring(equalIndex + 1).trim();
                                if (key && val) {
                                    validLines++;
                                }
                            }
                        });
                        console.log(`Tabesh: paper_types has ${validLines} valid entries (will be processed by PHP)`);
                    }
                }

                console.log('Tabesh: Form validation complete');
            });
        }
    }

    // Orders Management
    class TabeshOrdersManager {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Status filter
            $('#status-filter').on('change', (e) => {
                this.filterByStatus($(e.target).val());
            });

            // Status change
            $('.tabesh-status-select').on('change', (e) => {
                const $select = $(e.target);
                const orderId = $select.data('order-id');
                const newStatus = $select.val();
                
                this.updateOrderStatus(orderId, newStatus);
            });

            // View order details
            $('.view-order').on('click', (e) => {
                const orderId = $(e.target).data('order-id');
                this.viewOrderDetails(orderId);
            });

            // Close modal
            $('.tabesh-modal-close').on('click', () => {
                $('#order-details-modal').hide();
            });

            // Click outside modal to close
            $(window).on('click', (e) => {
                if ($(e.target).hasClass('tabesh-modal')) {
                    $('#order-details-modal').hide();
                }
            });
        }

        filterByStatus(status) {
            const $rows = $('.tabesh-orders-table tbody tr');
            
            if (!status) {
                $rows.show();
                return;
            }

            $rows.each(function() {
                const rowStatus = $(this).data('status');
                if (rowStatus === status) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        updateOrderStatus(orderId, newStatus) {
            if (!confirm('آیا از تغییر وضعیت این سفارش اطمینان دارید؟')) {
                return;
            }

            const $select = $(`.tabesh-status-select[data-order-id="${orderId}"]`);
            const originalStatus = $select.find('option:selected').val();

            // Show loading
            $select.prop('disabled', true);

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'update-status'),
                method: 'POST',
                contentType: 'application/json',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                data: JSON.stringify({
                    order_id: orderId,
                    status: newStatus
                }),
                success: (response) => {
                    if (response.success) {
                        this.showNotification('وضعیت با موفقیت به‌روزرسانی شد', 'success');
                        // Update the row's data attribute
                        $select.closest('tr').data('status', newStatus);
                    } else {
                        this.showNotification(response.message || 'خطا در به‌روزرسانی وضعیت', 'error');
                        $select.val(originalStatus);
                    }
                },
                error: () => {
                    this.showNotification('خطا در برقراری ارتباط با سرور', 'error');
                    $select.val(originalStatus);
                },
                complete: () => {
                    $select.prop('disabled', false);
                }
            });
        }

        viewOrderDetails(orderId) {
            const $modal = $('#order-details-modal');
            const $content = $('#order-details-content');

            $content.html('<p style="text-align:center;"><span class="tabesh-loading-inline"></span> در حال بارگذاری...</p>');
            $modal.show();

            // Get order details via AJAX
            $.ajax({
                url: tabeshAdminData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_order_details',
                    order_id: orderId,
                    nonce: tabeshAdminData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $content.html(response.data.html);
                    } else {
                        $content.html('<p class="tabesh-message error">خطا در بارگذاری اطلاعات</p>');
                    }
                },
                error: () => {
                    $content.html('<p class="tabesh-message error">خطا در برقراری ارتباط با سرور</p>');
                }
            });
        }

        showNotification(message, type = 'info') {
            const $notification = $('<div>')
                .addClass('notice notice-' + (type === 'success' ? 'success' : 'error') + ' is-dismissible')
                .html('<p>' + message + '</p>')
                .prependTo('.wrap');

            setTimeout(() => {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            }, 3000);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize parameter manager
        if ($('.tabesh-param-manager').length) {
            new TabeshParameterManager();
        }

        // Initialize settings tabs
        if ($('.tabesh-admin-settings').length) {
            new TabeshSettingsTabs();
        }

        // Initialize SMS pattern validation
        if ($('.sms-pattern-input').length) {
            // Call inline SMS validation function
            (function() {
                // Add real-time validation for SMS pattern inputs
                $('.sms-pattern-input').on('input', function() {
                    const value = $(this).val().trim();
                    // Use more robust selector
                    const $description = $(this).closest('td').find('.description small');
                    
                    if (value === '') {
                        $(this)[0].setCustomValidity('');
                        if ($description.length) {
                            $description.css('color', '#666');
                        }
                        return;
                    }
                    
                    if (!/^\d+$/.test(value)) {
                        $(this)[0].setCustomValidity('کد الگو باید فقط شامل اعداد باشد');
                        if ($description.length) {
                            $description.css('color', '#dc3232').text('⚠️ فقط عدد وارد کنید');
                        }
                    } else {
                        $(this)[0].setCustomValidity('');
                        if ($description.length) {
                            $description.css('color', '#46b450').text('✓ معتبر');
                        }
                    }
                });

                // Validate on form submit
                $('form:has(.sms-pattern-input)').on('submit', function(e) {
                    let hasError = false;
                    let $firstErrorInput = null;
                    
                    $(this).find('.sms-pattern-input').each(function() {
                        const value = $(this).val().trim();
                        if (value !== '' && !/^\d+$/.test(value)) {
                            hasError = true;
                            if (!$firstErrorInput) {
                                $firstErrorInput = $(this);
                            }
                        }
                    });
                    
                    if (hasError) {
                        e.preventDefault();
                        if ($firstErrorInput) {
                            $firstErrorInput.focus();
                        }
                        
                        // Use specific container
                        const $targetContainer = $('.tabesh-admin-settings .wrap').first();
                        const $noticeContainer = $targetContainer.length ? $targetContainer : $(this).parent();
                        
                        const $notice = $('<div>')
                            .addClass('notice notice-error is-dismissible')
                            .html('<p><strong>خطا:</strong> لطفاً کد الگوهای پیامک را به صورت عددی وارد کنید.</p>')
                            .prependTo($noticeContainer);
                        
                        $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">بستن این پیام</span></button>');
                        $notice.find('.notice-dismiss').on('click', function() {
                            $notice.fadeOut(function() {
                                $notice.remove();
                            });
                        });
                        
                        setTimeout(function() {
                            $notice.fadeOut(function() {
                                $notice.remove();
                            });
                        }, 5000);
                        
                        return false;
                    }
                });
            })();
        }

        // Initialize orders manager
        if ($('.tabesh-admin-orders').length || $('.tabesh-staff-panel').length) {
            new TabeshOrdersManager();
        }

        // Initialize file management
        if ($('.tabesh-order-details-container').length) {
            new TabeshFileManager();
        }

        // Export/Import functionality
        initExportImport();

        // Cleanup functionality
        initCleanup();
    });

    /**
     * File Management Class
     */
    class TabeshFileManager {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            const self = this;

            // Approve file
            $(document).on('click', '.approve-file-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                
                if (!confirm('آیا از تایید این فایل اطمینان دارید؟')) {
                    return;
                }
                
                self.approveFile(fileId);
            });

            // Reject file
            $(document).on('click', '.reject-file-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.showRejectModal(fileId);
            });

            // Add comment
            $(document).on('click', '.add-comment-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.showCommentModal(fileId);
            });

            // View comments
            $(document).on('click', '.view-comments-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.showCommentsModal(fileId);
            });

            // View versions
            $(document).on('click', '.view-versions-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                // Version history modal will be implemented in a future release
                // This requires additional backend support for version comparison and download
                alert('قابلیت مشاهده نسخه‌های فایل در نسخه بعدی اضافه خواهد شد');
            });

            // Download file
            $(document).on('click', '.download-file-btn', function(e) {
                e.preventDefault();
                const fileId = $(this).data('file-id');
                self.downloadFile(fileId);
            });
        }

        approveFile(fileId) {
            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'approve-file'),
                type: 'POST',
                data: {
                    file_id: fileId
                },
                headers: {
                    'X-WP-Nonce': tabeshAdminData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'خطا در تایید فایل');
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        }

        showRejectModal(fileId) {
            const modalHtml = `
                <div class="tabesh-modal" id="reject-modal" style="display: none;">
                    <div class="tabesh-modal-overlay"></div>
                    <div class="tabesh-modal-dialog">
                        <div class="tabesh-modal-content">
                            <div class="tabesh-modal-header">
                                <h3>رد کردن فایل</h3>
                                <button type="button" class="tabesh-modal-close">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="tabesh-modal-body">
                                <p>لطفاً دلیل رد کردن فایل را وارد کنید:</p>
                                <textarea id="rejection-reason" rows="5" style="width: 100%; padding: 8px;"></textarea>
                            </div>
                            <div class="tabesh-modal-footer">
                                <button type="button" class="button button-secondary tabesh-modal-close">
                                    انصراف
                                </button>
                                <button type="button" class="button button-primary" id="confirm-rejection">
                                    رد کردن فایل
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#reject-modal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            $('#reject-modal').fadeIn(300);
            
            // Bind close events
            $('.tabesh-modal-close').on('click', function() {
                $('#reject-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Bind confirm event
            $('#confirm-rejection').on('click', () => {
                const reason = $('#rejection-reason').val().trim();
                
                if (!reason) {
                    alert('لطفاً دلیل رد کردن را وارد کنید');
                    return;
                }
                
                this.rejectFile(fileId, reason);
            });
        }

        rejectFile(fileId, reason) {
            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'reject-file'),
                type: 'POST',
                data: {
                    file_id: fileId,
                    reason: reason
                },
                headers: {
                    'X-WP-Nonce': tabeshAdminData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'خطا در رد فایل');
                    }
                    $('#reject-modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        }

        showCommentModal(fileId) {
            const modalHtml = `
                <div class="tabesh-modal" id="comment-modal" style="display: none;">
                    <div class="tabesh-modal-overlay"></div>
                    <div class="tabesh-modal-dialog">
                        <div class="tabesh-modal-content">
                            <div class="tabesh-modal-header">
                                <h3>افزودن نظر</h3>
                                <button type="button" class="tabesh-modal-close">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="tabesh-modal-body">
                                <p>نظر خود را درباره این فایل وارد کنید:</p>
                                <textarea id="comment-text" rows="5" style="width: 100%; padding: 8px;"></textarea>
                            </div>
                            <div class="tabesh-modal-footer">
                                <button type="button" class="button button-secondary tabesh-modal-close">
                                    انصراف
                                </button>
                                <button type="button" class="button button-primary" id="confirm-comment">
                                    ثبت نظر
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#comment-modal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            $('#comment-modal').fadeIn(300);
            
            // Bind close events
            $('.tabesh-modal-close').on('click', function() {
                $('#comment-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Bind confirm event
            $('#confirm-comment').on('click', () => {
                const comment = $('#comment-text').val().trim();
                
                if (!comment) {
                    alert('لطفاً نظر خود را وارد کنید');
                    return;
                }
                
                this.addComment(fileId, comment);
            });
        }

        addComment(fileId, comment) {
            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'file-comment'),
                type: 'POST',
                data: {
                    file_id: fileId,
                    comment: comment
                },
                headers: {
                    'X-WP-Nonce': tabeshAdminData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'خطا در ثبت نظر');
                    }
                    $('#comment-modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        }

        showCommentsModal(fileId) {
            const modalHtml = `
                <div class="tabesh-modal" id="comments-modal" style="display: none;">
                    <div class="tabesh-modal-overlay"></div>
                    <div class="tabesh-modal-dialog tabesh-modal-large">
                        <div class="tabesh-modal-content">
                            <div class="tabesh-modal-header">
                                <h3>نظرات فایل</h3>
                                <button type="button" class="tabesh-modal-close">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <div class="tabesh-modal-body">
                                <div id="comments-list">
                                    <p style="text-align: center; padding: 20px;">
                                        <span class="spinner is-active" style="float: none;"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#comments-modal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            $('#comments-modal').fadeIn(300);
            
            // Bind close events
            $('.tabesh-modal-close').on('click', function() {
                $('#comments-modal').fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Load comments
            this.loadComments(fileId);
        }

        loadComments(fileId) {
            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'file-comments/' + fileId),
                type: 'GET',
                headers: {
                    'X-WP-Nonce': tabeshAdminData.nonce
                },
                success: function(response) {
                    if (response.success && response.comments) {
                        const comments = response.comments;
                        let html = '';
                        
                        if (comments.length === 0) {
                            html = '<p style="text-align: center; color: #999;">هیچ نظری ثبت نشده است.</p>';
                        } else {
                            html = '<div class="comments-list-container">';
                            comments.forEach(function(comment) {
                                html += `
                                    <div class="comment-item">
                                        <div class="comment-header">
                                            <strong>${comment.author_name || 'ادمین'}</strong>
                                            <span class="comment-date">${comment.created_at}</span>
                                        </div>
                                        <div class="comment-body">
                                            ${comment.comment_text}
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        
                        $('#comments-list').html(html);
                    } else {
                        $('#comments-list').html('<p style="text-align: center; color: #d32f2f;">خطا در بارگذاری نظرات</p>');
                    }
                },
                error: function() {
                    $('#comments-list').html('<p style="text-align: center; color: #d32f2f;">خطا در ارتباط با سرور</p>');
                }
            });
        }

        downloadFile(fileId) {
            // Request a download token from the server
            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'files/generate-token'),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ file_id: fileId }),
                headers: {
                    'X-WP-Nonce': tabeshAdminData.nonce
                },
                success: function(response) {
                    if (response.success && response.download_url) {
                        // Open download URL in new tab to ensure proper Referer header for CDN/firewall compatibility
                        window.open(response.download_url, '_blank');
                    } else {
                        alert(response.message || 'خطا در دریافت لینک دانلود');
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'خطا در ارتباط با سرور';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    alert(errorMessage);
                }
            });
        }
    }

    /**
     * Initialize Export/Import functionality
     */
    function initExportImport() {
        let importFileData = null;

        // Export: Select all checkbox
        $('#export_all_sections').on('change', function() {
            $('.export-section-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Update "select all" when individual checkboxes change
        $(document).on('change', '.export-section-checkbox', function() {
            const total = $('.export-section-checkbox').length;
            const checked = $('.export-section-checkbox:checked').length;
            $('#export_all_sections').prop('checked', total === checked);
        });

        // Show export preview
        $('#show-export-preview').on('click', function() {
            const sections = [];
            $('.export-section-checkbox:checked').each(function() {
                sections.push($(this).val());
            });

            if (sections.length === 0) {
                alert('لطفاً حداقل یک بخش را انتخاب کنید');
                return;
            }

            const $preview = $('#export-preview');
            const $content = $('#export-preview-content');
            
            $content.html('<p>در حال بارگذاری...</p>');
            $preview.show();

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'export/preview'),
                method: 'GET',
                data: { sections: sections },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success && response.preview) {
                        let html = '<ul style="list-style: none; padding: 0;">';
                        $.each(response.preview, function(key, data) {
                            html += '<li style="padding: 5px 0;">✓ <strong>' + data.label + '</strong>: ' + data.count + ' رکورد</li>';
                        });
                        html += '</ul>';
                        $content.html(html);
                    } else {
                        $content.html('<p style="color: red;">خطا در دریافت پیش‌نمایش</p>');
                    }
                },
                error: function() {
                    $content.html('<p style="color: red;">خطا در ارتباط با سرور</p>');
                }
            });
        });

        // Export data
        $('#export-data-btn').on('click', function() {
            const sections = [];
            $('.export-section-checkbox:checked').each(function() {
                sections.push($(this).val());
            });

            if (sections.length === 0) {
                alert('لطفاً حداقل یک بخش را انتخاب کنید');
                return;
            }

            const $btn = $(this);
            const $status = $('#export-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال برونبری...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'export'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ sections: sections }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Create download
                        const dataStr = JSON.stringify(response.data, null, 2);
                        const dataBlob = new Blob([dataStr], { type: 'application/json' });
                        const url = URL.createObjectURL(dataBlob);
                        const link = document.createElement('a');
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
                        link.href = url;
                        link.download = 'tabesh-backup-' + timestamp + '.json';
                        link.click();
                        URL.revokeObjectURL(url);
                        
                        $status.html('<span style="color: #46b450;">✓ برونبری با موفقیت انجام شد</span>');
                        setTimeout(() => $status.html(''), 3000);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ خطا در برونبری</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در ارتباط با سرور';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Import: Select all checkbox
        $('#import_all_sections').on('change', function() {
            $('.import-section-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Update "select all" when individual import checkboxes change
        $(document).on('change', '.import-section-checkbox', function() {
            const total = $('.import-section-checkbox').length;
            const checked = $('.import-section-checkbox:checked').length;
            $('#import_all_sections').prop('checked', total === checked);
        });

        // Validate import file
        $('#validate-import-btn').on('click', function() {
            const fileInput = document.getElementById('import-file');
            const file = fileInput.files[0];

            if (!file) {
                alert('لطفاً ابتدا یک فایل انتخاب کنید');
                return;
            }

            if (!file.name.endsWith('.json')) {
                alert('فقط فایل‌های JSON مجاز هستند');
                return;
            }

            const $btn = $(this);
            const $status = $('#import-status');
            const $preview = $('#import-preview');
            const $content = $('#import-preview-content');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال بررسی فایل...</span>');

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    importFileData = data;

                    // Send to server for validation
                    $.ajax({
                        url: buildRestUrl(tabeshAdminData.restUrl, 'import/validate'),
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ data: data }),
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                        },
                        success: function(response) {
                            if (response.valid) {
                                let html = '<ul style="list-style: none; padding: 0;">';
                                html += '<li><strong>نسخه:</strong> ' + response.version + '</li>';
                                html += '<li><strong>تاریخ برونبری:</strong> ' + response.export_date + '</li>';
                                if (response.site_url) {
                                    html += '<li><strong>سایت مبدا:</strong> ' + response.site_url + '</li>';
                                }
                                html += '</ul>';
                                $content.html(html);

                                // Show sections
                                let sectionsHtml = '';
                                $.each(response.sections, function(key, data) {
                                    sectionsHtml += '<label style="display: block; margin-bottom: 8px;">';
                                    sectionsHtml += '<input type="checkbox" class="import-section-checkbox" ';
                                    sectionsHtml += 'value="' + key + '" checked style="margin-left: 5px;">';
                                    sectionsHtml += data.label + ' (' + data.count + ' رکورد)';
                                    sectionsHtml += '</label>';
                                });
                                $('#import-sections-list').html(sectionsHtml);

                                $preview.show();
                                $('#import-data-btn').prop('disabled', false);
                                $status.html('<span style="color: #46b450;">✓ فایل معتبر است</span>');
                            } else {
                                $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                                $('#import-data-btn').prop('disabled', true);
                            }
                            $btn.prop('disabled', false);
                        },
                        error: function(xhr) {
                            let msg = 'خطا در بررسی فایل';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                            $btn.prop('disabled', false);
                        }
                    });

                } catch (err) {
                    $status.html('<span style="color: #dc3232;">✗ فایل JSON نامعتبر است</span>');
                    $btn.prop('disabled', false);
                }
            };
            reader.readAsText(file);
        });

        // Import data
        $('#import-data-btn').on('click', function() {
            if (!importFileData) {
                alert('لطفاً ابتدا فایل را بررسی کنید');
                return;
            }

            const sections = [];
            $('.import-section-checkbox:checked').each(function() {
                sections.push($(this).val());
            });

            if (sections.length === 0) {
                alert('لطفاً حداقل یک بخش را انتخاب کنید');
                return;
            }

            const mode = $('input[name="import_mode"]:checked').val();

            // Confirm if replace mode
            if (mode === 'replace') {
                if (!confirm('⚠️ توجه: در حالت جایگزینی، تمام داده‌های موجود در بخش‌های انتخاب شده حذف و با داده‌های جدید جایگزین می‌شوند.\n\nآیا مطمئن هستید؟')) {
                    return;
                }
            }

            const $btn = $(this);
            const $status = $('#import-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال درونریزی...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'import'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    data: importFileData,
                    sections: sections,
                    mode: mode
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        let msg = response.message;
                        if (response.results) {
                            msg += '<ul style="list-style: none; padding: 0; margin-top: 10px;">';
                            $.each(response.results, function(key, result) {
                                msg += '<li>• ' + result.message + '</li>';
                            });
                            msg += '</ul>';
                        }
                        $status.html('<span style="color: #46b450;">✓ ' + msg + '</span>');
                        
                        // Clear file input and reset
                        $('#import-file').val('');
                        $('#import-preview').hide();
                        importFileData = null;
                        $btn.prop('disabled', true);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    let msg = 'خطا در درونریزی';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize Cleanup functionality
     */
    function initCleanup() {
        // Show cleanup preview
        $('#show-cleanup-preview').on('click', function() {
            const $preview = $('#cleanup-preview');
            const $content = $('#cleanup-preview-content');
            
            $content.html('<p>در حال بارگذاری...</p>');
            $preview.show();

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/preview'),
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success && response.preview) {
                        const p = response.preview;
                        let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
                        html += '<div><strong>سفارشات:</strong><ul style="list-style: none; padding-right: 20px;">';
                        html += '<li>کل: ' + p.orders.total + '</li>';
                        html += '<li>بایگانی: ' + p.orders.archived + '</li>';
                        html += '</ul></div>';
                        html += '<div><strong>فایل‌ها:</strong><ul style="list-style: none; padding-right: 20px;">';
                        html += '<li>رکوردها: ' + p.files.records + '</li>';
                        html += '<li>نسخه‌ها: ' + p.file_versions + '</li>';
                        html += '<li>فیزیکی: ' + p.physical_files + '</li>';
                        html += '</ul></div>';
                        html += '<div><strong>لاگ‌ها:</strong><ul style="list-style: none; padding-right: 20px;">';
                        html += '<li>عادی: ' + p.logs + '</li>';
                        html += '<li>امنیتی: ' + p.security_logs + '</li>';
                        html += '</ul></div>';
                        html += '<div><strong>سایر:</strong><ul style="list-style: none; padding-right: 20px;">';
                        html += '<li>وظایف آپلود: ' + p.upload_tasks + '</li>';
                        html += '</ul></div>';
                        html += '</div>';
                        $content.html(html);
                    } else {
                        $content.html('<p style="color: red;">خطا در دریافت آمار</p>');
                    }
                },
                error: function() {
                    $content.html('<p style="color: red;">خطا در ارتباط با سرور</p>');
                }
            });
        });

        // Cleanup orders
        $('#cleanup-orders-btn').on('click', function() {
            const all = $('#cleanup_orders_all').is(':checked');
            const archived = $('#cleanup_orders_archived').is(':checked');
            const days = parseInt($('#cleanup_orders_days').val()) || 0;
            const userId = parseInt($('#cleanup_orders_user_id').val()) || 0;

            if (!all && !archived && !days && !userId) {
                alert('لطفاً حداقل یک گزینه را انتخاب کنید');
                return;
            }

            let confirmMsg = 'آیا مطمئن هستید که می‌خواهید سفارشات را حذف کنید؟\n';
            if (all) confirmMsg += '- همه سفارشات حذف خواهند شد\n';
            if (archived) confirmMsg += '- سفارشات بایگانی شده حذف خواهند شد\n';
            if (days) confirmMsg += '- سفارشات قدیمی‌تر از ' + days + ' روز حذف خواهند شد\n';
            if (userId) confirmMsg += '- سفارشات کاربر ' + userId + ' حذف خواهند شد\n';
            confirmMsg += '\nاین عملیات قابل بازگشت نیست!';

            if (!confirm(confirmMsg)) {
                return;
            }

            const $btn = $(this);
            const $status = $('#cleanup-orders-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال حذف...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/orders'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    all: all,
                    archived: archived,
                    older_than: days,
                    user_id: userId
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                        // Reset form
                        $('#cleanup_orders_all, #cleanup_orders_archived').prop('checked', false);
                        $('#cleanup_orders_days, #cleanup_orders_user_id').val('');
                        // Refresh preview
                        $('#show-cleanup-preview').trigger('click');
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در حذف سفارشات';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Cleanup files
        $('#cleanup-files-btn').on('click', function() {
            const database = $('#cleanup_files_database').is(':checked');
            const physical = $('#cleanup_files_physical').is(':checked');

            if (!database && !physical) {
                alert('لطفاً حداقل یک گزینه را انتخاب کنید');
                return;
            }

            let confirmMsg = 'آیا مطمئن هستید که می‌خواهید فایل‌ها را حذف کنید؟\n';
            if (database) confirmMsg += '- رکوردهای فایل از دیتابیس حذف خواهند شد\n';
            if (physical) confirmMsg += '- فایل‌های فیزیکی از سرور حذف خواهند شد\n';
            confirmMsg += '\nاین عملیات قابل بازگشت نیست!';

            if (!confirm(confirmMsg)) {
                return;
            }

            const $btn = $(this);
            const $status = $('#cleanup-files-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال حذف...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/files'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    database: database,
                    physical: physical
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                        $('#cleanup_files_database, #cleanup_files_physical').prop('checked', false);
                        $('#show-cleanup-preview').trigger('click');
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در حذف فایل‌ها';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Cleanup orphan files
        $('#cleanup-orphan-files-btn').on('click', function() {
            if (!confirm('آیا مطمئن هستید که می‌خواهید فایل‌های یتیم را حذف کنید؟\n\nفایل‌های یتیم شامل:\n- رکوردهای دیتابیس بدون فایل فیزیکی\n- فایل‌های فیزیکی بدون رکورد دیتابیس\n\nاین عملیات قابل بازگشت نیست!')) {
                return;
            }

            const $btn = $(this);
            const $status = $('#cleanup-files-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال بررسی و حذف...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/files'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    orphans: true
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                        $('#show-cleanup-preview').trigger('click');
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در حذف فایل‌های یتیم';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Cleanup logs
        $('#cleanup-logs-btn').on('click', function() {
            const type = $('input[name="cleanup_logs_type"]:checked').val();
            const days = parseInt($('#cleanup_logs_days').val()) || 0;

            let confirmMsg = 'آیا مطمئن هستید که می‌خواهید لاگ‌ها را حذف کنید؟\n';
            if (type === 'all') confirmMsg += '- همه لاگ‌ها (عادی و امنیتی) حذف خواهند شد\n';
            else if (type === 'regular') confirmMsg += '- لاگ‌های عادی حذف خواهند شد\n';
            else if (type === 'security') confirmMsg += '- لاگ‌های امنیتی حذف خواهند شد\n';
            if (days > 0) confirmMsg += '- فقط لاگ‌های قدیمی‌تر از ' + days + ' روز\n';
            confirmMsg += '\nاین عملیات قابل بازگشت نیست!';

            if (!confirm(confirmMsg)) {
                return;
            }

            const $btn = $(this);
            const $status = $('#cleanup-logs-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال حذف...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/logs'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    type: type,
                    older_than: days
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                        $('#cleanup_logs_days').val('');
                        $('#show-cleanup-preview').trigger('click');
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در حذف لاگ‌ها';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Reset settings
        $('#reset-settings-btn').on('click', function() {
            if (!confirm('آیا مطمئن هستید که می‌خواهید تنظیمات را به حالت پیش‌فرض بازگردانید؟\n\nسفارشات و فایل‌ها حفظ می‌شوند، فقط تنظیمات ریست می‌شود.\n\nاین عملیات قابل بازگشت نیست!')) {
                return;
            }

            const $btn = $(this);
            const $status = $('#reset-settings-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #0073aa;">⏳ در حال بازگردانی...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/reset-settings'),
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در بازگردانی تنظیمات';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Factory reset
        $('#factory-reset-btn').on('click', function() {
            const confirmKey = $('#factory-reset-confirm').val().trim();

            if (confirmKey !== 'RESET') {
                alert('برای تأیید ریست کامل، باید کلمه RESET را دقیقاً تایپ کنید.');
                return;
            }

            if (!confirm('⛔ هشدار نهایی ⛔\n\nشما در حال انجام ریست کامل هستید!\n\nتمام داده‌های زیر برای همیشه حذف خواهند شد:\n- همه سفارشات\n- همه فایل‌ها\n- همه لاگ‌ها\n- همه تنظیمات\n\nآیا کاملاً مطمئن هستید؟')) {
                return;
            }

            // Second confirmation
            if (!confirm('آخرین فرصت!\n\nآیا واقعاً می‌خواهید ادامه دهید؟\nاین عملیات قابل بازگشت نیست و تمام داده‌ها از بین خواهند رفت.')) {
                return;
            }

            const $btn = $(this);
            const $status = $('#factory-reset-status');
            
            $btn.prop('disabled', true);
            $status.html('<span style="color: #dc3232;">⏳ در حال حذف همه چیز...</span>');

            $.ajax({
                url: buildRestUrl(tabeshAdminData.restUrl, 'cleanup/factory-reset'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    confirm_key: confirmKey
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tabeshAdminData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ ' + response.message + '</span>');
                        $('#factory-reset-confirm').val('');
                        $('#show-cleanup-preview').trigger('click');
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.html('<span style="color: #dc3232;">✗ ' + response.message + '</span>');
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr) {
                    let msg = 'خطا در ریست کامل';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $status.html('<span style="color: #dc3232;">✗ ' + msg + '</span>');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

})(jQuery);
