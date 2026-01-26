<?php
/**
 * Integrations Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * IntegrationsTab class - renders the integrations tab content.
 */
class IntegrationsTab {

	/**
	 * Render the integrations tab.
	 *
	 * @param array $settings Current settings.
	 */
	public static function render( array $settings ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-tab-content" id="tab-integrations">
			<h2><?php esc_html_e( 'Plugin Integrations', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure integrations with WordPress plugins and features.', 'cloudflare-r2-offload-cdn' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="woocommerce_integration"><?php esc_html_e( 'WooCommerce Integration', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="woocommerce_integration" value="0" />
						<label class="cloudflare-r2-offload-cdn-toggle">
							<input type="checkbox" id="woocommerce_integration" name="woocommerce_integration" value="1"
								<?php checked( 1, $settings['woocommerce_integration'] ?? 0 ); ?> />
							<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Automatically offload WooCommerce product images to R2.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gutenberg_integration"><?php esc_html_e( 'Gutenberg Integration', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="gutenberg_integration" value="0" />
						<label class="cloudflare-r2-offload-cdn-toggle">
							<input type="checkbox" id="gutenberg_integration" name="gutenberg_integration" value="1"
								<?php checked( 1, $settings['gutenberg_integration'] ?? 0 ); ?> />
							<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Enable CDN URLs in Gutenberg block editor.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
