<?php
/**
 * Where a custom hostname has got to.
 *
 * @package   SugarCommerce\Cloudflare
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Cloudflare;

/**
 * Enum Status
 *
 * Cloudflare verifies a custom hostname and its certificate separately,
 * each with its own vocabulary of a dozen states. A domain serves traffic
 * only once **both** are active, and is stuck for good once either has
 * given up — a hostname blocked, moved or deleted, or a certificate whose
 * validation timed out. Everything else is "still going".
 *
 * Reading only the hostname's status is the usual mistake: it goes
 * `active` before the certificate does, and the shop answers with a TLS
 * error in the meantime.
 *
 * The values are what to store.
 *
 * @since 1.0.0
 */
enum Status: string {

	/**
	 * Hostname and certificate both active: the domain serves.
	 */
	case Active = 'active';

	/**
	 * Still being verified or issued; ask again later.
	 */
	case Pending = 'pending';

	/**
	 * Given up: the hostname is blocked, moved or deleted, or the
	 * certificate's validation timed out.
	 */
	case Failed = 'failed';

	/**
	 * Hostname statuses Cloudflare does not recover from.
	 */
	private const array DEAD = array( 'blocked', 'moved', 'deleted' );

	/**
	 * One status out of Cloudflare's two.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hostname The custom hostname's `status`.
	 * @param string $ssl      Its certificate's `ssl.status`.
	 *
	 * @return self
	 */
	public static function from_cloudflare( string $hostname, string $ssl ): self {
		return match ( true ) {
			'active' === $hostname && 'active' === $ssl                     => self::Active,
			in_array( $hostname, self::DEAD, true ), str_contains( $ssl, 'timed_out' ) => self::Failed,
			default                                                         => self::Pending,
		};
	}

	/**
	 * Whether it is worth asking again.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function settled(): bool {
		return self::Pending !== $this;
	}
}
