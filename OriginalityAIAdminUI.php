<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OriginalityAIAdminUI extends OriginalityAI {

	/**
	 * Allowed for <svg>, for wp_kses.
	 */
	public static $allowed_svg_tags = array(
		'svg'      => array(
			'xmlns'               => true,
			'version'             => true,
			'width'               => true,
			'height'              => true,
			'viewbox'             => true,
			'preserveaspectratio' => true,
			'xmlns:xlink'         => true,
			'xmlns:svg'           => true
		),
		'g'        => array( 'fill' => true ),
		'title'    => array(),
		'desc'     => array(),
		'defs'     => array(),
		'path'     => array(
			'd'    => true,
			'fill' => true,
		),
		'circle'   => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
		'line'     => array(
			'x1' => true,
			'y1' => true,
			'x2' => true,
			'y2' => true,
		),
		'rect'     => array(
			'width'  => true,
			'height' => true,
			'x'      => true,
			'y'      => true,
		),
		'ellipse'  => array(
			'cx' => true,
			'cy' => true,
			'rx' => true,
			'ry' => true,
		),
		'polygon'  => array(
			'points' => true,
		),
		'polyline' => array(
			'points' => true,
		),
	);

	/**
	 * Temporary state flag
	 *
	 * @var int
	 */
	protected static $flag = 0;

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ self::class, 'dashboard_init' ] );
		add_action( 'admin_notices', [ self::class, 'show_activation_notice' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_scripts' ] );
		add_action( 'wp_ajax_dismiss_originalityai_notice', [ self::class, 'dismiss_activation_notice' ] );

		// Only if user has plugin connected
		if ( OriginalityAI::is_connected() ) {
			// Register bulk actions for posts and pages
			add_action( 'admin_init', [ self::class, 'register_my_bulk_actions' ] );
			add_action( 'admin_enqueue_scripts', [ self::class, 'wpb_hook_javascript' ] );
			add_action( 'admin_notices', [ self::class, 'bulk_actions_admin_notice' ] );

			add_action( 'ai_scan_batch_event', [ self::class, 'handle_batch_event' ] );
			add_action( 'wp_ajax_bulk_scan_progress', [ self::class, 'bulk_scan_progress_callback' ] );

			add_action( 'wp_ajax_get_latest_scan_results', [ self::class, 'ai_get_latest_scan_results_ajax' ] );
			add_action( 'wp_ajax_ai_get_table_data', [ self::class, 'ai_get_table_data' ] );

			add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_scripts' ] );

			$post_types = get_post_types( [ 'public' => true ], 'names' );
			foreach ( $post_types as $post_type ) {
				add_filter( "manage_{$post_type}_posts_columns", [ self::class, 'add_new_column' ] );
				add_action( "manage_{$post_type}_posts_custom_column", [ self::class, 'add_new_columns_content' ], 10, 2 );
			}

			add_action( 'manage_posts_extra_tablenav', [ self::class, 'add_scan_all_button_posts_extra_tablenav' ], 10, 1 );
			add_action( 'admin_init', [ self::class, 'handle_scan_all_posts_action' ] );

			foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
				add_filter( "{$taxonomy}_row_actions", [ self::class, 'add_scan_all_button_to_taxonomy_term' ], 10, 2 );
			}

			add_action( 'admin_init', [ self::class, 'execute_scan_on_taxonomy_posts' ] );
		}
	}

	/**
	 * Enqueue global admin script for dismissing the notice.
	 *
	 * @return void
	 */
	public static function enqueue_admin_scripts() {
		// Enqueue script only on admin pages.
		wp_enqueue_script(
			'originalityai-dismiss-notice',
			ORIGINALITYAI_ROOT_URL . 'assets/js/originalityai-dismiss-notice.js',
			[ 'jquery' ],
			'1.0',
			true
		);

		// Pass data to JavaScript.
		wp_localize_script( 'originalityai-dismiss-notice', 'originalityAIDismiss', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dismiss_notice' ),
		] );
	}

	/**
	 * Get the latest scan results for a post.
	 *
	 * @return void
	 */
	public static function ai_get_latest_scan_results_ajax() {
		// Verify nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'originality_ai_nonce' ) ) {
			wp_send_json_error( ['message' => 'Invalid nonce.'] );
		}

		// Verify post_id.
		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing post_id' ] );
		}

		$post_id = (int) $_POST['post_id'];

		// Check if current user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this post.', 'originality-ai' ) );
		}

		$record  = OriginalityAILogger::get_latest_by_post_id( $post_id );

		$status = get_post_meta( $post_id, '_originalityai_scan_status', true );

		ob_start();

		if ( $record && isset( $record['score_original'] ) && 'processing' !== $status ) {
			list( $score_original, $score_ai ) = self::calculate_int_scores( $record['score_original'] );

			$color_mapping_item           = self::get_color_mapping_item( $record['score_ai'], $record['score_original'] );
			$record['color_mapping_item'] = $color_mapping_item;
			$record['percentage']         = max( $score_original, $score_ai );

			$svg_icon_info = self::get_svg_icon( 'info', $record['color_mapping_item']['color'] );
			$current_model_name = OriginalityAIAPI::AI_SCAN_MODELS[ $record['ai_model_version'] ];
			$svg_icon_info_sm   = self::get_svg_icon( 'info_sm', '#5f5f5f' );

			$dateTimeString = $record['request_timestamp'];
			$dateTime       = new DateTime( $dateTimeString );
			$formattedDate  = $dateTime->format( 'M j, Y' );

			include __DIR__ . '/inc/sidebar_scan_result.php';
		} else {
			// If no results are found, send error JSON
			wp_send_json_error( [ 'message' => 'No scan results found.', 'models' => OriginalityAIAPI::AI_SCAN_MODELS, 'current_model_id' => OriginalityAI::get_setting_ai_scan_model() ] );
		}

		if ( 'processing' === $status  ) {
			//   Say that scan is in progress
			echo esc_html__( 'AI Scan in progress...', 'originality-ai' ) . "<br>";
		} elseif ( '' === $status ) {
			echo '<span id="originality_ai_scan_not_started_' . esc_attr( $post_id ) . '"><strong>AI Scan:</strong> not started<br></span>';
		}

		$html = ob_get_clean();

		$record['html']   = $html;
		$record['models'] = OriginalityAIAPI::AI_SCAN_MODELS;

		// If results are found, send success JSON
		wp_send_json_success( $record );
	}

	/**
	 * Dashboard init.
	 *
	 * @return void
	 */
	public static function dashboard_init() {
		$connection = self::is_connected() ? 'Connected' : 'Disconnected';

		if ( isset( $connection ) && $connection !== 'Connected' ) {
			setcookie( 'show_vue', 'false', time() + OriginalityAI::COOKIE_EXPIRATION, "/" );
		} else {
			setcookie( 'show_vue', 'true', time() + OriginalityAI::COOKIE_EXPIRATION, "/" );
		}
	}

	/**
	 * Editor page assists including ApexCharts
	 *
	 * @return void
	 */
	public static function enqueue_editor_scripts() {
		// JS
		wp_enqueue_script( 'originality-ai--sidebar-js', ORIGINALITYAI_ROOT_URL . 'assets/js/sidebar.js', // Your sidebar script file path
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ), // Dependencies
			filemtime( ORIGINALITYAI_ROOT_PATH . 'assets/js/sidebar.js' ), // File modification time for cache busting
			true
		);

		// CSS
		wp_enqueue_style( 'originality-ai--sidebar-style', ORIGINALITYAI_ROOT_URL . 'assets/css/sidebar.css', array(), filemtime( ORIGINALITYAI_ROOT_PATH . 'assets/css/sidebar.css' ) );

		$plugin_img_src = esc_url( self::get_logo_url() );

		wp_localize_script( 'originality-ai--sidebar-js', 'originalityAISidebar', array(
			'originality_ai__logo_img' => $plugin_img_src,
			'nonce' => wp_create_nonce( 'originality_ai_nonce' ),
			'scan_nonce' => wp_create_nonce( 'originality_ai_scan_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ), 
		) );

		// ApexCharts
		wp_enqueue_script( 'originality-ai--apexcharts', ORIGINALITYAI_ROOT_URL . 'assets/js/apexcharts.min.js', array(), ORIGINALITYAI_PLUGIN_VERSION, true );
	}

	/**
	 * Registers bulk actions for all public post types.
	 *
	 * @return void
	 */
	public static function register_my_bulk_actions() {
		$post_types = get_post_types( ['public' => true] );
		foreach ( $post_types as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, [self::class, 'add_bulk_action'], 10, 1 );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, [self::class, 'bulk_actions_handler'], 10, 3 );
		}
	}

	/**
	 * Hook JavaScript code that sets the 'dc_ajax' object with the URL for admin-ajax.php.
	 *
	 * @return void
	 */
	public static function wpb_hook_javascript() {
		wp_register_script( 'oai_admin_script', ORIGINALITYAI_ROOT_URL . 'assets/js/oai_admin_script.js', array('jquery'), ORIGINALITYAI_PLUGIN_VERSION, false );
		wp_enqueue_script( 'oai_admin_script' );

		$localize_data = array(
			'ajaxUrl' => admin_url("admin-ajax.php"),
			'adminUrl' => admin_url("edit.php"),
			'scansTitle' => 'Recent Content Scans',
			'nonce' => wp_create_nonce( 'originalityai_delete_scan_nonce' ),
		);

		wp_localize_script( 'oai_admin_script', 'oaiAjaxObject', $localize_data );

		// Generate and verify nonce for bulk scan.
		if ( ! empty( $_REQUEST['bulk_ai_scans'] ) && check_admin_referer( 'bulk_scan_nonce', 'bulk_scan_nonce' ) ) {
			$total_posts = intval( $_REQUEST['bulk_ai_scans'] );
		} else {
			$total_posts = 0;
		}

		wp_enqueue_script(
			'bulk-scan-progress',
			ORIGINALITYAI_ROOT_URL . 'assets/js/bulk-scan-progress.js',
			[ 'jquery' ],
			'1.0',
			true
		);

		wp_localize_script( 'bulk-scan-progress', 'bulkScanData', [
			'ajaxurl'   => admin_url( 'admin-ajax.php' ),
			'scanNonce' => wp_create_nonce( 'bulk_scan_nonce' ),
			'totalPosts' => $total_posts,
		] );
	}

	/**
	 * Add new column to posts list screen.
	 *
	 * @param $columns The columns array.
	 *
	 * @return mixed
	 */
	public static function add_new_column( $columns ) {
		// Add a script for the AJAX function
		self::enqueue_scan_script();

		$svg_icon_logo = self::get_svg_icon( 'logo', '#303030' );

		$originalityai_details_col = '
	<div style="display: flex; flex-wrap: nowrap; align-items: center; column-gap: 0.25rem;">
		<div class="flex-shrink-0">' . $svg_icon_logo . '</div>
		<div>Originality.ai score</div>
	</div>';

		$columns['originalityai'] = $originalityai_details_col;
		$columns['originalityai-refresh'] = '';
		$columns['originalityai-share']   = '';

		return $columns;
	}

	/**
	 * Adds new columns content.
	 *
	 * @param $column The column name.
	 * @param $post_id The post ID.
	 *
	 * @return void
	 */
	public static function add_new_columns_content( $column, $post_id ) {
		$status = get_post_meta( $post_id, '_originalityai_scan_status', true );

		if ( 'originalityai' === $column ) {
			$record = \OriginalityAILogger::get_latest_by_post_id( $post_id );

			if ( $record && isset( $record['score_original'] ) && 'processing' !== $status ) {
				list( $score_original, $score_ai ) = self::calculate_int_scores( $record['score_original'] );

				$color_mapping_item = self::get_color_mapping_item( $record['score_ai'], $record['score_original'] );

				echo "<span id='col-originalityai--" . esc_attr( $post_id ) . "'>
						<span style='color: " . esc_attr( $color_mapping_item['color'] ) . ";'>
							" . esc_html( $color_mapping_item['label'] ) . " <a target='_blank' href='https://app.originality.ai/home/content-scan/" . esc_attr( $record['id'] ) . "'>(View Scan)</a><br>
							" . (int) max( $score_ai, $score_original ) . esc_html( '%' ) . " confidence
						</span>
					  </span>";

			} elseif ( 'processing' === $status ) {
				echo esc_html__( 'AI Scan in progress...', 'originality-ai' ) . "<br>";
			} elseif ( '' === $status || ( ! $record ) ) {
				echo '<span id="originality_ai_scan_not_started_' . esc_attr( $post_id ) . '"><strong>AI Scan:</strong> not started<br></span>';
				echo '<a href="javascript:void(0)" id="start-scan-' . esc_attr( $post_id ) . '" class="start-scan-link" data-post-id="' . esc_attr( $post_id ) . '">Start New Scan</a>';
			}
		}

		if ( 'originalityai-refresh' === $column ) {
			$record    = \OriginalityAILogger::get_latest_by_post_id( $post_id );

			if ( $record && isset( $record['score_original'] ) && 'processing' !== $status ) {
				$svg_icon_refresh = self::get_svg_icon( 'refresh' );
				echo '<a class="text-nowrap refresh-scan-link" data-originalityai-tooltip="Refresh to scan again" href="javascript:void(0)" id="refresh-scan-' . esc_attr( $post_id ) . '" data-post-id="' . esc_attr( $post_id ) . '">' . wp_kses( $svg_icon_refresh, self::$allowed_svg_tags ) . '</a>';
			}

			if ( '' === $status || 'processing' === $status || ( ! $record ) ) {
				$rotate_class = 'processing' === $status ? 'originalityai-rotate-child-img' : '';
				$svg_icon_refresh = self::get_svg_icon( 'refresh', '#a7aaad' );
				echo '<a class="text-nowrap refresh-scan-link '. esc_attr( $rotate_class ) . '" data-originalityai-tooltip="Refresh to scan again" href="javascript:void(0)" id="refresh-scan-' . esc_attr( $post_id ) . '" data-post-id="' . esc_attr( $post_id ) . '" style="pointer-events: none; cursor: default;">' . wp_kses( $svg_icon_refresh, self::$allowed_svg_tags ) . '</a>';
			}
		}

		if ( 'originalityai-share' === $column ) {
			$record    = \OriginalityAILogger::get_latest_by_post_id( $post_id );

			if ( $record && isset( $record['score_original'] ) && 'processing' !== $status ) {
				$svg_icon_share = self::get_svg_icon( 'share' );
				echo '<a class="text-nowrap" data-originalityai-tooltip="Share scan results" href="' . esc_attr( $record['public_link'] ) . '" target="_blank">' . wp_kses( $svg_icon_share, self::$allowed_svg_tags ) . '</a>';
			}

			if ( '' === $status || 'processing' === $status || ( ! $record ) ) {
				$svg_icon_share = self::get_svg_icon( 'share', '#a7aaad' );
				echo '<a class="text-nowrap" data-originalityai-tooltip="Share scan results" style="pointer-events: none;" href="#">' . wp_kses( $svg_icon_share, self::$allowed_svg_tags ) . '</a>';
			}
		}
	}

	/**
	 * Enqueue scan script.
	 *
	 * @return void
	 */
	public static function enqueue_scan_script() {
		wp_enqueue_script( 'ai-scan-script', ORIGINALITYAI_ROOT_URL . 'assets/js/ai-scan.js', ['jquery'], ORIGINALITYAI_PLUGIN_VERSION, true );
		wp_localize_script( 'ai-scan-script', 'aiScanData', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'originality_ai_scan_nonce' ),
		] );
	}

	/**
	 * Get icon SVG.
	 *
	 * @param string $name Icon name.
	 * @param string $color Icon color.
	 *
	 * @return string
	 */
	public static function get_svg_icon( $name = 'logo', $color = '#156FB9' ) {
		$img = '';
		if ( $name === 'logo' ) {
			$img = '<svg style="display: block; position: relative; top: .5px;" width="20" height="16" viewBox="0 0 20 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M15.7056 10.6154C15.5823 10.4478 15.6734 10.2827 15.7344 10.2212C15.8124 10.1589 16.0179 10.0064 16.1699 9.85353C16.437 9.58497 16.5024 9.53354 16.8376 9.01925C17.2386 8.40402 17.4067 7.44784 17.154 6.65356C16.9206 5.91982 16.5705 5.305 15.6892 4.55643C14.821 3.81906 13.7313 3.28025 12.2664 2.94311C10.8016 2.60597 8.98955 2.5755 7.51444 2.88597C6.03934 3.19644 5.20138 3.58218 4.4122 4.12596C3.58015 4.69929 3.0542 5.38309 2.79739 6.21166C2.58022 6.91231 2.8015 7.37355 3.02749 7.66498C3.26678 7.97355 3.82052 8.2469 4.79639 8.05355C5.8154 7.85164 6.58583 7.39831 7.49595 7.09355C8.40608 6.78879 9.14775 6.68403 10.0455 6.68975C10.9433 6.69546 11.6398 6.92403 11.8617 7.28022C12.0836 7.63641 12.0014 7.93355 11.7487 8.2345C11.496 8.53545 10.929 8.61926 10.5345 8.61164C10.1401 8.60402 9.61822 8.56973 9.12309 8.65354C8.62797 8.73735 8.08148 8.94306 8.07737 9.21925C8.07326 9.49544 8.24173 9.57163 8.91559 9.67639C9.58946 9.78115 10.8201 9.65734 11.4714 9.46877C12.1226 9.2802 13.0882 8.86306 13.1745 7.98307C13.2608 7.10308 12.7205 6.68022 12.3096 6.43832C11.8987 6.19642 11.1734 5.92975 10.0702 5.89737C8.96695 5.86499 8.43485 5.94118 7.69524 6.10689C6.95563 6.27261 6.0907 6.67641 5.89758 6.7526C5.70446 6.82879 5.3778 6.94689 5.15797 6.97927C4.93815 7.01165 4.36495 7.13736 4.07938 6.6288C3.79381 6.12023 4.22936 5.53357 4.77995 5.15833C5.33055 4.7831 6.09686 4.36405 7.02137 4.12596C7.94588 3.88787 9.2402 3.61739 11.12 3.82882C12.9999 4.04025 14.401 4.84405 15.04 5.43262C15.6789 6.02118 15.8864 6.44213 15.983 6.72022C16.0795 6.99832 16.2377 7.5126 16.0549 8.08974C15.872 8.66687 15.5515 9.00592 15.3194 9.30687C15.0872 9.60782 14.9208 9.79449 14.9763 10.3831C15.0317 10.9716 15.722 11.8973 16.0672 12.4973C16.4123 13.0973 16.4226 13.5506 15.9706 13.8649C15.5186 14.1792 14.7277 13.8878 14.3188 13.5735C13.91 13.2592 13.1807 12.6021 12.4102 12.0954C11.6398 11.5888 10.6372 11.5849 10.2489 11.5945C9.86065 11.604 8.79849 11.7602 7.73016 11.4859C6.66184 11.2116 6.16877 10.7392 5.92224 10.2288C5.6757 9.7183 5.76404 9.26687 5.82157 8.98497C5.87909 8.70306 6.15028 8.72211 6.21397 8.72783C6.27766 8.73354 6.56939 8.80211 6.51803 9.09925C6.46667 9.39639 6.39682 9.74496 6.70088 10.1564C7.00494 10.5678 7.55759 10.8116 8.08353 10.905C8.60948 10.9983 9.03064 11.004 9.66136 10.9735C10.2921 10.943 11.1488 10.8764 11.9233 11.1507C12.6979 11.425 13.0471 11.6726 13.4375 12.0002C13.8278 12.3278 13.9162 12.404 14.4483 12.8154C14.9804 13.2268 15.3995 13.4478 15.5433 13.2687C15.6871 13.0897 15.4262 12.7164 15.3707 12.6211C15.3153 12.5259 15.2783 12.524 14.9208 11.9659C14.5633 11.4078 14.2305 10.804 14.2531 10.124C14.2757 9.44401 14.701 8.98687 14.7996 8.86306C14.8785 8.76402 14.9708 8.64529 15.0071 8.5983L15.153 8.38878C15.2584 8.24148 15.4578 7.83831 15.4118 7.40403C15.3543 6.86117 15.1201 6.37737 14.325 5.72595C13.5299 5.07452 12.2192 4.63072 11.1981 4.5031C10.177 4.37548 8.91765 4.34501 7.61717 4.6631C6.31669 4.98119 5.70241 5.37928 5.43327 5.53357C5.16414 5.68785 4.66079 6.08975 4.81899 6.25927C4.97718 6.4288 5.26481 6.27451 5.77021 6.07261C6.2756 5.87071 6.781 5.49166 8.35678 5.29928C9.93255 5.1069 12.3548 5.2269 13.308 6.35451C14.2613 7.48212 13.9696 8.42116 13.532 8.96021C13.0944 9.49925 12.3774 9.9583 11.3419 10.1792C10.3065 10.4002 9.32032 10.4116 8.52113 10.2707C7.72195 10.1297 7.41172 9.71829 7.37063 9.3602C7.32954 9.00211 7.50006 8.54307 8.24173 8.21926C8.98339 7.89545 9.80929 7.95069 10.4688 7.95831C11.1283 7.96593 11.2556 7.81164 11.2495 7.69164C11.2433 7.57165 11.0646 7.37927 10.3702 7.3545C9.67574 7.32974 9.27512 7.34879 8.77794 7.44784C8.28076 7.54688 8.12873 7.5526 7.34187 7.86498C6.55501 8.17735 6.16055 8.35069 5.6983 8.50307C5.23604 8.65545 4.48616 8.84402 3.71779 8.73925C2.94942 8.63449 2.60017 8.3164 2.32897 7.90307C2.12648 7.59445 1.81125 7.05545 2.14818 5.93166C2.48511 4.80786 3.49797 3.81496 4.50876 3.2593C5.61407 2.65169 6.79538 2.19395 8.86217 2.03645C10.5119 1.91074 11.8062 2.12407 13.0615 2.46312C14.3168 2.80216 16.0138 3.71644 16.9424 4.84405C17.8652 5.96461 17.9501 6.94221 17.9713 7.18618L17.9717 7.1907C17.9922 7.42688 18.1073 8.00212 17.6594 8.9564C17.3011 9.71982 16.765 10.2612 16.5418 10.4364L16.361 10.5907C16.3267 10.6192 16.2406 10.6867 16.1699 10.7278C16.0816 10.7792 15.8597 10.825 15.7056 10.6154Z" fill="' . esc_attr( $color ) . '"/> </svg>';
		}
		if ( $name === 'refresh' ) {
			$img = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.00032 13.3334C6.51143 13.3334 5.25032 12.8167 4.21699 11.7834C3.18366 10.7501 2.66699 9.48897 2.66699 8.00008C2.66699 6.51119 3.18366 5.25008 4.21699 4.21675C5.25033 3.18342 6.51144 2.66675 8.00033 2.66675C8.76699 2.66675 9.50033 2.82508 10.2003 3.14175C10.9003 3.45842 11.5003 3.91119 12.0003 4.50008L12.0003 3.33342C12.0003 3.14453 12.0642 2.98619 12.192 2.85842C12.3198 2.73064 12.4781 2.66675 12.667 2.66675C12.8559 2.66675 13.0142 2.73064 13.142 2.85842C13.2698 2.98619 13.3337 3.14453 13.3337 3.33342L13.3337 6.66675C13.3337 6.85564 13.2698 7.01397 13.142 7.14175C13.0142 7.26953 12.8559 7.33342 12.667 7.33342L9.33366 7.33342C9.14477 7.33342 8.98644 7.26953 8.85866 7.14175C8.73088 7.01397 8.66699 6.85564 8.66699 6.66675C8.66699 6.47786 8.73088 6.31953 8.85866 6.19175C8.98644 6.06397 9.14477 6.00008 9.33366 6.00008L11.467 6.00008C11.1114 5.37786 10.6253 4.88897 10.0087 4.53342C9.39199 4.17786 8.72255 4.00008 8.00032 4.00008C6.88921 4.00008 5.94477 4.38897 5.16699 5.16675C4.38921 5.94453 4.00032 6.88897 4.00032 8.00008C4.00032 9.11119 4.38921 10.0556 5.16699 10.8334C5.94477 11.6112 6.88921 12.0001 8.00032 12.0001C8.75588 12.0001 9.44755 11.8084 10.0753 11.4251C10.7031 11.0417 11.1892 10.5279 11.5337 9.88342C11.6225 9.72786 11.7475 9.61953 11.9087 9.55842C12.0698 9.4973 12.2337 9.49453 12.4003 9.55008C12.5781 9.60564 12.7059 9.72231 12.7837 9.90008C12.8614 10.0779 12.8559 10.2445 12.767 10.4001C12.3114 11.289 11.6614 12.0001 10.817 12.5334C9.97255 13.0667 9.03366 13.3334 8.00032 13.3334Z" fill="' . esc_attr( $color ) . '"/></svg>';
		}
		if ( $name === 'share' ) {
			$img = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.333 14.6666C10.7775 14.6666 10.3052 14.4721 9.91634 14.0833C9.52745 13.6944 9.33301 13.2221 9.33301 12.6666C9.33301 12.5999 9.34967 12.4444 9.38301 12.1999L4.69967 9.46659C4.5219 9.63325 4.31634 9.76381 4.08301 9.85825C3.84967 9.9527 3.59967 9.99992 3.33301 9.99992C2.77745 9.99992 2.30523 9.80547 1.91634 9.41659C1.52745 9.0277 1.33301 8.55547 1.33301 7.99992C1.33301 7.44436 1.52745 6.97214 1.91634 6.58325C2.30523 6.19436 2.77745 5.99992 3.33301 5.99992C3.59967 5.99992 3.84967 6.04714 4.08301 6.14159C4.31634 6.23603 4.5219 6.36659 4.69967 6.53325L9.38301 3.79992C9.36079 3.72214 9.3469 3.64714 9.34134 3.57492C9.33579 3.5027 9.33301 3.42214 9.33301 3.33325C9.33301 2.7777 9.52745 2.30548 9.91634 1.91659C10.3052 1.5277 10.7775 1.33325 11.333 1.33325C11.8886 1.33325 12.3608 1.5277 12.7497 1.91659C13.1386 2.30548 13.333 2.7777 13.333 3.33325C13.333 3.88881 13.1386 4.36103 12.7497 4.74992C12.3608 5.13881 11.8886 5.33325 11.333 5.33325C11.0663 5.33325 10.8163 5.28603 10.583 5.19159C10.3497 5.09714 10.1441 4.96659 9.96634 4.79992L5.28301 7.53325C5.30523 7.61103 5.31912 7.68603 5.32467 7.75825C5.33023 7.83048 5.33301 7.91103 5.33301 7.99992C5.33301 8.08881 5.33023 8.16936 5.32467 8.24159C5.31912 8.31381 5.30523 8.38881 5.28301 8.46659L9.96634 11.1999C10.1441 11.0333 10.3497 10.9027 10.583 10.8083C10.8163 10.7138 11.0663 10.6666 11.333 10.6666C11.8886 10.6666 12.3608 10.861 12.7497 11.2499C13.1386 11.6388 13.333 12.111 13.333 12.6666C13.333 13.2221 13.1386 13.6944 12.7497 14.0833C12.3608 14.4721 11.8886 14.6666 11.333 14.6666ZM11.333 13.3333C11.5219 13.3333 11.6802 13.2694 11.808 13.1416C11.9358 13.0138 11.9997 12.8555 11.9997 12.6666C11.9997 12.4777 11.9358 12.3194 11.808 12.1916C11.6802 12.0638 11.5219 11.9999 11.333 11.9999C11.1441 11.9999 10.9858 12.0638 10.858 12.1916C10.7302 12.3194 10.6663 12.4777 10.6663 12.6666C10.6663 12.8555 10.7302 13.0138 10.858 13.1416C10.9858 13.2694 11.1441 13.3333 11.333 13.3333ZM3.33301 8.66659C3.5219 8.66659 3.68023 8.6027 3.80801 8.47492C3.93578 8.34714 3.99967 8.18881 3.99967 7.99992C3.99967 7.81103 3.93578 7.6527 3.80801 7.52492C3.68023 7.39714 3.5219 7.33325 3.33301 7.33325C3.14412 7.33325 2.98578 7.39714 2.85801 7.52492C2.73023 7.6527 2.66634 7.81103 2.66634 7.99992C2.66634 8.18881 2.73023 8.34714 2.85801 8.47492C2.98578 8.6027 3.14412 8.66659 3.33301 8.66659ZM11.333 3.99992C11.5219 3.99992 11.6802 3.93603 11.808 3.80825C11.9358 3.68048 11.9997 3.52214 11.9997 3.33325C11.9997 3.14437 11.9358 2.98603 11.808 2.85825C11.6802 2.73048 11.5219 2.66659 11.333 2.66659C11.1441 2.66659 10.9858 2.73048 10.858 2.85825C10.7302 2.98603 10.6663 3.14436 10.6663 3.33325C10.6663 3.52214 10.7302 3.68048 10.858 3.80825C10.9858 3.93603 11.1441 3.99992 11.333 3.99992Z" fill="' . esc_attr( $color ) . '"/></svg>';
		}
		if ( $name === 'info' ) {
			$img = '<svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.50016 11.3335C8.68905 11.3335 8.84738 11.2696 8.97516 11.1418C9.10294 11.0141 9.16683 10.8557 9.16683 10.6668L9.16683 8.00016C9.16683 7.81128 9.10294 7.65294 8.97516 7.52516C8.84738 7.39739 8.68905 7.3335 8.50016 7.3335C8.31127 7.3335 8.15294 7.39739 8.02516 7.52516C7.89738 7.65294 7.8335 7.81128 7.8335 8.00016L7.83349 10.6668C7.83349 10.8557 7.89738 11.0141 8.02516 11.1418C8.15294 11.2696 8.31127 11.3335 8.50016 11.3335ZM8.50016 6.00016C8.68905 6.00016 8.84738 5.93628 8.97516 5.8085C9.10294 5.68072 9.16683 5.52239 9.16683 5.3335C9.16683 5.14461 9.10294 4.98628 8.97516 4.8585C8.84738 4.73072 8.68905 4.66683 8.50016 4.66683C8.31127 4.66683 8.15294 4.73072 8.02516 4.8585C7.89738 4.98628 7.8335 5.14461 7.8335 5.3335C7.8335 5.52239 7.89738 5.68072 8.02516 5.8085C8.15294 5.93628 8.31127 6.00016 8.50016 6.00016ZM8.50016 14.6668C7.57794 14.6668 6.71127 14.4918 5.90016 14.1418C5.08905 13.7918 4.38349 13.3168 3.78349 12.7168C3.18349 12.1168 2.70849 11.4113 2.35849 10.6002C2.00849 9.78905 1.83349 8.92239 1.83349 8.00016C1.8335 7.07794 2.0085 6.21127 2.3585 5.40016C2.7085 4.58905 3.1835 3.8835 3.7835 3.2835C4.3835 2.6835 5.08905 2.2085 5.90016 1.8585C6.71127 1.5085 7.57794 1.3335 8.50016 1.3335C9.42239 1.3335 10.2891 1.5085 11.1002 1.8585C11.9113 2.2085 12.6168 2.6835 13.2168 3.2835C13.8168 3.8835 14.2918 4.58905 14.6418 5.40017C14.9918 6.21128 15.1668 7.07794 15.1668 8.00017C15.1668 8.92239 14.9918 9.78905 14.6418 10.6002C14.2918 11.4113 13.8168 12.1168 13.2168 12.7168C12.6168 13.3168 11.9113 13.7918 11.1002 14.1418C10.289 14.4918 9.42238 14.6668 8.50016 14.6668ZM8.50016 13.3335C9.98905 13.3335 11.2502 12.8168 12.2835 11.7835C13.3168 10.7502 13.8335 9.48905 13.8335 8.00017C13.8335 6.51128 13.3168 5.25017 12.2835 4.21683C11.2502 3.1835 9.98905 2.66683 8.50016 2.66683C7.01127 2.66683 5.75016 3.1835 4.71683 4.21683C3.6835 5.25016 3.16683 6.51127 3.16683 8.00016C3.16683 9.48905 3.68349 10.7502 4.71683 11.7835C5.75016 12.8168 7.01127 13.3335 8.50016 13.3335Z" fill="' . esc_attr( $color ) . '"/></svg>';
		}
		if ( $name === 'info_sm' ) {
			$img = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 8.5C6.14167 8.5 6.26042 8.45208 6.35625 8.35625C6.45208 8.26042 6.5 8.14167 6.5 8L6.5 6C6.5 5.85833 6.45208 5.73958 6.35625 5.64375C6.26042 5.54792 6.14167 5.5 6 5.5C5.85833 5.5 5.73958 5.54792 5.64375 5.64375C5.54792 5.73958 5.5 5.85833 5.5 6L5.5 8C5.5 8.14167 5.54792 8.26042 5.64375 8.35625C5.73958 8.45208 5.85833 8.5 6 8.5ZM6 4.5C6.14167 4.5 6.26042 4.45208 6.35625 4.35625C6.45208 4.26042 6.5 4.14167 6.5 4C6.5 3.85833 6.45208 3.73958 6.35625 3.64375C6.26042 3.54792 6.14167 3.5 6 3.5C5.85833 3.5 5.73958 3.54792 5.64375 3.64375C5.54792 3.73958 5.5 3.85833 5.5 4C5.5 4.14167 5.54792 4.26042 5.64375 4.35625C5.73958 4.45208 5.85833 4.5 6 4.5ZM6 11C5.30833 11 4.65833 10.8688 4.05 10.6063C3.44167 10.3438 2.9125 9.9875 2.4625 9.5375C2.0125 9.0875 1.65625 8.55833 1.39375 7.95C1.13125 7.34167 0.999999 6.69167 0.999999 6C0.999999 5.30833 1.13125 4.65833 1.39375 4.05C1.65625 3.44167 2.0125 2.9125 2.4625 2.4625C2.9125 2.0125 3.44167 1.65625 4.05 1.39375C4.65833 1.13125 5.30833 1 6 1C6.69167 1 7.34167 1.13125 7.95 1.39375C8.55833 1.65625 9.0875 2.0125 9.5375 2.4625C9.9875 2.9125 10.3437 3.44167 10.6062 4.05C10.8687 4.65834 11 5.30834 11 6C11 6.69167 10.8687 7.34167 10.6062 7.95C10.3437 8.55834 9.9875 9.0875 9.5375 9.5375C9.0875 9.9875 8.55833 10.3438 7.95 10.6063C7.34167 10.8688 6.69166 11 6 11ZM6 10C7.11667 10 8.0625 9.6125 8.8375 8.8375C9.6125 8.0625 10 7.11667 10 6C10 4.88333 9.6125 3.9375 8.8375 3.1625C8.0625 2.3875 7.11667 2 6 2C4.88333 2 3.9375 2.3875 3.1625 3.1625C2.3875 3.9375 2 4.88333 2 6C2 7.11667 2.3875 8.0625 3.1625 8.8375C3.9375 9.6125 4.88333 10 6 10Z" fill="' . esc_attr( $color ) . '"/></svg>';
		}

		return $img;
	}

	/**
	 * Adds a bulk action "AI Detection Scan" to the given bulk actions array.
	 *
	 * @param array $bulk_actions The array of bulk actions.
	 *
	 * @return array The updated array of bulk actions.
	 */
	public static function add_bulk_action( $bulk_actions ) {
		$bulk_actions['ai_detection_scan'] = __( 'AI Detection Scan', 'originality-ai' );

		return $bulk_actions;
	}

	/**
	 * Add additional button to posts list screens - in the same row like bulk actions select and filter
	 *
	 * @param $which string The position of the extra table nav markup: 'top' or 'bottom'.
	 *
	 * @return void
	 */
	public static function add_scan_all_button_posts_extra_tablenav( $which ) {
		$screen     = get_current_screen();
		$post_types = get_post_types( [ 'public' => true ] );
	
		if ( 'top' === $which && isset( $screen->post_type ) && in_array( $screen->post_type, $post_types ) ) {
			$post_type_obj = get_post_type_object( $screen->post_type );
	
			$post_type_name = ! empty( $post_type_obj->labels->name_plural )
				? $post_type_obj->labels->name_plural
				: ( ! empty( $post_type_obj->labels->name ) ? $post_type_obj->labels->name : 'Posts' );
	
			$scan_text   = __( 'Start AI Scan for All', 'originality-ai' );
			$button_text = $scan_text . ' ' . $post_type_name;
	
			$logo_url     = self::get_logo_url( false ); // Use the URL helper function
			$button_style = 'background: url(' . esc_url( $logo_url ) . ') 5px center / auto 1em no-repeat, none; padding-left: 25px;';
			$nonce = wp_create_nonce( 'scan_all_posts_nonce' );
	
			echo '<div class="alignleft actions">';
			echo '<input style="' . esc_attr( $button_style ) . '" type="submit" name="scan_all_posts" class="button action" value="' . esc_attr( $button_text ) . '">';
			echo '<input type="hidden" name="_scan_all_posts_nonce" value="' . esc_attr( $nonce ) . '">';
			echo '</div>';
		}
	}
	

	/**
	 * Handle the "AI Detection Scan" action for all posts.
	 *
	 * @return void
	 */
	public static function handle_scan_all_posts_action() {
		if ( isset( $_REQUEST['scan_all_posts'] ) && ! isset( $_REQUEST['taxonomy'] ) ) {
			// Verify the nonce for security
			if ( ! isset( $_REQUEST['_scan_all_posts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_scan_all_posts_nonce'] ) ), 'scan_all_posts_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'originality-ai' ) );
			}

			// Check if the user has the capability to edit posts.
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'originality-ai' ) );
			}

			// Get the post type from the request
			$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) ) : 'post';
	
			// Query all posts for the given post type
			$query    = new WP_Query( [ 'posts_per_page' => -1, 'post_type' => $post_type ] );
			$post_ids = wp_list_pluck( $query->posts, 'ID' );
	
			// Redirect URL for the bulk action
			$redirect_url = admin_url( 'edit.php?post_type=' . $post_type );
	
			// Schedule posts for a scan and append query arg for bulk scans
			$redirect_url = static::bulk_actions_handler( $redirect_url, 'ai_detection_scan', $post_ids );
	
			// Safely redirect to the new URL
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * @param $actions
	 * @param $term
	 *
	 * @return mixed
	 */
	public static function add_scan_all_button_to_taxonomy_term( $actions, $term ) {
		$screen     = get_current_screen();
		$taxonomies = get_taxonomies( [ 'public' => true ] );

		if ( isset( $screen->taxonomy ) && in_array( $screen->taxonomy, $taxonomies ) ) {
			$nonce = wp_create_nonce( 'scan_all_posts_nonce' );
			$button_url                = admin_url( 'edit-tags.php?taxonomy=' . $term->taxonomy . '&scan_all_posts=' . $term->term_id . '&_wpnonce=' . $nonce );
			$actions['scan_all_posts'] = '<a href="' . esc_url( $button_url ) . '">' . __( 'Start AI Scan for All Posts', 'originality-ai' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Execute the scan on all posts of a specific taxonomy term.
	 *
	 * @return void
	 */
	public static function execute_scan_on_taxonomy_posts() {
		if ( isset( $_GET['scan_all_posts'] ) && isset( $_GET['taxonomy'] ) ) {
			// Verify nonce for security.
			check_admin_referer( 'scan_all_posts_nonce', '_wpnonce' );

			// Check if the user has the capability to edit posts.
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'originality-ai' ) );
			}

			$taxonomy   = sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) );
			$term       = intval( $_GET['scan_all_posts'] );
			$query_args = [
				'post_type'      => 'post',
				'posts_per_page' => - 1,
				'fields'         => 'ids',
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'field'    => 'id',
						'terms'    => $term
					]
				],
			];

			$query = new WP_Query( $query_args );
			$post_ids = $query->get_posts();

			if ( ! empty( $post_ids ) ) {
				// Redirect back to the taxonomy term listing page after the scan.
				$redirect_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy );
				$redirect_url = static::bulk_actions_handler( $redirect_url, 'ai_detection_scan', $post_ids );

				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit();
			}

			// If no posts found, redirect back without changes.
			wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy ) );
			exit();
		}
	}

	/**
	 * Handle the bulk action "AI Detection Scan".
	 *
	 * @param string $redirect_to URL to redirect after the action.
	 * @param string $doaction The action being performed.
	 * @param array $post_ids The IDs of the posts to scan.
	 *
	 * @return string
	 */
	public static function bulk_actions_handler( $redirect_to, $doaction, $post_ids ) {
		if ( $doaction !== 'ai_detection_scan' ) {
			return $redirect_to;
		}
	
		$transient_key = 'originalityai_bulk_scan_post_ids';
	
		// Set posts to "processing" state
		self::set_posts_to_processing_state( $post_ids );
	
		// Initialize transient with post IDs and processing status
		$transient_data = array_fill_keys( $post_ids, 'processing' );
		set_transient( $transient_key, $transient_data, HOUR_IN_SECONDS );
	
		// Divide posts into manageable batches
		$batches = array_chunk( $post_ids, 50 ); // Process 50 posts per batch
	
		foreach ( $batches as $batch_index => $batch_post_ids ) {
			$batch_id = uniqid( 'ai_scan_batch_' );
	
			foreach ( $batch_post_ids as $post_id ) {
				update_post_meta( $post_id, '_originalityai_scan_batch', $batch_id );
			}
	
			// Schedule a batch scan
			wp_schedule_single_event( time() + ( $batch_index * 60 ), 'ai_scan_batch_event', [ $batch_id ] );
		}

		// Generate the nonce
		$nonce = wp_create_nonce( 'bulk_scan_nonce' );

		// Attach the nonce and bulk_ai_scans as query arguments
		return add_query_arg(
			[
				'bulk_ai_scans' => count( $post_ids ),
				'bulk_scan_nonce' => $nonce,
			],
			$redirect_to
		);
	}

	/**
	 * Process a batch of posts for AI detection scan.
	 *
	 * @param string $batch_id The unique batch ID to process.
	 *
	 * @return void
	 */
	public static function handle_batch_event( $batch_id ) {
		$transient_key = 'originalityai_bulk_scan_post_ids';
		$post_ids = get_transient( $transient_key );

		$query = new WP_Query( [
			'post_type'      => 'any',
			'meta_key'       => '_originalityai_scan_batch', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $batch_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'posts_per_page' => -1,
			'post_status'    => 'any',
		] );

		foreach ( $query->posts as $post ) {
			$post_id = $post->ID;

			// Skip if already completed
			if ( isset( $post_ids[ $post_id ] ) && $post_ids[ $post_id ] === 'completed' ) {
				continue;
			}

			$status = get_post_meta( $post_id, '_originalityai_scan_status', true );

			if ( $status === 'processing' ) {
				$scan_result = parent::run_scan_per_post( $post_id, $post );

				// Update scan status and transient
				if ( $scan_result ) {
					update_post_meta( $post_id, '_originalityai_scan_status', 'completed' );
					$post_ids[ $post_id ] = 'completed';
				} else {
					update_post_meta( $post_id, '_originalityai_scan_status', 'failed' );
					$post_ids[ $post_id ] = 'failed';
				}
			} else {
				// Skip if not in processing state
				$post_ids[ $post_id ] = $status;
			}
		}

		set_transient( $transient_key, $post_ids, HOUR_IN_SECONDS );
	}

	/**
	 * Set all posts to "processing" state efficiently.
	 *
	 * @param array $post_ids Array of post IDs to update.
	 *
	 * @return void
	 */
	public static function set_posts_to_processing_state( $post_ids ) {
		global $wpdb;

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return;
		}

		// Sanitize post IDs
		$post_ids = array_map( 'absint', $post_ids );

		// Step 1: Delete existing meta keys for these posts
		$cache_key = 'originalityai_postmeta_processing';
		wp_cache_set( $cache_key, $post_ids, 'originalityai', HOUR_IN_SECONDS );

		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$delete_query = "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($placeholders)";
		$delete_params = array_merge( [ '_originalityai_scan_status' ], $post_ids );

		// Run the delete query with $wpdb->query()
		$wpdb->query( $wpdb->prepare( $delete_query, ...$delete_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Step 2: Insert new meta keys with "processing" state
		$insert_placeholders = [];
		$insert_values = [];
		foreach ( $post_ids as $post_id ) {
			$insert_placeholders[] = '(%d, %s, %s)';
			$insert_values[] = $post_id;
			$insert_values[] = '_originalityai_scan_status';
			$insert_values[] = 'processing';
		}

		if ( ! empty( $insert_placeholders ) ) {
			$insert_query = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $insert_placeholders );
			$wpdb->query( $wpdb->prepare( $insert_query, ...$insert_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		// Clear the cache after processing
		wp_cache_delete( $cache_key, 'originalityai' );
	}

	/**
	 * Display an admin notice after performing a bulk action.
	 *
	 * @return void
	 */
	public static function bulk_actions_admin_notice() {
		if ( isset( $_REQUEST['bulk_ai_scans'], $_REQUEST['bulk_scan_nonce'] ) ) {
			// Verify the nonce to ensure the request is secure.
			check_admin_referer( 'bulk_scan_nonce', 'bulk_scan_nonce' );
	
			// Check if the user has the capability to manage options.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'originality-ai' ) );
			}
	
			$total_posts = intval($_REQUEST['bulk_ai_scans']);
			echo '<div id="bulk-scan-notice" class="updated notice is-dismissible">';
			echo '<p><strong>' . esc_html__('AI Detection Scan in progress...', 'originality-ai') . '</strong></p>';
			printf(
				'<p id="bulk-scan-progress"><strong>%s</strong></p>',
				esc_html__('Initializing scan...', 'originality-ai')
			);
			echo '</div>';
		}
	}
		

	/**
	 * AJAX callback for bulk scan progress.
	 *
	 * @return void
	 */
	public static function bulk_scan_progress_callback() {
		check_ajax_referer( 'bulk_scan_nonce', 'bulk_scan_nonce' );

		// Check if the user has the capability to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'You do not have sufficient permissions to access this page.' ] );
		}

		$transient_key = 'originalityai_bulk_scan_post_ids';
		$post_ids = get_transient( $transient_key );
	
		if ( ! is_array( $post_ids ) ) {
			wp_send_json_error( [ 'message' => 'No posts in progress.' ] );
		}

		$completed_posts = [];
		$failed_posts = [];
		$processing_count = 0;
		$all_processed = true;
	
		foreach ( $post_ids as $post_id => $status ) {
			if ( 'completed' === $status ) {
				$completed_posts[] = [
					'post_id'    => $post_id,
					'scan_result' => self::get_scan_result( $post_id ),
				];
			} elseif ( 'failed' === $status ) {
				$failed_posts[] = [
					'post_id'    => $post_id,
					'error_message' => __( 'Failed to scan!.', 'originality-ai' ),
				];
			} elseif ( 'processing' === $status ) {
				$processing_count++;
				$all_processed = false;
			}
		}

		$total_posts = count( $post_ids );
		$remaining_posts = $processing_count;

		// Delete the transient if all posts are processed.
		if ( $all_processed ) {
			delete_transient( $transient_key );
		}

		wp_send_json_success( [
			'total_posts'     => $total_posts,
			'completed_posts' => $completed_posts,
			'failed_posts'    => $failed_posts,
			'remaining_posts' => $remaining_posts,
		] );
	}

	/**
	 * Get the scan result for a post from db.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array The scan result.
	 */
	public static function get_scan_result( $post_id ) {
		$record = \OriginalityAILogger::get_latest_by_post_id( $post_id );
		if ( $record && isset( $record['score_original'] ) ) {

			list( $score_original, $score_ai ) = self::calculate_int_scores( $record['score_original'] );

			$color_mapping_item = self::get_color_mapping_item( $record['score_ai'], $record['score_original'] );

			return [
				'score_original' => $score_original,
				'score_ai'       => $score_ai,
				'record_id'      => $record['id'],
				'color_mapping_item' => $color_mapping_item,
				'max' => (int) max( $score_ai, $score_original ),
			];
		}
		return [];
	}

	/**
	 * Show activation notice.
	 *
	 * @return void
	 */
	public static function show_activation_notice() {
		if ( get_option( self::ORIGINALITYAI_ACTIVATION_NOTICE ) ) {
			if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'originalityai' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			?>
			<div class="notice notice-warning is-dismissible originalityai-activation-notice">
				<h3>
					<?php echo '🔔 <a href="https://originality.ai/" target="_blank" class="originalityai-notice-link">' . esc_html__( 'Originality.ai', 'originality-ai' ) . '</a>'; ?>
					<span class="notice-title"><?php esc_html_e( 'Important: Complete Plugin Setup', 'originality-ai' ); ?></span>
				</h3>
				<p>
					<?php
					esc_html_e( 'To start using the plugin, please complete the login in the plugin settings.', 'originality-ai' );
					echo ' <a href="options-general.php?page=originalityai" class="originalityai-settings-link">' . esc_html__( 'Click here to go to Plugin Settings', 'originality-ai' ) . '</a>.';
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Dismiss the activation notice.
	 *
	 * @return void
	 */
	public static function dismiss_activation_notice() {
		// Check nonce for security.
		check_ajax_referer( 'dismiss_notice', 'nonce' );

		// Check if current use has permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'originality-ai' ) );
		}

		delete_option( self::ORIGINALITYAI_ACTIVATION_NOTICE );

		wp_send_json_success();
	}

	/**
	 * Get color mapping item.
	 *
	 * @param $score_ai The AI score.
	 * @param $score_original The original score.
	 *
	 * @return mixed|null
	 */
	public static function get_color_mapping_item( $score_ai, $score_original_val ) {
		list( $score_original, $score_ai ) = self::calculate_int_scores( $score_original_val );

		$mapping = self::get_colors_titles_mapping();

		if ( $score_ai == 100 ) {
			return $mapping[ - 100 ];
		}
		if ( $score_ai == 0 ) {
			return $mapping[100];
		}
		if ( $score_original >= 90 ) {
			return $mapping[ - 90 ];
		}
		if ( $score_original >= 70 ) {
			return $mapping[ - 70 ];
		}
		if ( $score_original >= 60 ) {
			return $mapping[ - 60 ];
		}
		if ( $score_original >= 50 ) {
			return $mapping[ - 50 ];
		}
		if ( $score_ai >= 90 ) {
			return $mapping[90];
		}
		if ( $score_ai >= 70 ) {
			return $mapping[70];
		}
		if ( $score_ai >= 50 ) {
			return $mapping[ 50 ];
		}

		return null;
	}

	/**
	 * Get the logo URL only.
	 *
	 * @param bool $full Whether to get the full logo or just the icon.
	 * @return string Logo URL.
	 */
	public static function get_logo_url( $full = true ) {
		$logo_path = $full ? 'pt-logo-small-black.png' : 'pt-logo-small-black-only-icon.png';
		return esc_url( ORIGINALITYAI_ROOT_URL . 'assets/img/' . $logo_path . '?new' );
	}


	/**
	 * Get the logo image HTML.
	 *
	 * @param bool $full Whether to get the full logo or just the icon.
	 * @return string
	 */
	public static function get_logo( $full = true ) {
		$logo_path = $full ? 'pt-logo-small-black.png' : 'pt-logo-small-black-only-icon.png';

		// Try to register the image in the media library
		$attachment_id = self::register_plugin_image( $logo_path );

		if ( $attachment_id ) {
			return wp_get_attachment_image(
				$attachment_id,
				'full',
				false,
				array( 'id' => 'originality-ai--logo', 'alt' => __( 'Originality.ai Logo', 'originality-ai' ) )
			);
		}

		// Fallback to the URL -- added an empty string for now.
		return '';
	}

	/**
	 * Register plugin image in WordPress media library.
	 *
	 * @param string $image_path Relative path to image.
	 * @return int|false Attachment ID or false on failure.
	 */
	private static function register_plugin_image( $image_path ) {
		$upload_dir = wp_upload_dir();
		$full_path  = trailingslashit( ORIGINALITYAI_ROOT_PATH . 'assets/img' ) . $image_path;

		// Ensure the file exists
		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		// Check file type
		$file_type = wp_check_filetype( basename( $full_path ) );

		// Check if the image is already registered
		$args  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_originalityai_logo',
					'value'   => $image_path,
					'compare' => '='
				),
			),
			'posts_per_page' => 1,
		);
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			return $query->posts[0]->ID;
		}

		// Copy the image to the uploads directory
		$destination_path = trailingslashit( $upload_dir['path'] ) . basename( $full_path );
		if ( ! copy( $full_path, $destination_path ) ) {
			return false;
		}

		// Create the attachment
		$attachment = array(
			'guid'           => trailingslashit( $upload_dir['url'] ) . basename( $full_path ),
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( basename( $full_path ) ),
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $destination_path );
		if ( ! is_wp_error( $attach_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $destination_path ) );
			update_post_meta( $attach_id, '_originalityai_logo', $image_path );
			return $attach_id;
		}

		return false;
	}

	/**
	 * Retrieves the mapping of confidence levels to color and title values.
	 *
	 * The numeric keys represent confidence levels. Positive values denote AI-generated content
	 * and negative values denote human-written content. Each key maps to another associative array
	 * with keys 'color' and 'title'.
	 *
	 * The 'color' key maps to a string value specifying the RGB color associated with that confidence level.
	 * The 'title' key maps to a string specifying a descriptive sentence about what that confidence level means.
	 *
	 * @see https://app.originality.ai/home/content-scan/22816242
	 *
	 * @return array An associative array representing the mapping of confidence levels to color and title values.
	 *               Each key represents a confidence level, mapping to an array with keys 'color' and 'title'.
	 */
	public static function get_colors_titles_mapping() {
		return array(
			100   => array( 'color' => 'rgb(104, 159, 56)', 'label' => 'Likely Original', 'title' => '100% confidence this post was human written.' ),
			90    => array( 'color' => 'rgb(235, 142, 112)', 'label' => 'Likely AI', 'title' => '> 90% confidence this post was generated by AI.' ),
			70    => array( 'color' => 'rgb(246, 179, 107)', 'label' => 'Likely AI', 'title' => '> 70% confidence this post was generated by AI.' ),
			50    => array( 'color' => 'rgb(255, 214, 102)', 'label' => 'Likely AI', 'title' => '50% confidence this post was generated by AI.' ),
			- 50  => array( 'color' => 'rgb(154, 197, 124)', 'label' => 'Likely Original', 'title' => '50% confidence this post was human written.' ),
			- 60  => array( 'color' => 'rgb(154, 197, 124)', 'label' => 'Likely Original', 'title' => '60% confidence this post was human written.' ),
			- 70  => array( 'color' => 'rgb(154, 197, 124)', 'label' => 'Likely Original', 'title' => '> 70% confidence this post was human written.' ),
			- 90  => array( 'color' => '#539D17', 'label' => 'Likely Original', 'title' => '> 90% confidence this post was human written.' ),
			- 100 => array( 'color' => '#EB4735', 'label' => 'Likely AI', 'title' => '100% confidence this post was generated by AI.' )
		);
	}

	/**
	 * Calculate integer scores for Originality and AI.
	 *
	 * @param $score_original1
	 *
	 * @return int[]
	 */
	public static function calculate_int_scores( $score_original1 ) {
		$score_original = (int) ( ( $score_original1 * 100 >= 1 ) ? ceil( $score_original1 * 100 ) : 0 );
		$score_ai       = 100 - $score_original;

		return array( $score_original, $score_ai );
	}

	/**
	 * Get table data from OriginalityAILogger.
	 *
	 * @return void
	 */
	public static function ai_get_table_data() {
		$table_data = OriginalityAILogger::ai_get_table();
		wp_send_json( $table_data );
	}

}