<?php
/**
 * AI Persona Builder
 *
 * Builds user persona based on behavior, interactions, and preferences.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Persona_Builder
 *
 * Handles user persona building and analysis
 */
class Tabesh_AI_Persona_Builder {

	/**
	 * Build user persona from behavior data
	 *
	 * @param int    $user_id User ID (0 for guests).
	 * @param string $guest_uuid Guest UUID.
	 * @return array User persona.
	 */
	public function build_persona( $user_id = 0, $guest_uuid = '' ) {
		// Get behavior history.
		$tracker = new Tabesh_AI_Tracker();
		$history = $tracker->get_behavior_history( $user_id, $guest_uuid, 100 );

		// Get existing profile.
		$profile_manager = new Tabesh_AI_User_Profile();
		if ( $user_id ) {
			$profile = $profile_manager->get_user_profile( $user_id );
		} elseif ( $guest_uuid ) {
			$profile = $profile_manager->get_guest_profile( $guest_uuid );
		} else {
			$profile = array();
		}

		// Build persona structure.
		$persona = array(
			'detected_profession' => $this->detect_profession( $history, $profile ),
			'experience_level'    => $this->detect_experience_level( $history, $profile ),
			'current_intent'      => $this->detect_intent( $history ),
			'interests'           => $this->detect_interests( $history, $profile ),
			'browsing_history'    => $this->summarize_browsing( $history ),
			'form_interactions'   => $this->summarize_form_interactions( $history ),
			'confusion_signals'   => $this->detect_confusion_signals( $history ),
			'engagement_level'    => $this->calculate_engagement( $history ),
			'preferred_content'   => $this->detect_content_preferences( $history ),
			'last_updated'        => current_time( 'mysql' ),
		);

		return $persona;
	}

	/**
	 * Detect user profession from behavior
	 *
	 * @param array $history Behavior history.
	 * @param array $profile Existing profile.
	 * @return string Profession.
	 */
	private function detect_profession( $history, $profile ) {
		// Return existing profession if set.
		if ( ! empty( $profile['profession'] ) ) {
			return $profile['profession'];
		}

		// Analyze behavior patterns.
		$keywords = array(
			'author'    => array( 'نویسنده', 'author', 'نوشتن', 'writing', 'کتاب خودم' ),
			'publisher' => array( 'ناشر', 'publisher', 'انتشارات', 'چند عنوان', 'تیراژ بالا' ),
			'printer'   => array( 'چاپخانه', 'printer', 'چاپ', 'printing', 'offset' ),
			'buyer'     => array( 'خرید', 'buyer', 'سفارش', 'order', 'قیمت' ),
		);

		$scores = array_fill_keys( array_keys( $keywords ), 0 );

		foreach ( $history as $event ) {
			$event_data = isset( $event['event_data'] ) ? $event['event_data'] : array();
			$page_url   = isset( $event['page_url'] ) ? $event['page_url'] : '';

			// Check page URLs.
			foreach ( $keywords as $profession => $terms ) {
				foreach ( $terms as $term ) {
					if ( stripos( $page_url, $term ) !== false ) {
						$scores[ $profession ] += 2;
					}
				}
			}

			// Check event data.
			$event_text = wp_json_encode( $event_data );
			foreach ( $keywords as $profession => $terms ) {
				foreach ( $terms as $term ) {
					if ( stripos( $event_text, $term ) !== false ) {
						$scores[ $profession ] += 1;
					}
				}
			}
		}

		// Return profession with highest score.
		arsort( $scores );
		$top_profession = key( $scores );

		return $scores[ $top_profession ] > 0 ? $top_profession : 'buyer';
	}

	/**
	 * Detect experience level
	 *
	 * @param array $history Behavior history.
	 * @param array $profile Existing profile.
	 * @return string Experience level.
	 */
	private function detect_experience_level( $history, $profile ) {
		$total_visits     = count( $history );
		$confusion_count  = 0;
		$completion_count = 0;

		foreach ( $history as $event ) {
			$event_type = isset( $event['event_type'] ) ? $event['event_type'] : '';

			// Count confusion signals.
			if ( in_array( $event_type, array( 'help_request', 'back_navigation', 'idle' ), true ) ) {
				++$confusion_count;
			}

			// Count completions.
			if ( in_array( $event_type, array( 'order_submitted', 'checkout_completed' ), true ) ) {
				++$completion_count;
			}
		}

		// Calculate experience score.
		$confusion_ratio = $total_visits > 0 ? $confusion_count / $total_visits : 0;

		if ( $completion_count > 5 || ( $total_visits > 20 && $confusion_ratio < 0.1 ) ) {
			return 'expert';
		} elseif ( $completion_count > 1 || ( $total_visits > 10 && $confusion_ratio < 0.3 ) ) {
			return 'intermediate';
		} else {
			return 'beginner';
		}
	}

