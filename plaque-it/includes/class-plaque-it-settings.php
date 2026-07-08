<?php
/**
 * Settings helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Settings class. */
class Plaque_It_Settings {

	/** Default settings. */
	public static function defaults(): array {
		return [
			'min_width'          => 50,
			'max_width'          => 600,
			'min_height'         => 25,
			'max_height'         => 400,
			'min_font_size'      => 8,
			'max_lines'          => 6,
			'safe_width'         => 85,
			'safe_height'        => 80,
			'area_rate'          => 0.0005,
			'corner_styles'      => [ 'scallop', 'straight', 'none', 'rounded' ],
			'require_approval'   => 1,
			'font_restrictions'  => '',
		];
	}

	/** Get all settings. */
	public static function all(): array {
		$settings = get_option( 'plaque_it_settings', [] );
		return wp_parse_args( is_array( $settings ) ? $settings : [], self::defaults() );
	}

	/** Get a setting. */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::all();
		return $settings[ $key ] ?? $default;
	}

	/** Save settings from request. */
	public static function save( array $data ): void {
		$defaults = self::defaults();
		$styles   = isset( $data['corner_styles'] ) && is_array( $data['corner_styles'] ) ? array_map( 'sanitize_key', wp_unslash( $data['corner_styles'] ) ) : [];

		$settings = [
			'min_width'         => max( 1, (float) ( $data['min_width'] ?? $defaults['min_width'] ) ),
			'max_width'         => max( 1, (float) ( $data['max_width'] ?? $defaults['max_width'] ) ),
			'min_height'        => max( 1, (float) ( $data['min_height'] ?? $defaults['min_height'] ) ),
			'max_height'        => max( 1, (float) ( $data['max_height'] ?? $defaults['max_height'] ) ),
			'min_font_size'     => max( 1, (float) ( $data['min_font_size'] ?? $defaults['min_font_size'] ) ),
			'max_lines'         => max( 1, absint( $data['max_lines'] ?? $defaults['max_lines'] ) ),
			'safe_width'        => min( 100, max( 1, (float) ( $data['safe_width'] ?? $defaults['safe_width'] ) ) ),
			'safe_height'       => min( 100, max( 1, (float) ( $data['safe_height'] ?? $defaults['safe_height'] ) ) ),
			'area_rate'         => max( 0, (float) ( $data['area_rate'] ?? $defaults['area_rate'] ) ),
			'corner_styles'     => array_values( array_intersect( $styles, [ 'scallop', 'straight', 'none', 'rounded' ] ) ) ?: $defaults['corner_styles'],
			'require_approval'  => empty( $data['require_approval'] ) ? 0 : 1,
			'font_restrictions' => sanitize_textarea_field( wp_unslash( $data['font_restrictions'] ?? '' ) ),
		];

		update_option( 'plaque_it_settings', $settings );
	}
}
