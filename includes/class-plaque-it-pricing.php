<?php
/**
 * Pricing helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Pricing class. */
class Plaque_It_Pricing {

	/** Calculate surcharge from dimensions. */
	public static function area_surcharge( float $width, float $height ): float {
		$rate = (float) Plaque_It_Settings::get( 'area_rate', 0.0005 );
		return round( max( 0, $width * $height * $rate ), wc_get_price_decimals() );
	}

	/** Calculate final price. */
	public static function final_price( float $base_price, float $width, float $height ): float {
		return round( $base_price + self::area_surcharge( $width, $height ), wc_get_price_decimals() );
	}
}
