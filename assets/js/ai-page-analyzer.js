/**
 * AI Page Analyzer JavaScript
 *
 * Extracts page content, DOM structure, and user interactions
 * for AI-powered assistance.
 *
 * @package Tabesh
 */

(function($) {
    'use strict';

    // Debounce helper
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
     * Extract visible text from page
     */
    function extractVisibleText() {
        const body = document.body.cloneNode(true);
        
        // Remove script and style tags
        const scripts = body.querySelectorAll('script, style, noscript');
        scripts.forEach(el => el.remove());
        
        // Remove hidden elements
        const hidden = body.querySelectorAll('[style*="display: none"], [style*="visibility: hidden"], .hidden');
        hidden.forEach(el => el.remove());
        
        // Get text content
        let text = body.textContent || body.innerText || '';
        
        // Normalize whitespace
        text = text.replace(/\s+/g, ' ').trim();
        
        // Limit length
        return text.substring(0, 5000);
    }

    /**
     * Extract form data
     */
    function extractFormData() {
        const forms = [];
        const formElements = document.querySelectorAll('form');
        
        formElements.forEach(form => {
            const formData = {
                id: form.id || '',
                name: form.name || '',
                action: form.action || '',
                method: form.method || 'post',
                fields: []
            };
            
            // Extract fields
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach(field => {
                // Skip password fields for security
                if (field.type === 'password') {
                    return;
                }
                
                const fieldData = {
                    name: field.name || '',
                    type: field.type || 'text',
                    label: getFieldLabel(field),
                    value: field.value || '',
                    placeholder: field.placeholder || '',
                    required: field.required || false
                };
                
                formData.fields.push(fieldData);
            });
            
            forms.push(formData);
        });
        
        return forms;
    }

    /**
     * Get label for field
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
            return parentLabel.textContent.trim();
        }
        
        // Try to find previous sibling label
        let prev = field.previousElementSibling;
        while (prev) {
            if (prev.tagName === 'LABEL') {
                return prev.textContent.trim();
            }
            prev = prev.previousElementSibling;
        }
        
        // Use placeholder or name as fallback
        return field.placeholder || field.name || '';
    }

    /**
     * Get hovered element info
     */
    let currentHoveredElement = null;
    function getHoveredElementInfo() {
        if (!currentHoveredElement) {
            return {};
        }
        
        return {
            tagName: currentHoveredElement.tagName || '',
            id: currentHoveredElement.id || '',
            className: currentHoveredElement.className || '',
            text: currentHoveredElement.textContent ? currentHoveredElement.textContent.trim().substring(0, 100) : '',
            href: currentHoveredElement.href || '',
            name: currentHoveredElement.name || '',
            type: currentHoveredElement.type || '',
            placeholder: currentHoveredElement.placeholder || '',
            value: currentHoveredElement.value || ''
        };
    }

    /**
     * Get visible buttons info
     */
    function getButtonsInfo() {
        const buttons = [];
        const buttonElements = document.querySelectorAll('button, input[type="button"], input[type="submit"], a.button, .btn');
        
        buttonElements.forEach(btn => {
            // Check if visible
            if (btn.offsetParent === null) {
                return;
            }
            
            buttons.push({
                text: btn.textContent ? btn.textContent.trim() : (btn.value || ''),
                id: btn.id || '',
                className: btn.className || '',
                type: btn.type || 'button'
            });
        });
        
        return buttons;
    }

    /**
     * Get navigation menu structure
     */
    function getMenuStructure() {
        const menu = [];
        const menuElements = document.querySelectorAll('nav a, .menu a, header a');
        
        menuElements.forEach(link => {
            // Check if visible
            if (link.offsetParent === null) {
                return;
            }
            
            menu.push({
                text: link.textContent.trim(),
                href: link.href || ''
            });
        });
        
        return menu;
    }

    /**
     * Extract complete page context
     */
    window.tabeshExtractPageContext = function() {
        return {
            pageTitle: document.title,
            currentURL: window.location.href,
            pageContent: extractVisibleText(),
            forms: extractFormData(),
            hoveredElement: getHoveredElementInfo(),
            visibleButtons: getButtonsInfo(),
            navigationMenu: getMenuStructure()
        };
    };

    /**
     * Track hover events with debounce
     */
    const debouncedHoverTrack = debounce(function(element) {
        currentHoveredElement = element;
        
        // Send hover event to tracker if available
        if (window.tabeshAITracker) {
            window.tabeshAITracker.trackEvent('element_hover', {
                element_info: getHoveredElementInfo()
            });
        }
    }, 500);

    /**
     * Initialize hover tracking
     */
    function initHoverTracking() {
        // Track hover on interactive elements
        document.addEventListener('mouseover', function(e) {
            const target = e.target;
            
            // Only track meaningful elements
            if (target.tagName === 'A' || 
                target.tagName === 'BUTTON' || 
                target.tagName === 'INPUT' || 
                target.tagName === 'SELECT' || 
                target.tagName === 'TEXTAREA' ||
                target.classList.contains('button') ||
                target.classList.contains('btn')) {
                
                debouncedHoverTrack(target);
            }
        }, true);
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initialize hover tracking
        initHoverTracking();
        
        // Expose to global scope
        window.tabeshPageAnalyzer = {
            extractPageContext: window.tabeshExtractPageContext,
            getHoveredElement: getHoveredElementInfo,
            getForms: extractFormData,
            getButtons: getButtonsInfo,
            getMenu: getMenuStructure
        };
        
        console.log('[Tabesh AI] Page Analyzer initialized');
    });

})(jQuery);
