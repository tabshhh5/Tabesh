# FTP Connection and Security Implementation Guide

## Overview

This document describes the comprehensive FTP connection improvements and security features implemented in Tabesh. The implementation ensures complete security, prevents exposure of FTP credentials, and provides a robust file management system.

## Features Implemented

### 1. Temporary File Storage with Configurable Retention

Files uploaded by users follow a controlled lifecycle:

1. **Initial Upload**: Files are stored on the website host
2. **Retention Period**: Files remain on the website host for X minutes (configurable)
3. **FTP Transfer**: After retention period, files are automatically transferred to download host
4. **Local Deletion**: After another Y minutes, local copies are automatically deleted
5. **FTP Storage**: Files remain on FTP server for downloads

**Configuration Settings**:
- `ftp_transfer_delay`: Minutes to wait before transferring to FTP (default: 60)
- `ftp_local_retention_minutes`: Minutes to keep local copy after FTP transfer (default: 120)

### 2. Secure Protocol Encryption

All file transfers can be encrypted for maximum security:

**Encryption Features**:
- **File Encryption**: Files are encrypted before FTP transfer using AES-256-CBC
- **Hidden FTP Address**: FTP server address never exposed to browser/client
- **Secure Downloads**: Files are decrypted only during serving to authorized users
- **Key Management**: Encryption keys derived from WordPress security salts

**Configuration Settings**:
- `ftp_encrypt_files`: Enable/disable file encryption before FTP transfer
- `ftp_ssl`: Use FTP over SSL/TLS for transfer

### 3. Enhanced Download Security

Downloads are handled through a secure proxy system:

**Security Features**:
- **Token-Based Auth**: Download requires time-limited token
- **Server-Side Download**: Files downloaded from FTP to web server first
- **Client Delivery**: Then served to client through secure endpoint
- **No Direct FTP**: FTP address and credentials never exposed to client
- **Access Validation**: User ownership verified before token generation

**Token Properties**:
- Time-limited (configurable expiry)
- Single-use (marked as used after download)
- User-specific (bound to authenticated user)
- Automatically cleaned up when expired

### 4. User Authentication & Access Control

Strict validation ensures users can only access their own files:

**Access Control Features**:
- User ownership verification
- Admin override (admins can access all files)
- Token validation before every download
- Security event logging
- Unauthorized access attempt tracking

**Security Logging**:
- All download attempts logged
- Unauthorized access attempts tracked
- IP address and user agent recorded
- Viewable security statistics in admin panel

### 5. Fallback Host Option

System can operate in local-only mode when FTP is unavailable:

**Fallback Features**:
- `ftp_enabled` setting toggles FTP use
- When disabled, all files stored locally
- Seamless switching between modes
- Perfect for testing and development
- No code changes required

### 6. Connection Status Display

Admin dashboard shows real-time FTP connection status:

**Status Information**:
- Connection state (Connected/Disconnected/Disabled)
- Connection uptime
- Last successful connection time
- System type information
- Refresh button for real-time updates

**Status Types**:
- **Connected**: FTP is working normally
- **Disconnected**: FTP connection failed
- **Disabled**: Local-only mode enabled
- **Not Configured**: FTP settings not completed

### 7. Full Security Assurance

The implementation ensures complete security:

**Server-Side Only**:
- All FTP operations happen on server
- No client-side FTP interactions
- FTP credentials never sent to browser
- File paths never exposed to client
- Transfer process invisible to debugging tools

**Security Measures**:
- WordPress nonce verification
- REST API authentication
- Capability checks (manage_woocommerce)
- SQL injection prevention (prepared statements)
- XSS prevention (sanitization & escaping)
- File encryption option
- Secure token generation
- Event logging for auditing

## Database Schema

### New Tables

#### `wp_tabesh_download_tokens`
Stores secure download tokens.

```sql
CREATE TABLE wp_tabesh_download_tokens (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    file_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    token_hash varchar(255) NOT NULL,
    expires_at datetime NOT NULL,
    used tinyint(1) DEFAULT 0,
    used_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY file_id (file_id),
    KEY user_id (user_id),
    KEY token_hash (token_hash),
    KEY expires_at (expires_at)
);
```

#### `wp_tabesh_security_logs`
Logs all security-related events.

```sql
CREATE TABLE wp_tabesh_security_logs (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type varchar(100) NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    file_id bigint(20) UNSIGNED DEFAULT NULL,
    ip_address varchar(50) DEFAULT NULL,
    user_agent varchar(255) DEFAULT NULL,
    description longtext DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY user_id (user_id),
    KEY file_id (file_id),
    KEY created_at (created_at)
);
```

### Enhanced Tables

#### `wp_tabesh_files` (New Columns)
Added columns for transfer tracking:

- `transfer_status`: Current transfer status (scheduled/transferred/failed)
- `scheduled_transfer_at`: When file should be transferred to FTP
- `transferred_at`: When file was successfully transferred
- `scheduled_deletion_at`: When local copy should be deleted
- `local_deleted_at`: When local copy was deleted
- `is_encrypted`: Whether file is encrypted on FTP

## API Endpoints

### Generate Download Token
```
POST /wp-json/tabesh/v1/generate-download-token
```

**Parameters**:
- `file_id` (int, required): File ID to download

**Response**:
```json
{
    "success": true,
    "download_url": "https://site.com/wp-json/tabesh/v1/download-file?file_id=123&token=abc...",
    "expires_at": "2024-01-01 12:00:00"
}
```

### Download File
```
GET /wp-json/tabesh/v1/download-file?file_id=123&token=abc...
```

**Parameters**:
- `file_id` (int, required): File ID
- `token` (string, required): Download token

**Response**: File download (binary)

### Get FTP Status
```
GET /wp-json/tabesh/v1/ftp-status
```

