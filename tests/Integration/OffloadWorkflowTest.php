<?php
/**
 * Offload Workflow Integration Tests
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\R2Client;

/**
 * OffloadWorkflowTest class - tests offload workflow integration.
 */
class OffloadWorkflowTest extends TestCase {

	/**
	 * OffloadService instance.
	 *
	 * @var OffloadService
	 */
	private OffloadService $service;

	/**
	 * Queue table name.
	 *
	 * @var string
	 */
	private string $queue_table;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock R2Client.
		$r2_mock = $this->createMock( R2Client::class );
		$r2_mock->method( 'test_connection' )->willReturn( array( 'success' => true ) );

		$this->service = new OffloadService( $r2_mock );
	}

	/**
	 * Test OffloadService can be instantiated.
	 */
	public function test_offload_service_instantiation(): void {
		$this->assertInstanceOf( OffloadService::class, $this->service );
	}

	/**
	 * Test OffloadService has required methods.
	 */
	public function test_offload_service_has_methods(): void {
		$this->assertTrue( method_exists( $this->service, 'queue_offload' ) );
		$this->assertTrue( method_exists( $this->service, 'is_offloaded' ) );
		$this->assertTrue( method_exists( $this->service, 'offload' ) );
		$this->assertTrue( method_exists( $this->service, 'restore' ) );
		$this->assertTrue( method_exists( $this->service, 'get_r2_url' ) );
		$this->assertTrue( method_exists( $this->service, 'get_local_url' ) );
	}
}
