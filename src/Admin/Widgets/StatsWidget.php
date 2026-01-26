<?php
/**
 * Stats Widget class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Widgets;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Services\StatsTracker;

/**
 * StatsWidget class - displays usage statistics.
 */
class StatsWidget {

	/**
	 * Render stats widget content.
	 */
	public static function render(): void {
		$current_month = StatsTracker::get_current_month_transformations();
		$daily_stats   = StatsTracker::get_daily_stats( 14 );
		$settings      = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		$account_id    = $settings['r2_account_id'] ?? '';

		?>
		<div class="cfr2-stats-widget">
			<div class="cfr2-stats-summary">
				<div class="cfr2-stat-item">
					<span class="cfr2-stat-value"><?php echo esc_html( number_format( $current_month ) ); ?></span>
					<span class="cfr2-stat-label"><?php esc_html_e( 'Transformations This Month', 'cloudflare-r2-offload-cdn' ); ?></span>
				</div>
			</div>

			<div class="cfr2-stats-chart">
				<h5><?php esc_html_e( 'Last 14 Days', 'cloudflare-r2-offload-cdn' ); ?></h5>
				<?php self::render_mini_chart( $daily_stats ); ?>
			</div>

			<?php if ( ! empty( $account_id ) ) : ?>
				<div class="cfr2-stats-footer">
					<p>
						<?php
						printf(
							/* translators: %s: Cloudflare dashboard URL */
							__( 'View detailed billing and usage in your <a href="%s" target="_blank" rel="noopener">Cloudflare Dashboard</a>', 'cloudflare-r2-offload-cdn' ),
							esc_url( "https://dash.cloudflare.com/{$account_id}/r2/overview" )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render mini bar chart.
	 *
	 * @param array $daily_stats Daily stats array.
	 */
	private static function render_mini_chart( array $daily_stats ): void {
		if ( empty( $daily_stats ) ) {
			echo '<p class="cfr2-no-data">' . esc_html__( 'No data yet', 'cloudflare-r2-offload-cdn' ) . '</p>';
			return;
		}

		$max = max( array_column( $daily_stats, 'transformations' ) );
		$max = max( $max, 1 ); // Avoid division by zero.

		?>
		<div class="cfr2-mini-chart">
			<?php foreach ( $daily_stats as $day ) : ?>
				<?php
				$height       = ( $day['transformations'] / $max ) * 100;
				$date_display = date_i18n( 'M j', strtotime( $day['date'] ) );
				?>
				<div class="cfr2-chart-bar"
					 style="height: <?php echo esc_attr( max( 2, $height ) ); ?>%;"
					 title="<?php echo esc_attr( "{$date_display}: {$day['transformations']}" ); ?>">
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get widget data as JSON for JS charts.
	 *
	 * @return array Chart data.
	 */
	public static function get_chart_data(): array {
		$daily = StatsTracker::get_daily_stats( 30 );

		return array(
			'labels' => array_column( $daily, 'date' ),
			'values' => array_map( 'intval', array_column( $daily, 'transformations' ) ),
		);
	}
}
