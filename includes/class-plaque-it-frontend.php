<?php
/**
 * Frontend configurator.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Frontend class. */
class Plaque_It_Frontend {

	/** Register hooks. */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'wp', [ $this, 'replace_product_gallery' ] );
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_controls' ], 20 );
	}

	/** Replace the product gallery with the plaque preview on enabled products. */
	public function replace_product_gallery(): void {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();
		if ( ! $product_id || ! Plaque_It_Validator::is_enabled_product( $product_id ) ) {
			return;
		}

		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'render_gallery_preview' ], 20 );
	}

	/** Enqueue assets. */
	public function assets(): void {
		if ( ! is_product() ) {
			return;
		}

		$product_id = get_the_ID();
		if ( ! $product_id || ! Plaque_It_Validator::is_enabled_product( $product_id ) ) {
			return;
		}

		wp_enqueue_style( 'plaque-it-frontend', PLAQUE_IT_URL . 'assets/css/plaque-it-frontend.css', [], PLAQUE_IT_VERSION );
		wp_enqueue_script( 'plaque-it-frontend', PLAQUE_IT_URL . 'assets/js/plaque-it-frontend.js', [], PLAQUE_IT_VERSION, true );
		wp_localize_script( 'plaque-it-frontend', 'plaqueItData', $this->script_data( $product_id ) );
	}

	/** Render live preview in place of the product gallery. */
	public function render_gallery_preview(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! Plaque_It_Validator::is_enabled_product( $product->get_id() ) ) {
			return;
		}

		$config = $this->default_config( $product->get_id() );
		?>
		<div class="woocommerce-product-gallery plaque-it-gallery-preview" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
			<div class="plaque-it-preview-wrap"><div class="plaque-it-preview" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"><?php echo Plaque_It_Renderer::svg( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
		</div>
		<?php
	}

	/** Render customer inputs above the add-to-cart button. */
	public function render_controls(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! Plaque_It_Validator::is_enabled_product( $product->get_id() ) ) {
			return;
		}

		$settings = Plaque_It_Settings::all();
		$fonts    = Plaque_It_Fonts::active();
		if ( empty( $fonts ) ) {
			echo '<p class="plaque-it-warning">' . esc_html__( 'Plaque customisation is unavailable until fonts are uploaded.', 'plaque-it' ) . '</p>';
			return;
		}

		$product_id = $product->get_id();
		$min_w      = get_post_meta( $product_id, '_plaque_it_min_width', true ) ?: $settings['min_width'];
		$max_w      = get_post_meta( $product_id, '_plaque_it_max_width', true ) ?: $settings['max_width'];
		$min_h      = get_post_meta( $product_id, '_plaque_it_min_height', true ) ?: $settings['min_height'];
		$max_h      = get_post_meta( $product_id, '_plaque_it_max_height', true ) ?: $settings['max_height'];
		$corners    = get_post_meta( $product_id, '_plaque_it_corner_styles', true ) ?: $settings['corner_styles'];
		?>
		<div class="plaque-it-configurator" data-product-id="<?php echo esc_attr( $product_id ); ?>">
			<h3><?php esc_html_e( 'Design Your Plaque', 'plaque-it' ); ?></h3>
			<p class="plaque-it-errors" role="alert" aria-live="polite"></p>
			<div class="plaque-it-controls">
				<div class="plaque-it-row">
					<label><?php esc_html_e( 'Width (mm)', 'plaque-it' ); ?><input type="number" class="plaque-it-width" min="<?php echo esc_attr( $min_w ); ?>" max="<?php echo esc_attr( $max_w ); ?>" step="1" value="<?php echo esc_attr( $min_w ); ?>" /></label>
					<label><?php esc_html_e( 'Height (mm)', 'plaque-it' ); ?><input type="number" class="plaque-it-height" min="<?php echo esc_attr( $min_h ); ?>" max="<?php echo esc_attr( $max_h ); ?>" step="1" value="<?php echo esc_attr( $min_h ); ?>" /></label>
				</div>
				<label><?php esc_html_e( 'Corners', 'plaque-it' ); ?><select class="plaque-it-corner">
					<?php foreach ( (array) $corners as $corner ) : ?>
						<option value="<?php echo esc_attr( $corner ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $corner ) ) ); ?></option>
					<?php endforeach; ?>
				</select></label>
				<label><?php esc_html_e( 'Message', 'plaque-it' ); ?><textarea class="plaque-it-message" rows="4" placeholder="<?php esc_attr_e( 'Type each plaque line on a new line', 'plaque-it' ); ?>"></textarea></label>
				<div class="plaque-it-lines"></div>
				<p><label><input type="checkbox" class="plaque-it-approval" /> <?php esc_html_e( 'I approve the plaque preview.', 'plaque-it' ); ?></label></p>
				<p class="plaque-it-price"></p>
			</div>
			<input type="hidden" name="_plaque_it_config" class="plaque-it-config-input" value="" />
		</div>
		<?php
	}

	/** Script data for product. */
	private function script_data( int $product_id ): array {
		$product  = wc_get_product( $product_id );
		$settings = Plaque_It_Settings::all();
		$settings['min_width']  = (float) ( get_post_meta( $product_id, '_plaque_it_min_width', true ) ?: $settings['min_width'] );
		$settings['max_width']  = (float) ( get_post_meta( $product_id, '_plaque_it_max_width', true ) ?: $settings['max_width'] );
		$settings['min_height'] = (float) ( get_post_meta( $product_id, '_plaque_it_min_height', true ) ?: $settings['min_height'] );
		$settings['max_height'] = (float) ( get_post_meta( $product_id, '_plaque_it_max_height', true ) ?: $settings['max_height'] );
		$settings['max_lines']  = (int) ( get_post_meta( $product_id, '_plaque_it_max_lines', true ) ?: $settings['max_lines'] );
		$colors   = [
			0 => [
				'plaque'    => get_post_meta( $product_id, '_plaque_it_plaque_colour', true ) ?: '#111111',
				'engraving' => get_post_meta( $product_id, '_plaque_it_engraving_colour', true ) ?: '#ffffff',
			],
		];

		if ( $product instanceof WC_Product_Variable ) {
			foreach ( $product->get_children() as $variation_id ) {
				$colors[ $variation_id ] = [
					'plaque'    => get_post_meta( $variation_id, '_plaque_it_plaque_colour', true ) ?: '#111111',
					'engraving' => get_post_meta( $variation_id, '_plaque_it_engraving_colour', true ) ?: '#ffffff',
				];
			}
		}

		return [
			'dpi'           => PLAQUE_IT_DPI,
			'settings'      => $settings,
			'fonts'         => Plaque_It_Fonts::payload(),
			'variationData' => $colors,
			'basePrice'     => $product ? (float) $product->get_price() : 0,
			'currency'      => html_entity_decode( get_woocommerce_currency_symbol() ),
		];
	}

	/** Build the default preview config shown before customer input. */
	private function default_config( int $product_id ): array {
		$settings = Plaque_It_Settings::all();
		$corners  = (array) ( get_post_meta( $product_id, '_plaque_it_corner_styles', true ) ?: $settings['corner_styles'] );

		return [
			'width'            => (float) ( get_post_meta( $product_id, '_plaque_it_min_width', true ) ?: $settings['min_width'] ),
			'height'           => (float) ( get_post_meta( $product_id, '_plaque_it_min_height', true ) ?: $settings['min_height'] ),
			'corner_style'     => (string) ( $corners[0] ?? 'rounded' ),
			'plaque_colour'    => get_post_meta( $product_id, '_plaque_it_plaque_colour', true ) ?: '#111111',
			'engraving_colour' => get_post_meta( $product_id, '_plaque_it_engraving_colour', true ) ?: '#ffffff',
			'lines'            => [],
		];
	}
}
