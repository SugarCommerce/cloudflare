<?php
/**
 * Cloudflare for SaaS custom hostnames.
 *
 * @package   SugarCommerce\Cloudflare
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace SugarCommerce\Cloudflare\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Cloudflare\{CustomHostnames, Hostname, Status};
use SugarCommerce\HttpClient\{Fake, Response};

/**
 * Class CustomHostnamesTest
 *
 * Against a double: nothing here reaches api.cloudflare.com.
 */
final class CustomHostnamesTest extends TestCase {

	private const string ZONE = 'zone123';

	/* ─── Create ────────────────────────────────────────────────────── */

	public function test_creating_asks_for_an_http_validated_certificate(): void {
		$http = Fake::strict()->on( 'POST', CustomHostnames::API . '/zones/zone123/custom_hostnames', new Response( true, 201, '{"result":{"id":"ch_1","hostname":"shop.example.com","status":"pending","ssl":{"status":"pending_validation"}}}' ) );

		$created = ( new CustomHostnames( 'tok', self::ZONE, $http ) )->create( 'shop.example.com' );

		$this->assertEquals( new Hostname( 'ch_1', 'shop.example.com', Status::Pending ), $created );
		$this->assertSame( array( 'hostname' => 'shop.example.com', 'ssl' => array( 'method' => 'http', 'type' => 'dv' ) ), json_decode( (string) $http->sent( 'POST' )[0]['body'], true ) );
		$this->assertSame( 'Bearer tok', $http->sent( 'POST' )[0]['headers']['Authorization'] );
	}

	public function test_a_refused_hostname_is_null(): void {
		$http = Fake::strict()->on( 'POST', '*', new Response( false, 409, '{"success":false}' ) );

		$this->assertNull( ( new CustomHostnames( 'tok', self::ZONE, $http ) )->create( 'shop.example.com' ) );
	}

	public function test_only_http_or_txt_validation_is_sent(): void {
		$http = Fake::strict()->on( 'POST', '*', new Response( true, 201, '{"result":{"id":"ch_1"}}' ) );
		$api  = new CustomHostnames( 'tok', self::ZONE, $http );

		(void) $api->create( 'a.example.com', 'txt' );
		(void) $api->create( 'b.example.com', 'email' );

		$this->assertSame( 'txt', json_decode( (string) $http->sent( 'POST' )[0]['body'], true )['ssl']['method'] );
		$this->assertSame( 'http', json_decode( (string) $http->sent( 'POST' )[1]['body'], true )['ssl']['method'] );
	}

	/* ─── Status ────────────────────────────────────────────────────── */

	public function test_status_is_read_by_id_and_could_not_ask_is_not_failed(): void {
		$http = Fake::strict()
			->on( 'GET', '*/custom_hostnames/ch_1', new Response( true, 200, '{"result":{"id":"ch_1","status":"active","ssl":{"status":"active"}}}' ) )
			->on( 'GET', '*/custom_hostnames/ch_2', new Response( false, 503 ) );
		$api  = new CustomHostnames( 'tok', self::ZONE, $http );

		$this->assertSame( Status::Active, $api->status( 'ch_1' )?->status );
		$this->assertNull( $api->status( 'ch_2' ) );
		$this->assertNull( $api->status( '' ) );
	}

	/**
	 * @return array<string, array{string, string, Status}>
	 */
	public static function statuses(): array {
		return array(
			'both active'                       => array( 'active', 'active', Status::Active ),
			'hostname active, certificate not'  => array( 'active', 'pending_validation', Status::Pending ),
			'certificate active, hostname not'  => array( 'pending', 'active', Status::Pending ),
			'validation timed out'              => array( 'pending', 'validation_timed_out', Status::Failed ),
			'issuance timed out'                => array( 'active', 'issuance_timed_out', Status::Failed ),
			'blocked'                           => array( 'blocked', 'active', Status::Failed ),
			'moved'                             => array( 'moved', 'pending_validation', Status::Failed ),
			'deleted'                           => array( 'deleted', '', Status::Failed ),
			'nothing said'                      => array( '', '', Status::Pending ),
		);
	}

	/**
	 * The shop is reachable only when both are active.
	 */
	#[DataProvider( 'statuses' )]
	public function test_two_statuses_become_one( string $hostname, string $ssl, Status $expected ): void {
		$this->assertSame( $expected, Status::from_cloudflare( $hostname, $ssl ) );
		$this->assertSame( Status::Pending !== $expected, $expected->settled() );
	}

	public function test_a_result_without_an_id_is_nothing(): void {
		$this->assertNull( Hostname::from_array( array( 'status' => 'active' ) ) );
		$this->assertSame( '', Hostname::from_array( array( 'id' => 'x', 'ssl' => 'junk' ) )?->hostname );
	}

	/* ─── Delete ────────────────────────────────────────────────────── */

	public function test_deleting_one_already_gone_is_done(): void {
		$http = Fake::strict()
			->on( 'DELETE', '*/ch_1', new Response( true, 200 ) )
			->on( 'DELETE', '*/ch_2', new Response( false, 404 ) )
			->on( 'DELETE', '*/ch_3', new Response( false, 500 ) );
		$api  = new CustomHostnames( 'tok', self::ZONE, $http );

		$this->assertTrue( $api->delete( 'ch_1' ) );
		$this->assertTrue( $api->delete( 'ch_2' ) );
		$this->assertFalse( $api->delete( 'ch_3' ) );
		$this->assertTrue( $api->delete( '' ), 'Nothing to delete.' );
		$this->assertCount( 3, $http->requests );
	}

	/* ─── Configuration ─────────────────────────────────────────────── */

	public function test_configured_needs_a_token_and_a_zone(): void {
		$this->assertTrue( ( new CustomHostnames( 'tok', self::ZONE ) )->configured() );
		$this->assertFalse( ( new CustomHostnames( '', self::ZONE ) )->configured() );
		$this->assertFalse( ( new CustomHostnames( 'tok', '' ) )->configured() );
	}

	public function test_ids_are_encoded_into_the_path(): void {
		$http = Fake::strict()->on( 'GET', '*', new Response( false, 404 ) );

		( new CustomHostnames( 'tok', 'z/1', $http ) )->status( '../x' );

		$this->assertSame( CustomHostnames::API . '/zones/z%2F1/custom_hostnames/..%2Fx', $http->sent()[0]['url'] );
	}

	public function test_create_must_be_checked(): void {
		$attributes = ( new \ReflectionMethod( CustomHostnames::class, 'create' ) )->getAttributes( \NoDiscard::class );

		$this->assertCount( 1, $attributes );
		$this->assertNotSame( '', $attributes[0]->newInstance()->message );
	}
}
