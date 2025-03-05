<?php
/**
 * Plugin Name: Originality.ai AI Checker
 * Plugin URI: https://originality.ai/wordpress-plugin
 * Description: Publish your content with integrity using Originality.ai - the most accurate AI Checker in the world!
 * Version: 1.0.11
 * Author: Originality.ai
 * Author URI: https://originality.ai/
 * Text Domain: originality-ai
 * Stable tag: 1.0.11
 * License: GPLv2 or later

 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Globals constants.
 */
define( 'ORIGINALITYAI_PLUGIN_VERSION', '1.0.10' );
define( 'ORIGINALITYAI_ROOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ORIGINALITYAI_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'ORIGINALITYAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! class_exists( 'OriginalityAI' ) ) {
	/**
	 * Core class
	 */
	require_once __DIR__ . '/OriginalityAI.php';

	/**
	 * Class for API integration
	 */
	require_once __DIR__ . '/OriginalityAIAPI.php';

	/**
	 * Admin UI, bulk actions
	 */
	require_once __DIR__ . '/OriginalityAIAdminUI.php';

	/**
	 * Logger class
	 */
	require_once __DIR__ . '/OriginalityAILogger.php';

	OriginalityAI::init();
	OriginalityAIAdminUI::init();
}

/**
 * Set website_id when plugin is activated
 */
register_activation_hook( __FILE__, array( 'OriginalityAI', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'OriginalityAI', 'uninstall' ) );
