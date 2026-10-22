<?php
/**
 * Where licences and their seats live.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Interface Store
 *
 * Storage is the application's: its tables, its sealing of personal data,
 * its idea of when a subscription's licence lapses. {@see Seats} holds the
 * rules and calls these.
 *
 * Two things an implementation must get right:
 *
 * - **atomically() is a write transaction.** {@see Seats::claim()} counts
 *   the seats in use and adds one inside it. Apart, ten parallel
 *   activations of a one-seat key each count zero and each take one. On
 *   SQLite that means `BEGIN IMMEDIATE`; elsewhere, a row lock on the
 *   licence.
 * - **Seats are scoped to their licence.** seat() must not find an
 *   instance of another licence, or one key could release another's seat.
 *
 * @since 1.0.0
 */
interface Store {

	/**
	 * A licence by its key, with its expiry resolved.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Licence key, as the client sent it.
	 *
	 * @return License|null
	 */
	public function license( string $key ): ?License;

	/**
	 * How many seats a licence has in use.
	 *
	 * @since 1.0.0
	 *
	 * @param License $license Licence.
	 *
	 * @return int
	 */
	public function seats_in_use( License $license ): int;

	/**
	 * One of a licence's seats, active or not, by the instance id issued for it.
	 *
	 * @since 1.0.0
	 *
	 * @param License $license  Licence.
	 * @param string  $instance Instance id.
	 *
	 * @return Seat|null
	 */
	public function seat( License $license, string $instance ): ?Seat;

	/**
	 * A seat the same client claimed before, by its fingerprint.
	 *
	 * For idempotency only — a reinstall gets its seat back — never for
	 * authorising anything: fingerprints are guessable. Whatever this
	 * returns is handed back to the client, instance id included, by
	 * {@see Seats::claim()}.
	 *
	 * @since 1.0.0
	 *
	 * @param License $license     Licence.
	 * @param string  $fingerprint Client-supplied, non-empty.
	 *
	 * @return Seat|null
	 */
	public function seat_for_fingerprint( License $license, string $fingerprint ): ?Seat;

	/**
	 * Take a new seat.
	 *
	 * @since 1.0.0
	 *
	 * @param License $license     Licence.
	 * @param string  $instance    The id Seats issued for it.
	 * @param string  $name        What a person calls the machine or site.
	 * @param string  $fingerprint Client-supplied identifier, or ''.
	 * @param string  $ip          The client's address. Hash or drop it;
	 *                             do not keep it as sent.
	 *
	 * @return void
	 */
	public function add_seat( License $license, string $instance, string $name, string $fingerprint, string $ip ): void;

	/**
	 * Make a released seat active again.
	 *
	 * @since 1.0.0
	 *
	 * @param Seat   $seat Seat.
	 * @param string $name A new name for it, or '' to keep the old.
	 *
	 * @return void
	 */
	public function reactivate( Seat $seat, string $name ): void;

	/**
	 * Release an active seat, keeping the record.
	 *
	 * @since 1.0.0
	 *
	 * @param License $license  Licence.
	 * @param string  $instance Instance id.
	 *
	 * @return bool Whether an active seat was released.
	 */
	public function release( License $license, string $instance ): bool;

	/**
	 * Run work in one write transaction.
	 *
	 * @since 1.0.0
	 *
	 * @template T
	 *
	 * @param \Closure(): T $work The work.
	 *
	 * @return T What the work returned.
	 */
	public function atomically( \Closure $work ): mixed;
}
