<?php
/**
 * Main plugin loader.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Main plugin class. */
class Plaque_It {

	private static ?Plaque_It $instance = null;

	/** Get singleton instance. */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Constructor. */
	private function __construct() {
		$this->load_files();
		$this->init_hooks();
	}

	/** Load plugin classes. */
	private function load_files(): void {
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-db.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-files.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-settings.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-fonts.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-validator.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-renderer.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-pricing.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-frontend.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-cart.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-admin.php';
		require_once PLAQUE_IT_PATH . 'includes/class-plaque-it-admin-order-metabox.php';
	}

	/** Initialise hooks. */
	private function init_hooks(): void {
		Plaque_It_DB::maybe_install();
		add_filter( 'plugin_action_links_' . plugin_basename( PLAQUE_IT_PATH . 'plaque-it.php' ), [ $this, 'plugin_action_links' ] );
		( new Plaque_It_Frontend() )->register();
		( new Plaque_It_Cart() )->register();
		( new Plaque_It_Admin() )->register();
		( new Plaque_It_Admin_Order_Metabox() )->register();
	}

	/** Add plugin row action links. */
	public function plugin_action_links( array $links ): array {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=plaque-it' ) ) . '">' . esc_html__( 'Settings', 'plaque-it' ) . '</a>';
		$products = '<a href="' . esc_url( admin_url( 'admin.php?page=plaque-it-products' ) ) . '">' . esc_html__( 'Products', 'plaque-it' ) . '</a>';
		array_unshift( $links, $settings, $products );
		return $links;
	}
}
