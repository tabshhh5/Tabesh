# Staff Panel - Visual Specification

## Overview
This document provides a detailed visual specification of the redesigned Staff Panel UI.

## Color Palette

### Light Theme
- **Primary Background**: `#f0f2f5` (Light gray)
- **Secondary Background**: `#ffffff` (White)
- **Card Background**: `#ffffff` (White)
- **Primary Text**: `#1a1a1a` (Near black)
- **Secondary Text**: `#666666` (Medium gray)
- **Tertiary Text**: `#999999` (Light gray)
- **Accent Blue**: `#4a90e2` (Bright blue)
- **Accent Gold**: `#ffd700` (Gold)
- **Primary Gradient**: `linear-gradient(135deg, #667eea 0%, #764ba2 100%)` (Blue to purple)

### Dark Theme
- **Primary Background**: `#1a1a2e` (Dark navy)
- **Secondary Background**: `#16213e` (Darker navy)
- **Card Background**: `#0f3460` (Dark blue)
- **Primary Text**: `#e8e8e8` (Light gray)
- **Secondary Text**: `#b8b8b8` (Medium light gray)
- **Tertiary Text**: `#888888` (Gray)
- **Accent Blue**: `#5dade2` (Lighter blue)
- **Accent Gold**: `#f39c12` (Orange-gold)

## Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│                         HEADER                              │
│  ┌──────────────────┐              ┌────────────────────┐  │
│  │  👤 User Info    │              │ 🌙 🔔 🚪 Actions │  │
│  └──────────────────┘              └────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                      SEARCH BAR                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │  🔍 جستجو در سفارشات...                              │ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                    ORDERS GRID                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   ORDER      │  │   ORDER      │  │   ORDER      │     │
│  │   CARD 1     │  │   CARD 2     │  │   CARD 3     │     │
│  │  (Collapsed) │  │  (Collapsed) │  │  (Expanded)  │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

## Component Specifications

### 1. Header Section
**Dimensions**: Full width, fixed height (variable based on content wrap)
**Background**: Blue-purple gradient
**Color**: White text
**Shadow**: `0 4px 12px rgba(0, 0, 0, 0.15)`

**Left Section (Profile)**:
- Avatar: 50x50px circle, bordered with `rgba(255, 255, 255, 0.3)`
- Name: 18px, font-weight 600
- Welcome text: 13px, opacity 0.9

**Right Section (Actions)**:
- Theme button: Icon + text, padding 10px 15px
- Notification button: Bell icon with optional badge
- Logout button: Icon + text, padding 10px 15px
- All buttons: Semi-transparent white background, hover effect

### 2. Search Bar
**Width**: Max 1400px, centered
**Height**: Variable (min 60px with padding)
**Background**: Card background with neumorphic shadow
**Border Radius**: 16px

**Input Field**:
- Full width padding: 16px 50px 16px 20px (room for icon)
- Font size: 16px
- Border: None
- Placeholder: Persian text

**Search Icon**:
- Position: Absolute left 20px
- Size: 20px
- Color: Tertiary text color

### 3. Order Card (Collapsed State)
**Dimensions**: Min width 350px, auto height
**Background**: Card background
**Border Radius**: 16px
**Shadow**: Subtle card shadow, elevated on hover
**Transition**: 0.3s ease

**Header Section (Gradient)**:
- Background: Primary gradient
- Color: White
- Padding: 20px

**Header Content**:
- Order number: 18px, bold, top-left
- Expand icon: 24px, top-right
- Book title: 15px, below order number
- Quick info row: 13px, flex layout with icons

**Status Badge**:
- Padding: 6px 12px
- Border radius: 20px (pill shape)
- Font size: 12px, bold
- Colors by status:
  - Pending: `#f39c12` (Orange)
  - Confirmed: `#3498db` (Blue)
  - Processing: `#9b59b6` (Purple)
  - Ready: `#1abc9c` (Teal)
  - Completed: `#27ae60` (Green)
  - Cancelled: `#e74c3c` (Red)

### 4. Order Card (Expanded State)
**Grid Column Span**: Full width (1 / -1)
**Animation**: Fade in with slide down (0.3s)

**Body Section**:
- Padding: 25px
- Info grid: Auto-fit columns, min 200px
- Gap between items: 20px

**Info Item**:
- Label: 13px, secondary text color
- Value: 15px, primary text color, bold

