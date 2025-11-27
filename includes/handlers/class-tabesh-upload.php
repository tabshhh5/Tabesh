<?php
/**
 * Upload Manager Class
 *
 * Handles file upload management, secure downloads, and customer file interface.
 * This class provides a complete file upload management system with:
 * - Modern responsive UI with RTL support
 * - Secure file storage and download tokens
 * - File versioning
 * - Search functionality
 * - Admin integration
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Upload {

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
     * Number of required file categories
     *
     * @var int
     */
    const REQUIRED_FILE_CATEGORIES = 3;

    /**
     * Default max file sizes in bytes
     *
     * @var array
     */
    const DEFAULT_MAX_FILE_SIZES = array(
        'text' => 52428800,      // 50 MB (50 * 1024 * 1024)
        'cover' => 10485760,     // 10 MB (10 * 1024 * 1024)
        'documents' => 10485760  // 10 MB (10 * 1024 * 1024)
    );

    /**
     * Allowed file types by category
     *
     * @var array
     */
    private $allowed_types = array(
        'text' => array('pdf'),
        'cover' => array('pdf', 'jpg', 'jpeg', 'png', 'psd'),
        'documents' => array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx')
    );

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/plugin-files/';
        $this->upload_url = $upload_dir['baseurl'] . '/plugin-files/';
        
        // Create upload directory if it doesn't exist
        $this->ensure_upload_directory();
        
        // Register shortcodes
        add_shortcode('tabesh_upload_manager', array($this, 'render_upload_manager'));
        
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Ensure upload directory exists with proper protection
     *
     * @return void
     */
    private function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess to protect files
            $htaccess_content = "# Tabesh Plugin Files Protection\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "\n";
            $htaccess_content .= "# Block direct access\n";
            $htaccess_content .= "<FilesMatch \".*\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            // Use wp_filesystem or direct file write
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($this->upload_dir . '.htaccess', $htaccess_content, FS_CHMOD_FILE);
            } else {
                // Fallback to direct file write - file_put_contents is safe here as we're writing to our own directory
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
            }
            
            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden.\n";
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($this->upload_dir . 'index.php', $index_content, FS_CHMOD_FILE);
            } else {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents($this->upload_dir . 'index.php', $index_content);
            }
        }
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes() {
        // Upload file endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/upload-file', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_upload_file'),
            'permission_callback' => array($this, 'check_user_permission')
        ));

        // Get order files endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/order-files/(?P<order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_order_files'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Download file endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/download-file/(?P<file_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_download_file'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'token' => array(
                    'required' => false,
                    'type' => 'string'
                )
            )
        ));

        // Delete file endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/delete-file/(?P<file_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'rest_delete_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Search orders endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/search-orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_search_orders'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'q' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10
                )
            )
        ));

        // Generate download token endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/generate-download-token/(?P<file_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_download_token'),
            'permission_callback' => array($this, 'check_user_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Approve file endpoint (Admin only)
        register_rest_route(TABESH_REST_NAMESPACE, '/approve-file/(?P<file_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_approve_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Reject file endpoint (Admin only)
        register_rest_route(TABESH_REST_NAMESPACE, '/reject-file/(?P<file_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_reject_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'reason' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                )
            )
        ));
    }

    /**
     * Check user permission for REST API
     *
     * @return bool|WP_Error
     */
    public function check_user_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('برای دسترسی به این بخش باید وارد سیستم شوید.', 'tabesh'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Check admin permission for REST API
     *
     * @return bool|WP_Error
     */
    public function check_admin_permission() {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('شما دسترسی لازم برای این عملیات را ندارید.', 'tabesh'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * REST: Upload file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_upload_file($request) {
        $user_id = get_current_user_id();
        
        // Get file from request
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('هیچ فایلی انتخاب نشده است', 'tabesh')
            ), 400);
        }
        
        $file = $files['file'];
        $order_id = intval($request->get_param('order_id'));
        $file_type = sanitize_text_field($request->get_param('file_type'));
        
        // Validate order_id
        if ($order_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه سفارش نامعتبر است', 'tabesh')
            ), 400);
        }
        
        // Validate file_type
        $valid_types = array('text', 'cover', 'documents');
        if (!in_array($file_type, $valid_types)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('نوع فایل نامعتبر است', 'tabesh')
            ), 400);
        }
        
        // Verify order ownership (unless admin)
        if (!$this->verify_order_access($order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این سفارش دسترسی ندارید', 'tabesh')
            ), 403);
        }
        
        // Process upload
        $result = $this->process_file_upload($file, $order_id, $user_id, $file_type);
        
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }

    /**
     * Process file upload
     *
     * @param array $file File data from $_FILES
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param string $file_type File type (text/cover/documents)
     * @return array Result array
     */
    private function process_file_upload($file, $order_id, $user_id, $file_type) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => $this->get_upload_error_message($file['error'])
            );
        }
        
        // Validate file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = isset($this->allowed_types[$file_type]) ? $this->allowed_types[$file_type] : array();
        
        if (!in_array($file_ext, $allowed)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('فرمت فایل مجاز نیست. فرمت‌های مجاز: %s', 'tabesh'),
                    implode(', ', $allowed)
                )
            );
        }
        
        // Validate file size
        $max_size = $this->get_max_file_size($file_type);
        if ($file['size'] > $max_size) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('حجم فایل بیش از حد مجاز است. حداکثر: %s', 'tabesh'),
                    size_format($max_size)
                )
            );
        }
        
        // Create directory structure
        $user_folder = $this->upload_dir . 'user-' . $user_id . '/';
        $order_folder = $user_folder . 'order-' . $order_id . '/';
        $type_folder = $order_folder . $file_type . '/';
        
        if (!file_exists($type_folder)) {
            wp_mkdir_p($type_folder);
        }
        
        // Get next version number
        $version = $this->get_next_version($order_id, $user_id, $file_type);
        
        // Generate filename
        $original_filename = sanitize_file_name($file['name']);
        $filename_base = pathinfo($original_filename, PATHINFO_FILENAME);
        
        if ($version > 1) {
            $stored_filename = $filename_base . ' v' . $version . '.' . $file_ext;
        } else {
            $stored_filename = $original_filename;
        }
        
        $file_path = $type_folder . $stored_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return array(
                'success' => false,
                'message' => __('خطا در ذخیره فایل', 'tabesh')
            );
        }
        
        // Set file permissions
        chmod($file_path, 0644);
        
        // Store in database
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $wpdb->insert($table, array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'file_type' => $file_type,
            'file_category' => $file_type,
            'original_filename' => $original_filename,
            'stored_filename' => $stored_filename,
            'file_path' => 'user-' . $user_id . '/order-' . $order_id . '/' . $file_type . '/' . $stored_filename,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'version' => $version,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        
        $file_id = $wpdb->insert_id;
        
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
     * Get next version number for a file type
     *
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param string $file_type File type
     * @return int Next version number
     */
    private function get_next_version($order_id, $user_id, $file_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM $table WHERE order_id = %d AND user_id = %d AND file_type = %s AND deleted_at IS NULL",
            $order_id,
            $user_id,
            $file_type
        ));
        
        return $max_version ? intval($max_version) + 1 : 1;
    }

    /**
     * REST: Get order files
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_get_order_files($request) {
        $user_id = get_current_user_id();
        $order_id = intval($request->get_param('order_id'));
        
        // Verify access
        if (!$this->verify_order_access($order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این سفارش دسترسی ندارید', 'tabesh')
            ), 403);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d AND deleted_at IS NULL ORDER BY file_type, version DESC",
            $order_id
        ));
        
        // Format files for response
        $formatted_files = array();
        foreach ($files as $file) {
            $formatted_files[] = array(
                'id' => $file->id,
                'file_type' => $file->file_type,
                'original_filename' => $file->original_filename,
                'stored_filename' => $file->stored_filename,
                'file_size' => $file->file_size,
                'file_size_formatted' => size_format($file->file_size),
                'version' => $file->version,
                'status' => $file->status,
                'status_label' => $this->get_status_label($file->status),
                'rejection_reason' => $file->rejection_reason,
                'created_at' => $file->created_at,
                'created_at_formatted' => date_i18n('j F Y - H:i', strtotime($file->created_at))
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'files' => $formatted_files
        ), 200);
    }

    /**
     * REST: Download file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|void
     */
    public function rest_download_file($request) {
        $user_id = get_current_user_id();
        $file_id = intval($request->get_param('file_id'));
        $token = sanitize_text_field($request->get_param('token'));
        
        // Get file info
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
        
        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Verify access (token-based or ownership)
        $has_access = false;
        
        if (!empty($token)) {
            // Verify token
            $has_access = $this->verify_download_token($file_id, $token);
        } else {
            // Verify ownership or admin
            $has_access = $this->verify_order_access($file->order_id, $user_id);
        }
        
        if (!$has_access) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این فایل دسترسی ندارید', 'tabesh')
            ), 403);
        }
        
        // Build file path
        $file_path = $this->upload_dir . $file->file_path;
        
        if (!file_exists($file_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل روی سرور یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Log download
        $this->log_action($file->order_id, $user_id, 'file_downloaded', sprintf(
            __('فایل "%s" دانلود شد', 'tabesh'),
            $file->stored_filename
        ));
        
        // Serve file
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: attachment; filename="' . $file->original_filename . '"');
        header('Content-Length: ' . $file->file_size);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Read file and output
        readfile($file_path);
        exit;
    }

    /**
     * REST: Delete file (Admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_delete_file($request) {
        $file_id = intval($request->get_param('file_id'));
        $admin_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
        
        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Soft delete - mark as deleted
        $result = $wpdb->update(
            $table,
            array(
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $file_id)
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در حذف فایل', 'tabesh')
            ), 500);
        }
        
        // Log the action
        $this->log_action($file->order_id, $admin_id, 'file_deleted', sprintf(
            __('فایل "%s" حذف شد', 'tabesh'),
            $file->stored_filename
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل با موفقیت حذف شد', 'tabesh')
        ), 200);
    }

    /**
     * REST: Approve file (Admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_approve_file($request) {
        $file_id = intval($request->get_param('file_id'));
        $admin_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
        
        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Update file status
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'approved',
                'approved_at' => current_time('mysql'),
                'approved_by' => $admin_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $file_id)
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در تایید فایل', 'tabesh')
            ), 500);
        }
        
        // Log the action
        $this->log_action($file->order_id, $admin_id, 'file_approved', sprintf(
            __('فایل "%s" تایید شد', 'tabesh'),
            $file->stored_filename
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل با موفقیت تایید شد', 'tabesh')
        ), 200);
    }

    /**
     * REST: Reject file (Admin only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_reject_file($request) {
        $file_id = intval($request->get_param('file_id'));
        $reason = $request->get_param('reason');
        $admin_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';
        
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
        
        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Update file status
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_at' => current_time('mysql'),
                'rejected_by' => $admin_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $file_id)
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در رد فایل', 'tabesh')
            ), 500);
        }
        
        // Log the action
        $this->log_action($file->order_id, $admin_id, 'file_rejected', sprintf(
            __('فایل "%s" رد شد: %s', 'tabesh'),
            $file->stored_filename,
            $reason
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل با موفقیت رد شد', 'tabesh')
        ), 200);
    }

    /**
     * REST: Search orders
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_search_orders($request) {
        $user_id = get_current_user_id();
        $search = $request->get_param('q');
        $page = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page'))));
        $offset = ($page - 1) * $per_page;
        
        global $wpdb;
        $orders_table = $wpdb->prefix . 'tabesh_orders';
        $files_table = $wpdb->prefix . 'tabesh_files';
        
        // Build query
        $where_conditions = array("o.archived = 0");
        $query_params = array();
        
        // Non-admins can only see their own orders
        if (!current_user_can('manage_woocommerce')) {
            $where_conditions[] = "o.user_id = %d";
            $query_params[] = $user_id;
        }
        
        // Search filter
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(o.book_title LIKE %s OR o.order_number LIKE %s OR o.book_size LIKE %s OR CAST(o.quantity AS CHAR) LIKE %s OR CAST(o.page_count_total AS CHAR) LIKE %s)";
            $query_params = array_merge($query_params, array($search_term, $search_term, $search_term, $search_term, $search_term));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $orders_table o WHERE $where_clause";
        $total = $wpdb->get_var($wpdb->prepare($count_query, ...$query_params));
        
        // Get orders with file status
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        $query = "SELECT o.*, 
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.deleted_at IS NULL) as total_files,
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.status = 'approved' AND f.deleted_at IS NULL) as approved_files,
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.status = 'pending' AND f.deleted_at IS NULL) as pending_files
            FROM $orders_table o 
            WHERE $where_clause 
            ORDER BY o.created_at DESC 
            LIMIT %d OFFSET %d";
        
        $orders = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
        
        // Format response
        $formatted_orders = array();
        foreach ($orders as $order) {
            $upload_status = 'no_files';
            if ($order->total_files > 0) {
                if ($order->pending_files > 0) {
                    $upload_status = 'pending';
                } elseif ($order->approved_files == $order->total_files) {
                    $upload_status = 'complete';
                } else {
                    $upload_status = 'partial';
                }
            }
            
            $formatted_orders[] = array(
                'id' => $order->id,
                'order_number' => $order->order_number,
                'book_title' => $order->book_title ?: __('بدون عنوان', 'tabesh'),
                'book_size' => $order->book_size,
                'page_count' => $order->page_count_total,
                'quantity' => $order->quantity,
                'total_price' => $order->total_price,
                'total_price_formatted' => number_format($order->total_price) . ' ' . __('تومان', 'tabesh'),
                'status' => $order->status,
                'status_label' => $this->get_order_status_label($order->status),
                'upload_status' => $upload_status,
                'upload_status_label' => $this->get_upload_status_label($upload_status),
                'total_files' => intval($order->total_files),
                'approved_files' => intval($order->approved_files),
                'pending_files' => intval($order->pending_files),
                'upload_progress' => $order->total_files > 0 ? round(($order->approved_files / max(1, self::REQUIRED_FILE_CATEGORIES)) * 100) : 0,
                'created_at' => $order->created_at,
                'created_at_formatted' => date_i18n('j F Y', strtotime($order->created_at))
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'orders' => $formatted_orders,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ), 200);
    }

    /**
     * REST: Generate download token
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_generate_download_token($request) {
        $user_id = get_current_user_id();
        $file_id = intval($request->get_param('file_id'));
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'tabesh_files';
        $tokens_table = $wpdb->prefix . 'tabesh_download_tokens';
        
        // Get file
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
        
        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد', 'tabesh')
            ), 404);
        }
        
        // Verify access
        if (!$this->verify_order_access($file->order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این فایل دسترسی ندارید', 'tabesh')
            ), 403);
        }
        
        // Generate token
        $token = wp_generate_password(64, false);
        $token_hash = wp_hash($token);
        
        // Get expiry time from settings (default 24 hours)
        $expiry_hours = intval(Tabesh()->get_setting('file_download_link_expiry', 24));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));
        
        // Store token
        $wpdb->insert($tokens_table, array(
            'file_id' => $file_id,
            'user_id' => $user_id,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ));
        
        // Build download URL
        $download_url = rest_url(TABESH_REST_NAMESPACE . '/download-file/' . $file_id) . '?token=' . $token;
        
        return new WP_REST_Response(array(
            'success' => true,
            'download_url' => $download_url,
            'expires_at' => $expires_at,
            'expires_in' => sprintf(__('%d ساعت', 'tabesh'), $expiry_hours)
        ), 200);
    }

    /**
     * Verify download token
     *
     * @param int $file_id File ID
     * @param string $token Token string
     * @return bool
     */
    private function verify_download_token($file_id, $token) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';
        
        $token_hash = wp_hash($token);
        
        $valid = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE file_id = %d AND token_hash = %s AND expires_at > NOW() AND used = 0",
            $file_id,
            $token_hash
        ));
        
        if ($valid) {
            // Mark token as used
            $wpdb->update(
                $table,
                array(
                    'used' => 1,
                    'used_at' => current_time('mysql')
                ),
                array('id' => $valid)
            );
            return true;
        }
        
        return false;
    }

    /**
     * Verify order access
     *
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @return bool
     */
    private function verify_order_access($order_id, $user_id) {
        // Admins have access to all orders
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        $order_owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $order_id
        ));
        
        return $order_owner && intval($order_owner) === $user_id;
    }

    /**
     * Get file status label
     *
     * @param string $status Status key
     * @return string Status label
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => __('در انتظار بررسی', 'tabesh'),
            'approved' => __('تایید شده', 'tabesh'),
            'rejected' => __('رد شده', 'tabesh')
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Get order status label
     *
     * @param string $status Status key
     * @return string Status label
     */
    public function get_order_status_label($status) {
        $labels = array(
            'pending' => __('در انتظار بررسی', 'tabesh'),
            'confirmed' => __('تایید شده', 'tabesh'),
            'processing' => __('در حال چاپ', 'tabesh'),
            'ready' => __('آماده تحویل', 'tabesh'),
            'completed' => __('تحویل داده شده', 'tabesh'),
            'cancelled' => __('لغو شده', 'tabesh')
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Get upload status label
     *
     * @param string $status Upload status
     * @return string Label
     */
    private function get_upload_status_label($status) {
        $labels = array(
            'no_files' => __('در انتظار آپلود', 'tabesh'),
            'pending' => __('در حال بررسی', 'tabesh'),
            'partial' => __('ناقص', 'tabesh'),
            'complete' => __('کامل', 'tabesh')
        );
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Get max file size for type
     *
     * @param string $file_type File type
     * @return int Max size in bytes
     */
    private function get_max_file_size($file_type) {
        $defaults = self::DEFAULT_MAX_FILE_SIZES;
        $sizes = array(
            'text' => intval(Tabesh()->get_setting('upload_max_size_text', $defaults['text'])),
            'cover' => intval(Tabesh()->get_setting('upload_max_size_cover', $defaults['cover'])),
            'documents' => intval(Tabesh()->get_setting('upload_max_size_documents', $defaults['documents']))
        );
        return isset($sizes[$file_type]) ? $sizes[$file_type] : $defaults['documents'];
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => __('حجم فایل بیش از حد مجاز سرور است', 'tabesh'),
            UPLOAD_ERR_FORM_SIZE => __('حجم فایل بیش از حد مجاز است', 'tabesh'),
            UPLOAD_ERR_PARTIAL => __('فایل به طور کامل آپلود نشد', 'tabesh'),
            UPLOAD_ERR_NO_FILE => __('هیچ فایلی انتخاب نشده است', 'tabesh'),
            UPLOAD_ERR_NO_TMP_DIR => __('پوشه موقت سرور یافت نشد', 'tabesh'),
            UPLOAD_ERR_CANT_WRITE => __('خطا در نوشتن فایل روی سرور', 'tabesh'),
            UPLOAD_ERR_EXTENSION => __('آپلود فایل توسط سرور متوقف شد', 'tabesh')
        );
        return isset($messages[$error_code]) ? $messages[$error_code] : __('خطای ناشناخته در آپلود فایل', 'tabesh');
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
            'description' => sanitize_text_field($description),
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Render upload manager shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_upload_manager($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="tabesh-notice error">' . 
                   __('برای دسترسی به این بخش باید وارد حساب کاربری خود شوید.', 'tabesh') . 
                   ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">' . __('ورود', 'tabesh') . '</a>' .
                   '</div>';
        }

        // Enqueue assets
        $this->enqueue_upload_manager_assets();

        ob_start();
        include TABESH_PLUGIN_DIR . 'templates/frontend/upload-manager.php';
        return ob_get_clean();
    }

    /**
     * Enqueue upload manager CSS and JS
     *
     * @return void
     */
    private function enqueue_upload_manager_assets() {
        // CSS
        wp_enqueue_style(
            'tabesh-upload-manager',
            TABESH_PLUGIN_URL . 'assets/css/upload.css',
            array(),
            TABESH_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'tabesh-upload-manager',
            TABESH_PLUGIN_URL . 'assets/js/upload.js',
            array('jquery'),
            TABESH_VERSION,
            true
        );

        // Localize script
        wp_localize_script('tabesh-upload-manager', 'tabeshUploadData', array(
            'restUrl' => rest_url(TABESH_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'isAdmin' => current_user_can('manage_woocommerce'),
            'maxFileSizes' => array(
                'text' => $this->get_max_file_size('text'),
                'cover' => $this->get_max_file_size('cover'),
                'documents' => $this->get_max_file_size('documents')
            ),
            'allowedTypes' => $this->allowed_types,
            'strings' => array(
                'uploading' => __('در حال آپلود...', 'tabesh'),
                'uploadSuccess' => __('فایل با موفقیت آپلود شد', 'tabesh'),
                'uploadError' => __('خطا در آپلود فایل', 'tabesh'),
                'loading' => __('در حال بارگذاری...', 'tabesh'),
                'noResults' => __('نتیجه‌ای یافت نشد', 'tabesh'),
                'loadMore' => __('مشاهده بیشتر', 'tabesh'),
                'searching' => __('در حال جستجو...', 'tabesh'),
                'confirmDelete' => __('آیا از حذف این فایل اطمینان دارید؟', 'tabesh'),
                'networkError' => __('خطا در اتصال به سرور. لطفاً اتصال اینترنت را بررسی کنید.', 'tabesh'),
                'fileTypes' => array(
                    'text' => __('متن کتاب', 'tabesh'),
                    'cover' => __('جلد کتاب', 'tabesh'),
                    'documents' => __('مدارک', 'tabesh')
                ),
                'statuses' => array(
                    'pending' => __('در انتظار بررسی', 'tabesh'),
                    'approved' => __('تایید شده', 'tabesh'),
                    'rejected' => __('رد شده', 'tabesh')
                )
            )
        ));
    }

    /**
     * Get user orders with file stats
     *
     * @param int $user_id User ID
     * @return array Orders array
     */
    public function get_user_orders_with_files($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        global $wpdb;
        $orders_table = $wpdb->prefix . 'tabesh_orders';
        $files_table = $wpdb->prefix . 'tabesh_files';

        $query = "SELECT o.*, 
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.deleted_at IS NULL) as total_files,
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.status = 'approved' AND f.deleted_at IS NULL) as approved_files,
            (SELECT COUNT(*) FROM $files_table f WHERE f.order_id = o.id AND f.status = 'pending' AND f.deleted_at IS NULL) as pending_files
            FROM $orders_table o 
            WHERE o.user_id = %d AND o.archived = 0 
            ORDER BY o.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($query, $user_id));
    }
}
