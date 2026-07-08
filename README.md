# PlaqueIt

WooCommerce plaque customiser plugin with live previews, uploaded fonts, size-based pricing, cart/checkout previews, and backend SVG print files.

## Plugin

The WordPress plugin lives in `plaque-it/`.

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.0+

## Development Checks

```bash
phpunit -c plaque-it/phpunit.xml.dist
npm --prefix plaque-it run lint:js
```

## Notes

PlaqueIt is separate from PersonaliseIt and must not be enabled on the same product.
