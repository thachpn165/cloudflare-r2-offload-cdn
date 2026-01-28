<?php
/**
 * Admin Menu class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;
use ThachPN165\CFR2OffLoad\Services\EncryptionService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use ThachPN165\CFR2OffLoad\Services\CloudflareAPI;
use ThachPN165\CFR2OffLoad\Services\WorkerDeployer;

/**
 * AdminMenu class - handles admin menu registration and AJAX settings save.
 */
class AdminMenu implements HookableInterface {

	/**
	 * Rate limit: max saves per minute.
	 */
	private const RATE_LIMIT_MAX = 10;

	/**
	 * Rate limit window in seconds.
	 */
	private const RATE_LIMIT_WINDOW = 60;

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_cloudflare_r2_offload_cdn_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_cloudflare_r2_offload_cdn_test_r2', array( $this, 'ajax_test_r2_connection' ) );
		add_action( 'wp_ajax_cfr2_bulk_offload_all', array( $this, 'ajax_bulk_offload_all' ) );
		add_action( 'wp_ajax_cfr2_cancel_bulk', array( $this, 'ajax_cancel_bulk' ) );
		add_action( 'wp_ajax_cfr2_get_bulk_progress', array( $this, 'ajax_get_bulk_progress' ) );
		add_action( 'wp_ajax_cfr2_process_bulk_item', array( $this, 'ajax_process_bulk_item' ) );
		add_action( 'wp_ajax_cfr2_bulk_restore_all', array( $this, 'ajax_bulk_restore_all' ) );
		add_action( 'wp_ajax_cfr2_process_restore_item', array( $this, 'ajax_process_restore_item' ) );
		add_action( 'wp_ajax_cfr2_deploy_worker', array( $this, 'ajax_deploy_worker' ) );
		add_action( 'wp_ajax_cfr2_remove_worker', array( $this, 'ajax_remove_worker' ) );
		add_action( 'wp_ajax_cfr2_worker_status', array( $this, 'ajax_worker_status' ) );
		add_action( 'wp_ajax_cfr2_validate_cdn_dns', array( $this, 'ajax_validate_cdn_dns' ) );
		add_action( 'wp_ajax_cfr2_enable_dns_proxy', array( $this, 'ajax_enable_dns_proxy' ) );
		add_action( 'wp_ajax_cfr2_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_cfr2_get_activity_log', array( $this, 'ajax_get_activity_log' ) );
		add_action( 'wp_ajax_cfr2_retry_failed', array( $this, 'ajax_retry_failed' ) );
		add_action( 'wp_ajax_cfr2_retry_single', array( $this, 'ajax_retry_single' ) );
		add_action( 'wp_ajax_cfr2_clear_log', array( $this, 'ajax_clear_log' ) );
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
	 * manually in ajax_save_settings(). Using both would cause double encryption
	 * of sensitive fields like r2_secret_access_key.
	 */
	public function register_settings(): void {
		register_setting(
			'cloudflare_r2_offload_cdn_settings_group',
			'cloudflare_r2_offload_cdn_settings',
			array(
				'type'    => 'array',
				'default' => $this->get_default_settings(),
			)
		);
	}

	/**
	 * Handle AJAX settings save.
	 */
	public function ajax_save_settings(): void {
		// Rate limiting - prevent spam/DoS.
		$user_id   = get_current_user_id();
		$rate_key  = 'cloudflare_r2_offload_cdn_rate_' . $user_id;
		$save_count = get_transient( $rate_key );

		if ( false !== $save_count && (int) $save_count >= self::RATE_LIMIT_MAX ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please try again later.', 'cloudflare-r2-offload-cdn' ) ),
				429
			);
		}

