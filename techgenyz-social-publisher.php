<?php
/**
 * Plugin Name:       TechGenyz Social Publisher
 * Description:       Generates social captions with OpenAI and publishes immediately through Buffer.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            TechGenyz
 * License:           GPL-2.0-or-later
 * Text Domain:       techgenyz-social-publisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TGSP_VERSION', '1.0.0' );
define( 'TGSP_DEVELOPMENT_VERSION', '1.6.1' );
define( 'TGSP_FILE', __FILE__ );
define( 'TGSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'TGSP_URL', plugin_dir_url( __FILE__ ) );

require_once TGSP_DIR . 'includes/class-social-publisher-logger.php';
require_once TGSP_DIR . 'includes/class-social-publisher-security.php';
// Retained for backward compatibility; the social manager is the active delivery path.
require_once TGSP_DIR . 'includes/class-social-publisher-webhook.php';
require_once TGSP_DIR . 'includes/social/class-caption-generator.php';
require_once TGSP_DIR . 'includes/social/class-social-payload.php';
require_once TGSP_DIR . 'includes/social/class-normalized-response.php';
require_once TGSP_DIR . 'includes/social/class-social-webhook.php';
require_once TGSP_DIR . 'includes/social/class-openai-client.php';
require_once TGSP_DIR . 'includes/social/class-buffer-client.php';
require_once TGSP_DIR . 'includes/social/class-social-manager.php';
require_once TGSP_DIR . 'includes/class-social-publisher-settings.php';
require_once TGSP_DIR . 'includes/class-social-publisher-metabox.php';

/**
 * Creates the log table on activation.
 */
function tgsp_activate() {
	TGSP_Logger::create_table();
}
register_activation_hook( __FILE__, 'tgsp_activate' );

/**
 * Boots admin-only functionality.
 */
function tgsp_boot() {
	// REST routes must be registered on REST requests; all UI remains admin-only.
	TGSP_Metabox::init();

	if ( is_admin() ) {
		if ( TGSP_VERSION !== get_option( 'tgsp_db_version' ) ) {
			TGSP_Logger::create_table();
		}
		TGSP_Settings::init();
	}
}
add_action( 'plugins_loaded', 'tgsp_boot' );

/**
 * Adds a direct Settings link on the Plugins screen.
 *
 * @param array $links Existing plugin action links.
 * @return array
 */
function tgsp_plugin_action_links( $links ) {
	$url = admin_url( 'admin.php?page=' . TGSP_Settings::PAGE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'techgenyz-social-publisher' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tgsp_plugin_action_links' );
