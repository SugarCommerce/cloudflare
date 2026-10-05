# Cloudflare

Cloudflare for SaaS custom hostnames: add a customer's domain, ask how it is going, remove it, with Cloudflare's two separate verifications read as one status a shop can act on.

## Why

Letting customers use their own domain — `shop.example.com` instead of `you.example.app/shop` — means a TLS certificate for a domain you do not own. Cloudflare for SaaS issues and renews it once the customer points a CNAME at you. The API is three calls, and the traps are in reading the answers:

- **Two statuses, not one.** A custom hostname and its certificate are verified separately. The hostname goes `active` first, and a shop switched on then answers with a TLS error until the certificate catches up.
- **A dozen states each.** What matters is three: serving, still going, and stuck for good (a hostname blocked, moved or deleted, or a certificate whose validation timed out).
- **"Could not ask" is not "failed".** An outage at the API must not mark a working domain dead.
- **Already gone is gone.** Removing a hostname Cloudflare does not know is a 404, and success.

There is no clash with `sugarcommerce/network`'s `Cloudflare`, which reads request headers set by the edge; this one talks to the API.

## Features

- 🌐 **Create, status, delete** — HTTP validation by default, so the customer adds one DNS record.
- 🚦 **`Status`** — `Active`, `Pending` or `Failed` from the hostname and certificate together; the values are what to store.
- 🧾 **`Hostname`** — the id to keep, the name, the status.
- 🛟 **Null means "ask again"** — `status()` never reports an outage as a failure.

## Requirements

PHP 8.5+, [`sugarcommerce/http-client`](https://github.com/sugarcommerce/http-client).

## Installation

```bash
composer require sugarcommerce/cloudflare
```

## Usage

```php
use SugarCommerce\Cloudflare\{CustomHostnames, Status};

$hostnames = new CustomHostnames( $api_token, $zone_id );

$created = $hostnames->create( 'shop.example.com' );   // ?Hostname

if ( null !== $created ) {
    save( $created->id, $created->status->value );     // 'pending'
}

// Later, while it is pending:
$now = $hostnames->status( $id );

if ( null !== $now ) {               // null: Cloudflare could not be asked
    save( $id, $now->status->value );
}

$hostnames->delete( $id );           // true when gone, including already gone
```

Validate the name before sending it — Cloudflare accepts names you would not want to serve. The API token needs **SSL and Certificates: Edit** on the SaaS zone.

`CustomHostnames` is not final, so an application can add a factory from its own configuration.

## Testing

```bash
composer install
composer test
```

18 tests, all against `HttpClient\Fake` — nothing reaches Cloudflare. The request Cloudflare is sent; a refused hostname; validation methods; every way the two statuses combine; an outage that is not a failure; deletion with 404 as success; path encoding; and the `#[\NoDiscard]` on `create()`.

## License

GPL-2.0-or-later
