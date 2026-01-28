<?php
/**
 * Queue Action Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Queue action constants.
 */
class QueueAction {
	public const OFFLOAD      = 'offload';
	public const RESTORE      = 'restore';
	public const DELETE_LOCAL = 'delete_local';
}
