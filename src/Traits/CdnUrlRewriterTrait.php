<?php
/**
 * CDN URL Rewriter Trait.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Provides shared CDN URL rewriting logic for integrations.
 */
trait CdnUrlRewriterTrait {

	/**
	 * Get CDN URL for an image if offloaded.
	 *
	 * @param string $url      Original URL.
	 * @param array  $settings Plugin settings with cdn_url and quality.
	 * @return string Possibly modified URL.
	 */
	protected function get_cdn_url_for_image( string $url, array $settings ): string {
		$attachment_id = attachment_url_to_postid( $url );
		if ( ! $attachment_id ) {
			return $url;
		}

		if ( ! get_post_meta( $attachment_id, '_cfr2_offloaded', true ) ) {
			return $url;
		}

		$r2_key = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		if ( ! $r2_key ) {
			return $url;
		}

		$cdn_url = rtrim( $settings['cdn_url'] ?? '', '/' );
		$quality = $settings['quality'] ?? 85;

		return "{$cdn_url}/{$r2_key}?q={$quality}&f=auto";
	}

	/**
	 * Rewrite srcset attribute value with CDN URLs.
	 *
	 * @param string $srcset   Srcset value.
	 * @param array  $settings Plugin settings.
	 * @return string Modified srcset.
	 */
	protected function rewrite_srcset_urls( string $srcset, array $settings ): string {
		$sources     = explode( ',', $srcset );
		$new_sources = array();

		foreach ( $sources as $source ) {
			$parts = preg_split( '/\s+/', trim( $source ) );
			if ( ! empty( $parts[0] ) ) {
				$url           = $this->get_cdn_url_for_image( $parts[0], $settings );
				$descriptor    = $parts[1] ?? '';
				$new_sources[] = trim( "{$url} {$descriptor}" );
			}
		}

		return implode( ', ', $new_sources );
	}

	/**
	 * Rewrite img src and srcset in HTML content.
	 *
	 * @param string $html     HTML content.
	 * @param array  $settings Plugin settings.
	 * @return string Modified HTML.
	 */
	protected function rewrite_img_tags_in_html( string $html, array $settings ): string {
		return preg_replace_callback(
			'/<img\s+([^>]*)>/i',
			function ( $matches ) use ( $settings ) {
				$attributes = $matches[1];

				$attributes = preg_replace_callback(
					'/src=["\']([^"\']+)["\']/i',
					function ( $m ) use ( $settings ) {
						$url = $this->get_cdn_url_for_image( $m[1], $settings );
						return 'src="' . esc_attr( $url ) . '"';
					},
					$attributes
				);

				$attributes = preg_replace_callback(
					'/srcset=["\']([^"\']+)["\']/i',
					function ( $m ) use ( $settings ) {
						$srcset = $this->rewrite_srcset_urls( $m[1], $settings );
						return 'srcset="' . esc_attr( $srcset ) . '"';
					},
					$attributes
				);

				return '<img ' . $attributes . '>';
			},
			$html
		);
	}
}
