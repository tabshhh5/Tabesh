/**
 * Tabesh Staff Panel - Printing Substatus JavaScript
 * Handles interactive printing workflow management
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

    // Printing Substatus Controller
    const PrintingSubstatus = {
        /**
         * Initialize printing substatus functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle substatus section
            $(document).on('click', '.toggle-substatus-btn, .printing-substatus-header', this.toggleSubstatusSection.bind(this));
            
            // Prevent checkbox toggle when clicking the button itself
            $(document).on('click', '.toggle-substatus-btn', function(e) {
                e.stopPropagation();
            });
            
            // Checkbox changes
            $(document).on('change', '.substatus-checkbox', this.handleCheckboxChange.bind(this));
            
            // Prevent card collapse when interacting with substatus section
            $(document).on('click', '.printing-substatus-section', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Toggle substatus section visibility
         */
        toggleSubstatusSection: function(e) {
            const $header = $(e.currentTarget).closest('.printing-substatus-header');
            const $section = $header.closest('.printing-substatus-section');
            const $content = $section.find('.printing-substatus-content');
            const $button = $header.find('.toggle-substatus-btn');
            
            const isExpanded = $button.attr('aria-expanded') === 'true';
            
            if (isExpanded) {
                $content.slideUp(300);
                $button.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(300);
                $button.attr('aria-expanded', 'true');
            }
        },

        /**
         * Handle checkbox state change
         */
        handleCheckboxChange: function(e) {
            const $checkbox = $(e.currentTarget);
            const $item = $checkbox.closest('.substatus-item');
            const $section = $checkbox.closest('.printing-substatus-section');
            const orderId = $section.data('order-id');
            const substatusKey = $checkbox.data('substatus');
            const value = $checkbox.is(':checked') ? 1 : 0;
            
            // Mark as updating
            $item.addClass('updating');
            $checkbox.prop('disabled', true);
            
            // Prepare data
            const data = {
                order_id: orderId,
                substatus_key: substatusKey,
                value: value
            };
            
            // Handle additional services differently
            if (substatusKey === 'additional_service') {
                data.service_name = $checkbox.data('service-name');
            }
            
            // Send AJAX request
            this.updateSubstatus(data)
                .done((response) => {
                    if (response.success) {
                        // Update progress bar
                        this.updateProgressBar($section, response.percentage);
                        
                        // Show completion notice if completed
                        if (response.completed && !$section.find('.completion-notice').length) {
                            this.showCompletionNotice($section);
                            
                            // Reload page after a delay to show updated status
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                        
                        // Show success toast
                        this.showToast('success', response.message || 'وضعیت به‌روزرسانی شد');
                    } else {
                        // Revert checkbox on error
                        $checkbox.prop('checked', !value);
                        this.showToast('error', response.message || 'خطا در به‌روزرسانی');
                    }
                })
                .fail((xhr) => {
                    // Revert checkbox on error
                    $checkbox.prop('checked', !value);
                    const message = xhr.responseJSON?.message || 'خطا در ارتباط با سرور';
                    this.showToast('error', message);
                })
                .always(() => {
                    // Remove updating state
                    $item.removeClass('updating');
                    $checkbox.prop('disabled', false);
                });
        },

        /**
         * Send substatus update to server
         */
        updateSubstatus: function(data) {
            const restUrl = buildRestUrl(window.tabeshStaffData.rest_url, 'printing-substatus/update');
            
            return $.ajax({
                url: restUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', window.tabeshStaffData.nonce);
                }
            });
        },

        /**
         * Update progress bar
         */
        updateProgressBar: function($section, percentage) {
            const $progressBar = $section.find('.progress-bar');
            const $progressPercentage = $section.find('.progress-percentage');
            
            // Animate progress bar
            $progressBar.css('width', percentage + '%');
            
            // Animate percentage text
            const currentPercentage = parseInt($progressPercentage.text()) || 0;
            this.animateNumber($progressPercentage, currentPercentage, percentage, 500);
        },

        /**
         * Animate number change
         */
        animateNumber: function($element, from, to, duration) {
            const steps = 20;
            const increment = (to - from) / steps;
            const stepDuration = duration / steps;
            let current = from;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= to) || (increment < 0 && current <= to)) {
                    current = to;
                    clearInterval(timer);
                }
                $element.text(Math.round(current) + '%');
            }, stepDuration);
        },

        /**
         * Show completion notice
         */
        showCompletionNotice: function($section) {
            const now = new Date();
            const timeString = now.toLocaleDateString('fa-IR') + ' - ' + now.toLocaleTimeString('fa-IR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const notice = `
                <div class="completion-notice">
                    <span class="completion-icon">✅</span>
                    <span class="completion-text">تکمیل شده در ${timeString}</span>
                </div>
            `;
            
            $section.find('.printing-substatus-content').append(notice);
        },

        /**
         * Show toast notification
         */
        showToast: function(type, message) {
            // Create toast element
            const $toast = $('<div>', {
                class: 'tabesh-toast tabesh-toast-' + type,
                text: message
            });
            
            // Add to DOM
            if (!$('.tabesh-toast-container').length) {
                $('body').append('<div class="tabesh-toast-container"></div>');
            }
            
            $('.tabesh-toast-container').append($toast);
            
            // Animate in
            setTimeout(() => {
                $toast.addClass('show');
            }, 10);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize if we're on the staff panel page
        if ($('.tabesh-staff-panel').length) {
            PrintingSubstatus.init();
        }
    });

    // Expose to global scope if needed
    window.TabeshPrintingSubstatus = PrintingSubstatus;

})(jQuery);
