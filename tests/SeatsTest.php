<?php
/**
 * The rules for licence keys and their seats.
 *
 * @package   SugarCommerce\Licensing
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace SugarCommerce\Licensing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Licensing\{Activation, Claim, License, Seats, Verdict};

/**
 * Class SeatsTest
 */
final class SeatsTest extends TestCase {

	private const int NOW = 1_800_000_000;

	private MemoryStore $store;

	protected function setUp(): void {
		$this->store = new MemoryStore();

		$this->store->licenses['KEY-ONE']     = new License( 1, 'prod_A', 1 );
		$this->store->licenses['KEY-TWO']     = new License( 2, 'prod_A', 2 );
		$this->store->licenses['KEY-REVOKED'] = new License( 3, 'prod_A', 1, revoked: true, expires_at: self::NOW - 10 );
		$this->store->licenses['KEY-EXPIRED'] = new License( 4, 'prod_A', 1, expires_at: self::NOW - 1 );
		$this->store->licenses['KEY-OTHER']   = new License( 5, 'prod_B', 1 );
	}

	private function seats(): Seats {
		return new Seats( $this->store, self::NOW );
	}

	/* ─── Validating ────────────────────────────────────────────────── */

	public function test_a_good_key_is_valid(): void {
		$check = $this->seats()->validate( 'KEY-ONE', 'prod_A' );

		$this->assertTrue( $check->valid() );
		$this->assertSame( Verdict::Ok, $check->verdict );
		$this->assertSame( 1, $check->license?->id );
		$this->assertSame( 0, $check->in_use );
	}

	/**
	 * A key bought for another product — or another seller's — is reported
	 * as not found, so asking reveals nothing about it.
	 */
	public function test_a_key_for_another_product_is_not_found(): void {
		$check = $this->seats()->validate( 'KEY-OTHER', 'prod_A' );

		$this->assertSame( Verdict::NotFound, $check->verdict );
		$this->assertNull( $check->license );
		$this->assertTrue( $this->seats()->validate( 'KEY-OTHER', 'prod_B' )->valid() );
	}

	/**
	 * There is no "any product". An empty one used to skip the check, so a
	 * caller that forgot to send it accepted every key anybody sold.
	 */
	public function test_the_product_cannot_be_left_out(): void {
		$check = $this->seats()->validate( 'KEY-ONE', '' );

		$this->assertSame( Verdict::ProductRequired, $check->verdict );
		$this->assertNull( $check->license );
		$this->assertSame( Verdict::ProductRequired, $this->seats()->claim( 'KEY-ONE', '', 'Laptop' )->verdict );
		$this->assertSame( array(), $this->store->seats, 'Nothing claimed.' );

		$seat = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop' )->seat;

		$this->assertNull( $this->seats()->release( 'KEY-ONE', 'prod_A', '', (string) $seat?->instance ) );
		$this->assertNull( $this->seats()->release( 'KEY-ONE', 'prod_A', 'prod_B', (string) $seat?->instance ), 'Another product releases nothing.' );
		$this->assertTrue( $this->store->seats[0]['active'] );
	}

	/**
	 * @return array<string, array{string, Verdict}>
	 */
	public static function verdicts(): array {
		return array(
			'unknown'                  => array( 'NOPE', Verdict::NotFound ),
			'empty'                    => array( '', Verdict::NotFound ),
			'revoked beats expired'    => array( 'KEY-REVOKED', Verdict::Revoked ),
			'expired'                  => array( 'KEY-EXPIRED', Verdict::Expired ),
		);
	}

	#[DataProvider( 'verdicts' )]
	public function test_a_bad_key_says_why( string $key, Verdict $verdict ): void {
		$this->assertSame( $verdict, $this->seats()->validate( $key, 'prod_A' )->verdict );
	}

	public function test_expiry_is_to_the_second(): void {
		$this->store->licenses['KEY-NOW'] = new License( 9, 'prod_A', 1, expires_at: self::NOW );

		$this->assertTrue( $this->seats()->validate( 'KEY-NOW', 'prod_A' )->valid(), 'Still good at its last second.' );
	}

