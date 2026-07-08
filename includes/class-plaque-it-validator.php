<?php
/**
 * Plaque configuration validation.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Validator class. */
class Plaque_It_Validator {

	/** Decode posted config. */
	public static function decode_posted(): ?array {
		$raw = isset( $_POST['_plaque_it_config'] ) ? wp_unslash( $_POST['_plaque_it_config'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_string( $raw ) || '' === trim( $raw ) || strlen( $raw ) > 200000 ) {
			return null;
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/** Check if product is plaque-enabled. */
	public static function is_enabled_product( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_plaque_it_enabled', true ) && ! self::has_personaliseit( $product_id );
	}

	/** Detect existing PersonaliseIt assignment/configuration for conflict prevention. */
	public static function has_personaliseit( int $product_id ): bool {
		if ( class_exists( 'OC_DB' ) ) {
			if ( method_exists( 'OC_DB', 'get_config_by_product' ) && OC_DB::get_config_by_product( $product_id ) ) {
				return true;
			}
			if ( method_exists( 'OC_DB', 'get_assignment_for_product' ) && OC_DB::get_assignment_for_product( $product_id, 0 ) ) {
				return true;
			}
		}

		return false;
	}

	/** Sanitize and validate config. */
	public static function sanitise( array $data, int $product_id, int $variation_id = 0 ): array|WP_Error {
		$settings = Plaque_It_Settings::all();
		$product  = wc_get_product( $product_id );

		if ( ! $product || ! self::is_enabled_product( $product_id ) ) {
			return new WP_Error( 'plaque_it_disabled', __( 'Plaque customisation is not enabled for this product.', 'plaque-it' ) );
		}

		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || (int) $variation->get_parent_id() !== $product_id ) {
				return new WP_Error( 'plaque_it_bad_variation', __( 'Please choose a valid plaque variation.', 'plaque-it' ) );
			}
		}

		$width  = (float) ( $data['width'] ?? 0 );
		$height = (float) ( $data['height'] ?? 0 );
		$min_w  = (float) get_post_meta( $product_id, '_plaque_it_min_width', true ) ?: (float) $settings['min_width'];
		$max_w  = (float) get_post_meta( $product_id, '_plaque_it_max_width', true ) ?: (float) $settings['max_width'];
		$min_h  = (float) get_post_meta( $product_id, '_plaque_it_min_height', true ) ?: (float) $settings['min_height'];
		$max_h  = (float) get_post_meta( $product_id, '_plaque_it_max_height', true ) ?: (float) $settings['max_height'];

		if ( $width < $min_w || $width > $max_w || $height < $min_h || $height > $max_h ) {
			return new WP_Error( 'plaque_it_size', sprintf( __( 'Plaque size must be between %1$s-%2$smm wide and %3$s-%4$smm high.', 'plaque-it' ), $min_w, $max_w, $min_h, $max_h ) );
		}

		$allowed_corners = (array) ( get_post_meta( $product_id, '_plaque_it_corner_styles', true ) ?: $settings['corner_styles'] );
		$corner          = sanitize_key( (string) ( $data['corner_style'] ?? '' ) );
		if ( ! in_array( $corner, $allowed_corners, true ) ) {
			return new WP_Error( 'plaque_it_corner', __( 'Please choose a valid corner style.', 'plaque-it' ) );
		}

		if ( ! empty( $settings['require_approval'] ) && empty( $data['preview_approved'] ) ) {
			return new WP_Error( 'plaque_it_approval', __( 'Please approve the plaque preview before adding to cart.', 'plaque-it' ) );
		}

		$plaque_colour    = $variation_id ? get_post_meta( $variation_id, '_plaque_it_plaque_colour', true ) : get_post_meta( $product_id, '_plaque_it_plaque_colour', true );
		$engraving_colour = $variation_id ? get_post_meta( $variation_id, '_plaque_it_engraving_colour', true ) : get_post_meta( $product_id, '_plaque_it_engraving_colour', true );
		$plaque_colour    = sanitize_hex_color( $plaque_colour ?: '#111111' ) ?: '#111111';
		$engraving_colour = sanitize_hex_color( $engraving_colour ?: '#ffffff' ) ?: '#ffffff';

		$lines = self::sanitise_lines( is_array( $data['lines'] ?? null ) ? $data['lines'] : [], $product_id, $width, $height );
		if ( is_wp_error( $lines ) ) {
			return $lines;
		}

		return [
			'width'            => $width,
			'height'           => $height,
			'unit'             => 'mm',
			'dpi'              => PLAQUE_IT_DPI,
			'product_id'       => $product_id,
			'variation_id'     => $variation_id,
			'plaque_colour'    => $plaque_colour,
			'engraving_colour' => $engraving_colour,
			'corner_style'     => $corner,
			'lines'            => $lines,
			'preview_approved' => ! empty( $data['preview_approved'] ),
			'pricing'          => [
				'area_mm2'  => $width * $height,
				'surcharge' => Plaque_It_Pricing::area_surcharge( $width, $height ),
			],
			'constraints'      => self::constraints( $width, $height ),
		];
	}

	/** Build constraints. */
	public static function constraints( float $width, float $height ): array {
		$settings = Plaque_It_Settings::all();
		return [
			'safe_width'        => Plaque_It_Renderer::mm_to_px( $width ) * ( (float) $settings['safe_width'] / 100 ),
			'safe_height'       => Plaque_It_Renderer::mm_to_px( $height ) * ( (float) $settings['safe_height'] / 100 ),
			'max_lines'         => (int) $settings['max_lines'],
			'minimum_font_size' => (float) $settings['min_font_size'],
		];
	}

	/** Sanitize and validate text lines. */
	private static function sanitise_lines( array $raw_lines, int $product_id, float $width, float $height ): array|WP_Error {
		$settings    = Plaque_It_Settings::all();
		$constraints = self::constraints( $width, $height );
		$max_lines   = (int) get_post_meta( $product_id, '_plaque_it_max_lines', true ) ?: (int) $settings['max_lines'];
		$allowed_ids = array_map( fn( $font ) => (int) $font->id, Plaque_It_Fonts::active() );

		$lines = [];
		foreach ( $raw_lines as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$text = sanitize_text_field( (string) ( $line['text'] ?? '' ) );
			if ( '' === $text ) {
				continue;
			}
			$font_id = absint( $line['font_id'] ?? 0 );
			if ( ! in_array( $font_id, $allowed_ids, true ) ) {
				$font_id = $allowed_ids[0] ?? 0;
			}
			$font      = $font_id ? Plaque_It_Fonts::get( $font_id ) : null;
			$min_size  = max( (float) $settings['min_font_size'], $font ? (float) $font->min_size : 0 );
			$size      = max( $min_size, (float) ( $line['size'] ?? $min_size ) );
			$width_fac = $font ? (float) $font->width_factor : 0.56;

			if ( ( strlen( $text ) * $size * $width_fac ) > $constraints['safe_width'] ) {
				return new WP_Error( 'plaque_it_line_width', sprintf( __( 'The line "%s" is too large for this plaque width.', 'plaque-it' ), $text ) );
			}

			$lines[] = [
				'text'    => $text,
				'font_id' => $font_id,
				'size'    => $size,
			];
		}

		if ( empty( $lines ) ) {
			return new WP_Error( 'plaque_it_empty', __( 'Please enter at least one plaque message line.', 'plaque-it' ) );
		}

		if ( count( $lines ) > $max_lines ) {
			return new WP_Error( 'plaque_it_lines', sprintf( __( 'This plaque allows a maximum of %d lines.', 'plaque-it' ), $max_lines ) );
		}

		$total_height = array_sum( array_map( fn( $line ) => (float) $line['size'] * 1.25, $lines ) );
		if ( $total_height > $constraints['safe_height'] ) {
			return new WP_Error( 'plaque_it_text_height', __( 'The selected font sizes are too tall for this plaque height.', 'plaque-it' ) );
		}

		return $lines;
	}
}
