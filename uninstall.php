<?php
/**
 * Plugin uninstall script for Originality.ai plugin.
 *
 * @package Originality.ai
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Global database object.
global $wpdb;

// Define constants if not already defined.
if ( ! defined( 'ORIGINALITYAI_CONNECTION_OPTION' ) ) {
    define( 'ORIGINALITYAI_CONNECTION_OPTION', 'originalityai_connection_option' );
}
if ( ! defined( 'ORIGINALITYAI_AI_SCAN_ID_OPTION' ) ) {
    define( 'ORIGINALITYAI_AI_SCAN_ID_OPTION', 'originalityai_ai_scan_id_option' );
}
if ( ! defined( 'ORIGINALITYAI_WEBSITE_ID_OPTION' ) ) {
    define( 'ORIGINALITYAI_WEBSITE_ID_OPTION', 'originalityai_website_id_option' );
}
if ( ! defined( 'ORIGINALITYAI_ACTIVATION_NOTICE' ) ) {
    define( 'ORIGINALITYAI_ACTIVATION_NOTICE', 'originalityai_activation_notice' );
}

// Define options, transients, and meta keys to delete.
$options = [
    ORIGINALITYAI_CONNECTION_OPTION,
    ORIGINALITYAI_AI_SCAN_ID_OPTION,
    ORIGINALITYAI_WEBSITE_ID_OPTION,
    ORIGINALITYAI_ACTIVATION_NOTICE,
];

$transients = [
    'originalityai_admin_notice_error',
    'originalityai_admin_notice_success',
    'originalityai_bulk_scan_post_ids',
];

$meta_keys = [
    '_originalityai_scan_batch',
    '_originalityai_scan_status',
];

// Define custom table name.
$table_name = $wpdb->prefix . 'originalityai_log';

// **Remove plugin options**
foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option ); // For multisite.
}

// **Remove post metadata**
foreach ( $meta_keys as $meta_key ) {
    $delete_meta_query = $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    );
    $wpdb->query( $delete_meta_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

// **Remove custom database table**
$cached_table_key = 'originalityai_table_check';
$table_exists = wp_cache_get( $cached_table_key, 'originalityai' );

if ( false === $table_exists ) {
    $table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) )
    );
    wp_cache_set( $cached_table_key, $table_exists, 'originalityai', 3600 );
}

if ( $table_exists === $table_name ) {
    $wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS `%s`", $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// **Remove transients**
foreach ( $transients as $transient_key ) {
    delete_transient( $transient_key );
    delete_site_transient( $transient_key ); // For multisite.
}

// **Handle multisite-specific cleanup**
if ( is_multisite() ) {
    $blog_ids = wp_cache_get( 'originalityai_blog_ids', 'originalityai' );

    if ( false === $blog_ids ) {
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        wp_cache_set( 'originalityai_blog_ids', $blog_ids, 'originalityai', HOUR_IN_SECONDS );
    }

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );

        // Remove options.
        foreach ( $options as $option ) {
            delete_option( $option );
        }

        // Remove transients.
        foreach ( $transients as $transient_key ) {
            delete_transient( $transient_key );
        }

        restore_current_blog();
    }
}