	/**
	 * A key that is fine but whose seat was released elsewhere is the case
	 * a client checking only the key never sees.
	 */
	public function test_an_installation_is_checked_as_well_as_the_key(): void {
		$seat = $this->seats()->claim( 'KEY-TWO', 'prod_A', 'Laptop' )->seat;

		$this->assertTrue( $this->seats()->validate( 'KEY-TWO', 'prod_A', (string) $seat?->instance )->valid() );
		$this->assertSame( Verdict::InstanceNotFound, $this->seats()->validate( 'KEY-TWO', 'prod_A', 'not-an-instance' )->verdict );

		$this->assertNotNull( $this->seats()->release( 'KEY-TWO', 'prod_A', (string) $seat?->instance ) );
		$this->assertSame( Verdict::InstanceDeactivated, $this->seats()->validate( 'KEY-TWO', 'prod_A', (string) $seat?->instance )->verdict );
	}

	public function test_an_instance_of_another_key_is_not_found(): void {
		$seat = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop' )->seat;

		$this->assertSame( Verdict::InstanceNotFound, $this->seats()->validate( 'KEY-TWO', 'prod_A', (string) $seat?->instance )->verdict );
	}

	/* ─── Claiming ──────────────────────────────────────────────────── */

	public function test_a_seat_is_claimed_with_an_id_we_issue(): void {
		$taken = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'https://my-site.example', '203.0.113.5' );

