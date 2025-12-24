/**
 * AI Browser Sidebar JavaScript
 *
 * Handles the main logic for the AI Browser sidebar interface.
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // State management
    let isOpen = false;
    let isTyping = false;
    let guestUUID = null;
    let conversationState = 'greeting'; // greeting, profession, show_target, chat
    let userProfession = null;

    /**
     * Initialize AI Browser
     */
    function initAIBrowser() {
        // Get or create guest UUID
        guestUUID = getOrCreateGuestUUID();

        // Set up event listeners
        setupEventListeners();

        // Show welcome message
        showWelcomeMessage();

        // Load user profile if available
        loadUserProfile();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Toggle button click
        $('#tabesh-ai-browser-toggle').on('click', function() {
            toggleSidebar();
        });

        // Close button click
        $('#tabesh-ai-browser-close').on('click', function() {
            closeSidebar();
        });

        // Overlay click (mobile)
        $('#tabesh-ai-browser-overlay').on('click', function() {
            closeSidebar();
        });

        // Form submission
        $('#tabesh-ai-browser-form').on('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Auto-resize textarea
        $('#tabesh-ai-browser-input').on('input', function() {
            autoResizeTextarea(this);
        });

        // Handle Enter key
        $('#tabesh-ai-browser-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Handle escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && isOpen) {
                closeSidebar();
            }
        });
    }

    /**
     * Toggle sidebar open/close
     */
    function toggleSidebar() {
        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    /**
     * Open sidebar
     */
    function openSidebar() {
        isOpen = true;
        $('#tabesh-ai-browser-sidebar').addClass('active');
        $('#tabesh-ai-browser-overlay').addClass('active');
        $('body').addClass('ai-browser-open');
        $('#tabesh-ai-browser-input').focus();

        // Track page view
        if (window.tabeshAITracker) {
            window.tabeshAITracker.trackEvent('sidebar_opened', {
                page_url: window.location.href,
                referrer: document.referrer
            });
        }
    }

    /**
     * Close sidebar
     */
    function closeSidebar() {
        isOpen = false;
        $('#tabesh-ai-browser-sidebar').removeClass('active');
        $('#tabesh-ai-browser-overlay').removeClass('active');
        $('body').removeClass('ai-browser-open');
    }

    /**
     * Show welcome message
     */
    function showWelcomeMessage() {
        const welcomeMessage = tabeshAIBrowser.strings.greeting;
        addMessage(welcomeMessage, 'bot');

        // After a short delay, show profession questions
        setTimeout(function() {
            showProfessionQuestions();
        }, 1000);
    }

    /**
     * Show profession questions
     */
    function showProfessionQuestions() {
        const questions = [
            { text: tabeshAIBrowser.strings.profession_buyer, value: 'buyer' },
            { text: tabeshAIBrowser.strings.profession_author, value: 'author' },
            { text: tabeshAIBrowser.strings.profession_publisher, value: 'publisher' },
            { text: tabeshAIBrowser.strings.profession_printer, value: 'printer' }
        ];

        const actionsHtml = questions.map(q => 
            `<button class="message-action-btn" data-profession="${q.value}">${q.text}</button>`
        ).join('');

        addMessage('', 'bot', actionsHtml);
        conversationState = 'profession';

        // Add event listeners to profession buttons
        $('.message-action-btn[data-profession]').on('click', function() {
            const profession = $(this).data('profession');
            handleProfessionSelection(profession);
        });
    }

    /**
     * Handle profession selection
     */
    function handleProfessionSelection(profession) {
        userProfession = profession;

        // Disable all profession buttons
        $('.message-action-btn[data-profession]').prop('disabled', true).css('opacity', '0.5');

        // Add user's selection as a message
        const professionText = $(`.message-action-btn[data-profession="${profession}"]`).text();
        addMessage(professionText, 'user');

        // Save profession to backend
        saveProfession(profession);

        // Show confirmation and offer to show target
        setTimeout(function() {
            addMessage(tabeshAIBrowser.strings.show_target, 'bot');
            showYesNoButtons();
        }, 500);

        conversationState = 'show_target';
    }

    /**
     * Show Yes/No buttons
     */
    function showYesNoButtons() {
        const actionsHtml = `
            <button class="message-action-btn" data-action="yes">${tabeshAIBrowser.strings.yes}</button>
            <button class="message-action-btn" data-action="no">${tabeshAIBrowser.strings.no}</button>
        `;

        addMessage('', 'bot', actionsHtml);

        // Add event listeners
        $('.message-action-btn[data-action="yes"]').on('click', function() {
            handleNavigationRequest(true);
        });

        $('.message-action-btn[data-action="no"]').on('click', function() {
            handleNavigationRequest(false);
        });
    }

    /**
     * Handle navigation request
     */
    function handleNavigationRequest(accepted) {
        // Disable buttons
        $('.message-action-btn[data-action]').prop('disabled', true).css('opacity', '0.5');

        if (accepted) {
            addMessage(tabeshAIBrowser.strings.yes, 'user');
            navigateToTarget();
        } else {
            addMessage(tabeshAIBrowser.strings.no, 'user');
            addMessage('باشه! اگر سوالی داشتید در خدمتم 😊', 'bot');
            conversationState = 'chat';
        }
    }

    /**
     * Navigate to target page based on profession
     */
    function navigateToTarget() {
        showTyping();

        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/browser/navigate',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                profession: userProfession,
                guest_uuid: guestUUID,
                context: {
                    page_url: window.location.href,
                    referrer: document.referrer
                }
            }),
            success: function(response) {
                hideTyping();
                
                if (response.success && response.target_url) {
                    addMessage(response.message, 'bot');
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = response.target_url;
                    }, 1000);
                } else {
                    addMessage('متأسفانه خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'bot');
                }
            },
            error: function() {
                hideTyping();
                addMessage(tabeshAIBrowser.strings.error, 'bot');
            }
        });
    }

    /**
     * Send user message
     */
    function sendMessage() {
        const $input = $('#tabesh-ai-browser-input');
        const message = $input.val().trim();

        if (!message || isTyping) {
            return;
        }

        // Add user message
        addMessage(message, 'user');

        // Clear input
        $input.val('');
        autoResizeTextarea($input[0]);

        // Show typing indicator
        showTyping();

        // Get context
        const context = getPageContext();

        // Send to AI
        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/chat',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                message: message,
                context: context
            }),
            success: function(response) {
                hideTyping();
                
                if (response.success && response.message) {
                    addMessage(response.message, 'bot');
                } else {
                    addMessage(tabeshAIBrowser.strings.error, 'bot');
                }
            },
            error: function(xhr) {
                hideTyping();
                let errorMsg = tabeshAIBrowser.strings.error;
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                addMessage(errorMsg, 'bot');
            }
        });
    }

    /**
     * Add message to chat
     */
    function addMessage(text, type, actionsHtml) {
        const $messages = $('#tabesh-ai-browser-messages');
        const time = new Date().toLocaleTimeString('fa-IR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });

        const messageHtml = `
            <div class="ai-message ${type}">
                ${text ? `<div class="message-bubble">${escapeHtml(text)}</div>` : ''}
                ${actionsHtml ? `<div class="message-actions">${actionsHtml}</div>` : ''}
                <div class="message-time">${time}</div>
            </div>
        `;

        $messages.append(messageHtml);
        scrollToBottom();
    }

    /**
     * Show typing indicator
     */
    function showTyping() {
        isTyping = true;
        $('.tabesh-ai-browser-typing').show();
        scrollToBottom();
    }

    /**
     * Hide typing indicator
     */
    function hideTyping() {
        isTyping = false;
        $('.tabesh-ai-browser-typing').hide();
    }

    /**
     * Scroll messages to bottom
     */
    function scrollToBottom() {
        const $messages = $('#tabesh-ai-browser-messages');
        $messages.animate({
            scrollTop: $messages[0].scrollHeight
        }, 300);
    }

    /**
     * Auto-resize textarea
     */
    function autoResizeTextarea(element) {
        element.style.height = 'auto';
        element.style.height = element.scrollHeight + 'px';
    }

    /**
     * Get page context for AI
     */
    function getPageContext() {
        const context = {
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            form_data: {},
            profession: userProfession
        };

        // Try to read form data
        const formSelectors = [
            '#book_title', '#book_size', '#paper_type', '#paper_weight',
            '#print_type', '#page_count', '#quantity', '#binding_type'
        ];

        formSelectors.forEach(function(selector) {
            const $element = $(selector);
            if ($element.length && $element.val()) {
                const key = selector.replace('#', '');
                context.form_data[key] = $element.val();
            }
        });

        return context;
    }

    /**
     * Save profession to backend
     */
    function saveProfession(profession) {
        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/browser/navigate',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                profession: profession,
                guest_uuid: guestUUID,
                context: {}
            })
        });
    }

    /**
     * Load user profile
     */
    function loadUserProfile() {
        const url = tabeshAIBrowser.ajaxUrl + '/ai/browser/profile' + 
                   (guestUUID ? '?guest_uuid=' + guestUUID : '');

        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            success: function(response) {
                if (response.success && response.profile) {
                    userProfession = response.profile.profession;
                }
            }
        });
    }

    /**
     * Get or create guest UUID
     */
    function getOrCreateGuestUUID() {
        // Check localStorage first
        let uuid = localStorage.getItem('tabesh_guest_uuid');

        if (!uuid) {
            // Generate new UUID
            uuid = generateUUID();
            localStorage.setItem('tabesh_guest_uuid', uuid);
        }

        return uuid;
    }

    /**
     * Generate UUID
     */
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Escape HTML
     */
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
        if ($('#tabesh-ai-browser-sidebar').length) {
            initAIBrowser();
        }
    });

    // Expose functions to global scope for other scripts
    window.tabeshAIBrowserAPI = {
        openSidebar: openSidebar,
        closeSidebar: closeSidebar,
        addMessage: addMessage,
        getGuestUUID: function() { return guestUUID; }
    };

})(jQuery);
