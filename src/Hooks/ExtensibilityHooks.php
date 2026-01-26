<?php
/**
 * Extensibility hooks for third-party developers.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Extensibility hooks for third-party developers.
 *
 * Actions:
 * - cfr2_before_offload      (int $attachment_id)
 * - cfr2_after_offload       (int $attachment_id, array $result)
 * - cfr2_before_restore      (int $attachment_id)
 * - cfr2_after_restore       (int $attachment_id, array $result)
 *
 * Filters:
 * - cfr2_cdn_url             (string $url, int $attachment_id, array $params)
 * - cfr2_transform_params    (array $params, int $attachment_id)
 * - cfr2_should_offload      (bool $should, int $attachment_id)
 * - cfr2_r2_key              (string $key, int $attachment_id)
 * - cfr2_allowed_mime_types  (array $types)
 */
class ExtensibilityHooks {

	/**
	 * Get allowed MIME types for offload.
	 *
	 * @return array Allowed MIME types.
	 */
	public static function get_allowed_mime_types(): array {
		$defaults = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'video/mp4',
			'video/webm',
			'application/pdf',
		);

		/**
		 * Filter allowed MIME types for offload.
		 *
		 * @param array $types Default allowed MIME types.
		 */
		return apply_filters( 'cfr2_allowed_mime_types', $defaults );
	}

	/**
	 * Check if attachment should be offloaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if should offload.
	 */
	public static function should_offload( int $attachment_id ): bool {
		$should = true;

		// Check MIME type.
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::get_allowed_mime_types(), true ) ) {
			$should = false;
		}

		/**
		 * Filter whether attachment should be offloaded.
		 *
		 * @param bool $should Whether to offload.
		 * @param int $attachment_id Attachment ID.
		 */
		return apply_filters( 'cfr2_should_offload', $should, $attachment_id );
	}

	/**
	 * Get R2 key for attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path File path.
	 * @return string R2 key.
	 */
	public static function get_r2_key( int $attachment_id, string $file_path ): string {
		$upload_dir = wp_upload_dir();
		$key        = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

		/**
		 * Filter R2 key for attachment.
		 *
		 * @param string $key Generated R2 key.
		 * @param int $attachment_id Attachment ID.
		 */
		return apply_filters( 'cfr2_r2_key', $key, $attachment_id );
	}

	/**
	 * Build CDN URL with filters.
	 *
	 * @param string $base_url Base CDN URL.
	 * @param string $r2_key R2 object key.
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $params Transform params.
	 * @return string CDN URL.
	 */
	public static function build_cdn_url( string $base_url, string $r2_key, int $attachment_id, array $params ): string {
		/**
		 * Filter transformation parameters.
		 *
		 * @param array $params Transform params (q, f, w, h, fit).
		 * @param int $attachment_id Attachment ID.
		 */
		$params = apply_filters( 'cfr2_transform_params', $params, $attachment_id );

		$url = rtrim( $base_url, '/' ) . '/' . $r2_key;
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		/**
		 * Filter final CDN URL.
		 *
		 * @param string $url Generated CDN URL.
		 * @param int $attachment_id Attachment ID.
		 * @param array $params Transform params used.
		 */
		return apply_filters( 'cfr2_cdn_url', $url, $attachment_id, $params );
	}

	/**
	 * Fire before offload action.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function before_offload( int $attachment_id ): void {
		/**
		 * Action before attachment offload.
		 *
		 * @param int $attachment_id Attachment ID.
		 */
		do_action( 'cfr2_before_offload', $attachment_id );
	}

	/**
	 * Fire after offload action.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $result Offload result.
	 */
	public static function after_offload( int $attachment_id, array $result ): void {
		/**
		 * Action after attachment offload.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param array $result Offload result with success, url, etc.
		 */
		do_action( 'cfr2_after_offload', $attachment_id, $result );
	}

	/**
	 * Fire before restore action.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function before_restore( int $attachment_id ): void {
		/**
		 * Action before attachment restore.
		 *
		 * @param int $attachment_id Attachment ID.
		 */
		do_action( 'cfr2_before_restore', $attachment_id );
	}

	/**
	 * Fire after restore action.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $result Restore result.
	 */
	public static function after_restore( int $attachment_id, array $result ): void {
		/**
		 * Action after attachment restore.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param array $result Restore result.
		 */
		do_action( 'cfr2_after_restore', $attachment_id, $result );
	}
}
