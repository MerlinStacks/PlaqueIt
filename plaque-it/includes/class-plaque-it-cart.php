<?php
/**
 * WooCommerce cart/order integration.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Cart integration. */
class Plaque_It_Cart {

	/** Register hooks. */
	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 4 );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_price' ], 20 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_item_data' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'cart_thumbnail' ], 10, 3 );
		add_filter( 'woocommerce_cart_item_name', [ $this, 'checkout_name_preview' ], 10, 3 );
		add_filter( 'woocommerce_store_api_cart_item_images', [ $this, 'store_api_images' ], 10, 3 );
		add_action( 'woocommerce_remove_cart_item', [ $this, 'delete_removed_cart_preview' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item' ], 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'generate_order_print_files' ] );
		add_action( 'woocommerce_order_item_meta_end', [ $this, 'display_order_preview' ], 10, 3 );
		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'display_admin_order_preview' ], 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_meta' ] );
	}

	/** Validate add-to-cart. */
	public function validate_add_to_cart( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		unset( $quantity );
		if ( ! Plaque_It_Validator::is_enabled_product( $product_id ) ) {
			return $passed;
		}

		$config = Plaque_It_Validator::decode_posted();
		if ( ! $config ) {
			wc_add_notice( __( 'Please complete the plaque customisation.', 'plaque-it' ), 'error' );
			return false;
		}

		$result = Plaque_It_Validator::sanitise( $config, $product_id, $variation_id );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/** Add cart item data. */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		if ( ! Plaque_It_Validator::is_enabled_product( $product_id ) ) {
			return $cart_item_data;
		}

		$config = Plaque_It_Validator::decode_posted();
		if ( ! $config ) {
			return $cart_item_data;
		}

		$result = Plaque_It_Validator::sanitise( $config, $product_id, $variation_id );
		if ( is_wp_error( $result ) ) {
			return $cart_item_data;
		}

		$preview = Plaque_It_Renderer::save_preview_file( $result );
		if ( $preview ) {
			$result['preview_url']  = $preview['url'];
			$result['preview_path'] = $preview['path'];
		}

		$product = wc_get_product( $variation_id ?: $product_id );
		$base    = $product ? (float) $product->get_price( 'edit' ) : 0;

		$cart_item_data['_plaque_it_config']     = $result;
		$cart_item_data['_plaque_it_base_price'] = $base;
		$cart_item_data['_plaque_it_unique_key'] = md5( wp_json_encode( $result ) . microtime() );
		return $cart_item_data;
	}

	/** Apply size price. */
	public function apply_price( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['_plaque_it_config'] ) || empty( $cart_item['data'] ) ) {
				continue;
			}
			$config = $cart_item['_plaque_it_config'];
			$base   = isset( $cart_item['_plaque_it_base_price'] ) ? (float) $cart_item['_plaque_it_base_price'] : (float) $cart_item['data']->get_price( 'edit' );
			$cart_item['data']->set_price( Plaque_It_Pricing::final_price( $base, (float) $config['width'], (float) $config['height'] ) );
		}
	}

	/** Display cart/checkout item data. */
	public function display_item_data( array $item_data, array $cart_item ): array {
		$config = $cart_item['_plaque_it_config'] ?? null;
		if ( ! is_array( $config ) ) {
			return $item_data;
		}

		$item_data[] = [ 'name' => __( 'Plaque size', 'plaque-it' ), 'value' => esc_html( $config['width'] . 'mm x ' . $config['height'] . 'mm' ) ];
		$item_data[] = [ 'name' => __( 'Corners', 'plaque-it' ), 'value' => esc_html( ucwords( str_replace( '_', ' ', $config['corner_style'] ) ) ) ];
		$item_data[] = [ 'name' => __( 'Message', 'plaque-it' ), 'value' => esc_html( implode( ' / ', wp_list_pluck( $config['lines'], 'text' ) ) ) ];
		$item_data[] = [ 'name' => __( 'Size surcharge', 'plaque-it' ), 'value' => wc_price( (float) ( $config['pricing']['surcharge'] ?? 0 ) ) ];
		return $item_data;
	}

	/** Replace cart thumbnail. */
	public function cart_thumbnail( string $thumbnail, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );
		return is_array( $cart_item['_plaque_it_config'] ?? null ) ? Plaque_It_Renderer::preview_img( $cart_item['_plaque_it_config'] ) : $thumbnail;
	}

	/** Add preview in checkout name area if theme has no thumbnail column. */
	public function checkout_name_preview( string $name, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );
		if ( ! is_checkout() || is_wc_endpoint_url() || ! is_array( $cart_item['_plaque_it_config'] ?? null ) ) {
			return $name;
		}
		return '<span class="plaque-it-checkout-preview">' . Plaque_It_Renderer::preview_img( $cart_item['_plaque_it_config'] ) . '</span>' . $name;
	}

	/** Store API cart image support. */
	public function store_api_images( array $images, array $cart_item, string $cart_item_key ): array {
		unset( $cart_item_key );
		if ( ! is_array( $cart_item['_plaque_it_config'] ?? null ) ) {
			return $images;
		}
		$config = $cart_item['_plaque_it_config'];
		$src    = ! empty( $config['preview_url'] ) ? (string) $config['preview_url'] : 'data:image/svg+xml;base64,' . base64_encode( Plaque_It_Renderer::svg( $config ) );
		return [ [ 'id' => 0, 'src' => $src, 'thumbnail' => $src, 'srcset' => '', 'sizes' => '', 'name' => __( 'Plaque preview', 'plaque-it' ), 'alt' => __( 'Plaque preview', 'plaque-it' ) ] ];
	}

	/** Save data to order item. */
	public function save_order_item( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $order );
		$config = $values['_plaque_it_config'] ?? null;
		if ( ! is_array( $config ) ) {
			return;
		}
		$item->add_meta_data( '_plaque_it_config', $config, true );
		$item->add_meta_data( '_plaque_it_base_price', (float) ( $values['_plaque_it_base_price'] ?? 0 ), true );
		$item->add_meta_data( __( 'Plaque size', 'plaque-it' ), $config['width'] . 'mm x ' . $config['height'] . 'mm', true );
		$item->add_meta_data( __( 'Plaque message', 'plaque-it' ), implode( ' / ', wp_list_pluck( $config['lines'], 'text' ) ), true );
	}

	/** Generate print files for order items. */
	public function generate_order_print_files( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$config = $item->get_meta( '_plaque_it_config', true );
			if ( ! is_array( $config ) || $item->get_meta( '_plaque_it_print_file_id', true ) ) {
				continue;
			}
			$file_id = Plaque_It_Renderer::save_print_file( $order_id, (int) $item_id, $config );
			if ( $file_id ) {
				$item->update_meta_data( '_plaque_it_print_file_id', $file_id );
				$item->save();
			}
		}
	}

	/** Display frontend order preview. */
	public function display_order_preview( int $item_id, WC_Order_Item $item, WC_Order $order ): void {
		unset( $item_id, $order );
		$config = $item->get_meta( '_plaque_it_config', true );
		if ( is_array( $config ) ) {
			echo '<p>' . Plaque_It_Renderer::preview_img( $config ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Display admin order preview. */
	public function display_admin_order_preview( int $item_id, WC_Order_Item $item, WC_Product|false $product ): void {
		unset( $item_id, $product );
		$config = $item->get_meta( '_plaque_it_config', true );
		if ( is_array( $config ) ) {
			echo '<p>' . Plaque_It_Renderer::preview_img( $config, 'plaque-it-order-preview' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Hide internal meta. */
	public function hidden_meta( array $hidden ): array {
		$hidden[] = '_plaque_it_config';
		$hidden[] = '_plaque_it_base_price';
		$hidden[] = '_plaque_it_print_file_id';
		return $hidden;
	}

	/** Delete preview file when a plaque cart item is removed. */
	public function delete_removed_cart_preview( string $cart_item_key, WC_Cart $cart ): void {
		$item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
		if ( is_array( $item['_plaque_it_config'] ?? null ) ) {
			Plaque_It_Renderer::delete_preview_file( $item['_plaque_it_config'] );
		}
	}
}
