<?php
/**
 * SMS Handler Class for MelliPayamak Template-based SMS
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Tabesh_SMS
 * 
 * Handles SMS notifications using MelliPayamak's template-based (pattern) API.
 * Sends SMS notifications when order status changes based on admin settings.
 */
class Tabesh_SMS {

    /**
     * MelliPayamak API base URL for template-based SMS
     *
     * @var string
     */
    const API_BASE_URL = 'https://console.melipayamak.com/api/send/shared/';

    /**
     * Order statuses with Persian labels
     *
     * @var array
     */
    private static $status_labels = array(
        'pending'    => 'در حال بررسی',
        'confirmed'  => 'تایید شده',
        'processing' => 'در حال چاپ',
        'ready'      => 'آماده تحویل',
        'completed'  => 'تحویل داده شده',
        'cancelled'  => 'لغو شده',
        'archived'   => 'بایگانی شده',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into order status change event
        add_action('tabesh_order_status_changed', array($this, 'on_status_changed'), 15, 2);
    }

    /**
     * Check if SMS system is enabled
     *
     * @return bool
     */
    public function is_sms_enabled() {
        return (bool) Tabesh()->get_setting('sms_enabled', '0');
    }

    /**
     * Check if SMS is enabled for a specific status
     *
     * @param string $status Order status
     * @return bool
     */
    public function is_status_enabled($status) {
        if (!$this->is_sms_enabled()) {
            return false;
        }

        $status = sanitize_text_field($status);
        return (bool) Tabesh()->get_setting('sms_status_' . $status . '_enabled', '0');
    }

    /**
     * Get pattern code for a status
     *
     * @param string $status Order status
     * @return string Pattern code or empty string
     */
    public function get_pattern_code($status) {
        $status = sanitize_text_field($status);
        return Tabesh()->get_setting('sms_status_' . $status . '_pattern', '');
    }

    /**
     * Validate Iranian mobile number
     * 
     * Valid format: 09XXXXXXXXX (11 digits starting with 09)
     *
     * @param string $phone Phone number
     * @return string|false Sanitized phone number or false if invalid
     */
    public function validate_phone($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check for valid Iranian mobile format (11 digits starting with 09)
        if (preg_match('/^09[0-9]{9}$/', $phone)) {
            return $phone;
        }
        
        // Also accept format without leading 0 (10 digits starting with 9)
        if (preg_match('/^9[0-9]{9}$/', $phone)) {
            return '0' . $phone;
        }
        
        return false;
    }

    /**
     * Get order variables for SMS template
     *
     * @param object $order Order object from database
     * @return array Variables for SMS template
     */
    public function get_order_variables($order) {
        if (!$order) {
            return array();
        }

        // Get user information
        $user = get_userdata($order->user_id);
        $customer_name = $user ? $user->display_name : __('مشتری', 'tabesh');
        
        // Get status label in Persian
        $status_label = isset(self::$status_labels[$order->status]) 
            ? self::$status_labels[$order->status] 
            : $order->status;

        // Format date in Persian (Jalali) if possible, otherwise use Gregorian
        $date = date_i18n('Y/m/d', strtotime($order->created_at));

        return array(
            'order_number'  => $order->order_number,
            'customer_name' => $customer_name,
            'status'        => $status_label,
            'date'          => $date,
            'book_title'    => $order->book_title ?? '',
            'quantity'      => $order->quantity ?? 0,
            'total_price'   => number_format($order->total_price ?? 0),
        );
    }

    /**
     * Send template-based SMS via MelliPayamak API
     *
     * @param string $phone Recipient phone number
     * @param string $pattern_code Pattern code (bodyId) from MelliPayamak
     * @param array  $parameters Template parameters
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function send_template_sms($phone, $pattern_code, $parameters = array()) {
        // Validate phone number
        $phone = $this->validate_phone($phone);
        if (!$phone) {
            $this->log_error('invalid_phone', __('شماره موبایل نامعتبر است', 'tabesh'));
            return new WP_Error('invalid_phone', __('شماره موبایل نامعتبر است', 'tabesh'));
        }

        // Get API credentials - don't log sensitive data
        $username = Tabesh()->get_setting('sms_username', '');
        $password = Tabesh()->get_setting('sms_password', '');

        if (empty($username) || empty($password)) {
            $this->log_error('config_missing', __('تنظیمات SMS کامل نیست', 'tabesh'));
            return new WP_Error('sms_config_missing', __('تنظیمات SMS کامل نیست', 'tabesh'));
        }

        if (empty($pattern_code)) {
            $this->log_error('pattern_missing', __('کد الگوی پیامک تعریف نشده', 'tabesh'));
            return new WP_Error('pattern_missing', __('کد الگوی پیامک تعریف نشده', 'tabesh'));
        }

        // Prepare API request body
        // MelliPayamak template API uses 'args' array for template variables
        $body = array(
            'username' => $username,
            'password' => $password,
            'to'       => $phone,
            'bodyId'   => intval($pattern_code),
        );

        // Add template parameters as 'args' array (values only, in order)
        if (!empty($parameters) && is_array($parameters)) {
            $body['args'] = array_values($parameters);
        }

        // Send API request using WordPress HTTP API
        $response = wp_remote_post(self::API_BASE_URL . $pattern_code, array(
            'body'    => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        // Check for HTTP error
        if (is_wp_error($response)) {
            $this->log_error('http_error', $response->get_error_message());
            return $response;
        }

        // Parse response
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        // Log response for debugging (without sensitive data)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Tabesh SMS: Response code: %d, Body: %s',
                $response_code,
                $response_body
            ));
        }

        // Check response status
        // MelliPayamak returns numeric status codes
        // Positive values indicate success (message ID)
        // Negative values indicate errors
        if ($response_code === 200 && isset($result['Value'])) {
            if (is_numeric($result['Value']) && intval($result['Value']) > 0) {
                $this->log_success($phone, $pattern_code, intval($result['Value']));
                return true;
            }
        }

        // Handle error response
        $error_message = isset($result['RetStatus']) ? $result['RetStatus'] : __('خطا در ارسال پیامک', 'tabesh');
        $this->log_error('api_error', $error_message, array(
            'phone'   => substr($phone, 0, 4) . '****' . substr($phone, -2),
            'pattern' => $pattern_code,
        ));

        return new WP_Error('sms_send_failed', $error_message);
    }

    /**
     * Send SMS notification for order status change
     *
     * @param int    $order_id  Order ID
     * @param string $new_status New order status
     * @return bool|WP_Error True on success, WP_Error on failure, false if disabled
     */
    public function send_order_status_sms($order_id, $new_status) {
        // Check if SMS is enabled for this status
        if (!$this->is_status_enabled($new_status)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh SMS: SMS disabled for status "%s"', $new_status));
            }
            return false;
        }

