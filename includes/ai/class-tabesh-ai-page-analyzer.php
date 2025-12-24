<?php
/**
 * AI Page Analyzer
 *
 * Analyzes page content (DOM, text, forms, buttons) to provide
 * context-aware AI assistance.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Page_Analyzer
 *
 * Handles page content analysis for AI context
 */
class Tabesh_AI_Page_Analyzer {

	/**
	 * Extract and sanitize page context from client data
	 *
	 * @param array $client_data Raw data from JavaScript.
	 * @return array Sanitized page context.
	 */
	public function extract_page_context( $client_data ) {
		$context = array(
			'page_title'      => '',
			'page_url'        => '',
			'page_content'    => '',
			'forms'           => array(),
			'hovered_element' => array(),
			'visible_buttons' => array(),
			'navigation_menu' => array(),
			'page_type'       => 'unknown',
		);

		// Sanitize page title.
		if ( isset( $client_data['pageTitle'] ) ) {
			$context['page_title'] = sanitize_text_field( $client_data['pageTitle'] );
		}

		// Sanitize URL.
		if ( isset( $client_data['currentURL'] ) ) {
			$context['page_url'] = esc_url_raw( $client_data['currentURL'] );
		}

		// Sanitize visible text content.
		if ( isset( $client_data['pageContent'] ) ) {
			$context['page_content'] = $this->sanitize_html_content( $client_data['pageContent'] );
		}

		// Sanitize form data.
		if ( isset( $client_data['forms'] ) && is_array( $client_data['forms'] ) ) {
			$context['forms'] = $this->sanitize_forms( $client_data['forms'] );
		}

		// Sanitize hovered element.
		if ( isset( $client_data['hoveredElement'] ) && is_array( $client_data['hoveredElement'] ) ) {
			$context['hovered_element'] = $this->sanitize_element_info( $client_data['hoveredElement'] );
		}

		// Sanitize buttons.
		if ( isset( $client_data['visibleButtons'] ) && is_array( $client_data['visibleButtons'] ) ) {
			$context['visible_buttons'] = $this->sanitize_buttons( $client_data['visibleButtons'] );
		}

		// Sanitize navigation menu.
		if ( isset( $client_data['navigationMenu'] ) && is_array( $client_data['navigationMenu'] ) ) {
			$context['navigation_menu'] = $this->sanitize_menu( $client_data['navigationMenu'] );
		}

		// Detect page type.
		$context['page_type'] = $this->detect_page_type( $context );

		return $context;
	}

	/**
	 * Sanitize HTML content
	 *
	 * @param string $content Raw HTML content.
	 * @return string Sanitized content.
	 */
	private function sanitize_html_content( $content ) {
		// Strip all HTML tags and keep only text.
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );

		// Trim and limit length.
		$content = trim( $content );
		$content = mb_substr( $content, 0, 5000 ); // Limit to 5000 chars.

