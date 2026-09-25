<?php
/**
 * Preserves delivery history and settings by default.
 *
 * Define TGSP_REMOVE_DATA as true in wp-config.php before uninstalling to remove
 * the plugin's options, post metadata, and custom log table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'TGSP_REMOVE_DATA' ) || true !== TGSP_REMOVE_DATA ) {
	return;
}

delete_option( 'tgsp_webhook_url' );
delete_option( 'tgsp_message_format' );
delete_option( 'tgsp_webhook_secret' );
delete_option( 'tgsp_debug_logging' );
delete_option( 'tgsp_db_version' );
delete_option( 'tgsp_webhook_connection_status' );
delete_option( 'tgsp_webhook_last_success' );
delete_option( 'tgsp_delivery_method' );
delete_option( 'tgsp_openai_api_key' );
delete_option( 'tgsp_openai_model' );
delete_option( 'tgsp_openai_caption_prompt' );
delete_option( 'tgsp_buffer_api_key' );
delete_option( 'tgsp_buffer_organization_id' );
foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
	delete_option( 'tgsp_enable_' . $platform );
	delete_option( 'tgsp_' . $platform . '_caption_template' );
	delete_option( 'tgsp_buffer_channel_' . $platform );
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}social_publish_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_social_publisher_status' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_social_publisher_sent_time' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_social_publisher_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_social_publisher_platform_statuses' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( '_tgsp_saved_caption_facebook', '_tgsp_saved_caption_linkedin', '_tgsp_saved_caption_x' ) as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
