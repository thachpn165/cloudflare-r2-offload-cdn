<?php
/**
 * Plugin Deactivator class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivator class - runs on plugin deactivation.
 */
class Deactivator {

	/**
	 * Deactivate the plugin.
	 */
	public static function deactivate(): void {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
