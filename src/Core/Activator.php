<?php
/**
 * Plugin Activator class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Activator class - runs on plugin activation.
 */
class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( CLOUDFLARE_R2_OFFLOAD_CDN_BASENAME );
			wp_die(
				esc_html__( 'This plugin requires PHP 7.4 or higher.', 'cloudflare-r2-offload-cdn' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Check WP version.
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			deactivate_plugins( CLOUDFLARE_R2_OFFLOAD_CDN_BASENAME );
			wp_die(
				esc_html__( 'This plugin requires WordPress 6.0 or higher.', 'cloudflare-r2-offload-cdn' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Create default options.
		if ( false === get_option( 'cloudflare_r2_offload_cdn_settings' ) ) {
			add_option(
				'cloudflare_r2_offload_cdn_settings',
				array(
					'enable_feature' => 0,
					'api_key'        => '',
				)
			);
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
