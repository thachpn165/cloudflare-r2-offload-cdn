<?php
/**
 * URL Rewriter Service class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;

/**
 * URLRewriter class - handles URL transformation for CDN.
 */
class URLRewriter implements HookableInterface {

	/**
	 * Responsive breakpoints for srcset.
	 */
	private const BREAKPOINTS = array( 320, 640, 768, 1024, 1280, 1536 );

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * CDN availability status.
	 *
	 * @var bool
	 */
	private bool $cdn_available;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings      = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$this->cdn_available = $this->check_cdn_availability();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		// Register if CDN enabled OR R2 public domain is set.
		$has_cdn    = ! empty( $this->settings['cdn_enabled'] ) && ! empty( $this->settings['cdn_url'] );
		$has_r2_pub = ! empty( $this->settings['r2_public_domain'] );

		if ( ! $has_cdn && ! $has_r2_pub ) {
			return;
		}

		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ), 99, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lazy_loading' ), 10, 3 );

		// Srcset/size transforms only when CDN Worker is enabled.
		if ( $has_cdn ) {
			add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_image_src' ), 99, 4 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'generate_srcset' ), 99, 5 );
		}
	}

	/**
	 * Rewrite attachment URL to CDN or R2 public domain.
	 *
	 * @param string $url Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string Rewritten URL.
	 */
	public function rewrite_attachment_url( string $url, int $attachment_id ): string {
		// Check if offloaded.
		$is_offloaded = get_post_meta( $attachment_id, '_cfr2_offloaded', true );
		if ( ! $is_offloaded ) {
			return $url;
		}

		// Get R2 key.
		$r2_key = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		if ( ! $r2_key ) {
			return $url;
		}

		// Priority 1: CDN with Worker (if enabled and available).
		$cdn_enabled = ! empty( $this->settings['cdn_enabled'] ) && ! empty( $this->settings['cdn_url'] );
		if ( $cdn_enabled && $this->cdn_available ) {
			// Track transformation for stats.
			$this->track_transformation( $attachment_id );

			return $this->build_cdn_url(
				$r2_key,
				array(
					'q' => $this->settings['quality'] ?? 85,
					'f' => 'auto',
				)
			);
		}

		// Priority 2: R2 public domain (no transforms, just direct URL).
		if ( ! empty( $this->settings['r2_public_domain'] ) ) {
			$r2_domain = rtrim( $this->settings['r2_public_domain'], '/' );
			return "{$r2_domain}/{$r2_key}";
		}

		// Fallback: stored local URL.
		$local_url = get_post_meta( $attachment_id, '_cfr2_local_url', true );
		return $local_url ?: $url;
	}

	/**
	 * Track transformation asynchronously.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function track_transformation( int $attachment_id ): void {
		$track_action = 'cfr2_tracked_' . $attachment_id;
		if ( ! did_action( $track_action ) ) {
			add_action(
				'shutdown',
				function () {
					StatsTracker::increment();
				}
			);
			do_action( $track_action );
		}
	}

	/**
	 * Rewrite image src with size.
	 *
	 * @param array|false $image Image data or false.
	 * @param int         $attachment_id Attachment ID.
	 * @param string|int[] $size Requested size.
	 * @param bool        $icon Whether to use icon.
	 * @return array|false Modified image data.
	 */
	public function rewrite_image_src( $image, int $attachment_id, $size, bool $icon ) {
		if ( ! $image || $icon ) {
			return $image;
		}

		$is_offloaded = get_post_meta( $attachment_id, '_cfr2_offloaded', true );
		if ( ! $is_offloaded || ! $this->cdn_available ) {
			return $image;
		}

		$r2_key = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		if ( ! $r2_key ) {
			return $image;
		}

		// Get requested dimensions.
		$width  = $image[1];
		$height = $image[2];

		$image[0] = $this->build_cdn_url(
			$r2_key,
			array(
				'w'   => $width,
				'h'   => $height,
				'q'   => $this->settings['quality'] ?? 85,
				'f'   => 'auto',
				'fit' => 'cover',
			)
		);

		return $image;
	}

	/**
	 * Generate responsive srcset with CDN URLs.
	 *
	 * @param array $sources Srcset sources.
	 * @param array $size_array Size array.
	 * @param string $image_src Image src.
	 * @param array $image_meta Image metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified sources.
	 */
	public function generate_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ): array {
		$is_offloaded = get_post_meta( $attachment_id, '_cfr2_offloaded', true );
		if ( ! $is_offloaded || ! $this->cdn_available ) {
			return $sources;
		}

		$r2_key = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		if ( ! $r2_key ) {
			return $sources;
		}

		// Generate sources for each breakpoint.
		$new_sources    = array();
		$quality        = $this->settings['quality'] ?? 85;
		$original_width = $image_meta['width'] ?? 1920;

		foreach ( self::BREAKPOINTS as $width ) {
			// Skip if larger than original.
			if ( $width > $original_width ) {
				continue;
			}

			$new_sources[ $width ] = array(
				'url'        => $this->build_cdn_url(
					$r2_key,
					array(
						'w' => $width,
						'q' => $quality,
						'f' => 'auto',
					)
				),
				'descriptor' => 'w',
				'value'      => $width,
			);
		}

		// Add original size if not in breakpoints.
		if ( ! isset( $new_sources[ $original_width ] ) ) {
			$new_sources[ $original_width ] = array(
				'url'        => $this->build_cdn_url(
					$r2_key,
					array(
						'q' => $quality,
						'f' => 'auto',
					)
				),
				'descriptor' => 'w',
				'value'      => $original_width,
			);
		}

		return $new_sources;
	}

	/**
	 * Add lazy loading attribute.
	 *
	 * @param array    $attr Attributes.
	 * @param \WP_Post $attachment Attachment post.
	 * @param string|int[] $size Size.
	 * @return array Modified attributes.
	 */
	public function add_lazy_loading( array $attr, \WP_Post $attachment, $size ): array {
		// Add loading="lazy" if not already set.
		if ( ! isset( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}

		return $attr;
	}

	/**
	 * Build CDN URL with transformation params.
	 *
	 * @param string $r2_key R2 object key.
	 * @param array  $params Transform params.
	 * @return string CDN URL.
	 */
	private function build_cdn_url( string $r2_key, array $params = array() ): string {
		$cdn_url = rtrim( $this->settings['cdn_url'], '/' );
		$url     = "{$cdn_url}/{$r2_key}";

		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		return $url;
	}

	/**
	 * Check if CDN is available (cached check).
	 *
	 * @return bool True if available.
	 */
	private function check_cdn_availability(): bool {
		// Skip check if not configured.
		if ( empty( $this->settings['cdn_url'] ) ) {
			return false;
		}

		// Check cache.
		$cache_key = 'cfr2_cdn_available';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		// Perform health check.
		$test_url = rtrim( $this->settings['cdn_url'], '/' ) . '/health';
		$response = wp_remote_head(
			$test_url,
			array(
				'timeout'   => 5,
				'sslverify' => true,
			)
		);

		$is_available = ! is_wp_error( $response ) &&
						wp_remote_retrieve_response_code( $response ) < 500;

		// Cache for 5 minutes.
		set_transient( $cache_key, $is_available ? 'yes' : 'no', 300 );

		return $is_available;
	}

	/**
	 * Force CDN availability check (clear cache).
	 */
	public static function clear_availability_cache(): void {
		delete_transient( 'cfr2_cdn_available' );
	}
}
