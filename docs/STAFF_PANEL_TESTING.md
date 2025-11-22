# Staff Panel Testing Checklist

Use this checklist to verify all features of the redesigned staff panel are working correctly.

## Prerequisites
- [ ] WordPress 6.8+ installed
- [ ] WooCommerce installed and activated
- [ ] Tabesh plugin activated
- [ ] Test user with `edit_shop_orders` capability created
- [ ] Test user with `manage_woocommerce` capability created
- [ ] At least 5 test orders created with various statuses

## Access & Permissions

### Staff User (edit_shop_orders capability)
- [ ] Can access staff panel page with `[tabesh_staff_panel]` shortcode
- [ ] Cannot see financial information (prices)
- [ ] Can see customer names only (no contact/address)
- [ ] Can see all order details except financial/personal

### Admin User (manage_woocommerce capability)
- [ ] Can access staff panel
- [ ] Can see all financial information
- [ ] Can see complete customer information
- [ ] Has all staff permissions plus additional data

### Non-privileged User
- [ ] Cannot access staff panel
- [ ] Sees "access denied" message

## UI Components

### Header
- [ ] Avatar displays correctly
- [ ] Username displays correctly
- [ ] Welcome message shows: "خوش آمدید به پنل کارمندان"
- [ ] Theme toggle button visible (🌙 icon for light mode)
- [ ] Notification button visible
- [ ] Logout button visible and functional

### Search Bar
- [ ] Search input field visible and styled
- [ ] Search icon (🔍) visible on the left side (RTL)
- [ ] Placeholder text: "جستجو در سفارشات..."
- [ ] Input accepts Persian/English text

### Order Cards (Collapsed State)
- [ ] Cards display in grid layout (3 columns on desktop)
- [ ] Each card shows:
  - [ ] Order number
  - [ ] Book title (if available)
  - [ ] Book size icon and value
  - [ ] Quantity icon and value
  - [ ] Status badge with correct color
  - [ ] Expand icon (▼)
- [ ] Cards have gradient header (blue-purple)
- [ ] Cards have shadow effect
- [ ] Hover effect: card lifts slightly

### Order Cards (Expanded State)
- [ ] Click on card header expands the card
- [ ] Click again collapses the card
- [ ] Only one card expanded at a time
- [ ] Expanded card takes full width
- [ ] Smooth animation when expanding/collapsing
- [ ] Shows all order details in grid layout
- [ ] Extras shown as chips (if any)
- [ ] Notes shown in styled box (if any)
- [ ] Status stepper visible
- [ ] Status update section visible

## Search Functionality

### Basic Search
- [ ] Type in search box triggers search after 500ms delay
- [ ] Search works for order numbers
- [ ] Search works for book titles
- [ ] Search works for book sizes
- [ ] Search works for customer names
- [ ] Search result count displays correctly
- [ ] "نتیجه‌ای یافت نشد" shows when no results

### Search Results Display
- [ ] Shows first 3 results initially
- [ ] "نمایش بیشتر" button appears if more than 3 results
- [ ] Clicking "نمایش بیشتر" shows next 3 results
- [ ] Button disappears when all results shown
- [ ] Clear search (empty input) shows all orders
- [ ] Results sorted by relevance (exact matches first)

### Persian Number Support
- [ ] Search result count shows in Persian digits (۱۲۳)
- [ ] Works with both English and Persian number input

## Status Management

### Status Stepper
- [ ] Shows 5 steps: در انتظار, تایید شده, در حال چاپ, آماده, تحویل
- [ ] Current status highlighted in blue
- [ ] Completed steps have blue background
- [ ] Pending steps have gray border
- [ ] Steps are clickable
- [ ] Clicking step sets dropdown to that status
- [ ] Visual feedback on click (pulse animation)

### Status Update
- [ ] Dropdown shows all available statuses
- [ ] Current status can be changed
- [ ] "به‌روزرسانی وضعیت" button visible
- [ ] Clicking button shows confirmation dialog
- [ ] Confirmation text: "آیا از تغییر وضعیت این سفارش اطمینان دارید؟"
- [ ] Cancel in dialog keeps old status
- [ ] Confirm updates status via AJAX
- [ ] Loading spinner shows during update
- [ ] Success toast appears: "وضعیت با موفقیت تغییر کرد"
- [ ] Status badge updates without page refresh
- [ ] Stepper updates without page refresh
- [ ] Dropdown resets to default value
- [ ] Order doesn't disappear from view

### Status Update Errors
- [ ] Error handling if server unreachable
- [ ] Error toast shows: "خطا در برقراری ارتباط با سرور"
- [ ] Error for no internet: "اتصال اینترنت را بررسی کنید"
- [ ] Error for no permission: "شما مجوز انجام این عملیات را ندارید"

