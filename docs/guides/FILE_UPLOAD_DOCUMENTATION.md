# File Upload and Management System

## Overview

The Tabesh plugin now includes a comprehensive file upload and management system that allows customers to upload files related to their book printing orders, and admins to review, approve, or reject these files.

## Features

### Customer Features

1. **File Upload Interface**
   - Upload book content files (PDF)
   - Upload book cover files (PSD, PDF, JPG, PNG)
   - Upload documents (optional)
   - Real-time progress tracking
   - File validation feedback

2. **File Status Tracking**
   - View uploaded files and their status
   - See rejection reasons
   - Re-upload rejected files
   - View file version history
   - Countdown timer for rejected files

3. **Shortcode Support**
   - Use `[tabesh_file_upload order_id="123"]` to display file upload form

### Admin Features

1. **File Management Dashboard**
   - View all files for each order
   - Approve or reject files
   - Add rejection comments
   - View file validation results
   - Track file versions

2. **File Settings**
   - Configure file size limits
   - Set minimum DPI requirements
   - Define retention periods
   - Set correction fees

3. **FTP Integration**
   - Automatic file transfer to download host
   - FTP connection testing
   - Secure file transfer with SSL/TLS support
   - Customizable folder structure

## Database Schema

### wp_tabesh_files
Stores file metadata and status information.

**Columns:**
- `id` - Primary key
- `order_id` - Associated order ID
- `user_id` - File uploader user ID
- `upload_task_id` - Optional upload task reference
- `file_type` - File extension
- `file_category` - Category (book_content, book_cover, document)
- `original_filename` - Original uploaded filename
- `stored_filename` - Stored filename with version
- `file_path` - Relative file path
- `ftp_path` - FTP storage path
- `file_size` - File size in bytes
- `mime_type` - MIME type
- `version` - File version number
- `status` - File status (pending, approved, rejected)
- `validation_status` - Validation result
- `validation_data` - JSON validation details
- `rejection_reason` - Admin rejection comment
- `approved_by` - Admin user ID who approved
- `approved_at` - Approval timestamp
- `deleted_at` - Soft delete timestamp
- `expires_at` - Expiration timestamp for rejected files
- `created_at` - Upload timestamp
- `updated_at` - Last update timestamp

### wp_tabesh_file_versions
Tracks all versions of each file.

**Columns:**
- `id` - Primary key
- `file_id` - Reference to wp_tabesh_files
- `version` - Version number
- `stored_filename` - Filename for this version
- `file_path` - File path for this version
- `file_size` - File size
- `status` - Version status
- `uploaded_by` - Uploader user ID
- `uploaded_at` - Upload timestamp

### wp_tabesh_upload_tasks
Defines upload requirements for orders.

**Columns:**
- `id` - Primary key
- `order_id` - Associated order ID
- `task_title` - Task title
- `task_type` - Task type
- `allowed_file_types` - JSON array of allowed types
- `min_file_size` - Minimum size in bytes
- `max_file_size` - Maximum size in bytes
- `min_file_count` - Minimum files required
- `max_file_count` - Maximum files allowed
- `min_width` - Minimum image width
- `max_width` - Maximum image width
- `min_height` - Minimum image height
- `max_height` - Maximum image height
- `required_color_mode` - Required color mode (CMYK, RGB)
- `status` - Task status
- `created_at` - Creation timestamp

## REST API Endpoints

### POST /wp-json/tabesh/v1/upload-file
Upload a file for an order.

**Parameters:**
- `file` - File data (multipart/form-data)
- `order_id` - Order ID
- `file_category` - Category (book_content, book_cover, document)
- `upload_task_id` - Optional task ID

**Response:**
```json
{
  "success": true,
  "message": "فایل با موفقیت آپلود شد",
  "file_id": 123,
  "version": 1,
  "filename": "book-content.pdf"
}
```

### POST /wp-json/tabesh/v1/validate-file
Validate an uploaded file.

**Parameters:**
- `file_id` - File ID

**Response:**
```json
{
  "success": true,
  "errors": [],
  "warnings": ["اندازه صفحات PDF با قطع سفارش مطابقت ندارد"],
  "data": {
    "page_count": 150,
    "correction_fee": 50000,
    "requires_confirmation": true
  }
}
```

### POST /wp-json/tabesh/v1/approve-file
Approve a file (Admin only).

**Parameters:**
- `file_id` - File ID

**Response:**
```json
{
  "success": true,
  "message": "فایل با موفقیت تایید شد"
}
```

### POST /wp-json/tabesh/v1/reject-file
Reject a file with reason (Admin only).

**Parameters:**
- `file_id` - File ID
- `reason` - Rejection reason

**Response:**
```json
{
  "success": true,
  "message": "فایل رد شد"
}
```

### GET /wp-json/tabesh/v1/order-files/{order_id}
Get all files for an order.

**Response:**
```json
{
  "success": true,
  "files": [
    {
      "id": 123,
      "order_id": 456,
      "file_category": "book_content",
      "original_filename": "book.pdf",
      "file_size": 5242880,
      "version": 2,
      "status": "approved",
      "created_at": "2025-01-15 10:30:00"
    }
  ]
}
```

### DELETE /wp-json/tabesh/v1/delete-file/{file_id}
Soft delete a file.

**Response:**
```json
{
  "success": true,
  "message": "فایل حذف شد"
}
```

### POST /wp-json/tabesh/v1/test-ftp-connection
Test FTP connection (Admin only).

