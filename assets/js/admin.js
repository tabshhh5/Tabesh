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

        // Export settings
        $('#export-settings').on('click', function(e) {
            e.preventDefault();
            // Implement export functionality
            alert('قابلیت خروجی گرفتن از تنظیمات در نسخه بعدی اضافه خواهد شد');
        });

        // Import settings
        $('#import-settings').on('click', function(e) {
            e.preventDefault();
            // Implement import functionality
            alert('قابلیت وارد کردن تنظیمات در نسخه بعدی اضافه خواهد شد');
        });
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
                        // Initiate download by redirecting to the download URL
                        // This allows the browser to handle the download naturally
                        window.location.href = response.download_url;
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

})(jQuery);
