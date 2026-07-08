<?php
/**
 * File-system helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** File helper class. */
class Plaque_It_Files {

	/** Ensure upload subdirectory exists and is protected. */
	public static function ensure_upload_dir( string $subdir ): array|WP_Error {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'plaque_it_upload_dir', (string) $uploads['error'] );
		}

		$subdir = trim( sanitize_key( $subdir ), '/' );
		$dir    = trailingslashit( $uploads['basedir'] ) . 'plaque-it/' . $subdir;
		$url    = trailingslashit( $uploads['baseurl'] ) . 'plaque-it/' . $subdir;
		wp_mkdir_p( $dir );

		self::write_protection_files( trailingslashit( $uploads['basedir'] ) . 'plaque-it' );
		self::write_protection_files( $dir );

		return [
			'dir' => $dir,
			'url' => $url,
		];
	}

	/** Recursively remove PlaqueIt upload files. */
	public static function remove_uploads(): void {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] ) . 'plaque-it';
		self::delete_dir( $base );
	}

	/** Write simple protection files. */
	private static function write_protection_files( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/** Delete directory recursively. */
	private static function delete_dir( string $dir ): void {
		$real = realpath( $dir );
		if ( ! $real || ! is_dir( $real ) ) {
			return;
		}

		$uploads = wp_upload_dir();
		$allowed = realpath( trailingslashit( $uploads['basedir'] ) . 'plaque-it' );
		if ( ! $allowed || ! str_starts_with( $real, $allowed ) ) {
			return;
		}

		$items = scandir( $real );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $real . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				self::delete_dir( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}

		rmdir( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
