<?php
/**
 * Admin Menu class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;

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
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page(): void {
		add_menu_page(
			__( 'CloudFlare R2 Offload My Plugin CDN Settings', 'cloudflare-r2-offload-cdn' ),
			__( 'CloudFlare R2 Offload My Plugin CDN', 'cloudflare-r2-offload-cdn' ),
			'manage_options',
			'cloudflare-r2-offload-cdn',
			array( SettingsPage::class, 'render' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'cloudflare_r2_offload_cdn_settings_group',
			'cloudflare_r2_offload_cdn_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
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
			'enable_feature'      => ! empty( $_POST['enable_feature'] ) ? 1 : 0,
			'plugin_mode'         => sanitize_text_field( wp_unslash( $_POST['plugin_mode'] ?? 'basic' ) ),
			'cache_duration'      => absint( $_POST['cache_duration'] ?? 3600 ),
			'api_key'             => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
			'debug_mode'          => ! empty( $_POST['debug_mode'] ) ? 1 : 0,
			'custom_css'          => wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) ),
			'enable_analytics'    => ! empty( $_POST['enable_analytics'] ) ? 1 : 0,
			'third_party_api_url' => esc_url_raw( wp_unslash( $_POST['third_party_api_url'] ?? '' ) ),
			'webhook_url'         => esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ),
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

		// General settings.
		$sanitized['enable_feature'] = ! empty( $input['enable_feature'] ) ? 1 : 0;
		$sanitized['plugin_mode']    = in_array( $input['plugin_mode'] ?? '', array( 'basic', 'advanced', 'pro' ), true )
			? $input['plugin_mode']
			: 'basic';

		// Cache duration: enforce 0-86400 range (0-24h).
		$cache_duration = absint( $input['cache_duration'] ?? 3600 );
		$sanitized['cache_duration'] = max( 0, min( $cache_duration, 86400 ) );

		// Advanced settings.
		$api_key = sanitize_text_field( $input['api_key'] ?? '' );
		$sanitized['api_key'] = ! empty( $api_key ) ? self::encrypt_api_key( $api_key ) : '';
		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] ) ? 1 : 0;

		// Custom CSS with dangerous pattern blocking.
		$sanitized['custom_css'] = $this->sanitize_custom_css( $input['custom_css'] ?? '' );

		// Integrations settings with SSRF protection.
		$sanitized['enable_analytics']    = ! empty( $input['enable_analytics'] ) ? 1 : 0;
		$sanitized['third_party_api_url'] = $this->sanitize_url_field( $input['third_party_api_url'] ?? '' );
		$sanitized['webhook_url']         = $this->sanitize_url_field( $input['webhook_url'] ?? '' );

		return $sanitized;
	}

	/**
	 * Sanitize custom CSS field - block dangerous patterns.
	 *
	 * @param string $css Raw CSS input.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_custom_css( string $css ): string {
		$css = wp_strip_all_tags( $css );

		if ( empty( $css ) ) {
			return '';
		}

		// Block dangerous CSS patterns.
		$dangerous_patterns = array(
			'/(@import|expression|behavior|javascript:|data:(?!image))/i',
			'/(document\.|window\.|eval\()/i',
			'/url\s*\(\s*["\']?\s*(?!data:image)/i',
		);

		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return ''; // Clear if dangerous patterns detected.
			}
		}

		return $css;
	}

	/**
	 * Sanitize URL field with SSRF protection.
	 *
	 * @param string $url Raw URL input.
	 * @return string Sanitized URL or empty if invalid/blocked.
	 */
	private function sanitize_url_field( string $url ): string {
		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			return '';
		}

		$parsed = wp_parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['scheme'], $parsed['host'] ) ) {
			return '';
		}

		// Only allow http/https.
		if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return '';
		}

		// Block private/internal IPs (SSRF protection).
		$host = $parsed['host'];

		$blocked_patterns = array(
			'/^(localhost|127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|169\.254\.)/i',
			'/^(0\.0\.0\.0|::1|fc00::|fe80::)/i',
		);

		foreach ( $blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $host ) ) {
				return '';
			}
		}

		return $url;
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings(): array {
		return array(
			'enable_feature'      => 0,
			'plugin_mode'         => 'basic',
			'cache_duration'      => 3600,
			'api_key'             => '',
			'debug_mode'          => 0,
			'custom_css'          => '',
			'enable_analytics'    => 0,
			'third_party_api_url' => '',
			'webhook_url'         => '',
		);
	}

	/**
	 * Encrypt API key with HMAC authentication.
	 *
	 * @param string $key Plain text API key.
	 * @return string Encrypted API key (base64 encoded with HMAC).
	 */
	public static function encrypt_api_key( string $key ): string {
		if ( empty( $key ) ) {
			return '';
		}

		// Validate AUTH_KEY is properly configured.
		if ( ! self::is_auth_key_valid() ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return base64_encode( $key );
		}

		$method    = 'AES-256-CBC';
		$iv_length = openssl_cipher_iv_length( $method );

		if ( false === $iv_length ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( $iv_length, $crypto_strong );

		if ( false === $iv || ! $crypto_strong ) {
			return '';
		}

		$encrypted = openssl_encrypt( $key, $method, AUTH_KEY, 0, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		// Add HMAC for authenticated encryption.
		$hmac = hash_hmac( 'sha256', $encrypted, AUTH_KEY, true );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $hmac . $encrypted );
	}

	/**
	 * Decrypt API key with HMAC verification.
	 *
	 * @param string $encrypted Encrypted API key.
	 * @return string Decrypted plain text API key.
	 */
	public static function decrypt_api_key( string $encrypted ): string {
		if ( empty( $encrypted ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = base64_decode( $encrypted, true );

		if ( false === $data ) {
			return '';
		}

		// Fallback for non-encrypted (base64 only) keys.
		if ( ! self::is_auth_key_valid() ) {
			return $data;
		}

		$method      = 'AES-256-CBC';
		$iv_length   = openssl_cipher_iv_length( $method );
		$hmac_length = 32; // SHA256 = 32 bytes.

		// Validate data length.
		if ( strlen( $data ) < $iv_length + $hmac_length ) {
			return '';
		}

		// Extract components.
		$iv         = substr( $data, 0, $iv_length );
		$hmac       = substr( $data, $iv_length, $hmac_length );
		$ciphertext = substr( $data, $iv_length + $hmac_length );

		// Verify HMAC (constant-time comparison).
		$expected_hmac = hash_hmac( 'sha256', $ciphertext, AUTH_KEY, true );

		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
			return ''; // Tampering detected.
		}

		$decrypted = openssl_decrypt( $ciphertext, $method, AUTH_KEY, 0, $iv );

		return false !== $decrypted ? $decrypted : '';
	}

	/**
	 * Check if AUTH_KEY is properly configured.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_auth_key_valid(): bool {
		return defined( 'AUTH_KEY' )
			&& AUTH_KEY !== 'put your unique phrase here'
			&& strlen( AUTH_KEY ) >= 32;
	}
}
