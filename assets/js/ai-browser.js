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
    let isMinimized = false;
    let isTyping = false;
    let guestUUID = null;
    let conversationState = 'greeting'; // greeting, profession, show_target, chat
    let userProfession = null;
    let userInterests = [];
    let interactionCount = 0;
    let idleTimeout = null;
    let lastInteractionTime = Date.now();

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

        // Minimize button click
        $('#tabesh-ai-browser-minimize').on('click', function(e) {
            e.stopPropagation();
            minimizeSidebar();
        });

        // Header click when minimized - restore
        $('.tabesh-ai-browser-header').on('click', function() {
            if (isMinimized) {
                restoreSidebar();
            }
        });

        // Overlay click (mobile only)
        $('#tabesh-ai-browser-overlay').on('click', function() {
            // Only close on mobile devices
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
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

        // Track user interactions for idle detection
        $(document).on('click scroll mousemove keypress', trackUserActivity);

        // Start idle detection timer
        startIdleDetection();
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
        $('#tabesh-ai-browser-sidebar').addClass('active').removeClass('minimized');
        $('#tabesh-ai-browser-overlay').addClass('active');
        $('body').addClass('ai-browser-open');
        $('#tabesh-ai-browser-input').focus();
        
        // Hide notification badge
        hideNotificationBadge();

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
        isMinimized = false;
        $('#tabesh-ai-browser-sidebar').removeClass('active minimized');
        $('#tabesh-ai-browser-overlay').removeClass('active');
        $('body').removeClass('ai-browser-open');
    }

    /**
     * Minimize sidebar
     */
    function minimizeSidebar() {
        isMinimized = true;
        $('#tabesh-ai-browser-sidebar').addClass('minimized');
        
        // Track minimization
        if (window.tabeshAITracker) {
            window.tabeshAITracker.trackEvent('sidebar_minimized', {
                page_url: window.location.href
            });
        }
    }

    /**
     * Restore minimized sidebar
     */
    function restoreSidebar() {
        isMinimized = false;
        $('#tabesh-ai-browser-sidebar').removeClass('minimized');
    }

    /**
     * Track user activity
     */
    function trackUserActivity() {
        lastInteractionTime = Date.now();
        interactionCount++;
        
        // Clear idle timeout
        if (idleTimeout) {
            clearTimeout(idleTimeout);
        }
        
        // Restart idle detection
        startIdleDetection();
    }

    /**
     * Start idle detection
     */
    function startIdleDetection() {
        // Clear existing timeout
        if (idleTimeout) {
            clearTimeout(idleTimeout);
        }
        
        // Set timeout for 30 seconds of inactivity
        idleTimeout = setTimeout(function() {
            handleIdleUser();
        }, 30000);
    }

    /**
     * Handle idle user - offer proactive help
     */
    function handleIdleUser() {
        // Only show if sidebar is not open and user has been on page for a while
        if (!isOpen && interactionCount > 5) {
            showNotificationBadge(1);
            
            // Track idle state
            if (window.tabeshAITracker) {
                window.tabeshAITracker.trackEvent('user_idle', {
                    page_url: window.location.href,
                    idle_duration: 30,
                    interaction_count: interactionCount
                });
            }
            
            // Check if user seems confused (many interactions but still on same page)
            if (interactionCount > 20) {
                // User seems stuck, offer highlighted tour
                setTimeout(function() {
                    offerProactiveHelp();
                }, 2000);
            }
        }
    }

    /**
     * Offer proactive help when user seems stuck
     */
    function offerProactiveHelp() {
        if (!isOpen) {
            openSidebar();
            
            // Add proactive message
            setTimeout(function() {
                const helpMessage = 'سلام! متوجه شدم که شاید کمک نیاز دارید. می‌تونم راهنماییتون کنم؟ 😊';
                addMessage(helpMessage, 'bot');
                
                // Show quick action buttons
                const actionsHtml = `
                    <button class="message-action-btn quick-help" data-action="tour">راهنمای صفحه</button>
                    <button class="message-action-btn quick-help" data-action="help">سوال دارم</button>
                `;
                addMessage('', 'bot', actionsHtml);
                
                // Attach event listeners
                $('.message-action-btn.quick-help[data-action="tour"]').on('click', function() {
                    startPageTour();
                });
                
                $('.message-action-btn.quick-help[data-action="help"]').on('click', function() {
                    conversationState = 'chat';
                    addMessage('البته! چه کمکی می‌تونم بکنم؟', 'bot');
                });
            }, 500);
        }
    }

    /**
     * Start contextual page tour
     */
    function startPageTour() {
        // Detect current page and start appropriate tour
        const currentUrl = window.location.href;
        
        if (currentUrl.includes('order-form') || $('#book_title').length) {
            if (window.tabeshAITourGuide) {
                window.tabeshAITourGuide.startTour('order-form');
            }
            addMessage('راهنمای تکمیل فرم سفارش را برایتان آغاز می‌کنم!', 'bot');
        } else if (currentUrl.includes('cart') || $('.woocommerce-cart').length) {
            if (window.tabeshAITourGuide) {
                window.tabeshAITourGuide.highlightElement('.woocommerce-cart', {
                    pulse: true,
                    tooltip: 'سبد خرید شما'
                });
            }
            addMessage('سبد خرید شما را برایتان نشان دادم!', 'bot');
        } else {
            addMessage('در این صفحه راهنمای ویژه‌ای ندارم. سوالی دارید؟', 'bot');
        }
        
        closeSidebar();
    }

    /**
     * Show notification badge
     */
    function showNotificationBadge(count) {
        const $badge = $('.tabesh-ai-browser-notification-badge');
        if (count > 0) {
            $badge.text(count).fadeIn();
        } else {
            $badge.fadeOut();
        }
    }

    /**
     * Hide notification badge
     */
    function hideNotificationBadge() {
        showNotificationBadge(0);
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

        // Ask follow-up questions based on profession
        setTimeout(function() {
            askProfessionFollowUp(profession);
        }, 500);

        conversationState = 'interests';
    }

    /**
     * Ask follow-up questions based on profession
     */
    function askProfessionFollowUp(profession) {
        let followUpMessage = '';
        let interestButtons = [];

        switch(profession) {
            case 'buyer':
                followUpMessage = 'عالی! چه نوع کتابی می‌خواهید چاپ کنید؟';
                interestButtons = [
                    { text: 'رمان و داستان', value: 'fiction' },
                    { text: 'کتاب درسی', value: 'textbook' },
                    { text: 'کتاب کودک', value: 'children' },
                    { text: 'سایر', value: 'other' }
                ];
                break;
            case 'author':
                followUpMessage = 'خوشحالم از آشناییتون! چه موضوعی می‌نویسید؟';
                interestButtons = [
                    { text: 'ادبیات و شعر', value: 'literature' },
                    { text: 'علمی و آموزشی', value: 'educational' },
                    { text: 'کودک و نوجوان', value: 'youth' },
                    { text: 'سایر', value: 'other' }
                ];
                break;
            case 'publisher':
                followUpMessage = 'خوش آمدید! چه تعداد عنوان در سال منتشر می‌کنید؟';
                interestButtons = [
                    { text: 'کمتر از 10 عنوان', value: 'small' },
                    { text: '10 تا 50 عنوان', value: 'medium' },
                    { text: 'بیشتر از 50 عنوان', value: 'large' },
                    { text: 'تازه شروع کرده‌ام', value: 'startup' }
                ];
                break;
            case 'printer':
                followUpMessage = 'سلام! چه نوع خدماتی ارائه می‌دهید؟';
                interestButtons = [
                    { text: 'چاپ افست', value: 'offset' },
                    { text: 'چاپ دیجیتال', value: 'digital' },
                    { text: 'صحافی', value: 'binding' },
                    { text: 'همه موارد', value: 'all' }
                ];
                break;
        }

        addMessage(followUpMessage, 'bot');

        // Add interest buttons
        const actionsHtml = interestButtons.map(btn => 
            `<button class="message-action-btn interest-btn" data-interest="${btn.value}">${btn.text}</button>`
        ).join('');

        addMessage('', 'bot', actionsHtml);

        // Add event listeners
        $('.message-action-btn.interest-btn').on('click', function() {
            const interest = $(this).data('interest');
            handleInterestSelection(interest);
        });
    }

    /**
     * Handle interest selection
     */
    function handleInterestSelection(interest) {
        userInterests.push(interest);

        // Disable buttons
        $('.message-action-btn.interest-btn').prop('disabled', true).css('opacity', '0.5');

        // Add user's selection
        const interestText = $(`.message-action-btn.interest-btn[data-interest="${interest}"]`).text();
        addMessage(interestText, 'user');

        // Save interest and ask if they want navigation
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
     * Navigation Intent Detection
     */
    const navigationIntents = {
        'سفارش': 'order_form',
        'ثبت سفارش': 'order_form',
        'میخوام سفارش': 'order_form',
        'میخواهم سفارش': 'order_form',
        'چاپ کتاب': 'order_form',
        'چاپ': 'order_form',
        'قیمت': 'pricing',
        'تماس': 'contact',
        'راهنما': 'help',
        'کمک': 'help',
        'سبد خرید': 'cart',
        'حساب کاربری': 'account',
        'حساب': 'account'
    };

    /**
     * Detect navigation intent from user message
     */
    function detectNavigationIntent(message) {
        const lowerMessage = message.toLowerCase();
        
        for (const [keyword, intentType] of Object.entries(navigationIntents)) {
            if (lowerMessage.includes(keyword)) {
                return {
                    detected: true,
                    keyword: keyword,
                    intentType: intentType
                };
            }
        }
        
        return { detected: false };
    }

    /**
     * Smart search for pages using the AI indexer
     */
    function smartSearchPages(query, callback) {
        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/browser/search-pages',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                query: query,
                limit: 1
            }),
            success: function(response) {
                if (response.success && response.results && response.results.length > 0) {
                    callback(response.results[0]);
                } else {
                    callback(null);
                }
            },
            error: function() {
                callback(null);
            }
        });
    }

    /**
     * Get target URL for navigation intent with smart search fallback
     */
    function getTargetUrl(intentType, keyword, callback) {
        // Map intent types to search queries
        const searchQueries = {
            order_form: 'سفارش چاپ کتاب',
            pricing: 'قیمت تعرفه',
            contact: 'تماس با ما',
            help: 'راهنما کمک',
            cart: 'سبد خرید',
            account: 'حساب کاربری'
        };

        const searchQuery = searchQueries[intentType] || keyword;

        // Try smart search first
        smartSearchPages(searchQuery, function(page) {
            if (page && page.page_url) {
                callback(page.page_url);
            } else {
                // Fallback to hardcoded routes
                const routes = window.tabeshAIRoutes || {
                    order_form: '/order-form/',
                    pricing: '/pricing/',
                    contact: '/contact/',
                    help: '/help/',
                    cart: '/cart/',
                    account: '/my-account/'
                };
                callback(routes[intentType] || null);
            }
        });
    }

    /**
     * Show navigation offer to user
     */
    function showNavigationOffer(intentType, keyword) {
        // Use smart search to get target URL
        getTargetUrl(intentType, keyword, function(targetUrl) {
            if (!targetUrl) {
                return;
            }
            
            // Ensure URL is properly formed (add home URL if relative)
            if (targetUrl.startsWith('/')) {
                targetUrl = window.location.origin + targetUrl;
            }
            
        const offerHtml = `
            <div class="tabesh-ai-navigation-offer">
                <p>میخواهید به صفحه <strong>${keyword}</strong> بروید؟</p>
                <div class="tabesh-ai-offer-buttons">
                    <button class="tabesh-ai-btn-primary nav-btn-go" data-url="${targetUrl}">
                        بله، ببرم 🚀
                    </button>
                    <button class="tabesh-ai-btn-secondary nav-btn-tour" data-url="${targetUrl}">
                        اول نشونم بده 👆
                    </button>
                    <button class="tabesh-ai-btn-tertiary nav-btn-dismiss">
                        نه، ممنون
                    </button>
                </div>
            </div>
        `;
        
        const $messages = $('#tabesh-ai-browser-messages');
        $messages.append(offerHtml);
        scrollToBottom();
        
        // Attach event listeners
        $('.nav-btn-go').off('click').on('click', function() {
            const url = $(this).data('url');
            navigateToPage(url);
        });
        
        $('.nav-btn-tour').off('click').on('click', function() {
            const url = $(this).data('url');
            startTourGuide(url);
        });
        
        $('.nav-btn-dismiss').off('click').on('click', function() {
            $(this).closest('.tabesh-ai-navigation-offer').fadeOut();
        });
        }); // Close getTargetUrl callback
    }

    /**
     * Navigate to target page
     */
    function navigateToPage(url) {
        addMessage('در حال انتقال... ⏳', 'bot');
        
        setTimeout(function() {
            window.location.href = url;
        }, 500);
    }

    /**
     * Start tour guide for target page
     */
    function startTourGuide(targetUrl) {
        const currentPath = window.location.pathname;
        
        // Check if we're already on the target page
        if (currentPath.includes(targetUrl) || window.location.href.includes(targetUrl)) {
            // Ask permission before starting tour
            askTourPermission(function(granted) {
                if (granted) {
                    addMessage('راهنمای این صفحه را برایتان نشان می‌دهم! 👇', 'bot');
                    closeSidebar();
                    
                    setTimeout(function() {
                        startGuidedTour();
                    }, 500);
                } else {
                    addMessage('باشه! اگر بعداً نیاز به راهنما داشتید، حتماً بگید.', 'bot');
                }
            });
        } else {
            // Navigate to page and show tour after load
            addMessage('ابتدا شما را به صفحه مورد نظر می‌برم...', 'bot');
            sessionStorage.setItem('tabesh_show_tour', 'true');
            sessionStorage.setItem('tabesh_tour_url', targetUrl);
            
            setTimeout(function() {
                window.location.href = targetUrl;
            }, 1000);
        }
    }

    /**
     * Ask user permission to start tour guide
     */
    function askTourPermission(callback) {
        const permissionHtml = `
            <div class="tabesh-ai-tour-permission">
                <p>آیا میخواهید راهنمای گام به گام را ببینید؟</p>
                <div class="tabesh-ai-offer-buttons">
                    <button class="tabesh-ai-btn-primary tour-permission-yes">
                        بله، راهنمایی کن 🎯
                    </button>
                    <button class="tabesh-ai-btn-tertiary tour-permission-no">
                        نه، خودم انجام میدم
                    </button>
                </div>
            </div>
        `;
        
        const $messages = $('#tabesh-ai-browser-messages');
        $messages.append(permissionHtml);
        scrollToBottom();
        
        // Event listeners
        $('.tour-permission-yes').off('click').on('click', function() {
            $(this).closest('.tabesh-ai-tour-permission').fadeOut();
            callback(true);
        });
        
        $('.tour-permission-no').off('click').on('click', function() {
            $(this).closest('.tabesh-ai-tour-permission').fadeOut();
            callback(false);
        });
    }

    /**
     * Start guided tour with animated arrows
     */
    function startGuidedTour() {
        // Detect page type and start appropriate tour
        const currentUrl = window.location.href;
        
        if (currentUrl.includes('order-form') || $('#book_title').length) {
            // Start order form tour
            if (window.tabeshAITourGuide) {
                window.tabeshAITourGuide.startTour('order-form');
            }
        } else if (currentUrl.includes('cart') || $('.woocommerce-cart').length) {
            // Highlight cart
            highlightElement('.woocommerce-cart, .cart-container', 'سبد خرید شما اینجاست!');
        } else if (currentUrl.includes('contact') || $('.contact-form').length) {
            // Highlight contact form
            highlightElement('.contact-form, [data-contact-form]', 'از اینجا میتونید با ما در ارتباط باشید!');
        } else {
            // Generic page highlight
            highlightMainContent();
        }
    }

    /**
     * Highlight main content area with animated arrow
     */
    function highlightMainContent() {
        const mainContent = document.querySelector('main, .main-content, #main, .content, article');
        
        if (!mainContent) {
            console.warn('Main content not found for highlighting');
            return;
        }
        
        if (window.tabeshAITourGuide) {
            window.tabeshAITourGuide.instantHighlight(mainContent, 'محتوای اصلی صفحه اینجاست!', {
                arrow: 'top',
                pulse: true,
                duration: 5000
            });
        }
    }

    /**
     * Highlight specific element with message
     */
    function highlightElement(selector, message) {
        const element = document.querySelector(selector);
        
        if (!element) {
            console.warn('Element not found for highlighting:', selector);
            return;
        }
        
        if (window.tabeshAITourGuide) {
            window.tabeshAITourGuide.instantHighlight(selector, message, {
                arrow: 'top',
                pulse: true,
                duration: 5000
            });
        }
    }

    /**
     * Highlight form or element on page (legacy support)
     */
    function highlightOrderForm() {
        startGuidedTour();
    }

    /**
     * Check for pending tour on page load
     */
    function checkPendingTour() {
        const pendingTour = sessionStorage.getItem('tabesh_show_tour');
        const tourUrl = sessionStorage.getItem('tabesh_tour_url');
        
        if (pendingTour === 'true') {
            sessionStorage.removeItem('tabesh_show_tour');
            sessionStorage.removeItem('tabesh_tour_url');
            
            // Show permission dialog after brief delay
            setTimeout(function() {
                askTourPermission(function(granted) {
                    if (granted) {
                        setTimeout(function() {
                            startGuidedTour();
                        }, 500);
                    }
                });
            }, 1000);
        }
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

        // Detect navigation intent
        const intent = detectNavigationIntent(message);

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
                    
                    // Show navigation offer if intent detected
                    if (intent.detected) {
                        showNavigationOffer(intent.intentType, intent.keyword);
                    }
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
        
        // Save to chat history (only actual text messages, not action buttons)
        if (text && text.length > 0) {
            saveMessageToHistory(text, type === 'user' ? 'user' : 'assistant');
        }
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
                    userInterests = response.profile.interests || [];
                    
                    // Load chat history if available
                    if (response.profile.chat_history) {
                        loadChatHistory(response.profile.chat_history);
                    }
                }
            }
        });
    }

    /**
     * Load chat history from profile
     */
    function loadChatHistory(chatHistory) {
        // Only show last 5 messages to avoid clutter
        const recentHistory = chatHistory.slice(-5);
        
        // Clear existing messages except welcome
        $('#tabesh-ai-browser-messages').empty();
        
        // Add history messages
        recentHistory.forEach(function(msg) {
            const msgType = msg.role === 'user' ? 'user' : 'bot';
            addMessage(msg.content, msgType);
        });
        
        // If history exists, skip welcome and go straight to chat
        if (recentHistory.length > 0) {
            conversationState = 'chat';
        }
    }

    /**
     * Save message to chat history
     */
    function saveMessageToHistory(message, role) {
        // Store in localStorage for quick access
        const historyKey = 'tabesh_chat_history_' + guestUUID;
        let history = [];
        
        try {
            const stored = localStorage.getItem(historyKey);
            if (stored) {
                history = JSON.parse(stored);
            }
        } catch (e) {
            // Ignore parsing errors
        }
        
        // Add new message
        history.push({
            content: message,
            role: role,
            timestamp: Date.now()
        });
        
        // Keep only last 20 messages
        if (history.length > 20) {
            history = history.slice(-20);
        }
        
        // Save back
        try {
            localStorage.setItem(historyKey, JSON.stringify(history));
        } catch (e) {
            // Ignore storage errors
        }
        
        // Also send to backend periodically
        saveChatHistoryToBackend(history);
    }

    /**
     * Save chat history to backend
     */
    let historySaveTimer = null;
    function saveChatHistoryToBackend(history) {
        // Debounce backend saves
        if (historySaveTimer) {
            clearTimeout(historySaveTimer);
        }
        
        historySaveTimer = setTimeout(function() {
            $.ajax({
                url: tabeshAIBrowser.ajaxUrl + '/ai/browser/save-history',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': tabeshAIBrowser.nonce
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    guest_uuid: guestUUID,
                    chat_history: history
                }),
                async: true
            });
        }, 5000);
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
            
            // Check for pending tour guide
            checkPendingTour();
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
