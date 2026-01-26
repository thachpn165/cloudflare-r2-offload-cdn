<?php
/**
 * REST API Offload Handler class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Integrations;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use WP_REST_Request;
use WP_REST_Response;

/**
 * RestApiOffloadHandler class - handles offload endpoints.
 */
class RestApiOffloadHandler {

	/**
	 * Trigger offload for single attachment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function trigger_offload( WP_REST_Request $request ): WP_REST_Response {
		$attachment_id = (int) $request->get_param( 'id' );
		$force         = $request->get_param( 'force' );

		// Verify attachment exists.
		if ( ! RestApiHelper::verify_attachment( $attachment_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Attachment not found' ),
				404
			);
		}

		// Clear offload status if force.
		if ( $force ) {
			delete_post_meta( $attachment_id, '_cfr2_offloaded' );
		}

		// Queue for offload.
		$credentials = RestApiHelper::get_credentials();
		$r2          = new R2Client( $credentials );
		$offload     = new OffloadService( $r2 );
		$queued      = $offload->queue_offload( $attachment_id );

		if ( ! $queued ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Already offloaded or queued',
				)
			);
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'message'       => 'Queued for offload',
				'attachment_id' => $attachment_id,
			)
		);
	}

	/**
	 * Bulk offload multiple attachments.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function bulk_offload( WP_REST_Request $request ): WP_REST_Response {
		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_REST_Response(
				array( 'error' => 'No attachment IDs provided' ),
				400
			);
		}

		$queued = 0;
		global $wpdb;

		foreach ( $ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id ) {
				continue;
			}

			// Skip if already offloaded.
			if ( get_post_meta( $attachment_id, '_cfr2_offloaded', true ) ) {
				continue;
			}

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

		// Trigger queue processing.
		if ( $queued > 0 && ! as_next_scheduled_action( 'cfr2_process_queue' ) ) {
			as_schedule_single_action( time(), 'cfr2_process_queue' );
		}

		return new WP_REST_Response(
			array(
				'success'         => true,
				'queued'          => $queued,
				'total_requested' => count( $ids ),
			)
		);
	}
}
