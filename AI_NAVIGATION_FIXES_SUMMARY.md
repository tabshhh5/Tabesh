# AI Navigation Fixes Implementation Summary

**Date:** 2025-12-24
**Issue:** AI chatbot navigation problems - incorrect URLs, broken "show me" links, missing guided tours

## Problem Statement (Persian)

با هدف ایجاد یک ویژگی جدید هوش مصنوعی در این افزونه از طریق pr های زیر اقدام به ایجاد کد های جدید شده:
- PRs #180-#186

### مشکلات شناسایی شده:

1. **کلاس هوش مصنوعی به صورت اتوماتیک صفحات سایت را ایندکس میکند اما بر اساس محتوا، عنوان تایتل، نامک، لینک نمیتواند درست هدایت کند**
   - وقتی میخواهد به صفحه ثبت سفارش هدایت کند مارا به لینک که وجود ندارد می برد
   - باید یک سرچ داخلی انجام دهد بین صفحات

2. **زمانی که میخواهد فقط نشان دهد لینک اشتباه را نشان میدهد**
   - برسی کنید چرا وقتی کاربر روی گزینه "فقط نشانم بده" میزند لینک را خراب میکند

3. **زمانی که کاربر به چت بات میگوید چطور ثبت سفارش کنم، وقتی کاربر را به هر صفحه جدید میبرد باید یک خط شبیه خود کار روی صفحه بکشد**
   - با فلش نشان دهد چطور ثبت سفارش کند
   - تور آموزشی را شروع کند با اجازه کاربر

## Solutions Implemented

### 1. Smart Page Search with Fuzzy Matching ✅

**File:** `includes/ai/class-tabesh-ai-site-indexer.php`

Added intelligent page search functionality:

```php
public function smart_search_pages( $user_query, $limit = 5 )
```

**Features:**
- **Persian Keyword Extraction**: Recognizes common Persian intents
  - سفارش → searches for order-related pages
  - چاپ کتاب → searches for printing pages
  - تماس → searches for contact pages
  - قیمت → searches for pricing pages

- **Fuzzy Matching**: Maps related keywords
  ```php
  'سفارش'       => array( 'سفارش', 'فرم', 'order' ),
  'ثبت سفارش'   => array( 'سفارش', 'ثبت', 'فرم', 'order' ),
  'فرم سفارش'   => array( 'سفارش', 'فرم', 'order', 'form' ),
  ```

- **Relevance Scoring**: Ranks results by:
  - Title matches (weight: 10-20 points)
  - Page type matches (weight: 8-15 points)
  - Content matches (weight: 5 points)
  - Keyword matches (weight: 3 points)

- **Best Page Selection**:
  ```php
  public function find_best_page( $user_query )
  ```

### 2. REST API Endpoint for Page Search ✅

**File:** `includes/ai/class-tabesh-ai-browser.php`

Added new endpoint:
```
POST /wp-json/tabesh/v1/ai/browser/search-pages
```

**Request:**
```json
{
  "query": "سفارش چاپ کتاب",
  "limit": 5
}
```

**Response:**
```json
{
  "success": true,
  "results": [
    {
      "page_url": "https://example.com/order-form/",
      "page_title": "فرم ثبت سفارش",
      "page_type": "order-form",
      "relevance": 45
    }
  ],
  "count": 1
}
```

### 3. Enhanced Navigation with Smart Search ✅

**File:** `includes/ai/class-tabesh-ai-browser.php`

Updated `rest_navigate()` method:

**Before:**
```php
// Hardcoded routes only
$target_url = $this->get_target_url_for_profession( $profession, $context );
```

**After:**
```php
// Smart search first, then fallback
$indexer    = new Tabesh_AI_Site_Indexer();
$best_page  = $indexer->find_best_page( $search_query );

if ( $best_page && ! empty( $best_page['page_url'] ) ) {
    $target_url = $best_page['page_url'];
} else {
    // Fallback to hardcoded routes
    $target_url = $this->get_target_url_for_profession( $profession, $context );
}
```

### 4. Fixed JavaScript URL Handling ✅