**Response**:
```json
{
    "success": true,
    "ftp_status": {
        "connected": true,
        "status": "connected",
        "message": "متصل",
        "uptime": "2 روز, 5 ساعت",
        "system_type": "UNIX",
        "last_success": "2024-01-01 10:00:00"
    },
    "security_stats": {
        "downloads_today": 45,
        "unauthorized_attempts_today": 2,
        "active_tokens": 12
    }
}
```

## Cron Jobs

### File Transfer Processing
- **Hook**: `tabesh_process_ftp_transfers`
- **Schedule**: Hourly
- **Function**: `Tabesh::process_ftp_transfers()`
- **Purpose**: Transfer files scheduled for FTP upload

### File Deletion Processing
- **Hook**: `tabesh_process_file_deletions`
- **Schedule**: Hourly
- **Function**: `Tabesh::process_file_deletions()`
- **Purpose**: Delete local files after retention period

### Token Cleanup
- **Hook**: `tabesh_cleanup_download_tokens`
- **Schedule**: Daily
- **Function**: `Tabesh::cleanup_download_tokens()`
- **Purpose**: Remove expired download tokens

## Configuration Guide

### Basic FTP Setup

1. Navigate to **Tabesh → Settings → FTP Settings**
2. Configure FTP connection:
   - FTP Host: Your FTP server address
   - Port: Usually 21 (default)
   - Username: FTP username
   - Password: FTP password
   - Path: Base path for file storage
3. Enable "Passive Mode" if behind firewall
4. Enable "FTP over SSL" for encrypted connection
5. Click "Test FTP Connection" to verify

### Advanced Settings

**Transfer Timing**:
- **Transfer Delay**: How long files stay on web server before FTP transfer
  - Recommended: 60 minutes
  - Shorter: Faster FTP transfer, less local storage
  - Longer: More time for validation, better user experience

- **Local Retention**: How long files stay locally after FTP transfer
  - Recommended: 120 minutes
  - Shorter: Saves disk space, requires FTP for all downloads
  - Longer: Faster downloads, uses more local storage

**Security Options**:
- **Encrypt Files**: Enable encryption before FTP transfer
  - Pros: Maximum security, protected files on FTP
  - Cons: Slight performance overhead, requires decryption on download

- **File Encryption**: Encrypt stored filenames
  - Prevents filename guessing attacks

### Fallback Mode (Local Only)

To use local-only mode:
1. Uncheck "Use FTP" in FTP Settings
2. Files will only be stored on web server
3. Perfect for testing or when FTP unavailable
4. Can switch back to FTP mode anytime

## Security Best Practices

1. **Always use FTPS**: Enable "FTP over SSL" setting
2. **Enable file encryption**: For sensitive documents
3. **Regular monitoring**: Check security logs for unauthorized access
4. **Strong FTP credentials**: Use complex passwords
5. **Firewall FTP server**: Limit access to known IPs
6. **Regular backups**: Enable auto-backup feature
7. **Monitor disk space**: Especially with longer retention periods

## Troubleshooting

### FTP Connection Issues

**Problem**: "Connection failed" status
- **Check**: FTP credentials correct
- **Check**: FTP server accessible from web server
- **Try**: Enable/disable passive mode
- **Try**: Different port (if not using 21)
- **Check**: Firewall rules on FTP server

### Files Not Transferring

**Problem**: Files stuck in "scheduled" status
- **Check**: Cron jobs are running
- **Check**: FTP connection working
- **Check**: Adequate disk space on FTP server
- **Check**: Write permissions on FTP path
- **Debug**: Enable WP_DEBUG and check error logs

### Downloads Not Working

**Problem**: Download tokens not generating
- **Check**: User is logged in
- **Check**: User owns the order/file
- **Check**: File status is "approved"
- **Debug**: Check browser console for errors

**Problem**: Token expired message
- **Check**: Token expiry setting (default 24 hours)
- **Note**: Tokens are single-use, generate new one if needed

### Performance Issues

**Problem**: Slow downloads
- **Solution**: Increase local retention time
- **Solution**: Ensure FTP server has good bandwidth
- **Check**: File encryption overhead (if enabled)

**Problem**: High disk usage
- **Solution**: Reduce retention times
- **Solution**: Enable immediate deletion after FTP transfer
- **Check**: Cleanup cron jobs running properly

## Development Notes

### File Flow Diagram

```
Upload → Local Storage → [Delay X min] → FTP Transfer → [Delay Y min] → Local Delete → FTP Storage
         ↓                                                                               ↓
         Available immediately                                                    Available via secure proxy
```

### Class Structure

- `Tabesh_File_Security`: Encryption, tokens, secure serving
- `Tabesh_FTP_Handler`: FTP operations, status monitoring
- `Tabesh_File_Manager`: File lifecycle, transfer scheduling

### Testing Checklist

- [ ] Upload file as customer
- [ ] Verify file stays local during transfer delay
- [ ] Verify FTP transfer happens automatically
- [ ] Verify local deletion after retention period
- [ ] Download file as customer
- [ ] Verify token generation
- [ ] Verify download works
- [ ] Verify token expires
- [ ] Test unauthorized access (different user)
- [ ] Test FTP status display
- [ ] Test fallback mode (FTP disabled)
- [ ] Test with encryption enabled
- [ ] Test with encryption disabled

## Support

For issues or questions:
1. Check error logs (if WP_DEBUG enabled)
2. Check security logs (in database)
3. Verify FTP connection status
4. Review configuration settings
5. Contact development team

## Version History

- **v1.0.1**: Initial FTP security implementation
  - Added file encryption
  - Added secure download tokens
  - Added transfer scheduling
  - Added connection monitoring
  - Added fallback mode
  - Added security logging
