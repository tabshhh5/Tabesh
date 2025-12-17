/**
 * Tabesh Frontend JavaScript
 */

(function($) {
    'use strict';

    // Add global error handler to prevent external JS errors from breaking our functionality
    window.addEventListener('error', function(event) {
        // Check if error is from noUiSlider (common external library that may not have proper element checks)
        if (event.message && event.message.indexOf('noUiSlider') !== -1) {
            console.warn('Tabesh: Caught external noUiSlider error, preventing page crash:', event.message);
            // Prevent the error from breaking the rest of the page JavaScript
            event.preventDefault();
            return true;
        }
    }, true);

    // Helper function to safely construct REST URLs without double slashes
    function buildRestUrl(base, endpoint) {
        const cleanBase = base.replace(/\/+$/, ''); // Remove trailing slashes
        const cleanEndpoint = endpoint.replace(/^\/+/, ''); // Remove leading slashes
        return cleanBase + '/' + cleanEndpoint;
    }

    // Defensive fallback for getOption if it doesn't exist
    if (typeof getOption !== 'function') {
        window.getOption = function(key, defaultValue) {
            return defaultValue;
        };
    }

    // Order Form Handler
    class TabeshOrderForm {
        constructor() {
            this.currentStep = 1;
            this.totalSteps = 12;
            this.formData = {};
            this.paperTypes = {};
            
            this.init();
        }

        init() {
            console.log('Tabesh: Initializing TabeshOrderForm');
            this.cacheDom();
            
            // Verify form exists
            if (!this.$form.length) {
                console.error('Tabesh: Order form not found!');
                return;
            }
            
            console.log('Tabesh: Form found, binding events');
            this.bindEvents();
            this.loadPaperTypes();
            console.log('Tabesh: Initialization complete');
        }

        cacheDom() {
            this.$form = $('#tabesh-order-form');
            this.$steps = $('.tabesh-form-step');
            this.$prevBtn = $('#prev-btn');
            this.$nextBtn = $('#next-btn');
            this.$calculateBtn = $('#calculate-btn');
            this.$progressBar = $('.tabesh-progress-bar');
            this.$priceResult = $('#tabesh-price-result');
            this.$editBtn = $('#edit-order-btn');
            this.$submitBtn = $('#submit-order-btn');
        }

        bindEvents() {
            this.$nextBtn.on('click', () => this.nextStep());
            this.$prevBtn.on('click', () => this.prevStep());
            this.$calculateBtn.on('click', () => this.calculatePrice());
            this.$editBtn.on('click', () => this.editOrder());
            this.$submitBtn.on('click', () => this.submitOrder());
            
            // Paper type change
            this.$form.find('#paper_type').on('change', (e) => this.updatePaperWeights(e.target.value));
            
            // Book size change - update all parameters if V2 is enabled, otherwise just quantity constraints
            this.$form.find('#book_size').on('change', (e) => {
                const bookSize = e.target.value;
                if (tabeshData.v2Enabled) {
                    this.updateFormParametersForBookSize(bookSize);
                } else {
                    this.updateQuantityConstraints(bookSize);
                }
            });
            
            // License type change
            this.$form.find('#license_type').on('change', (e) => this.toggleLicenseUpload(e.target.value));
            
            // Quantity auto-correct
            this.$form.find('#quantity').on('change', (e) => this.correctQuantity(e));
        }

        /**
         * Update form parameters when book size changes (V2 only)
         * This dynamically loads paper types, binding types, and extras from the selected book size's pricing matrix
         */
        updateFormParametersForBookSize(bookSize) {
            if (!tabeshData.v2Enabled || !tabeshData.v2PricingMatrices || !tabeshData.v2PricingMatrices[bookSize]) {
                console.warn('Tabesh: V2 pricing matrix not found for book size:', bookSize);
                return;
            }

            const matrix = tabeshData.v2PricingMatrices[bookSize];
            
            // Update paper types
            const $paperTypeSelect = this.$form.find('#paper_type');
            const currentPaperType = $paperTypeSelect.val();
            $paperTypeSelect.empty().append('<option value="">انتخاب کنید...</option>');
            
            if (matrix.paper_types) {
                Object.keys(matrix.paper_types).forEach(paperType => {
                    $paperTypeSelect.append(`<option value="${paperType}">${paperType}</option>`);
                });
                
                // Try to restore previous selection if it exists in new matrix
                if (currentPaperType && matrix.paper_types[currentPaperType]) {
                    $paperTypeSelect.val(currentPaperType);
                    // Update weights for this paper type
                    this.updatePaperWeightsV2(currentPaperType, bookSize);
                } else {
                    // Clear paper weight select
                    this.$form.find('#paper_weight').empty().append('<option value="">ابتدا نوع کاغذ را انتخاب کنید</option>');
                }
            }
            
            // Update binding types
            const $bindingTypeSelect = this.$form.find('#binding_type');
            const currentBindingType = $bindingTypeSelect.val();
            $bindingTypeSelect.empty().append('<option value="">انتخاب کنید...</option>');
            
            if (matrix.binding_types && matrix.binding_types.length > 0) {
                matrix.binding_types.forEach(bindingType => {
                    $bindingTypeSelect.append(`<option value="${bindingType}">${bindingType}</option>`);
                });
                
                // Try to restore previous selection
                if (currentBindingType && matrix.binding_types.includes(currentBindingType)) {
                    $bindingTypeSelect.val(currentBindingType);
                }
            }
            
            // Update extras (checkboxes)
            const $extrasContainer = this.$form.find('#extras_container');
            if ($extrasContainer.length && matrix.extras && matrix.extras.length > 0) {
                // Get currently checked extras
                const checkedExtras = [];
                this.$form.find('input[name="extras[]"]:checked').each(function() {
                    checkedExtras.push($(this).val());
                });
                
                // Rebuild extras checkboxes
                $extrasContainer.empty();
                matrix.extras.forEach(extra => {
                    const isChecked = checkedExtras.includes(extra) ? 'checked' : '';
                    $extrasContainer.append(`
                        <label>
                            <input type="checkbox" name="extras[]" value="${extra}" ${isChecked}>
                            ${extra}
                        </label>
                    `);
                });
            }
            
            // Update quantity constraints
            this.updateQuantityConstraints(bookSize);
        }

        /**
         * Update paper weights for V2 (based on book size and paper type)
         */
        updatePaperWeightsV2(paperType, bookSize) {
            const $weightSelect = this.$form.find('#paper_weight');
            $weightSelect.empty();
            
            if (!tabeshData.v2PricingMatrices || !tabeshData.v2PricingMatrices[bookSize]) {
                return;
            }
            
            const matrix = tabeshData.v2PricingMatrices[bookSize];
            
            if (matrix.paper_types && matrix.paper_types[paperType]) {
                const weights = matrix.paper_types[paperType];
                weights.forEach(weight => {
                    $weightSelect.append(`<option value="${weight}">${weight}g</option>`);
                });
            }
        }

        loadPaperTypes() {
            // This should be populated from PHP
            // For now, we'll handle it dynamically
            const paperTypeSelect = this.$form.find('#paper_type');
            paperTypeSelect.on('change', () => {
                // Paper weights will be updated via updatePaperWeights or updatePaperWeightsV2
            });
        }

        updatePaperWeights(paperType) {
            const $weightSelect = this.$form.find('#paper_weight');
            $weightSelect.empty();
            
            // For V2, use book-size-specific weights
            if (tabeshData.v2Enabled) {
                const bookSize = this.$form.find('#book_size').val();
                if (bookSize) {
                    this.updatePaperWeightsV2(paperType, bookSize);
                    return;
                }
            }
            
            // Fallback to V1 method
            const paperTypes = tabeshData.paperTypes || {};

            if (paperTypes[paperType]) {
                paperTypes[paperType].forEach(weight => {
                    $weightSelect.append(`<option value="${weight}">${weight}g</option>`);
                });
            }
        }

        toggleLicenseUpload(licenseType) {
            const $uploadDiv = this.$form.find('#license_upload');
            if (licenseType === 'دارم') {
                $uploadDiv.show();
            } else {
                $uploadDiv.hide();
            }
        }

        correctQuantity(e) {
            const $input = $(e.target);
            const bookSize = this.$form.find('#book_size').val();
            
            // Check if V2 is enabled and has constraints for this book size
            if (tabeshData.v2Enabled && tabeshData.quantityConstraints && tabeshData.quantityConstraints[bookSize]) {
                const constraints = tabeshData.quantityConstraints[bookSize];
                const min = constraints.minimum_quantity || parseInt($input.attr('min'));
                const max = constraints.maximum_quantity || parseInt($input.attr('max'));
                const step = constraints.quantity_step || parseInt($input.attr('step'));
                let value = parseInt($input.val());

                // Check minimum
                if (value < min) {
                    value = min;
                }
                
                // Check maximum
                if (max > 0 && value > max) {
                    value = max;
                }
                
                // Check step
                if ((value - min) % step !== 0) {
                    value = min + Math.floor((value - min) / step) * step;
                }

                $input.val(value);
            } else {
                // Fallback to HTML attributes (legacy/V1)
                const min = parseInt($input.attr('min'));
                const step = parseInt($input.attr('step'));
                let value = parseInt($input.val());

                if (value < min) {
                    value = min;
                } else if ((value - min) % step !== 0) {
                    value = min + Math.floor((value - min) / step) * step;
                }

                $input.val(value);
            }
        }

        updateQuantityConstraints(bookSize) {
            const $quantityInput = this.$form.find('#quantity');
            
            // Check if V2 is enabled and has constraints for this book size
            if (tabeshData.v2Enabled && tabeshData.quantityConstraints && tabeshData.quantityConstraints[bookSize]) {
                const constraints = tabeshData.quantityConstraints[bookSize];
                
                // Update HTML attributes
                $quantityInput.attr('min', constraints.minimum_quantity);
                $quantityInput.attr('max', constraints.maximum_quantity);
                $quantityInput.attr('step', constraints.quantity_step);
                
                // Update value if current value is out of bounds
                const currentValue = parseInt($quantityInput.val()) || constraints.minimum_quantity;
                if (currentValue < constraints.minimum_quantity) {
                    $quantityInput.val(constraints.minimum_quantity);
                } else if (constraints.maximum_quantity > 0 && currentValue > constraints.maximum_quantity) {
                    $quantityInput.val(constraints.maximum_quantity);
                }
                
                // Update label to show constraints
                const $label = $quantityInput.closest('.tabesh-form-group').find('label');
                if ($label.length) {
                    const labelText = 'تعداد (حداقل ' + constraints.minimum_quantity + '، حداکثر ' + constraints.maximum_quantity + ')';
                    $label.text(labelText);
                }
            }
        }

        nextStep() {
            if (this.validateStep(this.currentStep)) {
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                    this.updateStep();
                }
            }
        }

        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.updateStep();
            }
        }

        updateStep() {
            this.$steps.removeClass('active');
            this.$steps.filter(`[data-step="${this.currentStep}"]`).addClass('active');

            // Update buttons
            if (this.currentStep === 1) {
                this.$prevBtn.hide();
            } else {
                this.$prevBtn.show();
            }

            if (this.currentStep === this.totalSteps) {
                this.$nextBtn.hide();
                this.$calculateBtn.show();
            } else {
                this.$nextBtn.show();
                this.$calculateBtn.hide();
            }

            // Update progress bar
            const progress = (this.currentStep / this.totalSteps) * 100;
            this.$progressBar.css('width', progress + '%');
        }

        validateStep(step) {
            const $currentStep = this.$steps.filter(`[data-step="${step}"]`);
            const $inputs = $currentStep.find('input[required], select[required]');
            
            let isValid = true;
            $inputs.each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).css('border-color', '#e74c3c');
                    setTimeout(() => {
                        $(this).css('border-color', '');
                    }, 2000);
                }
            });

            if (!isValid) {
                this.showNotification('لطفا تمام فیلدهای الزامی را پر کنید', 'error');
            }

            return isValid;
        }

        collectFormData() {
            // Helper function to safely get value from an element
            const safeVal = (selector, defaultValue = '') => {
                try {
                    const $el = this.$form.find(selector);
                    if ($el.length === 0) {
                        console.warn('Tabesh: Element not found:', selector);
                        return defaultValue;
                    }
                    const val = $el.val();
                    return (val !== null && val !== undefined) ? val : defaultValue;
                } catch (e) {
                    console.error('Tabesh: Error getting value for', selector, e);
                    return defaultValue;
                }
            };

            this.formData = {
                book_title: safeVal('#book_title', ''),
                book_size: safeVal('#book_size', ''),
                paper_type: safeVal('#paper_type', ''),
                paper_weight: safeVal('#paper_weight', ''),
                print_type: safeVal('#print_type', ''),
                page_count_bw: parseInt(safeVal('#page_count_bw', '0')) || 0,
                page_count_color: parseInt(safeVal('#page_count_color', '0')) || 0,
                quantity: parseInt(safeVal('#quantity', '0')) || 0,
                binding_type: safeVal('#binding_type', ''),
                license_type: safeVal('#license_type', ''),
                cover_paper_weight: safeVal('#cover_paper_weight', '250'),
                lamination_type: safeVal('#lamination_type', 'براق'),
                extras: [],
                notes: safeVal('#notes', '')
            };

            // Collect extras - with defensive checks
            try {
                const extrasCheckboxes = this.$form.find('input[name="extras[]"]:checked');
                console.log('Tabesh: Found ' + extrasCheckboxes.length + ' checked extras');
                
                extrasCheckboxes.each((index, element) => {
                    try {
                        // Use direct property access instead of jQuery .val() to avoid toLowerCase errors
                        const value = element.value || element.getAttribute('value') || '';
                        // Ensure value is a non-empty string
                        if (value && typeof value === 'string' && value.trim() !== '' && value !== 'on') {
                            console.log('Tabesh: Adding extra:', value);
                            this.formData.extras.push(value.trim());
                        } else {
                            console.warn('Tabesh: Skipping invalid extra value:', value);
                        }
                    } catch (e) {
                        console.error('Tabesh: Error processing extra checkbox:', e);
                    }
                });
                
                console.log('Tabesh: Total extras collected:', this.formData.extras.length, this.formData.extras);
            } catch (e) {
                console.error('Tabesh: Error collecting extras:', e);
                this.formData.extras = [];
            }

            return this.formData;
        }

        calculatePrice() {
            try {
                this.collectFormData();
                
                console.log('Tabesh: Calculating price with data:', this.formData);

                // Validate total pages
                const totalPages = this.formData.page_count_bw + this.formData.page_count_color;
                if (totalPages === 0) {
                    this.showNotification('لطفا حداقل تعداد یک صفحه را وارد کنید', 'error');
                    return;
                }

                // Validate required fields
                if (!this.formData.book_size || !this.formData.paper_type || !this.formData.quantity) {
                    this.showNotification('لطفا تمام فیلدهای الزامی را پر کنید', 'error');
                    return;
                }

                // Show loading
                this.$calculateBtn.prop('disabled', true).html('<span class="tabesh-loading"></span> در حال محاسبه...');
                
                // Construct URL safely to avoid double slashes
                const requestUrl = buildRestUrl(tabeshData.restUrl, 'calculate-price');
                
                console.log('Tabesh: Sending AJAX request to:', requestUrl);
                console.log('Tabesh: Request data:', JSON.stringify(this.formData));
                console.log('Tabesh: Extras in request:', this.formData.extras);

                // Call API
                $.ajax({
                    url: requestUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', tabeshData.nonce);
                    },
                    data: JSON.stringify(this.formData),
                    success: (response) => {
                        console.log('Tabesh: Received response:', response);
                        if (response.success) {
                            console.log('Tabesh: Calculation successful, displaying price');
                            this.displayPrice(response.data);
                        } else {
                            console.error('Tabesh calculation error:', response);
                            this.showNotification(response.message || 'خطا در محاسبه قیمت', 'error');
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Tabesh AJAX error:', {xhr, status, error});
                        console.error('Response text:', xhr.responseText);
                        let errorMessage = 'خطا در برقراری ارتباط با سرور';
                        try {
                            const errorData = JSON.parse(xhr.responseText);
                            if (errorData.message) {
                                errorMessage = errorData.message;
                            }
                        } catch (e) {
                            // Unable to parse error, use default message
                        }
                        this.showNotification(errorMessage, 'error');
                    },
                    complete: () => {
                        this.$calculateBtn.prop('disabled', false).text('محاسبه قیمت');
                    }
                });
            } catch (e) {
                console.error('Tabesh: Exception in calculatePrice:', e);
                this.showNotification('خطای غیرمنتظره در محاسبه قیمت', 'error');
                this.$calculateBtn.prop('disabled', false).text('محاسبه قیمت');
            }
        }

        displayPrice(data) {
            console.log('Tabesh: Displaying price with data:', data);
            
            this.$priceResult.find('#price-per-book').text(this.formatPrice(data.price_per_book));
            this.$priceResult.find('#price-quantity').text(data.quantity + ' عدد');
            this.$priceResult.find('#price-subtotal').text(this.formatPrice(data.subtotal));
            this.$priceResult.find('#price-total').text(this.formatPrice(data.total_price));

            // Display extras cost breakdown if any extras were selected
            if (data.breakdown && data.breakdown.options_cost > 0) {
                console.log('Tabesh: Options cost detected:', data.breakdown.options_cost);
                const $extrasRow = this.$priceResult.find('#extras-row');
                if ($extrasRow.length > 0) {
                    $extrasRow.show();
                    this.$priceResult.find('#price-extras').text(this.formatPrice(data.breakdown.options_cost));
                } else {
                    console.warn('Tabesh: Extras row element not found in template');
                }
                
                // Log extras breakdown for debugging
                if (data.breakdown.options_breakdown) {
                    console.log('Tabesh: Extras breakdown:', data.breakdown.options_breakdown);
                    
                    // Display detailed breakdown in console for transparency
                    for (const [extra, cost] of Object.entries(data.breakdown.options_breakdown)) {
                        console.log(`  - ${extra}: ${this.formatPrice(cost)}`);
                    }
                }
            } else {
                console.log('Tabesh: No extras cost to display');
                const $extrasRow = this.$priceResult.find('#extras-row');
                if ($extrasRow.length > 0) {
                    $extrasRow.hide();
                }
            }

            if (data.discount_percent > 0) {
                this.$priceResult.find('#discount-row').show();
                this.$priceResult.find('#price-discount').text(data.discount_percent + '% (' + this.formatPrice(data.discount_amount) + ')');
            } else {
                this.$priceResult.find('#discount-row').hide();
            }

            console.log('Tabesh: Price display complete, showing results');
            this.$form.hide();
            this.$priceResult.fadeIn();
        }

        editOrder() {
            this.$priceResult.hide();
            this.$form.fadeIn();
        }

        submitOrder() {
            // Check for authentication - prefer TabeshSettings if available, fallback to tabeshData
            const settings = typeof TabeshSettings !== 'undefined' ? TabeshSettings : tabeshData;
            const nonce = settings.nonce || tabeshData.nonce;
            
            if (!nonce) {
                this.showNotification('لطفا ابتدا وارد حساب کاربری خود شوید', 'error');
                window.location.href = '/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href);
                return;
            }

            this.$submitBtn.prop('disabled', true).html('<span class="tabesh-loading"></span> در حال ثبت...');

            // Check if there are any files to upload
            const licenseFileInput = document.getElementById('license_file');
            const hasFiles = licenseFileInput && licenseFileInput.files && licenseFileInput.files.length > 0;

            // Get REST URL - prefer TabeshSettings if available
            const restUrl = (typeof TabeshSettings !== 'undefined' && TabeshSettings.rest_url) 
                ? TabeshSettings.rest_url 
                : tabeshData.restUrl;
            
            // Construct URL safely to avoid double slashes
            const requestUrl = buildRestUrl(restUrl, 'submit-order');

            let ajaxSettings = {
                url: requestUrl,
                method: 'POST',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                },
                success: (response) => {
                    console.log('Tabesh: Submit response:', response);
                    
                    // Check for success - handle both old format (response.success) and new format (response.data)
                    if (response.success || (response.data && response.data.order_id)) {
                        const orderId = response.data ? response.data.order_id : response.order_id;
                        console.log('Tabesh: Order submitted successfully, ID:', orderId);
                        
                        this.showNotification('سفارش با موفقیت ثبت شد', 'success');
                        setTimeout(() => {
                            window.location.href = '/my-account/orders/'; // Redirect to user orders
                        }, 2000);
                    } else {
                        console.error('Tabesh submit error:', response);
                        this.showNotification(response.message || 'خطا در ثبت سفارش', 'error');
                        this.$submitBtn.prop('disabled', false).text('ثبت سفارش');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Tabesh submit AJAX error:', {xhr, status, error});
                    console.error('Response text:', xhr.responseText);
                    console.error('Response status:', xhr.status);
                    
                    let errorMessage = 'خطا در برقراری ارتباط با سرور';
                    
                    // Try to parse JSON error response first
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.message) {
                            errorMessage = errorData.message;
                        } else if (errorData.code) {
                            errorMessage = `خطا: ${errorData.code}`;
                        }
                    } catch (e) {
                        // Failed to parse JSON, check status codes for better messages
                        console.warn('Tabesh: Could not parse error response as JSON');
                        
                        if (xhr.status === 400) {
                            errorMessage = 'خطا در پردازش درخواست. لطفاً دوباره تلاش کنید.';
                        } else if (xhr.status === 401 || xhr.status === 403) {
                            errorMessage = 'خطای احراز هویت. لطفاً مجدداً وارد شوید.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'خطای سرور. لطفاً با پشتیبانی تماس بگیرید.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'خطای اتصال. لطفاً اتصال اینترنت خود را بررسی کنید.';
                        }
                    }
                    
                    this.showNotification(errorMessage, 'error');
                    this.$submitBtn.prop('disabled', false).text('ثبت سفارش');
                }
            };

            if (hasFiles) {
                // Use FormData when files are present
                console.log('Tabesh: Submitting with files using FormData');
                const formData = new FormData();
                
                // Append all form data fields
                for (const [key, value] of Object.entries(this.formData)) {
                    if (Array.isArray(value)) {
                        // For arrays (like extras), append each item
                        value.forEach(item => {
                            formData.append(key + '[]', item);
                        });
                    } else {
                        formData.append(key, value);
                    }
                }
                
                // Append file
                formData.append('license_file', licenseFileInput.files[0]);
                
                // Set FormData specific settings - these override contentType
                ajaxSettings.data = formData;
                ajaxSettings.processData = false;
                ajaxSettings.contentType = false;
            } else {
                // Use JSON when no files
                console.log('Tabesh: Submitting without files using JSON');
                ajaxSettings.contentType = 'application/json; charset=utf-8';
                ajaxSettings.data = JSON.stringify(this.formData);
            }

            // Make the AJAX request
            $.ajax(ajaxSettings);
        }

        formatPrice(price) {
            return new Intl.NumberFormat('fa-IR').format(Math.round(price)) + ' تومان';
        }

        showNotification(message, type = 'info') {
            const $notification = $('<div>')
                .addClass('tabesh-notification ' + type)
                .text(message)
                .appendTo('body');

            setTimeout(() => {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            }, 3000);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        try {
            console.log('Tabesh: Document ready, jQuery version:', $.fn.jquery);
            console.log('Tabesh: Looking for order form...');
            
            if ($('#tabesh-order-form').length) {
                console.log('Tabesh: Order form found, creating TabeshOrderForm instance');
                new TabeshOrderForm();
            } else {
                console.log('Tabesh: Order form not found on this page');
            }
        } catch (error) {
            console.error('Tabesh: Error during initialization:', error);
            // Continue gracefully - don't let external errors break our functionality
        }
    });

})(jQuery);