**Extras Section**:
- Chips layout: Flex wrap
- Each chip: Background in primary background, padding 8px 15px, rounded

**Notes Section**:
- Background: Primary background
- Padding: 15px
- Border radius: 8px
- Line height: 1.6

### 5. Status Stepper
**Background**: Primary background
**Padding**: 25px
**Border Radius**: 16px
**Margin**: 25px 0

**Steps Layout**:
- Flex layout, equal width
- Connecting lines between steps

**Step Circle**:
- Size: 40x40px
- Border: 3px solid
- Border radius: 50%
- Center text: Step number
- Colors:
  - Inactive: Border and text in tertiary color
  - Active/Completed: Blue background, white text

**Step Label**:
- Font size: 12px
- Color: Secondary text (active) or tertiary text (inactive)

### 6. Status Update Section
**Background**: Primary background
**Padding**: 20px
**Border Radius**: 16px

**Layout**: Flex row with gap

**Select Dropdown**:
- Flex: 1
- Padding: 14px 18px
- Border: 2px solid tertiary text
- Border radius: 8px
- Focus: Border color changes to accent blue

**Update Button**:
- Padding: 14px 30px
- Background: Primary gradient
- Color: White
- Border: None
- Border radius: 8px
- Hover: Lift effect with shadow

### 7. Loading Overlay
**Position**: Fixed, full viewport
**Background**: `rgba(0, 0, 0, 0.5)`
**Z-index**: 9999

**Loading Content**:
- Center: White card with padding 40px
- Spinner: 50x50px rotating circle
- Text: 16px, below spinner

### 8. Toast Notification
**Position**: Fixed, top center
**Width**: Auto
**Padding**: 16px 30px
**Border Radius**: 8px
**Shadow**: Elevated
**Z-index**: 10000
**Animation**: Slide down from top (0.3s)

**Colors**:
- Success: `#27ae60` background, white text
- Error: `#e74c3c` background, white text

**Auto-dismiss**: 3 seconds

## Responsive Breakpoints

### Desktop (> 768px)
- Orders grid: Auto-fill with min 350px columns
- Header: Single row
- Stepper: Horizontal layout

### Tablet/Mobile (≤ 768px)
- Orders grid: Single column
- Header: Wrap to multiple rows
- Stepper: Vertical layout with no connecting lines
- Status update: Vertical layout
- Font sizes: Slightly reduced

### Mobile (≤ 480px)
- Search container: Reduced padding
- Panel container: Reduced padding
- Card quick info: Vertical layout

## Animations

### Card Hover
- Transform: `translateY(-5px)`
- Shadow: Elevated
- Duration: 0.3s

### Card Expand
- Animation: `fadeIn 0.3s ease-in-out`
- From: `opacity 0, translateY(-10px)`
- To: `opacity 1, translateY(0)`

### Button Hover
- Transform: `translateY(-2px)`
- Shadow: Enhanced
- Duration: 0.3s

### Theme Toggle
- Background color: 0.3s transition
- Color: 0.3s transition

## Typography

### Font Family
- Primary: 'Vazir' (Persian font)
- Fallback: 'Tahoma', Arial, sans-serif

### Font Sizes
- Page title: 28px
- Card title: 18px
- Section headers: 16px
- Body text: 15px
- Labels: 13px
- Small text: 12px

### Font Weights
- Regular: 400
- Semi-bold: 500
- Bold: 600
- Extra bold: 700

## Accessibility

### Focus Styles
- Outline: 3px solid accent blue
- Offset: 2px

### Color Contrast
- All text meets WCAG AA standards
- Minimum contrast ratio: 4.5:1

### Keyboard Navigation
- All interactive elements focusable
- Tab order follows visual layout
- Enter activates buttons
- Space toggles checkboxes

## RTL Support

### Text Direction
- All containers: `dir="rtl"`
- Text align: `text-align: right`

### Layout Adjustments
- Search icon: Left side (not right)
- Expand icon: Left side (not right)
- Padding and margins: Use logical properties where possible

### Persian Number Formatting
- All numbers displayed in Persian digits (۰-۹)
- Implemented via JavaScript utility function

## Print Styles
- Hide: Header, search, action buttons, theme toggle
- Show: Order cards with simplified styling
- Border: 1px solid for card separation
- Page break: Avoid inside cards
