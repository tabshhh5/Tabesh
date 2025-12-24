# AI Browser Feature Documentation

## Overview

The AI Browser is an intelligent sidebar assistant that helps users navigate the Tabesh website efficiently. It appears as a modern sidebar interface on the right side (RTL-compatible) with context-aware conversations, behavior tracking, and interactive tour guides.

## Key Features

### 1. Intelligent Sidebar Interface
- **Desktop**: Sidebar slides in from the right, pushing content to the left
- **Mobile**: Full-screen overlay with 70% height panel
- **Modern Design**: Gradient headers, smooth animations, RTL-ready
- **Floating Button**: Always accessible at bottom-right corner

### 2. User Behavior Tracking
The AI Browser tracks user activities to provide personalized assistance:
- **Page views** and navigation patterns
- **Scroll position** and reading behavior
- **Click events** on links and buttons
- **Form interactions** (field focus and changes)
- **Time on page** and idle detection
- **Referrer tracking** (e.g., from external sites like Tarbiat.com)

### 3. Smart Conversations
Personalized conversation flow based on user context:

**Initial Greeting:**
```
"سلام! من دستیار هوشمند تابش هستم. اجازه میدید کمکتون کنم؟"
```

**Profession Questions:**
- آیا خریدار کتاب هستید؟ (Book Buyer)
- آیا نویسنده هستید؟ (Author)
- آیا ناشر هستید؟ (Publisher)
- آیا چاپخانه‌دار هستید؟ (Printer)

**Navigation Offer:**
```
"اجازه میدید چیزی را به شما نشان دهم که شاید به دنبالش میگردید؟"
```

### 4. Interactive Tour Guides
Highlight and guide users through important features:
- **Element Highlighting**: Pulse animation with colored border
- **Arrow Pointers**: Animated arrows pointing to target elements
- **Tooltips**: Contextual messages explaining each step
- **Smooth Scrolling**: Auto-scroll to highlighted elements

**Pre-configured Tours:**
- `order-form`: Step-by-step guide for order submission
- `cart`: Show where shopping cart is located
- `dashboard`: Guide through user dashboard

### 5. User Profiles & Data Storage

**For Logged-In Users:**
- Stored in `wp_tabesh_ai_user_profiles`
- Linked to WordPress user ID
- Persists across sessions
- Includes profession, interests, preferences, chat history

**For Guest Users:**
- Stored in `wp_tabesh_ai_guest_profiles`
- UUID-based identification via localStorage
- 90-day expiration
- Optional name and mobile collection

## Database Tables

### wp_tabesh_ai_user_profiles
Stores logged-in user AI profiles:
```sql
- id (bigint, primary key)
- user_id (bigint, unique, foreign key to wp_users)
- profession (varchar)
- interests (JSON)
- preferences (JSON)
- behavior_data (JSON)
- chat_history (JSON)
- first_name (varchar)
- created_at (datetime)
- updated_at (datetime)
```

### wp_tabesh_ai_guest_profiles
Stores guest visitor AI profiles:
```sql
- id (bigint, primary key)
- guest_uuid (varchar, unique)
- name (varchar, optional)
- mobile (varchar, optional)
- profession (varchar)
- interests (JSON)
- preferences (JSON)
- behavior_data (JSON)
- chat_history (JSON)
- created_at (datetime)
- updated_at (datetime)
- expires_at (datetime)
```

### wp_tabesh_ai_behavior_logs
Stores user behavior tracking data:
```sql
- id (bigint, primary key)
- user_id (bigint, nullable)
- guest_uuid (varchar, nullable)
- page_url (text)
- event_type (varchar)
- event_data (JSON)
- referrer (text)
- created_at (datetime)
```

## REST API Endpoints

All endpoints are prefixed with `/wp-json/tabesh/v1/ai/browser/`

### POST /track
Track user behavior event.

**Request:**
```json
{
  "event_type": "page_view",
  "event_data": {
    "page_url": "https://example.com/order-form",
    "referrer": "https://tarbiat.com",
    "scroll_percent": 50
  },
  "guest_uuid": "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx"
}
```

**Response:**
```json
{
  "success": true,
  "message": "رفتار ثبت شد"
}
```

### GET /profile
Get user or guest profile.

**Parameters:**
- `guest_uuid` (optional): Guest UUID for non-logged-in users

**Response:**
```json
{
  "success": true,
  "profile": {
    "profession": "author",
    "interests": [],
    "preferences": {},
    "chat_history": []
  }
}
```

### POST /navigate
Request navigation to target page based on profession.

**Request:**
```json
{
  "profession": "author",
  "context": {
    "page_url": "https://example.com",
    "referrer": ""
  },
  "guest_uuid": "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx"
}
```

**Response:**
```json
{
  "success": true,
  "target_url": "https://example.com/author-services/",
  "message": "در حال هدایت شما..."
}
```

### POST /tour
Start interactive tour guide.

**Request:**
```json
{
  "target": "order-form"
}
```

**Response:**
```json
{
  "success": true,
  "steps": [
    {
      "selector": "#book_title",
      "message": "ابتدا عنوان کتاب خود را وارد کنید",
      "arrow": "top",
      "pulse": true
    }
  ]
}
```

### POST /suggest
Get context-aware AI suggestions.

**Request:**
```json
{
  "context": {
    "page_url": "https://example.com/order-form",
    "form_data": {
      "book_size": "وزیری",
      "paper_type": "گلاسه"
    }
  },
  "guest_uuid": "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx"
}
```

