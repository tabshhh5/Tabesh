<?php
/**
 * AI Site Indexer
 *
 * Scans and indexes site pages for AI-powered navigation assistance.
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_AI_Site_Indexer
 *
 * Handles site page indexing for AI context
 */
class Tabesh_AI_Site_Indexer {

	/**
	 * Index a single page
	 *
	 * @param string $url Page URL.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function index_page( $url ) {
		global $wpdb;

		// Sanitize URL.
		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'آدرس نامعتبر است', 'tabesh' ) );
		}

		// Fetch page content.
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'Tabesh-AI-Indexer/1.0',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_content', __( 'محتوای صفحه خالی است', 'tabesh' ) );
		}

		// Parse page content.
		$page_data = $this->parse_page_content( $body, $url );

		// Save to database.
		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table_name} WHERE page_url = %s",
				$url
			)
		);

		$data = array(
			'page_url'             => $url,
			'page_title'           => $page_data['title'],
			'page_content_summary' => $page_data['summary'],
			'page_keywords'        => wp_json_encode( $page_data['keywords'] ),
			'page_type'            => $page_data['type'],
			'last_scanned'         => current_time( 'mysql' ),
		);

		if ( $existing ) {
			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$table_name,
				$data,
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return $result !== false;
	}

	/**
	 * Parse page content and extract data
	 *
	 * @param string $html HTML content.
	 * @param string $url Page URL.
	 * @return array Page data.
	 */
	private function parse_page_content( $html, $url ) {
		// Extract title.
		$title = '';
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			$title = wp_strip_all_tags( $matches[1] );
			$title = html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
		}

