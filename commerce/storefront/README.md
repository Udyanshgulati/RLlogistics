# Storefront themes

Classic WooCommerce themes. No WordPress or WooCommerce core files are modified.

- `themes/brandhouse` — shared parent theme for all three stores: header, footer, listing with size filter, product page (size buttons from real variation stock, size guide, accordions), cart/checkout styling. Checkout stays WooCommerce's own.
- `themes/schermo` — Schermo child theme: monochrome tokens from the logo, Syncopate + Inter (self-hosted, OFL), homepage.

## Client content (placeholders until supplied)
Nothing is invented. Empty slots show a labelled grey placeholder.

| What | Where |
|---|---|
| Logo | Appearance → Customize → Site Identity → Logo (until then the name is typeset as a wordmark) |
| Hero image, headline, line, button; brand story text and image | Customize → Homepage |
| Announcement bar, footer note | Customize → General |
| Delivery and returns text, size guide table | Customize → Product pages |
| Category images | Products → Categories → Thumbnail |
| Menus | Appearance → Menus (Primary, Footer: Shop / Help / Policies) |

## Local preview
Install WordPress + WooCommerce, symlink both theme folders into `wp-content/themes`, activate **Schermo**, and create the global attribute **Size** (`pa_size`, terms S–XXXL). Product variations use that attribute.
