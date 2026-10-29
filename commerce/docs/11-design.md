# 11 · Design

## 1. Operations dashboard — Stitch process

| Step | What happened |
|---|---|
| Visual study | RL Logistics source in this repository: navy `#0B1F4D`, cool paper, amber `#F2A900` "used sparingly", dashed route lines that fill as you progress, waypoint codes. Translated, not copied: navy becomes the single accent, amber is reserved for the moving route marker, dashed lines and monospace waypoint codes (`DEL → TRANSIT → DEST`) carry the logistics character. |
| Stitch project | "Operations Hub — Milan Icons · Significant Sports · Schermo" (`projects/8354299628741593392`). |
| Design system in Stitch | "Operations Hub — Route": Inter, warm off-white `#F6F6F4`, navy primary, amber tertiary, 1px `#E8E6E1` borders, status pill palette, KPI strip, table rules, motion rules, brand tags. |
| Overview screen | Generated and retrieved. Adopted: six-cell KPI strip, Shipment journey card with ring nodes and codes, Needs attention with square count chips, Integration health with "Background jobs" footer, compact Inventory flow, stock movements with delta pills, navy selected navigation. **Rejected:** its invented sample data (order numbers, SKUs) and a "LIVE SYNC" label that would overstate what the system does. |
| Inventory (with adjust side panel) and Order details screens | Requested from Stitch; generation ran past the tool's 60-second limit and the screens could not be retrieved (`list_screens` returned nothing, and edit/download need screen IDs). **Not used.** These screens were implemented from the same design system and checked in the browser instead. |

Implementation: `hub/public/assets/app.css` (tokens on `:root`, no framework), `hub/public/assets/app.js` (no dependencies). Strict CSP: no inline styles or scripts, so progress states use classes, not `style=""`.

### Motion (all disabled under `prefers-reduced-motion`)
- **Shipment journey** (Overview): dashed route, navy fill to the TRANSIT node and an amber marker travelling along it only when parcels are in transit. Counts come from shipment statuses; the card says it is not live GPS.
- **Inventory flow** (Overview) and **stock lifecycle strip** (Inventory): Received → Available → Reserved → Dispatched with directional connectors and plain definitions.
- **Order journey** (Order page): New order → Processing → Packed → Dispatched → Delivered. Stages render from the server's status only; when a stage advanced since this browser last viewed the order, the connector fills and the node pops once.
- Micro-interactions: side panel slides in/out (220/180 ms), size rows expand with a short fade, filters dim results while loading, flashes slide in, buttons lock on submit to stop double posts.

## 2. Storefront art direction (to build; needs assets)

Shared principles: warm white or ivory backgrounds, charcoal type, one restrained accent per brand, generous whitespace, large photography, editorial serif headlines paired with a neutral sans for UI, square or barely rounded image containers, minimal motion, no gradients/glassmorphism/fake trust badges/invented reviews. One parent theme carries components (header, product grid, product page with size selector and size guide, cart, checkout styling, footer); each brand is a child theme with its own tokens, homepage composition and copy.

| Brand | Direction | Basis | Open |
|---|---|---|---|
| Milan Icons | Black and gold, Duomo motifs used sparingly, match-kit photography, crest | Real assets in the Milan Icons repository (black `#0b0b0c`, gold `#dca745`, crimson accents, League Spartan/Montserrat, kit renders, Duomo artwork) | Client confirmation to reuse; product photography |
| Significant Sports | Performance and training; cleaner, cooler palette | Parent company logo (white) seen in the Milan Icons repo; no guidelines | Brand guidelines, colours, imagery |
| Schermo | Distinct luxury-commerce direction; do not assume colours | Nothing provided beyond the domain | All brand assets |

Homepage sections per brief (announcement bar, header, editorial hero, headline, CTA, featured products/collections, brand story, categories, campaign block, shipping/service information, newsletter if wanted, footer) are composed differently per brand; product listing and product pages follow the brief's lists (real filters only, availability from the hub, sizes as real WooCommerce variations).
