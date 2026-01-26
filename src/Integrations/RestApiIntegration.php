<?php
/**
 * REST API Integration class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Integrations;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;

/**
 * RestApiIntegration class - handles REST API endpoints.
 */
class RestApiIntegration implements HookableInterface {

	/**
	 * REST API namespace.
	 */
	private const NAMESPACE = 'cfr2/v1';

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// Get attachment offload status.
		register_rest_route(
			self::NAMESPACE,
			'/status/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( RestApiStatusHandler::class, 'get_status' ),
				'permission_callback' => array( RestApiHelper::class, 'check_read_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => array( RestApiHelper::class, 'validate_attachment_id' ),
					),
				),
			)
		);

		// Trigger offload for attachment.
		register_rest_route(
			self::NAMESPACE,
			'/offload/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( RestApiOffloadHandler::class, 'trigger_offload' ),
				'permission_callback' => array( RestApiHelper::class, 'check_write_permission' ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'validate_callback' => array( RestApiHelper::class, 'validate_attachment_id' ),
					),
					'force' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Get usage stats.
		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( RestApiStatusHandler::class, 'get_stats' ),
				'permission_callback' => array( RestApiHelper::class, 'check_read_permission' ),
				'args'                => array(
					'period' => array(
						'default' => 'month',
						'enum'    => array( 'week', 'month' ),
					),
				),
			)
		);

		// Bulk offload.
		register_rest_route(
			self::NAMESPACE,
			'/bulk-offload',
			array(
				'methods'             => 'POST',
				'callback'            => array( RestApiOffloadHandler::class, 'bulk_offload' ),
				'permission_callback' => array( RestApiHelper::class, 'check_admin_permission' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);
	}
}
