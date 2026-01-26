<?php
/**
 * REST API Integration Tests
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Integrations\RestApiIntegration;

/**
 * RestApiTest class - tests REST API endpoints.
 */
class RestApiTest extends TestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'cfr2/v1';

	/**
	 * RestApiIntegration instance.
	 *
	 * @var RestApiIntegration
	 */
	private RestApiIntegration $integration;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->integration = new RestApiIntegration();
	}

	/**
	 * Test RestApiIntegration can be instantiated.
	 */
	public function test_rest_api_integration_instantiation(): void {
		$this->assertInstanceOf( RestApiIntegration::class, $this->integration );
	}

	/**
	 * Test RestApiIntegration has register_routes method.
	 */
	public function test_rest_api_has_register_routes(): void {
		$this->assertTrue( method_exists( $this->integration, 'register_routes' ) );
	}

	/**
	 * Test REST API namespace is correctly set.
	 */
	public function test_rest_api_namespace(): void {
		$this->assertEquals( 'cfr2/v1', $this->namespace );
	}
}
