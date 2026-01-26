<?php
/**
 * URLRewriter Unit Tests
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Services\URLRewriter;

/**
 * URLRewriterTest class - tests URL rewriting logic.
 */
class URLRewriterTest extends TestCase {

	/**
	 * URLRewriter instance.
	 *
	 * @var URLRewriter
	 */
	private URLRewriter $rewriter;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up test settings.
		update_option(
			'cloudflare_r2_offload_cdn_settings',
			array(
				'cdn_enabled' => true,
				'cdn_url'     => 'https://cdn.example.com',
				'quality'     => 85,
			)
		);

		$this->rewriter = new URLRewriter();
	}

	/**
	 * Cleanup after tests.
	 */
	public function tearDown(): void {
		delete_option( 'cloudflare_r2_offload_cdn_settings' );
		parent::tearDown();
	}

	/**
	 * Test URLRewriter can be instantiated.
	 */
	public function test_urlrewriter_instantiation(): void {
		$this->assertInstanceOf( URLRewriter::class, $this->rewriter );
	}

	/**
	 * Test add_lazy_loading adds loading attribute.
	 */
	public function test_add_lazy_loading_attribute(): void {
		$attr = array();
		$post = new \WP_Post( (object) array( 'ID' => 1 ) );

		$result = $this->rewriter->add_lazy_loading( $attr, $post, 'medium' );

		$this->assertArrayHasKey( 'loading', $result );
		$this->assertEquals( 'lazy', $result['loading'] );
	}
}
