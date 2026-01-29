<?php
/**
 * Main Plugin class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Traits\SingletonTrait;
use ThachPN165\CFR2OffLoad\Core\Loader;
use ThachPN165\CFR2OffLoad\Admin\AdminMenu;
use ThachPN165\CFR2OffLoad\Admin\MediaLibraryExtension;
use ThachPN165\CFR2OffLoad\Admin\DeactivationHandler;
use ThachPN165\CFR2OffLoad\PublicSide\Assets;
use ThachPN165\CFR2OffLoad\Database\Schema;
use ThachPN165\CFR2OffLoad\Hooks\MediaUploadHooks;
use ThachPN165\CFR2OffLoad\Services\URLRewriter;
use ThachPN165\CFR2OffLoad\Integrations\WooCommerceIntegration;
use ThachPN165\CFR2OffLoad\Integrations\GutenbergIntegration;
use ThachPN165\CFR2OffLoad\Integrations\RestApiIntegration;

/**
 * Plugin class - main entry point.
 */
class Plugin {

	use SingletonTrait;

	/**
	 * Hook loader instance.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new Loader();
		Schema::maybe_upgrade();
		$this->define_hooks();
		$this->loader->run();
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'cloudflare-r2-offload-cdn',
			false,
			dirname( CFR2_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Define all hooks.
	 */
	private function define_hooks(): void {
		// Load text domain for translations (priority 0 for early loading).
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		// Admin hooks.
		if ( is_admin() ) {
			$admin_menu = new AdminMenu();
			$admin_menu->register_hooks();

			$media_lib = new MediaLibraryExtension();
			$media_lib->register_hooks();

			$deactivation = new DeactivationHandler();
			$deactivation->register_hooks();
		}

		// Assets.
		$assets = new Assets();
		$assets->register_hooks();

		// Media upload hooks.
		$media_hooks = new MediaUploadHooks();
		$media_hooks->register_hooks();

		// URL rewriting (frontend + admin for previews).
		$url_rewriter = new URLRewriter();
		$url_rewriter->register_hooks();

		// REST API integration (always).
		$rest_api = new RestApiIntegration();
		$rest_api->register_hooks();

		// WooCommerce integration (conditional).
		if ( class_exists( 'WooCommerce' ) ) {
			$woo = new WooCommerceIntegration();
			$woo->register_hooks();
		}

		// Gutenberg integration (always).
		$gutenberg = new GutenbergIntegration();
		$gutenberg->register_hooks();

		// Stats cleanup cron.
		add_action( 'cfr2_cleanup_stats', array( Services\StatsTracker::class, 'cleanup_old_stats' ) );
	}

	/**
	 * Get loader instance.
	 *
	 * @return Loader
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}
}
