<?php
/**
 * Cloudflare for SaaS custom hostnames.
 *
 * @package   SugarCommerce\Cloudflare
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Cloudflare;

use SugarCommerce\HttpClient\Client as Http;

/**
 * Class CustomHostnames
 *
 * Cloudflare does the hard part — issuing and renewing a certificate for a
 * domain you do not own, once its owner points it at you — and this asks
 * it to. Three calls: add a hostname, ask how it is going, remove it.
 *
 * Validation is by HTTP by default: Cloudflare fetches a file over the
 * customer's CNAME, so the only thing a customer ever does is add one DNS
 * record. (TXT validation would need a second record, and a second
 * support ticket.)
 *
 * The distinctions kept:
 *
 * - **"Could not ask" is not "failed".** {@see self::status()} answers null
 *   when Cloudflare could not be reached, so a caller keeps what it last
 *   knew rather than marking a working domain dead.
 * - **Already gone is gone.** Deleting a hostname Cloudflare does not know
 *   (404) is success.
 *
 * Not final: an application adds a factory from its own configuration.
 *
 * @since 1.0.0
 */
readonly class CustomHostnames {

	/**
	 * Cloudflare's API.
	 */
	public const string API = 'https://api.cloudflare.com/client/v4';

	/**
	 * @since 1.0.0
	 *
	 * @param string $token API token with "SSL and Certificates: Edit" on the zone.
	 * @param string $zone  Zone id of the SaaS zone.
	 * @param Http   $http  HTTP client. Substitute `HttpClient\Fake` in tests.
	 */
	public function __construct(
		#[\SensitiveParameter] protected string $token,
		protected string $zone,
		protected Http $http = new Http(),
	) {}

	/**
	 * Whether both are set.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function configured(): bool {
		return '' !== $this->token && '' !== $this->zone;
	}

	/**
	 * Start serving a hostname.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hostname e.g. shop.example.com. Validate it first.
	 * @param string $method   Certificate validation: `http` or `txt`.
	 *
	 * @return Hostname|null Null when Cloudflare refused.
	 */
	#[\NoDiscard( 'Null means Cloudflare did not take the hostname.' )]
	public function create( string $hostname, string $method = 'http' ): ?Hostname {
		$response = $this->http->post_json( $this->url(), array(
			'hostname' => $hostname,
			'ssl'      => array( 'method' => 'txt' === $method ? 'txt' : 'http', 'type' => 'dv' ),
		), $this->auth() );

		return $response->ok ? Hostname::from_array( (array) ( $response->json()['result'] ?? array() ) ) : null;
	}

	/**
	 * How a hostname is getting on.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Cloudflare's id for it.
	 *
	 * @return Hostname|null Null when Cloudflare could not be asked, which is
	 *                       not the same as failed.
	 */
	public function status( string $id ): ?Hostname {
		if ( '' === $id ) {
			return null;
		}

		$response = $this->http->get( $this->url( $id ), $this->auth() );

		return $response->ok ? Hostname::from_array( (array) ( $response->json()['result'] ?? array() ) ) : null;
	}

	/**
	 * Stop serving it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Cloudflare's id for it.
	 *
	 * @return bool True when it is gone, including when it already was.
	 */
	public function delete( string $id ): bool {
		if ( '' === $id ) {
			return true;
		}

		$response = $this->http->delete( $this->url( $id ), $this->auth() );

		return $response->ok || 404 === $response->status;
	}

	/**
	 * The custom hostnames endpoint, or one hostname under it.
	 *
	 * @param string $id Cloudflare's id, or '' for the collection.
	 *
	 * @return string
	 */
	private function url( string $id = '' ): string {
		return self::API . '/zones/' . rawurlencode( $this->zone ) . '/custom_hostnames' . ( '' === $id ? '' : '/' . rawurlencode( $id ) );
	}

	/**
	 * Headers for an API call.
	 *
	 * @return array<string, string>
	 */
	private function auth(): array {
		return array( 'Authorization' => 'Bearer ' . $this->token );
	}
}
