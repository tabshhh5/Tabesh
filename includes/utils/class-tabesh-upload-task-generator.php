<?php
/**
 * Upload Task Generator Class
 *
 * Analyzes orders and generates dynamic upload tasks with validation rules
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_Upload_Task_Generator {

    /**
     * Binding types that don't require a cover
     */
    const BINDING_NO_COVER = array('سیمی');
    
    /**
     * Services that require additional documents
     */
    const SERVICES_REQUIRE_DOCS = array('شماره گذاری', 'لب گرد');
    
    /**
     * Currency conversion ratio (Rial to Toman)
     */
    const RIAL_TO_TOMAN = 10;

    /**
     * Generate upload tasks for an order
     *
     * Analyzes order details and creates appropriate upload tasks
     *
     * @param int|object $order Order ID or order object
     * @return array Array of upload task configurations
     */
    public function generate_tasks($order) {
        if (is_numeric($order)) {
            $order = $this->get_order($order);
        }
        
        if (!$order) {
            return array();
        }
        
        $tasks = array();
        
        // Task 1: Book Content (always required)
        $tasks[] = $this->generate_book_content_task($order);
        
        // Task 2: Book Cover (always required unless it's a simple binding)
        if (!in_array($order->binding_type, self::BINDING_NO_COVER)) {
            $tasks[] = $this->generate_book_cover_task($order);
        }
        
        // Task 3: License/Permission (if customer has license)
        if ($order->license_type === 'دارم') {
            $tasks[] = $this->generate_license_task($order);
        }
        
        // Task 4: Custom documents based on extras
        $extras = $this->parse_extras($order->extras);
        if (!empty($extras)) {
            // Add document upload if special services are requested
            if (array_intersect(self::SERVICES_REQUIRE_DOCS, $extras)) {
                $tasks[] = $this->generate_documents_task($order);
            }
        }
        
        return $tasks;
    }
    
    /**
     * Generate book content upload task
     *
     * @param object $order Order object
     * @return array Task configuration
     */
    private function generate_book_content_task($order) {
        $admin = Tabesh()->admin;
        $book_format = $order->book_size;
        
        // Get format-specific settings
        $format_settings = $this->get_format_settings($book_format, 'book_content');
        
        $task = array(
            'id' => 'book_content',
            'title' => __('محتوای کتاب (PDF)', 'tabesh'),
            'description' => sprintf(
                __('فایل PDF محتوای کتاب خود را آپلود کنید. فایل باید شامل %d صفحه باشد (±2 صفحه قابل قبول است).', 'tabesh'),
                $order->page_count_total
            ),
            'category' => 'book_content',
            'required' => true,
            'allowed_types' => array('pdf'),
            'max_file_size' => intval($admin->get_setting('file_max_size_pdf', 52428800)),
            'max_file_size_display' => size_format(intval($admin->get_setting('file_max_size_pdf', 52428800))),
            'max_files' => 1,
            'validation_rules' => array(
                'page_count' => array(
                    'expected' => $order->page_count_total,
                    'tolerance' => 2
                ),
                'book_format' => $book_format
            ),
            'requirements' => array(
                sprintf(__('قطع: %s', 'tabesh'), $book_format),
                sprintf(__('تعداد صفحات: %d', 'tabesh'), $order->page_count_total),
                __('فرمت: PDF', 'tabesh'),
                sprintf(__('حداکثر حجم: %s', 'tabesh'), size_format(intval($admin->get_setting('file_max_size_pdf', 52428800))))
            )
        );
        
        // Add resolution requirements from format settings
        if ($format_settings && $format_settings->min_resolution) {
            $task['validation_rules']['min_dpi'] = $format_settings->min_resolution;
            $task['requirements'][] = sprintf(__('حداقل DPI: %d', 'tabesh'), $format_settings->min_resolution);
        }
        
        // Add color page information for mixed/color print types
        if (in_array($order->print_type, array('رنگی', 'ترکیبی')) && $order->page_count_color > 0) {
            $task['validation_rules']['color_pages'] = $order->page_count_color;
            $task['requirements'][] = sprintf(__('صفحات رنگی: %d صفحه', 'tabesh'), $order->page_count_color);
        }
        
        return $task;
    }
    
    /**
     * Generate book cover upload task
     *
     * @param object $order Order object
     * @return array Task configuration
     */
    private function generate_book_cover_task($order) {
        $admin = Tabesh()->admin;
        $book_format = $order->book_size;
        
        // Get format-specific settings
        $format_settings = $this->get_format_settings($book_format, 'book_cover');
        
        $task = array(
            'id' => 'book_cover',
            'title' => __('جلد کتاب', 'tabesh'),
            'description' => __('فایل طراحی جلد کتاب را آپلود کنید. فایل باید با کیفیت چاپ و در فضای رنگی CMYK باشد.', 'tabesh'),
            'category' => 'book_cover',
            'required' => true,
            'allowed_types' => array('psd', 'pdf', 'jpg', 'jpeg', 'png'),
            'max_file_size' => intval($admin->get_setting('file_max_size_image', 10485760)),
            'max_file_size_display' => size_format(intval($admin->get_setting('file_max_size_image', 10485760))),
            'max_files' => 1,
            'validation_rules' => array(
                'min_dpi' => intval($admin->get_setting('file_min_dpi', 300)),
                'required_color_mode' => 'CMYK',
                'book_format' => $book_format
            ),
            'requirements' => array(
                sprintf(__('قطع: %s', 'tabesh'), $book_format),
                __('فرمت: PSD, PDF, JPG, PNG', 'tabesh'),
                sprintf(__('حداقل DPI: %d', 'tabesh'), intval($admin->get_setting('file_min_dpi', 300))),
                __('فضای رنگی: CMYK (توصیه می‌شود)', 'tabesh'),
                sprintf(__('حداکثر حجم: %s', 'tabesh'), size_format(intval($admin->get_setting('file_max_size_image', 10485760))))
            )
        );
        
        // Add cover-specific requirements
        if ($order->cover_paper_weight) {
            $task['requirements'][] = sprintf(__('وزن کاغذ جلد: %s گرم', 'tabesh'), $order->cover_paper_weight);
        }
        
        if ($order->lamination_type && $order->lamination_type !== 'بدون سلفون') {
            $task['requirements'][] = sprintf(__('سلفون: %s', 'tabesh'), $order->lamination_type);
        }
        
        return $task;
    }
    
    /**
     * Generate license document upload task
     *
     * @param object $order Order object
     * @return array Task configuration
     */
    private function generate_license_task($order) {
        $admin = Tabesh()->admin;
        
        return array(
            'id' => 'license_document',
            'title' => __('مجوز انتشار', 'tabesh'),
            'description' => __('تصویر یا PDF مجوز انتشار کتاب را آپلود کنید.', 'tabesh'),
            'category' => 'document',
            'document_type' => 'license',
            'required' => true,
            'allowed_types' => array('pdf', 'jpg', 'jpeg', 'png'),
            'max_file_size' => intval($admin->get_setting('file_max_size_document', 10485760)),
            'max_file_size_display' => size_format(intval($admin->get_setting('file_max_size_document', 10485760))),
            'max_files' => 1,
            'validation_rules' => array(
                'min_dpi' => 200
            ),
            'requirements' => array(
                __('فرمت: PDF, JPG, PNG', 'tabesh'),
                __('تصویر واضح و خوانا', 'tabesh'),
                sprintf(__('حداکثر حجم: %s', 'tabesh'), size_format(intval($admin->get_setting('file_max_size_document', 10485760))))
            )
        );
    }
    
    /**
     * Generate documents upload task
     *
     * @param object $order Order object
     * @return array Task configuration
     */
    private function generate_documents_task($order) {
        $admin = Tabesh()->admin;
        
        return array(
            'id' => 'additional_documents',
            'title' => __('مدارک اضافی (اختیاری)', 'tabesh'),
            'description' => __('در صورت نیاز، مدارک مورد نظر (شناسنامه، کارت ملی، مجوز و ...) را آپلود کنید.', 'tabesh'),
            'category' => 'document',
            'required' => false,
            'allowed_types' => array('pdf', 'jpg', 'jpeg', 'png'),
            'max_file_size' => intval($admin->get_setting('file_max_size_document', 10485760)),
            'max_file_size_display' => size_format(intval($admin->get_setting('file_max_size_document', 10485760))),
            'max_files' => 5,
            'validation_rules' => array(
                'min_dpi' => 150
            ),
            'requirements' => array(
                __('فرمت: PDF, JPG, PNG', 'tabesh'),
                __('تا 5 فایل', 'tabesh'),
                sprintf(__('حداکثر حجم هر فایل: %s', 'tabesh'), size_format(intval($admin->get_setting('file_max_size_document', 10485760))))
            )
        );
    }
    
    /**
     * Get format settings from database
     *
     * @param string $book_format Book format (A5, A4, etc.)
     * @param string $file_category File category
     * @return object|null Format settings object or null
     */
    private function get_format_settings($book_format, $file_category) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_book_format_settings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE book_format = %s AND file_category = %s",
            $book_format,
            $file_category
        ));
    }
    
    /**
     * Get order by ID
     *
     * @param int $order_id Order ID
     * @return object|null Order object or null
     */
    private function get_order($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_orders';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $order_id
        ));
    }
    
    /**
     * Parse extras string
     *
     * @param string $extras_string Extras JSON or comma-separated string
     * @return array Array of extras
     */
    private function parse_extras($extras_string) {
        if (empty($extras_string)) {
            return array();
        }
        
        // Try JSON decode first
        $extras = json_decode($extras_string, true);
        if (is_array($extras)) {
            return $extras;
        }
        
        // Fall back to comma-separated
        return array_map('trim', explode(',', $extras_string));
    }
}
