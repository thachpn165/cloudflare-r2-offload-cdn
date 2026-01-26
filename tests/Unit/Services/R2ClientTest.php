<?php
/**
 * R2Client Unit Tests
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Services\R2Client;

/**
 * R2ClientTest class - tests R2Client service.
 */
class R2ClientTest extends TestCase {

	/**
	 * Test credentials.
	 *
	 * @var array
	 */
	private array $test_credentials;

	/**
	 * Setup test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->test_credentials = array(
			'account_id'        => 'test_account_123',
			'access_key_id'     => 'test_access_key',
			'secret_access_key' => 'test_secret_key',
			'bucket'            => 'test-bucket',
		);
	}

	/**
	 * Test constructor sets credentials correctly.
	 */
	public function test_constructor_sets_credentials(): void {
		$client = new R2Client( $this->test_credentials );

		// Use reflection to verify private properties.
		$reflection  = new \ReflectionClass( $client );
		$account_prop = $reflection->getProperty( 'account_id' );
		$account_prop->setAccessible( true );

		$this->assertEquals( 'test_account_123', $account_prop->getValue( $client ) );

		$bucket_prop = $reflection->getProperty( 'bucket' );
		$bucket_prop->setAccessible( true );

		$this->assertEquals( 'test-bucket', $bucket_prop->getValue( $client ) );
	}

	/**
	 * Test build_r2_url generates correct format.
	 */
	public function test_build_r2_url_format(): void {
		$client = new R2Client( $this->test_credentials );

		$reflection = new \ReflectionClass( $client );
		$method     = $reflection->getMethod( 'build_r2_url' );
		$method->setAccessible( true );

		$url = $method->invoke( $client, 'uploads/2026/01/image.jpg' );

		$this->assertStringContainsString( 'test-bucket', $url );
		$this->assertStringContainsString( 'test_account_123', $url );
		$this->assertStringContainsString( 'uploads/2026/01/image.jpg', $url );
		$this->assertStringStartsWith( 'https://', $url );
	}

	/**
	 * Test test_connection returns expected array structure.
	 */
	public function test_test_connection_returns_array(): void {
		// Simplified test - in real environment, mock AWS SDK.
		$client = new R2Client( $this->test_credentials );
		$result = $client->test_connection();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsBool( $result['success'] );
		$this->assertIsString( $result['message'] );
	}
}
