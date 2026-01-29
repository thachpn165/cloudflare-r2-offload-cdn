<?php
/**
 * Encryption Service class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

/**
 * EncryptionService class - handles AES-256-CBC encryption with HMAC.
 */
class EncryptionService {

	/**
	 * Singleton instance.
	 *
	 * @var EncryptionService|null
	 */
	private static ?EncryptionService $instance = null;

	/**
	 * Encryption method.
	 */
	private const METHOD = 'AES-256-CBC';

	/**
	 * HMAC hash algorithm.
	 */
	private const HMAC_ALGO = 'sha256';

	/**
	 * HMAC length in bytes (SHA256 = 32 bytes).
	 */
	private const HMAC_LENGTH = 32;

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		// Private constructor.
	}

	/**
	 * Get singleton instance.
	 *
	 * @return EncryptionService Singleton instance.
	 */
	public static function get_instance(): EncryptionService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Encrypt a string using AES-256-CBC with HMAC authentication.
	 *
	 * @param string $plaintext Plain text to encrypt.
	 * @return string Encrypted string (base64 encoded with HMAC).
	 */
	public function encrypt( string $plaintext ): string {
		if ( empty( $plaintext ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! $this->is_auth_key_valid() ) {
			return base64_encode( $plaintext );
		}

		$iv_length = openssl_cipher_iv_length( self::METHOD );
		if ( false === $iv_length ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( $iv_length, $crypto_strong );
		if ( false === $iv || ! $crypto_strong ) {
			return '';
		}

		$encrypted = openssl_encrypt( $plaintext, self::METHOD, AUTH_KEY, 0, $iv );
		if ( false === $encrypted ) {
			return '';
		}

		$hmac = hash_hmac( self::HMAC_ALGO, $encrypted, AUTH_KEY, true );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $hmac . $encrypted );
	}

	/**
	 * Decrypt a string using AES-256-CBC with HMAC verification.
	 *
	 * @param string $encrypted Encrypted string.
	 * @return string Decrypted plain text.
	 */
	public function decrypt( string $encrypted ): string {
		if ( empty( $encrypted ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = base64_decode( $encrypted, true );

		if ( false === $data ) {
			return '';
		}

		// Fallback for non-encrypted (base64 only) keys.
		if ( ! $this->is_auth_key_valid() ) {
			return $data;
		}

		$iv_length = openssl_cipher_iv_length( self::METHOD );

		// Validate data length.
		if ( strlen( $data ) < $iv_length + self::HMAC_LENGTH ) {
			return '';
		}

		// Extract components.
		$iv         = substr( $data, 0, $iv_length );
		$hmac       = substr( $data, $iv_length, self::HMAC_LENGTH );
		$ciphertext = substr( $data, $iv_length + self::HMAC_LENGTH );

		// Verify HMAC (constant-time comparison).
		$expected_hmac = hash_hmac( self::HMAC_ALGO, $ciphertext, AUTH_KEY, true );

		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
			return ''; // Tampering detected.
		}

		$decrypted = openssl_decrypt( $ciphertext, self::METHOD, AUTH_KEY, 0, $iv );

		return false !== $decrypted ? $decrypted : '';
	}

	/**
	 * Check if AUTH_KEY is properly configured.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_auth_key_valid(): bool {
		return defined( 'AUTH_KEY' )
			&& AUTH_KEY !== 'put your unique phrase here'
			&& strlen( AUTH_KEY ) >= 32;
	}
}
