<?php
/**
 * File Validator Class
 *
 * Handles specialized file validation for different file types
 *
 * @package Tabesh
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Tabesh_File_Validator {

    /**
     * Validate file based on category
     *
     * @param string $file_path File path
     * @param string $category File category (book_content, book_cover, document)
     * @param array $order_data Order data for validation context
     * @return array Validation result with success, warnings, and data
     */
    public function validate_file($file_path, $category, $order_data = array()) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'errors' => array(__('فایل یافت نشد', 'tabesh')),
                'warnings' => array(),
                'data' => array()
            );
        }

        switch ($category) {
            case 'book_content':
                return $this->validate_book_content($file_path, $order_data);
            
            case 'book_cover':
                return $this->validate_book_cover($file_path, $order_data);
            
            case 'document':
                return $this->validate_document($file_path, $order_data);
            
            default:
                return $this->validate_generic($file_path);
        }
    }

    /**
     * Validate book content file (PDF)
     *
     * @param string $file_path File path
     * @param array $order_data Order data
     * @return array Validation result
     */
    private function validate_book_content($file_path, $order_data) {
        $result = array(
            'success' => true,
            'errors' => array(),
            'warnings' => array(),
            'data' => array(),
            'requires_user_input' => false
        );

        // Get format-specific settings
        $book_format = isset($order_data['book_size']) ? $order_data['book_size'] : 'A5';
        $format_settings = $this->get_format_settings($book_format, 'book_content');

        // Analyze PDF
        $pdf_data = $this->analyze_pdf($file_path);

        // 1. Check PDF Size Detection
        if (isset($pdf_data['page_size']) && $format_settings) {
            $width = $pdf_data['page_size']['width'];
            $height = $pdf_data['page_size']['height'];
            
            $min_width = $format_settings->min_width;
            $max_width = $format_settings->max_width;
            $min_height = $format_settings->min_height;
            $max_height = $format_settings->max_height;
            
            if ($width < $min_width || $width > $max_width || $height < $min_height || $height > $max_height) {
                $result['warnings'][] = sprintf(
                    __('اندازه صفحات PDF (%.0f × %.0f پیکسل) با قطع سفارشی %s مطابقت ندارد. این فایل برای تطابق با قطع صحیح تنظیم مجدد خواهد شد و هزینه سرویس اضافی اعمال می‌شود.', 'tabesh'),
                    $width,
                    $height,
                    $book_format
                );
                $result['data']['size_mismatch'] = true;
                $result['data']['requires_confirmation'] = true;
                $result['data']['correction_fee'] = intval(Tabesh()->get_setting('file_correction_fee', 50000));
            }
            
            $result['data']['detected_size'] = array(
                'width' => $width,
                'height' => $height,
                'unit' => 'pixels'
            );
        }

        // 2. White Margin Detection
        if ($format_settings && isset($pdf_data['has_large_margins']) && $pdf_data['has_large_margins']) {
            $result['warnings'][] = __('حاشیه‌های سفید خارج از محدوده مجاز تشخیص داده شد. اصلاح حاشیه‌ها هزینه اضافی دارد.', 'tabesh');
            $result['data']['requires_confirmation'] = true;
            $result['data']['margin_issue'] = true;
        }

        // 3. Page Count Verification
        if (isset($pdf_data['page_count'])) {
            $expected_pages = isset($order_data['page_count_total']) ? intval($order_data['page_count_total']) : 0;
            
            if ($expected_pages > 0) {
                $page_diff = abs($pdf_data['page_count'] - $expected_pages);
                
                // If difference exceeds 2 pages → reject the file
                if ($page_diff > 2) {
                    $result['errors'][] = sprintf(
                        __('تعداد صفحات فایل (%d صفحه) با سفارش (%d صفحه) مطابقت ندارد. اختلاف بیش از 2 صفحه مجاز نیست. لطفاً فایل را با تعداد صفحات صحیح مجدداً آپلود کنید.', 'tabesh'),
                        $pdf_data['page_count'],
                        $expected_pages
                    );
                    $result['success'] = false;
                } elseif ($page_diff > 0) {
                    $result['warnings'][] = sprintf(
                        __('تفاوت %d صفحه‌ای با سفارش وجود دارد (فایل: %d، سفارش: %d)', 'tabesh'),
                        $page_diff,
                        $pdf_data['page_count'],
                        $expected_pages
                    );
                }
            }
            
            $result['data']['page_count'] = $pdf_data['page_count'];
            $result['data']['expected_pages'] = $expected_pages;
        }

        // 4. Image Page Detection
        if (isset($pdf_data['image_page_count']) && $pdf_data['image_page_count'] > 0) {
            $result['data']['image_page_count'] = $pdf_data['image_page_count'];
            $result['warnings'][] = sprintf(
                __('تعداد %d صفحه شامل تصویر تشخیص داده شد', 'tabesh'),
                $pdf_data['image_page_count']
            );
        }

        // 5. Color Page Validation
        $print_type = isset($order_data['print_type']) ? $order_data['print_type'] : '';
        $page_count_color = isset($order_data['page_count_color']) ? intval($order_data['page_count_color']) : 0;
        
        if (($print_type === 'رنگی' || $print_type === 'ترکیبی') && $page_count_color > 0) {
            $result['data']['requires_color_page_input'] = true;
            $result['data']['expected_color_pages'] = $page_count_color;
            $result['requires_user_input'] = true;
            $result['warnings'][] = sprintf(
                __('سفارش شما شامل %d صفحه رنگی است. لطفاً شماره صفحات رنگی را با استفاده از خط تیره وارد کنید (مثال: 14-25-36-75)', 'tabesh'),
                $page_count_color
            );
        }

        // 6. Check if file is standard or non-standard
        $is_standard = empty($result['errors']) && empty($result['warnings']);
        $result['data']['is_standard'] = $is_standard;
        
        if (!$is_standard) {
            $reasons = array_merge($result['errors'], $result['warnings']);
            $result['data']['non_standard_reasons'] = $reasons;
        }

        // 7. Final User Confirmation Required
        if ((isset($result['data']['requires_confirmation']) && $result['data']['requires_confirmation']) || $result['requires_user_input']) {
            $result['data']['requires_final_confirmation'] = true;
            $result['warnings'][] = __('قبل از ارسال نهایی، تأیید شما لازم است.', 'tabesh');
        }

        return $result;
    }

    /**
     * Validate book cover file
     *
     * @param string $file_path File path
     * @param array $order_data Order data
     * @return array Validation result
     */
    private function validate_book_cover($file_path, $order_data) {
        $result = array(
            'success' => true,
            'errors' => array(),
            'warnings' => array(),
            'data' => array()
        );

        // Get format-specific settings
        $book_format = isset($order_data['book_size']) ? $order_data['book_size'] : 'A5';
        $format_settings = $this->get_format_settings($book_format, 'book_cover');

        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Allowed formats: PSD, PDF, JPG, PNG
        $allowed_types = array('psd', 'pdf', 'jpg', 'jpeg', 'png');

        if (!in_array($file_ext, $allowed_types)) {
            $result['errors'][] = sprintf(
                __('فرمت فایل جلد باید یکی از موارد زیر باشد: PSD، PDF، JPG، PNG. فرمت دریافتی: %s', 'tabesh'),
                strtoupper($file_ext)
            );
            $result['success'] = false;
            return $result;
        }

        // Check image properties for raster images
        if (in_array($file_ext, array('jpg', 'jpeg', 'png', 'psd'))) {
            $image_data = $this->analyze_image($file_path);
            $result['data'] = array_merge($result['data'], $image_data);

            // Use format-specific DPI requirement if available
            $min_dpi = $format_settings && $format_settings->min_resolution ? 
                       $format_settings->min_resolution : 
                       intval(Tabesh()->get_setting('file_min_dpi', 300));

            // Check Resolution (minimum 300 DPI)
            if (isset($image_data['dpi']) && $image_data['dpi'] < $min_dpi) {
                $result['warnings'][] = sprintf(
                    __('رزولوشن فایل پایین است (DPI فعلی: %d). حداقل DPI مورد نیاز برای قطع %s: %d DPI. فایل شما استانداردهای پیکسل‌بندی را ندارد و نیاز به اصلاح دارد (مشمول هزینه سرویس اضافی).', 'tabesh'),
                    $image_data['dpi'],
                    $book_format,
                    $min_dpi
                );
                
                $correction_fee = intval(Tabesh()->get_setting('file_correction_fee', 50000));
                $result['data']['correction_fee'] = $correction_fee;
                $result['data']['requires_confirmation'] = true;
                $result['data']['dpi_issue'] = true;
            } else {
                $result['data']['dpi_ok'] = true;
            }

            // Check Color Mode (must be CMYK)
            $required_color_mode = $format_settings && $format_settings->required_color_mode ? 
                                   $format_settings->required_color_mode : 'CMYK';
            
            if (isset($image_data['color_mode'])) {
                // Check if color mode matches requirements
                if (strpos($required_color_mode, $image_data['color_mode']) === false) {
                    $result['warnings'][] = sprintf(
                        __('فایل شما در حالت رنگی %s است. برای چاپ حرفه‌ای قطع %s، حالت رنگی CMYK مناسب‌تر است. فایل شما حالت رنگی استاندارد را ندارد و نیاز به اصلاح دارد (مشمول هزینه سرویس اضافی).', 'tabesh'),
                        $image_data['color_mode'],
                        $book_format
                    );
                    $result['data']['requires_confirmation'] = true;
                    $result['data']['color_mode_issue'] = true;
                } else {
                    $result['data']['color_mode_ok'] = true;
                }
            }
        }

        // Check if file is standard or non-standard
        $is_standard = empty($result['errors']) && empty($result['warnings']);
        $result['data']['is_standard'] = $is_standard;
        
        if (!$is_standard) {
            $reasons = array_merge($result['errors'], $result['warnings']);
            $result['data']['non_standard_reasons'] = $reasons;
        }

        // User confirmation required if there are warnings
        if (isset($result['data']['requires_confirmation']) && $result['data']['requires_confirmation']) {
            $result['warnings'][] = __('تأیید شما قبل از ارسال نهایی لازم است.', 'tabesh');
        }

        return $result;
    }

    /**
     * Validate document file
     *
     * @param string $file_path File path
     * @param array $order_data Order data
     * @return array Validation result
     */
    private function validate_document($file_path, $order_data) {
        $result = array(
            'success' => true,
            'errors' => array(),
            'warnings' => array(),
            'data' => array()
        );

        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $allowed_types = array('pdf', 'jpg', 'jpeg', 'png');

        if (!in_array($file_ext, $allowed_types)) {
            $result['errors'][] = sprintf(
                __('فرمت فایل مدرک باید یکی از موارد زیر باشد: %s', 'tabesh'),
                implode(', ', $allowed_types)
            );
            $result['success'] = false;
            return $result;
        }

        // Check file size
        $file_size = filesize($file_path);
        $max_size = intval(Tabesh()->get_setting('file_max_size_document', 10485760));

        if ($file_size > $max_size) {
            $result['errors'][] = sprintf(
                __('حجم فایل (%s) بیش از حد مجاز (%s) است', 'tabesh'),
                size_format($file_size),
                size_format($max_size)
            );
            $result['success'] = false;
        }

        // Check if document requires additional information
        if (isset($order_data['document_type'])) {
            $result['data']['requires_additional_info'] = true;
            $result['data']['document_type'] = $order_data['document_type'];
        }

        return $result;
    }

    /**
     * Validate generic file
     *
     * @param string $file_path File path
     * @return array Validation result
     */
    private function validate_generic($file_path) {
        return array(
            'success' => true,
            'errors' => array(),
            'warnings' => array(),
            'data' => array(
                'file_size' => filesize($file_path),
                'mime_type' => mime_content_type($file_path)
            )
        );
    }

    /**
     * Analyze PDF file
     *
     * @param string $file_path PDF file path
     * @return array PDF data
     */
    private function analyze_pdf($file_path) {
        $data = array();

        // Validate file path is within upload directory for security
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/tabesh-files/';
        $real_path = realpath($file_path);
        $real_base = realpath($base_dir);
        
        // Security check: ensure file is within expected directory
        if ($real_path === false || $real_base === false || strpos($real_path, $real_base) !== 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: Invalid file path for PDF analysis: ' . $file_path);
            }
            return $data;
        }

        // Try to get PDF info using various methods
        // Method 1: Use pdfinfo if available (requires poppler-utils)
        // Only run if exec is enabled and pdfinfo command exists
        if (function_exists('exec') && $this->command_exists('pdfinfo')) {
            $output = array();
            $escaped_path = escapeshellarg($real_path);
            @exec('pdfinfo ' . $escaped_path . ' 2>&1', $output, $return_var);
            
            // Only process output if command succeeded
            if ($return_var === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (strpos($line, 'Pages:') !== false) {
                        $data['page_count'] = intval(trim(str_replace('Pages:', '', $line)));
                    }
                    if (strpos($line, 'Page size:') !== false) {
                        preg_match('/(\d+\.?\d*)\s*x\s*(\d+\.?\d*)/', $line, $matches);
                        if (isset($matches[1]) && isset($matches[2])) {
                            $data['page_size'] = array(
                                'width' => floatval($matches[1]),
                                'height' => floatval($matches[2])
                            );
                        }
                    }
                }
            }
        }

        // Method 2: Simple page count extraction from PDF structure
        if (!isset($data['page_count']) && filesize($real_path) < 10485760) { // Only for files < 10MB
            $content = @file_get_contents($real_path);
            if ($content) {
                preg_match("/\/Count\s+(\d+)/", $content, $matches);
                if (isset($matches[1])) {
                    $data['page_count'] = intval($matches[1]);
                }
            }
        }

        // Estimate if PDF has images (simple heuristic)
        // Only run if exec is enabled and pdfimages command exists
        if (function_exists('exec') && $this->command_exists('pdfimages')) {
            $output = array();
            $escaped_path = escapeshellarg($real_path);
            @exec('pdfimages -list ' . $escaped_path . ' 2>&1', $output, $return_var);
            
            // Only count lines if command succeeded
            if ($return_var === 0 && !empty($output)) {
                $line_count = count($output);
                if ($line_count > 2) { // More than header lines
                    $data['image_page_count'] = $line_count - 2;
                }
            }
        }

        // Check for large margins (simplified check)
        $data['has_large_margins'] = false; // Would require actual PDF parsing

        return $data;
    }

    /**
     * Check if a command exists on the system
     *
     * @param string $command Command name
     * @return bool True if command exists
     */
    private function command_exists($command) {
        // Whitelist of allowed commands for security
        $allowed_commands = array('pdfinfo', 'pdfimages', 'identify', 'convert');
        
        if (!in_array($command, $allowed_commands, true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tabesh: Attempted to check for non-whitelisted command: ' . $command);
            }
            return false;
        }
        
        // Use PHP's built-in function_exists check for security
        if (!function_exists('exec')) {
            return false;
        }
        
        // Sanitize command name - only allow alphanumeric and dash
        $command = preg_replace('/[^a-zA-Z0-9\-]/', '', $command);
        
        $output = array();
        $return_var = 0;
        @exec('which ' . escapeshellarg($command) . ' 2>&1', $output, $return_var);
        return $return_var === 0;
    }

    /**
     * Analyze image file
     *
     * @param string $file_path Image file path
     * @return array Image data
     */
    private function analyze_image($file_path) {
        $data = array();

        // Get image info
        $image_info = @getimagesize($file_path);
        
        if ($image_info) {
            $data['width'] = $image_info[0];
            $data['height'] = $image_info[1];
            $data['mime_type'] = $image_info['mime'];

            // Calculate DPI if available
            if (isset($image_info['channels'])) {
                $data['channels'] = $image_info['channels'];
            }

            // Try to get DPI from EXIF data
            $dpi_detected = false;
            if (function_exists('exif_read_data') && in_array(strtolower(pathinfo($file_path, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'tiff', 'tif'))) {
                $exif = @exif_read_data($file_path);
                if ($exif) {
                    // Try XResolution
                    if (isset($exif['XResolution'])) {
                        // XResolution is often in format "72/1" or just "72"
                        if (is_string($exif['XResolution']) && strpos($exif['XResolution'], '/') !== false) {
                            list($num, $denom) = explode('/', $exif['XResolution']);
                            $denom = intval($denom);
                            // Prevent division by zero
                            if ($denom > 0) {
                                $data['dpi'] = intval($num / $denom);
                            } else {
                                $data['dpi'] = intval($num); // Use numerator as fallback
                            }
                        } else {
                            $data['dpi'] = intval($exif['XResolution']);
                        }
                        $dpi_detected = true;
                    }
                    // Try YResolution if XResolution not found
                    elseif (isset($exif['YResolution'])) {
                        if (is_string($exif['YResolution']) && strpos($exif['YResolution'], '/') !== false) {
                            list($num, $denom) = explode('/', $exif['YResolution']);
                            $denom = intval($denom);
                            // Prevent division by zero
                            if ($denom > 0) {
                                $data['dpi'] = intval($num / $denom);
                            } else {
                                $data['dpi'] = intval($num); // Use numerator as fallback
                            }
                        } else {
                            $data['dpi'] = intval($exif['YResolution']);
                        }
                        $dpi_detected = true;
                    }
                }
            }

            // Try ImageMagick if available and DPI not detected yet
            if (!$dpi_detected && function_exists('exec') && $this->command_exists('identify')) {
                $escaped_path = escapeshellarg($file_path);
                $output = array();
                @exec('identify -format "%x %y" ' . $escaped_path . ' 2>&1', $output, $return_var);
                
                if ($return_var === 0 && !empty($output[0])) {
                    // Parse output like "72 PixelsPerInch 72 PixelsPerInch" or "300x300"
                    $resolution_info = $output[0];
                    if (preg_match('/(\d+)/', $resolution_info, $matches)) {
                        $data['dpi'] = intval($matches[1]);
                        $dpi_detected = true;
                    }
                }
            }

            // If still no DPI, estimate based on pixel dimensions
            // This is a rough estimate for standard print sizes
            if (!$dpi_detected) {
                $width_px = $data['width'];
                $height_px = $data['height'];
                
                // Estimate assuming common print sizes
                // For A4 size cover: ~8.3" x 11.7"
                $estimated_dpi_width = $width_px / 8.3;
                $estimated_dpi_height = $height_px / 11.7;
                
                // Take average and round
                $data['dpi'] = intval(($estimated_dpi_width + $estimated_dpi_height) / 2);
                $data['dpi_estimated'] = true; // Flag that this is estimated
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Tabesh: Estimated DPI for %s: %d (from %dx%d pixels)', 
                        basename($file_path), $data['dpi'], $width_px, $height_px));
                }
            }

            // Try to detect color mode
            $data['color_mode'] = 'RGB'; // Default to RGB
            
            $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            
            // For PSD files, try to detect CMYK using ImageMagick
            if ($file_ext === 'psd' && function_exists('exec') && $this->command_exists('identify')) {
                $escaped_path = escapeshellarg($file_path);
                $output = array();
                @exec('identify -format "%[colorspace]" ' . $escaped_path . ' 2>&1', $output, $return_var);
                
                if ($return_var === 0 && !empty($output[0])) {
                    $colorspace = trim($output[0]);
                    if (stripos($colorspace, 'CMYK') !== false) {
                        $data['color_mode'] = 'CMYK';
                    } elseif (stripos($colorspace, 'Gray') !== false) {
                        $data['color_mode'] = 'Grayscale';
                    }
                }
            }
            // For other image types, check channels
            elseif (isset($data['channels'])) {
                if ($data['channels'] == 1) {
                    $data['color_mode'] = 'Grayscale';
                } elseif ($data['channels'] == 3) {
                    $data['color_mode'] = 'RGB';
                } elseif ($data['channels'] == 4) {
                    // Could be RGBA or CMYK - default to RGB
                    $data['color_mode'] = 'RGB';
                }
            }
        }

        return $data;
    }

    /**
     * Check if PDF page size matches book size
     *
     * @param array $page_size PDF page size (width, height in points)
     * @param string $book_size Book size from order
     * @return bool True if sizes match
     */
    private function check_page_size_match($page_size, $book_size) {
        // Define standard book sizes in points (1 inch = 72 points)
        $standard_sizes = array(
            'A4' => array('width' => 595, 'height' => 842),
            'A5' => array('width' => 420, 'height' => 595),
            'B5' => array('width' => 499, 'height' => 709),
            'رقعی' => array('width' => 432, 'height' => 612), // Approx 6" x 8.5"
            'وزیری' => array('width' => 504, 'height' => 720), // Approx 7" x 10"
            'خشتی' => array('width' => 540, 'height' => 756), // Approx 7.5" x 10.5"
        );

        if (!isset($standard_sizes[$book_size])) {
            return true; // Unknown size, assume OK
        }

        $expected = $standard_sizes[$book_size];
        $tolerance = 20; // 20 points tolerance

        // Check both orientations
        $match_portrait = (
            abs($page_size['width'] - $expected['width']) < $tolerance &&
            abs($page_size['height'] - $expected['height']) < $tolerance
        );

        $match_landscape = (
            abs($page_size['width'] - $expected['height']) < $tolerance &&
            abs($page_size['height'] - $expected['width']) < $tolerance
        );

        return $match_portrait || $match_landscape;
    }

    /**
     * Get format-specific settings from database
     *
     * @param string $book_format Book format (A5, A4, B5, etc.)
     * @param string $file_category File category (book_content, book_cover)
     * @return object|null Format settings or null if not found
     */
    private function get_format_settings($book_format, $file_category) {
        global $wpdb;
        $table = $wpdb->prefix . 'tabesh_book_format_settings';
        
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE book_format = %s AND file_category = %s",
            $book_format,
            $file_category
        ));
        
        return $settings;
    }
}
