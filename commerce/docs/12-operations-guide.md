# 12 · Operations guide (for the team)

## Roles

| Role | Can | Cannot |
|---|---|---|
| Owner / Super admin | Everything, all brands | — |
| Operations manager | Inventory (all adjustments, import/export), products and linking, purchasing, orders and fulfilment, cancellations, returns, operational and sales reports, integration health | Team, settings, refunds, offline payments |
| Inventory manager | Stock (all adjustments, stocktake, import/export), products, linking, purchasing, view orders, operational reports | Fulfil orders, refunds |
| Warehouse staff | View stock, record **received / damaged / returned-to-inventory**, receive purchase orders, pick/pack/ship/dispatch, receive and inspect returns | Losses and corrections, customer details, refunds, settings |
| Brand manager (per brand) | View that brand's orders, stock and products; operational and sales reports for that brand | Other brands, customer details, changes |
| Customer support | View orders with customer details, request cancellations, open returns | Stock changes, refunds |
| Finance | View orders with customer details, record offline payments (sent to the storefront for confirmation), request refunds, sales and finance reports | Stock changes |
| Marketing | Products (view), sales reports (no customer details) | Orders, stock |

Roles can be given for all brands or one brand. Every permission is checked by the server; hidden buttons are only a convenience.

## Daily routine
1. **Overview → Needs attention.** Work top to bottom; each line has a button to the place to fix it.
2. **Orders → To process.** Open each order and press the large button at the top: *Start processing → Mark as picked → Mark as packed → Create shipment → Mark as dispatched*. Print a pick list for several orders from the Orders page (tick them, then *Print pick list*).
3. **Deliveries.** Inventory → Incoming stock → enter good / damaged / missing per line.
4. **Returns.** Record receipt, then inspection. Only units you approve go back on sale.
5. **Low stock.** Inventory → Low stock → create a purchase order.

## Adjusting stock
Inventory → *Adjust* on the size row. Choose what happened, enter the quantity, read the "On hand 21 → 26 · Available 15 → 20" preview, confirm. Every change is kept with your name, the time and the reason. Made a mistake? Press *Undo* on the green message within 10 minutes; later, record a stock correction.

## Words
On hand · Reserved · Available · Incoming · Damaged · Awaiting inspection · Reorder level — see *Help & glossary* in the app.

## When something looks wrong
| You see | Do |
|---|---|
| Red number on Orders | An order has a problem (not linked to stock, paid but no stock, delivery problem). Open it; the page says what to do and offers *Check again*. |
| "Not linked yet" on a product | Ask an inventory manager to link it on the product page. Until then no store can sell it. |
| A number on *Integration health* | Background jobs failed. Owners/operations managers press *Retry*; if it fails again, call the administrator with the message shown. |
| "This form expired" | Refresh the page and try again. |
| Cannot sign in | After 5 wrong passwords, wait 15 minutes. An owner can restore access in *Team & Permissions*. |
