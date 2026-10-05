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

	case Ok                  = 'ok';
	case NotFound            = 'not_found';
	case Revoked             = 'revoked';
	case Expired             = 'expired';
	case InstanceNotFound    = 'instance_not_found';
	case InstanceDeactivated = 'instance_deactivated';
	case LimitReached        = 'limit_reached';
	case ProductRequired     = 'product_required';
	case Unavailable         = 'unavailable';

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
