<?php
/**
 * Error Messages Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Error message constants.
 */
class ErrorMessages {
	public const SECURITY_FAILED      = 'Security check failed.';
	public const PERMISSION_DENIED    = 'Permission denied.';
	public const R2_NOT_CONFIGURED    = 'R2 credentials not configured.';
	public const INVALID_ITEM_ID      = 'Invalid item ID.';
	public const QUEUE_FULL           = 'Queue is full. Please try again later.';
	public const MISSING_FIELD        = 'Missing required field: %s';
	public const CONNECTION_FAILED    = 'Connection test failed.';
	public const SAVE_FAILED          = 'Failed to save settings. Please try again.';
	public const TOO_MANY_REQUESTS    = 'Too many requests. Please try again later.';
}
