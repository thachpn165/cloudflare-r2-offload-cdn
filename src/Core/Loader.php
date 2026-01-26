<?php
/**
 * Hook Loader class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Loader class - manages hooks and filters.
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array
	 */
	private array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array
	 */
	private array $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string $hook      Hook name.
	 * @param object $component Component instance.
	 * @param string $callback  Callback method.
	 * @param int    $priority  Priority.
	 * @param int    $args      Number of arguments.
	 */
	public function add_action( string $hook, $component, string $callback, int $priority = 10, int $args = 1 ): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string $hook      Hook name.
	 * @param object $component Component instance.
	 * @param string $callback  Callback method.
	 * @param int    $priority  Priority.
	 * @param int    $args      Number of arguments.
	 */
	public function add_filter( string $hook, $component, string $callback, int $priority = 10, int $args = 1 ): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['args']
			);
		}
	}
}
