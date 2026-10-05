# Licensing

Licence-key seats for software sellers: validate a key (and one installation of it) with a reason a client can show, claim a seat atomically so parallel activations cannot overrun the limit, and release it by an instance id the server issued. Storage is yours, behind one interface. Zero dependencies.

## Why

A licence server looks like three endpoints and a counter. The holes are all in the details:

- **A key for somebody else's product.** A key is a string anybody can buy, from any seller, in test mode, for nothing. Unless the check names the product, a key bought for one thing unlocks another.
- **A client-chosen instance id.** Clients name themselves by site URL or machine name, which are guessable, and a guessable id lets anybody release somebody else's seat.
- **A race on the last seat.** Count, then insert, as two statements: ten parallel activations of a one-seat key each count zero and each take one.
- **Checking only the key.** A key that is fine but whose seat was released on another machine is exactly what a client checking only the key never sees.
- **A bool instead of a reason.** "Invalid" makes the seller's support team find out what happened; "expired" lets their software say "renew here".

## Features

- ✅ **`validate()`** — the key, optionally one installation, optionally the product; precedence not found → revoked → expired → installation.
- 💺 **`claim()`** — inside your transaction; instance ids issued here (128 random bits); a fingerprint only as an idempotency hint, so a reinstall gets its seat back.
- 🔓 **`release()`** — by the issued instance id only, scoped to the key; the seat is kept, inactive, so a key's history stays visible.
- 🗣️ **`Verdict`** — stable wire values with a sentence for each; `Claim` says whether a seat changed hands, so check-ins on startup are not announced.
- 🗄️ **`Store`** — eight methods; your tables, your expiry rules, your hashing of addresses.

## Requirements

PHP 8.5+

## Installation

```bash
composer require sugarcommerce/licensing
```

## Usage

Implement `Store` over your tables:

```php
use SugarCommerce\Licensing\{License, Seat, Store};

final class MyStore implements Store {

    public function license( string $key ): ?License {
        $row = $this->db->find_license( $key );

        return null === $row ? null : new License(
            id: $row['id'],
            product: $row['product_id'],
            seats: $row['max_activations'],
            revoked: 'revoked' === $row['status'],
            expires_at: $this->expiry( $row ),   // resolved: a subscription's period, say
            record: $row,
        );
    }

    public function atomically( \Closure $work ): mixed {
        return $this->db->transaction( $work );   // BEGIN IMMEDIATE on SQLite
    }

    // seats_in_use, seat, seat_for_fingerprint, add_seat, reactivate, release …
}
```

Then:

```php
use SugarCommerce\Licensing\Seats;

$seats = new Seats( new MyStore( $db ) );

$check = $seats->validate( $key, $instance_id, product: 'prod_ABC' );
$check->valid();                       // bool
$check->verdict->value;                // 'expired'
$check->verdict->public_message();     // 'This licence has expired.'

$taken = $seats->claim( $key, 'Studio Mac', $fingerprint, $ip, product: 'prod_ABC' );
$taken->ok();
$taken->reason();                      // 'activated', 'already_active', 'limit_reached' …
$taken->seat->instance;                // the client stores this
$taken->claim?->changed();             // announce only when true

$released = $seats->release( $key, $instance_id );   // ?Seat, as it was
```

`add_seat()` receives the client's address as sent; hash it (or drop it) in your store.

## Testing

```bash
composer install
composer test
```

22 tests against an in-memory store: product scoping, every verdict and their precedence, expiry to the second, an installation released elsewhere, another key's instance, issued instance ids, the last seat, fingerprints as idempotency (already active, reactivated, waiting for room), bad keys claiming nothing, every write inside the transaction, release by instance only and only once, stable wire values, and the `#[\NoDiscard]` results.

## License

GPL-2.0-or-later
