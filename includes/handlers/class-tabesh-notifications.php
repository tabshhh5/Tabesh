<?php
/**
 * Notifications Class
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Notifications {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into order events
        add_action('tabesh_order_submitted', array($this, 'send_order_submitted_notification'), 10, 2);
        add_action('tabesh_order_status_changed', array($this, 'send_status_change_notification'), 10, 2);
    }

    /**
     * Send SMS via MelliPayamak
     *
     * @param string $to Phone number
     * @param string $message Message text
     * @return bool|WP_Error
     */
    private function send_sms($to, $message) {
        $admin = Tabesh()->admin;
        
        $username = $admin->get_setting('mellipayamak_username');
        $password = $admin->get_setting('mellipayamak_password');
        $from = $admin->get_setting('mellipayamak_from');

        if (empty($username) || empty($password) || empty($from)) {
            return new WP_Error('sms_config_missing', __('تنظیمات SMS کامل نیست', 'tabesh'));
        }

        // MelliPayamak REST API
        $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
        
        $data = array(
            'username' => $username,
            'password' => $password,
            'to' => $to,
            'from' => $from,
            'text' => $message,
            'isflash' => false
        );

        $response = wp_remote_post($url, array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['Value']) && $result['Value'] > 0) {
            return true;
        }

        return new WP_Error('sms_send_failed', __('ارسال SMS با خطا مواجه شد', 'tabesh'));
    }

    /**
     * Send notification on order submission
     *
     * @param int $order_id
     * @param array $order_data
     */
    public function send_order_submitted_notification($order_id, $order_data) {
        $admin = Tabesh()->admin;
        
        if (!$admin->get_setting('sms_on_order_submit', '1')) {
            return;
        }

        // Get user info
        $user = get_userdata($order_data['user_id']);
        if (!$user) {
            return;
        }

        $user_phone = get_user_meta($order_data['user_id'], 'billing_phone', true);
        
        // Send SMS to customer
        if (!empty($user_phone)) {
            $message = sprintf(
                "سلام %s\nسفارش شما با شماره %s ثبت شد.\nتیم چاپکو",
                $user->display_name,
                $order_data['order_number']
            );
            $this->send_sms($user_phone, $message);
        }

        // Send SMS to admin
        $admin_phone = $admin->get_setting('admin_phone');
        if (!empty($admin_phone)) {
            $message = sprintf(
                "سفارش جدید\nشماره: %s\nمشتری: %s\nمبلغ: %s تومان",
                $order_data['order_number'],
                $user->display_name,
                number_format($order_data['total_price'])
            );
            $this->send_sms($admin_phone, $message);
        }

        // Send email notification
        $this->send_email_notification($user->user_email, 'order_submitted', $order_data);
    }

    /**
     * Send notification on status change
     *
     * @param int $order_id
     * @param string $status
     */
    public function send_status_change_notification($order_id, $status) {
        $admin = Tabesh()->admin;
        
        if (!$admin->get_setting('sms_on_status_change', '1')) {
            return;
        }

        $order = Tabesh()->order->get_order($order_id);
        if (!$order) {
            return;
        }

        $user = get_userdata($order->user_id);
        if (!$user) {
            return;
        }

        $user_phone = get_user_meta($order->user_id, 'billing_phone', true);

        if (!empty($user_phone)) {
            $status_labels = array(
                'pending' => 'در انتظار بررسی',
                'confirmed' => 'تایید شده',
                'processing' => 'در حال چاپ',
                'ready' => 'آماده تحویل',
                'completed' => 'تحویل داده شده',
                'cancelled' => 'لغو شده'
            );

            $status_label = $status_labels[$status] ?? $status;

            $message = sprintf(
                "سلام %s\nوضعیت سفارش %s به \"%s\" تغییر کرد.\nتیم چاپکو",
                $user->display_name,
                $order->order_number,
                $status_label
            );
            
            $this->send_sms($user_phone, $message);
        }

        // Send email notification
        $this->send_email_notification($user->user_email, 'status_changed', array(
            'order_number' => $order->order_number,
            'status' => $status
        ));
    }

    /**
     * Send email notification
     *
     * @param string $to Email address
     * @param string $type Notification type
     * @param array $data Additional data
     */
    private function send_email_notification($to, $type, $data) {
        $subject = '';
        $message = '';

        switch ($type) {
            case 'order_submitted':
                $subject = 'ثبت سفارش جدید - چاپکو';
                $message = sprintf(
                    "سفارش شما با شماره %s با موفقیت ثبت شد.\n\nجزئیات سفارش:\nتعداد: %d\nمبلغ کل: %s تومان\n\nبا تشکر\nتیم چاپکو",
                    $data['order_number'],
                    $data['quantity'],
                    number_format($data['total_price'])
                );
                break;

            case 'status_changed':
                $subject = 'تغییر وضعیت سفارش - چاپکو';
                $message = sprintf(
                    "وضعیت سفارش %s تغییر کرد.\n\nوضعیت جدید: %s\n\nبا تشکر\nتیم چاپکو",
                    $data['order_number'],
                    $data['status']
                );
                break;
        }

        if (!empty($subject) && !empty($message)) {
            wp_mail($to, $subject, $message);
        }
    }
}
