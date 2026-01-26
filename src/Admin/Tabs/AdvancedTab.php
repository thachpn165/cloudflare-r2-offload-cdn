<?php
/**
 * Advanced Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

use ThachPN165\CFR2OffLoad\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

/**
 * AdvancedTab class - renders the advanced settings tab content.
 */
class AdvancedTab {

	/**
	 * Render the advanced tab.
	 *
	 * @param array $settings Current settings.
	 */
	public static function render( array $settings ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-tab-content" id="tab-advanced">
			<h2><?php esc_html_e( 'Advanced Settings', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Advanced configuration options for power users.', 'cloudflare-r2-offload-cdn' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="api_key"><?php esc_html_e( 'API Key', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<?php
						// Decrypt API key for display (stored encrypted in database).
						$api_key = AdminMenu::decrypt_api_key( $settings['api_key'] ?? '' );
						?>
						<input type="password" id="api_key" name="api_key"
							value="<?php echo esc_attr( $api_key ); ?>"
							class="regular-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Enter your API key for external services.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="debug_mode" value="0" />
						<label class="cloudflare-r2-offload-cdn-toggle">
							<input type="checkbox" id="debug_mode" name="debug_mode" value="1"
								<?php checked( 1, $settings['debug_mode'] ?? 0 ); ?> />
							<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Enable debug logging for troubleshooting.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="custom_css"><?php esc_html_e( 'Custom CSS', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<textarea id="custom_css" name="custom_css" rows="6" class="large-text code"><?php echo esc_textarea( $settings['custom_css'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Add custom CSS styles.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
