<?php
/**
 * WP-CLI Commands for CloudFlare R2 Offload.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_CLI\Utils;
use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use ThachPN165\CFR2OffLoad\Services\BulkProgressService;
use ThachPN165\CFR2OffLoad\Traits\CredentialsHelperTrait;
use ThachPN165\CFR2OffLoad\Constants\MetaKeys;

/**
 * Manage CloudFlare R2 media offloading.
 *
 * ## EXAMPLES
 *
 *     # Check offload statistics
 *     wp cfr2 status
 *
 *     # Offload single attachment
 *     wp cfr2 offload 123
 *
 *     # Offload all attachments
 *     wp cfr2 offload all --batch-size=100
 */
class Commands {

	use CredentialsHelperTrait;

	/**
	 * Offload service instance.
	 *
	 * @var OffloadService
	 */
	private OffloadService $offload_service;

	/**
	 * Progress service instance.
	 *
	 * @var BulkProgressService
	 */
	private BulkProgressService $progress_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$credentials            = $this->get_r2_credentials();
		$r2_client              = new R2Client( $credentials );
		$this->offload_service  = new OffloadService( $r2_client );
		$this->progress_service = new BulkProgressService();
	}

	/**
	 * Show R2 offload statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfr2 status
	 *
	 * @subcommand status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ): void {
		$counts = $this->progress_service->get_counts();
		$total  = $counts['offloaded'] + $counts['not_offloaded'];

		$items = array(
			array(
				'Metric' => 'Total Attachments',
				'Count'  => $total,
			),
			array(
				'Metric' => 'Offloaded to R2',
				'Count'  => $counts['offloaded'],
			),
			array(
				'Metric' => 'Not Offloaded',
				'Count'  => $counts['not_offloaded'],
			),
			array(
				'Metric' => 'Disk Saveable',
				'Count'  => $counts['disk_saveable'],
			),
			array(
				'Metric' => 'Pending in Queue',
				'Count'  => $counts['pending'],
			),
		);

		Utils\format_items( 'table', $items, array( 'Metric', 'Count' ) );
	}

	/**
	 * Offload media to R2.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Attachment ID or 'all' for all attachments.
	 *
	 * [--dry-run]
	 * : Preview without making changes.
	 *
	 * [--batch-size=<number>]
	 * : Batch size for bulk operations. Default: 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfr2 offload 123
	 *     wp cfr2 offload all --batch-size=100
	 *     wp cfr2 offload all --dry-run
	 *
	 * @subcommand offload
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function offload( $args, $assoc_args ): void {
		$dry_run    = Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$batch_size = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 50 );
		$target     = $args[0] ?? null;

		if ( ! $target ) {
			WP_CLI::error( 'Specify attachment ID or "all".' );
		}

		if ( 'all' === $target ) {
			$this->offload_all( $dry_run, $batch_size );
		} else {
			$this->offload_single( (int) $target, $dry_run );
		}
	}

	/**
	 * Restore media from R2 (remove R2 metadata).
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Attachment ID or 'all' for all offloaded attachments.
	 *
	 * [--dry-run]
	 * : Preview without making changes.
	 *
	 * [--batch-size=<number>]
	 * : Batch size for bulk operations. Default: 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfr2 restore 123
	 *     wp cfr2 restore all
	 *     wp cfr2 restore all --dry-run
	 *
	 * @subcommand restore
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function restore( $args, $assoc_args ): void {
		$dry_run    = Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$batch_size = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 50 );
		$target     = $args[0] ?? null;

		if ( ! $target ) {
			WP_CLI::error( 'Specify attachment ID or "all".' );
		}

		if ( 'all' === $target ) {
			$this->restore_all( $dry_run, $batch_size );
		} else {
			$this->restore_single( (int) $target, $dry_run );
		}
	}

	/**
	 * Delete local files for offloaded media (free disk space).
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Attachment ID or 'all' for all offloaded with local files.
	 *
	 * [--dry-run]
	 * : Preview without making changes.
	 *
	 * [--batch-size=<number>]
	 * : Batch size for bulk operations. Default: 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfr2 free-space 123
	 *     wp cfr2 free-space all
	 *     wp cfr2 free-space all --dry-run
	 *
	 * @subcommand free-space
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function free_space( $args, $assoc_args ): void {
		$dry_run    = Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$batch_size = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 50 );
		$target     = $args[0] ?? null;

		if ( ! $target ) {
			WP_CLI::error( 'Specify attachment ID or "all".' );
		}

		if ( 'all' === $target ) {
			$this->free_space_all( $dry_run, $batch_size );
		} else {
			$this->free_space_single( (int) $target, $dry_run );
		}
	}

	/**
	 * Offload single attachment.
	 *
	 * @param int  $id      Attachment ID.
	 * @param bool $dry_run Dry run mode.
	 */
	private function offload_single( int $id, bool $dry_run ): void {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			WP_CLI::error( "Attachment #{$id} not found." );
		}

		if ( $this->offload_service->is_offloaded( $id ) ) {
			WP_CLI::warning( "Attachment #{$id} is already offloaded." );
			return;
		}

		if ( $dry_run ) {
			$file = get_attached_file( $id );
			WP_CLI::log( "[DRY-RUN] Would offload: #{$id} - " . basename( $file ) );
			return;
		}

		$result = $this->offload_service->offload( $id );
		if ( $result['success'] ) {
			WP_CLI::success( "Offloaded #{$id}: " . ( $result['message'] ?? 'OK' ) );
		} else {
			WP_CLI::error( "Failed #{$id}: " . ( $result['message'] ?? 'Unknown error' ) );
		}
	}

	/**
	 * Offload all not-offloaded attachments.
	 *
	 * @param bool $dry_run    Dry run mode.
	 * @param int  $batch_size Batch size.
	 */
	private function offload_all( bool $dry_run, int $batch_size ): void {
		$ids = $this->get_not_offloaded_ids();

		if ( empty( $ids ) ) {
			WP_CLI::success( 'No attachments to offload.' );
			return;
		}

		$total   = count( $ids );
		$success = 0;
		$failed  = 0;

		if ( $dry_run ) {
			WP_CLI::log( "[DRY-RUN] Would offload {$total} attachments." );
			return;
		}

		WP_CLI::log( "Offloading {$total} attachments..." );
		$progress = Utils\make_progress_bar( 'Offloading', $total );

		foreach ( array_chunk( $ids, $batch_size ) as $batch ) {
			foreach ( $batch as $id ) {
				$result = $this->offload_service->offload( $id );
				if ( $result['success'] ) {
					++$success;
				} else {
					++$failed;
				}
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( "Offloaded: {$success} success, {$failed} failed." );
	}

	/**
	 * Restore single attachment.
	 *
	 * @param int  $id      Attachment ID.
	 * @param bool $dry_run Dry run mode.
	 */
	private function restore_single( int $id, bool $dry_run ): void {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			WP_CLI::error( "Attachment #{$id} not found." );
		}

		if ( ! $this->offload_service->is_offloaded( $id ) ) {
			WP_CLI::warning( "Attachment #{$id} is not offloaded." );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::log( "[DRY-RUN] Would restore: #{$id}" );
			return;
		}

		$result = $this->offload_service->restore( $id );
		if ( $result['success'] ) {
			WP_CLI::success( "Restored #{$id}." );
		} else {
			WP_CLI::error( "Failed to restore #{$id}." );
		}
	}

	/**
	 * Restore all offloaded attachments.
	 *
	 * @param bool $dry_run    Dry run mode.
	 * @param int  $batch_size Batch size.
	 */
	private function restore_all( bool $dry_run, int $batch_size ): void {
		$ids = $this->get_offloaded_ids();

		if ( empty( $ids ) ) {
			WP_CLI::success( 'No offloaded attachments to restore.' );
			return;
		}

		$total   = count( $ids );
		$success = 0;
		$failed  = 0;

		if ( $dry_run ) {
			WP_CLI::log( "[DRY-RUN] Would restore {$total} attachments." );
			return;
		}

		WP_CLI::log( "Restoring {$total} attachments..." );
		$progress = Utils\make_progress_bar( 'Restoring', $total );

		foreach ( array_chunk( $ids, $batch_size ) as $batch ) {
			foreach ( $batch as $id ) {
				$result = $this->offload_service->restore( $id );
				if ( $result['success'] ) {
					++$success;
				} else {
					++$failed;
				}
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( "Restored: {$success} success, {$failed} failed." );
	}

	/**
	 * Free space for single attachment.
	 *
	 * @param int  $id      Attachment ID.
	 * @param bool $dry_run Dry run mode.
	 */
	private function free_space_single( int $id, bool $dry_run ): void {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			WP_CLI::error( "Attachment #{$id} not found." );
		}

		if ( ! $this->offload_service->is_offloaded( $id ) ) {
			WP_CLI::error( "Attachment #{$id} is not offloaded. Cannot delete local files." );
		}

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			WP_CLI::warning( "Attachment #{$id} has no local files." );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::log( "[DRY-RUN] Would delete local files: #{$id} - " . basename( $file ) );
			return;
		}

		$result = $this->offload_service->delete_local_files( $id );
		if ( $result['success'] ) {
			WP_CLI::success( "Deleted local files #{$id}: " . ( $result['message'] ?? 'OK' ) );
		} else {
			WP_CLI::error( "Failed #{$id}: " . ( $result['message'] ?? 'Unknown error' ) );
		}
	}

	/**
	 * Free space for all offloaded attachments with local files.
	 *
	 * @param bool $dry_run    Dry run mode.
	 * @param int  $batch_size Batch size.
	 */
	private function free_space_all( bool $dry_run, int $batch_size ): void {
		$ids = $this->get_disk_saveable_ids();

		if ( empty( $ids ) ) {
			WP_CLI::success( 'No local files to delete.' );
			return;
		}

		$total   = count( $ids );
		$success = 0;
		$failed  = 0;

		if ( $dry_run ) {
			WP_CLI::log( "[DRY-RUN] Would delete local files for {$total} attachments." );
			return;
		}

		WP_CLI::log( "Deleting local files for {$total} attachments..." );
		$progress = Utils\make_progress_bar( 'Freeing space', $total );

		foreach ( array_chunk( $ids, $batch_size ) as $batch ) {
			foreach ( $batch as $id ) {
				$result = $this->offload_service->delete_local_files( $id );
				if ( $result['success'] ) {
					++$success;
				} else {
					++$failed;
				}
				$progress->tick();
			}
		}

		$progress->finish();
		WP_CLI::success( "Freed space: {$success} success, {$failed} failed." );
	}

	/**
	 * Get IDs of not-offloaded attachments.
	 *
	 * @return array Array of attachment IDs.
	 */
	private function get_not_offloaded_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI bulk query.
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
				 AND (pm.meta_value IS NULL OR pm.meta_value != '1')",
				MetaKeys::OFFLOADED
			)
		);
	}

	/**
	 * Get IDs of offloaded attachments.
	 *
	 * @return array Array of attachment IDs.
	 */
	private function get_offloaded_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI bulk query.
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value = '1'",
				MetaKeys::OFFLOADED
			)
		);
	}

	/**
	 * Get IDs of offloaded attachments with local files (disk saveable).
	 *
	 * @return array Array of attachment IDs.
	 */
	private function get_disk_saveable_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI bulk query.
		return $wpdb->get_col(
			"SELECT attachment_id FROM {$wpdb->prefix}cfr2_offload_status
			 WHERE local_exists = 1"
		);
	}
}
