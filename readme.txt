=== PlaqueIt ===
Contributors: sldevs
Requires at least: 6.4
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later

WooCommerce plaque customiser with live previews, uploaded fonts, size-based pricing, and admin print files.

== Description ==

PlaqueIt adds a plaque configurator to selected WooCommerce products. Customers enter dimensions in millimetres, choose a corner style, type their message, and choose a font and size per line. The preview uses 76 DPI calculations and updates live.

The plugin stores plaque data in cart and order item meta, recalculates size-based pricing server-side, shows previews in cart/checkout/order screens, and generates SVG print files for WooCommerce order admins.

PlaqueIt is intentionally separate from PersonaliseIt and must not be enabled on the same product.

== Initial Setup ==

1. Activate WooCommerce.
2. Activate PlaqueIt.
3. Go to PlaqueIt > Settings and configure default dimensions, area pricing rate, line limits, and corner styles.
4. Go to PlaqueIt > Fonts and upload at least one font.
5. Enable PlaqueIt on a product through PlaqueIt > Products or the WooCommerce product edit PlaqueIt tab.
6. For variable products, set each variation's plaque colour and engraving colour.

== Print Files ==

SVG print files are generated after checkout and are available from the PlaqueIt Print Files metabox on the WooCommerce order edit screen.
