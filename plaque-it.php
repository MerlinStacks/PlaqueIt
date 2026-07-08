<?php
/**
 * Plugin Name: PlaqueIt
 * Description: WooCommerce plaque customiser with live previews, size pricing, uploaded fonts, and order print files.
 * Version:     0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author:      SLDevs
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * Text Domain: plaque-it
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAQUE_IT_VERSION', '0.1.0' );
define( 'PLAQUE_IT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLAQUE_IT_URL', plugin_dir_url( __FILE__ ) );
define( 'PLAQUE_IT_DPI', 76 );

add_action(
	'before_woocommerce_init',
	function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook(
	__FILE__,
	function (): void {
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-settings.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-db.php';
		Plaque_It_DB::install();
	}
);

add_action(
	'plugins_loaded',
	function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function (): void {
					echo '<div class="notice notice-warning"><p>' . esc_html__( 'PlaqueIt requires WooCommerce to be active.', 'plaque-it' ) . '</p></div>';
				}
			);
			return;
		}

		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it.php';
		Plaque_It::instance();
	}
);
