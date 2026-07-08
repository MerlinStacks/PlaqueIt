<?php
/**
 * Database installer and helpers.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Database class. */
class Plaque_It_DB {

	public const DB_VERSION = '0.1.0';

	/** Maybe install/update tables. */
	public static function maybe_install(): void {
		if ( get_option( 'plaque_it_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/** Install tables. */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$fonts   = self::fonts_table();
		$files   = self::print_files_table();

		dbDelta(
			"CREATE TABLE {$fonts} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(190) NOT NULL,
				file_path TEXT NOT NULL,
				file_url TEXT NOT NULL,
				weight VARCHAR(30) NOT NULL DEFAULT '400',
				style VARCHAR(30) NOT NULL DEFAULT 'normal',
				width_factor DECIMAL(5,3) NOT NULL DEFAULT 0.560,
				min_size DECIMAL(8,2) NOT NULL DEFAULT 8.00,
				active TINYINT(1) NOT NULL DEFAULT 1,
				production_restricted TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$files} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				order_id BIGINT UNSIGNED NOT NULL,
				order_item_id BIGINT UNSIGNED NOT NULL,
				file_type VARCHAR(20) NOT NULL DEFAULT 'svg',
				file_path TEXT NOT NULL,
				file_url TEXT NOT NULL,
				file_status VARCHAR(30) NOT NULL DEFAULT 'files_ready',
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY order_item_id (order_item_id),
				KEY order_id (order_id)
			) {$charset};"
		);

		add_option( 'plaque_it_db_version', self::DB_VERSION );
		if ( ! get_option( 'plaque_it_settings' ) ) {
			add_option( 'plaque_it_settings', Plaque_It_Settings::defaults() );
		}
	}

	/** Fonts table name. */
	public static function fonts_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'plaque_it_fonts';
	}

	/** Print files table name. */
	public static function print_files_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'plaque_it_print_files';
	}
}
