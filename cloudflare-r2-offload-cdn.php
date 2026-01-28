<?php
/**
 * Plugin Name: CloudFlare R2 Offload My Plugin CDN
 * Plugin URI:  https://example.com/cloudflare-r2-offload-cdn
 * Description: A WordPress plugin boilerplate.
 * Version:           1.0.0
 * Author:      ThachPN165
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloudflare-r2-offload-cdn
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package CFR2OffLoad
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_VERSION', '1.0.0' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_FILE', __FILE__ );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoload.
if ( file_exists( CLOUDFLARE_R2_OFFLOAD_CDN_PATH . 'vendor/autoload.php' ) ) {
	require_once CLOUDFLARE_R2_OFFLOAD_CDN_PATH . 'vendor/autoload.php';
}

// Load Action Scheduler early (must be before plugins_loaded priority 0).
// See: https://actionscheduler.org/usage/
$action_scheduler_path = CLOUDFLARE_R2_OFFLOAD_CDN_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( file_exists( $action_scheduler_path ) ) {
	require_once $action_scheduler_path;
}

// Initialize plugin.
add_action(
	'plugins_loaded',
	function () {
		\ThachPN165\CFR2OffLoad\Plugin::instance();
	}
);

// Activation/Deactivation hooks.
register_activation_hook( __FILE__, array( \ThachPN165\CFR2OffLoad\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ThachPN165\CFR2OffLoad\Core\Deactivator::class, 'deactivate' ) );
