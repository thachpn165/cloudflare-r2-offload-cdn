<?php
/**
 * CDN Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * CdnTab class - renders CDN configuration and Worker deployment.
 */
class CdnTab {

	/**
	 * Render the CDN tab.
	 *
	 * @param array $settings Current settings.
	 */
	public static function render( array $settings ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-tab-content" id="tab-cdn">
			<h2><?php esc_html_e( 'CDN Configuration', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure CDN URL rewriting and image optimization settings.', 'cloudflare-r2-offload-cdn' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="cdn_enabled"><?php esc_html_e( 'Enable CDN URL Rewriting', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="cdn_enabled" value="0" />
						<label class="cloudflare-r2-offload-cdn-toggle">
							<input type="checkbox" id="cdn_enabled" name="cdn_enabled" value="1"
								<?php checked( 1, $settings['cdn_enabled'] ?? 0 ); ?> />
							<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Replace media URLs with CDN URLs for optimized delivery.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="cdn-fields" <?php echo empty( $settings['cdn_enabled'] ) ? 'style="display:none;"' : ''; ?>>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="cdn_url"><?php esc_html_e( 'CDN URL', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<div class="cfr2-cdn-url-row">
								<input type="url" id="cdn_url" name="cdn_url"
									value="<?php echo esc_url( $settings['cdn_url'] ?? '' ); ?>"
									class="regular-text" placeholder="https://cdn.example.com" />
								<button type="button" id="validate-cdn-dns" class="button">
									<?php esc_html_e( 'Validate DNS', 'cloudflare-r2-offload-cdn' ); ?>
								</button>
							</div>
							<div id="cdn-dns-status" class="cfr2-dns-status" style="display:none;"></div>
							<p class="description"><?php esc_html_e( 'Your custom domain pointing to the Worker. DNS record will be created automatically if not exists.', 'cloudflare-r2-offload-cdn' ); ?></p>

							<details class="cfr2-setup-guide">
								<summary><?php esc_html_e( 'How does automatic DNS setup work?', 'cloudflare-r2-offload-cdn' ); ?></summary>
								<div class="cfr2-setup-guide-content">
									<p><strong><?php esc_html_e( 'Automatic Setup (Recommended)', 'cloudflare-r2-offload-cdn' ); ?></strong></p>
									<ol>
										<li><?php esc_html_e( 'Enter your desired CDN URL (e.g., https://cdn.yourdomain.com)', 'cloudflare-r2-offload-cdn' ); ?></li>
										<li><?php esc_html_e( 'Click "Validate DNS" to check/create the DNS record automatically', 'cloudflare-r2-offload-cdn' ); ?></li>
										<li><?php esc_html_e( 'Click "Deploy Worker" to deploy and configure routes', 'cloudflare-r2-offload-cdn' ); ?></li>
									</ol>

									<div class="cfr2-notice cfr2-notice-info">
										<strong><?php esc_html_e( 'What happens when you validate:', 'cloudflare-r2-offload-cdn' ); ?></strong>
										<ul style="margin: 8px 0 0 16px;">
											<li><?php esc_html_e( 'If DNS record does not exist → Creates A record with proxy enabled', 'cloudflare-r2-offload-cdn' ); ?></li>
											<li><?php esc_html_e( 'If DNS record exists but proxy disabled → Shows warning with fix button', 'cloudflare-r2-offload-cdn' ); ?></li>
											<li><?php esc_html_e( 'If DNS record exists with proxy → Ready to deploy!', 'cloudflare-r2-offload-cdn' ); ?></li>
										</ul>
									</div>

									<p style="margin-top: 16px;"><strong><?php esc_html_e( 'Requirements:', 'cloudflare-r2-offload-cdn' ); ?></strong></p>
									<ul>
										<li><?php esc_html_e( 'Domain must be in your Cloudflare account', 'cloudflare-r2-offload-cdn' ); ?></li>
										<li><?php esc_html_e( 'API Token must have Zone:Edit permission', 'cloudflare-r2-offload-cdn' ); ?></li>
									</ul>
								</div>
							</details>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="quality"><?php esc_html_e( 'Image Quality', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<input type="range" id="quality" name="quality"
								min="1" max="100" value="<?php echo esc_attr( $settings['quality'] ?? 85 ); ?>" />
							<span id="quality-value"><?php echo esc_html( $settings['quality'] ?? 85 ); ?></span>
							<p class="description"><?php esc_html_e( '1-100. Higher = better quality, larger files. Default: 85', 'cloudflare-r2-offload-cdn' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="enable_avif"><?php esc_html_e( 'Enable AVIF Format', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="enable_avif" value="0" />
							<label class="cloudflare-r2-offload-cdn-toggle">
								<input type="checkbox" id="enable_avif" name="enable_avif" value="1"
									<?php checked( 1, $settings['enable_avif'] ?? 0 ); ?> />
								<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Serve AVIF to supported browsers. Falls back to WebP/original.', 'cloudflare-r2-offload-cdn' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="smart_sizes"><?php esc_html_e( 'Smart Sizes', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="smart_sizes" value="0" />
							<label class="cloudflare-r2-offload-cdn-toggle">
								<input type="checkbox" id="smart_sizes" name="smart_sizes" value="1"
									<?php checked( 1, $settings['smart_sizes'] ?? 0 ); ?> />
								<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Calculate optimal sizes attribute based on content width. Reduces bandwidth on mobile but increases Cloudflare Transformations cost.', 'cloudflare-r2-offload-cdn' ); ?></p>
						</td>
					</tr>
					<tr class="smart-sizes-options" <?php echo empty( $settings['smart_sizes'] ) ? 'style="display:none;"' : ''; ?>>
						<th scope="row">
							<label for="content_max_width"><?php esc_html_e( 'Content Max Width', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<input type="number" id="content_max_width" name="content_max_width"
								value="<?php echo esc_attr( $settings['content_max_width'] ?? 800 ); ?>"
								min="320" max="1920" step="10" class="small-text" /> px
							<p class="description"><?php esc_html_e( 'Maximum content area width in your theme. Used to calculate optimal image sizes.', 'cloudflare-r2-offload-cdn' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Cloudflare API Token', 'cloudflare-r2-offload-cdn' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Required for Worker deployment. Create at Cloudflare Dashboard > My Profile > API Tokens.', 'cloudflare-r2-offload-cdn' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="cf_api_token"><?php esc_html_e( 'API Token', 'cloudflare-r2-offload-cdn' ); ?></label>
						</th>
						<td>
							<?php
							$token_value = ! empty( $settings['cf_api_token'] ) ? '********' : '';
							?>
							<input type="password" id="cf_api_token" name="cf_api_token"
								value="<?php echo esc_attr( $token_value ); ?>"
								class="regular-text" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Permissions needed: Workers Scripts (Edit), Zone (Read), Zone Settings (Edit)', 'cloudflare-r2-offload-cdn' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Worker Deployment', 'cloudflare-r2-offload-cdn' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Deploy Cloudflare Worker for image transformation.', 'cloudflare-r2-offload-cdn' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Worker Status', 'cloudflare-r2-offload-cdn' ); ?></th>
						<td>
							<div id="worker-status" class="cfr2-worker-status">
								<?php
								if ( ! empty( $settings['worker_deployed'] ) ) {
									echo '<span style="color: green;">✓ ' . esc_html__( 'Deployed', 'cloudflare-r2-offload-cdn' ) . '</span>';
									if ( ! empty( $settings['worker_deployed_at'] ) ) {
										echo ' <span class="description">(' . esc_html( $settings['worker_deployed_at'] ) . ')</span>';
									}
								} else {
									echo '<span style="color: #999;">○ ' . esc_html__( 'Not deployed', 'cloudflare-r2-offload-cdn' ) . '</span>';
								}
								?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Actions', 'cloudflare-r2-offload-cdn' ); ?></th>
						<td>
							<button type="button" id="deploy-worker" class="button button-primary">
								<?php esc_html_e( 'Deploy Worker', 'cloudflare-r2-offload-cdn' ); ?>
							</button>
							<button type="button" id="remove-worker" class="button button-secondary" <?php echo empty( $settings['worker_deployed'] ) ? 'style="display:none;"' : ''; ?>>
								<?php esc_html_e( 'Remove Worker', 'cloudflare-r2-offload-cdn' ); ?>
							</button>
							<span id="worker-deploy-result" style="margin-left: 10px;"></span>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}
}
