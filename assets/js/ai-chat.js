/**
 * AI Chat Interface JavaScript
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Chat state
    let isOpen = false;
    let isTyping = false;

    // Initialize chat
    function initChat() {
        const toggle = $('#tabesh-ai-toggle');
        const container = $('.tabesh-ai-chat-container');
        const minimize = $('.tabesh-ai-minimize');
        const form = $('#tabesh-ai-chat-form');
        const input = $('#tabesh-ai-input');
        const messages = $('#tabesh-ai-messages');

        // Toggle chat
        toggle.on('click', function() {
            toggleChat();
        });

        minimize.on('click', function() {
            toggleChat();
        });

        // Handle form submission
        form.on('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Auto-resize textarea
        input.on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Handle Enter key
        input.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Handle suggestions
        $('.tabesh-ai-suggestion').on('click', function() {
            const message = $(this).data('message');
            input.val(message);
            sendMessage();
        });

        // Read form data from page
        readFormData();
    }

    // Toggle chat visibility
    function toggleChat() {
        isOpen = !isOpen;
        const container = $('.tabesh-ai-chat-container');
        
        if (isOpen) {
            container.addClass('active');
            $('#tabesh-ai-input').focus();
        } else {
            container.removeClass('active');
        }
    }

    // Send message to AI
    function sendMessage() {
        const input = $('#tabesh-ai-input');
        const message = input.val().trim();

        if (!message || isTyping) {
            return;
        }

        // Add user message to chat
        addMessage(message, 'user');

        // Clear input
        input.val('').css('height', 'auto');

        // Show typing indicator
        showTypingIndicator();

        // Get form context
        const context = getFormContext();

        // Send to API
        $.ajax({
            url: tabeshAI.ajaxUrl + '/chat',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAI.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                message: message,
                context: context
            }),
            success: function(response) {
                hideTypingIndicator();
                
                if (response.success && response.message) {
                    addMessage(response.message, 'bot');
                } else {
                    addMessage(tabeshAI.strings.errorMessage, 'bot');
                }
            },
            error: function(xhr) {
                hideTypingIndicator();
                let errorMsg = tabeshAI.strings.errorMessage;
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                addMessage(errorMsg, 'bot');
            }
        });
    }

    // Add message to chat
    function addMessage(text, type) {
        const messages = $('#tabesh-ai-messages');
        const time = new Date().toLocaleTimeString('fa-IR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });

        const messageHtml = `
            <div class="tabesh-ai-message tabesh-ai-message-${type}">
                <div class="tabesh-ai-message-content">
                    <p>${escapeHtml(text)}</p>
                </div>
                <div class="tabesh-ai-message-time">${time}</div>
            </div>
        `;

        messages.append(messageHtml);
        scrollToBottom();
    }

    // Show typing indicator
    function showTypingIndicator() {
        isTyping = true;
        const messages = $('#tabesh-ai-messages');
        const indicator = $('.tabesh-ai-typing-indicator');
        
        if (indicator.length) {
            indicator.show();
            scrollToBottom();
        }
    }

    // Hide typing indicator
    function hideTypingIndicator() {
        isTyping = false;
        $('.tabesh-ai-typing-indicator').hide();
    }

    // Scroll to bottom of messages
    function scrollToBottom() {
        const messages = $('#tabesh-ai-messages');
        messages.animate({
            scrollTop: messages[0].scrollHeight
        }, 300);
    }

    // Get form context
    function getFormContext() {
        const context = {
            form_data: {}
        };

        // Try to read from order form
        const bookTitle = $('#book_title').val();
        const bookSize = $('#book_size').val() || $('select[name="book_size"]').val();
        const paperType = $('#paper_type').val() || $('select[name="paper_type"]').val();
        const paperWeight = $('#paper_weight').val() || $('select[name="paper_weight"]').val();
        const printType = $('#print_type').val() || $('select[name="print_type"]').val();
        const pageCount = $('#page_count').val() || $('#page_count_total').val();
        const quantity = $('#quantity').val();
        const bindingType = $('#binding_type').val() || $('select[name="binding_type"]').val();

        if (bookTitle) context.form_data.book_title = bookTitle;
        if (bookSize) context.form_data.book_size = bookSize;
        if (paperType) context.form_data.paper_type = paperType;
        if (paperWeight) context.form_data.paper_weight = paperWeight;
        if (printType) context.form_data.print_type = printType;
        if (pageCount) context.form_data.page_count = pageCount;
        if (quantity) context.form_data.quantity = quantity;
        if (bindingType) context.form_data.binding_type = bindingType;

        return context;
    }

    // Read form data on page load
    function readFormData() {
        // Listen for form changes
        $(document).on('change', 'select, input', function() {
            // Update context when form changes
        });
    }

    // Escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.tabesh-ai-chat-container').length) {
            initChat();
        }
    });

})(jQuery);
