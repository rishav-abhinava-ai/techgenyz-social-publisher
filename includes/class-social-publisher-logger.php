<?php
/**
 * Database logger.
 *
 * @package AISocialPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISP_Logger {
	/**
	 * Returns the site-prefixed log table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aisp_publish_logs';
	}

	/**
	 * Creates or upgrades the log table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			platform varchar(50) NOT NULL DEFAULT 'Webhook',
			status varchar(20) NOT NULL,
			response longtext NOT NULL,
			submitted_text longtext NOT NULL,
			remote_id varchar(255) NOT NULL DEFAULT '',
			response_code smallint(5) unsigned NOT NULL DEFAULT 0,
			attempt_id varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY post_platform (post_id, platform),
			KEY attempt_id (attempt_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'aisp_db_version', AISP_VERSION, false );
	}

	/**
	 * Writes a log entry. Failures can be forced even when debug mode is off.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status   Result status.
	 * @param mixed  $response Safe response details.
	 * @param bool   $force    Write regardless of the debug setting.
	 */
	public static function add( $post_id, $status, $response, $force = false ) {
		self::add_platform( $post_id, 'Webhook', $status, $response, array(), $force );
	}

	/**
	 * Writes a platform-specific log entry.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $platform Platform key.
	 * @param string $status Result status.
	 * @param mixed  $response Safe response details.
	 * @param array  $details Optional remote_id, response_code and attempt_id.
	 * @param bool   $force Write regardless of debug mode.
	 */
	public static function add_platform( $post_id, $platform, $status, $response, $details = array(), $force = false ) {
		if ( ! $force && ! (bool) get_option( 'aisp_debug_logging', false ) ) {
			return;
		}

		global $wpdb;
		$encoded = is_string( $response ) ? $response : wp_json_encode( $response );
		$encoded = self::redact( $encoded );
		$encoded = substr( (string) $encoded, 0, 10000 );
		$now     = current_time( 'mysql', true );

		$wpdb->insert(
			self::table_name(),
			array(
				'post_id'   => absint( $post_id ),
				'platform'  => sanitize_key( $platform ),
				'status'    => sanitize_key( $status ),
				'response'  => wp_strip_all_tags( (string) $encoded ),
				'submitted_text' => sanitize_textarea_field( isset( $details['submitted_text'] ) ? $details['submitted_text'] : '' ),
				'remote_id' => sanitize_text_field( isset( $details['remote_id'] ) ? $details['remote_id'] : '' ),
				'response_code' => absint( isset( $details['response_code'] ) ? $details['response_code'] : 0 ),
				'attempt_id' => sanitize_key( isset( $details['attempt_id'] ) ? $details['attempt_id'] : '' ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/** Updates the existing attempt log when Buffer reconciliation reaches a new state. */
	public static function update_platform_attempt( $post_id, $platform, $attempt_id, $remote_id, $status, $response, $details = array() ) {
		global $wpdb;
		$encoded = substr( (string) self::redact( wp_json_encode( $response ) ), 0, 10000 );
		$updated = $wpdb->update(
			self::table_name(),
			array( 'status' => sanitize_key( $status ), 'response' => wp_strip_all_tags( $encoded ), 'response_code' => absint( isset( $details['response_code'] ) ? $details['response_code'] : 0 ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'post_id' => absint( $post_id ), 'platform' => sanitize_key( $platform ), 'attempt_id' => sanitize_key( $attempt_id ), 'remote_id' => sanitize_text_field( $remote_id ) ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d', '%s', '%s', '%s' )
		);
		if ( false === $updated ) {
			self::add_platform( $post_id, $platform, $status, $response, $details, true );
		}
	}

	/**
	 * Removes common credential values from stored response text.
	 *
	 * @param string $value Response text.
	 * @return string
	 */
	private static function redact( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/(authorization|access[_-]?token|api[_-]?key|secret)(["\\s:=]+)([^"\\s,}]+)/i', '$1$2[REDACTED]', $value );
		return (string) $value;
	}
}
