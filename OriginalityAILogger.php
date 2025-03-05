<?php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OriginalityAILogger {

	/**
	 * Name for lof file / tables prefix
	 */
	const LOG_ALIAS = 'originalityai';

	/**
	 * Path to the log file.
	 */
	private static $file_path;

	/**
	 * Initialize the file path for the log file.
	 */
	private static function initialize_file_path() {
		if ( ! isset( self::$file_path ) ) {
			// Get the uploads directory.
			$upload_dir = wp_upload_dir();
	
			// Create a subdirectory for plugin logs.
			$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'originality-ai';
	
			// Ensure the directory exists.
			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir ); // Create the directory if it doesn't exist.
			}
	
			// Set the full path for the log file.
			self::$file_path = trailingslashit( $log_dir ) . 'originality-ai.log';
		}
	}

	/**
	 * Opens the file handle for logging purposes.
	 *
	 * Uses WP_Filesystem for compatibility and security.
	 */
	public static function open() {
		global $wp_filesystem;
	
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	
		self::initialize_file_path();
	
		// Check if the log file exists, and create it if not.
		if ( ! $wp_filesystem->exists( self::$file_path ) ) {
			$wp_filesystem->put_contents( self::$file_path, '', FS_CHMOD_FILE );
		}
	}

	/**
	 * Writes a log message to the file handle, if it exists and is a valid resource.
	 *
	 * @param string $message The log message to be written.
	 *
	 * @return void
	 */
	public static function debug_log( $message ) {
		global $wp_filesystem;

		self::open();

		if ( isset( self::$file_path ) && $wp_filesystem->exists( self::$file_path ) ) {
			// Read the existing content
			$existing_content = $wp_filesystem->get_contents( self::$file_path );

			// Append the new log entry
			$log_message = gmdate( 'Y-m-d H:i:s' ) . ": {$message}\n";
			$new_content = $existing_content . $log_message;

			// Write the updated content back to the file
			$wp_filesystem->put_contents( self::$file_path, $new_content, FS_CHMOD_FILE );
		}
	}

	/**
	 * Logs data to a database table if the table exists.
	 *
	 * @param array $data The data to be logged, including the following keys:
	 *                    - id (int): The ID of the log entry.
	 *                    - credits_used (int): The number of credits used.
	 *                    - credits (int): The remaining number of credits.
	 *                    - ai_model_version (int): The version of the AI model used.
	 *                    - score_original (float): The originality score.
	 *                    - score_ai (float): The AI-generated score.
	 *
	 * @return void
	 */
	public static function db_log( $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::LOG_ALIAS . '_log';

		// Check if the table exists.
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return; // Table does not exist, exit the function
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name, 
			array(
				'id'               => $data['id'],
				'post_id'          => $data['post_id'],
				'credits_used'     => $data['credits_used'],
				'credits'          => $data['credits'],
				'ai_model_version' => $data['ai_model_version'],
				'score_original'   => $data['score_original'],
				'score_ai'         => $data['score_ai'],
				'public_link'      => $data['public_link'],
				'title'            => $data['title'],
				'words_count'      => $data['words_count']
			), 
			array( '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s', '%d' )
		);

		// Clear cache for relevant post ID.
		wp_cache_delete( 'latest_log_post_' . $data['post_id'], 'originalityai' );
	}

	/**
	 * Retrieves the latest log entry for a specific post ID.
	 *
	 * @param int $post_id The ID of the post to retrieve the latest log entry for.
	 *
	 * @return array|null The latest log entry as an associative array or null if not found.
	 */
	public static function get_latest_by_post_id( $post_id ) {
		global $wpdb;

		$cache_key = 'latest_log_post_' . $post_id;
		$cached_result = wp_cache_get( $cache_key, 'originalityai' );

		if ( false === $cached_result ) {
			$table_name = $wpdb->prefix . 'originalityai_log';
			$sql = $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT * FROM {$table_name} WHERE `post_id` = %d ORDER BY `request_timestamp` DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id 
			);
			$cached_result = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

			// Cache the result
			wp_cache_set( $cache_key, $cached_result, 'originalityai', HOUR_IN_SECONDS );
		}

		return $cached_result;
	}

	/**
	 * Checks if a table exists and creates it if it doesn't exist.
	 *
	 * This method uses WordPress global $wpdb object to interact with the database. It first checks if the specified table name exists in the database.
	 * If the table does not exist, it creates the table using the specified table schema.
	 * The table schema includes columns for id, request_timestamp, credits_used, credits, ai_model_version, score_original, and score_ai.
	 * The method then uses the dbDelta function from WordPress to execute the SQL query and create the table in the database.
	 *
	 * @return string | null
	 * @global wpdb $wpdb WordPress database access abstraction object.
	 *
	 */
	public static function check_and_create_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . self::LOG_ALIAS . '_log';

		$sql = "CREATE TABLE `$table_name` (
					id bigint(20) UNSIGNED NOT NULL,
					post_id bigint(20) UNSIGNED,
					request_timestamp timestamp NOT NULL DEFAULT current_timestamp(),
					credits_used int(10) UNSIGNED,
					credits int(10) UNSIGNED,
					ai_model_version tinyint(3) UNSIGNED,
					score_original double(4,4) UNSIGNED,
					score_ai double(4,4) UNSIGNED,
					public_link varchar(255),
					words_count int(10) UNSIGNED,
					title varchar(255),
					PRIMARY KEY (id),
					INDEX idx_post_id (post_id),
					INDEX idx_request_timestamp (request_timestamp)
				) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return $table_name;
	}

	/**
	 * Retrieves data from the AI log table.
	 *
	 * @return array An array of table data or a failure response.
	 */
	public static function ai_get_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::LOG_ALIAS . '_log';

		$cache_key = 'all_logs_table';
		$cached_results = wp_cache_get( $cache_key, 'originalityai' );

		if ( false === $cached_results ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $table_exists ) {
				$sql = $wpdb->prepare(
					"SELECT a.*, 
					CONCAT(%s, CAST(id AS CHAR)) as results_url,
					public_link as share_url
					FROM {$table_name} a", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					OriginalityAI::ORIGINALITYAI_HOME_SCAN_URL
				);

				$cached_results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

				// Cache the results
				wp_cache_set( $cache_key, $cached_results, 'originalityai', HOUR_IN_SECONDS );
			} else {
				return array( 'success' => false );
			}
		}

		return $cached_results;
	}
}