<?php
/**
 * A licence, as the seat rules need it.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Class License
 *
 * Only what deciding needs, plus the store's own record for the caller.
 * `expires_at` is the expiry **resolved** by the store: a licence that
 * follows a subscription has none of its own and takes the subscription's
 * period, which only the store can know.
 *
 * @since 1.0.0
 */
final readonly class License {

	/**
	 * @since 1.0.0
	 *
	 * @param int|string           $id         The store's id for it.
	 * @param string               $product    The product it is for.
	 * @param int                  $seats      How many installations it allows.
	 * @param bool                 $revoked    Whether it was taken back.
	 * @param int|null             $expires_at When it stops working; null for never.
	 * @param array<string, mixed> $record     The store's own row, passed through.
	 */
	public function __construct(
		public int|string $id,
		public string $product,
		public int $seats,
		public bool $revoked = false,
		public ?int $expires_at = null,
		public array $record = array(),
	) {}
}
