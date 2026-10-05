<?php
/**
 * The rules for licence keys and their seats.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Class Seats
 *
 * What a seller's software asks on startup — is this key good, is this
 * installation still entitled — and the two writes: take a seat, give one
 * back. The mistakes it keeps out:
 *
 * - **A key for somebody else's product.** A key is a string anybody can
 *   buy, from any seller, in test mode, for nothing. Asked with a product,
 *   a key for another product is reported as not found — so asking
 *   reveals nothing about keys that are not yours.
 * - **A client-chosen instance id.** It is usually a site URL or a machine
 *   name, both guessable, and anything guessable releases somebody else's
 *   seat. The id is issued here, 128 random bits; a fingerprint survives
 *   only as an idempotency hint, so a reinstall gets its seat back.
 * - **A race on the last seat.** The count and the claim happen inside
 *   {@see Store::atomically()}.
 * - **Only checking the key.** A key that is fine but whose seat was
 *   released elsewhere is the case that matters; validate() with an
 *   instance answers for the installation too.
 *
 * Precedence, when several are true: not found, revoked, expired, then the
 * installation.
 *
 * @since 1.0.0
 */
final readonly class Seats {

	/**
	 * @since 1.0.0
	 *
	 * @param Store    $store Where licences and seats live.
	 * @param int|null $now   Fixed time, for tests. Null uses the clock.
	 */
	public function __construct(
		private Store $store,
		private ?int $now = null,
	) {}

	/**
	 * Check a key, and optionally one installation of it, changing nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      Licence key.
	 * @param string $instance An instance id to check as well, or ''.
	 * @param string $product  The product the caller sells, or '' to skip
	 *                         that check (your own screens only).
	 *
	 * @return Check
	 */
	#[\NoDiscard( 'A key that is not valid must not unlock anything.' )]
	public function validate( string $key, string $instance = '', string $product = '' ): Check {
		$license = '' === $key ? null : $this->store->license( $key );

		if ( null !== $license && '' !== $product && ! hash_equals( $license->product, $product ) ) {
			$license = null;
		}

		if ( null === $license ) {
			return new Check( Verdict::NotFound );
		}

		$seat = '' === $instance ? null : $this->store->seat( $license, $instance );

		$verdict = match ( true ) {
			$license->revoked                                                   => Verdict::Revoked,
			null !== $license->expires_at && $license->expires_at < $this->now() => Verdict::Expired,
			'' !== $instance && null === $seat                                  => Verdict::InstanceNotFound,
			null !== $seat && ! $seat->active                                   => Verdict::InstanceDeactivated,
			default                                                             => Verdict::Ok,
		};

		return new Check( $verdict, $license, $this->store->seats_in_use( $license ), $seat );
	}

	/**
	 * Take a seat for an installation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key         Licence key.
	 * @param string $name        What a person calls the machine or site.
	 * @param string $fingerprint Stable client identifier, or ''. An
	 *                            idempotency hint, never an authority.
	 * @param string $ip          The client's address, for the store.
	 * @param string $product     The product the caller sells, or ''.
	 *
	 * @return Activation
	 */
	#[\NoDiscard( 'A refused activation has no seat.' )]
	public function claim( string $key, string $name, string $fingerprint = '', string $ip = '', string $product = '' ): Activation {
		return $this->store->atomically( fn (): Activation => $this->take( $key, $name, $fingerprint, $ip, $product ) );
	}

	/**
	 * Give a seat back, by the instance id issued for it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      Licence key.
	 * @param string $instance The instance id from claim().
	 *
	 * @return Seat|null The seat as it was, when one was released.
	 */
	public function release( string $key, string $instance ): ?Seat {
		$license = '' === $key || '' === $instance ? null : $this->store->license( $key );

		if ( null === $license ) {
			return null;
		}

		$seat = $this->store->seat( $license, $instance );

		return null !== $seat && $this->store->release( $license, $instance ) ? $seat : null;
	}

	/**
	 * A fresh instance id: 128 random bits, hex.
	 *
	 * @since 1.0.0
	 *
	 * @return string 32 characters.
	 *
	 * @throws \Random\RandomException When the CSPRNG is unavailable.
	 */
	public static function instance_id(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * The body of claim(), inside the store's transaction.
	 *
	 * @param string $key         Licence key.
	 * @param string $name        Name.
	 * @param string $fingerprint Fingerprint, or ''.
	 * @param string $ip          Address.
	 * @param string $product     Product, or ''.
	 *
	 * @return Activation
	 */
	private function take( string $key, string $name, string $fingerprint, string $ip, string $product ): Activation {
		$check = $this->validate( $key, '', $product );

		if ( ! $check->valid() || null === $check->license ) {
			return new Activation( null, $check->verdict );
		}

		$license  = $check->license;
		$in_use   = $check->in_use;
		$previous = '' === $fingerprint ? null : $this->store->seat_for_fingerprint( $license, $fingerprint );

		if ( null !== $previous && $previous->active ) {
			return new Activation( Claim::AlreadyActive, Verdict::Ok, $in_use, $license->seats, $this->store->seat( $license, $previous->instance ) ?? $previous );
		}

		if ( $in_use >= $license->seats ) {
			return new Activation( null, Verdict::LimitReached, $in_use, $license->seats );
		}

		if ( null !== $previous ) {
			$this->store->reactivate( $previous, $name );

			return new Activation( Claim::Reactivated, Verdict::Ok, $in_use + 1, $license->seats, $this->store->seat( $license, $previous->instance ) );
		}

		$instance = self::instance_id();

		$this->store->add_seat( $license, $instance, $name, $fingerprint, $ip );

		return new Activation( Claim::Activated, Verdict::Ok, $in_use + 1, $license->seats, $this->store->seat( $license, $instance ) );
	}

	/**
	 * The time.
	 *
	 * @return int
	 */
	private function now(): int {
		return $this->now ?? time();
	}
}
