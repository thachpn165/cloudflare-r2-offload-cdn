<?php
/**
 * REST API Status Handler class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Integrations;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use ThachPN165\CFR2OffLoad\Constants\MetaKeys;
use ThachPN165\CFR2OffLoad\Services\SettingsService;

/**
 * RestApiStatusHandler class - handles read-only status endpoints.
 */
class RestApiStatusHandler {

	/**
	 * Get attachment info with URLs.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_attachment( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'id' );

		// Verify attachment exists.
		if ( ! RestApiHelper::verify_attachment( $attachment_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Attachment not found' ),
				404
			);
		}

		$is_offloaded  = (bool) get_post_meta( $attachment_id, MetaKeys::OFFLOADED, true );
		$r2_url        = get_post_meta( $attachment_id, MetaKeys::R2_URL, true );
		$local_deleted = (bool) get_post_meta( $attachment_id, MetaKeys::LOCAL_DELETED, true );

		// Get local URL.
		$local_url = null;
		if ( ! $local_deleted ) {
			$local_url = wp_get_attachment_url( $attachment_id );
		}

		// Build CDN URL if enabled.
		$cdn_url  = null;
		$settings = SettingsService::get_settings();
		if ( $is_offloaded && ! empty( $settings['cdn_enabled'] ) && ! empty( $settings['cdn_url'] ) ) {
			$cdn_url = $r2_url ? str_replace(
				$settings['r2_public_domain'] ?? '',
				rtrim( $settings['cdn_url'], '/' ),
				$r2_url
			) : null;
		}

		// Get attachment metadata.
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$file_size = null;
		$mime_type = get_post_mime_type( $attachment_id );

		if ( ! $local_deleted ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
			}
		}

		return new WP_REST_Response(
			array(
				'id'          => $attachment_id,
				'offloaded'   => $is_offloaded,
				'urls'        => array(
					'local' => $local_url,
					'r2'    => $r2_url ?: null,
					'cdn'   => $cdn_url,
				),
				'local_exists' => ! $local_deleted,
				'mime_type'    => $mime_type,
				'file_size'    => $file_size,
				'width'        => $metadata['width'] ?? null,
				'height'       => $metadata['height'] ?? null,
			)
		);
	}

	/**
	 * Get usage statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$period        = $request->get_param( 'period' );
		$days          = 'week' === $period ? 7 : 30;
		$daily_stats   = \ThachPN165\CFR2OffLoad\Services\StatsTracker::get_daily_stats( $days );
		$current_month = \ThachPN165\CFR2OffLoad\Services\StatsTracker::get_current_month_transformations();

		// Get offload counts from status table.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating stats requires fresh data.
		$counts = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN local_exists = 1 THEN 1 ELSE 0 END) as with_local,
				SUM(CASE WHEN local_exists = 0 THEN 1 ELSE 0 END) as r2_only
			FROM {$wpdb->prefix}cfr2_offload_status"
		);

		return new WP_REST_Response(
			array(
				'transformations' => array(
					'current_month' => $current_month,
					'daily'         => $daily_stats,
				),
				'offload'         => array(
					'total_offloaded' => (int) ( $counts->total ?? 0 ),
					'with_local'      => (int) ( $counts->with_local ?? 0 ),
					'r2_only'         => (int) ( $counts->r2_only ?? 0 ),
				),
			)
		);
	}
}
