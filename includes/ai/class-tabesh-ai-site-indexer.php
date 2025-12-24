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
	 * Index all WordPress pages and posts automatically
	 *
	 * This method queries WordPress directly for all published content
	 * and indexes it for AI navigation assistance.
	 *
	 * @return array Result summary.
	 */
	public function index_wordpress_content() {
		global $wpdb;

		$indexed = 0;
		$failed  = 0;
		$total   = 0;

		// Index all published pages.
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_date',
				'sort_order'  => 'DESC',
			)
		);

		foreach ( $pages as $page ) {
			++$total;
			$page_url = get_permalink( $page->ID );
			$result   = $this->index_wordpress_post( $page, 'page' );

			if ( true === $result ) {
				++$indexed;
			} else {
				++$failed;
			}
		}

		// Index all published posts.
		$posts = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		foreach ( $posts as $post ) {
			++$total;
			$result = $this->index_wordpress_post( $post, 'post' );

			if ( true === $result ) {
				++$indexed;
			} else {
				++$failed;
			}
		}

		// Index custom post types if any.
		$custom_post_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => false,
			)
		);

		foreach ( $custom_post_types as $post_type ) {
			$custom_posts = get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'numberposts' => -1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			foreach ( $custom_posts as $custom_post ) {
				++$total;
				$result = $this->index_wordpress_post( $custom_post, $post_type );

				if ( true === $result ) {
					++$indexed;
				} else {
					++$failed;
				}
			}
		}

		return array(
			'success' => true,
			'indexed' => $indexed,
			'failed'  => $failed,
			'total'   => $total,
		);
	}

	/**
	 * Index a single WordPress post/page
	 *
	 * @param WP_Post $post      WordPress post object.
	 * @param string  $post_type Post type identifier.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function index_wordpress_post( $post, $post_type ) {
		global $wpdb;

		$page_url = get_permalink( $post->ID );

		if ( empty( $page_url ) ) {
			return new WP_Error( 'invalid_url', __( 'آدرس نامعتبر است', 'tabesh' ) );
		}

		// Extract title.
		$page_title = get_the_title( $post->ID );

		// Extract content and create summary.
		$content = wp_strip_all_tags( get_the_content( null, false, $post->ID ) );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );
		$summary = mb_substr( $content, 0, 500 );

		// Extract keywords from title and content.
		$keywords = $this->extract_keywords( $page_title . ' ' . $content );

		// Detect page type based on post type, slug, and content.
		$page_type = $this->detect_wordpress_page_type( $post, $post_type, $page_title, $content );

		// Save to database.
		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table_name} WHERE page_url = %s",
				$page_url
			)
		);

		$data = array(
			'page_url'             => $page_url,
			'page_title'           => $page_title,
			'page_content_summary' => $summary,
			'page_keywords'        => wp_json_encode( $keywords ),
			'page_type'            => $page_type,
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
	 * Detect WordPress page type
	 *
	 * @param WP_Post $post      WordPress post object.
	 * @param string  $post_type Post type identifier.
	 * @param string  $title     Page title.
	 * @param string  $content   Page content.
	 * @return string Page type.
	 */
	private function detect_wordpress_page_type( $post, $post_type, $title, $content ) {
		// Check post slug for common patterns.
		$slug = $post->post_name;

		// Check for specific page types based on slug.
		if ( in_array( $slug, array( 'order-form', 'tabesh-order', 'form-order' ), true ) ) {
			return 'order-form';
		}

		if ( in_array( $slug, array( 'cart', 'shopping-cart', 'basket' ), true ) ) {
			return 'cart';
		}

		if ( in_array( $slug, array( 'checkout' ), true ) ) {
			return 'checkout';
		}

		if ( in_array( $slug, array( 'my-account', 'account', 'profile' ), true ) ) {
			return 'account';
		}

		if ( in_array( $slug, array( 'about', 'about-us', 'درباره-ما' ), true ) ) {
			return 'about';
		}

		if ( in_array( $slug, array( 'contact', 'contact-us', 'تماس-با-ما' ), true ) ) {
			return 'contact';
		}

		if ( in_array( $slug, array( 'portfolio', 'gallery', 'نمونه-کار' ), true ) ) {
			return 'portfolio';
		}

		// Check content for keywords.
		$combined = mb_strtolower( $title . ' ' . mb_substr( $content, 0, 1000 ) );

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

		// Return based on post type.
		if ( 'page' === $post_type ) {
			return 'page';
		}

		if ( 'post' === $post_type ) {
			return 'blog-post';
		}

		return $post_type;
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
	 * Intelligent page search with fuzzy matching for Persian keywords
	 *
	 * Searches pages based on user intent with Persian keyword matching.
	 *
	 * @param string $user_query User's natural language query.
	 * @param int    $limit      Maximum number of results.
	 * @return array Search results with relevance scores.
	 */
	public function smart_search_pages( $user_query, $limit = 5 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';
		$user_query = sanitize_text_field( $user_query );

		// Extract keywords from user query.
		$keywords = $this->extract_search_keywords( $user_query );

		if ( empty( $keywords ) ) {
			return array();
		}

		// Build search conditions dynamically.
		$where_parts = array();
		$sql_params  = array();

		foreach ( $keywords as $keyword ) {
			$like_pattern  = '%' . $wpdb->esc_like( $keyword ) . '%';
			$where_parts[] = '(page_title LIKE %s OR page_content_summary LIKE %s OR page_keywords LIKE %s OR page_type LIKE %s)';
			$sql_params[]  = $like_pattern;
			$sql_params[]  = $like_pattern;
			$sql_params[]  = $like_pattern;
			$sql_params[]  = $like_pattern;
		}

		// Add limit parameter.
		$sql_params[] = $limit;

		// Build the SQL query.
		$where_clause = implode( ' OR ', $where_parts );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY last_scanned DESC LIMIT %d";
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $sql, $sql_params ),
			ARRAY_A
		);

		// Score results by relevance.
		$scored_results = array();
		if ( $results ) {
			foreach ( $results as $result ) {
				$result['page_keywords'] = json_decode( $result['page_keywords'], true );
				$score                   = $this->calculate_relevance_score( $result, $keywords, $user_query );
				$result['relevance']     = $score;
				$scored_results[]        = $result;
			}

			// Sort by relevance score.
			usort(
				$scored_results,
				function ( $a, $b ) {
					return $b['relevance'] <=> $a['relevance'];
				}
			);
		}

		return $scored_results;
	}

	/**
	 * Extract search keywords from user query
	 *
	 * @param string $query User query.
	 * @return array Keywords.
	 */
	private function extract_search_keywords( $query ) {
		// Normalize query.
		$query = mb_strtolower( trim( $query ) );

		// Define Persian keyword mappings for common intents.
		$intent_keywords = array(
			// Order form related.
			'سفارش'       => array( 'سفارش', 'فرم', 'order' ),
			'ثبت سفارش'   => array( 'سفارش', 'ثبت', 'فرم', 'order' ),
			'فرم سفارش'   => array( 'سفارش', 'فرم', 'order', 'form' ),
			'چاپ'         => array( 'چاپ', 'print', 'کتاب' ),
			'چاپ کتاب'    => array( 'چاپ', 'کتاب', 'print', 'book' ),
			// Contact.
			'تماس'        => array( 'تماس', 'contact', 'ارتباط' ),
			'تماس با ما'  => array( 'تماس', 'contact' ),
			// Pricing.
			'قیمت'        => array( 'قیمت', 'price', 'تعرفه', 'pricing' ),
			'محاسبه'      => array( 'محاسبه', 'قیمت', 'calculate' ),
			// Cart.
			'سبد خرید'    => array( 'سبد', 'خرید', 'cart' ),
			'سبد'         => array( 'سبد', 'cart' ),
			// Account.
			'حساب'        => array( 'حساب', 'account', 'کاربری' ),
			'حساب کاربری' => array( 'حساب', 'کاربری', 'account' ),
			// About.
			'درباره'      => array( 'درباره', 'about' ),
			'درباره ما'   => array( 'درباره', 'about' ),
			// Portfolio.
			'نمونه'       => array( 'نمونه', 'portfolio', 'کار' ),
			'نمونه کار'   => array( 'نمونه', 'کار', 'portfolio' ),
		);

		$keywords = array();

		// Check for exact phrase matches first.
		foreach ( $intent_keywords as $phrase => $related_keywords ) {
			if ( strpos( $query, $phrase ) !== false ) {
				$keywords = array_merge( $keywords, $related_keywords );
			}
		}

		// If no exact matches, extract individual words.
		if ( empty( $keywords ) ) {
			$words = preg_split( '/\s+/', $query );
			foreach ( $words as $word ) {
				$word = trim( $word, '.,;:!?؟،' );
				if ( mb_strlen( $word ) >= 2 ) {
					$keywords[] = $word;

					// Check if word is in our intent mappings.
					foreach ( $intent_keywords as $phrase => $related_keywords ) {
						if ( strpos( $phrase, $word ) !== false || in_array( $word, $related_keywords, true ) ) {
							$keywords = array_merge( $keywords, $related_keywords );
						}
					}
				}
			}
		}

		// Remove duplicates.
		$keywords = array_unique( $keywords );

		return $keywords;
	}

	/**
	 * Calculate relevance score for search result
	 *
	 * @param array  $page     Page data.
	 * @param array  $keywords Search keywords.
	 * @param string $query    Original query.
	 * @return float Relevance score.
	 */
	private function calculate_relevance_score( $page, $keywords, $query ) {
		$score = 0;

		// Score based on page title.
		foreach ( $keywords as $keyword ) {
			if ( stripos( $page['page_title'], $keyword ) !== false ) {
				$score += 10;
			}
		}

		// Score based on page type.
		foreach ( $keywords as $keyword ) {
			if ( stripos( $page['page_type'], $keyword ) !== false ) {
				$score += 8;
			}
		}

		// Score based on content summary.
		foreach ( $keywords as $keyword ) {
			if ( stripos( $page['page_content_summary'], $keyword ) !== false ) {
				$score += 5;
			}
		}

		// Score based on keywords array.
		if ( ! empty( $page['page_keywords'] ) && is_array( $page['page_keywords'] ) ) {
			foreach ( $keywords as $keyword ) {
				foreach ( $page['page_keywords'] as $page_keyword ) {
					if ( stripos( $page_keyword, $keyword ) !== false ) {
						$score += 3;
					}
				}
			}
		}

		// Boost score for exact title match.
		if ( stripos( $page['page_title'], $query ) !== false ) {
			$score += 20;
		}

		// Boost for order-form type pages when query is about orders.
		if ( 'order-form' === $page['page_type'] && ( stripos( $query, 'سفارش' ) !== false || stripos( $query, 'چاپ' ) !== false ) ) {
			$score += 15;
		}

		return $score;
	}

	/**
	 * Find best matching page for user intent
	 *
	 * @param string $user_query User's query or intent.
	 * @return array|null Best matching page or null if not found.
	 */
	public function find_best_page( $user_query ) {
		$results = $this->smart_search_pages( $user_query, 1 );

		if ( empty( $results ) ) {
			return null;
		}

		return $results[0];
	}

	/**
	 * Get all indexed pages
	 *
	 * Retrieves all indexed pages for AI quick suggestions.
	 *
	 * @param int $limit Maximum number of pages to retrieve.
	 * @return array Array of indexed pages.
	 */
	public function get_all_pages( $limit = 100 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, page_title, page_type, page_content_summary 
				FROM {$table_name} 
				ORDER BY last_scanned DESC 
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get indexed pages by type
	 *
	 * @param string $type  Page type to filter by.
	 * @param int    $limit Maximum number of pages.
	 * @return array Array of indexed pages.
	 */
	public function get_pages_by_type( $type, $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tabesh_ai_site_pages';
		$type       = sanitize_text_field( $type );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, page_title, page_type, page_content_summary 
				FROM {$table_name} 
				WHERE page_type = %s 
				ORDER BY last_scanned DESC 
				LIMIT %d",
				$type,
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get formatted page list for AI context
	 *
	 * Returns a formatted string of all pages for AI consumption.
	 *
	 * @return string Formatted page list.
	 */
	public function get_page_list_for_ai() {
		$pages = $this->get_all_pages( 100 );

		if ( empty( $pages ) ) {
			return '';
		}

		$page_list = "صفحات موجود در سایت:\n\n";

		// Group pages by type.
		$grouped_pages = array();
		foreach ( $pages as $page ) {
			$type = $page['page_type'];
			if ( ! isset( $grouped_pages[ $type ] ) ) {
				$grouped_pages[ $type ] = array();
			}
			$grouped_pages[ $type ][] = $page;
		}

		// Format each group.
		$type_labels = array(
			'order-form' => 'فرم سفارش',
			'cart'       => 'سبد خرید',
			'checkout'   => 'پرداخت',
			'account'    => 'حساب کاربری',
			'about'      => 'درباره ما',
			'contact'    => 'تماس با ما',
			'portfolio'  => 'نمونه کارها',
			'page'       => 'صفحات',
			'blog-post'  => 'مقالات',
			'product'    => 'محصولات',
		);

		foreach ( $grouped_pages as $type => $type_pages ) {
			$type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
			$page_list .= "** {$type_label}:\n";

			foreach ( $type_pages as $page ) {
				$page_list .= sprintf(
					"- %s: %s\n",
					esc_html( $page['page_title'] ),
					esc_url( $page['page_url'] )
				);
			}

			$page_list .= "\n";
		}

		return $page_list;
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
	 *
	 * This method is called by the cron job to automatically index WordPress content.
	 */
	public static function run_scheduled_indexing() {
		$indexer = new self();

		// Use the new WordPress content indexing method.
		$result = $indexer->index_wordpress_content();

		// Log result.
		if ( $result['success'] ) {
			error_log(
				sprintf(
					'[Tabesh AI] WordPress content indexing completed: %d indexed, %d failed out of %d total',
					$result['indexed'],
					$result['failed'],
					$result['total']
				)
			);
		} else {
			error_log( '[Tabesh AI] WordPress content indexing failed: ' . $result['error'] );
		}
	}
}
