/**
 * Admin Order Form Shortcode JavaScript - Redesigned
 * 
 * جاوااسکریپت فرم ثبت سفارش ویژه مدیر - بازطراحی شده
 * Handles form interactions, user search, order submission,
 * toast notifications, and keyboard shortcuts
 * 
 * @package Tabesh
 * @since 1.0.4
 */

(function($) {
    'use strict';

    // Global variables / متغیرهای سراسری
    let userSearchTimeout = null;
    let calculatedPrice = null;
    let selectedUserId = null;

    /**
     * Initialize when document is ready
     * راه‌اندازی هنگام آماده شدن سند
     */
    $(document).ready(function() {
        // Only initialize if our form exists
        // فقط اگر فرم ما وجود داشته باشد راه‌اندازی شود
        if ($('#tabesh-admin-order-form-main').length === 0) {
            return;
        }

        initCustomerSelection();
        initFormFields();
        initPriceCalculation();
        initFormSubmission();
        initKeyboardShortcuts();
    });

    /**
     * Initialize keyboard shortcuts
     * راه‌اندازی میانبرهای صفحه‌کلید
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl+Enter to submit / Ctrl+Enter برای ثبت
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                submitOrder();
            }
        });
    }

    /**
     * Initialize customer selection functionality
     * راه‌اندازی قابلیت انتخاب مشتری
     */
    function initCustomerSelection() {
        // Toggle between existing and new user
        // تغییر بین کاربر موجود و جدید
        $('input[name="customer_type"]').on('change', function() {
            const type = $(this).val();
            
            if (type === 'existing') {
                $('#aof-existing-user-section').show();
                $('#aof-new-user-section').hide();
            } else {
                $('#aof-existing-user-section').hide();
                $('#aof-new-user-section').show();
                // Clear existing user selection / پاک کردن انتخاب کاربر موجود
                selectedUserId = null;
                $('#aof-selected-user-id').val('');
                $('#aof-selected-user-display').empty();
            }
        });

        // Live search for users / جستجوی زنده کاربران
        $('#aof-user-search').on('input', function() {
            const search = $(this).val().trim();
            
            clearTimeout(userSearchTimeout);
            
            if (search.length < 2) {
                $('#aof-user-search-results').empty();
                return;
            }

            userSearchTimeout = setTimeout(function() {
                searchUsers(search);
            }, 300);
        });

        // Close search results when clicking outside
        // بستن نتایج جستجو هنگام کلیک در خارج
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#aof-user-search, #aof-user-search-results').length) {
                $('#aof-user-search-results').empty();
            }
        });

        // Create new user button / دکمه ایجاد کاربر جدید
        $('#aof-create-user-btn').on('click', function() {
            createNewUser();
        });

        // Remove selected user / حذف کاربر انتخاب شده
        $(document).on('click', '#aof-selected-user-display .remove-user', function() {
            selectedUserId = null;
            $('#aof-selected-user-id').val('');
            $('#aof-selected-user-display').empty();
        });
    }

    /**
     * Search users via API
     * جستجوی کاربران از طریق API
     * 
     * @param {string} search Search query / عبارت جستجو
     */
    function searchUsers(search) {
        $.ajax({
            url: tabeshAdminOrderForm.restUrl + '/admin/search-users-live',
            method: 'GET',
            data: { search: search },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderForm.nonce);
                $('#aof-user-search-results').html(
                    '<div class="searching">' + tabeshAdminOrderForm.strings.searching + '</div>'
                );
            },
            success: function(response) {
                if (response.success && response.users && response.users.length > 0) {
                    displayUserResults(response.users);
                } else {
                    $('#aof-user-search-results').html(
                        '<div class="no-results">' + tabeshAdminOrderForm.strings.noResults + '</div>'
                    );
                }
            },
            error: function() {
                $('#aof-user-search-results').html(
                    '<div class="error">' + tabeshAdminOrderForm.strings.searchError + '</div>'
                );
            }
        });
    }

    /**
     * Display user search results
     * نمایش نتایج جستجوی کاربران
     * 
     * @param {Array} users Array of user objects / آرایه اشیاء کاربر
     */
    function displayUserResults(users) {
        const $results = $('#aof-user-search-results');
        $results.empty();

        users.forEach(function(user) {
            const displayName = user.display_name || (user.first_name + ' ' + user.last_name);
            const $item = $('<div class="user-result-item"></div>');
            $item.html(
                '<div class="user-name">' + escapeHtml(displayName) + '</div>' +
                '<div class="user-login">' + escapeHtml(user.user_login) + '</div>'
            );
            
            $item.on('click', function() {
                selectUser(user);
            });
            
            $results.append($item);
        });
    }

    /**
     * Select a user from search results
     * انتخاب یک کاربر از نتایج جستجو
     * 
     * @param {Object} user User object / شیء کاربر
     */
    function selectUser(user) {
        selectedUserId = user.id;
        $('#aof-selected-user-id').val(user.id);
        
        const displayName = user.display_name || (user.first_name + ' ' + user.last_name);
        $('#aof-selected-user-display').html(
            '<div class="selected-user">' +
            '<strong>' + escapeHtml(displayName) + '</strong> (' + escapeHtml(user.user_login) + ')' +
            '<button type="button" class="remove-user">&times;</button>' +
            '</div>'
        );
        
        $('#aof-user-search').val('');
        $('#aof-user-search-results').empty();
    }

    /**
     * Create new user
     * ایجاد کاربر جدید
     */
    function createNewUser() {
        const mobile = $('#aof-new-mobile').val().trim();
        const firstName = $('#aof-new-first-name').val().trim();
        const lastName = $('#aof-new-last-name').val().trim();

        // Validate / اعتبارسنجی
        if (!mobile || !firstName || !lastName) {
            showToast(tabeshAdminOrderForm.strings.fillAllUserFields, 'error');
            return;
        }

        if (!/^09[0-9]{9}$/.test(mobile)) {
            showToast(tabeshAdminOrderForm.strings.invalidMobile, 'error');
            return;
        }

        const $btn = $('#aof-create-user-btn');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> ' +
            tabeshAdminOrderForm.strings.submitting
        );

        $.ajax({
            url: tabeshAdminOrderForm.restUrl + '/admin/create-user',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                mobile: mobile,
                first_name: firstName,
                last_name: lastName
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderForm.nonce);
            },
            success: function(response) {
                if (response.success && response.user) {
                    // Switch to existing user mode and select the new user
                    // تغییر به حالت کاربر موجود و انتخاب کاربر جدید
                    $('input[name="customer_type"][value="existing"]').prop('checked', true).trigger('change');
                    selectUser(response.user);
                    
                    // Clear new user form / پاک کردن فرم کاربر جدید
                    $('#aof-new-mobile').val('');
                    $('#aof-new-first-name').val('');
                    $('#aof-new-last-name').val('');
                    
                    showToast(response.message || tabeshAdminOrderForm.strings.userCreated, 'success');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : tabeshAdminOrderForm.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-plus"></span> ' +
                    tabeshAdminOrderForm.strings.createNewUser
                );
            }
        });
    }

    /**
     * Initialize form fields
     * راه‌اندازی فیلدهای فرم
     */
    function initFormFields() {
        // Update paper weight options when paper type changes
        // به‌روزرسانی گزینه‌های گرماژ کاغذ هنگام تغییر نوع کاغذ
        $('#aof-paper-type').on('change', function() {
            updatePaperWeights();
        });

        // Update page count fields based on print type
        // به‌روزرسانی فیلدهای تعداد صفحات بر اساس نوع چاپ
        $('#aof-print-type').on('change', function() {
            updatePageCountFields();
        });
        
        // Initialize page count fields on load / راه‌اندازی فیلدهای تعداد صفحات در بارگذاری
        updatePageCountFields();

        // Chip checkbox fallback for browsers without :has() support
        // فالبک چک‌باکس چیپ برای مرورگرهای بدون پشتیبانی :has()
        $('input[name="extras[]"]').on('change', function() {
            $(this).closest('.tabesh-aof-chip').toggleClass('chip-checked', $(this).is(':checked'));
        });

        // Override price checkbox / چک‌باکس قیمت دلخواه
        $('#aof-override-price-check').on('change', function() {
            if ($(this).is(':checked')) {
                $('#aof-override-price').prop('disabled', false);
            } else {
                $('#aof-override-price').prop('disabled', true).val('');
            }
            updateFinalPrice();
        });

        // Update final price when override changes
        // به‌روزرسانی قیمت نهایی هنگام تغییر قیمت دلخواه
        $('#aof-override-price').on('input', function() {
            updateFinalPrice();
        });
    }

    /**
     * Update paper weight options based on selected paper type
     * به‌روزرسانی گزینه‌های گرماژ کاغذ بر اساس نوع کاغذ انتخاب شده
     */
    function updatePaperWeights() {
        const paperType = $('#aof-paper-type').val();
        const $weightSelect = $('#aof-paper-weight');
        
        $weightSelect.empty().append('<option value="">' + 
            (paperType ? tabeshAdminOrderForm.strings.selectOption : tabeshAdminOrderForm.strings.selectPaperFirst) + 
        '</option>');
        
        if (paperType && tabeshAdminOrderForm.settings && 
            tabeshAdminOrderForm.settings.paperTypes && 
            tabeshAdminOrderForm.settings.paperTypes[paperType]) {
            const weights = tabeshAdminOrderForm.settings.paperTypes[paperType];
            weights.forEach(function(weight) {
                $weightSelect.append('<option value="' + weight + '">' + weight + '</option>');
            });
        }
    }

    /**
     * Update page count fields based on print type
     * به‌روزرسانی فیلدهای تعداد صفحات بر اساس نوع چاپ
     * 
     * Manages visibility and required attributes for page count fields
     * based on the selected print type
     * مدیریت نمایش و ویژگی‌های الزامی برای فیلدهای تعداد صفحات
     * بر اساس نوع چاپ انتخاب شده
     */
    function updatePageCountFields() {
        const printType = $('#aof-print-type').val();
        
        // Hide all first and remove required attributes / ابتدا همه را مخفی کن و الزامی بودن را بردار
        $('#aof-page-count-color-group').hide();
        $('#aof-page-count-bw-group').hide();
        $('#aof-page-count-total-group').hide();
        
        // Remove required from all page count inputs / حذف الزامی بودن از تمام ورودی‌های تعداد صفحات
        $('#aof-page-count-color').removeAttr('required');
        $('#aof-page-count-bw').removeAttr('required');
        $('#aof-page-count-total').removeAttr('required');
        
        if (printType === 'رنگی') {
            $('#aof-page-count-color-group').show();
            $('#aof-page-count-color').attr('required', 'required');
            $('#aof-page-count-bw').val(0);
        } else if (printType === 'سیاه و سفید') {
            $('#aof-page-count-bw-group').show();
            $('#aof-page-count-bw').attr('required', 'required');
            $('#aof-page-count-color').val(0);
        } else if (printType === 'ترکیبی') {
            $('#aof-page-count-color-group').show();
            $('#aof-page-count-bw-group').show();
            // For combined print, at least one field should have a value
            // This is validated in isFormValid() - not using HTML required here
            // برای چاپ ترکیبی، حداقل یکی از فیلدها باید مقدار داشته باشد
            // این در isFormValid() اعتبارسنجی می‌شود - اینجا از required استفاده نمی‌کنیم
        } else {
            $('#aof-page-count-total-group').show();
            $('#aof-page-count-total').attr('required', 'required');
        }
    }

    /**
     * Initialize price calculation
     * راه‌اندازی محاسبه قیمت
     */
    function initPriceCalculation() {
        // Calculate price button / دکمه محاسبه قیمت
        $('#aof-calculate-btn').on('click', function() {
            calculatePrice();
        });

        // Auto-calculate on field change (debounced)
        // محاسبه خودکار هنگام تغییر فیلد (با تأخیر)
        let calcTimeout = null;
        $('#tabesh-admin-order-form-main select, #tabesh-admin-order-form-main input[type="number"]').on('change', function() {
            clearTimeout(calcTimeout);
            calcTimeout = setTimeout(function() {
                if (isFormReadyForCalculation()) {
                    calculatePrice();
                }
            }, 500);
        });
    }

    /**
     * Check if form has enough data for price calculation
     * بررسی آماده بودن فرم برای محاسبه قیمت
     * 
     * @returns {boolean}
     */
    function isFormReadyForCalculation() {
        const bookSize = $('#aof-book-size').val();
        const paperType = $('#aof-paper-type').val();
        const quantity = parseInt($('#aof-quantity').val()) || 0;
        const bindingType = $('#aof-binding-type').val();
        
        return bookSize && paperType && quantity > 0 && bindingType;
    }

    /**
     * Calculate order price
     * محاسبه قیمت سفارش
     */
    function calculatePrice() {
        const formData = getFormData();
        
        // Validate required fields / اعتبارسنجی فیلدهای الزامی
        if (!formData.book_size || !formData.paper_type || !formData.quantity || !formData.binding_type) {
            return;
        }

        const $btn = $('#aof-calculate-btn');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> ' +
            tabeshAdminOrderForm.strings.calculating
        );

        $.ajax({
            url: tabeshAdminOrderForm.restUrl + '/calculate-price',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderForm.nonce);
            },
            success: function(response) {
                if (response.success && response.data) {
                    calculatedPrice = response.data.total_price;
                    displayCalculatedPrice(response.data);
                    updateFinalPrice();
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : tabeshAdminOrderForm.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-calculator"></span> ' +
                    tabeshAdminOrderForm.strings.calculatePrice
                );
            }
        });
    }

    /**
     * Display calculated price
     * نمایش قیمت محاسبه شده
     * 
     * @param {Object} data Price data / داده‌های قیمت
     */
    function displayCalculatedPrice(data) {
        const formatted = formatPrice(data.total_price);
        $('#aof-calculated-price').text(formatted);
    }

    /**
     * Update final price display
     * به‌روزرسانی نمایش قیمت نهایی
     */
    function updateFinalPrice() {
        let finalPrice = calculatedPrice;
        
        if ($('#aof-override-price-check').is(':checked')) {
            const override = parseFloat($('#aof-override-price').val());
            if (!isNaN(override) && override > 0) {
                finalPrice = override;
            }
        }
        
        if (finalPrice) {
            const formatted = formatPrice(finalPrice);
            $('#aof-final-price').text(formatted);
        } else {
            $('#aof-final-price').text('---');
        }
    }

    /**
     * Initialize form submission
     * راه‌اندازی ارسال فرم
     */
    function initFormSubmission() {
        $('#tabesh-admin-order-form-main').on('submit', function(e) {
            e.preventDefault();
            submitOrder();
        });
    }

    /**
     * Submit order
     * ارسال سفارش
     */
    function submitOrder() {
        // Get user ID / دریافت شناسه کاربر
        const userType = $('input[name="customer_type"]:checked').val();
        let userId = null;
        
        if (userType === 'existing') {
            userId = $('#aof-selected-user-id').val();
            if (!userId) {
                showToast(tabeshAdminOrderForm.strings.selectCustomer, 'error');
                return;
            }
        } else {
            showToast(tabeshAdminOrderForm.strings.createUserFirst, 'error');
            return;
        }

        // Validate form / اعتبارسنجی فرم
        if (!isFormValid()) {
            showToast(tabeshAdminOrderForm.strings.fillAllFields, 'error');
            return;
        }

        const formData = getFormData();
        formData.user_id = parseInt(userId);

        // Add override price if set / افزودن قیمت دلخواه در صورت تنظیم
        if ($('#aof-override-price-check').is(':checked')) {
            const override = parseFloat($('#aof-override-price').val());
            if (!isNaN(override) && override > 0) {
                formData.override_price = override;
            }
        }

        const $btn = $('#aof-submit-btn');
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> ' +
            tabeshAdminOrderForm.strings.submitting
        );

        $.ajax({
            url: tabeshAdminOrderForm.restUrl + '/admin/create-order',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderForm.nonce);
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.message || tabeshAdminOrderForm.strings.success, 'success');
                    // Reset form after successful submission
                    // بازنشانی فرم پس از ارسال موفق
                    setTimeout(function() {
                        resetForm();
                    }, 1500);
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : tabeshAdminOrderForm.strings.error;
                showToast(message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-yes-alt"></span> ' +
                    tabeshAdminOrderForm.strings.submitOrder
                );
            }
        });
    }

    /**
     * Check if form is valid
     * بررسی معتبر بودن فرم
     * 
     * @returns {boolean}
     */
    function isFormValid() {
        const required = [
            'aof-book-title',
            'aof-book-size',
            'aof-paper-type',
            'aof-paper-weight',
            'aof-print-type',
            'aof-quantity',
            'aof-binding-type',
            'aof-license-type'
        ];

        for (let field of required) {
            const value = $('#' + field).val();
            if (!value || value.trim() === '') {
                return false;
            }
        }

        // Validate page counts based on print type
        // اعتبارسنجی تعداد صفحات بر اساس نوع چاپ
        const printType = $('#aof-print-type').val();
        if (printType === 'رنگی') {
            const pageCountColor = parseInt($('#aof-page-count-color').val()) || 0;
            if (pageCountColor <= 0) {
                return false;
            }
        } else if (printType === 'سیاه و سفید') {
            const pageCountBw = parseInt($('#aof-page-count-bw').val()) || 0;
            if (pageCountBw <= 0) {
                return false;
            }
        } else if (printType === 'ترکیبی') {
            const pageCountColor = parseInt($('#aof-page-count-color').val()) || 0;
            const pageCountBw = parseInt($('#aof-page-count-bw').val()) || 0;
            if (pageCountColor <= 0 && pageCountBw <= 0) {
                return false;
            }
        } else {
            const total = parseInt($('#aof-page-count-total').val()) || 0;
            if (total <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get form data as object
     * دریافت داده‌های فرم به صورت شیء
     * 
     * @returns {Object}
     */
    function getFormData() {
        const printType = $('#aof-print-type').val();
        let pageCountColor = 0;
        let pageCountBw = 0;

        if (printType === 'رنگی') {
            pageCountColor = parseInt($('#aof-page-count-color').val()) || 0;
        } else if (printType === 'سیاه و سفید') {
            pageCountBw = parseInt($('#aof-page-count-bw').val()) || 0;
        } else if (printType === 'ترکیبی') {
            pageCountColor = parseInt($('#aof-page-count-color').val()) || 0;
            pageCountBw = parseInt($('#aof-page-count-bw').val()) || 0;
        } else {
            // Total page count / تعداد کل صفحات
            const total = parseInt($('#aof-page-count-total').val()) || 0;
            pageCountBw = total;
        }

        // Get extras / دریافت آپشن‌های اضافی
        const extras = [];
        $('input[name="extras[]"]:checked').each(function() {
            extras.push($(this).val());
        });

        // Get default cover paper weight from settings
        // دریافت گرماژ پیش‌فرض کاغذ جلد از تنظیمات
        const defaultCoverWeight = (tabeshAdminOrderForm.settings.coverPaperWeights && 
            tabeshAdminOrderForm.settings.coverPaperWeights.length > 0) 
            ? tabeshAdminOrderForm.settings.coverPaperWeights[0] 
            : '250';
        
        // Get default lamination type from settings
        // دریافت نوع سلفون پیش‌فرض از تنظیمات
        const defaultLamination = (tabeshAdminOrderForm.settings.laminationTypes && 
            tabeshAdminOrderForm.settings.laminationTypes.length > 0) 
            ? tabeshAdminOrderForm.settings.laminationTypes[0] 
            : '';

        return {
            book_title: $('#aof-book-title').val().trim(),
            book_size: $('#aof-book-size').val(),
            paper_type: $('#aof-paper-type').val(),
            paper_weight: $('#aof-paper-weight').val(),
            print_type: $('#aof-print-type').val(),
            page_count_color: pageCountColor,
            page_count_bw: pageCountBw,
            quantity: parseInt($('#aof-quantity').val()) || 0,
            binding_type: $('#aof-binding-type').val(),
            license_type: $('#aof-license-type').val(),
            cover_paper_weight: $('#aof-cover-paper-weight').val() || defaultCoverWeight,
            lamination_type: $('#aof-lamination-type').val() || defaultLamination,
            extras: extras,
            notes: $('#aof-notes').val().trim()
        };
    }

    /**
     * Reset form to initial state
     * بازنشانی فرم به حالت اولیه
     */
    function resetForm() {
        $('#tabesh-admin-order-form-main')[0].reset();
        $('#aof-user-search-results').empty();
        $('#aof-selected-user-display').empty();
        $('#aof-selected-user-id').val('');
        $('#aof-calculated-price').text('---');
        $('#aof-final-price').text('---');
        calculatedPrice = null;
        selectedUserId = null;
        
        // Reset customer selection / بازنشانی انتخاب مشتری
        $('input[name="customer_type"][value="existing"]').prop('checked', true).trigger('change');
        
        // Reset page count visibility / بازنشانی نمایش تعداد صفحات
        $('#aof-page-count-color-group').hide();
        $('#aof-page-count-bw-group').hide();
        $('#aof-page-count-total-group').show();
        
        // Reset paper weight / بازنشانی گرماژ کاغذ
        $('#aof-paper-weight').empty().append('<option value="">' + tabeshAdminOrderForm.strings.selectPaperFirst + '</option>');
    }

    /**
     * Format price with thousands separator
     * فرمت‌بندی قیمت با جداکننده هزارگان
     * 
     * @param {number} price Price value / مقدار قیمت
     * @returns {string} Formatted price / قیمت فرمت‌شده
     */
    function formatPrice(price) {
        return new Intl.NumberFormat('fa-IR').format(price);
    }

    /**
     * Escape HTML special characters
     * فرار از کاراکترهای خاص HTML
     * 
     * @param {string} text Text to escape / متن برای فرار
     * @returns {string} Escaped text / متن فرار شده
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Show toast notification
     * نمایش اعلان توست
     * 
     * @param {string} message Message to show / پیام برای نمایش
     * @param {string} type Message type (error|success) / نوع پیام
     */
    function showToast(message, type) {
        type = type || 'error';
        
        let $container = $('#tabesh-aof-toast-container');
        if ($container.length === 0) {
            $('body').append('<div id="tabesh-aof-toast-container" class="tabesh-aof-toast-container"></div>');
            $container = $('#tabesh-aof-toast-container');
        }
        
        const $toast = $(
            '<div class="tabesh-aof-toast tabesh-aof-toast-' + type + '">' +
            '<span class="toast-message">' + escapeHtml(message) + '</span>' +
            '<button type="button" class="tabesh-aof-toast-close">&times;</button>' +
            '</div>'
        );
        
        $container.append($toast);
        
        // Auto-close after 4 seconds / بستن خودکار پس از ۴ ثانیه
        setTimeout(function() {
            $toast.addClass('fade-out');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 4000);
        
        // Close button / دکمه بستن
        $toast.find('.tabesh-aof-toast-close').on('click', function() {
            $toast.addClass('fade-out');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        });
    }

})(jQuery);
