<?php
/**
 * Credentials Helper Trait.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Traits;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Services\EncryptionService;

/**
 * Provides shared R2 credentials retrieval logic.
 */
trait CredentialsHelperTrait {

	/**
	 * Get R2 credentials from settings.
	 *
	 * @param array|null $settings Optional settings array.
	 * @return array R2 credentials array.
	 */
	protected static function get_r2_credentials( ?array $settings = null ): array {
		if ( null === $settings ) {
			$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		}

		$encryption = new EncryptionService();

		return array(
			'account_id'        => $settings['r2_account_id'] ?? '',
			'access_key_id'     => $settings['r2_access_key_id'] ?? '',
			'secret_access_key' => $encryption->decrypt( $settings['r2_secret_access_key'] ?? '' ),
			'bucket'            => $settings['r2_bucket'] ?? '',
		);
	}
}
