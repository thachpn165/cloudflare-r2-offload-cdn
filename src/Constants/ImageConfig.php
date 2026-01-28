<?php
/**
 * Image Configuration Constants.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Image configuration constants.
 */
class ImageConfig {
	public const BREAKPOINTS     = array( 320, 640, 768, 1024, 1280, 1536 );
	public const DEFAULT_QUALITY = 85;
	public const DEFAULT_WIDTH   = 1920;
	public const MOBILE_BP       = 640;
	public const TABLET_BP       = 1024;
}