	/**
	 * Detect current intent
	 *
	 * @param array $history Recent behavior history.
	 * @return string Intent.
	 */
	private function detect_intent( $history ) {
		// Get recent events (last 10).
		$recent = array_slice( $history, 0, 10 );

		$intents = array(
			'ordering_book'    => 0,
			'browsing_catalog' => 0,
			'seeking_help'     => 0,
			'comparing_prices' => 0,
			'checking_order'   => 0,
		);

		foreach ( $recent as $event ) {
			$page_url   = isset( $event['page_url'] ) ? $event['page_url'] : '';
			$event_type = isset( $event['event_type'] ) ? $event['event_type'] : '';

			// Order form.
			if ( strpos( $page_url, 'order-form' ) !== false ) {
				$intents['ordering_book'] += 3;
			}

			// Cart/Checkout.
			if ( strpos( $page_url, 'cart' ) !== false || strpos( $page_url, 'checkout' ) !== false ) {
				$intents['ordering_book'] += 2;
			}

			// Product pages.
			if ( strpos( $page_url, 'product' ) !== false ) {
				$intents['browsing_catalog'] += 2;
			}

			// Help requests.
			if ( in_array( $event_type, array( 'help_request', 'chat_opened' ), true ) ) {
				$intents['seeking_help'] += 3;
			}

			// Multiple price calculations.
			if ( $event_type === 'price_calculated' ) {
				$intents['comparing_prices'] += 2;
			}

			// Order status check.
			if ( strpos( $page_url, 'my-orders' ) !== false || strpos( $page_url, 'order-status' ) !== false ) {
				$intents['checking_order'] += 3;
			}
		}

		// Return intent with highest score.
		arsort( $intents );
		return key( $intents );
	}

	/**
	 * Detect interests from behavior
	 *
	 * @param array $history Behavior history.
	 * @param array $profile Existing profile.
	 * @return array Interests.
	 */
	private function detect_interests( $history, $profile ) {
		$interests = ! empty( $profile['interests'] ) ? (array) $profile['interests'] : array();

		$keywords = array(
			'literature'  => array( 'ادبیات', 'شعر', 'رمان', 'داستان' ),
			'educational' => array( 'آموزشی', 'درسی', 'تحصیلی', 'دانشگاهی' ),
			'children'    => array( 'کودک', 'نوجوان', 'کودکانه' ),
			'religious'   => array( 'مذهبی', 'قرآنی', 'دینی' ),
			'art'         => array( 'هنری', 'نقاشی', 'تصویری', 'آلبوم' ),
			'business'    => array( 'کسب‌وکار', 'مدیریت', 'بازاریابی' ),
		);

		foreach ( $history as $event ) {
			$event_text = wp_json_encode( $event );

			foreach ( $keywords as $interest => $terms ) {
				foreach ( $terms as $term ) {
					if ( stripos( $event_text, $term ) !== false && ! in_array( $interest, $interests, true ) ) {
						$interests[] = $interest;
						break;
					}
				}
			}
		}

		return $interests;
	}

	/**
	 * Summarize browsing history
	 *
	 * @param array $history Behavior history.
	 * @return array Browsing summary.
	 */
	private function summarize_browsing( $history ) {
		$pages = array();

		foreach ( $history as $event ) {
			if ( $event['event_type'] === 'page_view' && ! empty( $event['page_url'] ) ) {
				$url = $event['page_url'];
				if ( ! isset( $pages[ $url ] ) ) {
					$pages[ $url ] = 0;
				}
				++$pages[ $url ];
			}
		}

		// Sort by visit count.
		arsort( $pages );

		// Return top 10 pages.
		return array_slice( $pages, 0, 10, true );
	}

	/**
	 * Summarize form interactions
	 *
	 * @param array $history Behavior history.
	 * @return array Form interactions summary.
	 */
	private function summarize_form_interactions( $history ) {
		$interactions = array();

		foreach ( $history as $event ) {
			if ( in_array( $event['event_type'], array( 'field_focused', 'field_changed', 'form_submitted' ), true ) ) {
				$event_data = isset( $event['event_data'] ) ? $event['event_data'] : array();
				$field_name = isset( $event_data['field_name'] ) ? $event_data['field_name'] : 'unknown';

				if ( ! isset( $interactions[ $field_name ] ) ) {
					$interactions[ $field_name ] = 0;
				}
				++$interactions[ $field_name ];
			}
		}

		return $interactions;
	}

	/**
	 * Detect confusion signals
	 *
	 * @param array $history Behavior history.
	 * @return array Confusion signals.
	 */
	private function detect_confusion_signals( $history ) {
		$signals = array();

		$confusion_events = array(
			'idle'            => 'بیش از حد در صفحه ماند',
			'back_navigation' => 'چندین بار به عقب برگشت',
			'help_request'    => 'درخواست کمک کرد',
			'form_abandoned'  => 'فرم را ناتمام رها کرد',
			'rapid_clicks'    => 'کلیک‌های سریع متعدد',
		);

		foreach ( $history as $event ) {
			$event_type = isset( $event['event_type'] ) ? $event['event_type'] : '';

			if ( isset( $confusion_events[ $event_type ] ) ) {
				$signals[] = array(
					'type'        => $event_type,
					'description' => $confusion_events[ $event_type ],
					'timestamp'   => isset( $event['created_at'] ) ? $event['created_at'] : '',
				);
			}
		}

		return array_slice( $signals, 0, 5 ); // Return last 5 signals.
	}