## Theme Toggle

### Light Theme (Default)
- [ ] Background is light gray (#f0f2f5)
- [ ] Cards are white
- [ ] Text is dark
- [ ] Shadows are subtle
- [ ] Theme button shows 🌙 icon

### Dark Theme
- [ ] Background is dark navy (#1a1a2e)
- [ ] Cards are dark blue (#0f3460)
- [ ] Text is light
- [ ] Shadows are deeper
- [ ] Theme button shows ☀️ icon

### Theme Persistence
- [ ] Toggle theme to dark
- [ ] Refresh page
- [ ] Theme stays dark
- [ ] Toggle back to light
- [ ] Refresh page
- [ ] Theme stays light

## Responsive Design

### Desktop (> 768px)
- [ ] Header in single row
- [ ] Search bar centered with max width
- [ ] Order cards in 3-column grid
- [ ] Stepper horizontal layout
- [ ] Status update section horizontal

### Tablet (≤ 768px)
- [ ] Header wraps to multiple rows
- [ ] Order cards in single column
- [ ] Stepper vertical layout
- [ ] Status update section vertical

### Mobile (≤ 480px)
- [ ] All text readable
- [ ] Buttons touch-friendly (min 44px)
- [ ] Search input full width
- [ ] Cards stack nicely
- [ ] No horizontal scroll

## Browser Testing

### Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Mobile Browsers
- [ ] Chrome Mobile (Android)
- [ ] Safari (iOS)
- [ ] Firefox Mobile

## Performance

### Loading
- [ ] Initial page load < 2 seconds
- [ ] Search response < 500ms
- [ ] Status update < 1 second
- [ ] Theme toggle instant
- [ ] Card expand/collapse smooth (< 300ms)

### No Performance Issues
- [ ] No memory leaks
- [ ] No excessive DOM manipulation
- [ ] Smooth scrolling
- [ ] No layout shifts

## Accessibility

### Keyboard Navigation
- [ ] Tab through all interactive elements
- [ ] Focus indicators visible
- [ ] Enter activates buttons
- [ ] Escape closes expanded cards (if implemented)

### Screen Reader
- [ ] All images have alt text
- [ ] Buttons have descriptive labels
- [ ] Form fields have associated labels
- [ ] Status changes announced

### Color Contrast
- [ ] All text meets WCAG AA (4.5:1)
- [ ] Status badges readable
- [ ] Links distinguishable

## RTL Support

### Layout
- [ ] All text aligned right
- [ ] Search icon on left side
- [ ] Expand icon on left side
- [ ] Number formatting correct

### Persian Text
- [ ] All labels in Persian
- [ ] Date format: Y/m/d - H:i
- [ ] Number formatting: ۱۲۳۴
- [ ] Currency format: 1,234 تومان

## Security

### Authentication
- [ ] Nonce verification on all AJAX
- [ ] REST API requires authentication
- [ ] Unauthorized users blocked

### Data Protection
- [ ] No SQL injection possible
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] No XSS vulnerabilities

### Authorization
- [ ] Staff can only see allowed data
- [ ] Financial data hidden from staff
- [ ] Admin sees all data
- [ ] Permission checks working

## Edge Cases

### Empty States
- [ ] No orders: Shows "هیچ سفارش فعالی برای پردازش وجود ندارد"
- [ ] Search no results: Shows "نتیجه‌ای یافت نشد"

### Data Validation
- [ ] Missing book title: Handled gracefully
- [ ] Missing extras: No section shown
- [ ] Missing notes: No section shown
- [ ] Invalid status: Error handled

### Network Issues
- [ ] Slow connection: Shows loading
- [ ] Connection lost: Error message
- [ ] Timeout: Error message
- [ ] Retry mechanism works

## Integration Testing

### WordPress Integration
- [ ] Works with Gutenberg editor
- [ ] Works with classic editor
- [ ] Shortcode renders correctly
- [ ] No conflicts with other plugins

### WooCommerce Integration
- [ ] Order data syncs correctly
- [ ] Status changes reflected in WooCommerce
- [ ] Customer data retrieved correctly

## Final Verification

- [ ] No JavaScript console errors
- [ ] No PHP errors in debug log
- [ ] No 404 requests for CSS/JS files
- [ ] All images/icons load correctly
- [ ] Page validates as HTML5
- [ ] CSS validates (W3C)
- [ ] No broken links

## Sign-off

**Tester Name**: _______________
**Date**: _______________
**Version Tested**: _______________
**Environment**: _______________

**Overall Status**: 
- [ ] PASS - All tests passed
- [ ] PASS WITH NOTES - Most tests passed (see notes below)
- [ ] FAIL - Critical issues found

**Notes**:
```

```

**Bugs Found**:
```

```

**Recommendations**:
```

```
