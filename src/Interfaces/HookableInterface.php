<?php
/**
 * Hookable Interface.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Interface for classes that register WordPress hooks.
 */
interface HookableInterface {

	/**
	 * Register hooks with WordPress.
	 */
	public function register_hooks(): void;
}
