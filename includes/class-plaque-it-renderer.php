<?php
/**
 * SVG render helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Renderer class. */
class Plaque_It_Renderer {

	/** Convert mm to px at 76 DPI. */
	public static function mm_to_px( float $mm ): float {
		return $mm / 25.4 * PLAQUE_IT_DPI;
	}

	/** Render SVG string from config. */
	public static function svg( array $config ): string {
		$width_mm  = max( 1, (float) ( $config['width'] ?? 1 ) );
		$height_mm = max( 1, (float) ( $config['height'] ?? 1 ) );
		$width     = self::mm_to_px( $width_mm );
		$height    = self::mm_to_px( $height_mm );
		$plaque    = sanitize_hex_color( (string) ( $config['plaque_colour'] ?? '#111111' ) ) ?: '#111111';
		$engrave   = sanitize_hex_color( (string) ( $config['engraving_colour'] ?? '#ffffff' ) ) ?: '#ffffff';
		$corner    = sanitize_key( (string) ( $config['corner_style'] ?? 'rounded' ) );
		$lines     = is_array( $config['lines'] ?? null ) ? $config['lines'] : [];

		$defs  = self::font_defs( $lines );
		$shape = self::shape_markup( $corner, $width, $height, $plaque );
		$text  = self::text_markup( $lines, $width, $height, $engrave );

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%.3f" height="%.3f" viewBox="0 0 %.3f %.3f" role="img" aria-label="Plaque preview">%s%s</svg>',
			$width,
			$height,
			$width,
			$height,
			$defs . $shape,
			$text
		);
	}

	/** Render preview image tag. */
	public static function preview_img( array $config, string $class = 'plaque-it-cart-preview' ): string {
		if ( ! empty( $config['preview_url'] ) ) {
			return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( (string) $config['preview_url'] ) . '" alt="' . esc_attr__( 'Plaque preview', 'plaque-it' ) . '" />';
		}

		$svg = self::svg( $config );
		return '<img class="' . esc_attr( $class ) . '" src="data:image/svg+xml;base64,' . esc_attr( base64_encode( $svg ) ) . '" alt="' . esc_attr__( 'Plaque preview', 'plaque-it' ) . '" />';
	}

	/** Save preview SVG and return URL/path. */
	public static function save_preview_file( array $config ): ?array {
		$upload_dir = Plaque_It_Files::ensure_upload_dir( 'previews' );
		if ( is_wp_error( $upload_dir ) ) {
			return null;
		}
		$dir = $upload_dir['dir'];
		$url = $upload_dir['url'];

		$file = 'preview-' . md5( wp_json_encode( $config ) . microtime() ) . '.svg';
		$path = trailingslashit( $dir ) . $file;
		if ( false === file_put_contents( $path, self::svg( $config ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return null;
		}

		return [
			'path' => $path,
			'url'  => trailingslashit( $url ) . $file,
		];
	}

	/** Save production SVG for order item. */
	public static function save_print_file( int $order_id, int $item_id, array $config ): ?int {
		$upload_dir = Plaque_It_Files::ensure_upload_dir( 'print-files' );
		if ( is_wp_error( $upload_dir ) ) {
			return null;
		}
		$dir = $upload_dir['dir'];
		$url = $upload_dir['url'];

		$file = 'plaque-' . $order_id . '-' . $item_id . '-' . wp_generate_password( 12, false, false ) . '.svg';
		$path = trailingslashit( $dir ) . $file;
		if ( false === file_put_contents( $path, self::svg( $config ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return null;
		}

		global $wpdb;
		$wpdb->insert(
			Plaque_It_DB::print_files_table(),
			[
				'order_id'      => $order_id,
				'order_item_id' => $item_id,
				'file_type'     => 'svg',
				'file_path'     => $path,
				'file_url'      => trailingslashit( $url ) . $file,
				'file_status'   => 'files_ready',
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/** Delete print files for a specific order item. */
	public static function delete_print_files_for_item( int $item_id ): void {
		global $wpdb;
		$table = Plaque_It_DB::print_files_table();
		$files = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_item_id = %d", $item_id ) );

		foreach ( $files ?: [] as $file ) {
			if ( ! empty( $file->file_path ) && self::is_safe_generated_file( (string) $file->file_path, 'print-files' ) ) {
				unlink( $file->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		$wpdb->delete( $table, [ 'order_item_id' => $item_id ], [ '%d' ] );
	}

	/** Delete a generated preview file from a config array. */
	public static function delete_preview_file( array $config ): void {
		$path = isset( $config['preview_path'] ) ? (string) $config['preview_path'] : '';
		if ( '' !== $path && self::is_safe_generated_file( $path, 'previews' ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/** Check generated file is inside a PlaqueIt upload subdirectory. */
	private static function is_safe_generated_file( string $path, string $subdir ): bool {
		$uploads = wp_upload_dir();
		$base    = realpath( trailingslashit( $uploads['basedir'] ) . 'plaque-it/' . sanitize_key( $subdir ) );
		$real    = realpath( $path );
		return $base && $real && str_starts_with( $real, $base ) && is_file( $real );
	}

	/** Shape SVG markup. */
	private static function shape_markup( string $corner, float $width, float $height, string $fill ): string {
		if ( 'rounded' === $corner ) {
			$r = min( $width, $height ) * 0.08;
			return sprintf( '<rect x="0" y="0" width="%.3f" height="%.3f" rx="%.3f" fill="%s"/>', $width, $height, $r, esc_attr( $fill ) );
		}

		if ( 'straight' === $corner ) {
			$cut = min( $width, $height ) * 0.10;
			$pts = "{$cut},0 " . ( $width - $cut ) . ",0 {$width},{$cut} {$width}," . ( $height - $cut ) . ' ' . ( $width - $cut ) . ",{$height} {$cut},{$height} 0," . ( $height - $cut ) . " 0,{$cut}";
			return '<polygon points="' . esc_attr( $pts ) . '" fill="' . esc_attr( $fill ) . '"/>';
		}

		if ( 'scallop' === $corner ) {
			$r = min( $width, $height ) * 0.10;
			$d = sprintf( 'M %.3f 0 H %.3f Q %.3f %.3f %.3f %.3f V %.3f Q %.3f %.3f %.3f %.3f H %.3f Q %.3f %.3f %.3f %.3f V %.3f Q %.3f %.3f %.3f 0 Z', $r, $width - $r, $width - $r, $r, $width, $r, $height - $r, $width - $r, $height - $r, $width - $r, $height, $r, $r, $height - $r, 0, $height - $r, $r, $r, $r, $r );
			return '<path d="' . esc_attr( $d ) . '" fill="' . esc_attr( $fill ) . '"/>';
		}

		return sprintf( '<rect x="0" y="0" width="%.3f" height="%.3f" fill="%s"/>', $width, $height, esc_attr( $fill ) );
	}

	/** Text SVG markup. */
	private static function text_markup( array $lines, float $width, float $height, string $fill ): string {
		if ( empty( $lines ) ) {
			return '';
		}

		$total = 0;
		foreach ( $lines as $line ) {
			$total += (float) ( $line['size'] ?? Plaque_It_Settings::get( 'min_font_size', 8 ) ) * 1.25;
		}

		$y     = ( $height - $total ) / 2;
		$out   = '';
		$fonts = [];
		foreach ( $lines as $line ) {
			$font_id = absint( $line['font_id'] ?? 0 );
			if ( $font_id && ! isset( $fonts[ $font_id ] ) ) {
				$fonts[ $font_id ] = Plaque_It_Fonts::get( $font_id );
			}
			$font = $fonts[ $font_id ] ?? null;
			$size = max( 1, (float) ( $line['size'] ?? 8 ) );
			$y   += $size;
			$out .= sprintf(
				'<text x="50%%" y="%.3f" text-anchor="middle" dominant-baseline="middle" fill="%s" font-family="%s" font-size="%.3f" font-weight="%s" font-style="%s">%s</text>',
				$y,
				esc_attr( $fill ),
				esc_attr( $font ? 'PlaqueItFont' . (int) $font->id : 'Arial, sans-serif' ),
				$size,
				esc_attr( $font ? $font->weight : '400' ),
				esc_attr( $font ? $font->style : 'normal' ),
				esc_html( (string) ( $line['text'] ?? '' ) )
			);
			$y += $size * 0.25;
		}

		return $out;
	}

	/** Build embedded font definitions for standalone SVG previews/print files. */
	private static function font_defs( array $lines ): string {
		$font_ids = [];
		foreach ( $lines as $line ) {
			$font_id = absint( $line['font_id'] ?? 0 );
			if ( $font_id ) {
				$font_ids[ $font_id ] = true;
			}
		}

		if ( empty( $font_ids ) ) {
			return '';
		}

		$css = '';
		foreach ( array_keys( $font_ids ) as $font_id ) {
			$font = Plaque_It_Fonts::get( (int) $font_id );
			if ( ! $font || empty( $font->file_path ) || ! is_readable( $font->file_path ) ) {
				continue;
			}
			$ext  = strtolower( pathinfo( (string) $font->file_path, PATHINFO_EXTENSION ) );
			$mime = match ( $ext ) {
				'otf' => 'font/otf',
				'woff' => 'font/woff',
				'woff2' => 'font/woff2',
				default => 'font/ttf',
			};
			$contents = file_get_contents( $font->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $contents ) {
				continue;
			}
			$data = base64_encode( $contents );
			$css .= sprintf(
				"@font-face{font-family:'PlaqueItFont%d';src:url(data:%s;base64,%s);font-weight:%s;font-style:%s;}",
				(int) $font->id,
				$mime,
				$data,
				esc_attr( (string) $font->weight ),
				esc_attr( (string) $font->style )
			);
		}

		return '' === $css ? '' : '<defs><style><![CDATA[' . $css . ']]></style></defs>';
	}
}
