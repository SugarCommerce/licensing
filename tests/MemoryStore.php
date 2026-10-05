<?php
/**
 * A store in memory, for the tests.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing\Tests;

use SugarCommerce\Licensing\{License, Seat, Store};

/**
 * Class MemoryStore
 *
 * Arrays, and a flag that says whether work is inside atomically().
 */
final class MemoryStore implements Store {

	/**
	 * Licences by key.
	 *
	 * @var array<string, License>
	 */
	public array $licenses = array();

	/**
	 * Seats: [license id, instance, name, fingerprint, ip, active].
	 *
	 * @var array<int, array{license: int|string, instance: string, name: string, fingerprint: string, ip: string, active: bool}>
	 */
	public array $seats = array();

	/**
	 * Whether work is running inside atomically().
	 */
	public bool $inside = false;

	/**
	 * Writes made outside atomically().
	 */
	public int $loose_writes = 0;

	public function license( string $key ): ?License {
		return $this->licenses[ $key ] ?? null;
	}

	public function seats_in_use( License $license ): int {
		return count( array_filter( $this->seats, static fn ( array $s ): bool => $s['license'] === $license->id && $s['active'] ) );
	}

	public function seat( License $license, string $instance ): ?Seat {
		foreach ( $this->seats as $id => $s ) {
			if ( $s['license'] === $license->id && $s['instance'] === $instance ) {
				return new Seat( $id, $s['instance'], $s['active'], $s );
			}
		}

		return null;
	}

	public function seat_for_fingerprint( License $license, string $fingerprint ): ?Seat {
		foreach ( $this->seats as $id => $s ) {
			if ( $s['license'] === $license->id && $s['fingerprint'] === $fingerprint ) {
				return new Seat( $id, $s['instance'], $s['active'], $s );
			}
		}

		return null;
	}

	public function add_seat( License $license, string $instance, string $name, string $fingerprint, string $ip ): void {
		$this->write();
		$this->seats[] = array( 'license' => $license->id, 'instance' => $instance, 'name' => $name, 'fingerprint' => $fingerprint, 'ip' => $ip, 'active' => true );
	}

	public function reactivate( Seat $seat, string $name ): void {
		$this->write();
		$this->seats[ (int) $seat->id ]['active'] = true;

		if ( '' !== $name ) {
			$this->seats[ (int) $seat->id ]['name'] = $name;
		}
	}

	public function release( License $license, string $instance ): bool {
		foreach ( $this->seats as $id => $s ) {
			if ( $s['license'] === $license->id && $s['instance'] === $instance && $s['active'] ) {
				$this->seats[ $id ]['active'] = false;

				return true;
			}
		}

		return false;
	}

	public function atomically( \Closure $work ): mixed {
		$this->inside = true;

		try {
			return $work();
		} finally {
			$this->inside = false;
		}
	}

	/**
	 * Count a write made outside a transaction.
	 *
	 * @return void
	 */
	private function write(): void {
		if ( ! $this->inside ) {
			++$this->loose_writes;
		}
	}
}
