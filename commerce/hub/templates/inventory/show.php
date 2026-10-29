<?php use Commerce\Hub\View; use Commerce\Hub\Controllers\OverviewController; ?>
<div class="page-head">
  <div>
    <div class="crumb"><a href="/inventory">Inventory</a> / <?= View::e($item['sku']) ?></div>
    <h1><?= View::e($item['name']) ?><?= $item['option_label'] ? ' · ' . View::e($item['option_label']) : '' ?></h1>
    <p class="gap-row"><span class="mono"><?= View::e($item['sku']) ?></span><?= $v->badge($item['status']) ?><?= $v->badge($item['sharing']) ?></p>
  </div>
  <div class="actions">
    <a class="btn" href="/products/<?= (int) $item['product_id'] ?>">Product details</a>
    <a class="btn btn-primary" href="/inventory/<?= (int) $item['id'] ?>/adjust">Adjust stock</a>
  </div>
</div>

<div class="levels mb-16">
  <div class="is-key"><div class="l">Available <span class="tip" title="On hand − reserved − damaged − awaiting inspection. This is what the storefronts can sell.">?</span></div><div class="v"><?= $item['available'] ?></div></div>
  <div><div class="l">On hand <span class="tip" title="Every unit physically at the warehouse.">?</span></div><div class="v"><?= $item['on_hand'] ?></div></div>
  <div><div class="l">Reserved <span class="tip" title="Held for open orders, not yet dispatched.">?</span></div><div class="v"><?= $item['reserved'] ?></div></div>
  <div><div class="l">Damaged · Awaiting inspection</div><div class="v"><?= $item['damaged'] ?> · <?= $item['quarantine'] ?></div></div>
  <div><div class="l">Incoming <span class="tip" title="On purchase orders. Not counted as available until received.">?</span></div><div class="v"><?= $item['incoming'] ?></div></div>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><div><h2>Stock history</h2><p>Every change, newest first. Nothing is ever overwritten.</p></div></div>
    <?php if ($history === []): ?><div class="empty"><h3>No changes yet</h3></div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Change</th><th class="num">On hand</th><th class="num">Reserved</th><th class="num">Available</th><th>By</th><th class="hide-sm">Reason / reference</th></tr></thead>
      <tbody><?php foreach ($history as $m):
        $before = (int) $m['on_hand_after'] - (int) $m['d_on_hand'];
        $availAfter = (int) $m['on_hand_after'] - (int) $m['reserved_after'] - (int) $m['damaged_after'] - (int) $m['quarantine_after']; ?>
        <tr>
          <td class="nowrap small"><?= $v->date($m['created_at']) ?></td>
          <td><?= View::e(OverviewController::movementLabel($m['type'])) ?></td>
          <td class="num"><?= $m['d_on_hand'] ? $before . ' → <b>' . (int) $m['on_hand_after'] . '</b>' : '<span class="qty-zero">' . (int) $m['on_hand_after'] . '</span>' ?></td>
          <td class="num"><?= $m['d_reserved'] ? sprintf('%+d', $m['d_reserved']) : '<span class="qty-zero">—</span>' ?></td>
          <td class="num"><?= $availAfter ?></td>
          <td class="small nowrap"><?= View::e(preg_replace(['/^user:\d+$/', '/^store:\d+$/', '/^system.*$/'], ['Staff', 'Storefront', 'Automatic'], $m['actor'])) ?></td>
          <td class="small hide-sm"><?= View::e($m['reason'] ?? '') ?><?= $m['ref_type'] === 'shipment' ? ' Shipment' : '' ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </section>
  <div class="stack">
    <section class="card">
      <div class="card-head"><div><h2>Sold on</h2><p>Storefront products drawing from this stock.</p></div></div>
      <?php if ($listings === []): ?><div class="card-body"><p class="muted">Not linked to any storefront yet, so no store can sell it. Link it from <a class="link" href="/products/<?= (int) $item['product_id'] ?>">the product page</a>.</p></div>
      <?php else: ?><ul class="feed"><?php foreach ($listings as $l): ?><li><span class="w-full"><?= $v->brand($l['brand_id']) ?><span class="sub small muted">Storefront product #<?= (int) $l['wc_product_id'] ?><?= $l['wc_variation_id'] ? ' · variation #' . (int) $l['wc_variation_id'] : '' ?></span></span><?= $v->badge($l['status'] === 'mapped' ? 'connected' : 'not_connected') ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2>Reserved for</h2></div>
      <?php if ($reservations === []): ?><div class="card-body"><p class="muted">No open orders hold this stock.</p></div>
      <?php else: ?><ul class="feed"><?php foreach ($reservations as $r): ?><li><span class="w-full"><?php if ($r['order_id']): ?><a class="link" href="/orders/<?= (int) $r['order_id'] ?>">Order #<?= View::e($r['order_number']) ?></a><?php else: ?>Checkout in progress<?php endif; ?> · <?= (int) $r['qty'] - (int) $r['fulfilled_qty'] - (int) $r['released_qty'] ?> units</span><?= $v->badge($r['state']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php if ($incomingLines): ?><section class="card"><div class="card-head"><h2>Incoming</h2></div><ul class="feed"><?php foreach ($incomingLines as $l): ?><li><span class="w-full"><?= View::e($l['po_number']) ?> · <?= (int) $l['open_qty'] ?> units</span><span class="muted small"><?= $l['expected_at'] ? 'Expected ' . $v->date($l['expected_at'], false) : 'No date' ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if ($v->can('products.manage')): ?>
    <section class="card"><div class="card-head"><div><h2>Reorder level</h2><p>Show as Low stock when available drops below this.</p></div></div>
      <form class="card-body inline-form" method="post" action="/inventory/<?= (int) $item['id'] ?>/reorder"><?= $v->csrf() ?><label class="sr-only" for="reorder">Reorder level</label><input class="input input-num" id="reorder" name="reorder_level" type="number" min="0" value="<?= (int) $item['reorder_level'] ?>"><button class="btn">Save</button></form>
    </section><?php endif; ?>
  </div>
</div>