	/**
	 * Calculate engagement level
	 *
	 * @param array $history Behavior history.
	 * @return string Engagement level.
	 */
	private function calculate_engagement( $history ) {
		$total_events  = count( $history );
		$active_events = 0;

		foreach ( $history as $event ) {
			$event_type = isset( $event['event_type'] ) ? $event['event_type'] : '';

			// Count active interactions.
			if ( in_array( $event_type, array( 'click', 'field_focused', 'field_changed', 'form_submitted', 'chat_message' ), true ) ) {
				++$active_events;
			}
		}

		$engagement_ratio = $total_events > 0 ? $active_events / $total_events : 0;

		if ( $engagement_ratio > 0.5 && $total_events > 10 ) {
			return 'high';
		} elseif ( $engagement_ratio > 0.25 || $total_events > 5 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Detect content preferences
	 *
	 * @param array $history Behavior history.
	 * @return array Content preferences.
	 */
	private function detect_content_preferences( $history ) {
		$preferences = array(
			'prefers_images'  => false,
			'prefers_details' => false,
			'prefers_quick'   => false,
		);

		$image_views   = 0;
		$detail_views  = 0;
		$quick_actions = 0;

		foreach ( $history as $event ) {
			$event_type = isset( $event['event_type'] ) ? $event['event_type'] : '';
			$page_url   = isset( $event['page_url'] ) ? $event['page_url'] : '';

			// Count image-related events.
			if ( strpos( $page_url, 'gallery' ) !== false || strpos( $page_url, 'portfolio' ) !== false ) {
				++$image_views;
			}

			// Count detail page views.
			if ( strpos( $page_url, 'about' ) !== false || strpos( $page_url, 'details' ) !== false ) {
				++$detail_views;
			}

			// Count quick actions.
			if ( in_array( $event_type, array( 'quick_order', 'one_click' ), true ) ) {
				++$quick_actions;
			}
		}

		$total = count( $history );
		if ( $total > 0 ) {
			$preferences['prefers_images']  = ( $image_views / $total ) > 0.3;
			$preferences['prefers_details'] = ( $detail_views / $total ) > 0.2;
			$preferences['prefers_quick']   = ( $quick_actions / $total ) > 0.2;
		}

		return $preferences;
	}

	/**
	 * Get persona summary for AI context
	 *
	 * @param array $persona Persona data.
	 * @return string Persona summary.
	 */
	public function get_persona_summary( $persona ) {
		$parts = array();

		// Add profession.
		if ( ! empty( $persona['detected_profession'] ) ) {
			$profession_labels = array(
				'buyer'     => 'خریدار',
				'author'    => 'نویسنده',
				'publisher' => 'ناشر',
				'printer'   => 'چاپخانه‌دار',
			);
			$profession        = isset( $profession_labels[ $persona['detected_profession'] ] ) ? $profession_labels[ $persona['detected_profession'] ] : $persona['detected_profession'];
			$parts[]           = sprintf( 'شغل: %s', $profession );
		}

		// Add experience level.
		if ( ! empty( $persona['experience_level'] ) ) {
			$level_labels = array(
				'beginner'     => 'مبتدی',
				'intermediate' => 'متوسط',
				'expert'       => 'حرفه‌ای',
			);
			$level        = isset( $level_labels[ $persona['experience_level'] ] ) ? $level_labels[ $persona['experience_level'] ] : $persona['experience_level'];
			$parts[]      = sprintf( 'سطح تجربه: %s', $level );
		}

		// Add intent.
		if ( ! empty( $persona['current_intent'] ) ) {
			$intent_labels = array(
				'ordering_book'    => 'در حال سفارش کتاب',
				'browsing_catalog' => 'مرور کاتالوگ',
				'seeking_help'     => 'نیاز به کمک',
				'comparing_prices' => 'مقایسه قیمت',
				'checking_order'   => 'بررسی سفارش',
			);
			$intent        = isset( $intent_labels[ $persona['current_intent'] ] ) ? $intent_labels[ $persona['current_intent'] ] : $persona['current_intent'];
			$parts[]       = sprintf( 'هدف فعلی: %s', $intent );
		}

		// Add interests.
		if ( ! empty( $persona['interests'] ) && is_array( $persona['interests'] ) ) {
			$parts[] = sprintf( 'علایق: %s', implode( '، ', $persona['interests'] ) );
		}

		// Add confusion signals.
		if ( ! empty( $persona['confusion_signals'] ) && is_array( $persona['confusion_signals'] ) && count( $persona['confusion_signals'] ) > 0 ) {
			$parts[] = '⚠️ نشانه‌های سردرگمی شناسایی شد';
		}

		return implode( ' | ', $parts );
	}
}
