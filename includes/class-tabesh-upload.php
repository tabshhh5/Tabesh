<?php
/**
 * Upload Manager Class
 *
 * Independent file upload system for orders with modern UI integration.
 * Handles file uploads, versioning, and management for book printing orders.
 *
 * @package Tabesh
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Tabesh_Upload
 *
 * Manages file uploads for orders with support for:
 * - Multiple file categories (text, cover, documents)
 * - File versioning
 * - Secure download tokens
 * - Progress tracking
 */
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
     * Default max file sizes in bytes
     *
     * @var array
     */
    private $default_max_sizes = array(
        'text' => 52428800,      // 50 MB
        'cover' => 20971520,     // 20 MB
        'documents' => 10485760  // 10 MB
    );

    /**
     * Default max file counts per type
     *
     * @var array
     */
    private $default_max_counts = array(
        'text' => 10,
        'cover' => 10,
        'documents' => 20
    );

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/plugin-files/';
        $this->upload_url = $upload_dir['baseurl'] . '/plugin-files/';
        
        $this->init();
    }

    /**
     * Initialize hooks and create upload directory
     *
     * @return void
     */
    private function init() {
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess to protect files
            $htaccess_content = "# Tabesh Files Protection\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
            
            // Create index.php for additional protection
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents($this->upload_dir . 'index.php', '<?php // Silence is golden');
        }

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes for upload manager
     *
     * @return void
     */
    public function register_rest_routes() {
        // Upload file endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/upload-file', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_upload_file'),
            'permission_callback' => array($this, 'check_upload_permission')
        ));

        // Get order files endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/order-files/(?P<order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_order_files'),
            'permission_callback' => array($this, 'check_view_permission'),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Download file endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/download/(?P<file_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_download_file'),
            'permission_callback' => array($this, 'check_download_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'token' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Delete file endpoint (admin only)
        register_rest_route(TABESH_REST_NAMESPACE, '/delete-file/(?P<file_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'rest_delete_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Search orders endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/search-orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_search_orders'),
            'permission_callback' => array($this, 'check_upload_permission'),
            'args' => array(
                'search' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 3,
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Generate download token endpoint
        register_rest_route(TABESH_REST_NAMESPACE, '/generate-download-token/(?P<file_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_download_token'),
            'permission_callback' => array($this, 'check_view_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Approve file endpoint (admin only)
        register_rest_route(TABESH_REST_NAMESPACE, '/approve-file', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_approve_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));

        // Reject file endpoint (admin only)
        register_rest_route(TABESH_REST_NAMESPACE, '/reject-file', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_reject_file'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'file_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'reason' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                )
            )
        ));
    }

    /**
     * Check if user can upload files
     *
     * @return bool|WP_Error
     */
    public function check_upload_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('برای آپلود فایل باید وارد سیستم شوید.', 'tabesh'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Check if user can view files
     *
     * @return bool|WP_Error
     */
    public function check_view_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('برای مشاهده فایل‌ها باید وارد سیستم شوید.', 'tabesh'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Check if user can download files
     *
     * @return bool|WP_Error
     */
    public function check_download_permission() {
        // Allow download with valid token even if not logged in
        // Token validation happens in the download handler
        return true;
    }

    /**
     * Check if user has admin permission
     *
     * @return bool|WP_Error
     */
    public function check_admin_permission() {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('شما دسترسی مدیریتی ندارید.', 'tabesh'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * REST API: Upload file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_upload_file($request) {
        $order_id = intval($request->get_param('order_id'));
        $file_type = sanitize_text_field($request->get_param('file_type'));
        $user_id = get_current_user_id();

        // Validate order_id
        if ($order_id <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شناسه سفارش نامعتبر است.', 'tabesh')
            ), 400);
        }

        // Validate file_type
        if (!in_array($file_type, array('text', 'cover', 'documents'), true)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('نوع فایل نامعتبر است.', 'tabesh')
            ), 400);
        }

        // Check file was uploaded
        if (empty($_FILES['file'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایلی انتخاب نشده است.', 'tabesh')
            ), 400);
        }

        // Verify order ownership or admin access
        if (!$this->verify_order_access($order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این سفارش دسترسی ندارید.', 'tabesh')
            ), 403);
        }

        // Process upload
        $result = $this->process_upload($_FILES['file'], $order_id, $user_id, $file_type);

        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }

    /**
     * Process file upload
     *
     * @param array  $file_data  $_FILES array data
     * @param int    $order_id   Order ID
     * @param int    $user_id    User ID
     * @param string $file_type  File type (text, cover, documents)
     * @return array Result array
     */
    public function process_upload($file_data, $order_id, $user_id, $file_type) {
        // Check file errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => $this->get_upload_error_message($file_data['error'])
            );
        }

        // Validate file extension
        $file_ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        $allowed_types = $this->get_allowed_types($file_type);

        if (!in_array($file_ext, $allowed_types, true)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('فرمت فایل مجاز نیست. فرمت‌های مجاز: %s', 'tabesh'),
                    implode(', ', $allowed_types)
                )
            );
        }

        // Validate file size
        $max_size = $this->get_max_file_size($file_type);
        if ($file_data['size'] > $max_size) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('حجم فایل بیش از حد مجاز است. حداکثر: %s', 'tabesh'),
                    size_format($max_size)
                )
            );
        }

        // Check max file count for this type
        if (!$this->check_file_count_limit($order_id, $file_type)) {
            $max_count = $this->get_max_file_count($file_type);
            return array(
                'success' => false,
                'message' => sprintf(
                    __('حداکثر تعداد فایل مجاز برای این نوع %d عدد است.', 'tabesh'),
                    $max_count
                )
            );
        }

        // Create folder structure: /plugin-files/user-{id}/order-{id}/{type}/
        $user_folder = $this->upload_dir . 'user-' . $user_id . '/';
        $order_folder = $user_folder . 'order-' . $order_id . '/';
        $type_folder = $order_folder . $file_type . '/';

        if (!file_exists($type_folder)) {
            wp_mkdir_p($type_folder);
        }

        // Get next version number
        $version = $this->get_next_version($order_id, $file_type, $file_data['name']);

        // Generate filename with version
        $original_name = pathinfo($file_data['name'], PATHINFO_FILENAME);
        $safe_name = sanitize_file_name($original_name);
        
        if ($version > 1) {
            $stored_filename = $safe_name . ' v' . $version . '.' . $file_ext;
        } else {
            $stored_filename = $safe_name . '.' . $file_ext;
        }

        $file_path = $type_folder . $stored_filename;

        // Move uploaded file
        if (!move_uploaded_file($file_data['tmp_name'], $file_path)) {
            return array(
                'success' => false,
                'message' => __('خطا در ذخیره فایل. لطفاً دوباره تلاش کنید.', 'tabesh')
            );
        }

        // Set secure permissions
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
        chmod($file_path, 0644);

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        $insert_data = array(
            'order_id' => $order_id,
            'user_id' => $user_id,
            'file_type' => $file_ext,
            'file_category' => $file_type,
            'original_filename' => sanitize_file_name($file_data['name']),
            'stored_filename' => $stored_filename,
            'file_path' => str_replace($this->upload_dir, '', $file_path),
            'file_size' => $file_data['size'],
            'mime_type' => $file_data['type'] ? sanitize_text_field($file_data['type']) : 'application/octet-stream',
            'version' => $version,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table, $insert_data);
        $file_id = $wpdb->insert_id;

        if (!$file_id) {
            // Delete the uploaded file if database insert failed
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
            @unlink($file_path);
            return array(
                'success' => false,
                'message' => __('خطا در ذخیره اطلاعات فایل.', 'tabesh')
            );
        }

        // Log the upload action
        $this->log_action($order_id, $user_id, 'file_uploaded', sprintf(
            __('فایل "%s" (نسخه %d) آپلود شد', 'tabesh'),
            $stored_filename,
            $version
        ));

        return array(
            'success' => true,
            'message' => __('فایل با موفقیت آپلود شد.', 'tabesh'),
            'file_id' => $file_id,
            'filename' => $stored_filename,
            'version' => $version,
            'file_type' => $file_type,
            'upload_date' => current_time('mysql')
        );
    }

    /**
     * REST API: Get order files
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_get_order_files($request) {
        $order_id = $request->get_param('order_id');
        $user_id = get_current_user_id();

        // Verify access
        if (!$this->verify_order_access($order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این سفارش دسترسی ندارید.', 'tabesh')
            ), 403);
        }

        $files = $this->get_order_files($order_id);

        // Group files by type
        $grouped_files = array(
            'text' => array(),
            'cover' => array(),
            'documents' => array()
        );

        foreach ($files as $file) {
            $category = $file->file_category;
            if (isset($grouped_files[$category])) {
                $grouped_files[$category][] = $this->format_file_response($file);
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'files' => $grouped_files,
            'total_count' => count($files)
        ), 200);
    }

    /**
     * REST API: Download file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|void
     */
    public function rest_download_file($request) {
        $file_id = $request->get_param('file_id');
        $token = $request->get_param('token');
        $user_id = get_current_user_id();

        // Get file info
        $file = $this->get_file_by_id($file_id);

        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد.', 'tabesh')
            ), 404);
        }

        // Verify access: either valid token or order owner/admin
        $has_access = false;

        if (!empty($token)) {
            $has_access = $this->verify_download_token($file_id, $token);
        }

        if (!$has_access && $user_id > 0) {
            $has_access = $this->verify_order_access($file->order_id, $user_id);
        }

        if (!$has_access) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('دسترسی غیرمجاز.', 'tabesh')
            ), 403);
        }

        // Build file path
        $file_path = $this->upload_dir . $file->file_path;

        if (!file_exists($file_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل در سرور یافت نشد.', 'tabesh')
            ), 404);
        }

        // Log download
        $this->log_action($file->order_id, $user_id ?: 0, 'file_downloaded', sprintf(
            __('فایل "%s" دانلود شد', 'tabesh'),
            $file->stored_filename
        ));

        // Mark token as used if provided
        if (!empty($token)) {
            $this->mark_token_used($file_id, $token);
        }

        // Serve file
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file->mime_type);
        header('Content-Disposition: attachment; filename="' . $file->original_filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($file_path);
        exit;
    }

    /**
     * REST API: Delete file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_delete_file($request) {
        $file_id = $request->get_param('file_id');
        $user_id = get_current_user_id();

        // Get file info
        $file = $this->get_file_by_id($file_id);

        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد.', 'tabesh')
            ), 404);
        }

        // Delete physical file
        $file_path = $this->upload_dir . $file->file_path;
        if (file_exists($file_path)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
            @unlink($file_path);
        }

        // Mark as deleted in database
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            array(
                'deleted_at' => current_time('mysql'),
                'file_path' => null
            ),
            array('id' => $file_id),
            array('%s', '%s'),
            array('%d')
        );

        // Log deletion
        $this->log_action($file->order_id, $user_id, 'file_deleted', sprintf(
            __('فایل "%s" حذف شد', 'tabesh'),
            $file->stored_filename
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل با موفقیت حذف شد.', 'tabesh')
        ), 200);
    }

    /**
     * REST API: Search orders for upload manager
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_search_orders($request) {
        $search = $request->get_param('search');
        $page = max(1, $request->get_param('page'));
        $per_page = min(20, max(1, $request->get_param('per_page')));
        $user_id = get_current_user_id();

        global $wpdb;
        $table_orders = $wpdb->prefix . 'tabesh_orders';
        $table_files = $wpdb->prefix . 'tabesh_files';
        $offset = ($page - 1) * $per_page;

        // Build query based on user role
        $is_admin = current_user_can('manage_woocommerce');

        $where_clauses = array("o.archived = 0");
        $where_values = array();

        // Non-admins can only see their own orders
        if (!$is_admin) {
            $where_clauses[] = "o.user_id = %d";
            $where_values[] = $user_id;
        }

        // Add search condition
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(o.book_title LIKE %s OR o.order_number LIKE %s OR o.book_size LIKE %s OR CAST(o.quantity AS CHAR) LIKE %s OR CAST(o.page_count_total AS CHAR) LIKE %s)";
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Count total
        $count_query = "SELECT COUNT(*) FROM $table_orders o WHERE $where_sql";
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total = $wpdb->get_var($count_query);

        // Get orders with file counts
        $query = "SELECT o.*, 
                    (SELECT COUNT(*) FROM $table_files f WHERE f.order_id = o.id AND f.deleted_at IS NULL) as file_count,
                    (SELECT COUNT(*) FROM $table_files f WHERE f.order_id = o.id AND f.deleted_at IS NULL AND f.file_category = 'text') as text_count,
                    (SELECT COUNT(*) FROM $table_files f WHERE f.order_id = o.id AND f.deleted_at IS NULL AND f.file_category = 'cover') as cover_count,
                    (SELECT COUNT(*) FROM $table_files f WHERE f.order_id = o.id AND f.deleted_at IS NULL AND f.file_category = 'documents') as documents_count
                  FROM $table_orders o 
                  WHERE $where_sql 
                  ORDER BY o.created_at DESC 
                  LIMIT %d OFFSET %d";

        $query_values = array_merge($where_values, array($per_page, $offset));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $orders = $wpdb->get_results($wpdb->prepare($query, $query_values));

        // Format response
        $formatted_orders = array();
        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => intval($order->id),
                'order_number' => $order->order_number,
                'book_title' => $order->book_title ?: __('بدون عنوان', 'tabesh'),
                'book_size' => $order->book_size,
                'page_count' => intval($order->page_count_total),
                'quantity' => intval($order->quantity),
                'status' => $order->status,
                'created_at' => $order->created_at,
                'upload_status' => $this->get_upload_status($order),
                'file_counts' => array(
                    'text' => intval($order->text_count),
                    'cover' => intval($order->cover_count),
                    'documents' => intval($order->documents_count),
                    'total' => intval($order->file_count)
                )
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'orders' => $formatted_orders,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
            'has_more' => ($page * $per_page) < $total
        ), 200);
    }

    /**
     * REST API: Generate download token
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_generate_download_token($request) {
        $file_id = $request->get_param('file_id');
        $user_id = get_current_user_id();

        // Get file info
        $file = $this->get_file_by_id($file_id);

        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد.', 'tabesh')
            ), 404);
        }

        // Verify access
        if (!$this->verify_order_access($file->order_id, $user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('شما به این فایل دسترسی ندارید.', 'tabesh')
            ), 403);
        }

        // Generate token
        $token = $this->create_download_token($file_id, $user_id);

        // Get expiry time from settings (default 24 hours)
        $expiry_hours = intval(Tabesh()->get_setting('file_download_link_expiry', 24));

        return new WP_REST_Response(array(
            'success' => true,
            'token' => $token,
            'download_url' => rest_url(TABESH_REST_NAMESPACE . '/download/' . $file_id) . '?token=' . $token,
            'expires_in' => $expiry_hours . ' ' . __('ساعت', 'tabesh')
        ), 200);
    }

    /**
     * REST API: Approve file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_approve_file($request) {
        $file_id = intval($request->get_param('file_id'));
        $user_id = get_current_user_id();

        // Get file info
        $file = $this->get_file_by_id($file_id);

        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد.', 'tabesh')
            ), 404);
        }

        // Update file status
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'approved',
                'approved_by' => $user_id,
                'approved_at' => current_time('mysql'),
                'rejection_reason' => null,
                'expires_at' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $file_id),
            array('%s', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در تایید فایل.', 'tabesh')
            ), 500);
        }

        // Log action
        $this->log_action($file->order_id, $user_id, 'file_approved', sprintf(
            __('فایل "%s" تایید شد', 'tabesh'),
            $file->stored_filename
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل با موفقیت تایید شد.', 'tabesh')
        ), 200);
    }

    /**
     * REST API: Reject file
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function rest_reject_file($request) {
        $file_id = intval($request->get_param('file_id'));
        $reason = sanitize_textarea_field($request->get_param('reason'));
        $user_id = get_current_user_id();

        if (empty($reason)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('لطفاً دلیل رد را وارد کنید.', 'tabesh')
            ), 400);
        }

        // Get file info
        $file = $this->get_file_by_id($file_id);

        if (!$file) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('فایل یافت نشد.', 'tabesh')
            ), 404);
        }

        // Calculate expiration date
        $retention_days = intval(Tabesh()->get_setting('file_retention_days', 5));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$retention_days} days"));

        // Update file status
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'expires_at' => $expires_at,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $file_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('خطا در رد فایل.', 'tabesh')
            ), 500);
        }

        // Log action
        $this->log_action($file->order_id, $user_id, 'file_rejected', sprintf(
            __('فایل "%s" رد شد: %s', 'tabesh'),
            $file->stored_filename,
            $reason
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('فایل رد شد.', 'tabesh')
        ), 200);
    }

    /**
     * Create secure download token
     *
     * @param int $file_id File ID
     * @param int $user_id User ID
     * @return string Token
     */
    private function create_download_token($file_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        // Generate random token
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        // Get expiry time from settings
        $expiry_hours = intval(Tabesh()->get_setting('file_download_link_expiry', 24));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

        // Insert token
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table, array(
            'file_id' => $file_id,
            'user_id' => $user_id,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'used' => 0,
            'created_at' => current_time('mysql')
        ));

        return $token;
    }

    /**
     * Verify download token
     *
     * @param int    $file_id File ID
     * @param string $token   Token
     * @return bool
     */
    private function verify_download_token($file_id, $token) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        $token_hash = hash('sha256', $token);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE file_id = %d AND token_hash = %s AND expires_at > NOW() AND used = 0",
            $file_id,
            $token_hash
        ));

        return !empty($result);
    }

    /**
     * Mark download token as used
     *
     * @param int    $file_id File ID
     * @param string $token   Token
     * @return void
     */
    private function mark_token_used($file_id, $token) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_download_tokens';

        $token_hash = hash('sha256', $token);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            array(
                'used' => 1,
                'used_at' => current_time('mysql')
            ),
            array(
                'file_id' => $file_id,
                'token_hash' => $token_hash
            ),
            array('%d', '%s'),
            array('%d', '%s')
        );
    }

    /**
     * Get file by ID
     *
     * @param int $file_id File ID
     * @return object|null
     */
    private function get_file_by_id($file_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
            $file_id
        ));
    }

    /**
     * Get order files
     *
     * @param int $order_id Order ID
     * @return array
     */
    private function get_order_files($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d AND deleted_at IS NULL ORDER BY file_category, version DESC, created_at DESC",
            $order_id
        ));
    }

    /**
     * Format file data for API response
     *
     * @param object $file File object
     * @return array
     */
    private function format_file_response($file) {
        return array(
            'id' => intval($file->id),
            'filename' => $file->stored_filename,
            'original_filename' => $file->original_filename,
            'size' => intval($file->file_size),
            'size_formatted' => size_format($file->file_size),
            'type' => $file->file_type,
            'category' => $file->file_category,
            'version' => intval($file->version),
            'status' => $file->status,
            'upload_date' => $file->created_at,
            'upload_date_formatted' => date_i18n('j F Y - H:i', strtotime($file->created_at))
        );
    }

    /**
     * Verify user access to order
     *
     * @param int $order_id Order ID
     * @param int $user_id  User ID
     * @return bool
     */
    private function verify_order_access($order_id, $user_id) {
        // Admins have access to all orders
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $order_id
        ));

        return $order && intval($order->user_id) === $user_id;
    }

    /**
     * Get next version number for file
     *
     * @param int    $order_id     Order ID
     * @param string $file_type    File type
     * @param string $filename     Original filename
     * @return int
     */
    private function get_next_version($order_id, $file_type, $filename) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $safe_name = sanitize_file_name($base_name);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM $table WHERE order_id = %d AND file_category = %s AND original_filename LIKE %s AND deleted_at IS NULL",
            $order_id,
            $file_type,
            $safe_name . '%'
        ));

        return $max_version ? intval($max_version) + 1 : 1;
    }

    /**
     * Check if file count limit is reached
     *
     * @param int    $order_id  Order ID
     * @param string $file_type File type
     * @return bool True if more files can be uploaded
     */
    private function check_file_count_limit($order_id, $file_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_files';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $current_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE order_id = %d AND file_category = %s AND deleted_at IS NULL",
            $order_id,
            $file_type
        ));

        $max_count = $this->get_max_file_count($file_type);

        return intval($current_count) < $max_count;
    }

    /**
     * Get allowed file types for a category
     *
     * @param string $file_type File type
     * @return array
     */
    private function get_allowed_types($file_type) {
        $setting_key = 'file_allowed_types_' . $file_type;
        $setting = Tabesh()->get_setting($setting_key, null);

        if ($setting && is_array($setting)) {
            return $setting;
        }

        return $this->allowed_types[$file_type] ?? array('pdf');
    }

    /**
     * Get max file size for a category
     *
     * @param string $file_type File type
     * @return int Size in bytes
     */
    private function get_max_file_size($file_type) {
        $setting_key = 'file_max_size_' . $file_type;
        $setting = Tabesh()->get_setting($setting_key, null);

        if ($setting) {
            return intval($setting);
        }

        return $this->default_max_sizes[$file_type] ?? 10485760;
    }

    /**
     * Get max file count for a category
     *
     * @param string $file_type File type
     * @return int
     */
    private function get_max_file_count($file_type) {
        $setting_key = 'file_max_count_' . $file_type;
        $setting = Tabesh()->get_setting($setting_key, null);

        if ($setting) {
            return intval($setting);
        }

        return $this->default_max_counts[$file_type] ?? 10;
    }

    /**
     * Get upload status summary for an order
     *
     * @param object $order Order object with file counts
     * @return array
     */
    private function get_upload_status($order) {
        $has_files = intval($order->file_count) > 0;
        $has_text = intval($order->text_count) > 0;
        $has_cover = intval($order->cover_count) > 0;

        // Calculate progress percentage
        $required_types = 2; // text and cover are required
        $completed = 0;
        if ($has_text) {
            $completed++;
        }
        if ($has_cover) {
            $completed++;
        }
        $progress = round(($completed / $required_types) * 100);

        return array(
            'has_files' => $has_files,
            'has_text' => $has_text,
            'has_cover' => $has_cover,
            'is_complete' => $has_text && $has_cover,
            'progress' => $progress,
            'label' => $has_files ? __('آپلود شده', 'tabesh') : __('در انتظار آپلود', 'tabesh')
        );
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('حجم فایل بیش از حد مجاز است.', 'tabesh');
            case UPLOAD_ERR_PARTIAL:
                return __('فایل به طور کامل آپلود نشد. لطفاً دوباره تلاش کنید.', 'tabesh');
            case UPLOAD_ERR_NO_FILE:
                return __('فایلی انتخاب نشده است.', 'tabesh');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('خطای سرور: پوشه موقت یافت نشد.', 'tabesh');
            case UPLOAD_ERR_CANT_WRITE:
                return __('خطای سرور: امکان نوشتن فایل وجود ندارد.', 'tabesh');
            case UPLOAD_ERR_EXTENSION:
                return __('آپلود توسط یک افزونه متوقف شد.', 'tabesh');
            default:
                return __('خطای ناشناخته در آپلود فایل.', 'tabesh');
        }
    }

    /**
     * Log action to database
     *
     * @param int    $order_id    Order ID
     * @param int    $user_id     User ID
     * @param string $action      Action name
     * @param string $description Description
     * @return void
     */
    private function log_action($order_id, $user_id, $action, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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
    public function render_upload_manager($atts = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="tabesh-notice error">' . 
                   __('برای دسترسی به مدیریت آپلود فایل باید وارد حساب کاربری خود شوید.', 'tabesh') . 
                   '</div>';
        }

        // Enqueue upload manager assets
        $this->enqueue_upload_assets();

        // Start output buffering
        ob_start();

        // Include template
        include TABESH_PLUGIN_DIR . 'templates/upload-manager.php';

        return ob_get_clean();
    }

    /**
     * Enqueue upload manager specific assets
     *
     * @return void
     */
    private function enqueue_upload_assets() {
        // Helper function for file versioning
        $get_file_version = function($file_path) {
            if (defined('WP_DEBUG') && WP_DEBUG && file_exists($file_path)) {
                $mtime = @filemtime($file_path);
                return $mtime !== false ? $mtime : TABESH_VERSION;
            }
            return TABESH_VERSION;
        };

        // Enqueue upload CSS
        wp_enqueue_style(
            'tabesh-upload',
            TABESH_PLUGIN_URL . 'assets/css/upload.css',
            array(),
            $get_file_version(TABESH_PLUGIN_DIR . 'assets/css/upload.css')
        );

        // Enqueue upload JS
        wp_enqueue_script(
            'tabesh-upload',
            TABESH_PLUGIN_URL . 'assets/js/upload.js',
            array('jquery'),
            $get_file_version(TABESH_PLUGIN_DIR . 'assets/js/upload.js'),
            true
        );

        // Pass data to JavaScript
        wp_localize_script('tabesh-upload', 'tabeshUploadData', array(
            'restUrl' => rest_url(TABESH_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'maxSizes' => array(
                'text' => $this->get_max_file_size('text'),
                'cover' => $this->get_max_file_size('cover'),
                'documents' => $this->get_max_file_size('documents')
            ),
            'allowedTypes' => array(
                'text' => $this->get_allowed_types('text'),
                'cover' => $this->get_allowed_types('cover'),
                'documents' => $this->get_allowed_types('documents')
            ),
            'strings' => array(
                'uploading' => __('در حال آپلود...', 'tabesh'),
                'uploadSuccess' => __('فایل با موفقیت آپلود شد.', 'tabesh'),
                'uploadError' => __('خطا در آپلود فایل.', 'tabesh'),
                'downloading' => __('در حال دانلود...', 'tabesh'),
                'noResults' => __('نتیجه‌ای یافت نشد', 'tabesh'),
                'loadMore' => __('مشاهده بیشتر', 'tabesh'),
                'loading' => __('در حال بارگذاری...', 'tabesh'),
                'searchPlaceholder' => __('جستجو...', 'tabesh'),
                'confirmDelete' => __('آیا از حذف این فایل اطمینان دارید؟', 'tabesh'),
                'networkError' => __('اتصال اینترنت را بررسی کنید', 'tabesh'),
                'pleaseWait' => __('لطفاً منتظر بمانید...', 'tabesh'),
                'text' => __('فایل متن کتاب', 'tabesh'),
                'cover' => __('فایل جلد کتاب', 'tabesh'),
                'documents' => __('مدارک', 'tabesh')
            )
        ));
    }
}
