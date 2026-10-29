# 07 · Client information checklist

**D** = needed before development of that part · **T** = before integration testing on staging · **L** = before launch.
Never send passwords, API keys or payment secrets in this document, email or chat. Credentials are entered directly into the server's environment file or the provider's dashboard during a screen-share.

| Area | Item | When | Status |
|---|---|---|---|
| Business | Legal entity selling for each brand (and whether one entity sells all three) | D | Open |
| | Ownership structure (Significant Sports & Entertainment ↔ brands) | D | Open |
| | GSTIN, IEC (import-export code), LUT for zero-rated exports | T | Open |
| | Entity that receives payments for each brand | T | Open |
| Domains | Official domain for Milan Icons (repo README mentions milanicon.com — confirm) | D | Open |
| | Official domain for Significant Sports (significantsports.in referenced — confirm store domain) | D | Open |
| | schermo.in — DNS access | T | Given (domain) / access open |
| | DNS and hosting access method (delegated accounts, not shared passwords) | T | Open |
| | Any existing WordPress installs to migrate | D | None found |
| Brand | Logos, colours, typography, photography for Significant Sports and Schermo; confirmation that Milan Icons repo assets may be used | D | Open |
| Products | Catalogue: names, descriptions, images, prices per brand | D | Open |
| | SKU convention approval (proposed `BRAND-TYPE-NNN-SIZE`, prefixes MI / SS / SCH) | D | Open |
| | Variants and size runs per product (S–XXXL or other) | D | Open |
| | Opening stock per SKU (counted) | T | Open |
| | Weight, dimensions, country of origin, HS code, declared value per SKU | T | Open |
| Inventory | Which products are physically shared across brands, and which brand owns each | D | Open |
| | Allocation rules (any stock reserved for one brand?) | D | Open |
| | Current damaged / held stock | T | Open |
| Warehouse | Full Delhi address, PIN code, contact | T | Open |
| | Pickup windows, daily packing capacity, cut-off time | T | Open |
| | Returns address and inspection process | T | Open |
| | Storage locations (for pick lists), if used | L | Optional |
| Shipping | Priority destination countries | D | Open |
| | Carrier accounts (DHL / FedEx / UPS / aggregator) and API access | T | Open |
| | Contract rate cards; DAP vs DDP decision; insurance | T | Open |
| | Returns policy per market | L | Open |
| Payments | Preferred gateways; merchant accounts created and international payments activated | T | Open |
| | Currencies each gateway will charge and settle (in writing) | T | Open |
| | Refund rules (who approves, partial refunds) | L | Open |
| Tax | Adviser sign-off on GST treatment, destination VAT/duties presentation per market | L | Open |
| Communication | Sender addresses per brand (e.g. orders@…) and reply-to; support email/phone per brand | T | Open |
| | Email provider choice (recommended Amazon SES) and DNS access for SPF/DKIM/DMARC | T | Open |
| | WhatsApp Business number per brand (optional) and template approvals | L | Optional |
| | Consent wording for WhatsApp and newsletters | L | Open |
| Scale | Expected monthly orders, peak events (match days, launches), SKU count | D | Open |
| | Launch date per brand and budget | D | Open |
| Policies | Privacy, terms, returns, shipping policy per brand (legal review) | L | Open |
