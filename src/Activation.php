<?php
/**
 * The answer to "may this installation have a seat?".
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Class Activation
 *
 * Either a {@see Claim} (it has a seat) or a {@see Verdict} saying why
 * not. `seat->instance` is what the client stores: it is how it releases
 * the seat later, and how it asks whether this installation is still
 * entitled.
 *
 * @since 1.0.0
 */
final readonly class Activation {

	/**
	 * Record the outcome of a claim. Only {@see Seats::claim()} makes one.
	 *
	 * @since 1.0.0
	 *
	 * @param Claim|null $claim   How the seat was had; null when refused.
	 * @param Verdict    $verdict Ok, or why it was refused.
	 * @param int        $in_use  Seats in use afterwards.
	 * @param int        $seats   Seats allowed; 0 when there is no licence.
	 * @param Seat|null  $seat    The seat, when there is one.
	 */
	public function __construct(
		public ?Claim $claim,
		public Verdict $verdict,
		public int $in_use = 0,
		public int $seats = 0,
		public ?Seat $seat = null,
	) {}

	/**
	 * Whether the installation has a seat.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function ok(): bool {
		return null !== $this->claim;
	}

	/**
	 * The machine-readable reason: the claim when it worked, the verdict
	 * when it did not.
	 *
	 * @since 1.0.0
	 *
	 * @return string e.g. `activated`, `limit_reached`.
	 */
	public function reason(): string {
		return $this->claim->value ?? $this->verdict->value;
	}
}
