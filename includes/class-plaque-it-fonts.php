<?php
/**
 * Font helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Fonts class. */
class Plaque_It_Fonts {

	/** Allowed font MIME types. */
	private const MIME_TYPES = [
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
	];

	/** Register font system hooks. */
	public function register(): void {
		add_filter( 'upload_mimes', [ $this, 'allow_font_mimes' ] );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_font_mime' ], 10, 4 );
		add_action( 'wp_head', [ $this, 'output_font_face_css' ], 5 );
		add_action( 'admin_head', [ $this, 'output_font_face_css' ], 5 );
	}

	/** Add font MIME types to WordPress allowed uploads. */
	public function allow_font_mimes( array $mimes ): array {
		foreach ( self::MIME_TYPES as $ext => $mime ) {
			$mimes[ $ext ] = $mime;
		}
		return $mimes;
	}

	/** Fix MIME type detection for font files. */
	public function fix_font_mime( array $data, string $file, string $filename, ?array $mimes ): array {
		unset( $file, $mimes );
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( isset( self::MIME_TYPES[ $ext ] ) ) {
			$data['ext']  = $ext;
			$data['type'] = self::MIME_TYPES[ $ext ];
		}
		return $data;
	}

	/** Output active font declarations for admin and frontend usage. */
	public function output_font_face_css(): void {
		$fonts = self::active();
		if ( empty( $fonts ) ) {
			return;
		}

		$output = '';
		foreach ( $fonts as $font ) {
			if ( empty( $font->file_url ) ) {
				continue;
			}
			$output .= sprintf(
				"@font-face{font-family:'PlaqueItFont%d';src:url('%s') format('%s');font-weight:%s;font-style:%s;font-display:swap;}\n",
				(int) $font->id,
				esc_url( $font->file_url ),
				esc_attr( self::format( (string) $font->file_url ) ),
				esc_attr( $font->weight ),
				esc_attr( $font->style )
			);
		}

		if ( '' !== $output ) {
			echo "\n<style id=\"plaque-it-font-faces\">\n" . $output . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

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
				'family'      => 'PlaqueItFont' . (int) $font->id,
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

	/** Return CSS font format from a file path or URL. */
	private static function format( string $path ): string {
		$ext = strtolower( pathinfo( wp_parse_url( $path, PHP_URL_PATH ) ?: $path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'woff2' => 'woff2',
			'woff'  => 'woff',
			'otf'   => 'opentype',
			default => 'truetype',
		};
	}
}
