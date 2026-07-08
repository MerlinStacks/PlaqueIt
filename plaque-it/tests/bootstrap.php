<?php
/**
 * Minimal test bootstrap for helper-level tests.
 *
 * @package PlaqueIt
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'PLAQUE_IT_DPI', 76 );
define( 'PLAQUE_IT_PATH', dirname( __DIR__ ) . '/' );
define( 'PLAQUE_IT_URL', 'https://example.com/wp-content/plugins/plaque-it/' );

$GLOBALS['plaque_it_test_options'] = [];

function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['plaque_it_test_options'][ $key ] ?? $default;
}

function update_option( string $key, mixed $value ): bool {
	$GLOBALS['plaque_it_test_options'][ $key ] = $value;
	return true;
}

function wp_parse_args( mixed $args, array $defaults = [] ): array {
	return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function wc_get_price_decimals(): int {
	return 2;
}

require_once dirname( __DIR__ ) . '/includes/class-plaque-it-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-plaque-it-pricing.php';
require_once dirname( __DIR__ ) . '/includes/class-plaque-it-renderer.php';
