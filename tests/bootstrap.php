<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package CFR2OffLoad\Tests
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define test constants.
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_TESTING', true );

// Mock WordPress functions for unit tests.
if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Mock plugin_dir_path.
	 *
	 * @param string $file File path.
	 * @return string Directory path.
	 */
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Mock plugin_dir_url.
	 *
	 * @param string $file File path.
	 * @return string URL path.
	 */
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Mock plugin_basename.
	 *
	 * @param string $file File path.
	 * @return string Plugin basename.
	 */
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field.
	 *
	 * @param string $str String to sanitize.
	 * @return string Sanitized string.
	 */
	function sanitize_text_field( $str ) {
		return strip_tags( trim( $str ) );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Mock is_admin.
	 *
	 * @return bool Always false in tests.
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Mock add_action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of args.
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		// Mock - do nothing.
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Mock add_filter.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of args.
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		// Mock - do nothing.
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

// Define plugin constants.
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_VERSION', '1.0.0' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_FILE', dirname( __DIR__ ) . '/cloudflare-r2-offload-cdn.php' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_PATH', dirname( __DIR__ ) . '/' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_URL', 'http://example.com/wp-content/plugins/cloudflare-r2-offload-cdn/' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_BASENAME', 'cloudflare-r2-offload-cdn/cloudflare-r2-offload-cdn.php' );
