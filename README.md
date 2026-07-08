# PlaqueIt

WooCommerce plaque customiser plugin with live previews, uploaded fonts, size-based pricing, cart/checkout previews, and backend SVG print files.

## Plugin

The WordPress plugin lives at the repository root. The main plugin file is `plaque-it.php`.

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.0+

## Development Checks

```bash
phpunit -c phpunit.xml.dist
npm run lint:js
```

## Notes

PlaqueIt is separate from PersonaliseIt and must not be enabled on the same product.
