<?php
/**
 * Media Library Extension class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;
use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use ThachPN165\CFR2OffLoad\Traits\CredentialsHelperTrait;

/**
 * MediaLibraryExtension class - extends Media Library with R2 functionality.
 */
class MediaLibraryExtension implements HookableInterface {

	use CredentialsHelperTrait;

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		// Add column.
		add_filter( 'manage_media_columns', array( $this, 'add_status_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_status_column' ), 10, 2 );

		// Add row actions.
		add_filter( 'media_row_actions', array( $this, 'add_row_actions' ), 10, 2 );

		// Add bulk actions.
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'show_bulk_action_notices' ) );

		// AJAX handlers for row actions.
		add_action( 'wp_ajax_cfr2_offload_single', array( $this, 'ajax_offload_single' ) );
		add_action( 'wp_ajax_cfr2_restore_single', array( $this, 'ajax_restore_single' ) );
	}

	/**
	 * Add R2 Status column to Media Library.
	 *
	 * @param array $columns Columns array.
	 * @return array Modified columns array.
	 */
	public function add_status_column( array $columns ): array {
		$columns['cfr2_status'] = __( 'R2 Status', 'cloudflare-r2-offload-cdn' );
		return $columns;
	}

	/**
	 * Render R2 Status column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 */
	public function render_status_column( string $column_name, int $post_id ): void {
		if ( 'cfr2_status' !== $column_name ) {
			return;
		}

		$is_offloaded = get_post_meta( $post_id, '_cfr2_offloaded', true );
		$is_pending   = $this->is_pending( $post_id );

		if ( $is_offloaded ) {
			echo '<span class="cfr2-status cfr2-offloaded" title="' . esc_attr__( 'Offloaded to R2', 'cloudflare-r2-offload-cdn' ) . '">';
			echo '<span class="dashicons dashicons-cloud"></span> ' . esc_html__( 'R2', 'cloudflare-r2-offload-cdn' );
			echo '</span>';
		} elseif ( $is_pending ) {
			echo '<span class="cfr2-status cfr2-pending" title="' . esc_attr__( 'Queued for offload', 'cloudflare-r2-offload-cdn' ) . '">';
			echo '<span class="dashicons dashicons-clock"></span> ' . esc_html__( 'Pending', 'cloudflare-r2-offload-cdn' );
			echo '</span>';
		} else {
			echo '<span class="cfr2-status cfr2-local" title="' . esc_attr__( 'Stored locally', 'cloudflare-r2-offload-cdn' ) . '">';
			echo '<span class="dashicons dashicons-admin-site"></span> ' . esc_html__( 'Local', 'cloudflare-r2-offload-cdn' );
			echo '</span>';
		}
	}

	/**
	 * Add row actions to Media Library.
	 *
	 * @param array    $actions Row actions array.
	 * @param \WP_Post $post    Post object.
	 * @return array Modified row actions array.
	 */
	public function add_row_actions( array $actions, \WP_Post $post ): array {
		if ( 'attachment' !== $post->post_type ) {
			return $actions;
		}

		$is_offloaded = get_post_meta( $post->ID, '_cfr2_offloaded', true );
		$nonce        = wp_create_nonce( 'cfr2_media_action_' . $post->ID );

		if ( $is_offloaded ) {
			$actions['cfr2_restore'] = sprintf(
				'<a href="%s" class="cfr2-restore">%s</a>',
				esc_url( admin_url( "admin-ajax.php?action=cfr2_restore_single&id={$post->ID}&nonce={$nonce}" ) ),
				esc_html__( 'Restore to Local', 'cloudflare-r2-offload-cdn' )
			);
			$actions['cfr2_reoffload'] = sprintf(
				'<a href="%s" class="cfr2-reoffload">%s</a>',
				esc_url( admin_url( "admin-ajax.php?action=cfr2_offload_single&id={$post->ID}&nonce={$nonce}&force=1" ) ),
				esc_html__( 'Re-offload', 'cloudflare-r2-offload-cdn' )
			);
		} else {
			$actions['cfr2_offload'] = sprintf(
				'<a href="%s" class="cfr2-offload">%s</a>',
				esc_url( admin_url( "admin-ajax.php?action=cfr2_offload_single&id={$post->ID}&nonce={$nonce}" ) ),
				esc_html__( 'Offload to R2', 'cloudflare-r2-offload-cdn' )
			);
		}

		return $actions;
	}

	/**
	 * Add bulk actions to Media Library.
	 *
	 * @param array $actions Bulk actions array.
	 * @return array Modified bulk actions array.
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['cfr2_bulk_offload'] = __( 'Offload to R2', 'cloudflare-r2-offload-cdn' );
		$actions['cfr2_bulk_restore'] = __( 'Restore to Local', 'cloudflare-r2-offload-cdn' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Action name.
	 * @param array  $post_ids     Post IDs array.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'cfr2_bulk_offload', 'cfr2_bulk_restore' ), true ) ) {
			return $redirect_url;
		}

		$count = 0;
		foreach ( $post_ids as $attachment_id ) {
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'attachment_id' => $attachment_id,
					'action'        => 'cfr2_bulk_offload' === $action ? 'offload' : 'restore',
					'status'        => 'pending',
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
			++$count;
		}

		// Schedule queue processing.
		if ( ! as_next_scheduled_action( 'cfr2_process_queue' ) ) {
			as_schedule_single_action( time(), 'cfr2_process_queue' );
		}

		return add_query_arg( 'cfr2_queued', $count, $redirect_url );
	}

	/**
	 * Show bulk action notices.
	 */
	public function show_bulk_action_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['cfr2_queued'] ) ) {
			return;
		}

		$count = absint( $_GET['cfr2_queued'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %d: number of files */
				esc_html( _n( '%d file queued for processing.', '%d files queued for processing.', $count, 'cloudflare-r2-offload-cdn' ) ),
				$count
			)
		);
	}

	/**
	 * AJAX handler for single offload.
	 */
	public function ajax_offload_single(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id    = absint( $_GET['id'] ?? 0 );
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, 'cfr2_media_action_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cloudflare-r2-offload-cdn' ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) );
		}

		$credentials = self::get_r2_credentials();
		$r2          = new R2Client( $credentials );
		$offload     = new OffloadService( $r2 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['force'] ) ) {
			delete_post_meta( $id, '_cfr2_offloaded' );
		}

		$result = $offload->offload( $id );

		if ( $result['success'] ) {
			wp_safe_redirect( add_query_arg( 'cfr2_offloaded', 1, wp_get_referer() ) );
		} else {
			wp_safe_redirect( add_query_arg( 'cfr2_error', rawurlencode( $result['message'] ), wp_get_referer() ) );
		}
		exit;
	}

	/**
	 * AJAX handler for single restore.
	 */
	public function ajax_restore_single(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id    = absint( $_GET['id'] ?? 0 );
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, 'cfr2_media_action_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'cloudflare-r2-offload-cdn' ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) );
		}

		$credentials = self::get_r2_credentials();
		$r2          = new R2Client( $credentials );
		$offload     = new OffloadService( $r2 );
		$offload->restore( $id );

		wp_safe_redirect( add_query_arg( 'cfr2_restored', 1, wp_get_referer() ) );
		exit;
	}

	/**
	 * Check if attachment is pending in queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if pending, false otherwise.
	 */
	private function is_pending( int $attachment_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE attachment_id = %d AND status IN ('pending', 'processing')",
				$attachment_id
			)
		);
	}
}
