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

// Global options storage for tests.
global $_test_options;
$_test_options = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	function get_option( $option, $default = false ) {
		global $_test_options;
		return $_test_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool Always true.
	 */
	function update_option( $option, $value ) {
		global $_test_options;
		$_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Mock delete_option.
	 *
	 * @param string $option Option name.
	 * @return bool Always true.
	 */
	function delete_option( $option ) {
		global $_test_options;
		unset( $_test_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Mock did_action.
	 *
	 * @param string $hook Hook name.
	 * @return int Always 0.
	 */
	function did_action( $hook ) {
		return 0;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Mock absint.
	 *
	 * @param mixed $value Value to convert.
	 * @return int Absolute integer.
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Mock get_transient.
	 *
	 * @param string $transient Transient name.
	 * @return mixed False (no transient).
	 */
	function get_transient( $transient ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Mock set_transient.
	 *
	 * @param string $transient Transient name.
	 * @param mixed  $value     Transient value.
	 * @param int    $expiration Expiration time.
	 * @return bool Always true.
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Mock delete_transient.
	 *
	 * @param string $transient Transient name.
	 * @return bool Always true.
	 */
	function delete_transient( $transient ) {
		return true;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Mock wp_strip_all_tags.
	 *
	 * @param string $string String to strip.
	 * @return string Stripped string.
	 */
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'wp_remote_head' ) ) {
	/**
	 * Mock wp_remote_head.
	 *
	 * @param string $url URL to request.
	 * @param array  $args Request args.
	 * @return array Response array.
	 */
	function wp_remote_head( $url, $args = array() ) {
		return array( 'response' => array( 'code' => 200 ) );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Mock wp_remote_retrieve_response_code.
	 *
	 * @param array $response Response array.
	 * @return int Response code.
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 200;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Mock is_wp_error.
	 *
	 * @param mixed $thing Thing to check.
	 * @return bool Always false.
	 */
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Mock esc_url_raw.
	 *
	 * @param string $url URL to escape.
	 * @return string Escaped URL.
	 */
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

// Mock dbDelta function.
if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Mock dbDelta.
	 *
	 * @param string $queries SQL queries.
	 * @return array Always empty array.
	 */
	function dbDelta( $queries ) {
		return array();
	}
}

// Mock global $wpdb for tests.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public $prefix = 'wp_';
		public function get_charset_collate() {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}
	};
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

// Mock WP_Post class.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID;
		public $post_title;
		public $post_content;
		public function __construct( $post = null ) {
			if ( $post ) {
				foreach ( get_object_vars( $post ) as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}
}

// Define plugin constants.
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_VERSION', '1.0.0' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_FILE', dirname( __DIR__ ) . '/cloudflare-r2-offload-cdn.php' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_PATH', dirname( __DIR__ ) . '/' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_URL', 'http://example.com/wp-content/plugins/cloudflare-r2-offload-cdn/' );
define( 'CLOUDFLARE_R2_OFFLOAD_CDN_BASENAME', 'cloudflare-r2-offload-cdn/cloudflare-r2-offload-cdn.php' );
