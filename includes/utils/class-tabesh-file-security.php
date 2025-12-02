<?php
/**
 * File Security Class
 *
 * Handles file encryption, secure downloads, and access control
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_File_Security {

    /**
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Get encryption key from WordPress security salts
     *
     * @return string Encryption key
     */
    private function get_encryption_key() {
        // Use WordPress security salts to generate a consistent key
        $key = wp_hash(AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY);
        return substr($key, 0, 32); // AES-256 requires 32 bytes
    }

    /**
     * Encrypt file content
     *
     * @param string $file_path Path to file to encrypt
     * @return array Result with success status and encrypted path
     */
    public function encrypt_file($file_path) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            );
        }

        // Read file content
        $content = file_get_contents($file_path);
        if ($content === false) {
            return array(
                'success' => false,
                'message' => __('خطا در خواندن فایل', 'tabesh')
            );
        }

        // Generate IV (Initialization Vector)
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt content
        $encrypted = openssl_encrypt(
            $content,
            self::ENCRYPTION_METHOD,
            $this->get_encryption_key(),
            0,
            $iv
        );

        if ($encrypted === false) {
            return array(
                'success' => false,
                'message' => __('خطا در رمزنگاری فایل', 'tabesh')
            );
        }

        // Combine IV and encrypted content
        $encrypted_data = base64_encode($iv . $encrypted);

        // Create encrypted file path
        $encrypted_path = $file_path . '.encrypted';

        // Write encrypted content
        if (file_put_contents($encrypted_path, $encrypted_data) === false) {
            return array(
                'success' => false,
                'message' => __('خطا در ذخیره فایل رمزنگاری شده', 'tabesh')
            );
        }

        return array(
            'success' => true,
            'encrypted_path' => $encrypted_path,
            'original_path' => $file_path
        );
    }

    /**
     * Decrypt file content
     *
     * @param string $encrypted_file_path Path to encrypted file
     * @param string $output_path Optional output path for decrypted file
     * @return array Result with success status
     */
    public function decrypt_file($encrypted_file_path, $output_path = null) {
        if (!file_exists($encrypted_file_path)) {
            return array(
                'success' => false,
                'message' => __('فایل رمزنگاری شده یافت نشد', 'tabesh')
            );
        }

        // Read encrypted content
        $encrypted_data = file_get_contents($encrypted_file_path);
        if ($encrypted_data === false) {
            return array(
                'success' => false,
                'message' => __('خطا در خواندن فایل رمزنگاری شده', 'tabesh')
            );
        }

        // Decode base64
        $decoded = base64_decode($encrypted_data);

        // Extract IV and encrypted content
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);

        // Decrypt content
        $decrypted = openssl_decrypt(
            $encrypted,
            self::ENCRYPTION_METHOD,
            $this->get_encryption_key(),
            0,
            $iv
        );

        if ($decrypted === false) {
            return array(
                'success' => false,
                'message' => __('خطا در رمزگشایی فایل', 'tabesh')
            );
        }

        // If output path specified, write to file
        if ($output_path !== null) {
            if (file_put_contents($output_path, $decrypted) === false) {
                return array(
                    'success' => false,
                    'message' => __('خطا در ذخیره فایل رمزگشایی شده', 'tabesh')
                );
            }
        }

        return array(
            'success' => true,
            'decrypted_content' => $decrypted,
            'output_path' => $output_path
        );
    }

    /**
     * Verify user has access to file
     *
     * @param int $file_id File ID
     * @param int $user_id User ID
     * @return bool True if user has access
     */
    public function verify_file_access($file_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // Get file info
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, o.user_id as order_user_id FROM $table f 
             LEFT JOIN {$wpdb->prefix}tabesh_orders o ON f.order_id = o.id 
             WHERE f.id = %d AND f.deleted_at IS NULL",
            $file_id
        ));

        if (!$file) {
            return false;
        }

        // Admin users have access to all files
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // Check if user owns the order
        if ($file->order_user_id == $user_id) {
            return true;
        }

        // Log unauthorized access attempt
        $this->log_security_event(
            'unauthorized_file_access_attempt',
            $user_id,
            $file_id,
            sprintf(
                __('کاربر %d تلاش برای دسترسی غیرمجاز به فایل %d داشت', 'tabesh'),
                $user_id,
                $file_id
            )
        );

        return false;
    }

    /**
     * Generate secure download token
     *
     * @param int $file_id File ID
     * @param int $user_id User ID
     * @param int $expiry_hours Token expiry in hours
     * @return array Result with token info
     */
    public function generate_download_token($file_id, $user_id, $expiry_hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        // Verify user has access
        if (!$this->verify_file_access($file_id, $user_id)) {
            return array(
                'success' => false,
                'message' => __('شما دسترسی به این فایل ندارید', 'tabesh')
            );
        }

        // Generate unique token
        $token = wp_generate_password(64, false);
        $token_hash = wp_hash($token);

        // Calculate expiry time
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

        // Store token in database
        $result = $wpdb->insert(
            $table,
            array(
                'file_id' => $file_id,
                'user_id' => $user_id,
                'token_hash' => $token_hash,
                'expires_at' => $expires_at,
                'used' => 0
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );

        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('خطا در ایجاد توکن دانلود', 'tabesh')
            );
        }

        // Log token generation
        $this->log_security_event(
            'download_token_generated',
            $user_id,
            $file_id,
            sprintf(
                __('توکن دانلود برای فایل %d ایجاد شد', 'tabesh'),
                $file_id
            )
        );

        return array(
            'success' => true,
            'token' => $token,
            'token_id' => $wpdb->insert_id,
            'expires_at' => $expires_at
        );
    }

    /**
     * Verify download token
     *
     * @param string $token Download token
     * @param int $file_id File ID
     * @return array Result with verification status
     */
    public function verify_download_token($token, $file_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        $token_hash = wp_hash($token);

        // Find valid token
        $token_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE token_hash = %s 
             AND file_id = %d 
             AND expires_at > NOW() 
             AND used = 0
             LIMIT 1",
            $token_hash,
            $file_id
        ));

        if (!$token_record) {
            // Log failed verification
            $this->log_security_event(
                'invalid_download_token',
                get_current_user_id(),
                $file_id,
                sprintf(
                    __('تلاش استفاده از توکن نامعتبر برای فایل %d', 'tabesh'),
                    $file_id
                )
            );

            return array(
                'success' => false,
                'message' => __('توکن دانلود نامعتبر یا منقضی شده است', 'tabesh')
            );
        }

        // Mark token as used
        $wpdb->update(
            $table,
            array('used' => 1, 'used_at' => current_time('mysql')),
            array('id' => $token_record->id),
            array('%d', '%s'),
            array('%d')
        );

        return array(
            'success' => true,
            'user_id' => $token_record->user_id,
            'token_id' => $token_record->id
        );
    }

    /**
     * Serve file securely
     *
     * Downloads file from FTP if needed, then serves it to user
     *
     * @param int $file_id File ID
     * @param string $token Download token
     * @return void Outputs file and exits
     */
    public function serve_file_securely($file_id, $token) {
        // Verify token
        $verification = $this->verify_download_token($token, $file_id);
        if (!$verification['success']) {
            wp_die(
                esc_html($verification['message']),
                __('خطای امنیتی', 'tabesh'),
                array('response' => 403)
            );
        }

        // Get file info (including deleted files to show proper message)
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $file_id
        ));

        if (!$file) {
            wp_die(
                __('فایل یافت نشد', 'tabesh'),
                __('خطا', 'tabesh'),
                array('response' => 404)
            );
        }
        
        // Check if file has been deleted
        if (!empty($file->deleted_at)) {
            $message = __('این فایل حذف شده است و دیگر قابل دانلود نیست.', 'tabesh');
            
            // If file was rejected and expired, show specific message
            if ($file->status === 'rejected' && !empty($file->rejection_reason)) {
                $message .= '<br><br>';
                $message .= '<strong>' . __('دلیل رد:', 'tabesh') . '</strong><br>';
                $message .= nl2br(esc_html($file->rejection_reason));
                $message .= '<br><br>';
                $message .= '<em>' . __('نام فایل:', 'tabesh') . ' ' . esc_html($file->original_filename) . '</em>';
            }
            
            wp_die(
                $message,
                __('فایل حذف شده', 'tabesh'),
                array('response' => 410) // 410 Gone
            );
        }

        // Check if file needs to be retrieved from FTP
        $upload_dir = wp_upload_dir();
        $local_dir = $upload_dir['basedir'] . '/tabesh-files/';
        $local_path = $local_dir . $file->file_path;

        // If file doesn't exist locally and FTP path exists, download from FTP
        if (!file_exists($local_path) && !empty($file->ftp_path)) {
            $ftp_result = $this->retrieve_from_ftp($file);
            if (!$ftp_result['success']) {
                wp_die(
                    esc_html($ftp_result['message']),
                    __('خطا', 'tabesh'),
                    array('response' => 500)
                );
            }
            $local_path = $ftp_result['local_path'];
        }

        // Check if file is encrypted
        if (file_exists($local_path . '.encrypted')) {
            // Create secure temporary directory
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/tabesh-temp';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
                // Protect temp directory
                file_put_contents($temp_dir . '/.htaccess', "Deny from all\n");
            }
            
            $temp_path = $temp_dir . '/' . wp_generate_password(32, false);
            $decrypt_result = $this->decrypt_file($local_path . '.encrypted', $temp_path);
            
            if (!$decrypt_result['success']) {
                wp_die(
                    esc_html($decrypt_result['message']),
                    __('خطا', 'tabesh'),
                    array('response' => 500)
                );
            }
            
            $local_path = $temp_path;
            $delete_after_serve = true;
        } else {
            $delete_after_serve = false;
        }

        // Log download
        $this->log_security_event(
            'file_downloaded',
            $verification['user_id'],
            $file_id,
            sprintf(
                __('فایل %s دانلود شد', 'tabesh'),
                $file->stored_filename
            )
        );

        // Serve file
        $this->output_file($local_path, $file->original_filename, $file->mime_type);

        // Clean up temporary decrypted file
        if ($delete_after_serve && file_exists($local_path)) {
            @unlink($local_path);
        }

        exit;
    }

    /**
     * Retrieve file from FTP
     *
     * @param object $file File database record
     * @return array Result with local path
     */
    private function retrieve_from_ftp($file) {
        // Check if fallback to local-only mode is enabled
        $ftp_enabled = Tabesh()->get_setting('ftp_enabled', '1');
        if ($ftp_enabled == '0') {
            return array(
                'success' => false,
                'message' => __('سرور FTP غیرفعال است', 'tabesh')
            );
        }

        $ftp_handler = new Tabesh_FTP_Handler();
        
        // Prepare local destination
        $upload_dir = wp_upload_dir();
        $local_dir = $upload_dir['basedir'] . '/tabesh-files/';
        $local_path = $local_dir . $file->file_path;
        
        // Create directory if needed
        $dir = dirname($local_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Download from FTP
        $result = $ftp_handler->download_file($file->ftp_path, $local_path);
        
        if (!$result) {
            return array(
                'success' => false,
                'message' => __('خطا در دریافت فایل از سرور FTP', 'tabesh')
            );
        }

        return array(
            'success' => true,
            'local_path' => $local_path
        );
    }

    /**
     * Output file to browser
     *
     * @param string $file_path File path
     * @param string $filename Original filename
     * @param string $mime_type MIME type
     * @return void
     */
    private function output_file($file_path, $filename, $mime_type) {
        if (!file_exists($file_path)) {
            return;
        }

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers with CDN/Firewall bypass configuration
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        
        // Security headers to prevent CDN/Firewall from blocking
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('X-Download-Options: noopen');
        header('Pragma: private');
        header('Expires: 0');
        
        // Additional headers for better compatibility
        header('X-Robots-Tag: noindex, nofollow');

        // Output file
        readfile($file_path);
    }

    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param int $user_id User ID
     * @param int $file_id File ID
     * @param string $description Description
     * @return void
     */
    private function log_security_event($event_type, $user_id, $file_id, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_security_logs';

        // Get real IP address, considering proxies
        $ip_address = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_list[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }

        $wpdb->insert(
            $table,
            array(
                'event_type' => sanitize_text_field($event_type),
                'user_id' => $user_id,
                'file_id' => $file_id,
                'ip_address' => sanitize_text_field($ip_address),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'description' => sanitize_text_field($description),
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s')
        );

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                'Tabesh Security: [%s] User %d, File %d - %s',
                $event_type,
                $user_id,
                $file_id,
                $description
            ));
        }
    }

    /**
     * Clean up expired download tokens
     *
     * @return int Number of tokens deleted
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE expires_at < %s",
            current_time('mysql')
        ));

        return $deleted;
    }

    /**
     * Get security statistics
     *
     * @return array Security stats
     */
    public function get_security_stats() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'tabesh_security_logs';
        $tokens_table = $wpdb->prefix . 'tabesh_download_tokens';

        $stats = array();

        // Count recent download attempts
        $stats['downloads_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table 
             WHERE event_type = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'file_downloaded'
        ));

        // Count unauthorized access attempts
        $stats['unauthorized_attempts_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table 
             WHERE event_type = %s 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'unauthorized_file_access_attempt'
        ));

        // Count active tokens
        $stats['active_tokens'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tokens_table 
             WHERE expires_at > NOW() AND used = %d",
            0
        ));

        return $stats;
    }
}
