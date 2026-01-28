<?php
/**
 * Plugin Unit Tests.
 *
 * @package CFR2OffLoad\Tests\Unit
 */

namespace ThachPN165\CFR2OffLoad\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Plugin;

/**
 * Test Plugin class.
 */
class PluginTest extends TestCase {

	/**
	 * Test singleton returns same instance.
	 *
	 * @group skip
	 */
	public function test_singleton_returns_same_instance(): void {
		$this->markTestSkipped( 'Requires full WordPress environment' );
	}

	/**
	 * Test plugin constants are defined.
	 */
	public function test_plugin_constants_defined(): void {
		$this->assertTrue( defined( 'CFR2_VERSION' ) );
		$this->assertTrue( defined( 'CFR2_PATH' ) );
		$this->assertTrue( defined( 'CFR2_URL' ) );
	}

	/**
	 * Test plugin version is valid semver.
	 */
	public function test_plugin_version_is_valid(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', CFR2_VERSION );
	}
}
