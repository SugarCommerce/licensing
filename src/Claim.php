<?php
/**
 * How a seat was had.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing;

/**
 * Enum Claim
 *
 * Three kinds of success, kept apart because they mean different things
 * to whoever is listening: AlreadyActive is a client checking in on
 * startup and changes nothing, so announcing it would send a seller an
 * event per launch.
 *
 * @since 1.0.0
 */
enum Claim: string {

	/**
	 * A new seat was taken.
	 */
	case Activated = 'activated';

	/**
	 * A released seat with the same fingerprint was taken again.
	 */
	case Reactivated = 'reactivated';

	/**
	 * The same fingerprint already held a seat; nothing changed.
	 */
	case AlreadyActive = 'already_active';

	/**
	 * Whether a seat changed hands, so somebody should be told.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function changed(): bool {
		return self::AlreadyActive !== $this;
	}
}
