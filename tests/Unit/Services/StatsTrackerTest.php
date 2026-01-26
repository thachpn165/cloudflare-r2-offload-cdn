<?php
/**
 * StatsTracker Unit Tests
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ThachPN165\CFR2OffLoad\Services\StatsTracker;

/**
 * StatsTrackerTest class - tests stats tracking logic.
 */
class StatsTrackerTest extends TestCase {

	/**
	 * Test StatsTracker class exists.
	 */
	public function test_stats_tracker_class_exists(): void {
		$this->assertTrue( class_exists( StatsTracker::class ) );
	}

	/**
	 * Test static methods are callable.
	 */
	public function test_static_methods_callable(): void {
		$this->assertTrue( method_exists( StatsTracker::class, 'increment' ) );
		$this->assertTrue( method_exists( StatsTracker::class, 'get_monthly_summary' ) );
		$this->assertTrue( method_exists( StatsTracker::class, 'cleanup_old_stats' ) );
	}
}
