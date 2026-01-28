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
			<h2><?php esc_html_e( 'CloudFlare R2 Offload & CDN', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Offload WordPress media to Cloudflare R2 storage and serve via CDN with automatic image optimization (WebP/AVIF, resize, quality).', 'cloudflare-r2-offload-cdn' ); ?></p>

			<?php
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

		$local_count = max( 0, $total_count - $offloaded_count - $pending_count );
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
			<h3><?php esc_html_e( 'Worker Statistics', 'cloudflare-r2-offload-cdn' ); ?></h3>
			<?php StatsWidget::render(); ?>
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
			<h3><?php esc_html_e( 'Setup Guides', 'cloudflare-r2-offload-cdn' ); ?></h3>
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
			self::get_r2_setup_guide(),
			self::get_api_token_guide(),
			self::get_cdn_setup_guide(),
			self::get_offload_guide(),
			self::get_optimization_guide(),
		);
	}

	/**
	 * Get R2 bucket setup guide.
	 *
	 * @return array Guide data.
	 */
	private static function get_r2_setup_guide(): array {
		return array(
			'icon'    => 'cloud-saved',
			'title'   => __( '1. Create R2 Bucket', 'cloudflare-r2-offload-cdn' ),
			'content' => '
				<ol>
					<li>' . __( 'Log in to <a href="https://dash.cloudflare.com" target="_blank">Cloudflare Dashboard</a>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Go to <strong>R2 Object Storage</strong> in the left sidebar', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Create bucket</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Enter bucket name (e.g., <code>my-wp-media</code>) and select location', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Create bucket</strong> to finish', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<p><strong>' . __( 'Get R2 API Credentials:', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ol>
					<li>' . __( 'In R2 page, click <strong>Manage R2 API Tokens</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Create API token</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Set permissions: <strong>Object Read & Write</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Specify bucket (or all buckets)', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Copy <strong>Access Key ID</strong> and <strong>Secret Access Key</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<p class="description">' . __( 'Your Account ID is shown at the top right of the R2 page.', 'cloudflare-r2-offload-cdn' ) . '</p>',
		);
	}

	/**
	 * Get API token creation guide.
	 *
	 * @return array Guide data.
	 */
	private static function get_api_token_guide(): array {
		return array(
			'icon'    => 'admin-network',
			'title'   => __( '2. Create Cloudflare API Token', 'cloudflare-r2-offload-cdn' ),
			'content' => '
				<p>' . __( 'API Token is required for Worker deployment and cache purging.', 'cloudflare-r2-offload-cdn' ) . '</p>
				<ol>
					<li>' . __( 'Go to <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">API Tokens</a> page', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Create Token</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Select <strong>Create Custom Token</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Add the following permissions:', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<table class="widefat" style="margin: 10px 0;">
					<thead>
						<tr>
							<th>' . __( 'Permission', 'cloudflare-r2-offload-cdn' ) . '</th>
							<th>' . __( 'Access', 'cloudflare-r2-offload-cdn' ) . '</th>
							<th>' . __( 'Purpose', 'cloudflare-r2-offload-cdn' ) . '</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>Account > Workers Scripts</code></td>
							<td>Edit</td>
							<td>' . __( 'Deploy Worker', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
						<tr>
							<td><code>Zone > Workers Routes</code></td>
							<td>Edit</td>
							<td>' . __( 'Configure Worker route', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
						<tr>
							<td><code>Zone > Cache Purge</code></td>
							<td>Purge</td>
							<td>' . __( 'Clear CDN cache', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
						<tr>
							<td><code>Account > Workers R2 Storage</code></td>
							<td>Edit</td>
							<td>' . __( 'Bind R2 to Worker', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
					</tbody>
				</table>
				<ol start="5">
					<li>' . __( 'Set <strong>Account Resources</strong>: Include your account', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Set <strong>Zone Resources</strong>: Include your domain', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Continue to summary</strong> > <strong>Create Token</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Copy the token (shown only once!)', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>',
		);
	}

	/**
	 * Get CDN setup guide.
	 *
	 * @return array Guide data.
	 */
	private static function get_cdn_setup_guide(): array {
		return array(
			'icon'    => 'performance',
			'title'   => __( '3. Configure CDN URL', 'cloudflare-r2-offload-cdn' ),
			'content' => '
				<p>' . __( 'You need a custom domain to serve images via CDN with image transformations.', 'cloudflare-r2-offload-cdn' ) . '</p>
				<p><strong>' . __( 'Option A: Use R2 Custom Domain (Recommended)', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ol>
					<li>' . __( 'In R2 bucket settings, go to <strong>Settings</strong> tab', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Under <strong>Public access</strong>, click <strong>Connect Domain</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Enter subdomain (e.g., <code>cdn.yourdomain.com</code>)', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Cloudflare will automatically create DNS record', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<p><strong>' . __( 'Option B: Use Worker Route', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ol>
					<li>' . __( 'Deploy Worker first (see step 4)', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Create Worker route: <code>cdn.yourdomain.com/*</code>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Add DNS record: CNAME <code>cdn</code> to your Worker', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<p class="description">' . __( 'Enter the CDN URL in the CDN tab (e.g., https://cdn.yourdomain.com)', 'cloudflare-r2-offload-cdn' ) . '</p>',
		);
	}

	/**
	 * Get offload guide.
	 *
	 * @return array Guide data.
	 */
	private static function get_offload_guide(): array {
		return array(
			'icon'    => 'upload',
			'title'   => __( '4. Offload Media to R2', 'cloudflare-r2-offload-cdn' ),
			'content' => '
				<p><strong>' . __( 'Automatic Offload:', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ul>
					<li>' . __( 'Enable <strong>Auto Offload</strong> in Offload tab', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'New uploads will be automatically offloaded to R2', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ul>
				<p><strong>' . __( 'Bulk Offload Existing Media:', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ol>
					<li>' . __( 'Go to <strong>Bulk Actions</strong> tab', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Set batch size (25-50 recommended)', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Click <strong>Start Bulk Offload</strong>', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Wait for completion (you can cancel anytime)', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ol>
				<p><strong>' . __( 'Manual Offload:', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ul>
					<li>' . __( 'In Media Library, click <strong>Offload to R2</strong> for individual items', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Or use attachment edit page for single file offload', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ul>',
		);
	}

	/**
	 * Get optimization guide.
	 *
	 * @return array Guide data.
	 */
	private static function get_optimization_guide(): array {
		return array(
			'icon'    => 'images-alt2',
			'title'   => __( '5. Image Optimization Settings', 'cloudflare-r2-offload-cdn' ),
			'content' => '
				<p>' . __( 'Configure image optimization in the <strong>CDN</strong> tab:', 'cloudflare-r2-offload-cdn' ) . '</p>
				<table class="widefat" style="margin: 10px 0;">
					<tbody>
						<tr>
							<td><strong>' . __( 'Quality', 'cloudflare-r2-offload-cdn' ) . '</strong></td>
							<td>' . __( '1-100 (default 85). Lower = smaller file, less quality.', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
						<tr>
							<td><strong>' . __( 'Enable AVIF', 'cloudflare-r2-offload-cdn' ) . '</strong></td>
							<td>' . __( 'Serve AVIF format for supported browsers. Better compression than WebP.', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
						<tr>
							<td><strong>' . __( 'Smart Sizes', 'cloudflare-r2-offload-cdn' ) . '</strong></td>
							<td>' . __( 'Auto-calculate responsive sizes. Increases Transformations usage.', 'cloudflare-r2-offload-cdn' ) . '</td>
						</tr>
					</tbody>
				</table>
				<p><strong>' . __( 'Cost Information:', 'cloudflare-r2-offload-cdn' ) . '</strong></p>
				<ul>
					<li>' . __( 'R2 Storage: $0.015/GB/month', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'R2 Class A Operations (write): $4.50/million', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'R2 Class B Operations (read): $0.36/million', 'cloudflare-r2-offload-cdn' ) . '</li>
					<li>' . __( 'Image Transformations: First 5,000/month free, then $0.50/1,000', 'cloudflare-r2-offload-cdn' ) . '</li>
				</ul>
				<p class="description">' . __( 'Monitor usage in Worker Statistics above and Cloudflare Dashboard.', 'cloudflare-r2-offload-cdn' ) . '</p>',
		);
	}
}
