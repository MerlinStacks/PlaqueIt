<?php
/**
 * Uninstall cleanup for PlaqueIt.
 *
 * @package PlaqueIt
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-plaque-it-files.php';

global $wpdb;

Plaque_It_Files::remove_uploads();

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}plaque_it_fonts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}plaque_it_print_files" );

delete_option( 'plaque_it_db_version' );
delete_option( 'plaque_it_settings' );

$meta_keys = [
	'_plaque_it_enabled',
	'_plaque_it_min_width',
	'_plaque_it_max_width',
	'_plaque_it_min_height',
	'_plaque_it_max_height',
	'_plaque_it_max_lines',
	'_plaque_it_corner_styles',
	'_plaque_it_plaque_colour',
	'_plaque_it_engraving_colour',
];

foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

foreach ( [ '_plaque_it_config', '_plaque_it_base_price', '_plaque_it_print_file_id' ] as $item_meta_key ) {
	$wpdb->delete( $wpdb->prefix . 'woocommerce_order_itemmeta', [ 'meta_key' => $item_meta_key ], [ '%s' ] );
}
