# PlaqueIt WooCommerce Plugin Plan

## Goal

Build a WooCommerce plugin for customers ordering custom plaques. The plugin should provide a live plaque preview, collect plaque-specific customisation data, enforce readable text limits, and store the final configuration with the cart and order.

The plugin must work alongside existing plugins, especially PersonaliseIt and True Video Product Gallery.

Important constraint: do not edit the existing PersonaliseIt plugin files. The PersonaliseIt source is available for reference at:

```text
/home/agent/workspaces/Coding Files/CustomiseKings/personaliseit
```

PlaqueIt should follow the same broad UX patterns where useful, but it must be a separate plugin with its own files, database tables, assets, hooks, meta keys, and upload directories.

## Confirmed Requirements

- Dimensions are entered and stored in millimetres.
- Preview and print calculations use 76 DPI.
- Price changes based on plaque size.
- PlaqueIt only appears on products enabled through a PlaqueIt admin area.
- Each enabled product variant needs assigned plaque colour and engraving colour.
- Fonts need an admin font management page similar to PersonaliseIt.
- Orders need downloadable print files in the WooCommerce backend, similar to PersonaliseIt.
- Cart and checkout pages must show the plaque preview, similar to PersonaliseIt.
- Pricing uses the base variation price plus an area-based surcharge.
- Print output starts with SVG for production and PNG for previews.
- PlaqueIt has its own font manager. Importing from PersonaliseIt can be considered later, but PlaqueIt should not depend on PersonaliseIt at runtime.
- Minimum readable font size starts at 8pt and is configurable.
- Admins can set an absolute max line count, while automatic readability validation still enforces fit.
- Corner styles have the same price initially.
- Customers must approve the preview before adding to cart.
- PlaqueIt and PersonaliseIt must never be enabled on the same product.
- Pricing formula is configurable in the PlaqueIt admin settings.
- Fonts are uploaded through the PlaqueIt font manager.
- Minimum and maximum plaque sizes are configurable in PlaqueIt admin settings.
- Initial customer font availability is configurable in the PlaqueIt admin UI.
- Default formula area rate is configurable in the PlaqueIt admin UI.
- Uploaded font production restrictions are configurable in the PlaqueIt admin UI.
- Product variations only control plaque colour and engraving colour.
- Plaques do not use stock management.

## Customer Flow

Customers configure plaques in this order:

1. Enter plaque height and width.
2. Select the corner style.
3. Type their plaque message.
4. Select a font and font size for each typed line.
5. Review a live preview that updates after every change.
6. Add the configured plaque to cart only if the configuration passes readability rules.

The customer-facing preview should use the selected variation's plaque colour and engraving colour automatically. Customers select the product variation as normal through WooCommerce; PlaqueIt reads the selected variation and updates the plaque preview colours.

## Corner Styles

Initial corner styles:

- Scallop
- Straight cut
- No cut
- Rounded

Open questions:

- Do corner styles affect available text area?
- Do we need visual examples for each corner style in the selector?

Initial decision: corner styles do not affect price.

## Product Page Placement

The plaque configurator should render inside the WooCommerce add-to-cart form, not in the product gallery area.

Recommended hook:

```php
woocommerce_before_add_to_cart_button
```

Reason: True Video Product Gallery overrides the WooCommerce product image template, so PlaqueIt should avoid touching gallery templates or image hooks.

PlaqueIt should only render when the current product or selected variation has PlaqueIt enabled in the plugin admin configuration.

## Plugin Structure

Proposed structure:

```text
plaque-it.php
uninstall.php
readme.txt
composer.json
package.json
phpcs.xml.dist
phpunit.xml.dist
includes/
  class-plaque-it.php
  class-plaque-it-frontend.php
  class-plaque-it-cart.php
  class-plaque-it-admin.php
  class-plaque-it-admin-order-metabox.php
  class-plaque-it-db.php
  class-plaque-it-files.php
  class-plaque-it-fonts.php
  class-plaque-it-pricing.php
  class-plaque-it-renderer.php
  class-plaque-it-settings.php
  class-plaque-it-validator.php
assets/
  js/plaque-it-frontend.js
  js/plaque-it-admin.js
  css/plaque-it-frontend.css
  css/plaque-it-admin.css
tests/
  Unit/
```

## Main Components

### Frontend Configurator

Responsibilities:

- Render the plaque controls on eligible products.
- Render the live plaque preview.
- Split the customer message into editable lines.
- Allow font and font-size selection per line.
- Show live validation messages.
- Disable or block add-to-cart when the configuration is invalid.

Fields:

- Height
- Width
- Corner style
- Message lines
- Font per line
- Font size per line
- Selected product variation ID
- Plaque colour from the selected variation
- Engraving colour from the selected variation

