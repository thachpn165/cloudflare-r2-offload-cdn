<?php
/**
 * Dashboard Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

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
			self::render_plugin_info();
			self::render_usage_guides();
			?>
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
	 * Render plugin info section.
	 */
	private static function render_plugin_info(): void {
		?>
		<div class="cloudflare-r2-offload-cdn-info-box">
			<h3><?php esc_html_e( 'Plugin Information', 'cloudflare-r2-offload-cdn' ); ?></h3>
			<ul class="cloudflare-r2-offload-cdn-info-list">
				<li>
					<span class="label"><?php esc_html_e( 'Version', 'cloudflare-r2-offload-cdn' ); ?></span>
					<span class="value"><?php echo esc_html( CLOUDFLARE_R2_OFFLOAD_CDN_VERSION ); ?></span>
				</li>
				<li>
					<span class="label"><?php esc_html_e( 'PHP Version', 'cloudflare-r2-offload-cdn' ); ?></span>
					<span class="value"><?php echo esc_html( PHP_VERSION ); ?></span>
				</li>
				<li>
					<span class="label"><?php esc_html_e( 'WordPress Version', 'cloudflare-r2-offload-cdn' ); ?></span>
					<span class="value"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
				</li>
			</ul>
		</div>
		<?php
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
