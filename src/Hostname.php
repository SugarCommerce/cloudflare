<?php
/**
 * A custom hostname, as Cloudflare knows it.
 *
 * @package   SugarCommerce\Cloudflare
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Cloudflare;

/**
 * Class Hostname
 *
 * Keep the id: every later call — how is it going, remove it — is by id,
 * not by name.
 *
 * @since 1.0.0
 */
final readonly class Hostname {

	/**
	 * A hostname, by id, with where it has got to.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id       Cloudflare's id for it.
	 * @param string $hostname The name, e.g. shop.example.com.
	 * @param Status $status   Hostname and certificate, read together.
	 */
	public function __construct(
		public string $id,
		public string $hostname,
		public Status $status,
	) {}

	/**
	 * From Cloudflare's custom hostname object.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $result The API's `result`.
	 *
	 * @return self|null Null when it has no id.
	 */
	public static function from_array( array $result ): ?self {
		$id = is_string( $result['id'] ?? null ) ? $result['id'] : '';

		if ( '' === $id ) {
			return null;
		}

		$ssl = is_array( $result['ssl'] ?? null ) ? $result['ssl'] : array();

		return new self(
			$id,
			is_string( $result['hostname'] ?? null ) ? $result['hostname'] : '',
			Status::from_cloudflare(
				is_string( $result['status'] ?? null ) ? $result['status'] : '',
				is_string( $ssl['status'] ?? null ) ? $ssl['status'] : ''
			)
		);
	}
}