		return $content;
	}

	/**
	 * Sanitize forms data
	 *
	 * @param array $forms Forms array.
	 * @return array Sanitized forms.
	 */
	private function sanitize_forms( $forms ) {
		$sanitized = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$sanitized_form = array(
				'id'     => isset( $form['id'] ) ? sanitize_text_field( $form['id'] ) : '',
				'name'   => isset( $form['name'] ) ? sanitize_text_field( $form['name'] ) : '',
				'action' => isset( $form['action'] ) ? esc_url_raw( $form['action'] ) : '',
				'method' => isset( $form['method'] ) ? sanitize_text_field( $form['method'] ) : 'post',
				'fields' => array(),
			);

			// Sanitize fields.
			if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}

					$sanitized_form['fields'][] = array(
						'name'        => isset( $field['name'] ) ? sanitize_text_field( $field['name'] ) : '',
						'type'        => isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text',
						'label'       => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '',
						'value'       => isset( $field['value'] ) ? sanitize_textarea_field( $field['value'] ) : '',
						'placeholder' => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
						'required'    => isset( $field['required'] ) ? (bool) $field['required'] : false,
					);
				}
			}

			$sanitized[] = $sanitized_form;
		}

		return $sanitized;
	}

	/**
	 * Sanitize element info
	 *
	 * @param array $element Element data.
	 * @return array Sanitized element.
	 */
	private function sanitize_element_info( $element ) {
		if ( empty( $element ) || ! is_array( $element ) ) {
			return array();
		}

		return array(
			'tagName'     => isset( $element['tagName'] ) ? sanitize_text_field( $element['tagName'] ) : '',
			'id'          => isset( $element['id'] ) ? sanitize_text_field( $element['id'] ) : '',
			'className'   => isset( $element['className'] ) ? sanitize_text_field( $element['className'] ) : '',
			'text'        => isset( $element['text'] ) ? sanitize_text_field( $element['text'] ) : '',
			'href'        => isset( $element['href'] ) ? esc_url_raw( $element['href'] ) : '',
			'name'        => isset( $element['name'] ) ? sanitize_text_field( $element['name'] ) : '',
			'type'        => isset( $element['type'] ) ? sanitize_text_field( $element['type'] ) : '',
			'placeholder' => isset( $element['placeholder'] ) ? sanitize_text_field( $element['placeholder'] ) : '',
			'value'       => isset( $element['value'] ) ? sanitize_textarea_field( $element['value'] ) : '',
		);
	}

	/**
	 * Sanitize buttons array
	 *
	 * @param array $buttons Buttons array.
	 * @return array Sanitized buttons.
	 */
	private function sanitize_buttons( $buttons ) {
		$sanitized = array();

		foreach ( $buttons as $button ) {
			if ( ! is_array( $button ) ) {
				continue;
			}

			$sanitized[] = array(
				'text'      => isset( $button['text'] ) ? sanitize_text_field( $button['text'] ) : '',
				'id'        => isset( $button['id'] ) ? sanitize_text_field( $button['id'] ) : '',
				'className' => isset( $button['className'] ) ? sanitize_text_field( $button['className'] ) : '',
				'type'      => isset( $button['type'] ) ? sanitize_text_field( $button['type'] ) : 'button',
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize navigation menu
	 *
	 * @param array $menu Menu array.
	 * @return array Sanitized menu.
	 */
	private function sanitize_menu( $menu ) {
		$sanitized = array();

		foreach ( $menu as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$sanitized[] = array(
				'text' => isset( $item['text'] ) ? sanitize_text_field( $item['text'] ) : '',
				'href' => isset( $item['href'] ) ? esc_url_raw( $item['href'] ) : '',
			);
		}

		return $sanitized;
	}

	/**
	 * Detect page type based on context
	 *
	 * @param array $context Page context.
	 * @return string Page type.
	 */
	private function detect_page_type( $context ) {
		$url = $context['page_url'];

		// Check for order form.
		if ( strpos( $url, 'order-form' ) !== false || strpos( $url, 'tabesh-order' ) !== false ) {
			return 'order-form';
		}

		// Check for cart.
		if ( strpos( $url, '/cart' ) !== false || strpos( $url, 'wc-cart' ) !== false ) {
			return 'cart';
		}

		// Check for checkout.
		if ( strpos( $url, '/checkout' ) !== false ) {
			return 'checkout';
		}

		// Check for product page.
		if ( strpos( $url, '/product/' ) !== false ) {
			return 'product';
		}

		// Check for homepage.
		if ( home_url() === $url || home_url( '/' ) === $url ) {
			return 'homepage';
		}

		// Check for admin pages.
		if ( is_admin() ) {
			return 'admin';
		}

		return 'unknown';
	}

	/**
	 * Build enriched context for Gemini AI
	 *
	 * @param array $page_context Page context from client.
	 * @param array $user_profile User profile data.
	 * @return string Formatted context for AI.
	 */
	public function build_gemini_context( $page_context, $user_profile = array() ) {
		$context_parts = array();

		// Add page information.
		$context_parts[] = sprintf(
			'صفحه فعلی: %s (%s)',
			$page_context['page_title'],
			$page_context['page_type']
		);

		// Add user persona if available.
		if ( ! empty( $user_profile['profession'] ) ) {
			$context_parts[] = sprintf( 'شغل کاربر: %s', $user_profile['profession'] );
		}

		if ( ! empty( $user_profile['interests'] ) ) {
			$interests       = is_array( $user_profile['interests'] ) ? implode( ', ', $user_profile['interests'] ) : $user_profile['interests'];
			$context_parts[] = sprintf( 'علایق: %s', $interests );
		}

		// Add current form data if available.
		if ( ! empty( $page_context['forms'] ) ) {
			$form_summary = $this->summarize_forms( $page_context['forms'] );
			if ( $form_summary ) {
				$context_parts[] = 'اطلاعات فرم: ' . $form_summary;
			}
		}

		// Add hovered element if available.
		if ( ! empty( $page_context['hovered_element']['text'] ) ) {
			$context_parts[] = sprintf(
				'المان هاور شده: %s',
				$page_context['hovered_element']['text']
			);
		}

		return implode( "\n", $context_parts );
	}

	/**
	 * Summarize forms for context
	 *
	 * @param array $forms Forms array.
	 * @return string Form summary.
	 */
	private function summarize_forms( $forms ) {
		$summary_parts = array();

		foreach ( $forms as $form ) {
			$filled_fields = array();

			if ( ! empty( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( ! empty( $field['value'] ) && ! empty( $field['label'] ) ) {
						$filled_fields[] = sprintf(
							'%s: %s',
							$field['label'],
							mb_substr( $field['value'], 0, 50 )
						);
					}
				}
			}

			if ( ! empty( $filled_fields ) ) {
				$summary_parts[] = implode( ', ', $filled_fields );
			}
		}

		return implode( ' | ', $summary_parts );
	}
}
