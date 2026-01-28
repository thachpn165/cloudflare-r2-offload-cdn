<?php
/**
 * Batch Processing Configuration Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Batch processing configuration.
 */
class BatchConfig {
	public const DEFAULT_SIZE = 25;
	public const MIN_SIZE     = 10;
	public const MAX_SIZE     = 50;
}
