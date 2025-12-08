<?php
/**
 * SMS Handler Class for MelliPayamak Template-based SMS
 *
 * @package Tabesh
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
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
	 * MelliPayamak SOAP API endpoint
	 *
	 * @var string
	 */
	const SOAP_WSDL_URL = 'https://api.payamak-panel.com/post/Send.asmx?wsdl';

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
		add_action( 'tabesh_order_status_changed', array( $this, 'on_status_changed' ), 15, 2 );
	}

	/**
	 * Check if SMS system is enabled
	 *
	 * @return bool
	 */
	public function is_sms_enabled() {
		return (bool) Tabesh()->get_setting( 'sms_enabled', '0' );
	}

	/**
	 * Check if SMS is enabled for a specific status
	 *
	 * @param string $status Order status
	 * @return bool
	 */
	public function is_status_enabled( $status ) {
		if ( ! $this->is_sms_enabled() ) {
			return false;
		}

		$status = sanitize_text_field( $status );
		return (bool) Tabesh()->get_setting( 'sms_status_' . $status . '_enabled', '0' );
	}

	/**
	 * Get pattern code for a status
	 *
	 * @param string $status Order status
	 * @return string Pattern code or empty string
	 */
	public function get_pattern_code( $status ) {
		$status = sanitize_text_field( $status );
		return Tabesh()->get_setting( 'sms_status_' . $status . '_pattern', '' );
	}

	/**
	 * Validate Iranian mobile number
	 *
	 * Valid format: 09XXXXXXXXX (11 digits starting with 09)
	 *
	 * @param string $phone Phone number
	 * @return string|false Sanitized phone number or false if invalid
	 */
	public function validate_phone( $phone ) {
		// Remove any non-digit characters
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		// Check for valid Iranian mobile format (11 digits starting with 09)
		if ( preg_match( '/^09[0-9]{9}$/', $phone ) ) {
			return $phone;
		}

		// Also accept format without leading 0 (10 digits starting with 9)
		if ( preg_match( '/^9[0-9]{9}$/', $phone ) ) {
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
	public function get_order_variables( $order ) {
		if ( ! $order ) {
			return array();
		}

		// Get user information
		$user          = get_userdata( $order->user_id );
		$customer_name = $user ? $user->display_name : __( 'مشتری', 'tabesh' );

		// Get status label in Persian
		$status_label = isset( self::$status_labels[ $order->status ] )
			? self::$status_labels[ $order->status ]
			: $order->status;

		// Format date in Persian (Jalali) if possible, otherwise use Gregorian
		$date = date_i18n( 'Y/m/d', strtotime( $order->created_at ) );

		return array(
			'order_number'  => $order->order_number,
			'customer_name' => $customer_name,
			'status'        => $status_label,
			'date'          => $date,
			'book_title'    => $order->book_title ?? '',
			'quantity'      => $order->quantity ?? 0,
			'total_price'   => number_format( $order->total_price ?? 0 ),
		);
	}

	/**
	 * Test connection to MelliPayamak panel
	 * Uses GetCredit SOAP method to verify username/password and get remaining credit
	 *
	 * @return array|WP_Error Array with success and data on success, WP_Error on failure
	 */
	public function test_connection() {
		// Check if SOAP extension is available
		if ( ! extension_loaded( 'soap' ) ) {
			return new WP_Error( 'soap_extension_missing', __( 'افزونه SOAP در سرور فعال نیست. لطفاً با مدیر سرور تماس بگیرید.', 'tabesh' ) );
		}

		// Get API credentials
		$username = Tabesh()->get_setting( 'sms_username', '' );
		$password = Tabesh()->get_setting( 'sms_password', '' );

		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'config_missing', __( 'نام کاربری یا رمز عبور وارد نشده است', 'tabesh' ) );
		}

		try {
			// Initialize SOAP client with shorter timeout for better UX
			$soap_options = array(
				'encoding'           => 'UTF-8',
				'trace'              => true,
				'exceptions'         => true,
				'connection_timeout' => 15, // Reduced from 30 to 15 seconds for better responsiveness
				'cache_wsdl'         => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? WSDL_CACHE_NONE : WSDL_CACHE_BOTH,
			);

			$client = new SoapClient( self::SOAP_WSDL_URL, $soap_options );

			// Call GetCredit method to test connection and get remaining credit
			$response = $client->GetCredit(
				array(
					'username' => $username,
					'password' => $password,
				)
			);

			// Check response
			if ( isset( $response->GetCreditResult ) ) {
				$credit = floatval( $response->GetCreditResult );

				if ( $credit >= 0 ) {
					// Success - connection is valid
					return array(
						'success' => true,
						'credit'  => $credit,
						'message' => sprintf(
							__( 'اتصال برقرار شد. اعتبار باقیمانده: %s ریال', 'tabesh' ),
							number_format( $credit )
						),
					);
				} else {
					// Negative value indicates error
					$error_message = $this->get_melipayamak_error_message( intval( $credit ) );
					return new WP_Error( 'connection_failed', $error_message );
				}
			} else {
				return new WP_Error( 'unexpected_response', __( 'پاسخ نامعتبر از سرور', 'tabesh' ) );
			}
		} catch ( SoapFault $e ) {
			$error_message = sprintf(
				__( 'خطای اتصال: %s', 'tabesh' ),
				$e->getMessage()
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS Connection Test Error: ' . $e->getMessage() );
			}

			return new WP_Error( 'soap_error', $error_message );
		} catch ( Exception $e ) {
			$error_message = sprintf(
				__( 'خطا: %s', 'tabesh' ),
				$e->getMessage()
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS Connection Test Exception: ' . $e->getMessage() );
			}

			return new WP_Error( 'general_error', $error_message );
		}
	}

	/**
	 * Send template-based SMS via MelliPayamak SOAP API
	 *
	 * Uses SendByBaseNumber2 method for template-based (pattern) SMS sending
	 *
	 * @param string $phone Recipient phone number
	 * @param string $pattern_code Pattern code (bodyId) from MelliPayamak
	 * @param array  $parameters Template parameters (will be sent in order)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function send_template_sms( $phone, $pattern_code, $parameters = array() ) {
		// Validate phone number
		$phone = $this->validate_phone( $phone );
		if ( ! $phone ) {
			$this->log_error( 'invalid_phone', __( 'شماره موبایل نامعتبر است', 'tabesh' ) );
			return new WP_Error( 'invalid_phone', __( 'شماره موبایل نامعتبر است', 'tabesh' ) );
		}

		// Get API credentials - don't log sensitive data
		$username = Tabesh()->get_setting( 'sms_username', '' );
		$password = Tabesh()->get_setting( 'sms_password', '' );

		if ( empty( $username ) || empty( $password ) ) {
			$this->log_error( 'config_missing', __( 'تنظیمات SMS کامل نیست', 'tabesh' ) );
			return new WP_Error( 'sms_config_missing', __( 'تنظیمات SMS کامل نیست', 'tabesh' ) );
		}

		if ( empty( $pattern_code ) ) {
			$this->log_error( 'pattern_missing', __( 'کد الگوی پیامک تعریف نشده', 'tabesh' ) );
			return new WP_Error( 'pattern_missing', __( 'کد الگوی پیامک تعریف نشده', 'tabesh' ) );
		}

		// Validate pattern code is numeric before converting
		if ( ! is_numeric( $pattern_code ) ) {
			$this->log_error( 'invalid_pattern', __( 'کد الگوی پیامک باید عددی باشد', 'tabesh' ) );
			return new WP_Error( 'invalid_pattern', __( 'کد الگوی پیامک باید عددی باشد', 'tabesh' ) );
		}

		$bodyId = intval( $pattern_code );
		if ( $bodyId <= 0 ) {
			$this->log_error( 'invalid_pattern', __( 'کد الگوی پیامک باید عدد مثبت باشد', 'tabesh' ) );
			return new WP_Error( 'invalid_pattern', __( 'کد الگوی پیامک باید عدد مثبت باشد', 'tabesh' ) );
		}

		try {
			// Initialize SOAP client with options
			$soap_options = array(
				'encoding'           => 'UTF-8',
				'trace'              => true,
				'exceptions'         => true,
				'connection_timeout' => 30,
				// WSDL caching: Use WSDL_CACHE_BOTH in production for better performance
				// Use WSDL_CACHE_NONE only in debug mode (WP_DEBUG=true) for troubleshooting
				'cache_wsdl'         => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? WSDL_CACHE_NONE : WSDL_CACHE_BOTH,
			);

			// Create SOAP client
			$client = new SoapClient( self::SOAP_WSDL_URL, $soap_options );

			// Prepare parameters array - convert associative array to indexed array
			$text_array = ! empty( $parameters ) && is_array( $parameters ) ? array_values( $parameters ) : array();

			// Prepare SOAP parameters
			$soap_params = array(
				'username' => $username,
				'password' => $password,
				'text'     => $text_array,  // Array of parameter values in order
				'to'       => $phone,
				'bodyId'   => $bodyId,
			);

			// Log request in debug mode (without sensitive data)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'Tabesh SMS: Sending via SOAP to %s with pattern %d, params count: %d',
						substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 ),
						$bodyId,
						count( $text_array )
					)
				);
			}

			// Select correct SOAP method based on text parameter type.
			// According to MelliPayamak API documentation:
			// - SendByBaseNumber: Use when text is an array.
			// - SendByBaseNumber2: Use when text is a string.
			if ( is_array( $text_array ) && count( $text_array ) > 0 ) {
				// Text is an array - use SendByBaseNumber.
				$response   = $client->SendByBaseNumber( $soap_params );
				$result_key = 'SendByBaseNumberResult';
			} else {
				// Text is empty or string - use SendByBaseNumber2.
				$response   = $client->SendByBaseNumber2( $soap_params );
				$result_key = 'SendByBaseNumber2Result';
			}

			// Check response
			// MelliPayamak returns numeric values:
			// Positive values = success (message ID)
			// Negative values = error codes
			if ( isset( $response->$result_key ) ) {
				$result_value = intval( $response->$result_key );

				if ( $result_value > 0 ) {
					// Success - result is message ID
					$this->log_success( $phone, $pattern_code, $result_value );
					return true;
				} else {
					// Error - result is error code
					$error_message = $this->get_melipayamak_error_message( $result_value );
					$this->log_error(
						'api_error',
						$error_message,
						array(
							'phone'      => substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 ),
							'pattern'    => $pattern_code,
							'error_code' => $result_value,
						)
					);
					return new WP_Error( 'sms_send_failed', $error_message );
				}
			} else {
				// Unexpected response format
				$this->log_error( 'unexpected_response', __( 'پاسخ نامعتبر از سرور SMS', 'tabesh' ) );
				return new WP_Error( 'unexpected_response', __( 'پاسخ نامعتبر از سرور SMS', 'tabesh' ) );
			}
		} catch ( SoapFault $e ) {
			// Handle SOAP errors
			$error_message = sprintf(
				__( 'خطای SOAP: %s', 'tabesh' ),
				$e->getMessage()
			);

			$this->log_error(
				'soap_error',
				$error_message,
				array(
					'phone'   => substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 ),
					'pattern' => $pattern_code,
				)
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS SOAP Error: ' . $e->getMessage() );
				error_log( 'Tabesh SMS SOAP Trace: ' . $e->getTraceAsString() );
			}

			return new WP_Error( 'soap_error', $error_message );
		} catch ( Exception $e ) {
			// Handle general exceptions
			$error_message = sprintf(
				__( 'خطای ارسال پیامک: %s', 'tabesh' ),
				$e->getMessage()
			);

			$this->log_error( 'general_error', $error_message );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS Error: ' . $e->getMessage() );
			}

			return new WP_Error( 'general_error', $error_message );
		}
	}

	/**
	 * Send SMS notification for order status change
	 *
	 * @param int    $order_id  Order ID
	 * @param string $new_status New order status
	 * @return bool|WP_Error True on success, WP_Error on failure, false if disabled
	 */
	public function send_order_status_sms( $order_id, $new_status ) {
		// Check firewall - don't send notifications for WAR orders
		$firewall = new Tabesh_Doomsday_Firewall();
		if ( ! $firewall->should_send_notification( $order_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Tabesh SMS: Notification blocked by firewall for order $order_id" );
			}
			return false;
		}

		// Check if SMS is enabled for this status
		if ( ! $this->is_status_enabled( $new_status ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Tabesh SMS: SMS disabled for status "%s"', $new_status ) );
			}
			return false;
		}

		// Get pattern code for this status
		$pattern_code = $this->get_pattern_code( $new_status );
		if ( empty( $pattern_code ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Tabesh SMS: No pattern code for status "%s"', $new_status ) );
			}
			return false;
		}

		// Get order details
		$order = Tabesh()->order->get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', __( 'سفارش یافت نشد', 'tabesh' ) );
		}

		// Get customer phone number
		// First, try to get it from user_login (main phone number for Iranian users)
		$user  = get_userdata( $order->user_id );
		$phone = $user ? $user->user_login : '';

		// Validate phone from user_login
		if ( empty( $phone ) || ! $this->validate_phone( $phone ) ) {
			// Fallback to billing_phone from user meta if user_login is not a valid phone
			$phone = get_user_meta( $order->user_id, 'billing_phone', true );
		}

		// Final validation
		if ( empty( $phone ) || ! $this->validate_phone( $phone ) ) {
			$error_message = __( 'شماره موبایل مشتری یافت نشد یا نامعتبر است', 'tabesh' );
			$this->log_error(
				'no_phone',
				$error_message,
				array(
					'order_id' => $order_id,
					'user_id'  => $order->user_id,
				)
			);
			return new WP_Error( 'no_phone', $error_message );
		}

		// Get order variables for template
		$variables = $this->get_order_variables( $order );

		// Send SMS
		$result = $this->send_template_sms( $phone, $pattern_code, $variables );

		// Log the action
		if ( ! is_wp_error( $result ) && $result === true ) {
			$this->log_action(
				$order_id,
				'sms_sent',
				sprintf(
					__( 'پیامک وضعیت "%1$s" به شماره %2$s ارسال شد', 'tabesh' ),
					self::$status_labels[ $new_status ] ?? $new_status,
					substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 )
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
	public function on_status_changed( $order_id, $status ) {
		// Send SMS notification (will check if enabled internally)
		$this->send_order_status_sms( $order_id, $status );
	}

	/**
	 * Send test SMS
	 *
	 * @param string $phone      Test phone number
	 * @param string $pattern_code Pattern code to test
	 * @return bool|WP_Error
	 */
	public function send_test_sms( $phone, $pattern_code ) {
		// Create test parameters
		$test_params = array(
			'TB-TEST-00001',     // order_number
			__( 'مشتری آزمایشی', 'tabesh' ),  // customer_name
			__( 'تست', 'tabesh' ),  // status
			date_i18n( 'Y/m/d' ),  // date
		);

		return $this->send_template_sms( $phone, $pattern_code, $test_params );
	}

	/**
	 * Log successful SMS send
	 *
	 * @param string $phone      Phone number (partially masked)
	 * @param string $pattern    Pattern code
	 * @param int    $message_id Message ID from API
	 */
	private function log_success( $phone, $pattern, $message_id ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh SMS: Successfully sent to %s****%s, pattern: %s, message_id: %d',
					substr( $phone, 0, 4 ),
					substr( $phone, -2 ),
					$pattern,
					$message_id
				)
			);
		}
	}

	/**
	 * Log SMS error to database and error_log
	 *
	 * @param string $code    Error code
	 * @param string $message Error message
	 * @param array  $context Additional context (will not include sensitive data)
	 */
	private function log_error( $code, $message, $context = array() ) {
		// Log to error_log in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Tabesh SMS Error [%s]: %s - Context: %s',
					$code,
					$message,
					wp_json_encode( $context )
				)
			);
		}

		// Log to database
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		// Handle order_id - use NULL if not provided
		// Note: WordPress wpdb handles NULL values correctly even with %d format
		// It will insert NULL into the database when value is null
		$order_id = isset( $context['order_id'] ) && $context['order_id'] > 0
			? intval( $context['order_id'] )
			: null;

		$wpdb->insert(
			$table,
			array(
				'order_id'    => $order_id,
				'user_id'     => get_current_user_id(),
				'action'      => 'sms_error_' . sanitize_key( $code ),
				'description' => sanitize_text_field( $message ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Log SMS action to database
	 *
	 * @param int    $order_id    Order ID
	 * @param string $action      Action type
	 * @param string $description Description
	 */
	private function log_action( $order_id, $action, $description ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tabesh_logs';

		$wpdb->insert(
			$table,
			array(
				'order_id'    => intval( $order_id ),
				'user_id'     => get_current_user_id(),
				'action'      => sanitize_text_field( $action ),
				'description' => sanitize_text_field( $description ),
			),
			array( '%d', '%d', '%s', '%s' )
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

	/**
	 * Get Persian error message for MelliPayamak error codes
	 *
	 * @param int $error_code Error code from API
	 * @return string Persian error message
	 */
	private function get_melipayamak_error_message( $error_code ) {
		$error_messages = array(
			-1  => __( 'پارامترها ناقص است', 'tabesh' ),
			-2  => __( 'نام کاربری یا رمز عبور اشتباه است', 'tabesh' ),
			-3  => __( 'امکان ارسال روزانه شما به پایان رسیده', 'tabesh' ),
			-4  => __( 'پیامک با موفقیت ارسال شد اما به دلیل عدم تکمیل اطلاعات پنل کاربری، تمام پیام‌ها ارسال نشده است', 'tabesh' ),
			-5  => __( 'متغیرها با توجه به متن پیشفرض بدرستی ارسال نشده است', 'tabesh' ),
			-6  => __( 'اعتبار کافی نیست', 'tabesh' ),
			-7  => __( 'متن پیام بیش از حد طولانی است', 'tabesh' ),
			-8  => __( 'شماره فرستنده معتبر نیست', 'tabesh' ),
			-9  => __( 'شماره گیرنده معتبر نیست', 'tabesh' ),
			-10 => __( 'خطا در ارسال پیامک به سامانه ملی', 'tabesh' ),
			-11 => __( 'کد الگو پیدا نشد یا متعلق به شما نیست', 'tabesh' ),
			-12 => __( 'پارامترهای ارسالی با الگوی تعریف شده مطابقت ندارد', 'tabesh' ),
			-13 => __( 'IP شما در لیست سفید قرار ندارد', 'tabesh' ),
		);

		return isset( $error_messages[ $error_code ] )
			? $error_messages[ $error_code ]
			: sprintf( __( 'خطای ناشناخته با کد %d', 'tabesh' ), $error_code );
	}

	/**
	 * Get available variables for each pattern type
	 *
	 * @param string $pattern_type Pattern type (admin_user_registration, admin_order_created, status_change)
	 * @return array Available variables with their labels and descriptions
	 */
	public static function get_available_variables( $pattern_type ) {
		$variables = array(
			'admin_user_registration' => array(
				'user_name'  => array(
					'label'       => __( 'نام کامل', 'tabesh' ),
					'placeholder' => 'user_name',
					'description' => __( 'نام و نام خانوادگی کاربر', 'tabesh' ),
				),
				'first_name' => array(
					'label'       => __( 'نام', 'tabesh' ),
					'placeholder' => 'first_name',
					'description' => __( 'نام کاربر', 'tabesh' ),
				),
				'last_name'  => array(
					'label'       => __( 'نام خانوادگی', 'tabesh' ),
					'placeholder' => 'last_name',
					'description' => __( 'نام خانوادگی کاربر', 'tabesh' ),
				),
				'mobile'     => array(
					'label'       => __( 'شماره موبایل', 'tabesh' ),
					'placeholder' => 'mobile',
					'description' => __( 'شماره موبایل کاربر', 'tabesh' ),
				),
				'date'       => array(
					'label'       => __( 'تاریخ ثبت‌نام', 'tabesh' ),
					'placeholder' => 'date',
					'description' => __( 'تاریخ ثبت‌نام کاربر', 'tabesh' ),
				),
			),
			'admin_order_created'     => array(
				'order_number'  => array(
					'label'       => __( 'شماره سفارش', 'tabesh' ),
					'placeholder' => 'order_number',
					'description' => __( 'شماره سفارش (مثال: TB-00001)', 'tabesh' ),
				),
				'customer_name' => array(
					'label'       => __( 'نام مشتری', 'tabesh' ),
					'placeholder' => 'customer_name',
					'description' => __( 'نام مشتری', 'tabesh' ),
				),
				'book_title'    => array(
					'label'       => __( 'عنوان کتاب', 'tabesh' ),
					'placeholder' => 'book_title',
					'description' => __( 'عنوان کتاب', 'tabesh' ),
				),
				'quantity'      => array(
					'label'       => __( 'تیراژ', 'tabesh' ),
					'placeholder' => 'quantity',
					'description' => __( 'تعداد نسخه', 'tabesh' ),
				),
				'total_price'   => array(
					'label'       => __( 'قیمت کل', 'tabesh' ),
					'placeholder' => 'total_price',
					'description' => __( 'قیمت کل با فرمت (عددی)', 'tabesh' ),
				),
				'date'          => array(
					'label'       => __( 'تاریخ سفارش', 'tabesh' ),
					'placeholder' => 'date',
					'description' => __( 'تاریخ ثبت سفارش', 'tabesh' ),
				),
			),
			'status_change'           => array(
				'order_number'  => array(
					'label'       => __( 'شماره سفارش', 'tabesh' ),
					'placeholder' => 'order_number',
					'description' => __( 'شماره سفارش (مثال: TB-00001)', 'tabesh' ),
				),
				'customer_name' => array(
					'label'       => __( 'نام مشتری', 'tabesh' ),
					'placeholder' => 'customer_name',
					'description' => __( 'نام مشتری', 'tabesh' ),
				),
				'status'        => array(
					'label'       => __( 'وضعیت سفارش', 'tabesh' ),
					'placeholder' => 'status',
					'description' => __( 'وضعیت جدید سفارش', 'tabesh' ),
				),
				'book_title'    => array(
					'label'       => __( 'عنوان کتاب', 'tabesh' ),
					'placeholder' => 'book_title',
					'description' => __( 'عنوان کتاب', 'tabesh' ),
				),
				'quantity'      => array(
					'label'       => __( 'تیراژ', 'tabesh' ),
					'placeholder' => 'quantity',
					'description' => __( 'تعداد نسخه', 'tabesh' ),
				),
				'total_price'   => array(
					'label'       => __( 'قیمت کل', 'tabesh' ),
					'placeholder' => 'total_price',
					'description' => __( 'قیمت کل با فرمت (عددی)', 'tabesh' ),
				),
				'date'          => array(
					'label'       => __( 'تاریخ', 'tabesh' ),
					'placeholder' => 'date',
					'description' => __( 'تاریخ', 'tabesh' ),
				),
			),
		);

		return isset( $variables[ $pattern_type ] ) ? $variables[ $pattern_type ] : array();
	}

	/**
	 * Get pattern variable configuration from settings
	 *
	 * @param string $pattern_type Pattern type
	 * @return array Variable configuration
	 */
	public function get_pattern_variable_config( $pattern_type ) {
		$config_key = 'sms_pattern_vars_' . $pattern_type;
		$config     = Tabesh()->get_setting( $config_key, array() );

		// Ensure it's an array
		if ( ! is_array( $config ) ) {
			// Try to decode if it's JSON string
			$decoded = json_decode( $config, true );
			$config  = is_array( $decoded ) ? $decoded : array();
		}

		return $config;
	}

	/**
	 * Build variables array based on configuration
	 *
	 * @param array  $all_variables All available variables
	 * @param string $pattern_type  Pattern type
	 * @return array Ordered array of variable values
	 */
	public function build_variables_array( $all_variables, $pattern_type ) {
		$config = $this->get_pattern_variable_config( $pattern_type );

		// If no config, return all variables in default order
		if ( empty( $config ) ) {
			return array_values( $all_variables );
		}

		// Build ordered array based on config
		$ordered_vars = array();
		foreach ( $config as $var_key => $var_config ) {
			// Only include enabled variables
			if ( isset( $var_config['enabled'] ) && $var_config['enabled'] && isset( $all_variables[ $var_key ] ) ) {
				$order                = isset( $var_config['order'] ) ? intval( $var_config['order'] ) : 999;
				$ordered_vars[ $order ] = $all_variables[ $var_key ];
			}
		}

		// Sort by order
		ksort( $ordered_vars );

		// Return as indexed array
		return array_values( $ordered_vars );
	}

	/**
	 * Get user registration variables for SMS template
	 *
	 * @param int   $user_id   User ID
	 * @param array $user_data User data (optional, for newly created users)
	 * @return array Variables for SMS template
	 */
	public function get_user_registration_variables( $user_id, $user_data = array() ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		// Use provided data or fetch from user meta
		$first_name = ! empty( $user_data['first_name'] ) ? $user_data['first_name'] : get_user_meta( $user_id, 'first_name', true );
		$last_name  = ! empty( $user_data['last_name'] ) ? $user_data['last_name'] : get_user_meta( $user_id, 'last_name', true );
		$user_name  = trim( $first_name . ' ' . $last_name );
		$mobile     = $user->user_login; // Mobile is stored as username

		// Format date in Persian (Jalali) if possible, otherwise use Gregorian
		$date = date_i18n( 'Y/m/d' );

		$all_variables = array(
			'user_name'  => $user_name,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'mobile'     => $mobile,
			'date'       => $date,
		);

		return $this->build_variables_array( $all_variables, 'admin_user_registration' );
	}

	/**
	 * Get admin order variables for SMS template
	 *
	 * @param int   $order_id   Order ID
	 * @param array $order_data Order data (optional)
	 * @return array Variables for SMS template
	 */
	public function get_admin_order_variables( $order_id, $order_data = array() ) {
		// Get order from database if not provided
		if ( empty( $order_data ) ) {
			$order = Tabesh()->order->get_order( $order_id );
			if ( ! $order ) {
				return array();
			}
		} else {
			// Use provided data
			$order = (object) $order_data;
		}

		// Get user information
		$user          = get_userdata( $order->user_id );
		$customer_name = $user ? $user->display_name : __( 'مشتری', 'tabesh' );

		// Format date in Persian (Jalali) if possible, otherwise use Gregorian
		$date = date_i18n( 'Y/m/d' );

		$all_variables = array(
			'order_number'  => $order->order_number ?? '',
			'customer_name' => $customer_name,
			'book_title'    => $order->book_title ?? '',
			'quantity'      => $order->quantity ?? 0,
			'total_price'   => number_format( $order->total_price ?? 0 ),
			'date'          => $date,
		);

		return $this->build_variables_array( $all_variables, 'admin_order_created' );
	}

	/**
	 * Send SMS notification for user registration by admin
	 *
	 * @param int   $user_id   User ID
	 * @param array $user_data User data
	 * @return bool|WP_Error True on success, WP_Error on failure, false if disabled
	 */
	public function send_user_registration_sms( $user_id, $user_data = array() ) {
		// Check if SMS is enabled for admin user registration
		if ( ! Tabesh()->get_setting( 'sms_admin_user_registration_enabled', '0' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS: Admin user registration SMS disabled' );
			}
			return false;
		}

		// Get pattern code
		$pattern_code = Tabesh()->get_setting( 'sms_admin_user_registration_pattern', '' );
		if ( empty( $pattern_code ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS: No pattern code for admin user registration' );
			}
			return false;
		}

		// Get user phone number
		$user  = get_userdata( $user_id );
		$phone = $user ? $user->user_login : '';

		// Validate phone
		if ( empty( $phone ) || ! $this->validate_phone( $phone ) ) {
			$error_message = __( 'شماره موبایل کاربر یافت نشد یا نامعتبر است', 'tabesh' );
			$this->log_error(
				'no_phone',
				$error_message,
				array( 'user_id' => $user_id )
			);
			return new WP_Error( 'no_phone', $error_message );
		}

		// Get variables for template
		$variables = $this->get_user_registration_variables( $user_id, $user_data );

		// Send SMS
		$result = $this->send_template_sms( $phone, $pattern_code, $variables );

		// Log the action
		if ( ! is_wp_error( $result ) && $result === true ) {
			$this->log_action(
				0, // No order_id for user registration
				'sms_sent',
				sprintf(
					__( 'پیامک ثبت‌نام کاربر به شماره %s ارسال شد', 'tabesh' ),
					substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 )
				)
			);
		}

		return $result;
	}

	/**
	 * Send SMS notification when admin creates order
	 *
	 * @param int   $order_id   Order ID
	 * @param array $order_data Order data
	 * @return bool|WP_Error True on success, WP_Error on failure, false if disabled
	 */
	public function send_admin_order_created_sms( $order_id, $order_data = array() ) {
		// Check if SMS is enabled for admin order created
		if ( ! Tabesh()->get_setting( 'sms_admin_order_created_enabled', '0' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS: Admin order created SMS disabled' );
			}
			return false;
		}

		// Get pattern code
		$pattern_code = Tabesh()->get_setting( 'sms_admin_order_created_pattern', '' );
		if ( empty( $pattern_code ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh SMS: No pattern code for admin order created' );
			}
			return false;
		}

		// Get order details if not provided
		if ( empty( $order_data ) ) {
			$order = Tabesh()->order->get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'order_not_found', __( 'سفارش یافت نشد', 'tabesh' ) );
			}
			$order_data = (array) $order;
		}

		// Get customer phone number
		$user_id = isset( $order_data['user_id'] ) ? $order_data['user_id'] : 0;
		$user    = get_userdata( $user_id );
		$phone   = $user ? $user->user_login : '';

		// Validate phone from user_login
		if ( empty( $phone ) || ! $this->validate_phone( $phone ) ) {
			// Fallback to billing_phone from user meta if user_login is not a valid phone
			$phone = get_user_meta( $user_id, 'billing_phone', true );
		}

		// Final validation
		if ( empty( $phone ) || ! $this->validate_phone( $phone ) ) {
			$error_message = __( 'شماره موبایل مشتری یافت نشد یا نامعتبر است', 'tabesh' );
			$this->log_error(
				'no_phone',
				$error_message,
				array(
					'order_id' => $order_id,
					'user_id'  => $user_id,
				)
			);
			return new WP_Error( 'no_phone', $error_message );
		}

		// Get variables for template
		$variables = $this->get_admin_order_variables( $order_id, $order_data );

		// Send SMS
		$result = $this->send_template_sms( $phone, $pattern_code, $variables );

		// Log the action
		if ( ! is_wp_error( $result ) && $result === true ) {
			$this->log_action(
				$order_id,
				'sms_sent',
				sprintf(
					__( 'پیامک ثبت سفارش به شماره %s ارسال شد', 'tabesh' ),
					substr( $phone, 0, 4 ) . '****' . substr( $phone, -2 )
				)
			);
		}

		return $result;
	}
}