        // Get pattern code for this status
        $pattern_code = $this->get_pattern_code($new_status);
        if (empty($pattern_code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('Tabesh SMS: No pattern code for status "%s"', $new_status));
            }
            return false;
        }

        // Get order details
        $order = Tabesh()->order->get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('سفارش یافت نشد', 'tabesh'));
        }

        // Get customer phone number
        $phone = get_user_meta($order->user_id, 'billing_phone', true);
        if (empty($phone)) {
            $this->log_error('no_phone', __('شماره موبایل مشتری یافت نشد', 'tabesh'), array(
                'order_id' => $order_id,
            ));
            return new WP_Error('no_phone', __('شماره موبایل مشتری یافت نشد', 'tabesh'));
        }

        // Get order variables for template
        $variables = $this->get_order_variables($order);

        // Send SMS
        $result = $this->send_template_sms($phone, $pattern_code, $variables);

        // Log the action
        if (!is_wp_error($result) && $result === true) {
            $this->log_action(
                $order_id,
                'sms_sent',
                sprintf(
                    __('پیامک وضعیت "%s" به شماره %s ارسال شد', 'tabesh'),
                    self::$status_labels[$new_status] ?? $new_status,
                    substr($phone, 0, 4) . '****' . substr($phone, -2)
                )
            );
        }

        return $result;
    }

    /**
     * Handler for order status changed event
     *
     * @param int    $order_id Order ID
     * @param string $status   New status
     */
    public function on_status_changed($order_id, $status) {
        // Send SMS notification (will check if enabled internally)
        $this->send_order_status_sms($order_id, $status);
    }

    /**
     * Send test SMS
     *
     * @param string $phone      Test phone number
     * @param string $pattern_code Pattern code to test
     * @return bool|WP_Error
     */
    public function send_test_sms($phone, $pattern_code) {
        // Create test parameters
        $test_params = array(
            'TB-TEST-00001',     // order_number
            __('مشتری آزمایشی', 'tabesh'),  // customer_name
            __('تست', 'tabesh'),  // status
            date_i18n('Y/m/d'),  // date
        );

        return $this->send_template_sms($phone, $pattern_code, $test_params);
    }

    /**
     * Log successful SMS send
     *
     * @param string $phone      Phone number (partially masked)
     * @param string $pattern    Pattern code
     * @param int    $message_id Message ID from API
     */
    private function log_success($phone, $pattern, $message_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Tabesh SMS: Successfully sent to %s****%s, pattern: %s, message_id: %d',
                substr($phone, 0, 4),
                substr($phone, -2),
                $pattern,
                $message_id
            ));
        }
    }

    /**
     * Log SMS error to database and error_log
     *
     * @param string $code    Error code
     * @param string $message Error message
     * @param array  $context Additional context (will not include sensitive data)
     */
    private function log_error($code, $message, $context = array()) {
        // Log to error_log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Tabesh SMS Error [%s]: %s - Context: %s',
                $code,
                $message,
                wp_json_encode($context)
            ));
        }

        // Log to database
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_logs';

        $wpdb->insert(
            $table,
            array(
                'order_id'    => isset($context['order_id']) ? intval($context['order_id']) : null,
                'user_id'     => get_current_user_id(),
                'action'      => 'sms_error_' . $code,
                'description' => $message,
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    /**
     * Log SMS action to database
     *
     * @param int    $order_id    Order ID
     * @param string $action      Action type
     * @param string $description Description
     */
    private function log_action($order_id, $action, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_logs';

        $wpdb->insert(
            $table,
            array(
                'order_id'    => intval($order_id),
                'user_id'     => get_current_user_id(),
                'action'      => sanitize_text_field($action),
                'description' => sanitize_text_field($description),
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    /**
     * Get status labels
     *
     * @return array
     */
    public static function get_status_labels() {
        return self::$status_labels;
    }
}
