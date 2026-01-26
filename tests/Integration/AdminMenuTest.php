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
	 * Test settings sanitization.
	 */
	public function test_settings_sanitization(): void {
		$input = array(
			'enable_feature' => '1',
			'api_key'        => '<script>alert("xss")</script>',
		);

		$method = new \ReflectionMethod( AdminMenu::class, 'sanitize_settings' );
		$method->setAccessible( true );

		$sanitized = $method->invoke( $this->admin_menu, $input );

		$this->assertEquals( 1, $sanitized['enable_feature'] );
		$this->assertStringNotContainsString( '<script>', $sanitized['api_key'] );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings(): void {
		$method = new \ReflectionMethod( AdminMenu::class, 'get_default_settings' );
		$method->setAccessible( true );

		$defaults = $method->invoke( $this->admin_menu );

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'enable_feature', $defaults );
		$this->assertArrayHasKey( 'api_key', $defaults );
	}
}
