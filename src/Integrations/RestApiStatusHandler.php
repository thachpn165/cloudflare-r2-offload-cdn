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

/**
 * RestApiStatusHandler class - handles status and stats endpoints.
 */
class RestApiStatusHandler {

	/**
	 * Get offload status for attachment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_status( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'id' );

		// Verify attachment exists.
		if ( ! RestApiHelper::verify_attachment( $attachment_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Attachment not found' ),
				404
			);
		}

		$is_offloaded = (bool) get_post_meta( $attachment_id, '_cfr2_offloaded', true );
		$r2_url       = get_post_meta( $attachment_id, '_cfr2_r2_url', true );
		$r2_key       = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		$local_url    = get_post_meta( $attachment_id, '_cfr2_local_url', true );

		// Check queue status.
		global $wpdb;
		$queue_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE attachment_id = %d
				 ORDER BY created_at DESC LIMIT 1",
				$attachment_id
			)
		);

		return new WP_REST_Response(
			array(
				'id'           => $attachment_id,
				'offloaded'    => $is_offloaded,
				'r2_url'       => $r2_url ?: null,
				'r2_key'       => $r2_key ?: null,
				'local_url'    => $local_url ?: wp_get_attachment_url( $attachment_id ),
				'queue_status' => $queue_status,
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

		// Get offload counts.
		global $wpdb;
		$offloaded = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_cfr2_offloaded' AND meta_value = '1'"
		);

		return new WP_REST_Response(
			array(
				'transformations' => array(
					'current_month' => $current_month,
					'daily'         => $daily_stats,
				),
				'offload'         => array(
					'total_offloaded' => $offloaded,
				),
			)
		);
	}
}
