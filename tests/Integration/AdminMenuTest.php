<?php
/**
 * AdminMenu Integration Tests.
 *
 * @package CFR2OffLoad\Tests\Integration
 */

namespace ThachPN165\CFR2OffLoad\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Admin\AdminMenu;

/**
 * Test AdminMenu class.
 */
class AdminMenuTest extends TestCase {

	/**
	 * Admin menu instance.
	 *
	 * @var AdminMenu
	 */
	private AdminMenu $admin_menu;

	/**
	 * Set up test.
	 */
	protected function setUp(): void {
		$this->admin_menu = new AdminMenu();
	}

	/**
	 * Test admin menu can be instantiated.
	 */
	public function test_admin_menu_can_be_instantiated(): void {
		$this->assertInstanceOf( AdminMenu::class, $this->admin_menu );
	}

	/**
	 * Test register settings is called.
	 */
	public function test_register_settings(): void {
		// Register settings should be callable without error
		$this->admin_menu->register_settings();
		$this->assertTrue( true );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings(): void {
		$method = new \ReflectionMethod( AdminMenu::class, 'get_default_settings' );
		$method->setAccessible( true );

		$defaults = $method->invoke( $this->admin_menu );

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'r2_account_id', $defaults );
		$this->assertArrayHasKey( 'r2_access_key_id', $defaults );
		$this->assertArrayHasKey( 'batch_size', $defaults );
		$this->assertEquals( 25, $defaults['batch_size'] );
	}
}
