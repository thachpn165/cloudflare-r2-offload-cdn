<?php
/**
 * Deactivation Handler class.
 *
 * Adds confirmation dialog when deactivating the plugin.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Database\Schema;
use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;

/**
 * DeactivationHandler class - handles deactivation with cleanup option.
 */
class DeactivationHandler implements HookableInterface {

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer-plugins.php', array( $this, 'add_deactivation_dialog' ) );
		add_action( 'wp_ajax_cfr2_cleanup_data', array( $this, 'ajax_cleanup_data' ) );
	}

	/**
	 * Add deactivation dialog JavaScript.
	 */
	public function add_deactivation_dialog(): void {
		$plugin_file = 'cloudflare-r2-offload-cdn/cloudflare-r2-offload-cdn.php';
		$nonce       = wp_create_nonce( 'cfr2_cleanup_nonce' );
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var pluginRow = $('tr[data-plugin="<?php echo esc_js( $plugin_file ); ?>"]');
			var deactivateLink = pluginRow.find('.deactivate a');
			var originalHref = deactivateLink.attr('href');

			deactivateLink.on('click', function(e) {
				e.preventDefault();

				var confirmed = confirm(
					'Do you want to delete all plugin data?\n\n' +
					'• Click OK to DELETE all settings, database tables, and media metadata\n' +
					'• Click Cancel to keep data (you can reinstall later)\n\n' +
					'Note: Files on R2 storage will NOT be deleted.'
				);

				if (confirmed) {
					// User wants to delete data - call cleanup AJAX
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'cfr2_cleanup_data',
							nonce: '<?php echo esc_js( $nonce ); ?>'
						},
						success: function() {
							window.location.href = originalHref;
						},
						error: function() {
							window.location.href = originalHref;
						}
					});
				} else {
					// User wants to keep data - just deactivate
					window.location.href = originalHref;
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to cleanup all plugin data.
	 */
	public function ajax_cleanup_data(): void {
		check_ajax_referer( 'cfr2_cleanup_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		global $wpdb;

		// 1. Securely wipe sensitive data.
		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		if ( ! empty( $settings['r2_secret_access_key'] ) ) {
			$settings['r2_secret_access_key'] = str_repeat( '0', strlen( $settings['r2_secret_access_key'] ) );
		}
		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = str_repeat( '0', strlen( $settings['api_key'] ) );
		}

		// 2. Remove plugin options.
		delete_option( 'cloudflare_r2_offload_cdn_settings' );
		delete_option( 'cfr2_db_version' );

		// 3. Drop custom database tables.
		Schema::drop_tables();

		// 4. Remove all post meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				'_cfr2_%'
			)
		);

		// 5. Clean up transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_cfr2_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_cfr2_' ) . '%'
			)
		);

		// 6. Clear scheduled cron events.
		wp_clear_scheduled_hook( 'cfr2_process_queue' );

		wp_send_json_success( 'Data cleaned' );
	}
}
