/**
 * AI Field Explainer JavaScript
 *
 * Automatically explains form fields when user focuses or changes values.
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    let explanationTimeout = null;
    let currentExplanationTooltip = null;
    let explainedFields = new Set(); // Track already explained fields to avoid spam

    /**
     * Debounce helper
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Get field label
     */
    function getFieldLabel(field) {
        // Try to find label by 'for' attribute
        if (field.id) {
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (label) {
                return label.textContent.trim();
            }
        }
        
        // Try to find parent label
        const parentLabel = field.closest('label');
        if (parentLabel) {
            // Extract only the label text, not the field value
            let labelText = '';
            parentLabel.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    labelText += node.textContent.trim() + ' ';
                }
            });
            return labelText.trim();
        }
        
        // Try to find previous sibling label
        let prev = field.previousElementSibling;
        while (prev) {
            if (prev.tagName === 'LABEL') {
                return prev.textContent.trim();
            }
            prev = prev.previousElementSibling;
        }
        
        // Use placeholder, title, or name as fallback
        return field.placeholder || field.title || field.name || '';
    }

    /**
     * Get field info for explanation
     */
    function getFieldInfo(field) {
        return {
            fieldName: field.name || field.id || '',
            fieldLabel: getFieldLabel(field),
            selectedValue: field.value || '',
            fieldType: field.type || 'text'
        };
    }

    /**
     * Request field explanation from server
     */
    function requestFieldExplanation(fieldInfo) {
        // Check if already explained recently
        const fieldKey = fieldInfo.fieldName + '_' + fieldInfo.selectedValue;
        if (explainedFields.has(fieldKey)) {
            return;
        }

        // Get user profile if available
        let guestUUID = null;
        if (window.tabeshAIBrowserAPI && typeof window.tabeshAIBrowserAPI.getGuestUUID === 'function') {
            guestUUID = window.tabeshAIBrowserAPI.getGuestUUID();
        }

        $.ajax({
            url: tabeshAIBrowser.ajaxUrl + '/ai/field/explain',
            method: 'POST',
            headers: {
                'X-WP-Nonce': tabeshAIBrowser.nonce
            },
            contentType: 'application/json',
            data: JSON.stringify({
                field_info: fieldInfo,
                guest_uuid: guestUUID
            }),
            success: function(response) {
                if (response.success && response.explanation) {
                    showFieldExplanation(fieldInfo.fieldName, response.explanation);
                    explainedFields.add(fieldKey);
                }
            },
            error: function(xhr) {
                console.error('[Tabesh AI] Failed to get field explanation:', xhr);
            }
        });
    }

    /**
     * Show field explanation tooltip
     */
    function showFieldExplanation(fieldName, explanation) {
        // Find the field element
        const field = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
        if (!field) {
            return;
        }

        // Remove existing tooltip
        removeExplanationTooltip();

        // Create tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'tabesh-field-explanation-tooltip';
        tooltip.innerHTML = `
            <div class="explanation-content">
                <span class="explanation-icon">💡</span>
                <span class="explanation-text">${escapeHtml(explanation)}</span>
            </div>
        `;

        // Position tooltip
        const rect = field.getBoundingClientRect();
        tooltip.style.cssText = `
            position: fixed;
            top: ${rect.bottom + 8}px;
            left: ${rect.left}px;
            z-index: 100000;
        `;

        document.body.appendChild(tooltip);
        currentExplanationTooltip = tooltip;

        // Animate in
        setTimeout(() => {
            tooltip.classList.add('show');
        }, 10);

        // Auto-hide after 5 seconds
        explanationTimeout = setTimeout(() => {
            removeExplanationTooltip();
        }, 5000);

        // Track event
        if (window.tabeshAITracker) {
            window.tabeshAITracker.trackEvent('field_explained', {
                field_name: fieldName,
                explanation_length: explanation.length
            });
        }

        // Also send to AI Browser sidebar if open
        if (window.tabeshAIBrowserAPI && typeof window.tabeshAIBrowserAPI.addMessage === 'function') {
            const fieldLabel = getFieldLabel(field);
            const message = `ℹ️ ${fieldLabel ? fieldLabel + ': ' : ''}${explanation}`;
            window.tabeshAIBrowserAPI.addMessage(message, 'bot');
        }
    }

    /**
     * Remove explanation tooltip
     */
    function removeExplanationTooltip() {
        if (currentExplanationTooltip) {
            currentExplanationTooltip.classList.remove('show');
            setTimeout(() => {
                if (currentExplanationTooltip && currentExplanationTooltip.parentNode) {
                    currentExplanationTooltip.parentNode.removeChild(currentExplanationTooltip);
                }
                currentExplanationTooltip = null;
            }, 300);
        }
        if (explanationTimeout) {
            clearTimeout(explanationTimeout);
            explanationTimeout = null;
        }
    }

    /**
     * Handle field focus
     */
    const debouncedFieldFocus = debounce(function(e) {
        const field = e.target;
        
        // Only process form fields
        if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(field.tagName)) {
            return;
        }

        // Skip password fields
        if (field.type === 'password') {
            return;
        }

        // Get field info
        const fieldInfo = getFieldInfo(field);
        
        // If field has value, request explanation
        if (fieldInfo.selectedValue) {
            requestFieldExplanation(fieldInfo);
        }
    }, 500);

    /**
     * Handle field change
     */
    const debouncedFieldChange = debounce(function(e) {
        const field = e.target;
        
        // Only process form fields
        if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(field.tagName)) {
            return;
        }

        // Skip password fields
        if (field.type === 'password') {
            return;
        }

        // Get field info
        const fieldInfo = getFieldInfo(field);
        
        // Request explanation for the new value
        if (fieldInfo.selectedValue) {
            requestFieldExplanation(fieldInfo);
        }
    }, 800);

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

    /**
     * Initialize field explainer
     */
    function initFieldExplainer() {
        // Check if feature is enabled
        if (typeof tabeshAIBrowser === 'undefined' || !tabeshAIBrowser.fieldExplainerEnabled) {
            return;
        }

        // Listen for focus events (capture phase to catch all)
        document.addEventListener('focus', debouncedFieldFocus, true);

        // Listen for change events
        document.addEventListener('change', debouncedFieldChange, true);

        // Listen for click outside to close tooltip
        document.addEventListener('click', function(e) {
            if (currentExplanationTooltip && !currentExplanationTooltip.contains(e.target)) {
                // Check if clicked on a form field
                if (!['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName)) {
                    removeExplanationTooltip();
                }
            }
        });

        console.log('[Tabesh AI] Field Explainer initialized');
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initFieldExplainer();

        // Expose to global scope
        window.tabeshFieldExplainer = {
            explain: requestFieldExplanation,
            hideTooltip: removeExplanationTooltip
        };
    });

})(jQuery);
