/**
 * Tabesh File Upload JavaScript
 *
 * Handles file upload, progress tracking, and validation
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Add global error handler to prevent external JS errors from breaking file upload functionality
    window.addEventListener('error', function(event) {
        // Check if error is from noUiSlider or other external libraries
        if (event.message && event.message.indexOf('noUiSlider') !== -1) {
            console.warn('Tabesh File Upload: Caught external noUiSlider error, preventing page crash:', event.message);
            // Prevent the error from breaking the rest of the page JavaScript
            event.preventDefault();
            return true;
        }
    }, true);

    // File upload handler
    var TabeshFileUpload = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // File input change
            $(document).on('change', '.tabesh-file-input', function() {
                self.handleFileSelect($(this));
            });
            
            // Explicit label click handler (fallback for browsers that don't support for/id)
            $(document).on('click', '.tabesh-file-label', function(e) {
                // Get the associated file input using the for attribute
                var inputId = $(this).attr('for');
                var $input = null;
                
                // If label has a 'for' attribute, let the browser handle it naturally
                if (inputId) {
                    $input = $('#' + inputId);
                    // If input found by ID, don't interfere - let native behavior work
                    if ($input.length > 0) {
                        return; // Let the browser's native label->input behavior work
                    }
                }
                
                // Fallback: if input not found by ID, find it in the same container and manually trigger
                $input = $(this).closest('.tabesh-upload-task, .tabesh-file-upload-area').find('.tabesh-file-input');
                
                if ($input.length > 0) {
                    // Trigger click on the file input
                    $input[0].click();
                    e.preventDefault();
                } else {
                    console.error('File input not found for label click');
                }
            });

            // Upload button click
            $(document).on('click', '.tabesh-upload-btn', function(e) {
                e.preventDefault(); // Prevent any default action
                
                var $btn = $(this);
                var category = $btn.data('category');
                
                console.log('Upload button clicked for category:', category);
                
                var fileInput = $('.tabesh-file-input[data-category="' + category + '"]');
                
                console.log('File input found:', fileInput.length);
                
                if (fileInput.length === 0) {
                    console.error('No file input found for category:', category);
                    return;
                }
                
                console.log('File input has files:', fileInput[0].files.length);
                
                self.uploadFile(fileInput);
            });

            // Reupload button click
            $(document).on('click', '.tabesh-reupload-btn', function() {
                var category = $(this).data('category');
                var fileInput = $('#file-' + category.replace('_', '-'));
                fileInput.click();
            });

            // View versions button
            $(document).on('click', '.tabesh-view-versions-btn', function() {
                var fileId = $(this).data('file-id');
                self.viewFileVersions(fileId);
            });
        },

        handleFileSelect: function($input) {
            // Validate input element
            if (!$input || $input.length === 0 || !$input[0]) {
                console.error('Invalid file input element');
                return;
            }
            
            var file = $input[0].files[0];
            
            if (!file) {
                console.log('No file selected');
                return;
            }

            console.log('File selected:', file.name, 'Size:', file.size);

            var $container = $input.closest('.tabesh-upload-task');
            var $fileInfo = $container.find('.tabesh-file-info');
            var $uploadBtn = $container.find('.tabesh-upload-btn');
            var $message = $container.find('.tabesh-upload-message');

            // Clear previous messages
            $message.html('').removeClass('success error warning');

            // Display file info
            var fileSize = this.formatFileSize(file.size);
            var fileName = file.name;
            
            $fileInfo.html(
                '<p><strong>' + fileName + '</strong></p>' +
                '<p>' + fileSize + '</p>'
            );

            // Enable upload button
            $uploadBtn.prop('disabled', false);

            // Validate file size
            var maxSize = this.getMaxFileSize(file.name);
            if (file.size > maxSize) {
                $message.html(
                    '<p class="error">حجم فایل بیش از حد مجاز است. حداکثر: ' + this.formatFileSize(maxSize) + '</p>'
                );
                $uploadBtn.prop('disabled', true);
            }
        },

        uploadFile: function($input) {
            var self = this;
            
            // Validate input element
            if (!$input || $input.length === 0) {
                console.error('File input element not found');
                return;
            }
            
            // Get file
            var file = $input[0].files[0];
            
            // Get container and UI elements first
            var $container = $input.closest('.tabesh-upload-task');
            var $progress = $container.find('.tabesh-upload-progress');
            var $progressBar = $progress.find('.progress-fill');
            var $progressText = $progress.find('.progress-text');
            var $message = $container.find('.tabesh-upload-message');
            var $uploadBtn = $container.find('.tabesh-upload-btn');
            
            // Validate file exists
            if (!file) {
                console.error('No file selected');
                $message.html('<p class="error">لطفاً ابتدا یک فایل انتخاب کنید.</p>').addClass('error');
                return;
            }

            var category = $input.data('category');
            var orderId = $input.data('order-id');

            // Disable upload button
            $uploadBtn.prop('disabled', true);

            // Show progress bar if enabled
            var showProgressBar = tabeshData.showProgressBar !== false;
            if (showProgressBar) {
                $progress.show();
                $progressBar.css('width', '0%');
                $progressText.text('0%');
            }

            // Clear previous messages
            $message.html('').removeClass('success error warning');

            // Create FormData
            var formData = new FormData();
            formData.append('file', file);
            formData.append('order_id', orderId);
            formData.append('file_category', category);
            formData.append('_wpnonce', tabeshData.nonce);

            console.log('Starting upload:', {
                filename: file.name,
                size: file.size,
                category: category,
                orderId: orderId,
                url: tabeshData.restUrl + '/upload-file'
            });

            // Upload file
            $.ajax({
                url: tabeshData.restUrl + '/upload-file',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': tabeshData.nonce
                },
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    
                    // Upload progress (only if progress bar is enabled)
                    if (showProgressBar) {
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                $progressBar.css('width', percentComplete + '%');
                                $progressText.text(percentComplete + '%');
                            }
                        }, false);
                    }
                    
                    return xhr;
                },
                success: function(response) {
                    console.log('Upload response received:', response);
                    
                    // Show scanning animation
                    self.showScanningAnimation(function() {
                        $progress.hide();
                        
                        if (response.success) {
                            $message.html('<p class="success">' + response.message + '</p>').addClass('success');
                            
                            // Reset file input
                            $input.val('');
                            $container.find('.tabesh-file-info').html('');
                            $uploadBtn.prop('disabled', true);
                            
                            // Validate uploaded file
                            if (response.file_id) {
                                self.validateFile(response.file_id, $container);
                            }
                            
                            // Reload files list
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $message.html('<p class="error">' + response.message + '</p>').addClass('error');
                            $uploadBtn.prop('disabled', false);
                        }
                    });
                },
                error: function(xhr, status, error) {
                    $progress.hide();
                    console.error('Upload error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    var errorMessage = 'خطا در آپلود فایل. لطفاً دوباره تلاش کنید.';
                    
                    // Try to parse error response
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            // Keep default error message
                        }
                    }
                    
                    // Use configured error display mode
                    self.displayMessage(errorMessage, 'error', $container);
                    $uploadBtn.prop('disabled', false);
                }
            });
        },

        validateFile: function(fileId, $container) {
            var self = this;
            var $message = $container.find('.tabesh-upload-message');

            $.ajax({
                url: tabeshData.restUrl + '/validate-file',
                type: 'POST',
                data: {
                    file_id: fileId
                },
                headers: {
                    'X-WP-Nonce': tabeshData.nonce
                },
                success: function(response) {
                    if (response.warnings && response.warnings.length > 0) {
                        var warningHtml = '<div class="validation-warnings"><h5>هشدارها:</h5><ul>';
                        response.warnings.forEach(function(warning) {
                            warningHtml += '<li>' + warning + '</li>';
                        });
                        warningHtml += '</ul></div>';
                        
                        $message.append(warningHtml).addClass('warning');
                    }
                    
                    if (response.errors && response.errors.length > 0) {
                        var errorHtml = '<div class="validation-errors"><h5>خطاها:</h5><ul>';
                        response.errors.forEach(function(error) {
                            errorHtml += '<li>' + error + '</li>';
                        });
                        errorHtml += '</ul></div>';
                        
                        $message.append(errorHtml).addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Validation error:', error);
                }
            });
        },

        showScanningAnimation: function(callback) {
            var $animation = $('#tabesh-upload-animation');
            
            $animation.fadeIn(300);
            
            setTimeout(function() {
                $animation.fadeOut(300, function() {
                    if (typeof callback === 'function') {
                        callback();
                    }
                });
            }, 2000);
        },

        viewFileVersions: function(fileId) {
            // TODO: Implement file versions modal
            console.log('View versions for file:', fileId);
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        getMaxFileSize: function(filename) {
            var ext = filename.split('.').pop().toLowerCase();
            
            if (ext === 'pdf') {
                return 52428800; // 50 MB
            } else if (['jpg', 'jpeg', 'png', 'psd'].indexOf(ext) !== -1) {
                return 10485760; // 10 MB
            } else if (['zip', 'rar'].indexOf(ext) !== -1) {
                return 104857600; // 100 MB
            } else {
                return 10485760; // 10 MB default
            }
        },

        /**
         * Display error message based on configured display mode
         *
         * @param {string} message Error message
         * @param {string} type Message type (error, warning, success)
         * @param {jQuery} $container Optional container for inline display
         */
        displayMessage: function(message, type, $container) {
            type = type || 'error';
            var displayMode = tabeshData.errorDisplayMode || 'inline';
            
            switch (displayMode) {
                case 'modal':
                    this.showModal(message, type);
                    break;
                    
                case 'toast':
                    this.showToast(message, type);
                    break;
                    
                case 'inline':
                default:
                    if ($container && $container.length > 0) {
                        var $message = $container.find('.tabesh-upload-message');
                        if ($message.length === 0) {
                            $message = $('<div class="tabesh-upload-message"></div>');
                            $container.prepend($message);
                        }
                        $message.html('<p class="' + type + '">' + message + '</p>')
                            .removeClass('error warning success')
                            .addClass(type);
                    } else {
                        // Graceful fallback: create a temporary message div at top of body
                        this.showFallbackMessage(message, type);
                    }
                    break;
            }
        },
        
        /**
         * Show fallback message (for when no container is available)
         */
        showFallbackMessage: function(message, type) {
            var messageClass = 'tabesh-fallback-message tabesh-fallback-' + type;
            var $fallback = $('<div class="' + messageClass + '">' + message + '<button class="fallback-close">×</button></div>');
            
            // Remove any existing fallback messages
            $('.tabesh-fallback-message').remove();
            
            $('body').prepend($fallback);
            
            // Slide in
            setTimeout(function() {
                $fallback.addClass('show');
            }, 10);
            
            // Auto close after 8 seconds
            setTimeout(function() {
                $fallback.removeClass('show');
                setTimeout(function() {
                    $fallback.remove();
                }, 300);
            }, 8000);
            
            // Manual close
            $fallback.find('.fallback-close').on('click', function() {
                $fallback.removeClass('show');
                setTimeout(function() {
                    $fallback.remove();
                }, 300);
            });
        },
        
        /**
         * Show modal dialog
         */
        showModal: function(message, type) {
            var modalClass = 'tabesh-modal-' + type;
            var iconClass = type === 'error' ? 'dashicons-warning' : 
                           type === 'warning' ? 'dashicons-info' : 
                           'dashicons-yes-alt';
            
            var $modal = $('<div class="tabesh-modal-overlay"><div class="tabesh-modal ' + modalClass + '">' +
                '<span class="dashicons ' + iconClass + '"></span>' +
                '<div class="modal-message">' + message + '</div>' +
                '<button class="modal-close-btn">بستن</button>' +
                '</div></div>');
            
            $('body').append($modal);
            
            // Fade in
            $modal.fadeIn(300);
            
            // Close button
            $modal.find('.modal-close-btn, .tabesh-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $modal.fadeOut(300, function() {
                        $modal.remove();
                    });
                }
            });
        },
        
        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            var toastClass = 'tabesh-toast-' + type;
            var iconClass = type === 'error' ? 'dashicons-warning' : 
                           type === 'warning' ? 'dashicons-info' : 
                           'dashicons-yes-alt';
            
            // Remove any existing toasts
            $('.tabesh-toast').remove();
            
            var $toast = $('<div class="tabesh-toast ' + toastClass + '">' +
                '<span class="dashicons ' + iconClass + '"></span>' +
                '<div class="toast-message">' + message + '</div>' +
                '<button class="toast-close">×</button>' +
                '</div>');
            
            $('body').append($toast);
            
            // Slide in from top
            setTimeout(function() {
                $toast.addClass('show');
            }, 10);
            
            // Auto close after 5 seconds
            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 5000);
            
            // Manual close
            $toast.find('.toast-close').on('click', function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        try {
            // Check if tabeshData is available
            if (typeof tabeshData === 'undefined') {
                console.error('Tabesh: tabeshData is not defined. File upload will not work.');
                return;
            }
            
            // Check required properties
            if (!tabeshData.restUrl || !tabeshData.nonce) {
                console.error('Tabesh: Required tabeshData properties are missing.', tabeshData);
                return;
            }
            
            TabeshFileUpload.init();
        } catch (error) {
            console.error('Tabesh File Upload: Error during initialization:', error);
            // Continue gracefully - don't let external errors break file upload functionality
        }
    });

    // Expose to global scope if needed
    window.TabeshFileUpload = TabeshFileUpload;

})(jQuery);
