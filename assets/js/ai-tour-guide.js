/**
 * AI Tour Guide JavaScript
 *
 * Provides interactive tour guides with element highlighting and arrows.
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // State
    let activeTour = null;
    let currentStepIndex = 0;
    let $overlay = null;
    let $highlight = null;
    let $arrow = null;
    let $tooltip = null;

    /**
     * Start tour
     */
    function startTour(target) {
        // Get tour steps from server
        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/browser/tour',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({ target: target }),
            success: function(response) {
                if (response.success && response.steps && response.steps.length > 0) {
                    activeTour = response.steps;
                    currentStepIndex = 0;
                    showStep(0);
                }
            },
            error: function() {
                console.error('Failed to load tour steps');
            }
        });
    }

    /**
     * Show tour step
     */
    function showStep(stepIndex) {
        if (!activeTour || stepIndex >= activeTour.length) {
            endTour();
            return;
        }

        const step = activeTour[stepIndex];
        currentStepIndex = stepIndex;

        // Find target element
        const $target = $(step.selector);
        if (!$target.length) {
            console.warn('Tour target not found:', step.selector);
            nextStep();
            return;
        }

        // Create overlay if not exists
        if (!$overlay) {
            createOverlay();
        }

        // Show overlay
        $overlay.show();

        // Scroll to element
        scrollToElement($target, function() {
            // Highlight element
            highlightElement($target, step.pulse || false);

            // Show arrow
            if (step.arrow) {
                showArrow($target, step.arrow);
            }

            // Show tooltip
            showTooltip($target, step.message, step.arrow);
        });

        // Track tour step
        if (window.tabeshAITracker) {
            window.tabeshAITracker.trackEvent('tour_step', {
                tour_target: activeTour[0].selector,
                step_index: stepIndex,
                step_selector: step.selector
            });
        }
    }

    /**
     * Create overlay
     */
    function createOverlay() {
        $overlay = $('<div class="tour-guide-overlay"></div>');
        $('body').append($overlay);

        // Click anywhere to advance
        $overlay.on('click', function(e) {
            if ($(e.target).hasClass('tour-guide-overlay')) {
                nextStep();
            }
        });
    }

    /**
     * Highlight element
     */
    function highlightElement($element, pulse) {
        // Remove existing highlight
        if ($highlight) {
            $highlight.remove();
        }

        // Get element position and size
        const offset = $element.offset();
        const width = $element.outerWidth();
        const height = $element.outerHeight();

        // Create highlight
        $highlight = $('<div class="tour-guide-highlight"></div>');
        $highlight.css({
            position: 'absolute',
            top: offset.top - 5,
            left: offset.left - 5,
            width: width + 10,
            height: height + 10,
            zIndex: 100000
        });

        $('body').append($highlight);

        // Add pulse animation if requested
        if (pulse) {
            $highlight.addClass('pulse');
        }
    }

    /**
     * Show arrow
     */
    function showArrow($element, direction) {
        // Remove existing arrow
        if ($arrow) {
            $arrow.remove();
        }

        const offset = $element.offset();
        const width = $element.outerWidth();
        const height = $element.outerHeight();

        $arrow = $('<div class="tour-guide-arrow"></div>');
        $arrow.addClass(direction);

        let top, left;

        switch (direction) {
            case 'top':
                top = offset.top - 50;
                left = offset.left + (width / 2) - 20;
                break;
            case 'bottom':
                top = offset.top + height + 10;
                left = offset.left + (width / 2) - 20;
                break;
            case 'left':
                top = offset.top + (height / 2) - 20;
                left = offset.left - 50;
                break;
            case 'right':
                top = offset.top + (height / 2) - 20;
                left = offset.left + width + 10;
                break;
        }

        $arrow.css({
            position: 'absolute',
            top: top,
            left: left,
            zIndex: 100001
        });

        $('body').append($arrow);
    }

    /**
     * Show tooltip
     */
    function showTooltip($element, message, arrowDirection) {
        // Remove existing tooltip
        if ($tooltip) {
            $tooltip.remove();
        }

        const offset = $element.offset();
        const width = $element.outerWidth();
        const height = $element.outerHeight();

        $tooltip = $('<div class="tour-guide-tooltip"></div>');
        $tooltip.html('<p>' + escapeHtml(message) + '</p>');

        $('body').append($tooltip);

        // Calculate position based on arrow direction
        const tooltipWidth = $tooltip.outerWidth();
        const tooltipHeight = $tooltip.outerHeight();
        let top, left;

        switch (arrowDirection) {
            case 'top':
                top = offset.top - tooltipHeight - 60;
                left = offset.left + (width / 2) - (tooltipWidth / 2);
                break;
            case 'bottom':
                top = offset.top + height + 60;
                left = offset.left + (width / 2) - (tooltipWidth / 2);
                break;
            case 'left':
                top = offset.top + (height / 2) - (tooltipHeight / 2);
                left = offset.left - tooltipWidth - 60;
                break;
            case 'right':
                top = offset.top + (height / 2) - (tooltipHeight / 2);
                left = offset.left + width + 60;
                break;
            default:
                top = offset.top - tooltipHeight - 20;
                left = offset.left + (width / 2) - (tooltipWidth / 2);
        }

        // Keep tooltip in viewport
        if (left < 10) left = 10;
        if (left + tooltipWidth > $(window).width() - 10) {
            left = $(window).width() - tooltipWidth - 10;
        }
        if (top < 10) top = 10;

        $tooltip.css({
            position: 'absolute',
            top: top,
            left: left,
            zIndex: 100002
        });

        // Click to advance
        $tooltip.on('click', function() {
            nextStep();
        });
    }

    /**
     * Scroll to element
     */
    function scrollToElement($element, callback) {
        const offset = $element.offset();
        const scrollTop = offset.top - ($(window).height() / 2) + ($element.outerHeight() / 2);

        $('html, body').animate({
            scrollTop: scrollTop
        }, 500, 'swing', function() {
            if (callback) {
                callback();
            }
        });
    }

    /**
     * Next step
     */
    function nextStep() {
        currentStepIndex++;
        showStep(currentStepIndex);
    }

    /**
     * Previous step
     */
    function previousStep() {
        if (currentStepIndex > 0) {
            currentStepIndex--;
            showStep(currentStepIndex);
        }
    }

    /**
     * End tour
     */
    function endTour() {
        // Remove overlays and highlights
        if ($overlay) {
            $overlay.remove();
            $overlay = null;
        }
        if ($highlight) {
            $highlight.remove();
            $highlight = null;
        }
        if ($arrow) {
            $arrow.remove();
            $arrow = null;
        }
        if ($tooltip) {
            $tooltip.remove();
            $tooltip = null;
        }

        // Track tour completion
        if (window.tabeshAITracker && activeTour) {
            window.tabeshAITracker.trackEvent('tour_completed', {
                tour_target: activeTour[0].selector,
                steps_completed: currentStepIndex
            });
        }

        // Reset state
        activeTour = null;
        currentStepIndex = 0;
    }

    /**
     * Highlight element (public API)
     */
    function highlightElementPublic(selector, options) {
        options = options || {};

        const $element = $(selector);
        if (!$element.length) {
            return;
        }

        // Create temporary overlay
        const $tempOverlay = $('<div class="tour-guide-overlay"></div>');
        $('body').append($tempOverlay);
        $tempOverlay.show();

        // Scroll to element
        scrollToElement($element, function() {
            // Highlight
            highlightElement($element, options.pulse || false);

            // Show arrow if specified
            if (options.arrow) {
                showArrow($element, options.arrow);
            }

            // Show tooltip if specified
            if (options.tooltip) {
                showTooltip($element, options.tooltip, options.arrow);
            }

            // Auto-remove after duration
            setTimeout(function() {
                $tempOverlay.remove();
                if ($highlight) $highlight.remove();
                if ($arrow) $arrow.remove();
                if ($tooltip) $tooltip.remove();
            }, options.duration || 5000);
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

    // Expose API
    window.tabeshAITourGuide = {
        startTour: startTour,
        endTour: endTour,
        nextStep: nextStep,
        previousStep: previousStep,
        highlightElement: highlightElementPublic,
        scrollToElement: scrollToElement
    };

})(jQuery);
