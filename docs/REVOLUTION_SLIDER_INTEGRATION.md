# Revolution Slider Integration Guide

## Overview

The `[tabesh_order_form_slider]` shortcode provides seamless, real-time integration between the Tabesh order form and Revolution Slider (or any other slide/presentation system). This integration allows slides to automatically change based on user selections in the order form, creating an interactive product visualization experience.

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Basic Usage](#basic-usage)
5. [Revolution Slider Setup](#revolution-slider-setup)
6. [JavaScript API](#javascript-api)
7. [Integration Examples](#integration-examples)
8. [Shortcode Attributes](#shortcode-attributes)
9. [Event Reference](#event-reference)
10. [Troubleshooting](#troubleshooting)

## Features

- **Real-time Event Dispatching**: Every field change triggers a JavaScript event with complete form state
- **Standalone Functionality**: Works perfectly without Revolution Slider - gracefully degrades
- **Clean API**: Simple event listener for easy integration
- **All V2 Features**: Includes full cascading filters, dynamic pricing, and validation
- **Performance Optimized**: Minimal overhead, efficient event handling
- **Secure**: Maintains all Tabesh security features (nonces, sanitization, escaping)
- **RTL Support**: Full Persian/Arabic language support

## Requirements

- WordPress 6.8+
- PHP 8.2.2+
- Tabesh plugin installed and activated
- V2 Pricing Engine enabled
- At least one configured pricing matrix
- Revolution Slider plugin (optional - for slider integration)

## Installation

### Step 1: Enable V2 Pricing Engine

1. Go to WordPress Admin → Tabesh Settings → Product Pricing
2. Check "Enable V2 Pricing Engine"
3. Save settings

### Step 2: Configure Pricing Matrix

1. Go to Product Pricing management
2. Select a book size (e.g., A5, رقعی, وزیری)
3. Configure complete pricing matrix
4. Save configuration

### Step 3: Add Shortcode

Add the shortcode to your page or post:

```
[tabesh_order_form_slider]
```

## Basic Usage

### Simple Implementation

```html
<!-- Just add the shortcode -->
[tabesh_order_form_slider]
```

### With Attributes

```html
<!-- Specify slider ID for targeting -->
[tabesh_order_form_slider slider_id="my-product-slider"]

<!-- Disable events if needed (form still works) -->
[tabesh_order_form_slider enable_slider_events="false"]
```

### In PHP Template

```php
<?php echo do_shortcode('[tabesh_order_form_slider slider_id="product-preview"]'); ?>
```

## Revolution Slider Setup

### Method 1: Layer Action Integration

1. **Create Your Slider**
   - Add slides for each product configuration
   - Name slides meaningfully (e.g., "A5-glossy-color", "رقعی-گلاسه-رنگی")

2. **Add JavaScript to Slider**
   - Go to Revolution Slider → Edit Slider → Advanced Settings
   - Add this code to "Custom JavaScript":

```javascript
// Listen for Tabesh form updates
document.addEventListener('tabeshSliderUpdate', function(event) {
    const formState = event.detail;
    
    // Determine which slide to show based on form state
    let slideIndex = 0; // Default slide
    
    // Example: Switch based on book size and paper type
    if (formState.book_size === 'A5' && formState.paper_type === 'گلاسه') {
        slideIndex = 1;
    } else if (formState.book_size === 'رقعی' && formState.paper_type === 'گلاسه') {
        slideIndex = 2;
    }
    // Add more conditions as needed
    
    // Go to the selected slide
    if (revapi && revapi.revshowslide) {
        revapi.revshowslide(slideIndex);
    }
});

// Also check initial state
if (window.TabeshSlider && window.TabeshSlider.currentState) {
    // Process initial state
    console.log('Initial form state:', window.TabeshSlider.currentState);
}
```

### Method 2: External Script Integration

Create a separate JavaScript file (e.g., `slider-integration.js`):

```javascript
(function($) {
    'use strict';
    
    // Configuration: Map form states to slides
    const slideMapping = {
        'A5_گلاسه_80_color': 0,
        'A5_گلاسه_80_bw': 1,
        'A5_گلاسه_100_color': 2,
        'رقعی_گلاسه_80_color': 3,
        // Add more mappings...
    };
    
    // Wait for slider to initialize
    $(document).ready(function() {
        // Get Revolution Slider API
        const revapi = $('#rev_slider_1').revolution || null;
        
        if (!revapi) {
            console.warn('Revolution Slider not found');
            return;
        }
        
        // Listen for Tabesh updates
        document.addEventListener('tabeshSliderUpdate', function(event) {
            const state = event.detail;
            
            // Build state key
            const stateKey = [
                state.book_size,
                state.paper_type,
                state.paper_weight,
                state.print_type
            ].join('_');
            
            // Get slide index
            const slideIndex = slideMapping[stateKey];
            
            if (slideIndex !== undefined && revapi.revshowslide) {
                revapi.revshowslide(slideIndex);
                console.log('Switched to slide:', slideIndex, 'for state:', stateKey);
            }
        });
    });
})(jQuery);
```

Enqueue this script in your theme's `functions.php`:

```php
function my_theme_enqueue_slider_integration() {
    wp_enqueue_script(
        'tabesh-slider-integration',
        get_stylesheet_directory_uri() . '/js/slider-integration.js',
        array('jquery', 'tabesh-order-form-slider'),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_slider_integration');
```

## JavaScript API

### Event: `tabeshSliderUpdate`

Dispatched on every form field change with complete form state.

**Event Details:**

```javascript
{
    book_size: "A5",              // Selected book size
    paper_type: "گلاسه",          // Selected paper type
    paper_weight: "80",           // Selected paper weight (grams)
    print_type: "color",          // "color" or "bw" (black & white)
    page_count: 100,              // Number of pages
    quantity: 500,                // Print quantity
    binding_type: "سلفون",        // Selected binding type
    cover_weight: "200",          // Cover weight (grams)
    extras: ["celophane"],        // Array of selected extras
    calculated_price: {           // Price details (null if not calculated)
        price_per_book: 15000,
        total_price: 7500000,
        quantity: 500
    },
    slider_id: "my-slider"        // Slider ID from shortcode attribute
}
```

### Global Object: `window.TabeshSlider`

Access current form state directly:

```javascript
// Get current state anytime
const currentState = window.TabeshSlider.currentState;

if (currentState) {
    console.log('Current book size:', currentState.book_size);
    console.log('Current paper type:', currentState.paper_type);
}
```

### Event Listener Examples

**Basic Listener:**

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    console.log('Form updated:', event.detail);
});
```

**With State Validation:**

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Only react if all required fields are filled
    if (state.book_size && state.paper_type && state.paper_weight) {
        // Update your visualization
        updateProductPreview(state);
    }
});
```

**Debounced Listener (for performance):**

```javascript
let updateTimeout;

document.addEventListener('tabeshSliderUpdate', function(event) {
    clearTimeout(updateTimeout);
    
    updateTimeout = setTimeout(function() {
        // Process state after user stops changing fields
        processFormState(event.detail);
    }, 300); // 300ms delay
});
```

## Integration Examples

### Example 1: Simple Slider Control

```javascript
// Map book sizes to slide indices
const sizeToSlide = {
    'A5': 0,
    'A4': 1,
    'رقعی': 2,
    'وزیری': 3
};

document.addEventListener('tabeshSliderUpdate', function(event) {
    const slideIndex = sizeToSlide[event.detail.book_size];
    
    if (slideIndex !== undefined) {
        // Control your slider (example with Revolution Slider)
        jQuery('#my-slider').revshowslide(slideIndex);
    }
});
```

### Example 2: Image Gallery Update

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Build image URL based on selections
    const imageUrl = `/images/products/${state.book_size}-${state.paper_type}.jpg`;
    
    // Update image
    document.getElementById('product-preview').src = imageUrl;
});
```

### Example 3: Dynamic Content Update

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Update description text
    const desc = `قطع: ${state.book_size} | کاغذ: ${state.paper_type} ${state.paper_weight}گرم | چاپ: ${state.print_type === 'color' ? 'رنگی' : 'سیاه و سفید'}`;
    
    document.getElementById('product-description').textContent = desc;
    
    // Update price if calculated
    if (state.calculated_price) {
        document.getElementById('price-display').textContent = 
            state.calculated_price.total_price.toLocaleString('fa-IR') + ' تومان';
    }
});
```

### Example 4: Multi-Element Sync

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Update multiple page elements
    document.getElementById('size-display').textContent = state.book_size;
    document.getElementById('paper-display').textContent = state.paper_type;
    document.getElementById('pages-display').textContent = state.page_count;
    
    // Update CSS classes for styling
    document.body.className = `size-${state.book_size} paper-${state.paper_type}`;
    
    // Control slider
    updateSlider(state);
});
```

## Shortcode Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `enable_slider_events` | boolean | `true` | Enable/disable event dispatching. Form still works if disabled. |
| `slider_id` | string | `""` | Optional ID to identify which slider to control. Passed in event data. |

**Examples:**

```html
<!-- Enable events (default) -->
[tabesh_order_form_slider]

<!-- Disable events -->
[tabesh_order_form_slider enable_slider_events="false"]

<!-- Specify slider ID -->
[tabesh_order_form_slider slider_id="main-product-slider"]

<!-- Both attributes -->
[tabesh_order_form_slider enable_slider_events="true" slider_id="preview-slider"]
```

## Event Reference

### Custom Event: `tabeshSliderUpdate`

**Type:** `CustomEvent`

**Properties:**
- `detail`: Object containing complete form state
- `bubbles`: `true` (event bubbles up DOM tree)
- `cancelable`: `false` (cannot be cancelled)

**When Fired:**
- On any form field change (book size, paper type, etc.)
- On price calculation completion
- On form initialization (with empty/default values)

**Browser Compatibility:** Modern browsers (Chrome, Firefox, Safari, Edge)

## Troubleshooting

### Events Not Firing

**Check console:**
```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    console.log('Event received:', event.detail);
});
```

**Verify:**
- Shortcode is `[tabesh_order_form_slider]` not `[tabesh_order_form_v2]`
- `enable_slider_events` is not set to `false`
- Browser console shows no JavaScript errors
- Form is loading correctly

### Slider Not Responding

**Verify:**
- Revolution Slider API is available: `console.log(revapi)`
- Slider ID is correct
- Event listener is attached after slider initialization
- Slide indices match your slider configuration

**Debug:**
```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    console.log('State:', event.detail);
    console.log('Slider API:', revapi);
    console.log('Attempting slide change...');
});
```

### Performance Issues

**If updates are too frequent:**

```javascript
// Use debouncing
let timeout;
document.addEventListener('tabeshSliderUpdate', function(event) {
    clearTimeout(timeout);
    timeout = setTimeout(function() {
        updateSlider(event.detail);
    }, 300);
});
```

### State Not Updating

**Check if form fields are disabled:**
- Some fields may be disabled due to constraints
- Check pricing matrix configuration
- Verify V2 engine is enabled

**Access current state:**
```javascript
console.log('Current state:', window.TabeshSlider.currentState);
```

## Advanced Usage

### Conditional Slide Display

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Show/hide slides based on complex conditions
    if (state.book_size === 'A5' && state.quantity >= 1000) {
        // Show bulk discount slide
        revapi.revshowslide(5);
    } else if (state.print_type === 'color' && state.paper_type === 'گلاسه') {
        // Show premium quality slide
        revapi.revshowslide(2);
    } else {
        // Show default slide
        revapi.revshowslide(0);
    }
});
```

