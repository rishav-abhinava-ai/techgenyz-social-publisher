<?php
/**
 * Preserves delivery history and settings by default.
 *
 * Define AISP_REMOVE_DATA as true in wp-config.php before uninstalling to remove
 * the plugin's options, post metadata, and custom log table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'AISP_REMOVE_DATA' ) || true !== AISP_REMOVE_DATA ) {
	return;
}

delete_option( 'aisp_webhook_url' );
delete_option( 'aisp_message_format' );
delete_option( 'aisp_webhook_secret' );
delete_option( 'aisp_debug_logging' );
delete_option( 'aisp_db_version' );
delete_option( 'aisp_webhook_connection_status' );
delete_option( 'aisp_webhook_last_success' );
delete_option( 'aisp_delivery_method' );
delete_option( 'aisp_openai_api_key' );
delete_option( 'aisp_openai_model' );
delete_option( 'aisp_openai_caption_prompt' );
delete_option( 'aisp_buffer_api_key' );
delete_option( 'aisp_buffer_organization_id' );
foreach ( array( 'facebook', 'linkedin', 'x' ) as $platform ) {
	delete_option( 'aisp_enable_' . $platform );
	delete_option( 'aisp_' . $platform . '_caption_template' );
	delete_option( 'aisp_buffer_channel_' . $platform );
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aisp_publish_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aisp_status' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aisp_sent_time' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aisp_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aisp_platform_statuses' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array( '_aisp_saved_caption_facebook', '_aisp_saved_caption_linkedin', '_aisp_saved_caption_x' ) as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
