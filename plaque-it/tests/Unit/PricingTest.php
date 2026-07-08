<?php
/**
 * Pricing tests.
 *
 * @package PlaqueIt
 */

use PHPUnit\Framework\TestCase;

/** Pricing test case. */
class PricingTest extends TestCase {

	public function test_area_surcharge_uses_admin_rate(): void {
		update_option(
			'plaque_it_settings',
			array_merge(
				Plaque_It_Settings::defaults(),
				[ 'area_rate' => 0.01 ]
			)
		);

		$this->assertSame( 200.0, Plaque_It_Pricing::area_surcharge( 200, 100 ) );
	}

	public function test_final_price_adds_surcharge_to_base_price(): void {
		update_option(
			'plaque_it_settings',
			array_merge(
				Plaque_It_Settings::defaults(),
				[ 'area_rate' => 0.001 ]
			)
		);

		$this->assertSame( 35.0, Plaque_It_Pricing::final_price( 15, 100, 200 ) );
	}
}
