<?php
/**
 * Cloudflare API Service class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CloudflareAPI class - handles Workers REST API operations.
 */
class CloudflareAPI {

	/**
	 * Cloudflare API base URL.
	 */
	private const API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * API token for authentication.
	 *
	 * @var string
	 */
	private string $api_token;

	/**
	 * Cloudflare account ID.
	 *
	 * @var string
	 */
	private string $account_id;

	/**
	 * Constructor.
	 *
	 * @param string $api_token API token.
	 * @param string $account_id Account ID.
	 */
	public function __construct( string $api_token, string $account_id ) {
		$this->api_token  = $api_token;
		$this->account_id = $account_id;
	}

	/**
	 * Upload a new version of Worker script.
	 *
	 * @param string $worker_name Worker name.
	 * @param string $script Script content.
	 * @param array  $bindings Environment bindings.
	 * @return array Response array.
	 */
	public function upload_version( string $worker_name, string $script, array $bindings = array() ): array {
		$metadata = array(
			'main_module' => 'worker.js',
			'bindings'    => $bindings,
		);

		// Multipart form data.
		$boundary = wp_generate_uuid4();
		$body     = $this->build_multipart_body(
			$boundary,
			array(
				array(
					'name'    => 'metadata',
					'content' => wp_json_encode( $metadata ),
					'type'    => 'application/json',
				),
				array(
					'name'    => 'worker.js',
					'content' => $script,
					'type'    => 'application/javascript+module',
				),
			)
		);

		return $this->request(
			'PUT',
			"/accounts/{$this->account_id}/workers/scripts/{$worker_name}",
			null,
			array( 'Content-Type' => "multipart/form-data; boundary={$boundary}" ),
			$body
		);
	}

	/**
	 * Deploy Worker to production (enable subdomain).
	 *
	 * @param string $worker_name Worker name.
	 * @return array Response array.
	 */
	public function deploy_worker( string $worker_name ): array {
		return $this->request(
			'PUT',
			"/accounts/{$this->account_id}/workers/scripts/{$worker_name}/subdomain",
			array( 'enabled' => true )
		);
	}

	/**
	 * Configure route for Worker.
	 *
	 * @param string $zone_id     Zone ID.
	 * @param string $pattern     Route pattern.
	 * @param string $worker_name Worker name.
	 * @return array Response array.
	 */
	public function configure_route( string $zone_id, string $pattern, string $worker_name ): array {
		return $this->request(
			'POST',
			"/zones/{$zone_id}/workers/routes",
			array(
				'pattern' => $pattern,
				'script'  => $worker_name,
			)
		);
	}

	/**
	 * Get zone ID by domain name.
	 *
	 * @param string $domain Domain name.
	 * @return string|null Zone ID or null if not found.
	 */
	public function get_zone_id( string $domain ): ?string {
		$response = $this->request( 'GET', '/zones', array( 'name' => $domain ) );

		if ( $response['success'] && ! empty( $response['result'] ) ) {
			return $response['result'][0]['id'];
		}

		return null;
	}

	/**
	 * Delete Worker.
	 *
	 * @param string $worker_name Worker name.
	 * @return array Response array.
	 */
	public function delete_worker( string $worker_name ): array {
		return $this->request(
			'DELETE',
			"/accounts/{$this->account_id}/workers/scripts/{$worker_name}"
		);
	}

	/**
	 * Get Worker status.
	 *
	 * @param string $worker_name Worker name.
	 * @return array Response array.
	 */
	public function get_worker_status( string $worker_name ): array {
		return $this->request(
			'GET',
			"/accounts/{$this->account_id}/workers/scripts/{$worker_name}"
		);
	}

	/**
	 * Verify API token has required permissions.
	 *
	 * @return array Response array.
	 */
	public function verify_token(): array {
		return $this->request( 'GET', '/user/tokens/verify' );
	}

	/**
	 * Make API request.
	 *
	 * @param string      $method HTTP method.
	 * @param string      $endpoint API endpoint.
	 * @param array|null  $data Request data.
	 * @param array       $headers Additional headers.
	 * @param string|null $raw_body Raw body content.
	 * @return array Response array.
	 */
	private function request(
		string $method,
		string $endpoint,
		?array $data = null,
		array $headers = array(),
		?string $raw_body = null
	): array {
		$url = self::API_BASE . $endpoint;

		// Add query params for GET requests.
		if ( 'GET' === $method && $data ) {
			$url .= '?' . http_build_query( $data );
			$data = null;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array_merge(
				array(
					'Authorization' => "Bearer {$this->api_token}",
					'Content-Type'  => 'application/json',
				),
				$headers
			),
		);

		if ( $raw_body ) {
			$args['body'] = $raw_body;
		} elseif ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'errors'  => array( array( 'message' => $response->get_error_message() ) ),
			);
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( ! $decoded ) {
			return array(
				'success' => false,
				'errors'  => array( array( 'message' => 'Invalid JSON response' ) ),
			);
		}

		return $decoded;
	}

	/**
	 * Build multipart form body.
	 *
	 * @param string $boundary Boundary string.
	 * @param array  $parts Parts array.
	 * @return string Multipart body.
	 */
	private function build_multipart_body( string $boundary, array $parts ): string {
		$body = '';

		foreach ( $parts as $part ) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$part['name']}\"";

			if ( isset( $part['filename'] ) ) {
				$body .= "; filename=\"{$part['filename']}\"";
			}

			$body .= "\r\n";
			$body .= "Content-Type: {$part['type']}\r\n\r\n";
			$body .= $part['content'] . "\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		return $body;
	}
}
