<?php
/**
 * One installation of a licence.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Class Seat
 *
 * A released seat is kept, inactive, rather than deleted: "this key has
 * moved between forty machines" is the pattern that separates somebody
 * with several laptops from a key posted on a forum, and it is only
 * visible if the history is.
 *
 * @since 1.0.0
 */
final readonly class Seat {

	/**
	 * @since 1.0.0
	 *
	 * @param int|string           $id       The store's id for it.
	 * @param string               $instance The id issued when it was claimed.
	 * @param bool                 $active   Whether it holds a seat now.
	 * @param array<string, mixed> $record   The store's own row, passed through.
	 */
	public function __construct(
		public int|string $id,
		public string $instance,
		public bool $active = true,
		public array $record = array(),
	) {}
}
