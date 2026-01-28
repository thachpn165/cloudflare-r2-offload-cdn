<?php
/**
 * Admin Menu class.
 *
 * Slim coordinator for admin menu registration and AJAX handler delegation.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Admin\Ajax\SettingsAjaxHandler;
use ThachPN165\CFR2OffLoad\Admin\Ajax\BulkOperationAjaxHandler;
use ThachPN165\CFR2OffLoad\Admin\Ajax\WorkerAjaxHandler;
use ThachPN165\CFR2OffLoad\Admin\Ajax\ActivityAjaxHandler;
use ThachPN165\CFR2OffLoad\Constants\Settings;
use ThachPN165\CFR2OffLoad\Constants\BatchConfig;
use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;

/**
 * AdminMenu class - handles admin menu registration and AJAX delegation.
 */
class AdminMenu implements HookableInterface {

	/**
	 * Settings AJAX handler.
	 *
	 * @var SettingsAjaxHandler
	 */
	private SettingsAjaxHandler $settings_handler;

	/**
	 * Bulk operation AJAX handler.
	 *
	 * @var BulkOperationAjaxHandler
	 */
	private BulkOperationAjaxHandler $bulk_handler;

	/**
	 * Worker AJAX handler.
	 *
	 * @var WorkerAjaxHandler
	 */
	private WorkerAjaxHandler $worker_handler;

	/**
	 * Activity AJAX handler.
	 *
	 * @var ActivityAjaxHandler
	 */
	private ActivityAjaxHandler $activity_handler;

	/**
	 * Constructor - initialize AJAX handlers.
	 */
	public function __construct() {
		$this->settings_handler = new SettingsAjaxHandler();
		$this->bulk_handler     = new BulkOperationAjaxHandler();
		$this->worker_handler   = new WorkerAjaxHandler();
		$this->activity_handler = new ActivityAjaxHandler();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Delegate AJAX hooks to specialized handlers.
		$this->settings_handler->register_hooks();
		$this->bulk_handler->register_hooks();
		$this->worker_handler->register_hooks();
		$this->activity_handler->register_hooks();
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page(): void {
		add_menu_page(
			__( 'CloudFlare R2 Offload & CDN Settings', 'cloudflare-r2-offload-cdn' ),
			__( 'CloudFlare R2 Offload & CDN', 'cloudflare-r2-offload-cdn' ),
			'manage_options',
			'cloudflare-r2-offload-cdn',
			array( SettingsPage::class, 'render' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * Register settings.
	 *
	 * Note: sanitize_callback is NOT used here because we handle sanitization
	 * manually in SettingsAjaxHandler. Using both would cause double encryption
	 * of sensitive fields like r2_secret_access_key.
	 */
	public function register_settings(): void {
		register_setting(
			Settings::SETTINGS_GROUP,
			Settings::OPTION_KEY,
			array(
				'type'    => 'array',
				'default' => $this->get_default_settings(),
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings(): array {
		return array(
			'r2_account_id'        => '',
			'r2_access_key_id'     => '',
			'r2_secret_access_key' => '',
			'r2_bucket'            => '',
			'r2_public_domain'     => '',
			'auto_offload'         => 0,
			'batch_size'           => BatchConfig::DEFAULT_SIZE,
			'keep_local_files'     => 1,
			'cdn_enabled'          => 0,
			'cdn_url'              => '',
			'quality'              => 85,
			'image_format'         => 'webp',
			'smart_sizes'          => 0,
			'content_max_width'    => 800,
			'cf_api_token'         => '',
			'worker_deployed'      => false,
			'worker_name'          => '',
			'worker_deployed_at'   => '',
		);
	}
}
