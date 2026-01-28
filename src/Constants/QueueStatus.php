<?php
/**
 * Queue Status Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Queue status constants.
 */
class QueueStatus {
	public const PENDING    = 'pending';
	public const PROCESSING = 'processing';
	public const COMPLETED  = 'completed';
	public const FAILED     = 'failed';
	public const CANCELLED  = 'cancelled';
}