		// Verify nonce with strict equality check.
		if ( false === check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'cloudflare_r2_offload_cdn_nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'cloudflare-r2-offload-cdn' ) ),
				403
			);
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ),
				403
			);
		}

		// Increment rate limit counter.
		set_transient( $rate_key, ( $save_count ? (int) $save_count + 1 : 1 ), self::RATE_LIMIT_WINDOW );

		// Get and sanitize form data.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$input = array(
			'r2_account_id'           => sanitize_text_field( wp_unslash( $_POST['r2_account_id'] ?? '' ) ),
			'r2_access_key_id'        => sanitize_text_field( wp_unslash( $_POST['r2_access_key_id'] ?? '' ) ),
			'r2_secret_access_key'    => sanitize_text_field( wp_unslash( $_POST['r2_secret_access_key'] ?? '' ) ),
			'r2_bucket'               => sanitize_text_field( wp_unslash( $_POST['r2_bucket'] ?? '' ) ),
			'r2_public_domain'        => esc_url_raw( wp_unslash( $_POST['r2_public_domain'] ?? '' ) ),
			'auto_offload'            => ! empty( $_POST['auto_offload'] ) ? 1 : 0,
			'batch_size'              => absint( $_POST['batch_size'] ?? 25 ),
			'cdn_enabled'             => ! empty( $_POST['cdn_enabled'] ) ? 1 : 0,
			'cdn_url'                 => esc_url_raw( wp_unslash( $_POST['cdn_url'] ?? '' ) ),
			'quality'                 => absint( $_POST['quality'] ?? 85 ),
			'image_format'            => sanitize_text_field( wp_unslash( $_POST['image_format'] ?? 'webp' ) ),
			'smart_sizes'             => ! empty( $_POST['smart_sizes'] ) ? 1 : 0,
			'content_max_width'       => absint( $_POST['content_max_width'] ?? 800 ),
			'cf_api_token'            => sanitize_text_field( wp_unslash( $_POST['cf_api_token'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Sanitize settings.
		$sanitized = $this->sanitize_settings( $input );

		// Update option.
		$updated = update_option( 'cloudflare_r2_offload_cdn_settings', $sanitized );

		if ( false === $updated ) {
			// Check if genuinely unchanged (use strict comparison).
			$current = get_option( 'cloudflare_r2_offload_cdn_settings' );

			if ( false !== $current && $current === $sanitized ) {
				wp_send_json_success(
					array( 'message' => __( 'No changes detected.', 'cloudflare-r2-offload-cdn' ) )
				);
			}

			wp_send_json_error(
				array( 'message' => __( 'Failed to save settings. Please try again.', 'cloudflare-r2-offload-cdn' ) ),
				500
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Settings saved.', 'cloudflare-r2-offload-cdn' ) )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// R2 Credentials - sanitize and encrypt.
		$sanitized['r2_account_id']    = preg_replace( '/[^a-zA-Z0-9]/', '', $input['r2_account_id'] ?? '' );
		$sanitized['r2_access_key_id'] = sanitize_text_field( $input['r2_access_key_id'] ?? '' );

		// R2 Secret Access Key - only encrypt if not placeholder.
		$secret = $input['r2_secret_access_key'] ?? '';
		if ( ! empty( $secret ) && $secret !== '********' ) {
			$encryption                            = new EncryptionService();
			$sanitized['r2_secret_access_key'] = $encryption->encrypt( $secret );
		} else {
			// Keep existing value.
			$existing                              = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
			$sanitized['r2_secret_access_key'] = $existing['r2_secret_access_key'] ?? '';
		}

		// R2 Bucket - lowercase alphanumeric + hyphens only.
		$sanitized['r2_bucket'] = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $input['r2_bucket'] ?? '' ) );

		// R2 Public Domain - custom domain for public access.
		$sanitized['r2_public_domain'] = esc_url_raw( $input['r2_public_domain'] ?? '' );

		// Offload settings.
		$sanitized['auto_offload'] = ! empty( $input['auto_offload'] ) ? 1 : 0;
		$batch_size                = absint( $input['batch_size'] ?? 25 );
		$sanitized['batch_size']   = max( 10, min( $batch_size, 50 ) );

		// CDN settings.
		$sanitized['cdn_enabled'] = ! empty( $input['cdn_enabled'] ) ? 1 : 0;
		$sanitized['cdn_url']     = $this->sanitize_url_field( $input['cdn_url'] ?? '' );

		// Quality: 1-100.
		$quality              = absint( $input['quality'] ?? 85 );
		$sanitized['quality'] = max( 1, min( $quality, 100 ) );

		// Image format: original, webp, or avif.
		$allowed_formats            = array( 'original', 'webp', 'avif' );
		$image_format               = $input['image_format'] ?? 'webp';
		$sanitized['image_format'] = in_array( $image_format, $allowed_formats, true ) ? $image_format : 'webp';

		// Smart sizes settings.
		$sanitized['smart_sizes'] = ! empty( $input['smart_sizes'] ) ? 1 : 0;
		$content_max_width        = absint( $input['content_max_width'] ?? 800 );
		$sanitized['content_max_width'] = max( 320, min( $content_max_width, 1920 ) );

		// Cloudflare API Token - only encrypt if not placeholder.
		$cf_token = $input['cf_api_token'] ?? '';
		if ( ! empty( $cf_token ) && $cf_token !== '********' ) {
			$encryption                   = new EncryptionService();
			$sanitized['cf_api_token'] = $encryption->encrypt( $cf_token );
		} else {
			// Keep existing value.
			$existing                     = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
			$sanitized['cf_api_token'] = $existing['cf_api_token'] ?? '';
		}

		// Worker deployment internal fields (preserve).
		$existing                          = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$sanitized['worker_deployed']      = $existing['worker_deployed'] ?? false;
		$sanitized['worker_name']          = $existing['worker_name'] ?? '';
		$sanitized['worker_deployed_at']   = $existing['worker_deployed_at'] ?? '';

		return $sanitized;
	}

	/**
	 * Sanitize URL field - removes trailing slash and validates.
	 *
	 * @param string $url URL to sanitize.
	 * @return string Sanitized URL.
	 */
	private function sanitize_url_field( string $url ): string {
		$url = esc_url_raw( $url );
		return rtrim( $url, '/' );
	}

	/**
	 * Test R2 connection via AJAX.
	 */
	public function ajax_test_r2_connection(): void {
		// Verify nonce.
		if ( false === check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'cloudflare_r2_offload_cdn_nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'cloudflare-r2-offload-cdn' ) ),
				403
			);
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ),
				403
			);
		}

		// Rate limiting - 5 attempts per minute.
		$user_id  = get_current_user_id();
		$rate_key = 'cfr2_r2_test_' . $user_id;
		$count    = get_transient( $rate_key );

		if ( false !== $count && (int) $count >= 5 ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many attempts. Wait 60 seconds.', 'cloudflare-r2-offload-cdn' ) ),
				429
			);
		}
		set_transient( $rate_key, ( $count ? (int) $count + 1 : 1 ), 60 );

		// Get credentials from form values (for testing before save).
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$account_id    = sanitize_text_field( wp_unslash( $_POST['r2_account_id'] ?? '' ) );
		$access_key_id = sanitize_text_field( wp_unslash( $_POST['r2_access_key_id'] ?? '' ) );
		$secret_key    = sanitize_text_field( wp_unslash( $_POST['r2_secret_access_key'] ?? '' ) );
		$bucket        = sanitize_text_field( wp_unslash( $_POST['r2_bucket'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// If secret is placeholder, get from saved settings.
		if ( '********' === $secret_key || empty( $secret_key ) ) {
			$settings   = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
			$encryption = new EncryptionService();
			$secret_key = $encryption->decrypt( $settings['r2_secret_access_key'] ?? '' );
		}

		$credentials = array(
			'account_id'        => $account_id,
			'access_key_id'     => $access_key_id,
			'secret_access_key' => $secret_key,
			'bucket'            => $bucket,
		);

		// Validate all fields present.
		foreach ( $credentials as $key => $value ) {
			if ( empty( $value ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: credential field name */
							__( 'Missing %s', 'cloudflare-r2-offload-cdn' ),
							$key
						),
					)
				);
			}
		}

		// Test connection.
		$r2     = new R2Client( $credentials );
		$result = $r2->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success(
				array( 'message' => __( 'Connection successful!', 'cloudflare-r2-offload-cdn' ) )
			);
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings(): array {
		return array(
			'r2_account_id'           => '',
			'r2_access_key_id'        => '',
			'r2_secret_access_key'    => '',
			'r2_bucket'               => '',
			'r2_public_domain'        => '',
			'auto_offload'            => 0,
			'batch_size'              => 25,
			'cdn_enabled'             => 0,
			'cdn_url'                 => '',
			'quality'                 => 85,
			'image_format'            => 'webp',
			'smart_sizes'             => 0,
			'content_max_width'       => 800,
			'cf_api_token'            => '',
			'worker_deployed'         => false,
			'worker_name'             => '',
			'worker_deployed_at'      => '',
		);
	}

	/**
	 * AJAX handler for bulk offload all.
	 * Queues items for AJAX-based processing.
	 */
	public function ajax_bulk_offload_all(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		global $wpdb;

		// Clear old queue items first.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}cfr2_offload_queue WHERE status IN ('completed', 'cancelled', 'failed')" );

		// Get all non-offloaded attachments.
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_cfr2_offloaded',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$queued = 0;
		foreach ( $attachments as $attachment_id ) {
			// Check if already in queue.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cfr2_offload_queue WHERE attachment_id = %d AND status = 'pending'",
					$attachment_id
				)
			);

			if ( ! $exists ) {
				$wpdb->insert(
					$wpdb->prefix . 'cfr2_offload_queue',
					array(
						'attachment_id' => $attachment_id,
						'action'        => 'offload',
						'status'        => 'pending',
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
				++$queued;
			}
		}

		// Clear any cancellation flag.
		delete_transient( 'cfr2_bulk_cancelled' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of files */
					__( '%d files queued for offload.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'total'   => $queued,
			)
		);
	}

	/**
	 * AJAX handler for bulk restore all.
	 * Queues offloaded items for restore to local.
	 */
	public function ajax_bulk_restore_all(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		global $wpdb;

		// Clear old queue items first.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}cfr2_offload_queue WHERE action = 'restore'" );

		// Get all offloaded attachments.
		$attachments = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_cfr2_offloaded' AND meta_value = '1'"
		);

		$queued = 0;
		foreach ( $attachments as $attachment_id ) {
			$wpdb->insert(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'attachment_id' => $attachment_id,
					'action'        => 'restore',
					'status'        => 'pending',
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
			++$queued;
		}

		// Clear any cancellation flag.
		delete_transient( 'cfr2_bulk_cancelled' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of files */
					__( '%d files queued for restore.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'total'   => $queued,
			)
		);
	}

	/**
	 * AJAX handler for process single restore item.
	 * Called repeatedly by JavaScript to restore items one by one.
	 */
	public function ajax_process_restore_item(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// Check if cancelled.
		if ( get_transient( 'cfr2_bulk_cancelled' ) ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'Bulk restore cancelled.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		global $wpdb;

		// Get next pending restore item.
		$item = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
			 WHERE status = 'pending' AND action = 'restore'
			 ORDER BY created_at ASC
			 LIMIT 1"
		);

		if ( ! $item ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'All items restored.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Mark as processing.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => 'processing' ),
			array( 'id' => $item->id ),
			array( '%s' ),
			array( '%d' )
		);

		$attachment_id = (int) $item->attachment_id;
		$file_path     = get_attached_file( $attachment_id );
		$filename      = $file_path ? basename( $file_path ) : "ID: {$attachment_id}";

		// Process the restore.
		$settings   = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$encryption = new EncryptionService();

		$credentials = array(
			'account_id'        => $settings['r2_account_id'] ?? '',
			'access_key_id'     => $settings['r2_access_key_id'] ?? '',
			'secret_access_key' => $encryption->decrypt( $settings['r2_secret_access_key'] ?? '' ),
			'bucket'            => $settings['r2_bucket'] ?? '',
		);

		if ( empty( $credentials['secret_access_key'] ) ) {
			$this->mark_item_failed( $item->id, 'R2 credentials not configured' );
			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'error', 'R2 credentials not configured' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => __( 'R2 credentials not configured', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		$r2      = new R2Client( $credentials );
		$offload = new \ThachPN165\CFR2OffLoad\Services\OffloadService( $r2 );
		$result  = $offload->restore( $attachment_id );

		if ( $result['success'] ) {
			// Mark completed.
			$wpdb->update(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'status'       => 'completed',
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'success', 'Restored to local' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'success',
					'filename' => $filename,
					'message'  => __( 'Restored to local', 'cloudflare-r2-offload-cdn' ),
				)
			);
		} else {
			$error_msg = $result['message'] ?? 'Unknown error';
			$this->mark_item_failed( $item->id, $error_msg );
			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'error', $error_msg );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => $error_msg,
				)
			);
		}
	}

	/**
	 * AJAX handler for cancel bulk.
	 */
	public function ajax_cancel_bulk(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		set_transient( 'cfr2_bulk_cancelled', true, 300 );

		// Clear pending queue items.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => 'cancelled' ),
			array( 'status' => 'pending' ),
			array( '%s' ),
			array( '%s' )
		);

		wp_send_json_success( array( 'message' => __( 'Bulk offload cancelled.', 'cloudflare-r2-offload-cdn' ) ) );
	}

	/**
	 * AJAX handler for get bulk progress.
	 */
	public function ajax_get_bulk_progress(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		global $wpdb;

		$stats = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				COUNT(*) as total
			 FROM {$wpdb->prefix}cfr2_offload_queue
			 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		// Get current processing item.
		$current_item = $wpdb->get_row(
			"SELECT attachment_id FROM {$wpdb->prefix}cfr2_offload_queue
			 WHERE status = 'processing'
			 ORDER BY created_at ASC
			 LIMIT 1"
		);

		$current_file = '';
		if ( $current_item ) {
			$file_path    = get_attached_file( $current_item->attachment_id );
			$current_file = $file_path ? basename( $file_path ) : '';
		}

		wp_send_json_success(
			array(
				'completed'    => (int) $stats->completed,
				'failed'       => (int) $stats->failed,
				'pending'      => (int) $stats->pending,
				'processing'   => (int) $stats->processing,
				'total'        => (int) $stats->total,
				'is_running'   => $stats->pending > 0 || $stats->processing > 0,
				'current_file' => $current_file,
			)
		);
	}

	/**
	 * AJAX handler for process single bulk item.
	 * Called repeatedly by JavaScript to process queue items one by one.
	 */
	public function ajax_process_bulk_item(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// Check if cancelled.
		if ( get_transient( 'cfr2_bulk_cancelled' ) ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'Bulk offload cancelled.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		global $wpdb;

		// Get next pending item.
		$item = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
			 WHERE status = 'pending' AND action = 'offload'
			 ORDER BY created_at ASC
			 LIMIT 1"
		);

		if ( ! $item ) {
			// No more items.
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'All items processed.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Mark as processing.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => 'processing' ),
			array( 'id' => $item->id ),
			array( '%s' ),
			array( '%d' )
		);

		$attachment_id = (int) $item->attachment_id;
		$file_path     = get_attached_file( $attachment_id );
		$filename      = $file_path ? basename( $file_path ) : "ID: {$attachment_id}";

		// Process the offload.
		$settings   = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$encryption = new EncryptionService();

		$credentials = array(
			'account_id'        => $settings['r2_account_id'] ?? '',
			'access_key_id'     => $settings['r2_access_key_id'] ?? '',
			'secret_access_key' => $encryption->decrypt( $settings['r2_secret_access_key'] ?? '' ),
			'bucket'            => $settings['r2_bucket'] ?? '',
		);

		if ( empty( $credentials['secret_access_key'] ) ) {
			$this->mark_item_failed( $item->id, 'R2 credentials not configured' );
			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'error', 'R2 credentials not configured' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => __( 'R2 credentials not configured', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		$r2      = new R2Client( $credentials );
		$offload = new \ThachPN165\CFR2OffLoad\Services\OffloadService( $r2 );
		$result  = $offload->offload( $attachment_id );

		if ( $result['success'] ) {
			// Mark completed.
			$wpdb->update(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'status'       => 'completed',
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$thumb_info = '';
			if ( ! empty( $result['thumbnails']['total'] ) ) {
				$thumb_info = sprintf(
					' (+%d/%d thumbnails)',
					$result['thumbnails']['success'],
					$result['thumbnails']['total']
				);
			}

			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'success', 'Offloaded to R2' . $thumb_info );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'success',
					'filename' => $filename,
					'message'  => __( 'Offloaded to R2', 'cloudflare-r2-offload-cdn' ) . $thumb_info,
				)
			);
		} else {
			$error_msg = $result['message'] ?? 'Unknown error';
			$this->mark_item_failed( $item->id, $error_msg );
			\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::log( $attachment_id, 'error', $error_msg );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => $error_msg,
				)
			);
		}
	}

	/**
	 * Mark queue item as failed.
	 *
	 * @param int    $item_id      Queue item ID.
	 * @param string $error_message Error message.
	 */
	private function mark_item_failed( int $item_id, string $error_message ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'status'        => 'failed',
				'error_message' => $error_message,
				'processed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * AJAX handler for deploy worker.
	 */
	public function ajax_deploy_worker(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );

		// Validate required fields.
		if ( empty( $settings['cf_api_token'] ) || empty( $settings['r2_account_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing Cloudflare API Token or Account ID.', 'cloudflare-r2-offload-cdn' ) ) );
		}

		// Validate R2 bucket is configured.
		if ( empty( $settings['r2_bucket'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'R2 Bucket name is required. Please configure it in the Storage tab first.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Decrypt API token.
		$encryption = new EncryptionService();
		$api_token  = $encryption->decrypt( $settings['cf_api_token'] );

		// Initialize services.
		$api      = new CloudflareAPI( $api_token, $settings['r2_account_id'] );
		$deployer = new WorkerDeployer( $api );

		// Deploy with R2 bucket binding (direct access).
		$result = $deployer->deploy(
			array(
				'r2_bucket'    => $settings['r2_bucket'],
				'custom_domain' => $settings['cdn_url'] ?? '',
				'image_format' => $settings['image_format'] ?? 'webp',
			)
		);

		if ( $result['success'] ) {
			// Save deployment info.
			$settings['worker_deployed']    = true;
			$settings['worker_name']        = $result['worker_name'];
			$settings['worker_deployed_at'] = current_time( 'mysql' );
			update_option( 'cloudflare_r2_offload_cdn_settings', $settings );

			wp_send_json_success(
				array(
					'message'  => __( 'Worker deployed successfully!', 'cloudflare-r2-offload-cdn' ),
					'steps'    => $result['steps'],
					'warnings' => $result['warnings'] ?? array(),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'  => $result['message'],
					'steps'    => $result['steps'],
					'warnings' => $result['warnings'] ?? array(),
				)
			);
		}
	}

	/**
	 * AJAX handler for validate CDN DNS.
	 */
	public function ajax_validate_cdn_dns(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cdn_url = isset( $_POST['cdn_url'] ) ? esc_url_raw( wp_unslash( $_POST['cdn_url'] ) ) : '';

		if ( empty( $cdn_url ) ) {
			wp_send_json_error( array( 'message' => __( 'CDN URL is required.', 'cloudflare-r2-offload-cdn' ) ) );
		}

		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );

		if ( empty( $settings['cf_api_token'] ) || empty( $settings['r2_account_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please configure Cloudflare API Token first.', 'cloudflare-r2-offload-cdn' ) ) );
		}

		$encryption = new EncryptionService();
		$api_token  = $encryption->decrypt( $settings['cf_api_token'] );

		$api    = new CloudflareAPI( $api_token, $settings['r2_account_id'] );
		$result = $api->validate_cdn_dns( $cdn_url );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for enable DNS proxy.
	 */
	public function ajax_enable_dns_proxy(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$zone_id   = isset( $_POST['zone_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_id'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';

		if ( empty( $zone_id ) || empty( $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing zone or record ID.', 'cloudflare-r2-offload-cdn' ) ) );
		}

		$settings   = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$encryption = new EncryptionService();
		$api_token  = $encryption->decrypt( $settings['cf_api_token'] ?? '' );

		$api    = new CloudflareAPI( $api_token, $settings['r2_account_id'] ?? '' );
		$result = $api->enable_dns_proxy( $zone_id, $record_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Proxy enabled successfully!', 'cloudflare-r2-offload-cdn' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result['errors'][0]['message'] ?? __( 'Failed to enable proxy.', 'cloudflare-r2-offload-cdn' ) ) );
		}
	}

	/**
	 * AJAX handler for remove worker.
	 */
	public function ajax_remove_worker(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );

		$encryption = new EncryptionService();
		$api_token  = $encryption->decrypt( $settings['cf_api_token'] ?? '' );

		$api      = new CloudflareAPI( $api_token, $settings['r2_account_id'] ?? '' );
		$deployer = new WorkerDeployer( $api );

		$result = $deployer->undeploy();

		if ( $result['success'] ) {
			$settings['worker_deployed'] = false;
			unset( $settings['worker_name'], $settings['worker_deployed_at'] );
			update_option( 'cloudflare_r2_offload_cdn_settings', $settings );

			wp_send_json_success( array( 'message' => __( 'Worker removed.', 'cloudflare-r2-offload-cdn' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result['errors'][0]['message'] ?? 'Unknown error' ) );
		}
	}

	/**
	 * AJAX handler for worker status.
	 */
	public function ajax_worker_status(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );

		if ( empty( $settings['worker_deployed'] ) ) {
			wp_send_json_success( array( 'deployed' => false ) );
			return;
		}

		$encryption = new EncryptionService();
		$api_token  = $encryption->decrypt( $settings['cf_api_token'] ?? '' );

		$api      = new CloudflareAPI( $api_token, $settings['r2_account_id'] ?? '' );
		$deployer = new WorkerDeployer( $api );

		$status = $deployer->get_status();

		wp_send_json_success(
			array(
				'deployed'    => true,
				'worker_name' => $settings['worker_name'] ?? '',
				'deployed_at' => $settings['worker_deployed_at'] ?? '',
				'status'      => $status,
			)
		);
	}

	/**
	 * AJAX handler for get stats.
	 */
	public function ajax_get_stats(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$period = sanitize_text_field( wp_unslash( $_GET['period'] ?? 'month' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		switch ( $period ) {
			case 'week':
				$days = 7;
				break;
			case 'month':
			default:
				$days = 30;
				break;
		}

		$daily_stats    = \ThachPN165\CFR2OffLoad\Services\StatsTracker::get_daily_stats( $days );
		$current_month  = \ThachPN165\CFR2OffLoad\Services\StatsTracker::get_current_month_transformations();
		$chart_data     = \ThachPN165\CFR2OffLoad\Admin\Widgets\StatsWidget::get_chart_data();

		wp_send_json_success(
			array(
				'daily'         => $daily_stats,
				'current_month' => $current_month,
				'chart_data'    => $chart_data,
			)
		);
	}

	/**
	 * AJAX handler for get activity log.
	 */
	public function ajax_get_activity_log(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 20;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$logs = \ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::get_logs( $limit );

		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/**
	 * AJAX handler for retry all failed.
	 */
	public function ajax_retry_failed(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		global $wpdb;

		// Clear cancellation flag.
		delete_transient( 'cfr2_bulk_cancelled' );

		// Get all failed items from last 24 hours and reset to pending.
		$queued = $wpdb->query(
			"UPDATE {$wpdb->prefix}cfr2_offload_queue
			 SET status = 'pending', error_message = NULL, processed_at = NULL
			 WHERE status = 'failed'
			 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of items */
					__( '%d items queued for retry.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'queued'  => (int) $queued,
			)
		);
	}

	/**
	 * AJAX handler for retry single.
	 */
	public function ajax_retry_single(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'cloudflare-r2-offload-cdn' ) ) );
		}

		global $wpdb;

		// Reset status to pending.
		$updated = $wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'status'        => 'pending',
				'error_message' => null,
				'processed_at'  => null,
			),
			array( 'attachment_id' => $attachment_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( $updated ) {
			wp_send_json_success( array( 'message' => __( 'Item queued for retry.', 'cloudflare-r2-offload-cdn' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to queue item.', 'cloudflare-r2-offload-cdn' ) ) );
		}
	}

	/**
	 * AJAX handler for clear log.
	 */
	public function ajax_clear_log(): void {
		check_ajax_referer( 'cloudflare_r2_offload_cdn_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
		}

		\ThachPN165\CFR2OffLoad\Services\BulkOperationLogger::clear();

		wp_send_json_success( array( 'message' => __( 'Activity log cleared.', 'cloudflare-r2-offload-cdn' ) ) );
	}
}
