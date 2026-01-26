<?php
/**
 * General Tab class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * GeneralTab class - renders the general settings tab content.
 */
class GeneralTab {

	/**
	 * Render the general tab.
	 *
	 * @param array $settings Current settings.
	 */
	public static function render( array $settings ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-tab-content" id="tab-general">
			<h2><?php esc_html_e( 'General Settings', 'cloudflare-r2-offload-cdn' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure the basic plugin settings.', 'cloudflare-r2-offload-cdn' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="enable_feature"><?php esc_html_e( 'Enable Feature', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="hidden" name="enable_feature" value="0" />
						<label class="cloudflare-r2-offload-cdn-toggle">
							<input type="checkbox" id="enable_feature" name="enable_feature" value="1"
								<?php checked( 1, $settings['enable_feature'] ?? 0 ); ?> />
							<span class="cloudflare-r2-offload-cdn-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Enable the main feature.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="plugin_mode"><?php esc_html_e( 'Plugin Mode', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<select id="plugin_mode" name="plugin_mode" class="regular-text">
							<option value="basic" <?php selected( 'basic', $settings['plugin_mode'] ?? 'basic' ); ?>>
								<?php esc_html_e( 'Basic', 'cloudflare-r2-offload-cdn' ); ?>
							</option>
							<option value="advanced" <?php selected( 'advanced', $settings['plugin_mode'] ?? '' ); ?>>
								<?php esc_html_e( 'Advanced', 'cloudflare-r2-offload-cdn' ); ?>
							</option>
							<option value="pro" <?php selected( 'pro', $settings['plugin_mode'] ?? '' ); ?>>
								<?php esc_html_e( 'Professional', 'cloudflare-r2-offload-cdn' ); ?>
							</option>
						</select>
						<p class="description"><?php esc_html_e( 'Select the plugin operation mode.', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cache_duration"><?php esc_html_e( 'Cache Duration', 'cloudflare-r2-offload-cdn' ); ?></label>
					</th>
					<td>
						<input type="number" id="cache_duration" name="cache_duration"
							value="<?php echo esc_attr( $settings['cache_duration'] ?? 3600 ); ?>"
							class="small-text" min="0" step="60" />
						<span class="description"><?php esc_html_e( 'seconds', 'cloudflare-r2-offload-cdn' ); ?></span>
						<p class="description"><?php esc_html_e( 'How long to cache data (0 to disable).', 'cloudflare-r2-offload-cdn' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
