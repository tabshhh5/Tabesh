# Quick Start Guide: Revolution Slider Integration

## 🚀 5-Minute Setup

### Step 1: Add the Shortcode (30 seconds)

Add to any WordPress page or post:

```
[tabesh_order_form_slider]
```

**Done!** The form now dispatches events on every field change.

### Step 2: Test Events (1 minute)

Open browser console (F12) and check for event logs:

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    console.log('Form state:', event.detail);
});
```

Fill out the form and watch events appear in console.

### Step 3: Basic Revolution Slider Integration (3 minutes)

In your Revolution Slider settings, add this JavaScript:

```javascript
// Simple example: Change slide based on book size
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Map sizes to slides
    const slides = {
        'A5': 0,
        'A4': 1,
        'رقعی': 2,
        'وزیری': 3
    };
    
    // Get slide index
    const slideIndex = slides[state.book_size];
    
    // Change slide
    if (slideIndex !== undefined && revapi) {
        revapi.revshowslide(slideIndex);
    }
});
```

**That's it!** Your slider now responds to form changes.

---

## 📋 Common Use Cases

### Use Case 1: Show Different Product Images

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const { book_size, paper_type } = event.detail;
    
    // Update preview image
    const imageUrl = `/images/${book_size}-${paper_type}.jpg`;
    document.getElementById('product-preview').src = imageUrl;
});
```

### Use Case 2: Display Specifications

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    const specs = `
        قطع: ${state.book_size}
        کاغذ: ${state.paper_type} ${state.paper_weight}گرم
        چاپ: ${state.print_type === 'color' ? 'رنگی' : 'سیاه و سفید'}
    `;
    
    document.getElementById('specs').textContent = specs;
});
```

### Use Case 3: Show/Hide Content

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    // Show premium features for color printing
    if (event.detail.print_type === 'color') {
        document.getElementById('premium-features').style.display = 'block';
    } else {
        document.getElementById('premium-features').style.display = 'none';
    }
});
```

---

## 🎯 Integration Patterns

### Pattern 1: Slide Mapping (Recommended)

Best for: Pre-defined slide configurations

```javascript
const slideMapping = {
    'A5_گلاسه_80_color': 0,
    'A5_گلاسه_80_bw': 1,
    'A5_گلاسه_100_color': 2,
    'A4_تحریر_80_color': 3,
    // Add all combinations...
};

document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    const key = `${state.book_size}_${state.paper_type}_${state.paper_weight}_${state.print_type}`;
    const slideIndex = slideMapping[key];
    
    if (slideIndex !== undefined) {
        revapi.revshowslide(slideIndex);
    }
});
```

### Pattern 2: Dynamic Slides

Best for: Many possible combinations

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Calculate slide based on conditions
    let slideIndex = 0; // default
    
    if (state.book_size === 'A5') {
        if (state.print_type === 'color') {
            slideIndex = 1;
        } else {
            slideIndex = 2;
        }
    } else if (state.book_size === 'A4') {
        slideIndex = 3;
    }
    
    revapi.revshowslide(slideIndex);
});
```

### Pattern 3: Layer Control

Best for: Updating specific slider layers

```javascript
document.addEventListener('tabeshSliderUpdate', function(event) {
    const state = event.detail;
    
    // Update text layers in slider
    jQuery('#rev_slider_1').find('.price-layer').text(
        state.calculated_price ? 
        state.calculated_price.total_price.toLocaleString('fa-IR') + ' تومان' : 
        'محاسبه قیمت...'
    );
    
    jQuery('#rev_slider_1').find('.size-layer').text(state.book_size || '-');
});
```

---

## 🔧 Debugging Tips

### Check if Events are Working

```javascript
// Add this temporarily to your page
document.addEventListener('tabeshSliderUpdate', function(event) {
    alert('Event received! Book size: ' + event.detail.book_size);
});
```

### Check Current State

```javascript
// Check in console anytime
console.log(window.TabeshSlider.currentState);
```

### Count Events

```javascript
let eventCount = 0;
document.addEventListener('tabeshSliderUpdate', function() {
    console.log('Event count:', ++eventCount);
});
```

---

## ⚠️ Common Issues

### Issue: Events not firing

**Solution:** Verify you're using `[tabesh_order_form_slider]` not `[tabesh_order_form_v2]`

### Issue: Slider not changing

**Solution:** Check slider API is available:

```javascript
console.log('Slider API:', revapi);
```

### Issue: Wrong slide showing

**Solution:** Log the slide index before changing:

```javascript
console.log('Changing to slide:', slideIndex);
revapi.revshowslide(slideIndex);
```

---

## 📚 Next Steps

- Read full documentation: `/docs/REVOLUTION_SLIDER_INTEGRATION.md`
- See working examples: `/test-slider-integration.html`
- Test with browser console (F12)
- Create your slide mappings
- Add to Revolution Slider settings

---

## 💡 Pro Tips

1. **Start Simple**: Test with just book size first
2. **Use Console**: Log everything during development
3. **Cache Mappings**: Don't recalculate on every event
4. **Handle Missing Data**: Check if fields are filled before acting
5. **Test on Mobile**: Touch events work the same way

---

## 🆘 Need Help?

1. Check browser console for errors
2. Verify V2 pricing engine is enabled
3. Test with `console.log(event.detail)`
4. Check `/docs/REVOLUTION_SLIDER_INTEGRATION.md`
5. Contact: https://chapco.ir

---

**Remember:** The form works perfectly standalone. The slider integration is optional and additive!
