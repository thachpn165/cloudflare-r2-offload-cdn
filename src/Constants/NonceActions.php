<?php
/**
 * Nonce Actions Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Nonce action names for AJAX security.
 */
class NonceActions {
	public const SETTINGS = 'cfr2_settings_nonce';
	public const BULK     = 'cfr2_bulk_nonce';
	public const WORKER   = 'cfr2_worker_nonce';
	public const ACTIVITY = 'cfr2_activity_nonce';
	public const MEDIA    = 'cfr2_media_action_';

	/**
	 * Legacy nonce for backward compatibility.
	 * Used during transition period.
	 */
	public const LEGACY = 'cloudflare_r2_offload_cdn_save_settings';
}
