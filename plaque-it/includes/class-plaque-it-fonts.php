<?php
/**
 * Font helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Fonts class. */
class Plaque_It_Fonts {

	/** Get active fonts. */
	public static function active(): array {
		global $wpdb;
		$table = Plaque_It_DB::fonts_table();
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY name ASC" ) ?: [];
	}

	/** Get all fonts. */
	public static function all(): array {
		global $wpdb;
		$table = Plaque_It_DB::fonts_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ) ?: [];
	}

	/** Get one font. */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = Plaque_It_DB::fonts_table();
		$font  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $font ?: null;
	}

	/** Upload font from admin request. */
	public static function upload( array $file, array $data ): ?string {
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return __( 'No font file selected.', 'plaque-it' );
		}

		$ext = strtolower( pathinfo( sanitize_file_name( (string) $file['name'] ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, [ 'ttf', 'otf', 'woff', 'woff2' ], true ) ) {
			return __( 'Only TTF, OTF, WOFF, and WOFF2 fonts are allowed.', 'plaque-it' );
		}

		$upload_dir = Plaque_It_Files::ensure_upload_dir( 'fonts' );
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir->get_error_message();
		}
		$dir = $upload_dir['dir'];
		$url = $upload_dir['url'];

		$name      = sanitize_text_field( wp_unslash( $data['name'] ?? pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ) );
		$file_name = wp_unique_filename( $dir, sanitize_file_name( $file['name'] ) );
		$target    = trailingslashit( $dir ) . $file_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return __( 'Could not move uploaded font.', 'plaque-it' );
		}

		global $wpdb;
		$wpdb->insert(
			Plaque_It_DB::fonts_table(),
			[
				'name'                  => $name ?: pathinfo( $file_name, PATHINFO_FILENAME ),
				'file_path'             => $target,
				'file_url'              => trailingslashit( $url ) . $file_name,
				'weight'                => sanitize_text_field( wp_unslash( $data['weight'] ?? '400' ) ),
				'style'                 => sanitize_key( wp_unslash( $data['style'] ?? 'normal' ) ),
				'width_factor'          => max( 0.1, (float) ( $data['width_factor'] ?? 0.56 ) ),
				'min_size'              => max( 1, (float) ( $data['min_size'] ?? Plaque_It_Settings::get( 'min_font_size', 8 ) ) ),
				'active'                => empty( $data['active'] ) ? 0 : 1,
				'production_restricted' => empty( $data['production_restricted'] ) ? 0 : 1,
				'created_at'            => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s' ]
		);

		return null;
	}

	/** Delete a font and its file. */
	public static function delete( int $id ): bool {
		$font = self::get( $id );
		if ( ! $font ) {
			return false;
		}

		if ( ! empty( $font->file_path ) && is_file( $font->file_path ) ) {
			unlink( $font->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		global $wpdb;
		return false !== $wpdb->delete( Plaque_It_DB::fonts_table(), [ 'id' => $id ], [ '%d' ] );
	}

	/** Frontend font payload. */
	public static function payload(): array {
		return array_map(
			fn( $font ): array => [
				'id'          => (int) $font->id,
				'name'        => (string) $font->name,
				'url'         => (string) $font->file_url,
				'weight'      => (string) $font->weight,
				'style'       => (string) $font->style,
				'widthFactor' => (float) $font->width_factor,
				'minSize'     => (float) $font->min_size,
			],
			self::active()
		);
	}
}