Hidden submitted payload:

- JSON plaque configuration.
- Preview image URL or preview SVG generated before submit.
- Calculated price inputs.
- Preview approval flag.

### Live Preview

The preview should update when customers change:

- Plaque dimensions
- Corner style
- Message text
- Line count
- Font family
- Font size
- Product variation
- Plaque colour
- Engraving colour

Possible rendering options:

- SVG preview
- HTML/CSS preview
- Canvas preview

Recommended starting point: SVG preview, because it can represent dimensions, clipping paths, rounded corners, scalloped corners, and text positioning cleanly.

Preview scale:

- Customer dimensions are in millimetres.
- Rendering calculations use 76 DPI.
- Conversion formula: `pixels = millimetres / 25.4 * 76`.
- The on-page preview can be visually scaled down with CSS while preserving the 76 DPI coordinate system internally.

The preview should use:

- Plaque fill colour from the selected variation.
- Engraving/text colour from the selected variation.
- Corner style path/clip shape.
- Safe-area overlay in development/debug mode only.

The preview must also display in:

- Cart item thumbnail area.
- Classic checkout line item area.
- WooCommerce Blocks cart/checkout line item image if supported.
- Admin order item details.

Customers must tick a preview approval checkbox before add-to-cart. Server-side validation must reject plaque products without this approval flag.

### Readability Validator

The system must calculate allowed font sizes and line counts based on plaque dimensions.

Validation must run in both places:

- JavaScript for live feedback.
- PHP during add-to-cart, so customers cannot bypass limits.

Initial validation idea:

```text
px_width = plaque_width_mm / 25.4 * 76
px_height = plaque_height_mm / 25.4 * 76
safe_width = px_width * 0.85
safe_height = px_height * 0.80
line_height = selected_font_size * 1.25
total_text_height = line_count * line_height
estimated_line_width = character_count * selected_font_size * font_width_factor
```

Rules:

- Total text height must fit inside the safe height.
- Each line width must fit inside the safe width.
- Font size must not go below a minimum readable size.
- Line count must not exceed the calculated maximum.
- Empty configurations should not be addable to cart.

Possible default values:

- Safe width: 85% of plaque width.
- Safe height: 80% of plaque height.
- Minimum readable font size: 8pt.
- Line-height multiplier: 1.25.
- Font width factor: configurable per font.
- DPI: 76.
- Admin max lines: configurable per product.

Open questions:

- What is the real-world minimum readable font size for production?
- Should font limits differ by material or plaque type?
- Are there manufacturing margins we must reserve around edges or holes?

### WooCommerce Cart And Order Data

Plaque configuration should be saved as cart item data.

Recommended hooks:

```php
woocommerce_add_to_cart_validation
woocommerce_add_cart_item_data
woocommerce_get_item_data
woocommerce_checkout_create_order_line_item
```

Stored data should include:

- Height
- Width
- Unit: mm
- DPI: 76
- Corner style
- Message lines
- Font per line
- Font size per line
- Product ID
- Variation ID
- Plaque colour
- Engraving colour
- Calculated plaque price
- Validation result or calculated constraints
- Preview image/SVG reference
- Print file reference after order generation
- Preview approval flag

Use prefixed keys to avoid conflicts:

```text
_plaque_it_config
_plaque_it_preview_svg
_plaque_it_preview_url
_plaque_it_print_file_id
```

PersonaliseIt reference pattern observed:

- Uses `woocommerce_add_cart_item_data` to save a JSON customisation payload.
- Uses `woocommerce_get_item_data` to show cart/checkout summary rows.
- Uses `woocommerce_cart_item_thumbnail` and `woocommerce_cart_item_name` for cart/checkout preview display.
- Uses `woocommerce_store_api_cart_item_images` for Blocks cart/checkout image support.
- Uses `woocommerce_checkout_create_order_line_item` to save HPOS-compatible order item meta.
- Uses `woocommerce_order_item_meta_end` and `woocommerce_before_order_itemmeta` to show previews/details in order screens.
- Uses `woocommerce_hidden_order_itemmeta` to hide internal production meta from default WooCommerce tables.

PlaqueIt should implement the same behaviours with PlaqueIt-specific classes and meta keys.

### Product And Variation Admin Settings

The plugin is enabled only for selected products through a PlaqueIt admin menu. Product variation settings are required because each variant maps to a plaque colour and engraving colour.

Admin product settings could include:

- Enable PlaqueIt for this product.
- Minimum width.
- Maximum width.
- Minimum height.
- Maximum height.
- Available corner styles.
- Available fonts.
- Minimum readable font size.
- Safe area width percentage.
- Safe area height percentage.
- Whether preview approval is required.
- Formula pricing settings.
- Absolute max line count.
- Default area pricing rate.
- Uploaded font production restriction settings.
- Customer font availability settings.

