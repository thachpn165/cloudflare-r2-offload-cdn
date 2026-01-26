<?php
/**
 * Settings Page class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin;

use ThachPN165\CFR2OffLoad\Admin\Tabs\DashboardTab;
use ThachPN165\CFR2OffLoad\Admin\Tabs\GeneralTab;
use ThachPN165\CFR2OffLoad\Admin\Tabs\AdvancedTab;
use ThachPN165\CFR2OffLoad\Admin\Tabs\IntegrationsTab;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsPage class - renders the settings page with tabbed layout.
 */
class SettingsPage {

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cloudflare-r2-offload-cdn' ) );
		}

		// Add frame-busting headers to prevent clickjacking.
		if ( ! headers_sent() ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Content-Security-Policy: frame-ancestors \'self\'' );
		}

		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$tabs     = self::get_tabs();
		?>
		<div class="wrap cloudflare-r2-offload-cdn-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Toast notification container -->
			<div class="cloudflare-r2-offload-cdn-toast" id="cloudflare-r2-offload-cdn-toast"></div>

			<div class="cloudflare-r2-offload-cdn-settings-container">
				<?php self::render_sidebar( $tabs ); ?>
				<?php self::render_content( $settings ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get tabs configuration.
	 *
	 * @return array Tabs configuration.
	 */
	private static function get_tabs(): array {
		return array(
			'dashboard'    => array(
				'label' => __( 'Dashboard', 'cloudflare-r2-offload-cdn' ),
				'icon'  => 'dashicons-dashboard',
			),
			'general'      => array(
				'label' => __( 'General', 'cloudflare-r2-offload-cdn' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'advanced'     => array(
				'label' => __( 'Advanced', 'cloudflare-r2-offload-cdn' ),
				'icon'  => 'dashicons-admin-tools',
			),
			'integrations' => array(
				'label' => __( 'Integrations', 'cloudflare-r2-offload-cdn' ),
				'icon'  => 'dashicons-admin-plugins',
			),
		);
	}

	/**
	 * Render sidebar with tabs.
	 *
	 * @param array $tabs Tabs configuration.
	 */
	private static function render_sidebar( array $tabs ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-sidebar">
			<ul class="cloudflare-r2-offload-cdn-tabs">
				<?php
				$first = true;
				foreach ( $tabs as $tab_id => $tab ) :
					?>
					<li data-tab="<?php echo esc_attr( $tab_id ); ?>" class="<?php echo $first ? 'active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</li>
					<?php
					$first = false;
				endforeach;
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render content area with tab panels.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_content( array $settings ): void {
		?>
		<div class="cloudflare-r2-offload-cdn-content">
			<?php DashboardTab::render(); ?>

			<form id="cloudflare-r2-offload-cdn-settings-form">
				<?php wp_nonce_field( 'cloudflare_r2_offload_cdn_save_settings', 'cloudflare_r2_offload_cdn_nonce' ); ?>

				<?php GeneralTab::render( $settings ); ?>
				<?php AdvancedTab::render( $settings ); ?>
				<?php IntegrationsTab::render( $settings ); ?>

				<div class="cloudflare-r2-offload-cdn-form-actions">
					<button type="submit" class="button button-primary cloudflare-r2-offload-cdn-save-btn">
						<span class="cloudflare-r2-offload-cdn-save-text"><?php esc_html_e( 'Save Settings', 'cloudflare-r2-offload-cdn' ); ?></span>
						<span class="cloudflare-r2-offload-cdn-save-loading spinner"></span>
					</button>
				</div>
			</form>
		</div>
		<?php
	}
}
