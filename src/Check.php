<?php
/**
 * The answer to "is this key good?".
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Class Check
 *
 * What {@see Seats::validate()} found: the verdict, and the licence and
 * seat it was reached from, for the caller to show or log. A licence for
 * another product is never here, so a refused key reveals nothing.
 *
 * @since 1.0.0
 */
final readonly class Check {

	/**
	 * Record the outcome of a check. Only {@see Seats::validate()} makes one.
	 *
	 * @since 1.0.0
	 *
	 * @param Verdict      $verdict Why.
	 * @param License|null $license The licence, when the key is one (and for
	 *                              the product asked about).
	 * @param int          $in_use  Seats in use.
	 * @param Seat|null    $seat    The installation asked about, if one was
	 *                              and it exists.
	 */
	public function __construct(
		public Verdict $verdict,
		public ?License $license = null,
		public int $in_use = 0,
		public ?Seat $seat = null,
	) {}

	/**
	 * Whether the key — and the installation, if one was named — is good.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function valid(): bool {
		return Verdict::Ok === $this->verdict;
	}
}
