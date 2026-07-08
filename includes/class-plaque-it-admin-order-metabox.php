<?php
/**
 * Admin order metabox for print files.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Order metabox class. */
class Plaque_It_Admin_Order_Metabox {

	/** Register hooks. */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_init', [ $this, 'handle_download' ] );
		add_action( 'admin_init', [ $this, 'handle_regenerate' ] );
	}

	/** Add metabox. */
	public function add_meta_box(): void {
		$screens    = [ 'shop_order' ];
		$hpos_class = '\\Automattic\\WooCommerce\\Internal\\Admin\\Orders\\PageController';
		if ( function_exists( 'wc_get_container' ) && class_exists( $hpos_class ) ) {
			try {
				$screen = wc_get_container()->get( $hpos_class )->get_edit_screen_id();
				if ( $screen ) {
					$screens[] = $screen;
				}
			} catch ( Throwable $e ) {
				unset( $e );
			}
		}

		foreach ( $screens as $screen ) {
			add_meta_box( 'plaque-it-print-files', __( 'PlaqueIt Print Files', 'plaque-it' ), [ $this, 'render' ], $screen, 'normal', 'high' );
		}
	}

	/** Render metabox. */
	public function render( mixed $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Could not load order.', 'plaque-it' ) . '</p>';
			return;
		}

		$found = false;
		foreach ( $order->get_items() as $item_id => $item ) {
			$config = $item->get_meta( '_plaque_it_config', true );
			if ( ! is_array( $config ) ) {
				continue;
			}
			$found = true;
			$file  = $this->file_for_item( (int) $item_id );
			if ( ! $file ) {
				$file_id = Plaque_It_Renderer::save_print_file( $order->get_id(), (int) $item_id, $config );
				if ( $file_id ) {
					$item->update_meta_data( '_plaque_it_print_file_id', $file_id );
					$item->save();
					$file = $this->file_for_item( (int) $item_id );
				}
			}

			echo '<div class="plaque-it-print-file">';
			echo '<h4>' . esc_html( $item->get_name() ) . '</h4>';
			echo Plaque_It_Renderer::preview_img( $config, 'plaque-it-order-preview' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $file ) {
				$url = wp_nonce_url( admin_url( 'admin.php?plaque_it_download_file=' . (int) $file->id ), 'plaque_it_download_' . (int) $file->id );
				$regen_url = wp_nonce_url( admin_url( 'admin.php?plaque_it_regenerate_item=' . (int) $item_id . '&order_id=' . (int) $order->get_id() ), 'plaque_it_regenerate_' . (int) $item_id );
				echo '<p><strong>' . esc_html__( 'SVG print file:', 'plaque-it' ) . '</strong> <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Download', 'plaque-it' ) . '</a></p>';
				echo '<p><a class="button button-secondary" href="' . esc_url( $regen_url ) . '">' . esc_html__( 'Regenerate Print File', 'plaque-it' ) . '</a></p>';
			} else {
				$regen_url = wp_nonce_url( admin_url( 'admin.php?plaque_it_regenerate_item=' . (int) $item_id . '&order_id=' . (int) $order->get_id() ), 'plaque_it_regenerate_' . (int) $item_id );
				echo '<p>' . esc_html__( 'Print file could not be generated.', 'plaque-it' ) . ' <a class="button button-secondary" href="' . esc_url( $regen_url ) . '">' . esc_html__( 'Try Again', 'plaque-it' ) . '</a></p>';
			}
			echo '</div>';
		}

		if ( ! $found ) {
			echo '<p>' . esc_html__( 'No PlaqueIt items in this order.', 'plaque-it' ) . '</p>';
		}
	}

	/** Handle file download. */
	public function handle_download(): void {
		if ( empty( $_GET['plaque_it_download_file'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'plaque-it' ) );
		}

		$id = absint( $_GET['plaque_it_download_file'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'plaque_it_download_' . $id ) ) {
			wp_die( esc_html__( 'Invalid download link.', 'plaque-it' ) );
		}

		global $wpdb;
		$file = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Plaque_It_DB::print_files_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $file || ! $this->is_safe_file( (string) $file->file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'plaque-it' ) );
		}

		header( 'Content-Type: image/svg+xml' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file->file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file->file_path ) );
		readfile( $file->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Handle print file regeneration. */
	public function handle_regenerate(): void {
		if ( empty( $_GET['plaque_it_regenerate_item'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to regenerate this file.', 'plaque-it' ) );
		}

		$item_id  = absint( $_GET['plaque_it_regenerate_item'] );
		$order_id = absint( $_GET['order_id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'plaque_it_regenerate_' . $item_id ) ) {
			wp_die( esc_html__( 'Invalid regeneration link.', 'plaque-it' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order could not be loaded.', 'plaque-it' ) );
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item ) {
			wp_die( esc_html__( 'Order item could not be loaded.', 'plaque-it' ) );
		}

		$config = $item->get_meta( '_plaque_it_config', true );
		if ( ! is_array( $config ) ) {
			wp_die( esc_html__( 'This order item has no PlaqueIt configuration.', 'plaque-it' ) );
		}

		Plaque_It_Renderer::delete_print_files_for_item( $item_id );
		$file_id = Plaque_It_Renderer::save_print_file( $order_id, $item_id, $config );
		if ( $file_id ) {
			$item->update_meta_data( '_plaque_it_print_file_id', $file_id );
			$item->save();
		}

		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	/** Get file for item. */
	private function file_for_item( int $item_id ): ?object {
		global $wpdb;
		$table = Plaque_It_DB::print_files_table();
		$file  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_item_id = %d ORDER BY id DESC LIMIT 1", $item_id ) );
		return $file ?: null;
	}

	/** Ensure file is inside PlaqueIt print directory. */
	private function is_safe_file( string $path ): bool {
		$uploads = wp_upload_dir();
		$base    = realpath( trailingslashit( $uploads['basedir'] ) . 'plaque-it/print-files' );
		$real    = realpath( $path );
		return $base && $real && str_starts_with( $real, $base ) && is_file( $real );
	}
}
