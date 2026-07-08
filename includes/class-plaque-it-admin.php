<?php
/**
 * Admin screens.
 *
 * @package PlaqueIt
 */

defined( 'ABSPATH' ) || exit;

/** Admin class. */
class Plaque_It_Admin {

	/** Register hooks. */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'handle_posts' ] );
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panel' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_data_panel' ] );
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_fields' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_fields' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'wp_ajax_plaque_it_search_products', [ $this, 'ajax_search_products' ] );
	}

	/** Admin assets. */
	public function assets(): void {
		$css_file = PLAQUE_IT_PATH . 'assets/css/plaque-it-admin.css';
		$js_file  = PLAQUE_IT_PATH . 'assets/js/plaque-it-admin.js';
		$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : PLAQUE_IT_VERSION;
		$js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : PLAQUE_IT_VERSION;

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'plaque-it-admin', PLAQUE_IT_URL . 'assets/css/plaque-it-admin.css', [], $css_ver );
		wp_enqueue_script( 'plaque-it-admin', PLAQUE_IT_URL . 'assets/js/plaque-it-admin.js', [ 'jquery', 'wp-color-picker' ], $js_ver, true );
		wp_localize_script(
			'plaque-it-admin',
			'plaqueItAdmin',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'productsUrl'   => admin_url( 'admin.php?page=plaque-it-products' ),
				'productNonce'  => wp_create_nonce( 'plaque_it_search_products' ),
				'searching'     => __( 'Searching products...', 'plaque-it' ),
				'noResults'     => __( 'No products found. Try a different name, SKU, or product ID.', 'plaque-it' ),
				'searchPrompt'  => __( 'Start typing to search by product name, SKU, or ID.', 'plaque-it' ),
				'searchMinimum' => __( 'Type at least 2 characters to search.', 'plaque-it' ),
			]
		);
	}

	/** Register menu. */
	public function menu(): void {
		add_menu_page( __( 'PlaqueIt', 'plaque-it' ), __( 'PlaqueIt', 'plaque-it' ), 'manage_woocommerce', 'plaque-it', [ $this, 'render_settings' ], 'dashicons-format-image', 56 );
		add_submenu_page( 'plaque-it', __( 'Settings', 'plaque-it' ), __( 'Settings', 'plaque-it' ), 'manage_woocommerce', 'plaque-it', [ $this, 'render_settings' ] );
		add_submenu_page( 'plaque-it', __( 'Products', 'plaque-it' ), __( 'Products', 'plaque-it' ), 'manage_woocommerce', 'plaque-it-products', [ $this, 'render_products' ] );
		add_submenu_page( 'plaque-it', __( 'Fonts', 'plaque-it' ), __( 'Fonts', 'plaque-it' ), 'manage_woocommerce', 'plaque-it-fonts', [ $this, 'render_fonts' ] );
	}

	/** Handle admin form posts. */
	public function handle_posts(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['plaque_it_save_settings'] ) && check_admin_referer( 'plaque_it_save_settings' ) ) {
			Plaque_It_Settings::save( $_POST );
			$this->redirect_notice( 'settings-saved' );
		}

		if ( isset( $_POST['plaque_it_save_product'] ) && check_admin_referer( 'plaque_it_save_product' ) ) {
			$notice    = $this->save_product();
			$product_id = absint( $_POST['product_id'] ?? 0 );
			$this->redirect_notice( $notice, 'plaque-it-products', $product_id ? [ 'product_id' => $product_id ] : [] );
		}

		if ( isset( $_POST['plaque_it_upload_font'] ) && check_admin_referer( 'plaque_it_upload_font' ) ) {
			$error = Plaque_It_Fonts::upload( $_FILES['font_file'] ?? [], $_POST );
			$this->redirect_notice( $error ? rawurlencode( $error ) : 'font-uploaded', 'plaque-it-fonts' );
		}

		if ( isset( $_GET['page'], $_GET['plaque_it_toggle_font'], $_GET['_wpnonce'] ) && 'plaque-it-fonts' === $_GET['page'] ) {
			$id = absint( $_GET['plaque_it_toggle_font'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'plaque_it_toggle_font_' . $id ) ) {
				$this->toggle_font( $id );
				$this->redirect_notice( 'font-updated', 'plaque-it-fonts' );
			}
		}

		if ( isset( $_GET['page'], $_GET['plaque_it_delete_font'], $_GET['_wpnonce'] ) && 'plaque-it-fonts' === $_GET['page'] ) {
			$id = absint( $_GET['plaque_it_delete_font'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'plaque_it_delete_font_' . $id ) ) {
				Plaque_It_Fonts::delete( $id );
				$this->redirect_notice( 'font-deleted', 'plaque-it-fonts' );
			}
		}

		if ( isset( $_POST['plaque_it_update_font'] ) && check_admin_referer( 'plaque_it_update_font' ) ) {
			$this->update_font();
			$this->redirect_notice( 'font-updated', 'plaque-it-fonts' );
		}
	}

	/** Render settings page. */
	public function render_settings(): void {
		$settings = Plaque_It_Settings::all();
		$this->notice();
		?>
		<div class="wrap plaque-it-admin">
			<h1><?php esc_html_e( 'PlaqueIt Settings', 'plaque-it' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'plaque_it_save_settings' ); ?>
				<div class="plaque-it-grid">
					<?php $this->number_field( 'min_width', __( 'Minimum width (mm)', 'plaque-it' ), $settings['min_width'] ); ?>
					<?php $this->number_field( 'max_width', __( 'Maximum width (mm)', 'plaque-it' ), $settings['max_width'] ); ?>
					<?php $this->number_field( 'min_height', __( 'Minimum height (mm)', 'plaque-it' ), $settings['min_height'] ); ?>
					<?php $this->number_field( 'max_height', __( 'Maximum height (mm)', 'plaque-it' ), $settings['max_height'] ); ?>
					<?php $this->number_field( 'min_font_size', __( 'Minimum readable font size', 'plaque-it' ), $settings['min_font_size'] ); ?>
					<?php $this->number_field( 'max_lines', __( 'Absolute max lines', 'plaque-it' ), $settings['max_lines'], 1 ); ?>
					<?php $this->number_field( 'safe_width', __( 'Safe width (%)', 'plaque-it' ), $settings['safe_width'] ); ?>
					<?php $this->number_field( 'safe_height', __( 'Safe height (%)', 'plaque-it' ), $settings['safe_height'] ); ?>
					<?php $this->number_field( 'area_rate', __( 'Area price rate per mm2', 'plaque-it' ), $settings['area_rate'], '0.0001' ); ?>
				</div>
				<h2><?php esc_html_e( 'Corner Styles', 'plaque-it' ); ?></h2>
				<?php foreach ( [ 'scallop', 'straight', 'none', 'rounded' ] as $style ) : ?>
					<label><input type="checkbox" name="corner_styles[]" value="<?php echo esc_attr( $style ); ?>" <?php checked( in_array( $style, (array) $settings['corner_styles'], true ) ); ?> /> <?php echo esc_html( ucwords( str_replace( '_', ' ', $style ) ) ); ?></label><br />
				<?php endforeach; ?>
				<p><label><input type="checkbox" name="require_approval" value="1" <?php checked( ! empty( $settings['require_approval'] ) ); ?> /> <?php esc_html_e( 'Require customer preview approval', 'plaque-it' ); ?></label></p>
				<p><label><?php esc_html_e( 'Uploaded font production restrictions', 'plaque-it' ); ?><br /><textarea name="font_restrictions" rows="4" class="large-text"><?php echo esc_textarea( $settings['font_restrictions'] ); ?></textarea></label></p>
				<?php submit_button( __( 'Save Settings', 'plaque-it' ), 'primary', 'plaque_it_save_settings' ); ?>
			</form>
		</div>
		<?php
	}

	/** Render products page. */
	public function render_products(): void {
		$this->notice();
		$manual_id  = absint( $_GET['manual_product_id'] ?? 0 );
		$product_id = $manual_id ?: absint( $_GET['product_id'] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$settings   = Plaque_It_Settings::all();
		$conflict   = $product ? Plaque_It_Validator::has_personaliseit( $product_id ) : false;
		$types      = wc_get_product_types();
		?>
		<div class="wrap plaque-it-admin plaque-it-products-page">
			<div class="plaque-it-page-header">
				<div>
					<h1><?php esc_html_e( 'Product Assignments', 'plaque-it' ); ?></h1>
					<p><?php esc_html_e( 'Search your WooCommerce catalogue and configure PlaqueIt on plaque products only.', 'plaque-it' ); ?></p>
				</div>
			</div>

			<div class="plaque-it-products-layout">
				<div class="plaque-it-card plaque-it-product-search-card">
					<div class="plaque-it-card-header">
						<h2><?php esc_html_e( 'Find a product', 'plaque-it' ); ?></h2>
						<span><?php esc_html_e( 'Live catalogue search', 'plaque-it' ); ?></span>
					</div>
					<div class="plaque-it-card-body">
						<label class="plaque-it-search-label" for="plaque-it-product-search"><?php esc_html_e( 'Search by product name, SKU, or ID', 'plaque-it' ); ?></label>
						<input id="plaque-it-product-search" class="plaque-it-live-product-search" type="search" autocomplete="off" placeholder="<?php esc_attr_e( 'Start typing a product name...', 'plaque-it' ); ?>" />
						<div class="plaque-it-live-results" aria-live="polite">
							<div class="plaque-it-search-placeholder"><?php esc_html_e( 'Start typing to search by product name, SKU, or ID.', 'plaque-it' ); ?></div>
						</div>
						<form method="get" class="plaque-it-manual-load">
							<input type="hidden" name="page" value="plaque-it-products" />
							<label><?php esc_html_e( 'Know the Product ID?', 'plaque-it' ); ?> <input type="number" name="manual_product_id" value="" placeholder="<?php esc_attr_e( 'Product ID', 'plaque-it' ); ?>" /></label>
							<?php submit_button( __( 'Load', 'plaque-it' ), 'secondary', '', false ); ?>
						</form>
					</div>
				</div>

				<div class="plaque-it-product-editor">
			<?php if ( $product ) : ?>
				<form method="post" class="plaque-it-card plaque-it-product-settings-card">
					<?php wp_nonce_field( 'plaque_it_save_product' ); ?>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
					<div class="plaque-it-card-header plaque-it-product-editor-header">
						<div>
							<h2><?php echo esc_html( $product->get_name() ); ?></h2>
							<span><?php echo esc_html( '#' . $product_id . ' - ' . ( $types[ $product->get_type() ] ?? $product->get_type() ) ); ?></span>
						</div>
						<?php if ( $conflict ) : ?>
							<span class="plaque-it-status-badge plaque-it-status-conflict"><?php esc_html_e( 'PersonaliseIt conflict', 'plaque-it' ); ?></span>
						<?php elseif ( Plaque_It_Validator::is_enabled_product( $product_id ) ) : ?>
							<span class="plaque-it-status-badge plaque-it-status-enabled"><?php esc_html_e( 'Enabled', 'plaque-it' ); ?></span>
						<?php else : ?>
							<span class="plaque-it-status-badge plaque-it-status-disabled"><?php esc_html_e( 'Disabled', 'plaque-it' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="plaque-it-card-body">
					<?php if ( $conflict ) : ?>
						<p class="plaque-it-conflict"><?php esc_html_e( 'PersonaliseIt is already configured for this product. PlaqueIt cannot be enabled on the same product.', 'plaque-it' ); ?></p>
					<?php endif; ?>
					<p><label class="plaque-it-toggle-row"><input type="checkbox" name="enabled" value="yes" <?php checked( Plaque_It_Validator::is_enabled_product( $product_id ) ); ?> <?php disabled( $conflict ); ?> /> <?php esc_html_e( 'Enable PlaqueIt on this product', 'plaque-it' ); ?></label></p>
					<div class="plaque-it-grid">
						<?php $this->product_number_field( $product_id, 'min_width', __( 'Minimum width (mm)', 'plaque-it' ), $settings['min_width'] ); ?>
						<?php $this->product_number_field( $product_id, 'max_width', __( 'Maximum width (mm)', 'plaque-it' ), $settings['max_width'] ); ?>
						<?php $this->product_number_field( $product_id, 'min_height', __( 'Minimum height (mm)', 'plaque-it' ), $settings['min_height'] ); ?>
						<?php $this->product_number_field( $product_id, 'max_height', __( 'Maximum height (mm)', 'plaque-it' ), $settings['max_height'] ); ?>
						<?php $this->product_number_field( $product_id, 'max_lines', __( 'Max lines', 'plaque-it' ), $settings['max_lines'], 1 ); ?>
						<label><?php esc_html_e( 'Fallback plaque colour', 'plaque-it' ); ?><input type="text" name="plaque_colour" value="<?php echo esc_attr( get_post_meta( $product_id, '_plaque_it_plaque_colour', true ) ?: '#111111' ); ?>" /></label>
						<label><?php esc_html_e( 'Fallback engraving colour', 'plaque-it' ); ?><input type="text" name="engraving_colour" value="<?php echo esc_attr( get_post_meta( $product_id, '_plaque_it_engraving_colour', true ) ?: '#ffffff' ); ?>" /></label>
					</div>
					<h3><?php esc_html_e( 'Allowed Corner Styles', 'plaque-it' ); ?></h3>
					<?php $allowed = get_post_meta( $product_id, '_plaque_it_corner_styles', true ) ?: $settings['corner_styles']; ?>
					<?php foreach ( [ 'scallop', 'straight', 'none', 'rounded' ] as $style ) : ?>
						<label><input type="checkbox" name="corner_styles[]" value="<?php echo esc_attr( $style ); ?>" <?php checked( in_array( $style, (array) $allowed, true ) ); ?> /> <?php echo esc_html( ucwords( $style ) ); ?></label><br />
					<?php endforeach; ?>
					<?php submit_button( __( 'Save Product Settings', 'plaque-it' ), 'primary', 'plaque_it_save_product' ); ?>
					</div>
				</form>
			<?php else : ?>
				<div class="plaque-it-card plaque-it-empty-product-card">
					<div class="plaque-it-empty">
						<span class="plaque-it-empty-icon">#</span>
						<h3><?php esc_html_e( 'Select a product to configure', 'plaque-it' ); ?></h3>
						<p><?php esc_html_e( 'Use live search to find a product, then enable PlaqueIt and set product-specific limits.', 'plaque-it' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/** AJAX product search for the products admin page. */
	public function ajax_search_products(): void {
		check_ajax_referer( 'plaque_it_search_products', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to search products.', 'plaque-it' ) ], 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( [] );
		}

		wp_send_json_success( array_values( $this->search_products( $term ) ) );
	}

	/** Search WooCommerce products for the products admin page. */
	private function search_products( string $term ): array {
		$data_store  = WC_Data_Store::load( 'product' );
		$product_ids = $data_store->search_products( $term, '', false, true, 50 );
		$products    = [];
		$types       = wc_get_product_types();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$enabled  = Plaque_It_Validator::is_enabled_product( $product_id );
			$conflict = Plaque_It_Validator::has_personaliseit( $product_id );

			$products[ $product_id ] = [
				'id'       => $product_id,
				'name'     => $product->get_name(),
				'sku'      => $product->get_sku(),
				'type'     => $types[ $product->get_type() ] ?? $product->get_type(),
				'status'   => $product->get_status(),
				'enabled'  => $enabled,
				'conflict' => $conflict,
				'url'      => add_query_arg( [ 'page' => 'plaque-it-products', 'product_id' => $product_id ], admin_url( 'admin.php' ) ),
			];
		}

		return $products;
	}

	/** Render fonts page. */
	public function render_fonts(): void {
		$this->notice();
		$fonts = Plaque_It_Fonts::all();
		?>
		<div class="wrap plaque-it-admin plaque-it-font-manager">
			<div class="plaque-it-page-header">
				<div>
					<h1><?php esc_html_e( 'Font Manager', 'plaque-it' ); ?></h1>
					<p><?php esc_html_e( 'Manage the plaque fonts available to customers and production.', 'plaque-it' ); ?></p>
				</div>
				<button type="button" class="button button-primary plaque-it-upload-font-btn">+ <?php esc_html_e( 'Upload Font', 'plaque-it' ); ?></button>
			</div>

			<div class="plaque-it-tabs-bar">
				<button type="button" class="plaque-it-tab plaque-it-tab-active"><?php esc_html_e( 'Fonts', 'plaque-it' ); ?> <span><?php echo esc_html( count( $fonts ) ); ?></span></button>
			</div>

			<div class="plaque-it-card plaque-it-installed-fonts">
				<div class="plaque-it-card-header">
					<h2><?php esc_html_e( 'Installed Fonts', 'plaque-it' ); ?></h2>
					<span><?php echo esc_html( count( $fonts ) ); ?> <?php echo esc_html( 1 === count( $fonts ) ? __( 'font', 'plaque-it' ) : __( 'fonts', 'plaque-it' ) ); ?></span>
				</div>
				<?php if ( empty( $fonts ) ) : ?>
					<div class="plaque-it-empty">
						<span class="plaque-it-empty-icon">Aa</span>
						<h3><?php esc_html_e( 'No fonts uploaded yet', 'plaque-it' ); ?></h3>
						<p><?php esc_html_e( 'Upload a TTF, OTF, WOFF, or WOFF2 font to make plaque customisation available.', 'plaque-it' ); ?></p>
					</div>
				<?php else : ?>
					<div class="plaque-it-font-grid">
						<?php foreach ( $fonts as $font ) : ?>
							<div class="plaque-it-font-card<?php echo $font->active ? '' : ' plaque-it-font-card-inactive'; ?>">
								<div class="plaque-it-font-preview">
									<span style="font-family:'PlaqueItFont<?php echo (int) $font->id; ?>',sans-serif;font-weight:<?php echo esc_attr( $font->weight ); ?>;font-style:<?php echo esc_attr( $font->style ); ?>;">AaBbCc 123</span>
								</div>
								<form method="post" class="plaque-it-font-card-body">
									<?php wp_nonce_field( 'plaque_it_update_font' ); ?>
									<input type="hidden" name="font_id" value="<?php echo (int) $font->id; ?>" />
									<div class="plaque-it-font-title-row">
										<input type="text" name="name" value="<?php echo esc_attr( $font->name ); ?>" aria-label="<?php esc_attr_e( 'Font name', 'plaque-it' ); ?>" />
										<span class="plaque-it-badge <?php echo $font->active ? 'plaque-it-badge-active' : 'plaque-it-badge-inactive'; ?>"><?php echo $font->active ? esc_html__( 'Active', 'plaque-it' ) : esc_html__( 'Inactive', 'plaque-it' ); ?></span>
									</div>
									<div class="plaque-it-font-meta">
										<span class="plaque-it-badge"><?php echo esc_html( strtoupper( pathinfo( (string) $font->file_url, PATHINFO_EXTENSION ) ) ); ?></span>
										<span class="plaque-it-code"><?php echo esc_html( $font->weight ); ?></span>
										<?php if ( 'italic' === $font->style ) : ?><span class="plaque-it-badge"><?php esc_html_e( 'Italic', 'plaque-it' ); ?></span><?php endif; ?>
										<?php if ( ! empty( $font->production_restricted ) ) : ?><span class="plaque-it-badge plaque-it-badge-warning"><?php esc_html_e( 'Production restricted', 'plaque-it' ); ?></span><?php endif; ?>
									</div>
									<div class="plaque-it-font-fields">
										<label><?php esc_html_e( 'Width factor', 'plaque-it' ); ?><input type="number" step="0.001" name="width_factor" value="<?php echo esc_attr( $font->width_factor ); ?>" /></label>
										<label><?php esc_html_e( 'Minimum size', 'plaque-it' ); ?><input type="number" step="0.1" name="min_size" value="<?php echo esc_attr( $font->min_size ); ?>" /></label>
									</div>
									<label class="plaque-it-checkbox"><input type="checkbox" name="production_restricted" value="1" <?php checked( ! empty( $font->production_restricted ) ); ?> /> <?php esc_html_e( 'Production restricted', 'plaque-it' ); ?></label>
									<div class="plaque-it-font-actions">
										<?php submit_button( __( 'Save', 'plaque-it' ), 'secondary small', 'plaque_it_update_font', false ); ?>
										<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=plaque-it-fonts&plaque_it_toggle_font=' . (int) $font->id ), 'plaque_it_toggle_font_' . (int) $font->id ) ); ?>"><?php echo $font->active ? esc_html__( 'Deactivate', 'plaque-it' ) : esc_html__( 'Activate', 'plaque-it' ); ?></a>
										<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=plaque-it-fonts&plaque_it_delete_font=' . (int) $font->id ), 'plaque_it_delete_font_' . (int) $font->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this font?', 'plaque-it' ); ?>');"><?php esc_html_e( 'Delete', 'plaque-it' ); ?></a>
									</div>
								</form>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="plaque-it-modal-overlay" hidden aria-modal="true" role="dialog" aria-label="<?php esc_attr_e( 'Upload Font', 'plaque-it' ); ?>">
				<form method="post" enctype="multipart/form-data" class="plaque-it-modal">
					<?php wp_nonce_field( 'plaque_it_upload_font' ); ?>
					<div class="plaque-it-modal-header">
						<h3><?php esc_html_e( 'Upload Font', 'plaque-it' ); ?></h3>
						<button type="button" class="plaque-it-modal-close" aria-label="<?php esc_attr_e( 'Close', 'plaque-it' ); ?>">×</button>
					</div>
					<div class="plaque-it-modal-body">
						<label class="plaque-it-drop-zone">
							<span class="plaque-it-drop-icon">Aa</span>
							<span class="plaque-it-drop-title"><?php esc_html_e( 'Drop a font file here, or click to browse', 'plaque-it' ); ?></span>
							<span class="plaque-it-drop-hint"><?php esc_html_e( 'TTF, OTF, WOFF, or WOFF2', 'plaque-it' ); ?></span>
							<input type="file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required />
						</label>
						<div class="plaque-it-upload-preview"><span>AaBbCc 123</span></div>
						<div class="plaque-it-upload-fields">
							<label><?php esc_html_e( 'Font family name', 'plaque-it' ); ?><input type="text" name="name" placeholder="<?php esc_attr_e( 'e.g. Block Bold', 'plaque-it' ); ?>" /></label>
							<div class="plaque-it-upload-row">
								<label><?php esc_html_e( 'Weight', 'plaque-it' ); ?><select name="weight"><?php foreach ( [ '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ] as $weight ) : ?><option value="<?php echo esc_attr( $weight ); ?>" <?php selected( '400', $weight ); ?>><?php echo esc_html( $weight ); ?></option><?php endforeach; ?></select></label>
								<label><?php esc_html_e( 'Style', 'plaque-it' ); ?><select name="style"><option value="normal"><?php esc_html_e( 'Normal', 'plaque-it' ); ?></option><option value="italic"><?php esc_html_e( 'Italic', 'plaque-it' ); ?></option></select></label>
							</div>
							<div class="plaque-it-upload-row">
								<label><?php esc_html_e( 'Width factor', 'plaque-it' ); ?><input type="number" step="0.001" name="width_factor" value="0.56" /></label>
								<label><?php esc_html_e( 'Minimum size', 'plaque-it' ); ?><input type="number" step="0.1" name="min_size" value="8" /></label>
							</div>
							<label class="plaque-it-checkbox"><input type="checkbox" name="active" value="1" checked /> <?php esc_html_e( 'Available to customers', 'plaque-it' ); ?></label>
							<label class="plaque-it-checkbox"><input type="checkbox" name="production_restricted" value="1" /> <?php esc_html_e( 'Production restricted', 'plaque-it' ); ?></label>
						</div>
					</div>
					<div class="plaque-it-modal-footer">
						<button type="button" class="button plaque-it-modal-cancel"><?php esc_html_e( 'Cancel', 'plaque-it' ); ?></button>
						<?php submit_button( __( 'Upload Font', 'plaque-it' ), 'primary', 'plaque_it_upload_font', false ); ?>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/** Variation fields. */
	public function variation_fields( int $loop, array $variation_data, WP_Post $variation ): void {
		unset( $variation_data );
		woocommerce_wp_text_input( [ 'id' => "plaque_it_plaque_colour_{$loop}", 'name' => "plaque_it_plaque_colour[{$variation->ID}]", 'label' => __( 'Plaque colour', 'plaque-it' ), 'value' => get_post_meta( $variation->ID, '_plaque_it_plaque_colour', true ), 'placeholder' => '#111111', 'wrapper_class' => 'form-row form-row-first', 'class' => 'plaque-it-colour-field' ] );
		woocommerce_wp_text_input( [ 'id' => "plaque_it_engraving_colour_{$loop}", 'name' => "plaque_it_engraving_colour[{$variation->ID}]", 'label' => __( 'Engraving colour', 'plaque-it' ), 'value' => get_post_meta( $variation->ID, '_plaque_it_engraving_colour', true ), 'placeholder' => '#ffffff', 'wrapper_class' => 'form-row form-row-last', 'class' => 'plaque-it-colour-field' ] );
	}

	/** Add product data tab. */
	public function product_data_tab( array $tabs ): array {
		$tabs['plaque_it'] = [
			'label'    => __( 'PlaqueIt', 'plaque-it' ),
			'target'   => 'plaque_it_product_data',
			'class'    => [],
			'priority' => 80,
		];
		return $tabs;
	}

	/** Render product data panel. */
	public function product_data_panel(): void {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$product_id = $post->ID;
		$settings   = Plaque_It_Settings::all();
		$allowed    = get_post_meta( $product_id, '_plaque_it_corner_styles', true ) ?: $settings['corner_styles'];
		$conflict   = Plaque_It_Validator::has_personaliseit( $product_id );
		?>
		<div id="plaque_it_product_data" class="panel woocommerce_options_panel hidden">
			<?php if ( $conflict ) : ?>
				<p class="plaque-it-conflict"><?php esc_html_e( 'PersonaliseIt is already configured for this product. PlaqueIt cannot be enabled on the same product.', 'plaque-it' ); ?></p>
			<?php endif; ?>
			<?php
			woocommerce_wp_checkbox(
				[
					'id'          => '_plaque_it_enabled',
					'label'       => __( 'Enable PlaqueIt', 'plaque-it' ),
					'description' => __( 'Show the plaque configurator on this product.', 'plaque-it' ),
					'value'       => $conflict ? 'no' : get_post_meta( $product_id, '_plaque_it_enabled', true ),
				]
			);
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_min_width', 'label' => __( 'Minimum width (mm)', 'plaque-it' ), 'type' => 'number', 'custom_attributes' => [ 'step' => '0.1' ], 'value' => get_post_meta( $product_id, '_plaque_it_min_width', true ) ?: $settings['min_width'] ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_max_width', 'label' => __( 'Maximum width (mm)', 'plaque-it' ), 'type' => 'number', 'custom_attributes' => [ 'step' => '0.1' ], 'value' => get_post_meta( $product_id, '_plaque_it_max_width', true ) ?: $settings['max_width'] ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_min_height', 'label' => __( 'Minimum height (mm)', 'plaque-it' ), 'type' => 'number', 'custom_attributes' => [ 'step' => '0.1' ], 'value' => get_post_meta( $product_id, '_plaque_it_min_height', true ) ?: $settings['min_height'] ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_max_height', 'label' => __( 'Maximum height (mm)', 'plaque-it' ), 'type' => 'number', 'custom_attributes' => [ 'step' => '0.1' ], 'value' => get_post_meta( $product_id, '_plaque_it_max_height', true ) ?: $settings['max_height'] ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_max_lines', 'label' => __( 'Max lines', 'plaque-it' ), 'type' => 'number', 'custom_attributes' => [ 'step' => '1' ], 'value' => get_post_meta( $product_id, '_plaque_it_max_lines', true ) ?: $settings['max_lines'] ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_plaque_colour', 'label' => __( 'Fallback plaque colour', 'plaque-it' ), 'description' => __( 'Used for simple products and before a variation is selected.', 'plaque-it' ), 'value' => get_post_meta( $product_id, '_plaque_it_plaque_colour', true ) ?: '#111111', 'placeholder' => '#111111', 'class' => 'plaque-it-colour-field' ] );
			woocommerce_wp_text_input( [ 'id' => '_plaque_it_engraving_colour', 'label' => __( 'Fallback engraving colour', 'plaque-it' ), 'description' => __( 'Used for simple products and before a variation is selected.', 'plaque-it' ), 'value' => get_post_meta( $product_id, '_plaque_it_engraving_colour', true ) ?: '#ffffff', 'placeholder' => '#ffffff', 'class' => 'plaque-it-colour-field' ] );
			?>
			<p class="form-field"><label><?php esc_html_e( 'Corner styles', 'plaque-it' ); ?></label>
				<span class="wrap">
					<?php foreach ( [ 'scallop', 'straight', 'none', 'rounded' ] as $style ) : ?>
						<label style="margin-right:12px;"><input type="checkbox" name="_plaque_it_corner_styles[]" value="<?php echo esc_attr( $style ); ?>" <?php checked( in_array( $style, (array) $allowed, true ) ); ?> /> <?php echo esc_html( ucwords( $style ) ); ?></label>
					<?php endforeach; ?>
				</span>
			</p>
		</div>
		<?php
	}

	/** Save product data panel. */
	public function save_product_data_panel( int $product_id ): void {
		$enabled = empty( $_POST['_plaque_it_enabled'] ) ? 'no' : 'yes';
		if ( 'yes' === $enabled && Plaque_It_Validator::has_personaliseit( $product_id ) ) {
			$enabled = 'no';
		}
		update_post_meta( $product_id, '_plaque_it_enabled', $enabled );

		foreach ( [ 'min_width', 'max_width', 'min_height', 'max_height', 'max_lines' ] as $key ) {
			$field = '_plaque_it_' . $key;
			update_post_meta( $product_id, $field, (float) ( $_POST[ $field ] ?? 0 ) );
		}

		$plaque_colour    = sanitize_hex_color( wp_unslash( $_POST['_plaque_it_plaque_colour'] ?? '' ) ) ?: '#111111';
		$engraving_colour = sanitize_hex_color( wp_unslash( $_POST['_plaque_it_engraving_colour'] ?? '' ) ) ?: '#ffffff';
		update_post_meta( $product_id, '_plaque_it_plaque_colour', $plaque_colour );
		update_post_meta( $product_id, '_plaque_it_engraving_colour', $engraving_colour );

		$styles = isset( $_POST['_plaque_it_corner_styles'] ) && is_array( $_POST['_plaque_it_corner_styles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['_plaque_it_corner_styles'] ) ) : [];
		update_post_meta( $product_id, '_plaque_it_corner_styles', array_values( array_intersect( $styles, [ 'scallop', 'straight', 'none', 'rounded' ] ) ) );
	}

	/** Save variation fields. */
	public function save_variation_fields( int $variation_id, int $i ): void {
		unset( $i );
		$plaque = isset( $_POST['plaque_it_plaque_colour'][ $variation_id ] ) ? sanitize_hex_color( wp_unslash( $_POST['plaque_it_plaque_colour'][ $variation_id ] ) ) : '';
		$engrave = isset( $_POST['plaque_it_engraving_colour'][ $variation_id ] ) ? sanitize_hex_color( wp_unslash( $_POST['plaque_it_engraving_colour'][ $variation_id ] ) ) : '';
		update_post_meta( $variation_id, '_plaque_it_plaque_colour', $plaque ?: '#111111' );
		update_post_meta( $variation_id, '_plaque_it_engraving_colour', $engrave ?: '#ffffff' );
	}

	/** Save product settings. */
	private function save_product(): string {
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			return __( 'Product could not be loaded.', 'plaque-it' );
		}

		if ( ! empty( $_POST['enabled'] ) && Plaque_It_Validator::has_personaliseit( $product_id ) ) {
			update_post_meta( $product_id, '_plaque_it_enabled', 'no' );
			return __( 'PlaqueIt was not enabled because PersonaliseIt is already configured for this product.', 'plaque-it' );
		}

		update_post_meta( $product_id, '_plaque_it_enabled', empty( $_POST['enabled'] ) ? 'no' : 'yes' );
		foreach ( [ 'min_width', 'max_width', 'min_height', 'max_height', 'max_lines' ] as $key ) {
			update_post_meta( $product_id, '_plaque_it_' . $key, (float) ( $_POST[ $key ] ?? 0 ) );
		}
		$plaque_colour    = sanitize_hex_color( wp_unslash( $_POST['plaque_colour'] ?? '' ) ) ?: '#111111';
		$engraving_colour = sanitize_hex_color( wp_unslash( $_POST['engraving_colour'] ?? '' ) ) ?: '#ffffff';
		update_post_meta( $product_id, '_plaque_it_plaque_colour', $plaque_colour );
		update_post_meta( $product_id, '_plaque_it_engraving_colour', $engraving_colour );

		$styles = isset( $_POST['corner_styles'] ) && is_array( $_POST['corner_styles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['corner_styles'] ) ) : [];
		update_post_meta( $product_id, '_plaque_it_corner_styles', array_values( array_intersect( $styles, [ 'scallop', 'straight', 'none', 'rounded' ] ) ) );

		return __( 'Product settings saved.', 'plaque-it' );
	}

	/** Toggle font active state. */
	private function toggle_font( int $id ): void {
		$font = Plaque_It_Fonts::get( $id );
		if ( ! $font ) {
			return;
		}
		global $wpdb;
		$wpdb->update( Plaque_It_DB::fonts_table(), [ 'active' => empty( $font->active ) ? 1 : 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
	}

	/** Update font editable settings. */
	private function update_font(): void {
		$id = absint( $_POST['font_id'] ?? 0 );
		if ( ! $id || ! Plaque_It_Fonts::get( $id ) ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			Plaque_It_DB::fonts_table(),
			[
				'name'                  => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'width_factor'          => max( 0.1, (float) ( $_POST['width_factor'] ?? 0.56 ) ),
				'min_size'              => max( 1, (float) ( $_POST['min_size'] ?? 8 ) ),
				'production_restricted' => empty( $_POST['production_restricted'] ) ? 0 : 1,
			],
			[ 'id' => $id ],
			[ '%s', '%f', '%f', '%d' ],
			[ '%d' ]
		);
	}

	/** Number field. */
	private function number_field( string $name, string $label, mixed $value, mixed $step = '0.1' ): void {
		echo '<label>' . esc_html( $label ) . '<input type="number" step="' . esc_attr( (string) $step ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" /></label>';
	}

	/** Product number field. */
	private function product_number_field( int $product_id, string $name, string $label, mixed $default, mixed $step = '0.1' ): void {
		$value = get_post_meta( $product_id, '_plaque_it_' . $name, true );
		$this->number_field( $name, $label, '' === $value ? $default : $value, $step );
	}

	/** Redirect with notice. */
	private function redirect_notice( string $notice, string $page = '', array $extra = [] ): void {
		$page = $page ?: sanitize_key( $_GET['page'] ?? 'plaque-it' );
		$args = array_merge( [ 'page' => $page, 'plaque_it_notice' => $notice ], $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Notice output. */
	private function notice(): void {
		if ( empty( $_GET['plaque_it_notice'] ) ) {
			return;
		}
		echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['plaque_it_notice'] ) ) ) . '</p></div>';
	}
}