		// Remove script and style tags.
		$html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );

		// Extract visible text.
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Create summary (first 500 chars).
		$summary = mb_substr( $text, 0, 500 );

		// Extract keywords.
		$keywords = $this->extract_keywords( $text );

		// Detect page type.
		$type = $this->detect_page_type( $url, $title, $text );

		return array(
			'title'    => $title,
			'summary'  => $summary,
			'keywords' => $keywords,
			'type'     => $type,
		);
	}

	/**
	 * Extract keywords from text
	 *
	 * @param string $text Page text.
	 * @return array Keywords.
	 */
	private function extract_keywords( $text ) {
		// Common Persian stop words.
		$stop_words = array( 'و', 'به', 'از', 'که', 'این', 'در', 'را', 'با', 'است', 'برای', 'یک', 'آن', 'هر', 'تا', 'بر', 'کرد' );

		// Split into words.
		$words = preg_split( '/\s+/', $text );

		// Count word frequency.
		$word_freq = array();
		foreach ( $words as $word ) {
			$word = trim( $word, '.,;:!?()[]{}' );
			$word = mb_strtolower( $word );

			// Skip short words and stop words.
			if ( mb_strlen( $word ) < 3 || in_array( $word, $stop_words, true ) ) {
				continue;
			}

			if ( ! isset( $word_freq[ $word ] ) ) {
				$word_freq[ $word ] = 0;
			}
			++$word_freq[ $word ];
		}

		// Sort by frequency.
		arsort( $word_freq );

		// Return top 20 keywords.
		return array_keys( array_slice( $word_freq, 0, 20 ) );
	}

	/**
	 * Detect page type
	 *
	 * @param string $url Page URL.
	 * @param string $title Page title.
	 * @param string $text Page text.
	 * @return string Page type.
	 */
	private function detect_page_type( $url, $title, $text ) {
		// Check URL patterns.
		if ( strpos( $url, 'order-form' ) !== false || strpos( $url, 'tabesh-order' ) !== false ) {
			return 'order-form';
		}

		if ( strpos( $url, '/cart' ) !== false || strpos( $url, 'wc-cart' ) !== false ) {
			return 'cart';
		}

		if ( strpos( $url, '/checkout' ) !== false ) {
			return 'checkout';
		}

		if ( strpos( $url, '/product/' ) !== false ) {
			return 'product';
		}

		if ( strpos( $url, '/my-account' ) !== false || strpos( $url, '/my-orders' ) !== false ) {
			return 'account';
		}

		// Check content.
		$combined = mb_strtolower( $title . ' ' . mb_substr( $text, 0, 1000 ) );

		if ( strpos( $combined, 'سفارش' ) !== false && strpos( $combined, 'فرم' ) !== false ) {
			return 'order-form';
		}

		if ( strpos( $combined, 'درباره' ) !== false || strpos( $combined, 'about' ) !== false ) {
			return 'about';
		}

		if ( strpos( $combined, 'تماس' ) !== false || strpos( $combined, 'contact' ) !== false ) {
			return 'contact';
		}

		if ( strpos( $combined, 'نمونه' ) !== false || strpos( $combined, 'portfolio' ) !== false ) {
			return 'portfolio';
		}

		return 'info';
	}

	/**
	 * Index all pages from sitemap
	 *
	 * @return array Result summary.
	 */
	public function index_from_sitemap() {
		$sitemap_url = home_url( '/wp-sitemap.xml' );

		// Check if Yoast SEO sitemap exists.
		$yoast_sitemap = home_url( '/sitemap_index.xml' );
		$response      = wp_remote_head( $yoast_sitemap );
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$sitemap_url = $yoast_sitemap;
		}

		// Fetch sitemap.
		$response = wp_remote_get( $sitemap_url );
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$xml = wp_remote_retrieve_body( $response );

		// Parse URLs from sitemap.
		$urls = array();
		if ( preg_match_all( '/<loc>(.*?)<\/loc>/i', $xml, $matches ) ) {
			$urls = $matches[1];
		}

		// Index each page.
		$indexed = 0;
		$failed  = 0;

		foreach ( $urls as $url ) {
			// Skip external URLs.
			if ( strpos( $url, home_url() ) !== 0 ) {
				continue;
			}

			$result = $this->index_page( $url );
			if ( true === $result ) {
				++$indexed;
			} else {
				++$failed;
			}

			// Small delay to avoid overwhelming the server.
			usleep( 100000 ); // 0.1 second.
		}

		return array(
			'success' => true,
			'indexed' => $indexed,
			'failed'  => $failed,
			'total'   => count( $urls ),
		);
	}

	/**
	 * Get indexed page by URL
	 *
	 * @param string $url Page URL.
	 * @return array|null Page data or null if not found.
	 */
	public function get_indexed_page( $url ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$page = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE page_url = %s",
				esc_url_raw( $url )
			),
			ARRAY_A
		);

		if ( $page ) {
			// Decode keywords.
			$page['page_keywords'] = json_decode( $page['page_keywords'], true );
		}

		return $page;
	}

	/**
	 * Search indexed pages
	 *
	 * @param string $query Search query.
	 * @param int    $limit Number of results.
	 * @return array Search results.
	 */
	public function search_pages( $query, $limit = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';
		$query      = sanitize_text_field( $query );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
                WHERE page_title LIKE %s 
                OR page_content_summary LIKE %s 
                OR page_keywords LIKE %s
                ORDER BY last_scanned DESC
                LIMIT %d",
				'%' . $wpdb->esc_like( $query ) . '%',
				'%' . $wpdb->esc_like( $query ) . '%',
				'%' . $wpdb->esc_like( $query ) . '%',
				$limit
			),
			ARRAY_A
		);

		// Decode keywords for each result.
		if ( $results ) {
			foreach ( $results as &$result ) {
				$result['page_keywords'] = json_decode( $result['page_keywords'], true );
			}
		}

		return $results ? $results : array();
	}

	/**
	 * Clean up old indexed pages (older than specified days)
	 *
	 * @param int $days Days to keep.
	 * @return int Number of deleted pages.
	 */
	public function cleanup_old_pages( $days = 90 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE last_scanned < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $deleted ? absint( $deleted ) : 0;
	}

	/**
	 * Schedule periodic indexing cron job
	 */
	public static function schedule_indexing() {
		if ( ! wp_next_scheduled( 'tabesh_ai_index_site_pages' ) ) {
			wp_schedule_event( time(), 'daily', 'tabesh_ai_index_site_pages' );
		}
	}

	/**
	 * Unschedule indexing cron job
	 */
	public static function unschedule_indexing() {
		$timestamp = wp_next_scheduled( 'tabesh_ai_index_site_pages' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tabesh_ai_index_site_pages' );
		}
	}

	/**
	 * Run scheduled indexing
	 */
	public static function run_scheduled_indexing() {
		$indexer = new self();
		$result  = $indexer->index_from_sitemap();

		// Log result.
		if ( $result['success'] ) {
			error_log(
				sprintf(
					'[Tabesh AI] Site indexing completed: %d indexed, %d failed out of %d total',
					$result['indexed'],
					$result['failed'],
					$result['total']
				)
			);
		} else {
			error_log( '[Tabesh AI] Site indexing failed: ' . $result['error'] );
		}
	}
}
