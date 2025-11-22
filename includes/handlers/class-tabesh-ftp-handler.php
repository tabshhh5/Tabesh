<?php
/**
 * FTP Handler Class
 *
 * Handles FTP operations for file transfer to download host
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_FTP_Handler {

    /**
     * FTP connection resource
     *
     * @var resource|null
     */
    private $conn = null;

    /**
     * Test FTP connection
     *
     * @param array $config FTP configuration array
     * @return array Result array with success status and message
     */
    public function test_connection($config = null) {
        if ($config === null) {
            $config = $this->get_ftp_config();
        }

        // Validate required fields
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            return array(
                'success' => false,
                'message' => __('اطلاعات FTP ناقص است', 'tabesh')
            );
        }

        // Connect to FTP server
        $conn_result = $this->connect($config);
        
        if (!$conn_result['success']) {
            return $conn_result;
        }

        // Test write permission
        $test_file = 'tabesh_test_' . time() . '.txt';
        $test_content = 'Tabesh FTP Test File';
        $temp_file = sys_get_temp_dir() . '/' . $test_file;
        
        file_put_contents($temp_file, $test_content);
        
        $upload_result = $this->upload_file($temp_file, $config['path'] . $test_file);
        
        if ($upload_result) {
            // Delete test file
            ftp_delete($this->conn, $config['path'] . $test_file);
            unlink($temp_file);
            
            $this->disconnect();
            
            return array(
                'success' => true,
                'message' => __('اتصال FTP با موفقیت برقرار شد', 'tabesh')
            );
        }

        unlink($temp_file);
        $this->disconnect();
        
        return array(
            'success' => false,
            'message' => __('خطا در نوشتن فایل روی سرور FTP', 'tabesh')
        );
    }

    /**
     * Upload file to FTP
     *
     * @param string $local_file Local file path
     * @param string $remote_file Remote file path
     * @param array|null $config FTP configuration (optional)
     * @return bool Success status
     */
    public function upload_file($local_file, $remote_file, $config = null) {
        if ($config === null) {
            $config = $this->get_ftp_config();
        }

        // Validate file exists
        if (!file_exists($local_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh FTP: Local file does not exist: ' . $local_file);
            }
            return false;
        }

        // Connect if not already connected
        if (!$this->conn) {
            $conn_result = $this->connect($config);
            if (!$conn_result['success']) {
                return false;
            }
        }

        // Create remote directory if it doesn't exist
        $remote_dir = dirname($remote_file);
        $this->create_remote_directory($remote_dir);

        // Upload file
        $mode = (pathinfo($local_file, PATHINFO_EXTENSION) === 'txt') ? FTP_ASCII : FTP_BINARY;
        
        $result = ftp_put($this->conn, $remote_file, $local_file, $mode);

        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh FTP: Failed to upload file: ' . $local_file . ' to ' . $remote_file);
        }

        return $result;
    }

    /**
     * Connect to FTP server
     *
     * @param array $config FTP configuration
     * @return array Result array
     */
    private function connect($config) {
        $host = sanitize_text_field($config['host']);
        $port = intval($config['port'] ?? 21);
        $username = sanitize_text_field($config['username']);
        $password = $config['password']; // Don't sanitize password
        $passive = (bool)($config['passive'] ?? true);
        $ssl = (bool)($config['ssl'] ?? false);

        // Connect based on SSL setting
        if ($ssl && function_exists('ftp_ssl_connect')) {
            $this->conn = @ftp_ssl_connect($host, $port, 10);
        } else {
            $this->conn = @ftp_connect($host, $port, 10);
        }

        if (!$this->conn) {
            return array(
                'success' => false,
                'message' => sprintf(__('خطا در اتصال به سرور FTP: %s:%d', 'tabesh'), $host, $port)
            );
        }

        // Login
        $login_result = @ftp_login($this->conn, $username, $password);
        
        if (!$login_result) {
            ftp_close($this->conn);
            $this->conn = null;
            
            return array(
                'success' => false,
                'message' => __('خطا در ورود به سرور FTP. نام کاربری یا رمز عبور اشتباه است', 'tabesh')
            );
        }

        // Set passive mode
        ftp_pasv($this->conn, $passive);

        return array(
            'success' => true,
            'message' => __('اتصال FTP برقرار شد', 'tabesh')
        );
    }

    /**
     * Disconnect from FTP server
     *
     * @return void
     */
    private function disconnect() {
        if ($this->conn) {
            ftp_close($this->conn);
            $this->conn = null;
        }
    }

    /**
     * Create remote directory recursively
     *
     * @param string $dir Directory path
     * @return bool Success status
     */
    private function create_remote_directory($dir) {
        $dir = rtrim($dir, '/');
        
        // Check if directory exists
        if (@ftp_chdir($this->conn, $dir)) {
            ftp_cdup($this->conn);
            return true;
        }

        // Create parent directory first
        $parent = dirname($dir);
        if ($parent !== '.' && $parent !== '/') {
            $this->create_remote_directory($parent);
        }

        // Create this directory
        $result = @ftp_mkdir($this->conn, $dir);
        
        if ($result) {
            // Set directory permissions
            @ftp_chmod($this->conn, 0755, $dir);
        }

        return (bool)$result;
    }

    /**
     * Download file from FTP
     *
     * @param string $remote_file Remote file path
     * @param string $local_file Local file path
     * @param array|null $config FTP configuration (optional)
     * @return bool Success status
     */
    public function download_file($remote_file, $local_file, $config = null) {
        if ($config === null) {
            $config = $this->get_ftp_config();
        }

        // Connect if not already connected
        if (!$this->conn) {
            $conn_result = $this->connect($config);
            if (!$conn_result['success']) {
                return false;
            }
        }

        // Download file
        $mode = (pathinfo($remote_file, PATHINFO_EXTENSION) === 'txt') ? FTP_ASCII : FTP_BINARY;
        
        $result = ftp_get($this->conn, $local_file, $remote_file, $mode);

        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh FTP: Failed to download file: ' . $remote_file . ' to ' . $local_file);
        }

        return $result;
    }

    /**
     * Delete file from FTP
     *
     * @param string $remote_file Remote file path
     * @param array|null $config FTP configuration (optional)
     * @return bool Success status
     */
    public function delete_file($remote_file, $config = null) {
        if ($config === null) {
            $config = $this->get_ftp_config();
        }

        // Connect if not already connected
        if (!$this->conn) {
            $conn_result = $this->connect($config);
            if (!$conn_result['success']) {
                return false;
            }
        }

        // Delete file
        $result = @ftp_delete($this->conn, $remote_file);

        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Tabesh FTP: Failed to delete file: ' . $remote_file);
        }

        return $result;
    }

    /**
     * Get FTP connection status and uptime
     *
     * @return array Status information
     */
    public function get_connection_status() {
        $config = $this->get_ftp_config();
        
        // Check if FTP is enabled
        $ftp_enabled = Tabesh()->get_setting('ftp_enabled', '1');
        if ($ftp_enabled == '0') {
            return array(
                'connected' => false,
                'status' => 'disabled',
                'message' => __('FTP غیرفعال است (حالت محلی)', 'tabesh'),
                'uptime' => null
            );
        }

        // Check if FTP is configured
        if (empty($config['host'])) {
            return array(
                'connected' => false,
                'status' => 'not_configured',
                'message' => __('FTP پیکربندی نشده است', 'tabesh'),
                'uptime' => null
            );
        }

        // Try to connect
        $conn_result = $this->connect($config);
        
        if (!$conn_result['success']) {
            // Store failure time
            update_option('tabesh_ftp_last_failure', current_time('mysql'));
            
            return array(
                'connected' => false,
                'status' => 'failed',
                'message' => $conn_result['message'],
                'uptime' => null,
                'last_failure' => get_option('tabesh_ftp_last_failure')
            );
        }

        // Get system uptime from FTP server (if available)
        $uptime = null;
        if ($this->conn) {
            // Try to get server system info
            $systype = @ftp_systype($this->conn);
            
            // Store success time
            update_option('tabesh_ftp_last_success', current_time('mysql'));
            
            $this->disconnect();
            
            // Calculate uptime since last failure
            $last_failure = get_option('tabesh_ftp_last_failure');
            $last_success = get_option('tabesh_ftp_last_success');
            
            if ($last_success) {
                $uptime_seconds = time() - strtotime($last_success);
                $uptime = $this->format_uptime($uptime_seconds);
            }
        }

        return array(
            'connected' => true,
            'status' => 'connected',
            'message' => __('متصل', 'tabesh'),
            'uptime' => $uptime,
            'system_type' => $systype ?? null,
            'last_success' => get_option('tabesh_ftp_last_success')
        );
    }

    /**
     * Format uptime in human-readable format
     *
     * @param int $seconds Seconds
     * @return string Formatted uptime
     */
    private function format_uptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
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
        
        return !empty($parts) ? implode(', ', $parts) : __('کمتر از یک دقیقه', 'tabesh');
    }

    /**
     * Get FTP configuration from settings
     *
     * @return array FTP configuration
     */
    private function get_ftp_config() {
        $admin = Tabesh()->admin;
        
        return array(
            'host' => $admin->get_setting('ftp_host', ''),
            'port' => intval($admin->get_setting('ftp_port', 21)),
            'username' => $admin->get_setting('ftp_username', ''),
            'password' => $admin->get_setting('ftp_password', ''),
            'path' => $admin->get_setting('ftp_path', '/uploads/'),
            'passive' => (bool)$admin->get_setting('ftp_passive', true),
            'ssl' => (bool)$admin->get_setting('ftp_ssl', false),
        );
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct() {
        $this->disconnect();
    }
}