		$this->assertTrue( $taken->ok() );
		$this->assertSame( Claim::Activated, $taken->claim );
		$this->assertSame( 'activated', $taken->reason() );
		$this->assertSame( 1, $taken->in_use );
		$this->assertSame( 1, $taken->seats );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', (string) $taken->seat?->instance );
		$this->assertNotSame( 'https://my-site.example', $taken->seat?->instance, 'Never the client\'s own name for itself.' );
		$this->assertSame( '203.0.113.5', $this->store->seats[0]['ip'], 'Handed to the store, which decides how to keep it.' );
	}

	public function test_the_last_seat_cannot_be_taken_twice(): void {
		$this->assertTrue( $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop' )->ok() );

		$refused = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Desktop' );

		$this->assertFalse( $refused->ok() );
		$this->assertSame( Verdict::LimitReached, $refused->verdict );
		$this->assertSame( 'limit_reached', $refused->reason() );
		$this->assertSame( 1, $refused->in_use );
		$this->assertNull( $refused->seat );
	}

	/**
	 * A reinstall sends the same fingerprint and gets its seat back rather
	 * than burning another.
	 */
	public function test_the_same_fingerprint_gets_its_seat_back(): void {
		$first = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'fp-1' );
		$again = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'fp-1' );

		$this->assertSame( Claim::AlreadyActive, $again->claim );
		$this->assertFalse( $again->claim->changed(), 'Nothing to announce.' );
		$this->assertSame( $first->seat?->instance, $again->seat?->instance );
		$this->assertSame( 1, $again->in_use );
		$this->assertCount( 1, $this->store->seats );
	}

	public function test_a_released_fingerprint_is_reactivated_while_there_is_room(): void {
		$first = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'fp-1' );

		$this->seats()->release( 'KEY-ONE', 'prod_A', (string) $first->seat?->instance );

		$back = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop, renamed', 'fp-1' );

		$this->assertSame( Claim::Reactivated, $back->claim );
		$this->assertTrue( $back->claim->changed() );
		$this->assertSame( $first->seat?->instance, $back->seat?->instance );
		$this->assertSame( 'Laptop, renamed', $this->store->seats[0]['name'] );
	}

	public function test_a_released_fingerprint_waits_for_room(): void {
		$first = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'fp-1' );

		$this->seats()->release( 'KEY-ONE', 'prod_A', (string) $first->seat?->instance );
		$this->assertTrue( $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Desktop', 'fp-2' )->ok() );

		$this->assertSame( Verdict::LimitReached, $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop', 'fp-1' )->verdict );
	}

	public function test_a_bad_key_claims_nothing(): void {
		foreach ( array( 'KEY-REVOKED' => Verdict::Revoked, 'KEY-EXPIRED' => Verdict::Expired, 'NOPE' => Verdict::NotFound ) as $key => $verdict ) {
			$this->assertEquals( new Activation( null, $verdict ), $this->seats()->claim( $key, 'prod_A', 'Laptop' ), $key );
		}

		$this->assertSame( Verdict::NotFound, $this->seats()->claim( 'KEY-OTHER', 'prod_A', 'Laptop' )->verdict );
		$this->assertSame( array(), $this->store->seats );
	}

	/**
	 * Counting and taking happen in one transaction; apart, parallel
	 * activations each count the same free seat.
	 */
	public function test_claiming_is_one_transaction(): void {
		(void) $this->seats()->claim( 'KEY-TWO', 'prod_A', 'Laptop' );
		(void) $this->seats()->claim( 'KEY-TWO', 'prod_A', 'Laptop', 'fp' );

		$this->assertSame( 0, $this->store->loose_writes );
	}

	/* ─── Releasing ─────────────────────────────────────────────────── */

	public function test_a_seat_is_released_by_its_instance_and_kept(): void {
		$seat     = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop' )->seat;
		$released = $this->seats()->release( 'KEY-ONE', 'prod_A', (string) $seat?->instance );

		$this->assertSame( $seat?->instance, $released?->instance );
		$this->assertTrue( $released?->active, 'The seat as it was.' );
		$this->assertFalse( $this->store->seats[0]['active'] );
		$this->assertCount( 1, $this->store->seats, 'The history stays.' );
		$this->assertNull( $this->seats()->release( 'KEY-ONE', 'prod_A', (string) $seat?->instance ), 'Only once.' );
	}

	public function test_nothing_is_released_by_a_guess(): void {
		$seat = $this->seats()->claim( 'KEY-ONE', 'prod_A', 'Laptop' )->seat;

		$this->assertNull( $this->seats()->release( 'KEY-ONE', 'prod_A', '' ) );
		$this->assertNull( $this->seats()->release( 'KEY-TWO', 'prod_A', (string) $seat?->instance ), 'Another key\'s seat.' );
		$this->assertNull( $this->seats()->release( 'NOPE', 'prod_A', (string) $seat?->instance ) );
		$this->assertTrue( $this->store->seats[0]['active'] );
	}

	/* ─── Wording ───────────────────────────────────────────────────── */

	public function test_every_refusal_has_a_sentence_and_ok_needs_none(): void {
		foreach ( Verdict::cases() as $verdict ) {
			Verdict::Ok === $verdict
				? $this->assertNull( $verdict->public_message() )
				: $this->assertStringEndsWith( '.', (string) $verdict->public_message(), $verdict->value );
		}
	}

	public function test_the_wire_values_do_not_move(): void {
		$this->assertSame(
			array( 'ok', 'not_found', 'revoked', 'expired', 'instance_not_found', 'instance_deactivated', 'limit_reached', 'product_required', 'unavailable' ),
			array_map( static fn ( Verdict $v ): string => $v->value, Verdict::cases() )
		);
		$this->assertSame( array( 'activated', 'reactivated', 'already_active' ), array_map( static fn ( Claim $c ): string => $c->value, Claim::cases() ) );
	}

	/* ─── Results that must be checked ──────────────────────────────── */

	/**
	 * @return array<string, array{string}>
	 */
	public static function must_check(): array {
		return array( 'validate' => array( 'validate' ), 'claim' => array( 'claim' ) );
	}

	#[DataProvider( 'must_check' )]
	public function test_the_result_must_be_used( string $method ): void {
		$attributes = ( new \ReflectionMethod( Seats::class, $method ) )->getAttributes( \NoDiscard::class );

		$this->assertCount( 1, $attributes, $method . ' lost #[\NoDiscard].' );
		$this->assertNotSame( '', $attributes[0]->newInstance()->message );
	}
}