Variation settings should include:

- Plaque colour label.
- Plaque colour value, likely hex.
- Engraving colour label.
- Engraving colour value, likely hex.

Variation settings should not include sizing, stock, SKU, or pricing controls. Product variations only control the customer-facing plaque colour and engraving colour.

Recommended hook areas:

```php
woocommerce_product_data_tabs
woocommerce_product_data_panels
woocommerce_process_product_meta
```

Stock decision: plaques do not use stock management. Dimensions do not affect stock or SKU.

Admin menu pages should likely include:

- PlaqueIt Products
- PlaqueIt Fonts
- PlaqueIt Pricing
- PlaqueIt Settings
- PlaqueIt Print Files or Queue if print generation becomes asynchronous

## Compatibility Requirements

### True Video Product Gallery

Known behaviour:

- It overrides `single-product/product-image.php`.
- It manages product gallery rendering and frontend assets.

PlaqueIt should:

- Avoid overriding gallery templates.
- Avoid changing product image hooks.
- Avoid using generic class names that could conflict.
- Enqueue assets only on plaque-enabled product pages.

### PersonaliseIt

PersonaliseIt source is available for reference only. Do not edit it.

Relevant observed behaviours:

- Font manager page supports font upload, activation/deactivation, rename, delete, font groups, and print conversion.
- Cart integration stores customisation data under `_oc_customisation` and preview URL under `_oc_preview_url`.
- Cart and checkout preview images are injected into thumbnails and line item names.
- Order item meta stores the customisation payload.
- Admin order metabox lists generated print files with Download and Regenerate actions.
- Print files are generated and stored in plugin-managed upload locations.

PlaqueIt should:

- Use unique field names and meta keys.
- Avoid writing to `_oc_*` meta keys.
- Avoid using `oc-*` CSS classes or `OC_*` PHP class names.
- Avoid editing or depending directly on PersonaliseIt internals.
- Match the admin/user experience where useful: font manager, preview thumbnails, downloadable print files.
- Block PlaqueIt enablement if PersonaliseIt is already enabled for the product.
- Do not provide an admin override for running PlaqueIt and PersonaliseIt on the same product.

## Pricing

Pricing changes based on plaque size.

Possible pricing inputs:

- Width
- Height
- Total area
- Corner style
- Number of lines
- Premium fonts
- Proof generation

Recommended initial pricing model:

```text
area_mm2 = width_mm * height_mm
area_price = area_mm2 * product_area_rate
final_price = base_product_price + area_price + optional_surcharges
```

Confirmed initial approach: use the base product or variation price plus an area surcharge. PlaqueIt does not replace the variation price by default.

Formula pricing is configured in the PlaqueIt admin settings. The initial formula should support an admin-defined area rate.

Non-goal for initial build: size-band pricing.

WooCommerce implementation options:

- Store the calculated plaque price in cart item data.
- Set the cart item product price during `woocommerce_before_calculate_totals`.
- Recalculate server-side from saved dimensions every time cart totals are calculated.
- Never trust the frontend price as authoritative.

The product page should show a live price update when dimensions change.

Initial decision: corner style does not affect price.

## Fonts

The plugin needs a controlled font list so validation can estimate text width reliably. Fonts are uploaded and managed through a PlaqueIt font manager page similar to PersonaliseIt's Font Manager.

Each font should define:

- Label
- CSS font-family
- Uploaded font file path
- Active/inactive state
- Font groups
- Print-compatible converted font file if required
- Weight
- Style
- Width factor
- Minimum readable size if different from default
- Whether it is available to customers

Font manager requirements:

- Upload fonts.
- Rename fonts.
- Activate/deactivate fonts.
- Delete fonts.
- Preview installed fonts.
- Create and manage font groups.
- Assign font groups to plaque-enabled products.
- Support print-file generation using the selected font.

PersonaliseIt uses uploaded fonts and has a print conversion workflow. PlaqueIt should mirror that concept in its own tables and upload folders.

Initial decision: PlaqueIt has its own independent font manager. A future importer can copy fonts from PersonaliseIt, but the plugin should not depend on PersonaliseIt being active.

Open questions:

- Which fonts should be included?
- Are there production restrictions on fonts?

## Print Files

Orders must include downloadable print files in the WooCommerce order details backend, similar to PersonaliseIt.

Print file requirements:

- Generate a production-ready file after order creation.
- Use millimetre plaque dimensions converted at 76 DPI.
- Include final plaque shape/corner style.
- Include engraving text in selected fonts and sizes.
- Use selected variation plaque colour and engraving colour.
- Store file metadata against the WooCommerce order item.
- Add an admin order metabox listing print files.
- Provide Download action for ready files.
- Provide Regenerate action for changed or failed files.
- Work with HPOS orders.

Possible output formats:

- SVG for vector engraving/cutting workflows.
- PDF if production needs a print-proof format.
- PNG preview for cart/checkout thumbnails.

Recommended initial outputs:

- SVG print file for production.
- PNG preview image for cart, checkout, mini cart, and order previews.

Confirmed initial output approach: SVG for production and PNG for previews.

Upload storage:

```text
wp-content/uploads/plaque-it/previews/
wp-content/uploads/plaque-it/print-files/
wp-content/uploads/plaque-it/fonts/
```

Security requirements:

- Download links must be nonce-protected.
- Admin download actions require `manage_woocommerce`.
- Only PlaqueIt-generated files from PlaqueIt upload directories can be downloaded.
- File names should use random hashes, not customer text.

## Data Model Draft

Example cart/order configuration:

```json
{
  "width": 200,
  "height": 100,
  "unit": "mm",
  "dpi": 76,
  "product_id": 123,
  "variation_id": 456,
  "plaque_colour": "#111111",
  "engraving_colour": "#d4af37",
  "corner_style": "rounded",
  "lines": [
    {
      "text": "Line one",
      "font": "serif",
      "size": 18
    },
    {
      "text": "Line two",
      "font": "sans",
      "size": 14
    }
  ],
  "constraints": {
    "safe_width": 170,
    "safe_height": 80,
    "max_lines": 4,
    "minimum_font_size": 8
  },
  "pricing": {
    "area_mm2": 20000,
    "calculated_price": 24.99
  },
  "preview_approved": true,
  "preview_url": "https://example.com/wp-content/uploads/plaque-it/previews/preview-hash.png",
  "print_file_id": 789
}
```

## Security And Validation

Server-side validation is required.

Sanitisation rules:

- Dimensions must be numeric and within product limits.
- Corner style must be in the allowed list.
- Font must be in the allowed list.
- Font size must be numeric and within calculated limits.
- Message text must be sanitised.
- HTML should not be accepted in customer message text.
- Variation ID must belong to the selected product.
- Plaque colour and engraving colour must come from admin-assigned variation settings, not from customer input.
- Calculated price must be recomputed server-side.
- Preview and print file URLs must point to PlaqueIt-generated upload directories.
- Preview approval must be present for plaque-enabled products.

WooCommerce add-to-cart must fail with clear errors when invalid.

## Build Phases

### Phase 1: Requirements Finalisation

- Define admin UI defaults for available corner styles.
- Define admin UI defaults for uploaded fonts and customer font availability.
- Define admin UI defaults for minimum readable font size.
- Define admin UI defaults for formula area rate.
- Define admin UI defaults for min/max plaque dimensions.

### Phase 2: Plugin Foundation

- Create plugin bootstrap.
- Declare WooCommerce and HPOS compatibility.
- Add frontend, cart, validator, and admin classes.
- Add plaque-enabled product setting.
- Add database tables for enabled products, variation colour assignments, fonts, pricing, and print files.

### Phase 3: Frontend Configurator

- Render plaque fields.
- Build live preview.
- Add JS validation.
- Add per-line font controls.
- Add 76 DPI mm-to-pixel preview scaling.
- Add live price updates.
- Add variation colour updates.

### Phase 4: Server Validation And Cart Integration

- Validate posted data on add-to-cart.
- Save configuration to cart item data.
- Display configuration in cart and checkout.
- Replace cart/checkout thumbnail with plaque preview.
- Support Store API cart item images for block cart/checkout if needed.
- Recalculate and apply size-based price in cart totals.
- Save configuration to order item meta.

### Phase 5: Admin And Production Workflow

- Add product settings.
- Add variation plaque colour and engraving colour assignment.
- Add font manager.
- Add pricing manager.
- Add order admin display improvements.
- Add preview storage.
- Add print file generation.
- Add admin order metabox with Download and Regenerate actions.

### Phase 6: Testing And Compatibility

- Test with WooCommerce classic product pages.
- Test with cart and checkout blocks if required.
- Test alongside True Video Product Gallery.
- Test alongside PersonaliseIt once source is available.
- Test variable products if supported.
- Test cart preview.
- Test checkout preview.
- Test admin order print downloads.
- Test HPOS order screens.

## Admin UI Settings Needed

1. Customer-available fonts.
2. Default min/max plaque dimensions in mm.
3. Default formula area rate.
4. Uploaded font production restrictions.
5. Available corner styles.
6. Minimum readable font size.
7. Absolute max line count.
