<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OriginalityAI {

	const ORIGINALITYAI_CONNECTION_OPTION = 'originalityai_connection';

	const ORIGINALITYAI_AI_SCAN_ID_OPTION = 'originalityai_ai_scan_id';

	const ORIGINALITYAI_WEBSITE_ID_OPTION = 'originalityai_website_id';

	const ORIGINALITYAI_ACTIVATION_NOTICE = 'originalityai_activation_notice';

	/**
	 * For getting https://app.originality.ai/home/content-scan/24696157 etc
	 */
	const ORIGINALITYAI_HOME_SCAN_URL = 'https://app.originality.ai/home/content-scan/';

	/**
	 * Cookie expiration for dashboard
	 */
	const COOKIE_EXPIRATION = 10 * 365 * 24 * 60 * 60;

	const BATCH_LIMIT = 2;

	/**
	 * Initializes the OriginalityAI plugin by setting up the necessary actions.
	 *
	 * This method adds the 'admin_menu' and 'init' actions with their corresponding functions,
	 * 'originalityai_settings_menu' and 'handle_disconnection.
	 *
	 * @return void
	 */
	public static function init() {
		// Admin related actions.
		add_action( 'admin_menu', [ self::class, 'originalityai_settings_menu' ] );
		add_action( 'admin_post_originalityai_connect', [ self::class, 'handle_connection' ] );
    	add_action( 'admin_post_originalityai_disconnect', [ self::class, 'handle_disconnection' ] );
    	add_action( 'admin_post_originalityai_save_settings', [ self::class, 'handle_settings_form_submission' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_styles' ] );
		add_action( 'wp_ajax_ai_scan', [ self::class, 'ai_scan_for_post_ajax' ] );
		add_action( 'wp_ajax_ai_scan_result_remove', [ self::class, 'ai_scan_result_remove' ] );
		add_action('admin_notices', [ self::class, 'display_admin_notices' ] );

		// Admin related filters.
		add_filter( 'plugin_action_links_' . ORIGINALITYAI_PLUGIN_BASENAME, [ self::class, 'add_settings_link' ] );
	}

	/**
	 * Handles connection request from the user.
	 *
	 * This method checks if the connection request has been submitted, authenticate the user and connect to his account.
	 *
	 * @return void
	 */
	public static function handle_connection() {
		// Check nonce for security.
		check_admin_referer( 'originalityai_connection_action', 'originalityai_connection_nonce' );

		// Check user permissions.
		if ( ! current_user_can('manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'originality-ai' ) );
		}

		if ( ! isset( $_POST['login'] ) || ! isset( $_POST['password'] ) ) {
			set_transient( 'originalityai_admin_notice_error', 
				__( 'Please fill in all required fields.', 'originality-ai' ), 
				45
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
			exit;
		}

		// Authenticate the user.
		$auth = OriginalityAIAPI::authorize( sanitize_text_field( wp_unslash( $_POST['login'] ) ), sanitize_text_field( wp_unslash( $_POST['password'] ) ) );

		if ( isset( $auth['result'] ) && $auth['result'] === true && is_array( $auth['data'] ) && isset( $auth['data']['apikey'] ) && $auth['data']['apikey'] && isset( $auth['data']['email'] ) && $auth['data']['email'] ) {
			update_option( self::ORIGINALITYAI_CONNECTION_OPTION, $auth['data'] );
			delete_option( self::ORIGINALITYAI_ACTIVATION_NOTICE );

			set_transient( 'originalityai_admin_notice_success', 
				__( 'Connected successfully.', 'originality-ai' ), 
				45
			);
		} else {
			set_transient( 'originalityai_admin_notice_error', 
				__( 'Authorization failed. Please try again.', 'originality-ai' ), 
				45
			);
		}
	
		wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
		exit;
	}

	/**
	 * Handles disconnection request from the user.
	 *
	 * This method checks if the disconnection request has been submitted and sets the appropriate
	 * option value to disconnect the user. It then redirects the user to the OriginalityAI settings page
	 * in the WordPress admin area.
	 *
	 * @return void
	 */
	public static function handle_disconnection() {
		// Check nonce for security.
		check_admin_referer( 'originalityai_connection_action', 'originalityai_connection_nonce' );

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'originality-ai' ) );
		}
	
		delete_option( self::ORIGINALITYAI_CONNECTION_OPTION );
		update_option( self::ORIGINALITYAI_ACTIVATION_NOTICE, true );
		
		set_transient( 'originalityai_admin_notice_success', 
			__( 'Disconnected successfully.', 'originality-ai' ), 
			45
		);
		
		wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
		exit;
	}

	/**
	 * Handles the submission of the OriginalityAI settings form.
	 *
	 * This method checks if the settings form has been submitted and saves the settings to the WordPress options table.
	 *
	 * @return void
	 */
	public static function handle_settings_form_submission() {
		// Check nonce for security.
		check_admin_referer( 'originalityai_save_settings_action', 'originalityai_save_settings_nonce' );

		// Check user permissions.
		if ( ! current_user_can('manage_options' ) ) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'originality-ai'));
		}

		// Check if model_id is not set.
		if ( ! isset( $_POST['model_id'] ) ) {
			set_transient( 'originalityai_admin_notice_error', 
				__( 'Model ID is required.', 'originality-ai' ), 
				45
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
			exit;
		}

		$model_id = (int) $_POST['model_id'];
		if ( ! isset( OriginalityAIAPI::AI_SCAN_MODELS[$model_id] ) ) {
			set_transient( 'originalityai_admin_notice_error', 
				__( 'Invalid AI detection model selected.', 'originality-ai' ), 
				45
			);
			wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
			exit;
		}

		update_option( self::ORIGINALITYAI_AI_SCAN_ID_OPTION, $model_id );

		set_transient( 'originalityai_admin_notice_success', 
			__( 'Settings saved successfully.', 'originality-ai' ), 
			45
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=originalityai' ) );
		exit;
	}

	/**
	 * Displays admin notices.
	 *
	 * This method checks for success and error notices in the WordPress admin area and displays them if they exist.
	 *
	 * @return void
	 */
	public static function display_admin_notices() {
		// Check for success notice.
		$success_notice = get_transient('originalityai_admin_notice_success');

		// Check for success notice.
		if ($success_notice) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html($success_notice)
			);
			delete_transient('originalityai_admin_notice_success');
		}
	
		// Check for error notice.
		$error_notice = get_transient('originalityai_admin_notice_error');
		if ($error_notice) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html($error_notice)
			);
			delete_transient('originalityai_admin_notice_error');
		}
	}

	/**
	 * Returns selected in settings (or default one AI Scan Model)
	 *
	 * @return false|mixed|null
	 */
	public static function get_setting_ai_scan_model() {
		return get_option( self::ORIGINALITYAI_AI_SCAN_ID_OPTION, OriginalityAIAPI::ORIGINALITYAI_AI_SCAN_ID_DEFAULT );
	}

	/**
	 * Adds the OriginalityAI Settings menu page to the WordPress admin menu.
	 *
	 * This method uses the add_options_page function to add a submenu page to the Settings menu
	 * with the title 'OriginalityAI Settings' and the capability 'manage_options'. The menu page
	 * is assigned the slug 'originalityai'
	 * 
	 * @return void
	 */
	public static function originalityai_settings_menu() {
		add_options_page(
			__('Originality.ai Settings', 'originality-ai'),
			__('Originality.ai', 'originality-ai'),
			'manage_options',
			'originalityai',
			[self::class, 'originalityai_settings_page']
		);
	}

	/**
	 * Renders the OriginalityAI settings page.
	 *
	 * This method generates and outputs the HTML for the OriginalityAI settings page. It displays the website ID, connection status, and connection button.
	 * If the connection status is 'Connected', it also includes a hidden input field to indicate that the user wants to disconnect.
	 *
	 * @return void
	 */
	public static function originalityai_settings_page() {
		$connection = self::is_connected() ? 'Connected' : 'Disconnected';
   		require_once __DIR__ . '/inc/settings.php';
	}

	/**
	 * Get account API key.
	 *
	 * @return mixed|null
	 */
	public static function get_account_api_key() {
		$option_data = get_option( self::ORIGINALITYAI_CONNECTION_OPTION );

		return $option_data ? sanitize_text_field( $option_data['apikey'] ) : null;
	}

	/**
	 * Get account name.
	 *
	 * @return mixed|null
	 */
	public static function get_account_name() {
		$option_data = get_option( self::ORIGINALITYAI_CONNECTION_OPTION );

		return $option_data ? sanitize_text_field( $option_data['name'] ) : null;
	}

	/**
	 * Get account email.
	 * 
	 * @return mixed|null
	 */
	public static function get_account_email() {
		$option_data = get_option( self::ORIGINALITYAI_CONNECTION_OPTION );

		return $option_data ? sanitize_email( $option_data['email'] ) : null;
	}

	/**
	 * Gets the unique website ID for the current site.
	 *
	 * This method generates a unique website ID by concatenating the site URL with a generated UUID using md5 hashing algorithm.
	 *
	 * @return string The unique website ID.
	 */
	public static function get_website_id() {
		return get_option( self::ORIGINALITYAI_WEBSITE_ID_OPTION );
	}

	/**
	 * Generate Website ID
	 *
	 * @return string
	 */
	private static function generate_website_id() {
		return md5( get_site_url() . wp_generate_uuid4() );
	}

	/**
	 * Activates the OriginalityAI plugin by checking and setting the necessary options and create the db table.
	 * If the website ID option is empty, it sets the value using the get_website_id() method.
	 *
	 * @return void
	 */
	public static function activate() {
		// Check and create the table.
		OriginalityAILogger::check_and_create_table();

		// If website ID option is empty, set value.
		if ( ! self::get_website_id() ) {
			update_option( self::ORIGINALITYAI_WEBSITE_ID_OPTION, self::generate_website_id() );
		}
		if ( ! self::is_connected() ) {
			update_option( self::ORIGINALITYAI_ACTIVATION_NOTICE, true );
		}
	}

	/**
	 * On uninstall the plugin.
	 *
	 * @return void
	 */
	public static function uninstall() {
		include_once ORIGINALITYAI_ROOT_PATH . 'uninstall.php';
	}

	/**
	 * Checks if the OriginalityAI plugin is connected to the Originality.ai service.
	 *
	 * This method checks if the OriginalityAI plugin is connected to the Originality.ai service by checking the value of the 'originalityai_connection' option.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return (bool) get_option( self::ORIGINALITYAI_CONNECTION_OPTION );
	}

	/**
	 * Enqueues the required styles for the Originality AI Integration plugin.
	 *
	 * This method enqueues the 'originalityai-style' stylesheet to be used by the Originality AI Integration plugin.
	 * The stylesheet is loaded from the 'originalityai-integration/assets/css/style.css' file in the plugin's directory.
	 * The stylesheet is loaded with a version number of '1.0.0' and no dependencies.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		global $pagenow;

		// Check if current page is options-general.php and current GET 'page' parameter is 'originalityai'
		if ( $pagenow == 'options-general.php' && isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'originalityai' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_style( 'originalityai-style', ORIGINALITYAI_ROOT_URL . 'assets/css/style.css', array(), wp_rand() );

			wp_enqueue_style( 'originalityai-vue-style', ORIGINALITYAI_ROOT_URL . 'assets/css/vue-style.css', array(), wp_rand() );
			wp_enqueue_script( 'vue-main', ORIGINALITYAI_ROOT_URL . 'assets/js/vue-main.js', array(), wp_rand(), true );
		}

		// Admin-wise styles
		wp_enqueue_style( 'originalityai-style-wide', ORIGINALITYAI_ROOT_URL . 'assets/css/style-admin-wide.css', array(), wp_rand() );
	}

	/**
	 * Scans the given post for potential AI content.
	 *
	 * This method performs a scan on the post with the specified ID to identify potential AI content.
	 *
	 * @param int $post_id The ID of the post to scan for AI content.
	 *
	 * @return void
	 */
	public static function ai_scan_for_post_ajax( $post_id = null, $silent = false ) {
		// Check nonce for security.
		if ( ! isset( $_POST['scan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scan_nonce'] ) ), 'originality_ai_scan_nonce' ) ) {
			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
			wp_die( esc_html__( 'Security checked failed!', 'originality-ai' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : null;
		$post_id = is_array( $post_id ) ? array_shift( $post_id ) : $post_id;
		$ai_scan_model_id = isset( $_POST['originalityai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['originalityai_model'] ) ) : self::get_setting_ai_scan_model();
		$api_key = self::get_account_api_key();

		// Check API key
		if ( ! $api_key ) {
			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
			wp_send_json_error( [ 'message' => 'API key not found.' ] );
		}

		// Check if post_id is set.
		if ( ! $post_id ) {
			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
			wp_send_json_error( [ 'message' => 'Missing post_id' ] );
		}

		// Check if current user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
			wp_die( esc_html__( 'You do not have permission to edit this post.', 'originality-ai' ) );
		}

		OriginalityAILogger::debug_log( "Started with ID {$post_id}" );

		update_post_meta( $post_id, '_originalityai_scan_status', 'processing' );

		// Get post data
		$post = get_post( $post_id );

		if ( ! $post ) {
			OriginalityAILogger::debug_log( "Post not found" );

			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
			wp_send_json_error( [ 'message' => 'Post not found.' ] );
		}

		OriginalityAILogger::debug_log( "Post found" );

		// Use the post content and title for scanning
		$content = self::get_plain_post_content( $post->post_content );
		$title   = $post->post_title;

		OriginalityAILogger::debug_log( "Using model ID {$ai_scan_model_id}" );

		// Run a scan using OriginalityAIAPI class
		$result = OriginalityAIAPI::scan_ai_content( $api_key, $content, $title, $ai_scan_model_id );

		OriginalityAILogger::debug_log( "Request sent" );

		$data = isset( $result['data'] ) ? $result['data'] : [];

		OriginalityAILogger::debug_log( "Data received: " . wp_json_encode( $data ) );

		// Check the result
		if ( $result['result'] === true && isset( $data['success'] ) && $data['success'] == 1 ) {
			// Prepare data for DB Log
			$id               = $data['id'];
			$post_id          = $post_id;
			$credits_used     = $data['credits_used'];
			$credits          = $data['credits'];
			$ai_model_version = $data['aiModelVersion'];
			$score_original   = $data['score']['original'];
			$score_ai         = $data['score']['ai'];
			$public_link      = $data['public_link'];

			$title       = get_the_title( $post_id );
			$words_count = str_word_count( $content );

			// Write to DB
			OriginalityAILogger::db_log( compact( 'id', 'post_id', 'credits_used', 'credits', 'ai_model_version', 'score_original', 'score_ai', 'public_link', 'title', 'words_count' ) );

			update_post_meta( $post_id, '_originalityai_scan_status', 'completed' );

			wp_send_json_success( [ 'message' => 'success', 'raw' => $data ] );
		} else {
			update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );

			// If the scan failed, send an error JSON
			wp_send_json_error( [ 'message' => "Error: " . ( isset( $data['error'] ) ? $data['error'] : 'unknown error' ), 'raw' => $data ] );
		}
	}

	/**
	 * Scans the given post for potential AI content.
	 *
	 * This method performs a scan on the post with the specified ID to identify potential AI content.
	 *
	 * @param int $post_id The ID of the post to scan for AI content.
	 * @param WP_Post $post The post object to scan for AI content.
	 *
	 * @return bool True if the scan was successful, false otherwise.
	 */
	public static function run_scan_per_post( $post_id, $post ) {
		$scan_model = self::get_setting_ai_scan_model();
		$api_key = self::get_account_api_key();

		if ( ! $api_key || ! $scan_model || ! $post_id || ! $post ) {
			return false;
		}

		OriginalityAILogger::debug_log( "Bulk scan started with ID {$post_id}" );

		// Use the post content and title for scanning
		$content = self::get_plain_post_content( $post->post_content );
		$title   = $post->post_title;

		OriginalityAILogger::debug_log( "Using model ID {$scan_model}" );

		// Run a scan using OriginalityAIAPI class
		$result = OriginalityAIAPI::scan_ai_content( $api_key, $content, $title, $scan_model );

		OriginalityAILogger::debug_log( "Request sent" );

		$data = isset( $result['data'] ) ? $result['data'] : [];

		OriginalityAILogger::debug_log( "Data received: " . wp_json_encode( $data ) );

		// Check the result
		if ( $result['result'] === true && isset( $data['success'] ) && $data['success'] == 1 ) {
			// Prepare data for DB Log
			$id               = $data['id'];
			$post_id          = $post_id;
			$credits_used     = $data['credits_used'];
			$credits          = $data['credits'];
			$ai_model_version = $data['aiModelVersion'];
			$score_original   = $data['score']['original'];
			$score_ai         = $data['score']['ai'];
			$public_link      = $data['public_link'];

			$title       = get_the_title( $post_id );
			$words_count = str_word_count( $content );

			// Write to DB
			OriginalityAILogger::db_log( compact( 'id', 'post_id', 'credits_used', 'credits', 'ai_model_version', 'score_original', 'score_ai', 'public_link', 'title', 'words_count' ) );

			return true;
		}

		return false;
	}

	/**
	 * Remove scan result.
	 *
	 * This method handles the removal of a scan result from the database.
	 *
	 * @since 1.0.12
	 *
	 * @return void
	 */
	public static function ai_scan_result_remove() {
		// Verify nonce for security.
		check_ajax_referer( 'originalityai_delete_scan_nonce', 'nonce' );
	
		// Validate and sanitize scan ID.
		$scan_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	
		if ( $scan_id <= 0 ) {
			wp_send_json_error( ['message' => esc_html__( 'Invalid scan ID.', 'originality-ai' ) ], 400 );
		}
	
		global $wpdb;
		$table_name = $wpdb->prefix . 'originalityai_log';
	
		// Check if the scan result exists.
		$scan_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table_name` WHERE `id` = %d", $scan_id), ARRAY_A);
	
		if ( ! $scan_entry ) {
			wp_send_json_error( ['message' => esc_html__( 'Scan result not found.', 'originality-ai' )], 404 );
		}
	
		// Delete the scan result.
		$deleted = $wpdb->delete( $table_name, ['id' => $scan_id], ['%d'] );
	
		if ( $deleted ) {
			// Clear cache for related post ID.
			wp_cache_delete('latest_log_post_' . $scan_entry['post_id'], 'originalityai');
	
			wp_send_json_success( ['message' => esc_html( 'Scan result removed successfully.', 'originality-ai' )] );
		} else {
			wp_send_json_error( ['message' => esc_html__( 'Failed to remove scan result.', 'originality-ai' )], 500);
		}
	}

	/**
	 * Removes shortcodes, Gutenberg block comments, and HTML tags from post content.
	 *
	 * @param string $content - The post content.
	 *
	 * @return string - The cleaned post content.
	 */
	public static function get_plain_post_content( $content ) {
		// Remove any shortcodes
		$content_without_shortcodes = strip_shortcodes( $content );

		// Remove Gutenberg block comments
		$content_without_gutenberg_comments = preg_replace( '/<!--(.|\s)*?-->/', '', $content_without_shortcodes );

		// Remove any remaining HTML tags
		return wp_strip_all_tags( $content_without_gutenberg_comments );
	}

	/**
	 * Add a Settings link to the plugin's entry on the Plugins admin page.
	 *
	 * @param array $links An array of plugin action links.
	 *
	 * @return array The modified array of plugin action links.
	 */
	public static function add_settings_link( $links ) {
		// Build the URL to your plugin settings page.
		$settings_url = admin_url( 'options-general.php?page=originalityai' );

		// Create the "Settings" link.
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'originality-ai' ) . '</a>';

		// Add the Settings link to the start of the $links array.
		array_unshift( $links, $settings_link );

		return $links;
	}

}