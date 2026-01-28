<?php
/**
 * Cache Duration Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Cache durations in seconds.
 */
class CacheDuration {
	public const STATS_CACHE = 300; // 5 minutes.
	public const ERROR_TTL   = 30;
	public const CANCEL_TTL  = 300;
}