**File:** `assets/js/ai-browser.js`

**Before:** Used hardcoded routes only
```javascript
function getTargetUrl(intentType) {
    const routes = { order_form: '/order-form/', ... };
    return routes[intentType] || null;
}
```

**After:** Smart search with proper URL handling
```javascript
function getTargetUrl(intentType, keyword, callback) {
    // Try smart search first
    smartSearchPages(searchQuery, function(page) {
        if (page && page.page_url) {
            callback(page.page_url);
        } else {
            // Fallback to hardcoded routes
            callback(routes[intentType] || null);
        }
    });
}
```

**URL Validation:**
```javascript
// Ensure URL is properly formed
if (targetUrl.startsWith('/')) {
    targetUrl = window.location.origin + targetUrl;
}
```

### 5. Automated Tour Guide with Permission ✅

**File:** `assets/js/ai-browser.js`

**Permission Dialog:**
```javascript
function askTourPermission(callback) {
    // Shows user-friendly dialog:
    // "آیا میخواهید راهنمای گام به گام را ببینید؟"
    // [بله، راهنمایی کن 🎯] [نه، خودم انجام میدم]
}
```

**Guided Tour:**
```javascript
function startGuidedTour() {
    // Detects page type (order-form, cart, contact, etc.)
    // Starts appropriate tour with animated arrows
    if (window.tabeshAITourGuide) {
        window.tabeshAITourGuide.startTour('order-form');
    }
}
```

**Session Continuity:**
```javascript
// Before navigation
sessionStorage.setItem('tabesh_show_tour', 'true');

// After page load
function checkPendingTour() {
    if (pendingTour === 'true') {
        askTourPermission(function(granted) {
            if (granted) startGuidedTour();
        });
    }
}
```

### 6. Enhanced AI Prompt ✅

**File:** `includes/ai/class-tabesh-ai-gemini.php`

Updated system prompt to emphasize:
```php
"- **مهم**: اگر کاربر خواهان رفتن به صفحه خاصی است (مثل صفحه سفارش، تماس، قیمت)، 
   حتما از لیست صفحات موجود زیر استفاده کنید و لینک دقیق صفحه را ذکر کنید"
"- هنگام معرفی صفحات، همیشه URL کامل و صحیح را بدون هیچ تغییری از لیست زیر استفاده کنید"
```

## Testing Guide

### 1. Test Smart Page Search

**Test Case 1: Order Form Search**
```
User: "میخوام سفارش ثبت کنم"
Expected: Should find and suggest order form page
```

**Test Case 2: Contact Page**
```
User: "چطور با شما تماس بگیرم؟"
Expected: Should find contact page
```

**Test Case 3: Pricing**
```
User: "قیمت چاپ چقدره؟"
Expected: Should find pricing or order form page
```

### 2. Test Navigation Buttons

**Test "Take Me There" Button:**
1. Ask chatbot: "میخوام سفارش ثبت کنم"
2. Click "بله، ببرم 🚀" button
3. Verify: Should navigate to correct order form page
4. Check: URL should be valid and accessible

**Test "Show Me" Button:**
1. Ask chatbot: "میخوام سفارش ثبت کنم"
2. Click "اول نشونم بده 👆" button
3. Verify: Should show tour permission dialog
4. Check: Should highlight form elements with arrows

### 3. Test Guided Tour

**Test Permission Dialog:**
1. Click "اول نشونم بده 👆"
2. Verify: Permission dialog appears
3. Test both options:
   - "بله، راهنمایی کن 🎯" → Should start tour
   - "نه، خودم انجام میدم" → Should dismiss

**Test Tour Navigation:**
1. Accept tour permission
2. Verify: Tour guide starts automatically
3. Check: Animated arrows point to form fields
4. Verify: Step-by-step guidance appears

**Test Cross-Page Tour:**
1. On homepage, click "اول نشونم بده 👆" for order form
2. Verify: Navigates to order form page
3. Check: Tour permission dialog appears after page load
4. Accept: Tour should start on new page

### 4. Test URL Handling