**Response:**
```json
{
  "success": true,
  "suggestions": [
    {
      "text": "راهنمای تکمیل فرم",
      "action": "tour",
      "target": "order-form"
    }
  ]
}
```

## JavaScript API

### window.tabeshAIBrowserAPI
Main browser API for controlling the sidebar.

```javascript
// Open sidebar
window.tabeshAIBrowserAPI.openSidebar();

// Close sidebar
window.tabeshAIBrowserAPI.closeSidebar();

// Add message to chat
window.tabeshAIBrowserAPI.addMessage('سلام!', 'bot');

// Get guest UUID
const uuid = window.tabeshAIBrowserAPI.getGuestUUID();
```

### window.tabeshAITracker
Behavior tracking API.

```javascript
// Track custom event
window.tabeshAITracker.trackEvent('button_click', {
  button_id: 'submit',
  page_url: window.location.href
});

// Flush event queue immediately
window.tabeshAITracker.flushQueue();
```

### window.tabeshAITourGuide
Tour guide API for highlighting elements.

```javascript
// Start a pre-configured tour
window.tabeshAITourGuide.startTour('order-form');

// Highlight an element temporarily
window.tabeshAITourGuide.highlightElement('.cart-button', {
  pulse: true,
  arrow: 'left',
  tooltip: 'سبد خرید شما اینجاست',
  duration: 5000
});

// Scroll to an element smoothly
window.tabeshAITourGuide.scrollToElement($('#book_title'));

// End current tour
window.tabeshAITourGuide.endTour();
```

## Settings

### WordPress Options

**Enable/Disable AI Browser:**
```php
update_option('tabesh_ai_browser_enabled', true);
```

**Enable/Disable Behavior Tracking:**
```php
update_option('tabesh_ai_tracking_enabled', true);
```

**Configure Profession Routes:**
```php
update_option('tabesh_ai_profession_routes', array(
    'buyer'     => home_url('/order-form/'),
    'author'    => home_url('/author-services/'),
    'publisher' => home_url('/publisher-services/'),
    'printer'   => home_url('/printer-services/')
));
```

**Add Custom Tours:**
```php
$tour_guide = new Tabesh_AI_Tour_Guide();
$tour_guide->add_custom_tour('my-tour', array(
    array(
        'selector' => '#step-1',
        'message'  => 'First step',
        'arrow'    => 'top',
        'pulse'    => true
    )
));
```

## Security & Privacy

### Data Protection
- **Sanitization**: All user inputs are sanitized using WordPress functions
- **Escaping**: All outputs are escaped to prevent XSS attacks
- **Nonce Verification**: All AJAX requests require valid nonces
- **SQL Injection Protection**: All database queries use prepared statements

### GDPR Compliance
- **Tracking Opt-Out**: Users can disable tracking
- **Data Retention**: Guest profiles expire after 90 days
- **Data Cleanup**: Old behavior logs are automatically removed
- **No Sensitive Data**: Passwords and credit card info are never tracked

### Privacy Best Practices
```php
// Clean up old behavior logs (90 days)
$tracker = new Tabesh_AI_Tracker();
$tracker->cleanup_old_logs(90);

// Clean up expired guest profiles
$profile_manager = new Tabesh_AI_User_Profile();
$profile_manager->cleanup_expired_guests();
```

## Performance Optimization

### Debouncing & Batching
- **Scroll Events**: Debounced to 500ms
- **Batch Processing**: Events are batched and sent every 5 seconds
- **Queue Limit**: Maximum 10 events per batch
- **Lazy Loading**: Sidebar assets only load when needed

### Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- RTL support for Persian language
- Responsive design for all screen sizes

## Troubleshooting

### Sidebar Not Appearing
1. Check if AI Browser is enabled: `get_option('tabesh_ai_browser_enabled')`
2. Verify user has access: `Tabesh_AI_Config::user_has_access()`
3. Check browser console for JavaScript errors
4. Ensure jQuery is loaded

### Tracking Not Working
1. Check if tracking is enabled: `get_option('tabesh_ai_tracking_enabled')`
2. Verify REST API is accessible: Test `/wp-json/tabesh/v1/ai/browser/track`
3. Check nonce is valid in AJAX requests
4. Review browser console for network errors

### Tours Not Highlighting
1. Verify target element exists: `$(selector).length > 0`
2. Check element is visible: `$(selector).is(':visible')`
3. Ensure tour guide CSS is loaded
4. Review browser console for errors

## Migration & Updates

### Activating the Feature
The database tables are created automatically on plugin activation. No manual migration is required.

### Deactivating Tracking
To disable tracking for all users:
```php
update_option('tabesh_ai_tracking_enabled', false);
```

### Data Export
```php
// Export user behavior data
$tracker = new Tabesh_AI_Tracker();
$history = $tracker->get_behavior_history($user_id, '', 1000);
```

## Developer Hooks

### Filters
```php
// Modify profession routes
add_filter('tabesh_ai_profession_routes', function($routes) {
    $routes['custom_role'] = home_url('/custom-page/');
    return $routes;
});
```

### Actions
```php
// Hook into tour start
add_action('tabesh_ai_tour_started', function($target) {
    // Custom logic
});
```

## Credits

Developed by Chapco for the Tabesh WordPress plugin.
