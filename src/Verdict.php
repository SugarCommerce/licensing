<?php
/**
 * Why a key, or an installation of it, is or is not good.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Enum Verdict
 *
 * A reason, never a bare bool: the seller's software branches on the value
 * ("expired: show renew") and can show {@see self::public_message()}
 * without writing its own copy. A library that has to invent the wording
 * gets it wrong in a way the seller only hears about from a customer.
 *
 * The values are the wire form; do not rename them.
 *
 * @since 1.0.0
 */
enum Verdict: string {

	/**
	 * The key, and the installation if one was named, is good.
	 */
	case Ok = 'ok';

	/**
	 * No such key, or a key for another product.
	 */
	case NotFound = 'not_found';

	/**
	 * The licence was taken back, usually by a refund.
	 */
	case Revoked = 'revoked';

	/**
	 * The licence, or the subscription it follows, has lapsed.
	 */
	case Expired = 'expired';

	/**
	 * The key is good but the installation is not one of its seats.
	 */
	case InstanceNotFound = 'instance_not_found';

	/**
	 * The key is good but the installation's seat was released.
	 */
	case InstanceDeactivated = 'instance_deactivated';

	/**
	 * Every seat is taken.
	 */
	case LimitReached = 'limit_reached';

	/**
	 * No product was named. {@see Seats} returns it for an empty product
	 * (since 1.1.0) rather than skipping the check.
	 */
	case ProductRequired = 'product_required';

	/**
	 * For the caller to return when licensing is switched off; never returned by {@see Seats}.
	 */
	case Unavailable = 'unavailable';

	/**
	 * A sentence a client can show a person.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Null for Ok, which needs none.
	 */
	public function public_message(): ?string {
		return match ( $this ) {
			self::Ok                  => null,
			self::NotFound            => 'That licence key does not exist.',
			self::Revoked             => 'This licence was revoked. If the order was refunded, that is why.',
			self::Expired             => 'This licence has expired.',
			self::InstanceNotFound    => 'That installation is not on this licence.',
			self::InstanceDeactivated => 'This installation was deactivated. Activate it again to carry on.',
			self::LimitReached        => 'This licence key has reached its activation limit. Deactivate another installation first.',
			self::ProductRequired     => 'Send the product id this software is sold as, so a key for another product is refused.',
			self::Unavailable         => 'Licensing is not available on this install.',
		};
	}
}
