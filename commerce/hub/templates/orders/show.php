<?php use Commerce\Hub\View;
$addr = json_decode((string) $o['shipping_address'], true) ?: [];
$bill = json_decode((string) $o['billing_address'], true) ?: [];
$primary = array_values(array_diff($actions, ['request_cancel', 'request_mark_paid']))[0] ?? null;
$steps = ['unfulfilled' => 0, 'processing' => 1, 'picked' => 2, 'packed' => 3, 'partially_shipped' => 4, 'shipped' => 4, 'delivered' => 5, 'cancelled' => -1];
$stepNow = $steps[$o['fulfilment_status']] ?? 0;
$draft = null; foreach ($shipments as $s) { if (in_array($s['status'], ['draft', 'label_created'], true)) { $draft = $s; } }
$undelivered = null; foreach ($shipments as $s) { if (in_array($s['status'], ['dispatched', 'in_transit', 'exception'], true)) { $undelivered = $s; } }
$fmtAddr = fn (array $a) => View::e(implode(', ', array_filter([trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')), $a['company'] ?? '', $a['address_1'] ?? '', $a['address_2'] ?? '', $a['city'] ?? '', $a['state'] ?? '', $a['postcode'] ?? '', $a['country'] ?? ''])));
$canFulfil = $v->can('orders.fulfil', $brand);
?>
<div class="page-head">
  <div><div class="crumb"><a href="/orders">Orders</a> / #<?= View::e($o['order_number']) ?></div>
    <h1>Order #<?= View::e($o['order_number']) ?></h1>
    <p class="gap-row"><?= $v->brand($o['brand_id']) ?> · <?= $v->date($o['placed_at']) ?> <?= $v->badge($o['payment_status']) ?><?= $v->badge($o['fulfilment_status']) ?></p></div>
  <div class="actions">
    <?php if (in_array('request_cancel', $actions, true) && $v->can('orders.request_cancel', $brand)): ?>
      <form method="post" action="/orders/<?= (int) $o['id'] ?>/action" class="inline-form" data-confirm="The storefront will cancel the order and refund through its payment provider if it was paid. Reserved stock is released once the storefront confirms." data-confirm-title="Cancel this order?" data-confirm-ok="Cancel order">
        <?= $v->csrf() ?><input type="hidden" name="action" value="request_cancel"><label class="sr-only" for="cr">Reason</label><input class="input btn-sm" id="cr" name="reason" placeholder="Reason for cancelling" required><button class="btn btn-sm btn-danger">Cancel order</button></form>
    <?php endif; ?>
    <?php if ($v->can('returns.open', $brand) && array_sum(array_column($lines, 'qty_shipped')) > 0): ?><a class="btn btn-sm" href="#return">Start a return</a><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="card">
      <?php if ($o['attention']): ?>
        <div class="next-action"><div><span class="label">Needs attention</span><b class="attn"><?= View::e(View::ATTENTION[$o['attention']] ?? $o['attention']) ?></b>
          <p class="muted small mt-6"><?= ['unmapped_item' => 'Link the storefront product to a SKU on the Products page, then check again.', 'paid_no_stock' => 'Restock the item (or cancel the order and refund), then check again.', 'paid_after_cancel' => 'The payment arrived after cancellation. Refund it through the storefront, or ask the customer to reorder.', 'delivery_exception' => 'Contact the carrier or customer, then record how it was resolved.'][$o['attention']] ?? '' ?></p></div>
          <?php if ($canFulfil): ?><?php if ($o['attention'] === 'delivery_exception'): ?>
            <form method="post" action="/orders/<?= (int) $o['id'] ?>/action" class="inline-form"><?= $v->csrf() ?><input type="hidden" name="action" value="clear_exception"><label class="sr-only" for="ce">How was it resolved?</label><input class="input btn-sm" id="ce" name="reason" placeholder="How was it resolved?" required><button class="btn btn-primary">Mark resolved</button></form>
          <?php elseif ($o['attention'] !== 'paid_after_cancel'): ?>
            <form method="post" action="/orders/<?= (int) $o['id'] ?>/action"><?= $v->csrf() ?><input type="hidden" name="action" value="recheck"><button class="btn btn-primary">Check again</button></form>
          <?php endif; ?><?php endif; ?>
        </div>
      <?php elseif ($primary && $canFulfil): ?>
        <div class="next-action"><div><span class="label">Next step</span><b><?= View::e(View::ACTIONS[$primary]) ?></b></div>
          <?php if (in_array($primary, ['start_processing', 'mark_picked', 'mark_packed'], true)): ?>
            <form method="post" action="/orders/<?= (int) $o['id'] ?>/action"><?= $v->csrf() ?><input type="hidden" name="action" value="<?= $primary ?>"><button class="btn btn-primary btn-lg"><?= View::e(View::ACTIONS[$primary]) ?></button></form>
          <?php elseif ($primary === 'create_shipment'): ?><a class="btn btn-primary btn-lg" href="#ship">Create shipment</a>
          <?php elseif ($primary === 'mark_dispatched' && $draft): ?>
            <form method="post" action="/shipments/<?= (int) $draft['id'] ?>/dispatch" class="inline-form" data-confirm="Stock leaves the ledger now and the customer is notified with the tracking number." data-confirm-title="Mark as dispatched?" data-confirm-ok="Dispatch"><?= $v->csrf() ?>
              <?php if (!$draft['tracking_number']): ?><label class="sr-only" for="dt">Tracking number</label><input class="input" id="dt" name="tracking" placeholder="Tracking number" required><?php endif; ?>
              <button class="btn btn-primary btn-lg">Mark as dispatched</button></form>
          <?php elseif ($primary === 'mark_delivered' && $undelivered): ?>
            <form method="post" action="/shipments/<?= (int) $undelivered['id'] ?>/delivered" data-confirm="Use this only when the carrier has not confirmed delivery but you know the parcel arrived. It is recorded as confirmed by staff." data-confirm-title="Mark as delivered?"><?= $v->csrf() ?><button class="btn">Mark as delivered</button></form>
          <?php endif; ?>
        </div>
      <?php elseif ($o['payment_status'] === 'pending'): ?>
        <div class="next-action"><div><span class="label">Waiting</span><b>Awaiting payment from the customer</b><p class="muted small mt-6">Stock is held<?= $reservation && $reservation['expires_at'] ? ' until ' . $v->date($reservation['expires_at']) . ' (then checked with the storefront)' : '' ?>.</p></div>
          <?php if (in_array('request_mark_paid', $actions, true) && $v->can('payments.mark_paid', $brand)): ?>
            <form method="post" action="/orders/<?= (int) $o['id'] ?>/action" class="inline-form" data-confirm="This asks the storefront to mark the order as paid. Only do this when the money has arrived by another route, such as a bank transfer you have checked." data-confirm-title="Record an offline payment?"><?= $v->csrf() ?><input type="hidden" name="action" value="request_mark_paid"><label class="sr-only" for="pr">Payment reference</label><input class="input btn-sm" id="pr" name="reason" placeholder="Bank reference" required><button class="btn btn-sm">Record offline payment</button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($stepNow >= 0): ?>
      <div class="card-body"><ol class="steps" aria-label="Fulfilment progress">
        <?php $labels = ['Paid', 'Processing', 'Picked', 'Packed', 'Shipped', 'Delivered'];
          $flags = array_map(fn ($i) => $i === 0 ? ($o['payment_status'] === 'paid' || $stepNow > 0) : $stepNow >= $i, array_keys($labels));
          $nowAt = array_search(false, $flags, true);
          foreach ($labels as $i => $label): ?>
          <li class="<?= $flags[$i] ? 'done' : ($i === $nowAt ? 'now' : '') ?>"><?= $label ?></li>
        <?php endforeach; ?>
      </ol></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Items</h2><?php if ($reservation): ?><span class="gap-row small muted">Stock <?= $v->badge($reservation['state']) ?></span><?php endif; ?></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Size</th><th>SKU</th><th class="num">Qty</th><th class="num">Shipped</th><th class="num">Price</th><th class="num">Total</th></tr></thead>
        <tbody><?php foreach ($lines as $l): ?><tr>
          <td><?= View::e($l['name']) ?><?php if ($l['inventory_item_id'] === null): ?><span class="sub attn">Not linked to stock</span><?php endif; ?></td>
          <td><?= View::e($l['option_label'] ?? '—') ?></td>
          <td class="mono"><?php if ($l['inventory_item_id']): ?><a class="link" href="/inventory/<?= (int) $l['inventory_item_id'] ?>"><?= View::e($l['central_sku']) ?></a><?php else: ?><?= View::e($l['sku'] ?? '—') ?><?php endif; ?></td>
          <td class="num"><?= (int) $l['qty'] ?></td><td class="num"><?= (int) $l['qty_shipped'] ?></td>
          <td class="num"><?= $v->money($l['unit_price_minor'], $o['currency']) ?></td><td class="num"><?= $v->money($l['line_total_minor'], $o['currency']) ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
      <div class="card-body"><dl class="kv">
        <dt>Subtotal</dt><dd class="num text-right"><?= $v->money($o['subtotal_minor'], $o['currency']) ?></dd>
        <?php if ($o['discount_minor']): ?><dt>Discounts</dt><dd class="num text-right">−<?= $v->money($o['discount_minor'], $o['currency']) ?></dd><?php endif; ?>
        <dt>Shipping</dt><dd class="num text-right"><?= $v->money($o['shipping_minor'], $o['currency']) ?></dd>
        <dt>Taxes and duties</dt><dd class="num text-right"><?= $v->money($o['tax_minor'], $o['currency']) ?></dd>
        <dt><b>Total</b></dt><dd class="num text-right"><b><?= $v->money($o['total_minor'], $o['currency']) ?></b> <span class="muted small">charged in <?= View::e($o['currency']) ?><?= $o['display_currency'] && $o['display_currency'] !== $o['currency'] ? ', shown to the customer in ' . View::e($o['display_currency']) : '' ?></span></dd>
        <?php if ($o['refunded_minor']): ?><dt>Refunded</dt><dd class="num text-right">−<?= $v->money($o['refunded_minor'], $o['currency']) ?></dd><?php endif; ?>
        <dt>Payment</dt><dd><?= $v->badge($o['payment_status']) ?> <span class="muted small"><?= View::e($o['payment_gateway'] ?? '') ?><?= $o['transaction_ref'] ? ' · ' . View::e($o['transaction_ref']) : '' ?></span></dd>
      </dl></div>
    </section>

    <?php if ($shipments || ($primary === 'create_shipment' && $canFulfil)): ?>
    <section class="card" id="ship">
      <div class="card-head"><div><h2>Shipments</h2><p>Shipped from the Delhi warehouse.</p></div></div>
      <?php foreach ($shipments as $s): ?>
        <div class="card-body">
          <div class="gap-row"><b><?= View::e($s['carrier']) ?> · <?= View::e($s['service']) ?></b><?= $v->badge($s['status']) ?><span class="mono"><?= View::e($s['tracking_number'] ?? '') ?></span></div>
          <p class="muted small mt-6">Created <?= $v->date($s['created_at']) ?><?= $s['dispatched_at'] ? ' · dispatched ' . $v->date($s['dispatched_at']) : '' ?><?= $s['delivered_at'] ? ' · delivered ' . $v->date($s['delivered_at']) : '' ?><?= $s['estimated_delivery'] ? ' · estimated delivery ' . $v->date($s['estimated_delivery'], false) : '' ?></p>
          <?php if ($tracking[$s['id']]): ?><ul class="timeline"><?php foreach ($tracking[$s['id']] as $t): ?><li class="<?= $t['status'] === 'exception' ? 't-bad' : ($t['status'] === 'delivered' ? 't-good' : '') ?>"><time><?= $v->date($t['occurred_at']) ?></time><?= View::e($t['description'] ?? $t['status']) ?><?= $t['location'] ? ' · ' . View::e($t['location']) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($primary === 'create_shipment' && $canFulfil): $remaining = array_filter($lines, fn ($l) => (int) $l['qty'] > (int) $l['qty_shipped']); ?>
      <form class="card-body stack" method="post" action="/orders/<?= (int) $o['id'] ?>/shipments">
        <?= $v->csrf() ?>
        <div class="notice notice-info">Live carrier booking is not connected yet. Book the shipment in the carrier's own portal, then record the carrier, service and tracking number here.</div>
        <div class="form-grid">
          <div class="field"><label for="carrier">Carrier</label><input class="input" id="carrier" name="carrier" list="carriers" required placeholder="e.g. DHL Express"><datalist id="carriers"><option>DHL Express</option><option>FedEx</option><option>UPS</option><option>Aramex</option></datalist></div>
          <div class="field"><label for="service">Service</label><input class="input" id="service" name="service" required placeholder="As named by the carrier"></div>
          <div class="field"><label for="tracking">Tracking number</label><input class="input mono" id="tracking" name="tracking" placeholder="Can be added at dispatch"></div>
        </div>
        <details><summary class="link">Ship only some items (partial shipment)</summary>
          <input type="hidden" name="partial" value="0" id="partial-flag">
          <div class="table-wrap mt-12"><table><thead><tr><th>Item</th><th class="num">Left to ship</th><th class="num">In this shipment</th></tr></thead><tbody>
          <?php foreach ($remaining as $l): $left = (int) $l['qty'] - (int) $l['qty_shipped']; ?><tr><td><?= View::e($l['name']) ?> <span class="mono small"><?= View::e($l['central_sku'] ?? '') ?></span></td><td class="num"><?= $left ?></td><td class="num"><input class="input input-num btn-sm" name="qty[<?= (int) $l['id'] ?>]" type="number" min="0" max="<?= $left ?>" value="<?= $left ?>" aria-label="Units of <?= View::e($l['name']) ?>"></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <label class="gap-row mt-12"><input type="checkbox" name="partial" value="1"> Use these quantities</label>
        </details>
        <div><button class="btn btn-primary">Create shipment</button></div>
      </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($v->can('returns.open', $brand) && array_sum(array_column($lines, 'qty_shipped')) > 0): ?>
    <form class="card" id="return" method="post" action="/orders/<?= (int) $o['id'] ?>/returns">
      <?= $v->csrf() ?>
      <div class="card-head"><div><h2>Start a return or exchange</h2><p>Items go to “awaiting inspection” when they arrive, not straight back to sale.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Item</th><th class="num">Shipped</th><th class="num">Returning</th></tr></thead><tbody>
        <?php foreach ($lines as $l): if ((int) $l['qty_shipped'] === 0) continue; ?><tr><td><?= View::e($l['name']) ?></td><td class="num"><?= (int) $l['qty_shipped'] ?></td><td class="num"><input class="input input-num btn-sm" type="number" name="qty[<?= (int) $l['id'] ?>]" min="0" max="<?= (int) $l['qty_shipped'] ?>" value="0" aria-label="Units of <?= View::e($l['name']) ?> returning"></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <div class="card-body form-grid">
        <div class="field"><label for="rk">Type</label><select class="select" id="rk" name="kind"><option value="return">Return</option><option value="exchange">Exchange</option></select></div>
        <div class="field"><label for="rr">Reason</label><input class="input" id="rr" name="reason" required placeholder="e.g. Too small"></div>
        <div class="span-2"><button class="btn">Open return</button></div>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>Customer</h2></div>
      <div class="card-body">
        <?php if ($pii): ?><dl class="kv">
          <dt>Name</dt><dd><?= View::e($o['customer_name'] ?? '—') ?></dd>
          <dt>Email</dt><dd><?= View::e($o['customer_email'] ?? '—') ?></dd>
          <dt>Phone</dt><dd><?= View::e($o['customer_phone'] ?? '—') ?></dd>
          <dt>Ship to</dt><dd><?= $fmtAddr($addr) ?: '—' ?></dd>
          <?php if ($bill && $fmtAddr($bill) !== $fmtAddr($addr)): ?><dt>Billing</dt><dd><?= $fmtAddr($bill) ?></dd><?php endif; ?>
          <dt>Destination</dt><dd><?= View::e($o['ship_country'] ?? '—') ?></dd>
          <dt>Shipping chosen</dt><dd><?= View::e($o['shipping_service_requested'] ?? '—') ?></dd>
        </dl>
        <?php else: ?><dl class="kv"><dt>Destination</dt><dd><?= View::e($o['ship_country'] ?? '—') ?></dd><dt>Shipping chosen</dt><dd><?= View::e($o['shipping_service_requested'] ?? '—') ?></dd></dl><p class="muted small mt-12">Your role does not include customer contact details.</p><?php endif; ?>
      </div>
    </section>
    <?php if ($returns): ?><section class="card"><div class="card-head"><h2>Returns</h2></div><ul class="feed"><?php foreach ($returns as $r): ?><li><a class="link w-full" href="/returns/<?= (int) $r['id'] ?>"><?= View::e($r['rma_number']) ?></a><?= $v->badge($r['status']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <section class="card">
      <div class="card-head"><h2>Timeline</h2></div>
      <ul class="timeline"><?php foreach (array_reverse($events) as $e): ?>
        <li class="<?= str_contains($e['type'], 'fail') || str_contains($e['type'], 'short') || $e['type'] === 'tracking.exception' ? 't-bad' : (in_array($e['type'], ['payment.paid', 'shipment.dispatched', 'tracking.delivered', 'attention.resolved', 'refund.succeeded'], true) ? 't-good' : '') ?>"><time><?= $v->date($e['created_at']) ?></time><?= View::e($e['message']) ?></li>
      <?php endforeach; ?></ul>
    </section>
  </div>
</div>
