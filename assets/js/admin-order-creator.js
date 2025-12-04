/**
 * Admin Order Creator JavaScript
 * 
 * Handles modal functionality, user search, and order submission
 * 
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Global variables
    let userSearchTimeout = null;
    let calculatedPrice = null;
    let selectedUserId = null;
    
    // Animation duration constant (matches CSS animation duration)
    const MODAL_ANIMATION_DURATION = 300;

    $(document).ready(function() {
        initModal();
        initUserSelection();
        initPriceCalculation();
        initFormSubmission();
    });

    /**
     * Initialize modal
     */
    function initModal() {
        // Open modal button
        $(document).on('click', '#tabesh-open-order-modal', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $modal = $('#tabesh-order-modal');
            var $content = $modal.find('.tabesh-modal-content');
            var $overlay = $modal.find('.tabesh-modal-overlay');
            
            // Apply critical inline styles to modal container
            $modal.css({
                'display': 'flex',
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'right': '0',
                'bottom': '0',
                'width': '100vw',
                'height': '100vh',
                'margin': '0',
                'padding': '20px',
                'z-index': '999999',
                'align-items': 'center',
                'justify-content': 'center',
                'background': 'transparent',
                'direction': 'rtl',
                'box-sizing': 'border-box'
            }).addClass('tabesh-modal-open');
            
            // Apply critical inline styles to overlay
            $overlay.css({
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'right': '0',
                'bottom': '0',
                'width': '100vw',
                'height': '100vh',
                'background': 'rgba(0, 0, 0, 0.7)',
                'backdrop-filter': 'blur(10px)',
                '-webkit-backdrop-filter': 'blur(10px)',
                'z-index': '1'
            });
            
            // Apply critical inline styles to modal content
            $content.css({
                'position': 'relative',
                'z-index': '2',
                'background': '#ffffff',
                'border-radius': '16px',
                'box-shadow': '0 30px 60px rgba(0, 0, 0, 0.3)',
                'width': '95%',
                'max-width': '1400px',
                'max-height': 'calc(100vh - 40px)',
                'margin': '0 auto',
                'display': 'flex',
                'flex-direction': 'column',
                'overflow': 'hidden',
                'transform': 'none',
                'flex-shrink': '0'
            });
            
            $('body').addClass('modal-open');
        });

        // Close modal - multiple selectors
        $(document).on('click', '.tabesh-modal-close, #cancel-order-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        });
        
        // Close on overlay click
        $(document).on('click', '.tabesh-modal-overlay', function(e) {
            e.preventDefault();
            closeModal();
        });

        // Prevent modal content clicks from closing
        $(document).on('click', '.tabesh-modal-content', function(e) {
            e.stopPropagation();
        });

        // ESC key to close
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#tabesh-order-modal').hasClass('tabesh-modal-open')) {
                closeModal();
            }
        });
    }

    /**
     * Close modal and reset form
     */
    function closeModal() {
        var $modal = $('#tabesh-order-modal');
        
        // Remove inline styles and class
        $modal.removeAttr('style').removeClass('tabesh-modal-open');
        $modal.find('.tabesh-modal-content').removeAttr('style');
        $modal.find('.tabesh-modal-overlay').removeAttr('style');
        
        $('body').removeClass('modal-open');
        
        // Optional: Reset form after short delay for animation
        setTimeout(function() {
            resetForm();
        }, MODAL_ANIMATION_DURATION);
    }

    /**
     * Reset form to initial state
     */
    function resetForm() {
        $('#tabesh-admin-order-form')[0].reset();
        $('#user-search-results').empty();
        $('#selected-user-display').empty();
        $('#selected-user-id').val('');
        $('#calculated-price-value').text('-');
        $('#final-price-value').text('-');
        calculatedPrice = null;
        selectedUserId = null;
        
        // Reset user selection
        $('input[name="user_selection_type"][value="existing"]').prop('checked', true).trigger('change');
    }

    /**
     * Initialize user selection functionality
     */
    function initUserSelection() {
        // Toggle between existing and new user
        $('input[name="user_selection_type"]').on('change', function() {
            const type = $(this).val();
            
            if (type === 'existing') {
                $('#existing-user-section').show();
                $('#new-user-section').hide();
            } else {
                $('#existing-user-section').hide();
                $('#new-user-section').show();
                // Clear existing user selection
                selectedUserId = null;
                $('#selected-user-id').val('');
                $('#selected-user-display').empty();
            }
        });

        // Live search for users
        $('#user-search').on('input', function() {
            const search = $(this).val().trim();
            
            clearTimeout(userSearchTimeout);
            
            if (search.length < 2) {
                $('#user-search-results').empty();
                return;
            }

            userSearchTimeout = setTimeout(function() {
                searchUsers(search);
            }, 300);
        });

        // Create new user button
        $('#create-user-btn').on('click', function() {
            createNewUser();
        });

        // Dynamically manage required attributes based on print type selection
        // This prevents browser validation errors for hidden fields with required attribute
        $('#print_type').on('change', function() {
            const printType = $(this).val();
            
            // Reset all page count fields
            $('#page_count_color, #page_count_bw, #page_count_total').val('').prop('required', false);
            
            if (printType === 'رنگی') {
                $('#page-count-color-group').show();
                $('#page-count-bw-group').hide();
                $('#page-count-total-group').hide();
                $('#page_count_color').prop('required', true).attr('min', '1');
            } else if (printType === 'سیاه و سفید') {
                $('#page-count-color-group').hide();
                $('#page-count-bw-group').show();
                $('#page-count-total-group').hide();
                $('#page_count_bw').prop('required', true).attr('min', '1');
            } else if (printType === 'ترکیبی') {
                $('#page-count-color-group').show();
                $('#page-count-bw-group').show();
                $('#page-count-total-group').hide();
                $('#page_count_color').prop('required', true).attr('min', '1');
                $('#page_count_bw').prop('required', true).attr('min', '1');
            } else {
                // Default - show total
                $('#page-count-color-group').hide();
                $('#page-count-bw-group').hide();
                $('#page-count-total-group').show();
                $('#page_count_total').prop('required', true).attr('min', '1');
            }
        });

        // Update paper weight options when paper type changes
        $('#paper_type').on('change', function() {
            updatePaperWeights();
        });

        // Override price checkbox
        $('#override-price-check').on('change', function() {
            if ($(this).is(':checked')) {
                $('#override_price').prop('disabled', false);
                updateFinalPrice();
            } else {
                $('#override_price').prop('disabled', true).val('');
                updateFinalPrice();
            }
        });

        // Update final price when override changes
        $('#override_price').on('input', function() {
            updateFinalPrice();
        });
    }

    /**
     * Search users via API
     */
    function searchUsers(search) {
        $.ajax({
            url: tabeshAdminOrderCreator.restUrl + '/admin/search-users-live',
            method: 'GET',
            data: { search: search },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderCreator.nonce);
                $('#user-search-results').html('<div class="searching">در حال جستجو...</div>');
            },
            success: function(response) {
                if (response.success && response.users && response.users.length > 0) {
                    displayUserResults(response.users);
                } else {
                    $('#user-search-results').html('<div class="no-results">' + tabeshAdminOrderCreator.strings.noResults + '</div>');
                }
            },
            error: function() {
                $('#user-search-results').html('<div class="error">خطا در جستجو</div>');
            }
        });
    }

    /**
     * Display user search results
     */
    function displayUserResults(users) {
        const $results = $('#user-search-results');
        $results.empty();

        users.forEach(function(user) {
            const displayName = user.display_name || (user.first_name + ' ' + user.last_name);
            const $item = $('<div class="user-result-item"></div>');
            $item.html(
                '<div class="user-name">' + displayName + '</div>' +
                '<div class="user-login">' + user.user_login + '</div>'
            );
            
            $item.on('click', function() {
                selectUser(user);
            });
            
            $results.append($item);
        });
    }

    /**
     * Select a user from search results
     */
    function selectUser(user) {
        selectedUserId = user.id;
        $('#selected-user-id').val(user.id);
        
        const displayName = user.display_name || (user.first_name + ' ' + user.last_name);
        $('#selected-user-display').html(
            '<div class="selected-user">' +
            '<strong>' + displayName + '</strong> (' + user.user_login + ')' +
            '<button type="button" class="remove-user">&times;</button>' +
            '</div>'
        );
        
        $('#user-search').val('');
        $('#user-search-results').empty();
    }

    /**
     * Remove selected user
     */
    $(document).on('click', '.remove-user', function() {
        selectedUserId = null;
        $('#selected-user-id').val('');
        $('#selected-user-display').empty();
    });

    /**
     * Create new user
     */
    function createNewUser() {
        const mobile = $('#new-user-mobile').val().trim();
        const firstName = $('#new-user-first-name').val().trim();
        const lastName = $('#new-user-last-name').val().trim();

        // Validate
        if (!mobile || !firstName || !lastName) {
            alert('لطفاً تمام فیلدها را پر کنید');
            return;
        }

        if (!/^09[0-9]{9}$/.test(mobile)) {
            alert('فرمت شماره موبایل نامعتبر است');
            return;
        }

        const $btn = $('#create-user-btn');
        $btn.prop('disabled', true).text('در حال ایجاد...');

        $.ajax({
            url: tabeshAdminOrderCreator.restUrl + '/admin/create-user',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                mobile: mobile,
                first_name: firstName,
                last_name: lastName
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderCreator.nonce);
            },
            success: function(response) {
                if (response.success && response.user) {
                    // Switch to existing user mode and select the new user
                    $('input[name="user_selection_type"][value="existing"]').prop('checked', true).trigger('change');
                    selectUser(response.user);
                    
                    // Clear new user form
                    $('#new-user-mobile').val('');
                    $('#new-user-first-name').val('');
                    $('#new-user-last-name').val('');
                    
                    alert(response.message || 'کاربر با موفقیت ایجاد شد');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : 'خطا در ایجاد کاربر';
                alert(message);
            },
            complete: function() {
                $btn.prop('disabled', false).text('ایجاد کاربر');
            }
        });
    }

    /**
     * Update paper weight options based on selected paper type
     */
    function updatePaperWeights() {
        const paperType = $('#paper_type').val();
        const $weightSelect = $('#paper_weight');
        
        $weightSelect.empty().append('<option value="">انتخاب کنید...</option>');
        
        if (paperType && tabeshAdminOrderCreator.settings && 
            tabeshAdminOrderCreator.settings.paperTypes && 
            tabeshAdminOrderCreator.settings.paperTypes[paperType]) {
            const weights = tabeshAdminOrderCreator.settings.paperTypes[paperType];
            weights.forEach(function(weight) {
                $weightSelect.append('<option value="' + weight + '">' + weight + '</option>');
            });
        }
    }

    /**
     * Initialize price calculation
     */
    function initPriceCalculation() {
        // Calculate price button
        $('#calculate-price-btn').on('click', function() {
            calculatePrice();
        });

        // Auto-calculate on field change (debounced)
        let calcTimeout = null;
        $('#tabesh-admin-order-form select, #tabesh-admin-order-form input[type="number"]').on('change', function() {
            clearTimeout(calcTimeout);
            calcTimeout = setTimeout(function() {
                if (isFormValid()) {
                    calculatePrice();
                }
            }, 500);
        });
    }

    /**
     * Calculate order price
     */
    function calculatePrice() {
        const formData = getFormData();
        
        // Validate required fields
        if (!formData.book_size || !formData.paper_type || !formData.quantity || !formData.binding_type) {
            return;
        }

        const $btn = $('#calculate-price-btn');
        $btn.prop('disabled', true).text(tabeshAdminOrderCreator.strings.calculating);

        $.ajax({
            url: tabeshAdminOrderCreator.restUrl + '/calculate-price',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderCreator.nonce);
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
                    : 'خطا در محاسبه قیمت';
                alert(message);
            },
            complete: function() {
                $btn.prop('disabled', false).text('محاسبه قیمت');
            }
        });
    }

    /**
     * Display calculated price
     */
    function displayCalculatedPrice(data) {
        const formatted = new Intl.NumberFormat('fa-IR').format(data.total_price);
        $('#calculated-price-value').html('<strong>' + formatted + '</strong> ریال');
    }

    /**
     * Update final price display
     */
    function updateFinalPrice() {
        let finalPrice = calculatedPrice;
        
        if ($('#override-price-check').is(':checked')) {
            const override = parseFloat($('#override_price').val());
            if (!isNaN(override) && override > 0) {
                finalPrice = override;
            }
        }
        
        if (finalPrice) {
            const formatted = new Intl.NumberFormat('fa-IR').format(finalPrice);
            $('#final-price-value').html('<strong>' + formatted + '</strong> ریال');
        } else {
            $('#final-price-value').text('-');
        }
    }

    /**
     * Initialize form submission
     */
    function initFormSubmission() {
        $('#tabesh-admin-order-form').on('submit', function(e) {
            e.preventDefault();
            submitOrder();
        });
    }

    /**
     * Submit order
     */
    function submitOrder() {
        // Prevent native form validation issues by removing required from hidden fields
        $('#tabesh-admin-order-form').find('input, select, textarea').each(function() {
            if ($(this).closest('.tabesh-form-group').is(':hidden')) {
                $(this).prop('required', false);
            }
        });
        
        // Get user ID
        const userType = $('input[name="user_selection_type"]:checked').val();
        let userId = null;
        
        if (userType === 'existing') {
            userId = $('#selected-user-id').val();
            if (!userId) {
                alert('لطفاً یک کاربر را انتخاب کنید');
                return;
            }
        } else {
            alert('لطفاً ابتدا کاربر جدید را ایجاد کنید');
            return;
        }

        // Validate form
        if (!isFormValid()) {
            return;
        }

        const formData = getFormData();
        formData.user_id = parseInt(userId);

        // Add override price if set
        if ($('#override-price-check').is(':checked')) {
            const override = parseFloat($('#override_price').val());
            if (!isNaN(override) && override > 0) {
                formData.override_price = override;
            }
        }

        const $btn = $('#submit-order-btn');
        $btn.prop('disabled', true).text(tabeshAdminOrderCreator.strings.submitting);

        $.ajax({
            url: tabeshAdminOrderCreator.restUrl + '/admin/create-order',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', tabeshAdminOrderCreator.nonce);
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message || tabeshAdminOrderCreator.strings.success);
                    closeModal();
                    // Reload page to show new order
                    location.reload();
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : tabeshAdminOrderCreator.strings.error;
                alert(message);
            },
            complete: function() {
                $btn.prop('disabled', false).text('ثبت سفارش');
            }
        });
    }

    /**
     * Check if form has all required fields
     */
    function isFormValid() {
        const printType = $('#print_type').val();
        
        // Check base required fields
        const baseRequired = ['book_title', 'book_size', 'paper_type', 'paper_weight', 'print_type', 'quantity', 'binding_type', 'license_type'];
        
        for (let field of baseRequired) {
            const value = $('#' + field).val();
            if (!value || value.trim() === '') {
                return false;
            }
        }
        
        // Check page count based on print type
        if (printType === 'رنگی') {
            const colorPages = parseInt($('#page_count_color').val()) || 0;
            if (colorPages < 1) {
                alert('لطفاً تعداد صفحات رنگی را وارد کنید (حداقل 1 صفحه)');
                return false;
            }
        } else if (printType === 'سیاه و سفید') {
            const bwPages = parseInt($('#page_count_bw').val()) || 0;
            if (bwPages < 1) {
                alert('لطفاً تعداد صفحات سیاه و سفید را وارد کنید (حداقل 1 صفحه)');
                return false;
            }
        } else if (printType === 'ترکیبی') {
            const colorPages = parseInt($('#page_count_color').val()) || 0;
            const bwPages = parseInt($('#page_count_bw').val()) || 0;
            if (colorPages < 1 && bwPages < 1) {
                alert('لطفاً حداقل یکی از تعداد صفحات رنگی یا سیاه و سفید را وارد کنید (حداقل 1 صفحه)');
                return false;
            }
        } else {
            const totalPages = parseInt($('#page_count_total').val()) || 0;
            if (totalPages < 1) {
                alert('لطفاً تعداد کل صفحات را وارد کنید (حداقل 1 صفحه)');
                return false;
            }
        }

        return true;
    }

    /**
     * Get form data as object
     */
    function getFormData() {
        const printType = $('#print_type').val();
        let pageCountColor = 0;
        let pageCountBw = 0;

        if (printType === 'رنگی') {
            pageCountColor = parseInt($('#page_count_color').val()) || 0;
        } else if (printType === 'سیاه و سفید') {
            pageCountBw = parseInt($('#page_count_bw').val()) || 0;
        } else if (printType === 'ترکیبی') {
            pageCountColor = parseInt($('#page_count_color').val()) || 0;
            pageCountBw = parseInt($('#page_count_bw').val()) || 0;
        } else {
            // Total page count
            const total = parseInt($('#page_count_total').val()) || 0;
            pageCountBw = total;
        }

        // Get extras
        const extras = [];
        $('input[name="extras[]"]:checked').each(function() {
            extras.push($(this).val());
        });

        return {
            book_title: $('#book_title').val().trim(),
            book_size: $('#book_size').val(),
            paper_type: $('#paper_type').val(),
            paper_weight: $('#paper_weight').val(),
            print_type: $('#print_type').val(),
            page_count_color: pageCountColor,
            page_count_bw: pageCountBw,
            quantity: parseInt($('#quantity').val()) || 0,
            binding_type: $('#binding_type').val(),
            license_type: $('#license_type').val(),
            cover_paper_weight: $('#cover_paper_weight').val() || '250',
            lamination_type: $('#lamination_type').val() || 'براق',
            extras: extras,
            notes: $('#notes').val().trim()
        };
    }

})(jQuery);