**Test Relative URLs:**
```javascript
// Should convert /order-form/ to https://example.com/order-form/
```

**Test Absolute URLs:**
```javascript
// Should use as-is: https://example.com/order-form/
```

**Test Invalid URLs:**
```javascript
// Should fallback to hardcoded routes
```

## Files Modified

### Backend (PHP)
1. ✅ `includes/ai/class-tabesh-ai-site-indexer.php` (240 lines added)
   - Added smart search methods
   - Persian keyword extraction
   - Relevance scoring

2. ✅ `includes/ai/class-tabesh-ai-browser.php` (47 lines added)
   - New REST endpoint
   - Enhanced navigation logic

3. ✅ `includes/ai/class-tabesh-ai-gemini.php` (10 lines modified)
   - Enhanced AI prompt

### Frontend (JavaScript)
1. ✅ `assets/js/ai-browser.js` (200+ lines modified/added)
   - Smart search integration
   - URL handling fixes
   - Guided tour system
   - Permission dialog

## Code Quality

### Linting Status
- ✅ `class-tabesh-ai-gemini.php`: 0 errors, 0 warnings
- ✅ `class-tabesh-ai-site-indexer.php`: Modified code passes WPCS
- ⚠️ `class-tabesh-ai-browser.php`: 1 minor warning (pre-existing, unused parameter)
- ✅ WordPress Coding Standards compliant
- ✅ Proper SQL escaping and sanitization

### Security Measures
- ✅ All user input sanitized
- ✅ SQL injection protection via `$wpdb->prepare()`
- ✅ XSS prevention via proper escaping
- ✅ REST API authentication with nonces
- ✅ Permission checks on all endpoints

## Expected Behavior

### User Flow Example

**Scenario: User wants to place an order**

1. **User asks:** "چطور سفارش ثبت کنم؟"

2. **AI responds with indexed pages:**
   - Shows relevant pages found
   - Offers two buttons: "بله، ببرم 🚀" and "اول نشونم بده 👆"

3. **If user clicks "Take Me There":**
   - Navigates directly to order form page
   - Tour can be requested later

4. **If user clicks "Show Me":**
   - Shows permission dialog
   - If accepted, starts guided tour:
     - Navigates to order form (if not already there)
     - Shows animated arrows pointing to form fields
     - Step-by-step guidance through the form

5. **Tour continues:**
   - Field 1: "ابتدا عنوان کتاب خود را وارد کنید" ← arrow points to #book_title
   - Field 2: "سایز کتاب خود را انتخاب کنید" ← arrow points to #book_size
   - And so on...

## Known Limitations

1. **Search Accuracy**: Depends on indexed page quality
   - Solution: Run manual indexing via admin panel
   - Endpoint: POST `/wp-json/tabesh/v1/ai/index-site`

2. **Tour Customization**: Tours are predefined
   - Can be customized via `Tabesh_AI_Tour_Guide::add_custom_tour()`

3. **Language Support**: Currently optimized for Persian
   - English keywords supported but with lower priority

## Troubleshooting

### Issue: Pages not found by search
**Solution:** Re-index site pages
```php
$indexer = new Tabesh_AI_Site_Indexer();
$indexer->index_wordpress_content();
```

### Issue: Tour not starting
**Check:**
1. Is `tabeshAITourGuide` loaded?
2. Does page have tour-compatible elements?
3. Check browser console for errors

### Issue: Wrong URLs returned
**Debug:**
```javascript
console.log('Search results:', response.results);
console.log('Selected URL:', targetUrl);
```

## Future Enhancements

1. **Multi-language support**: Add English, Arabic keywords
2. **Learning system**: Track successful navigation patterns
3. **Custom tour builder**: Admin UI for creating tours
4. **Analytics**: Track tour completion rates

## References

- **Original PRs**: #180-#186
- **Related Issues**: AI navigation problems
- **Documentation**: See `/docs/AI-FEATURES.md` (if exists)

---

**Status:** ✅ Implementation Complete
**Testing:** ⏳ Awaiting Manual Testing
**Next Step:** Deploy to staging environment for testing
