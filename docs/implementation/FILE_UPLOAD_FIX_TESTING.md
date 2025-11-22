# File Upload Fix Testing Guide

## Overview
This guide provides instructions for testing the file upload fixes in the Customer Files Panel.

## Issues Fixed

### 1. Document File Selection Issue
**Problem**: Document file input was missing `id` and `for` attributes, preventing the file picker from opening when clicking the label.

**Fix**: Added unique `id` to input and matching `for` to label with dynamic ID generation.

### 2. Critical jQuery Selector Bug
**Problem**: Three functions used incorrect jQuery selectors that tried to find `.file-upload-area` inside `.file-upload-area`, which returned empty objects and broke all upload functionality.

**Affected Functions**:
- `handleFileSelection()` - Prevented file info display after selection
- `removeSelectedFile()` - Prevented clearing selected files
- `handleFileUpload()` - Prevented actual file uploads

**Fix**: Corrected selectors to use `$element.closest('.file-upload-area')` instead of `$element.closest('.file-upload-area').find('.file-upload-area').first()`.

## Files Modified

1. **templates/partials/file-upload-documents.php**
   - Added `id="file-document-<?php echo esc_attr($order->id); ?>-{{UNIQUE_ID}}"` to file input
   - Added `for="file-document-<?php echo esc_attr($order->id); ?>-{{UNIQUE_ID}}"` to label

2. **assets/js/customer-files-panel.js**
   - Fixed jQuery selector in `handleFileSelection()` (line 101)
   - Fixed jQuery selector in `removeSelectedFile()` (line 141)
   - Fixed jQuery selector in `handleFileUpload()` (line 158)
   - Added unique ID generation in `addNewDocument()` (line 432)

## Testing Instructions

### Prerequisites
1. WordPress installation with Tabesh plugin activated
2. User account with active orders
3. Browser with Developer Tools enabled
4. Clear browser cache before testing

### Test 1: Content File Upload

**Steps**:
1. Navigate to Customer Files Panel (shortcode: `[tabesh_customer_files_panel]`)
2. Expand an order row
3. Go to "محتوای کتاب" (Book Content) section
4. Click on "انتخاب فایل PDF" label
5. **Expected**: File picker dialog opens
6. Select a PDF file
7. **Expected**: 
   - File name and size appear below the dropzone
   - Dropzone is hidden
   - "آپلود و اعتبارسنجی" button becomes enabled
8. Click "آپلود و اعتبارسنجی" button
9. **Expected**: 
   - Progress bar shows
   - Upload completes
   - Page reloads showing the uploaded file

### Test 2: Cover File Upload

**Steps**:
1. Navigate to Customer Files Panel
2. Expand an order row
3. Go to "جلد کتاب" (Book Cover) section
4. Click on "انتخاب فایل جلد" label
5. **Expected**: File picker dialog opens
6. Select an image file (PSD, PDF, JPG, or PNG)
7. **Expected**: 
   - File name and size appear
   - Dropzone is hidden
   - "آپلود و بررسی کیفیت" button becomes enabled
8. Click upload button
9. **Expected**: Upload completes successfully

### Test 3: Document File Upload (Critical Fix)

**Steps**:
1. Navigate to Customer Files Panel
2. Expand an order row
3. Go to "مدارک" (Documents) section
4. Click "افزودن مدرک جدید" (Add New Document)
5. Select document type from dropdown
6. Click on "انتخاب فایل" label in the document form
7. **Expected**: File picker dialog opens (THIS WAS BROKEN BEFORE)
8. Select a file (PDF, JPG, or PNG)
9. **Expected**: 
   - File name appears
   - "آپلود مدرک" button becomes enabled
10. Click "آپلود مدرک" button
11. **Expected**: Document uploads successfully

### Test 4: Drag and Drop

**Steps**:
1. Navigate to Customer Files Panel
2. Expand an order row
3. For any file section (Content, Cover, or Documents):
   - Drag a valid file over the upload area
   - **Expected**: Upload area highlights with "drag-over" class
   - Drop the file
   - **Expected**: 
     - File is selected
     - UI updates as if file was selected via file picker
     - Upload button becomes enabled

### Test 5: Remove Selected File

**Steps**:
1. Select a file using any method
2. Click the remove button (X icon) next to file info
3. **Expected**:
   - File input is cleared
   - File info section is hidden
   - Dropzone reappears
   - Upload button becomes disabled

### Test 6: Multiple Documents

**Steps**:
1. Add 2-3 new document forms
2. **Expected**: Each has a unique ID (check in browser DevTools)
3. Select files in each form
4. **Expected**: Each form's file selection works independently
5. Upload documents
6. **Expected**: All upload successfully

## Debugging

### Browser Console Checks

1. Open DevTools Console (F12)
2. Look for errors starting with "Tabesh"
3. When selecting a file, you should see:
   - No errors about "cannot read property of undefined"
   - File info updates in the DOM

### Network Tab Checks

1. Open DevTools Network tab
2. Filter by "upload-file"
3. When uploading, check:
   - Request is POST to `/wp-json/tabesh/v1/upload-file`
   - Request payload includes file data
   - Response is 200 with `{success: true}`

### Common Issues

**Issue**: File picker doesn't open for documents
- **Solution**: Verify browser console shows unique IDs were generated
- **Check**: Inspect element and verify `id` and `for` attributes match

**Issue**: Upload button stays disabled after selecting file
- **Solution**: Check browser console for jQuery errors
- **Verify**: `$uploadArea` is not an empty jQuery object

**Issue**: Upload fails with network error
- **Solution**: Verify REST API is accessible at `/wp-json/tabesh/v1/upload-file`
- **Check**: WordPress REST API is not blocked by security plugins

## Expected Results

After these fixes:
✅ All file types (content, cover, documents) can be selected via clicking labels
✅ All file types can be uploaded successfully
✅ Document file picker works (was completely broken before)
✅ File selection updates UI properly
✅ Upload progress displays correctly
✅ Files can be removed and reselected

## Technical Details

### HTML Structure
```html
<div class="upload-area-wrapper">
  <div class="file-upload-area" data-category="book_content">
    <div class="upload-dropzone">
      <input type="file" id="file-content-123" class="file-input">
      <label for="file-content-123" class="dropzone-label">...</label>
    </div>
    <div class="file-info" style="display: none;">...</div>
    <button class="upload-btn" disabled>Upload</button>
  </div>
</div>
```

### jQuery Selector Fix
**Before** (BROKEN):
```javascript
const $uploadArea = $btn.closest('.file-upload-area').find('.file-upload-area').first();
// Returns empty jQuery object because .file-upload-area has no child .file-upload-area
```

**After** (FIXED):
```javascript
const $uploadArea = $btn.closest('.file-upload-area');
// Returns the correct parent element
```

## Rollback Instructions

If issues arise, the changes can be reverted:
```bash
git revert HEAD  # Revert last commit
# or
git checkout HEAD~2 -- assets/js/customer-files-panel.js templates/partials/file-upload-documents.php
```

## Additional Notes

- These fixes are minimal and surgical
- No changes to backend PHP upload handling
- No changes to REST API endpoints
- Only frontend JavaScript and HTML template changes
- Backward compatible with existing uploaded files