### Multiple Sliders

```javascript
// Control multiple sliders from one form
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Update main product slider
    jQuery('#main-slider').revshowslide(getMainSlideIndex(state));
    
    // Update detail slider
    jQuery('#detail-slider').revshowslide(getDetailSlideIndex(state));
    
    // Update comparison slider
    jQuery('#comparison-slider').revshowslide(getComparisonSlideIndex(state));
});
```

### Analytics Integration

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Track user selections
    if (typeof gtag !== 'undefined') {
        gtag('event', 'form_interaction', {
            'book_size': state.book_size,
            'paper_type': state.paper_type,
            'print_type': state.print_type
        });
    }
});
```

## Best Practices

1. **Wait for Slider Initialization**: Always check if slider API is available before using
2. **Handle Missing Data**: Check if required fields are filled before acting
3. **Debounce Rapid Changes**: Use debouncing for performance on rapid field changes
4. **Graceful Degradation**: Ensure slider works even if form events fail
5. **Test All Combinations**: Test with various field combinations
6. **Mobile Testing**: Test on mobile devices for touch events
7. **RTL Testing**: Test in RTL mode for Persian/Arabic

## Support

For issues or questions:
- Check Tabesh documentation: `/docs/`
- WordPress admin: Tabesh Settings
- Contact: https://chapco.ir

## Version History

- **1.0.0** - Initial release with Revolution Slider integration
- Based on Order Form V2 with full cascading filter support
- Real-time event dispatching
- Standalone functionality

---

**Note**: This feature requires V2 Pricing Engine. The form works perfectly without Revolution Slider - the integration is optional and additive.
