<?php
/**
 * React Dashboard Handler
 * Handles enqueuing and rendering of the React admin dashboard
 *
 * @package Tabesh
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tabesh_React_Dashboard
 */
class Tabesh_React_Dashboard {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_react_dashboard' ) );
	}

	/**
	 * Check if we should load the React dashboard
	 *
	 * @return bool
	 */
	private function should_load_react_dashboard() {
		global $post;

		// Check if we're on a page/post with the admin dashboard shortcode.
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'tabesh_admin_dashboard' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue React dashboard assets
	 */
	public function enqueue_react_dashboard() {
		if ( ! $this->should_load_react_dashboard() ) {
			return;
		}

		// Check if user has permission.
		$admin = Tabesh()->admin;
		if ( ! $admin || ! $admin->user_has_admin_dashboard_access( get_current_user_id() ) ) {
			return;
		}

		// Enqueue built React app.
		$dist_path = TABESH_PLUGIN_DIR . 'assets/dist/admin-dashboard/';
		$dist_url  = TABESH_PLUGIN_URL . 'assets/dist/admin-dashboard/';

		// Check if built files exist.
		if ( ! file_exists( $dist_path . 'admin-dashboard.js' ) ) {
			// Development fallback or error message.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Tabesh: React dashboard build files not found. Run: cd assets/react && npm run build' );
			}
			return;
		}

		// Enqueue CSS.
		if ( file_exists( $dist_path . 'admin-dashboard.css' ) ) {
			wp_enqueue_style(
				'tabesh-react-dashboard',
				$dist_url . 'admin-dashboard.css',
				array(),
				TABESH_VERSION
			);
		}

		// Enqueue Dashicons (WordPress icons).
		wp_enqueue_style( 'dashicons' );

		// Enqueue JS.
		wp_enqueue_script(
			'tabesh-react-dashboard',
			$dist_url . 'admin-dashboard.js',
			array(),
			TABESH_VERSION,
			true
		);

		// Pass configuration to React app.
		$this->localize_react_config();
	}

	/**
	 * Pass configuration data to React app via window object
	 */
	private function localize_react_config() {
		$current_user = wp_get_current_user();
		$admin        = Tabesh()->admin;

		$config = array(
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'restUrl'         => rest_url( TABESH_REST_NAMESPACE ),
			'restNamespace'   => TABESH_REST_NAMESPACE,
			'currentUserId'   => $current_user->ID,
			'currentUserRole' => ! empty( $current_user->roles ) ? $current_user->roles[0] : '',
			'isAdmin'         => $admin ? $admin->user_has_admin_dashboard_access( $current_user->ID ) : false,
			'canEditOrders'   => current_user_can( 'edit_shop_orders' ),
			'avatarUrl'       => get_avatar_url( $current_user->ID ),
			'userName'        => $current_user->display_name,
			'userEmail'       => $current_user->user_email,
		);

		// Output inline script before React app loads.
		wp_add_inline_script(
			'tabesh-react-dashboard',
			'window.tabeshConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Render React dashboard root element
	 *
	 * @return string
	 */
	public function render_dashboard() {
		// Check permission.
		$admin = Tabesh()->admin;
		if ( ! $admin || ! $admin->user_has_admin_dashboard_access( get_current_user_id() ) ) {
			return '<div class="tabesh-access-denied">' . esc_html__( 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'tabesh' ) . '</div>';
		}

		// Check if React build exists.
		$dist_path = TABESH_PLUGIN_DIR . 'assets/dist/admin-dashboard/admin-dashboard.js';
		if ( ! file_exists( $dist_path ) ) {
			return '<div class="tabesh-build-error">' .
				esc_html__( 'داشبورد React هنوز ساخته نشده است. ', 'tabesh' ) .
				( defined( 'WP_DEBUG' ) && WP_DEBUG ?
					'<br><code>cd assets/react && npm run build</code>' : '' ) .
				'</div>';
		}

		// Return root div for React to mount.
		return '<div id="tabesh-admin-dashboard-root"></div>';
	}
}
