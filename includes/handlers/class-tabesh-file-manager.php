<?php
/**
 * File Manager Class
 *
 * Handles file upload, storage, versioning, and management
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_File_Manager {

    /**
     * Upload directory path
     *
     * @var string
     */
    private $upload_dir;

    /**
     * Upload directory URL
     *
     * @var string
     */
    private $upload_url;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/tabesh-files/';
        $this->upload_url = $upload_dir['baseurl'] . '/tabesh-files/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess to protect files
            $htaccess_content = "# Tabesh Files Protection\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
        }
    }

    /**
     * Get file by ID
     *
     * @param int $file_id File ID
     * @return object|null File object or null if not found
     */
    public function get_file($file_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $file_id
        ));
    }

    /**
     * Get files by order ID
     *
     * @param int $order_id Order ID
     * @param string $status Optional status filter
     * @return array Array of file objects
     */
    public function get_order_files($order_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE order_id = %d AND status = %s AND deleted_at IS NULL ORDER BY created_at DESC",
                $order_id,
                $status
            ));
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d AND deleted_at IS NULL ORDER BY created_at DESC",
            $order_id
        ));
    }

    /**
     * Get file versions
     *
     * @param int $file_id File ID
     * @return array Array of version objects
     */
    public function get_file_versions($file_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_file_versions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE file_id = %d ORDER BY version DESC",
            $file_id
        ));
    }

    /**
     * Get deletion countdown info for a rejected file
     *
     * Returns time remaining before file deletion and formatting
     *
     * @param object $file File object
     * @return array Countdown information
     */
    public function get_file_deletion_countdown($file) {
        if ($file->status !== 'rejected' || empty($file->expires_at)) {
            return array(
                'has_countdown' => false,
                'message' => ''
            );
        }
        
        // Check if file is already deleted
        if (!empty($file->deleted_at)) {
            return array(
                'has_countdown' => false,
                'is_deleted' => true,
                'message' => __('این فایل حذف شده است و دیگر قابل دانلود نیست.', 'tabesh')
            );
        }
        
        $now = current_time('timestamp');
        $expires_timestamp = strtotime($file->expires_at);
        $time_remaining = $expires_timestamp - $now;
        
        if ($time_remaining <= 0) {
            return array(
                'has_countdown' => false,
                'expired' => true,
                'message' => __('زمان نگهداری این فایل به پایان رسیده و به زودی حذف خواهد شد.', 'tabesh')
            );
        }
        
        // Calculate days, hours, minutes
        $days = floor($time_remaining / 86400);
        $hours = floor(($time_remaining % 86400) / 3600);
        $minutes = floor(($time_remaining % 3600) / 60);
        
        // Format message
        $parts = array();
        if ($days > 0) {
            $parts[] = sprintf(_n('%d روز', '%d روز', $days, 'tabesh'), $days);
        }
        if ($hours > 0) {
            $parts[] = sprintf(_n('%d ساعت', '%d ساعت', $hours, 'tabesh'), $hours);
        }
        if ($minutes > 0 && $days == 0) {
            $parts[] = sprintf(_n('%d دقیقه', '%d دقیقه', $minutes, 'tabesh'), $minutes);
        }
        
        $time_string = !empty($parts) ? implode(' و ', $parts) : __('کمتر از یک دقیقه', 'tabesh');
        
        return array(
            'has_countdown' => true,
            'seconds_remaining' => $time_remaining,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'expires_at' => $file->expires_at,
            'message' => sprintf(__('این فایل در %s حذف خواهد شد.', 'tabesh'), $time_string)
        );
    }

    /**
     * Upload file
     *
     * @param array $file_data $_FILES array data
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param string $file_category File category (book_content, book_cover, document)
     * @param int|null $upload_task_id Upload task ID (optional)
     * @return array Result array with success status and data
     */
    public function upload_file($file_data, $order_id, $user_id, $file_category, $upload_task_id = null) {
        // Verify nonce - skip for REST API requests as they use X-WP-Nonce header verification
        // Check both REST_REQUEST constant and the request URI pattern for robustness
        $is_rest_request = (defined('REST_REQUEST') && REST_REQUEST) || 
                          (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false);
        
        if (!$is_rest_request) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tabesh_file_upload')) {
                return array(
                    'success' => false,
                    'message' => __('امنیتی: درخواست معتبر نیست', 'tabesh')
                );
            }
        }

        // Validate user permissions
        // CRITICAL: This check ensures ANY logged-in user (admin, staff, customer, subscriber, etc.)
        // can upload files, as long as they are authenticated.
        // In REST API context, user might be authenticated via nonce but is_user_logged_in() might return false,
        // so we check get_current_user_id() which works in both regular and REST API contexts.
        // 
        // This check ensures the authenticated user matches the user_id parameter passed to this function
        // to prevent unauthorized file uploads on behalf of other users.
        // When called from REST API, $user_id is always set to get_current_user_id() in the handler,
        // ensuring the upload is attributed to the currently authenticated user.
        $current_user_id = get_current_user_id();
        if ($current_user_id <= 0 || $current_user_id != $user_id) {
            return array(
                'success' => false,
                'message' => __('شما مجاز به آپلود فایل نیستید', 'tabesh')
            );
        }

        // Validate order belongs to user (unless user is admin)
        // CRITICAL: ALL logged-in users (customers, subscribers, etc.) can upload files for their own orders!
        // Only admins and shop managers can upload files for ANY order (including orders they don't own).
        // The file will be attributed to the current user (stored with their user_id) which is correct
        // for audit trail purposes as it shows who actually performed the upload.
        $is_admin = current_user_can('manage_woocommerce') || current_user_can('manage_options');
        if (!$is_admin && !$this->verify_order_ownership($order_id, $user_id)) {
            return array(
                'success' => false,
                'message' => __('سفارش متعلق به شما نیست', 'tabesh')
            );
        }

        // Check file errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => $this->get_upload_error_message($file_data['error'])
            );
        }

        // Validate file type
        $file_ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        $allowed_types = Tabesh()->get_setting('file_allowed_types', array('pdf', 'jpg', 'jpeg', 'png', 'psd', 'doc', 'docx', 'zip', 'rar'));
        
        if (!in_array($file_ext, $allowed_types)) {
            return array(
                'success' => false,
                'message' => sprintf(__('فرمت فایل مجاز نیست. فرمت‌های مجاز: %s', 'tabesh'), implode(', ', $allowed_types))
            );
        }

        // Validate file size
        $max_size = $this->get_max_file_size($file_ext);
        if ($file_data['size'] > $max_size) {
            return array(
                'success' => false,
                'message' => sprintf(__('حجم فایل بیش از حد مجاز است. حداکثر: %s', 'tabesh'), size_format($max_size))
            );
        }

        // Create user folder structure
        $user_folder = $this->upload_dir . 'user-' . $user_id . '/';
        $order_folder = $user_folder . 'order-' . $order_id . '/';
        $category_folder = $order_folder . $file_category . '/';
        
        if (!file_exists($category_folder)) {
            wp_mkdir_p($category_folder);
        }

        // Check if file exists and get next version number
        $existing_file = $this->get_existing_file($order_id, $file_category, pathinfo($file_data['name'], PATHINFO_FILENAME));
        $version = $existing_file ? $existing_file->version + 1 : 1;

        // Generate unique filename with version
        $filename = pathinfo($file_data['name'], PATHINFO_FILENAME);
        $filename = sanitize_file_name($filename);
        
        // Check if filename encryption is enabled
        $encrypt_filenames = Tabesh()->get_setting('file_encrypt_filenames', '0');
        if ($encrypt_filenames == '1') {
            // Generate encrypted filename
            $filename = 'file_' . md5($filename . time() . $user_id);
        }
        
        if ($version > 1) {
            $stored_filename = $filename . '-v' . $version . '.' . $file_ext;
        } else {
            $stored_filename = $filename . '.' . $file_ext;
        }
        
        $file_path = $category_folder . $stored_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file_data['tmp_name'], $file_path)) {
            return array(
                'success' => false,
                'message' => __('خطا در ذخیره فایل', 'tabesh')
            );
        }

        // Set file permissions
        chmod($file_path, 0644);

        // Store file metadata in database
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file_data_db = array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'upload_task_id' => $upload_task_id,
            'file_type' => $file_ext,
            'file_category' => $file_category,
            'original_filename' => sanitize_text_field($file_data['name']),
            'stored_filename' => $stored_filename,
            'file_path' => str_replace($this->upload_dir, '', $file_path),
            'file_size' => $file_data['size'],
            'mime_type' => sanitize_text_field($file_data['type']),
            'version' => $version,
            'status' => 'pending',
        );

        if ($existing_file) {
            // Update existing file record
            $wpdb->update(
                $table,
                $file_data_db,
                array('id' => $existing_file->id)
            );
            $file_id = $existing_file->id;
        } else {
            // Insert new file record
            $wpdb->insert($table, $file_data_db);
            $file_id = $wpdb->insert_id;
        }

        // Store version in versions table
        $versions_table = $wpdb->prefix . 'tabesh_file_versions';
        $wpdb->insert($versions_table, array(
            'file_id' => $file_id,
            'version' => $version,
            'stored_filename' => $stored_filename,
            'file_path' => str_replace($this->upload_dir, '', $file_path),
            'file_size' => $file_data['size'],
            'status' => 'pending',
            'uploaded_by' => $user_id,
        ));

        // Log the action
        $this->log_action($order_id, $user_id, 'file_uploaded', sprintf(
            __('فایل "%s" نسخه %d آپلود شد', 'tabesh'),
            $stored_filename,
            $version
        ));

        return array(
            'success' => true,
            'message' => __('فایل با موفقیت آپلود شد', 'tabesh'),
            'file_id' => $file_id,
            'version' => $version,
            'filename' => $stored_filename
        );
    }

    /**
     * Approve file
     *
     * @param int $file_id File ID
     * @param int $admin_id Admin user ID
     * @return array Result array
     */
    public function approve_file($file_id, $admin_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $this->get_file($file_id);
        if (!$file) {
            return array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            );
        }

        // Update file status
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'approved',
                'approved_by' => $admin_id,
                'approved_at' => current_time('mysql'),
                'rejection_reason' => null,
                'expires_at' => null  // Clear expiration since file is approved
            ),
            array('id' => $file_id)
        );

        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('خطا در تایید فایل', 'tabesh')
            );
        }

        // Delete previous versions immediately (keep retention only for project completion)
        $this->delete_previous_versions($file_id, $file->order_id, $file->file_category);

        // Log the action
        $this->log_action($file->order_id, $admin_id, 'file_approved', sprintf(
            __('فایل "%s" تایید شد', 'tabesh'),
            $file->stored_filename
        ));

        // Schedule file transfer to FTP if configured
        $ftp_enabled = Tabesh()->get_setting('ftp_enabled', '1');
        if ($ftp_enabled == '1') {
            $this->schedule_ftp_transfer($file);
        }

        return array(
            'success' => true,
            'message' => __('فایل با موفقیت تایید شد', 'tabesh')
        );
    }

    /**
     * Delete previous versions of a file
     *
     * When a new version is approved, previous versions are deleted immediately
     *
     * @param int $file_id Current file ID
     * @param int $order_id Order ID
     * @param string $file_category File category
     * @return int Number of files deleted
     */
    private function delete_previous_versions($file_id, $order_id, $file_category) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get current file version
        $current_file = $this->get_file($file_id);
        if (!$current_file) {
            return 0;
        }
        
        // Get all other files of same category and order with lower version
        $old_files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE order_id = %d 
             AND file_category = %s 
             AND id != %d 
             AND version < %d 
             AND deleted_at IS NULL",
            $order_id,
            $file_category,
            $file_id,
            $current_file->version
        ));
        
        $deleted_count = 0;
        foreach ($old_files as $old_file) {
            // Delete physical file
            $file_path = $this->upload_dir . $old_file->file_path;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Mark as deleted in database
            $wpdb->update(
                $table,
                array(
                    'deleted_at' => current_time('mysql'),
                    'file_path' => null
                ),
                array('id' => $old_file->id)
            );
            
            $deleted_count++;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh: Deleted previous version (v%d) after new version (v%d) was approved', 
                    $old_file->version, $current_file->version));
            }
        }
        
        return $deleted_count;
    }

    /**
     * Reject file
     *
     * @param int $file_id File ID
     * @param int $admin_id Admin user ID
     * @param string $reason Rejection reason
     * @return array Result array
     */
    public function reject_file($file_id, $admin_id, $reason) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $this->get_file($file_id);
        if (!$file) {
            return array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            );
        }

        // Calculate expiration date (current time + retention days)
        $retention_days = intval(Tabesh()->get_setting('file_retention_days', 5));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));

        // Update file status
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'rejection_reason' => sanitize_textarea_field($reason),
                'expires_at' => $expires_at
            ),
            array('id' => $file_id)
        );

        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('خطا در رد فایل', 'tabesh')
            );
        }

        // Log the action
        $this->log_action($file->order_id, $admin_id, 'file_rejected', sprintf(
            __('فایل "%s" رد شد: %s', 'tabesh'),
            $file->stored_filename,
            $reason
        ));

        return array(
            'success' => true,
            'message' => __('فایل رد شد', 'tabesh')
        );
    }

    /**
     * Delete expired files
     *
     * This method marks files as deleted but keeps the metadata, filename, and rejection reason
     * visible to users. Only the physical file is deleted and download is blocked.
     *
     * @return int Number of files deleted
     */
    public function cleanup_expired_files() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get expired files that are rejected
        $expired_files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s AND expires_at IS NOT NULL AND expires_at < NOW() AND deleted_at IS NULL",
            'rejected'
        ));

        $deleted_count = 0;
        foreach ($expired_files as $file) {
            // Delete physical file
            $file_path = $this->upload_dir . $file->file_path;
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Mark as deleted in database BUT keep metadata
            // This allows filename and rejection comments to remain visible
            $wpdb->update(
                $table,
                array(
                    'deleted_at' => current_time('mysql'),
                    'file_path' => null  // Clear file path since physical file is gone
                ),
                array('id' => $file->id)
            );
            
            $deleted_count++;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh: Expired rejected file deleted (ID: %d, kept metadata)', $file->id));
            }
        }

        return $deleted_count;
    }

    /**
     * Cleanup incomplete uploads
     *
     * Deletes files that were uploaded but never completed processing
     *
     * @return int Number of files deleted
     */
    public function cleanup_incomplete_uploads() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get timeout setting
        $timeout_minutes = intval(Tabesh()->get_setting('file_delete_incomplete_after', 30));
        $timeout = date('Y-m-d H:i:s', strtotime("-{$timeout_minutes} minutes"));
        
        // Get incomplete files (status = pending and created more than timeout ago)
        $incomplete_files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'pending' AND created_at < %s AND deleted_at IS NULL",
            $timeout
        ));

        $deleted_count = 0;
        foreach ($incomplete_files as $file) {
            if ($this->delete_file_physical($file)) {
                // Mark as deleted in database
                $wpdb->update(
                    $table,
                    array('deleted_at' => current_time('mysql')),
                    array('id' => $file->id)
                );
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete file physically from disk
     *
     * @param object $file File object
     * @return bool Success status
     */
    private function delete_file_physical($file) {
        $file_path = $this->upload_dir . $file->file_path;
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        
        return true; // Already deleted
    }

    /**
     * Schedule file for FTP transfer
     *
     * @param object $file File object
     * @return void
     */
    private function schedule_ftp_transfer($file) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Check if immediate transfer is enabled
        $immediate_transfer = Tabesh()->get_setting('ftp_immediate_transfer', '0');
        
        if ($immediate_transfer == '1') {
            // Transfer immediately without waiting for cron
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh: Starting immediate FTP transfer for file %d', $file->id));
            }
            
            // Mark as in progress
            $wpdb->update(
                $table,
                array('transfer_status' => 'in_progress'),
                array('id' => $file->id)
            );
            
            // Get encryption setting
            $encrypt_files = Tabesh()->get_setting('ftp_encrypt_files', '0');
            
            // Perform transfer
            if ($this->transfer_to_ftp($file, $encrypt_files == '1')) {
                // Transfer successful - update status
                $wpdb->update(
                    $table,
                    array(
                        'transfer_status' => 'completed',
                        'transferred_at' => current_time('mysql')
                    ),
                    array('id' => $file->id)
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Tabesh: Immediate FTP transfer completed for file %d', $file->id));
                }
            } else {
                // Transfer failed - mark as failed
                $wpdb->update(
                    $table,
                    array('transfer_status' => 'failed'),
                    array('id' => $file->id)
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Tabesh: Immediate FTP transfer failed for file %d', $file->id));
                }
            }
        } else {
            // Schedule for later (normal cron-based transfer)
            $transfer_delay = intval(Tabesh()->get_setting('ftp_transfer_delay', 60));
            $transfer_at = date('Y-m-d H:i:s', strtotime("+{$transfer_delay} minutes"));
            
            // Update file with scheduled transfer time
            $wpdb->update(
                $table,
                array(
                    'scheduled_transfer_at' => $transfer_at,
                    'transfer_status' => 'scheduled'
                ),
                array('id' => $file->id)
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh: Scheduled FTP transfer for file %d at %s', $file->id, $transfer_at));
            }
        }
    }

    /**
     * Transfer file to FTP
     *
     * @param object $file File object
     * @param bool $encrypt Whether to encrypt the file before transfer
     * @return bool Success status
     */
    private function transfer_to_ftp($file, $encrypt = false) {
        // Check if FTP is enabled
        $ftp_enabled = Tabesh()->get_setting('ftp_enabled', '1');
        if ($ftp_enabled == '0') {
            // Local-only mode, skip transfer
            return false;
        }

        // Check if FTP is configured
        $ftp_host = Tabesh()->get_setting('ftp_host', '');
        if (empty($ftp_host)) {
            return false;
        }

        // Build source path
        $source_path = $this->upload_dir . $file->file_path;
        
        // Check if file should be encrypted
        $file_to_upload = $source_path;
        if ($encrypt) {
            $file_security = new Tabesh_File_Security();
            $encrypt_result = $file_security->encrypt_file($source_path);
            
            if ($encrypt_result['success']) {
                $file_to_upload = $encrypt_result['encrypted_path'];
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Tabesh: Encrypted file before FTP transfer: %s', $file->stored_filename));
                }
            }
        }

        // Get FTP handler
        $ftp_handler = new Tabesh_FTP_Handler();
        
        // Build destination path
        $dest_path = $this->build_ftp_path($file);
        if ($encrypt) {
            $dest_path .= '.encrypted';
        }
        
        // Transfer file
        $result = $ftp_handler->upload_file($file_to_upload, $dest_path);
        
        if ($result) {
            // Update file record with FTP path and transfer status
            global $wpdb;
            $table = $wpdb->prefix . 'tabesh_files';
            $wpdb->update(
                $table,
                array(
                    'ftp_path' => $dest_path,
                    'transfer_status' => 'transferred',
                    'transferred_at' => current_time('mysql'),
                    'is_encrypted' => $encrypt ? 1 : 0
                ),
                array('id' => $file->id)
            );
            
            // Schedule deletion of local file based on settings
            $local_retention = intval(Tabesh()->get_setting('ftp_local_retention_minutes', 120));
            if ($local_retention > 0) {
                $delete_at = date('Y-m-d H:i:s', strtotime("+{$local_retention} minutes"));
                $wpdb->update(
                    $table,
                    array('scheduled_deletion_at' => $delete_at),
                    array('id' => $file->id)
                );
            }
            
            // Auto-backup if enabled (before potential deletion)
            $auto_backup = Tabesh()->get_setting('file_auto_backup_enabled', '1');
            if ($auto_backup == '1' && file_exists($source_path)) {
                $this->backup_file($file, $source_path);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh: Transferred file to FTP: %s -> %s', $source_path, $dest_path));
            }
        } else {
            // Mark transfer as failed
            global $wpdb;
            $table = $wpdb->prefix . 'tabesh_files';
            $wpdb->update(
                $table,
                array('transfer_status' => 'failed'),
                array('id' => $file->id)
            );
        }
        
        // Clean up encrypted temporary file if created
        if ($encrypt && $file_to_upload != $source_path && file_exists($file_to_upload)) {
            @unlink($file_to_upload);
        }
        
        return $result;
    }

    /**
     * Process pending FTP transfers
     *
     * Called by cron job to transfer files that are scheduled for transfer
     *
     * @return int Number of files transferred
     */
    public function process_pending_transfers() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get files scheduled for transfer
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE transfer_status = %s 
             AND scheduled_transfer_at <= NOW() 
             AND deleted_at IS NULL
             LIMIT 50",
            'scheduled'
        ));
        
        $transferred = 0;
        $encrypt_files = Tabesh()->get_setting('ftp_encrypt_files', '0') == '1';
        
        foreach ($files as $file) {
            if ($this->transfer_to_ftp($file, $encrypt_files)) {
                $transferred++;
            }
        }
        
        if ($transferred > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Tabesh: Processed %d pending FTP transfers', $transferred));
        }
        
        return $transferred;
    }

    /**
     * Process scheduled file deletions
     *
     * Called by cron job to delete local files after retention period
     *
     * @return int Number of files deleted
     */
    public function process_scheduled_deletions() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get files scheduled for deletion
        $files = $wpdb->get_results(
            "SELECT * FROM $table 
             WHERE scheduled_deletion_at IS NOT NULL 
             AND scheduled_deletion_at <= NOW() 
             AND deleted_at IS NULL
             LIMIT 50"
        );
        
        $deleted = 0;
        
        foreach ($files as $file) {
            $file_path = $this->upload_dir . $file->file_path;
            
            if (file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $deleted++;
                    
                    // Update database to mark local copy as deleted
                    $wpdb->update(
                        $table,
                        array(
                            'local_deleted_at' => current_time('mysql'),
                            'scheduled_deletion_at' => null
                        ),
                        array('id' => $file->id)
                    );
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf('Tabesh: Deleted local file after retention: %s', $file_path));
                    }
                }
            } else {
                // File already deleted, update database
                $wpdb->update(
                    $table,
                    array(
                        'local_deleted_at' => current_time('mysql'),
                        'scheduled_deletion_at' => null
                    ),
                    array('id' => $file->id)
                );
            }
        }
        
        return $deleted;
    }

    /**
     * Backup file to backup location
     *
     * Backs up to local backup folder and optionally to FTP backup location
     *
     * @param object $file File object
     * @param string $source_path Source file path
     * @return bool Success status
     */
    private function backup_file($file, $source_path) {
        if (!file_exists($source_path)) {
            return false;
        }
        
        $backup_location = Tabesh()->get_setting('file_backup_location', '/backups/');
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . $backup_location;
        
        // Create backup directory if it doesn't exist
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        // Build backup path
        $backup_path = $backup_dir . 'user-' . $file->user_id . '/order-' . $file->order_id . '/';
        if (!file_exists($backup_path)) {
            wp_mkdir_p($backup_path);
        }
        
        $backup_file = $backup_path . $file->stored_filename;
        
        // Copy file to local backup location
        $result = copy($source_path, $backup_file);
        
        if ($result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Tabesh: Backed up file locally: %s', $backup_file));
        }
        
        // Also backup to FTP if enabled
        $ftp_enabled = Tabesh()->get_setting('ftp_enabled', '1');
        if ($ftp_enabled == '1' && $result) {
            // Check if FTP handler class exists
            if (!class_exists('Tabesh_FTP_Handler')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tabesh: FTP Handler class not found for backup');
                }
                return $result;
            }
            
            try {
                $ftp_handler = new Tabesh_FTP_Handler();
                $ftp_base_path = Tabesh()->get_setting('ftp_path', '/uploads/');
                $ftp_backup_path = rtrim($ftp_base_path, '/') . $backup_location . 'user-' . $file->user_id . '/order-' . $file->order_id . '/' . $file->stored_filename;
                
                $ftp_result = $ftp_handler->upload_file($backup_file, $ftp_backup_path);
                
                if ($ftp_result && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Tabesh: Backed up file to FTP: %s', $ftp_backup_path));
                } elseif (!$ftp_result && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tabesh: FTP backup failed but local backup succeeded');
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tabesh: FTP backup exception: ' . $e->getMessage());
                }
                // Continue - local backup succeeded
            }
        }
        
        return $result;
    }

    /**
     * Build FTP path for file
     *
     * @param object $file File object
     * @return string FTP path
     */
    private function build_ftp_path($file) {
        $base_path = Tabesh()->get_setting('ftp_path', '/uploads/');
        $base_path = rtrim($base_path, '/') . '/';
        
        return $base_path . 'user-' . $file->user_id . '/order-' . $file->order_id . '/' . $file->file_category . '/' . $file->stored_filename;
    }

    /**
     * Schedule old version cleanup
     *
     * @param int $file_id File ID
     * @return void
     */
    private function schedule_old_version_cleanup($file_id) {
        $retention_days = intval(Tabesh()->get_setting('file_retention_days', 5));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));
        
        global $wpdb;
        $versions_table = $wpdb->prefix . 'tabesh_file_versions';
        
        // Get the maximum version number first
        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM $versions_table WHERE file_id = %d",
            $file_id
        ));
        
        if ($max_version) {
            // Mark old versions for deletion
            $wpdb->query($wpdb->prepare(
                "UPDATE $versions_table SET status = 'expired' WHERE file_id = %d AND version < %d",
                $file_id,
                $max_version
            ));
        }
    }

    /**
     * Get existing file
     *
     * @param int $order_id Order ID
     * @param string $file_category File category
     * @param string $filename Original filename without extension
     * @return object|null File object or null
     */
    private function get_existing_file($order_id, $file_category, $filename) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $filename = sanitize_file_name($filename);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d AND file_category = %s AND original_filename LIKE %s AND deleted_at IS NULL ORDER BY version DESC LIMIT 1",
            $order_id,
            $file_category,
            $filename . '%'
        ));
    }

    /**
     * Verify order ownership
     *
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @return bool True if user owns the order
     */
    private function verify_order_ownership($order_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $order_id
        ));
        
        return $order && $order->user_id == $user_id;
    }

    /**
     * Get max file size based on file type
     *
     * @param string $file_ext File extension
     * @return int Max size in bytes
     */
    private function get_max_file_size($file_ext) {
        $ext = strtolower($file_ext);
        
        if ($ext === 'pdf') {
            return intval(Tabesh()->get_setting('file_max_size_pdf', 52428800));
        } elseif (in_array($ext, array('jpg', 'jpeg', 'png', 'psd'))) {
            return intval(Tabesh()->get_setting('file_max_size_image', 10485760));
        } elseif (in_array($ext, array('zip', 'rar'))) {
            return intval(Tabesh()->get_setting('file_max_size_archive', 104857600));
        } else {
            return intval(Tabesh()->get_setting('file_max_size_document', 10485760));
        }
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('حجم فایل بیش از حد مجاز است', 'tabesh');
            case UPLOAD_ERR_PARTIAL:
                return __('فایل به طور کامل آپلود نشد', 'tabesh');
            case UPLOAD_ERR_NO_FILE:
                return __('هیچ فایلی انتخاب نشده است', 'tabesh');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('پوشه موقت یافت نشد', 'tabesh');
            case UPLOAD_ERR_CANT_WRITE:
                return __('خطا در نوشتن فایل', 'tabesh');
            case UPLOAD_ERR_EXTENSION:
                return __('آپلود فایل توسط افزونه متوقف شد', 'tabesh');
            default:
                return __('خطای ناشناخته در آپلود فایل', 'tabesh');
        }
    }

    /**
     * Log action
     *
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param string $action Action name
     * @param string $description Action description
     * @return void
     */
    private function log_action($order_id, $user_id, $action, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_logs';
        
        $wpdb->insert($table, array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'action' => sanitize_text_field($action),
            'description' => sanitize_text_field($description)
        ));
    }

    /**
     * Check if current user has admin access to files
     *
     * @return bool True if user has access
     */
    public function current_user_has_file_access() {
        // Super admins always have access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if user is in allowed admin list
        $allowed_admins = Tabesh()->get_setting('file_admin_access_list', array());
        
        // If list is empty, all admins have access
        if (empty($allowed_admins) || !is_array($allowed_admins)) {
            return current_user_can('manage_woocommerce');
        }
        
        // Check if current user is in the list
        $current_user_id = get_current_user_id();
        return in_array($current_user_id, $allowed_admins);
    }

    /**
     * Calculate total correction fees for an order
     *
     * Sums up correction fees from all files that have validation issues
     *
     * @param int $order_id Order ID
     * @return array Array with total_fee and breakdown by file
     */
    public function calculate_order_correction_fees($order_id) {
        // Currency conversion constant (Rial to Toman)
        $RIAL_TO_TOMAN = 10;
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        // Get all files for the order that have validation data
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT id, stored_filename, file_category, validation_data, status 
             FROM $table 
             WHERE order_id = %d 
             AND deleted_at IS NULL 
             AND validation_data IS NOT NULL",
            $order_id
        ));
        
        $total_fee = 0;
        $fee_breakdown = array();
        
        foreach ($files as $file) {
            $validation_data = json_decode($file->validation_data, true);
            
            if (is_array($validation_data) && isset($validation_data['data']['correction_fee'])) {
                $fee = intval($validation_data['data']['correction_fee']);
                
                if ($fee > 0) {
                    $total_fee += $fee;
                    $fee_breakdown[] = array(
                        'file_id' => $file->id,
                        'filename' => $file->stored_filename,
                        'category' => $file->file_category,
                        'fee' => $fee,
                        'status' => $file->status,
                        'issues' => $this->get_validation_issues($validation_data)
                    );
                }
            }
        }
        
        return array(
            'total_fee' => $total_fee,
            'total_fee_toman' => $total_fee / $RIAL_TO_TOMAN, // Convert Rial to Toman for display
            'breakdown' => $fee_breakdown,
            'file_count' => count($fee_breakdown)
        );
    }
    
    /**
     * Get validation issues from validation data
     *
     * @param array $validation_data Validation data array
     * @return array Array of issue descriptions
     */
    private function get_validation_issues($validation_data) {
        $issues = array();
        
        if (!is_array($validation_data)) {
            return $issues;
        }
        
        // Check for specific issues
        if (isset($validation_data['data'])) {
            $data = $validation_data['data'];
            
            if (isset($data['dpi_issue']) && $data['dpi_issue']) {
                $issues[] = __('رزولوشن پایین (DPI)', 'tabesh');
            }
            
            if (isset($data['color_mode_issue']) && $data['color_mode_issue']) {
                $issues[] = __('حالت رنگی نامناسب', 'tabesh');
            }
            
            if (isset($data['size_mismatch']) && $data['size_mismatch']) {
                $issues[] = __('عدم تطابق اندازه با قطع سفارش', 'tabesh');
            }
            
            if (isset($data['margin_issue']) && $data['margin_issue']) {
                $issues[] = __('حاشیه‌های خارج از محدوده', 'tabesh');
            }
        }
        
        return $issues;
    }
}