**Parameters:**
- `ftp_host` - FTP host
- `ftp_port` - FTP port
- `ftp_username` - FTP username
- `ftp_password` - FTP password
- `ftp_path` - Base path
- `ftp_passive` - Use passive mode
- `ftp_ssl` - Use SSL/TLS

**Response:**
```json
{
  "success": true,
  "message": "اتصال FTP با موفقیت برقرار شد"
}
```

## File Validation

### Book Content (PDF)

The system validates:
1. **Page Count**: Must match order page count (±2 pages tolerance)
2. **Page Size**: Must match book size (A4, A5, etc.)
3. **White Margins**: Checks for excessive margins
4. **Image Pages**: Counts pages containing images
5. **Color Pages**: Validates color page numbers if applicable

### Book Cover

The system validates:
1. **File Format**: Must be PSD, PDF, JPG, or PNG
2. **Resolution**: Minimum 300 DPI
3. **Color Mode**: Prefers CMYK for print quality

### Documents

The system validates:
1. **File Format**: Must be PDF, JPG, or PNG
2. **File Size**: Must be within limits

## File Storage

### Local Storage Structure
```
/wp-content/uploads/tabesh-files/
├── user-{user_id}/
│   └── order-{order_id}/
│       ├── book_content/
│       │   ├── filename.pdf
│       │   ├── filename-v2.pdf
│       │   └── filename-v3.pdf
│       ├── book_cover/
│       │   └── cover.psd
│       └── document/
│           └── certificate.pdf
```

### FTP Storage Structure
```
{ftp_path}/
├── user-{user_id}/
│   └── order-{order_id}/
│       ├── book_content/
│       ├── book_cover/
│       └── document/
```

## File Versioning

- Each new upload creates a new version
- Versions are numbered: file.pdf, file-v2.pdf, file-v3.pdf
- Old versions are kept for 5 days after approval
- Only the approved version is kept permanently
- Rejected versions expire after retention period

## Security

1. **Authentication**: Only logged-in users can upload files
2. **Authorization**: Users can only access their own order files
3. **Nonce Verification**: All requests use WordPress nonces
4. **File Type Validation**: Only allowed file types are accepted
5. **File Size Limits**: Enforced server-side
6. **Protected Storage**: Files stored outside web root with .htaccess protection
7. **SQL Injection Prevention**: All queries use prepared statements
8. **XSS Prevention**: All output is escaped

## Configuration

### File Settings (Admin → Tabesh → Settings → File Settings)

**File Size Limits:**
- PDF files: 50 MB (default)
- Images: 10 MB (default)
- Documents: 10 MB (default)
- Archives: 100 MB (default)

**Validation Settings:**
- Minimum DPI: 300 (default)
- Retention days: 5 (default)
- Correction fee: 50,000 Rials (default)

**FTP Settings:**
- Host: FTP server address
- Port: 21 (default)
- Username: FTP username
- Password: FTP password
- Path: Base upload path
- Passive mode: Enabled (default)
- SSL/TLS: Disabled (default)

## Cron Jobs

### Automatic File Cleanup
- **Schedule**: Daily
- **Hook**: `tabesh_cleanup_expired_files`
- **Function**: Deletes files that have exceeded retention period
- **Log**: Logs deletion count when WP_DEBUG is enabled

## Usage Examples

### Customer File Upload

```php
// Display file upload form for an order
echo do_shortcode('[tabesh_file_upload order_id="123"]');
```

### Admin File Management

```php
// Include in order details page
$order_id = 123;
include TABESH_PLUGIN_DIR . 'templates/file-management-admin.php';
```

### Programmatic File Upload

```php
// Get file manager instance
$file_manager = Tabesh()->file_manager;

// Upload a file
$result = $file_manager->upload_file(
    $_FILES['file'],
    $order_id,
    $user_id,
    'book_content'
);

if ($result['success']) {
    echo 'File uploaded: ' . $result['file_id'];
}
```

### Approve/Reject Files

```php
// Approve a file
$result = $file_manager->approve_file($file_id, $admin_id);

// Reject a file
$result = $file_manager->reject_file($file_id, $admin_id, 'Quality issues');
```

## Troubleshooting

### Files not uploading
1. Check PHP upload_max_filesize and post_max_size
2. Verify wp-content/uploads/tabesh-files/ is writable
3. Check WordPress debug log for errors
4. Verify user is logged in and has permission

### FTP transfer failing
1. Test FTP connection in settings
2. Verify firewall allows FTP connection
3. Check FTP credentials
4. Try disabling passive mode
5. Enable FTP debug logging

### File validation errors
1. Check file meets requirements (DPI, size, format)
2. Review validation messages for specific issues
3. Ensure PDF is not corrupted
4. Verify color mode for cover files

### Permission denied errors
1. Check wp-content/uploads permissions (0755)
2. Verify web server user owns upload directory
3. Check .htaccess file is present
4. Review WordPress file permissions

## Future Enhancements

Potential features for future versions:
- Drag-and-drop file upload
- Bulk file operations
- File preview/thumbnail generation
- Advanced PDF analysis (fonts, colors, etc.)
- Image optimization and conversion
- Integration with cloud storage (S3, Google Drive)
- File encryption at rest
- Digital signatures for files
- OCR for document verification
- Automated backup to multiple locations

## Support

For issues or questions about the file upload system:
1. Check the documentation above
2. Review troubleshooting section
3. Enable WP_DEBUG to see detailed error messages
4. Contact support with debug log information
