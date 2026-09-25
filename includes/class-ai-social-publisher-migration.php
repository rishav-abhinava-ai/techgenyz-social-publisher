<?php
/**
 * One-time, non-destructive development identity migration.
 *
 * @package AISocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISP_Identity_Migration {
	const VERSION = '1';

	/**
	 * Copies legacy development data to the AI Social Publisher identifiers.
	 *
	 * Existing destination values are never overwritten and legacy data is retained.
	 *
	 * @return bool Whether the migration completed successfully.
	 */
	public static function run() {
		if ( self::VERSION === (string) get_option( 'aisp_identity_migration_version', '' ) ) {
			return true;
		}

		global $wpdb;

		self::migrate_options();
		self::migrate_post_meta();
		AISP_Logger::create_table();
		self::migrate_log_rows();

		if ( '' !== $wpdb->last_error ) {
			return false;
		}

		update_option( 'aisp_identity_migration_version', self::VERSION, false );
		return true;
	}

	/**
	 * Copies every legacy option only when its destination does not exist.
	 */
	private static function migrate_options() {
		$options = array(
			'tgsp_delivery_method'             => 'aisp_delivery_method',
			'tgsp_openai_api_key'              => 'aisp_openai_api_key',
			'tgsp_openai_model'                => 'aisp_openai_model',
			'tgsp_openai_caption_prompt'       => 'aisp_openai_caption_prompt',
			'tgsp_buffer_api_key'              => 'aisp_buffer_api_key',
			'tgsp_buffer_organization_id'      => 'aisp_buffer_organization_id',
			'tgsp_buffer_channel_facebook'     => 'aisp_buffer_channel_facebook',
			'tgsp_buffer_channel_linkedin'     => 'aisp_buffer_channel_linkedin',
			'tgsp_buffer_channel_x'            => 'aisp_buffer_channel_x',
			'tgsp_webhook_url'                 => 'aisp_webhook_url',
			'tgsp_webhook_secret'              => 'aisp_webhook_secret',
			'tgsp_webhook_connection_status'   => 'aisp_webhook_connection_status',
			'tgsp_webhook_last_success'        => 'aisp_webhook_last_success',
			'tgsp_enable_facebook'             => 'aisp_enable_facebook',
			'tgsp_enable_linkedin'             => 'aisp_enable_linkedin',
			'tgsp_enable_x'                    => 'aisp_enable_x',
			'tgsp_facebook_caption_template'   => 'aisp_facebook_caption_template',
			'tgsp_linkedin_caption_template'   => 'aisp_linkedin_caption_template',
			'tgsp_x_caption_template'          => 'aisp_x_caption_template',
			'tgsp_message_format'              => 'aisp_message_format',
			'tgsp_debug_logging'               => 'aisp_debug_logging',
			'tgsp_db_version'                  => 'aisp_db_version',
		);
		$missing = new stdClass();

		foreach ( $options as $legacy => $current ) {
			$value = get_option( $legacy, $missing );
			if ( $missing !== $value && $missing === get_option( $current, $missing ) ) {
				add_option( $current, $value, '', 'no' );
			}
		}
	}

	/**
	 * Copies persistent metadata byte-for-byte and carries forward only active locks.
	 */
	private static function migrate_post_meta() {
		global $wpdb;

		$meta_map = array(
			'_social_publisher_status'            => '_aisp_status',
			'_social_publisher_sent_time'         => '_aisp_sent_time',
			'_social_publisher_platform_statuses' => '_aisp_platform_statuses',
			'_tgsp_saved_caption_facebook'        => '_aisp_saved_caption_facebook',
			'_tgsp_saved_caption_linkedin'        => '_aisp_saved_caption_linkedin',
			'_tgsp_saved_caption_x'               => '_aisp_saved_caption_x',
		);

		foreach ( $meta_map as $legacy => $current ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
					 SELECT legacy.post_id, %s, legacy.meta_value
					 FROM {$wpdb->postmeta} AS legacy
					 WHERE legacy.meta_key = %s
					 AND NOT EXISTS (
						 SELECT 1 FROM {$wpdb->postmeta} AS current
						 WHERE current.post_id = legacy.post_id AND current.meta_key = %s
					 )",
					$current,
					$legacy,
					$current
				)
			);
		}

		$active_after = time() - 120;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				 SELECT legacy.post_id, %s, legacy.meta_value
				 FROM {$wpdb->postmeta} AS legacy
				 WHERE legacy.meta_key = %s
				 AND CAST(legacy.meta_value AS UNSIGNED) > %d
				 AND NOT EXISTS (
					 SELECT 1 FROM {$wpdb->postmeta} AS current
					 WHERE current.post_id = legacy.post_id AND current.meta_key = %s
				 )",
				'_aisp_lock',
				'_social_publisher_lock',
				$active_after,
				'_aisp_lock'
			)
		);
	}

	/**
	 * Copies the legacy log rows without dropping or modifying the rollback table.
	 */
	private static function migrate_log_rows() {
		global $wpdb;

		$legacy = $wpdb->prefix . 'social_publish_logs';
		$current = AISP_Logger::table_name();
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
		if ( $legacy !== $exists ) {
			return;
		}

		$wpdb->query(
			"INSERT IGNORE INTO {$current}
			 (id, post_id, platform, status, response, submitted_text, remote_id, response_code, attempt_id, created_at, updated_at)
			 SELECT id, post_id, platform, status, response, submitted_text, remote_id, response_code, attempt_id, created_at, updated_at
			 FROM {$legacy}"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
