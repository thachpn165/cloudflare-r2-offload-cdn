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
							<input type="url" id="cdn_url" name="cdn_url"
								value="<?php echo esc_url( $settings['cdn_url'] ?? '' ); ?>"
								class="regular-text" placeholder="https://cdn.example.com" />
							<p class="description"><?php esc_html_e( 'Your custom domain pointing to the Worker.', 'cloudflare-r2-offload-cdn' ); ?></p>
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
