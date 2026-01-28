<?php
/**
 * Rate Limiting Configuration Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Rate limiting configuration.
 */
class RateLimit {
	public const MAX_SAVES  = 10;
	public const WINDOW_SEC = 60;
}
