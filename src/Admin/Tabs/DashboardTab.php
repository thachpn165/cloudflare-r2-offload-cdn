<?php
/**
 * Dashboard Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Admin\Widgets\StatsWidget;

/**
 * DashboardTab class - renders the dashboard tab content.
 */
class DashboardTab {

	/**
	 * Render the dashboard tab.
	 */
	public static function render(): void {
		?>
		<div class="cloudflare-r2-offload-cdn-tab-content active" id="tab-dashboard">
			<h2><?php esc_html_e( 'Welcome to CloudFlare R2 Offload My Plugin CDN', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'A powerful WordPress plugin boilerplate with modern development practices.', 'cloudflare-r2-offload-cdn' ); ?></p>

			<?php
			self::render_notices();
			self::render_usage_statistics();
			self::render_quick_stats();
			self::render_usage_guides();
			?>
		</div>
		<?php
	}

	/**
	 * Render quick stats section.
	 */
	private static function render_quick_stats(): void {
		global $wpdb;

		// Get stats.
		$total_attachments = wp_count_posts( 'attachment' );
		$total_count       = $total_attachments->inherit ?? 0;

		$offloaded_count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id)
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_cfr2_offloaded' AND meta_value = '1'"
		);

		$pending_count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id)
			 FROM {$wpdb->prefix}cfr2_offload_queue
			 WHERE status IN ('pending', 'processing')"
		);

		$local_count = $total_count - $offloaded_count - $pending_count;
		?>
		<div class="settings-section cfr2-quick-stats">
			<h3><?php esc_html_e( 'Media Overview', 'cloudflare-r2-offload-cdn' ); ?></h3>

			<div class="cfr2-stats-row">
				<div class="cfr2-stat">
					<span class="cfr2-stat-value"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></span>
					<span class="cfr2-stat-label"><?php esc_html_e( 'Total Media', 'cloudflare-r2-offload-cdn' ); ?></span>
				</div>
				<div class="cfr2-stat">
					<span class="cfr2-stat-value"><?php echo esc_html( number_format_i18n( $offloaded_count ) ); ?></span>
					<span class="cfr2-stat-label"><?php esc_html_e( 'Offloaded', 'cloudflare-r2-offload-cdn' ); ?></span>
				</div>
				<div class="cfr2-stat">
					<span class="cfr2-stat-value"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></span>
					<span class="cfr2-stat-label"><?php esc_html_e( 'Pending', 'cloudflare-r2-offload-cdn' ); ?></span>
				</div>
				<div class="cfr2-stat">
					<span class="cfr2-stat-value"><?php echo esc_html( number_format_i18n( $local_count ) ); ?></span>
					<span class="cfr2-stat-label"><?php esc_html_e( 'Local', 'cloudflare-r2-offload-cdn' ); ?></span>
				</div>
			</div>

			<p style="margin-top: 16px; text-align: center;">
				<a href="#" class="button" data-tab="bulk-actions" id="goto-bulk-actions">
					<?php esc_html_e( 'Go to Bulk Actions', 'cloudflare-r2-offload-cdn' ); ?> &rarr;
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render usage statistics section.
	 */
	private static function render_usage_statistics(): void {
		?>
		<div class="settings-section cfr2-stats-section">
			<h3><?php esc_html_e( 'Usage Statistics', 'cloudflare-r2-offload-cdn' ); ?></h3>
			<?php StatsWidget::render(); ?>
		</div>
		<?php
	}

	/**
	 * Render dashboard notices/announcements.
	 */
	private static function render_notices(): void {
		$notices = self::get_notices();
		if ( empty( $notices ) ) {
			return;
		}
		?>
		<div class="cloudflare-r2-offload-cdn-dashboard-notices">
			<?php foreach ( $notices as $notice ) : ?>
				<div class="cloudflare-r2-offload-cdn-notice <?php echo esc_attr( $notice['type'] ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $notice['icon'] ); ?>"></span>
					<div class="cloudflare-r2-offload-cdn-notice-content">
						<strong><?php echo esc_html( $notice['title'] ); ?></strong>
						<p><?php echo esc_html( $notice['message'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get dashboard notices.
	 *
	 * @return array Notices array.
	 */
	private static function get_notices(): array {
		$notices  = array();
		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );

		if ( empty( $settings['enable_feature'] ) ) {
			$notices[] = array(
				'type'    => 'info',
				'icon'    => 'info',
				'title'   => __( 'Getting Started', 'cloudflare-r2-offload-cdn' ),
				'message' => __( 'Enable the main feature in General settings to get started.', 'cloudflare-r2-offload-cdn' ),
			);
		}

		return $notices;
	}

	/**
	 * Render usage guides accordions.
	 */
	private static function render_usage_guides(): void {
		$guides = self::get_usage_guides();
		?>
		<div class="cloudflare-r2-offload-cdn-guides">
			<h3><?php esc_html_e( 'Usage Guides', 'cloudflare-r2-offload-cdn' ); ?></h3>
			<div class="cloudflare-r2-offload-cdn-accordion">
				<?php foreach ( $guides as $guide ) : ?>
					<div class="cloudflare-r2-offload-cdn-accordion-item">
						<button type="button" class="cloudflare-r2-offload-cdn-accordion-header">
							<span class="dashicons dashicons-<?php echo esc_attr( $guide['icon'] ); ?>"></span>
							<span class="title"><?php echo esc_html( $guide['title'] ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
						</button>
						<div class="cloudflare-r2-offload-cdn-accordion-content">
							<?php echo wp_kses_post( $guide['content'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get usage guides content.
	 *
	 * @return array Guides array.
	 */
	private static function get_usage_guides(): array {
		return array(
			array(
				'icon'    => 'book',
				'title'   => __( 'Quick Start Guide', 'cloudflare-r2-offload-cdn' ),
				'content' => '<ol>
					<li>' . esc_html__( 'Go to General tab and enable the main feature.', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . esc_html__( 'Configure your preferred settings.', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . esc_html__( 'Click Save Settings to apply changes.', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>',
			),
			array(
				'icon'    => 'admin-generic',
				'title'   => __( 'Advanced Configuration', 'cloudflare-r2-offload-cdn' ),
				'content' => '<p>' . esc_html__( 'The Advanced tab provides options for power users:', 'cloudflare-r2-offload-cdn' ) . '</p>
				<ul>
					<li><strong>' . esc_html__( 'API Key', 'cloudflare-r2-offload-cdn' ) . '</strong> - ' . esc_html__( 'Required for external service integration.', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li><strong>' . esc_html__( 'Debug Mode', 'cloudflare-r2-offload-cdn' ) . '</strong> - ' . esc_html__( 'Enable logging for troubleshooting.', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li><strong>' . esc_html__( 'Custom CSS', 'cloudflare-r2-offload-cdn' ) . '</strong> - ' . esc_html__( 'Add your own styling.', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ul>',
			),
			array(
				'icon'    => 'admin-plugins',
				'title'   => __( 'Third-party Integrations', 'cloudflare-r2-offload-cdn' ),
				'content' => '<p>' . esc_html__( 'Connect with external services via the Integrations tab:', 'cloudflare-r2-offload-cdn' ) . '</p>
				<ul>
					<li><strong>' . esc_html__( 'Analytics', 'cloudflare-r2-offload-cdn' ) . '</strong> - ' . esc_html__( 'Track usage and performance metrics.', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li><strong>' . esc_html__( 'Webhooks', 'cloudflare-r2-offload-cdn' ) . '</strong> - ' . esc_html__( 'Receive real-time notifications.', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ul>',
			),
		);
	}
}
