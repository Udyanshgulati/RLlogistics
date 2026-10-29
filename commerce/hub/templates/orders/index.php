<?php use Commerce\Hub\View; $q = $req->query; $filtered = array_diff_key(array_filter($q, fn ($x) => $x !== ''), ['page' => 1]) !== []; ?>
<div class="page-head">
  <div><h1>Orders</h1><p>Every order from all three storefronts.</p></div>
  <div class="actions"><?php if ($v->can('orders.fulfil')): ?><a class="btn" id="picklist-btn" href="/orders/picklist">Print pick list</a><?php endif; ?></div>
</div>
<div class="card">
  <div class="presets">
    <a class="preset<?= !$filtered ? ' on' : '' ?>" href="/orders">All</a>
    <a class="preset<?= ($q['attention'] ?? '') === '1' ? ' on' : '' ?>" href="/orders?attention=1">Needs attention</a>
    <a class="preset<?= ($q['fulfilment'] ?? '') === 'unfulfilled' && ($q['payment'] ?? '') === 'paid' ? ' on' : '' ?>" href="/orders?payment=paid&amp;fulfilment=unfulfilled">To process</a>
    <a class="preset<?= ($q['fulfilment'] ?? '') === 'packed' ? ' on' : '' ?>" href="/orders?fulfilment=packed">Ready to dispatch</a>
    <a class="preset<?= ($q['payment'] ?? '') === 'pending' ? ' on' : '' ?>" href="/orders?payment=pending">Awaiting payment</a>
    <?php foreach ($views as $sv): ?><a class="preset" href="/orders?<?= View::e($sv['query']) ?>"><?= View::e($sv['name']) ?></a><?php endforeach; ?>
  </div>
  <form class="toolbar" method="get" action="/orders">
    <label class="sr-only" for="oq">Search</label><input class="input grow" id="oq" name="q" value="<?= View::e($req->q('q')) ?>" placeholder="Order number or SKU">
    <label class="sr-only" for="op">Payment</label><select class="select" id="op" name="payment"><option value="">Any payment</option><?php foreach (['pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'] as $s): ?><option value="<?= $s ?>"<?= $req->q('payment') === $s ? ' selected' : '' ?>><?= View::STATUS[$s][0] ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="of">Fulfilment</label><select class="select" id="of" name="fulfilment"><option value="">Any fulfilment</option><?php foreach (['unfulfilled', 'processing', 'picked', 'packed', 'partially_shipped', 'shipped', 'delivered', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= $req->q('fulfilment') === $s ? ' selected' : '' ?>><?= View::STATUS[$s][0] ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="oc">Country</label><select class="select" id="oc" name="country"><option value="">Any country</option><?php foreach ($countries as $c): ?><option<?= $req->q('country') === $c ? ' selected' : '' ?>><?= View::e($c) ?></option><?php endforeach; ?></select>
    <?php if ($services): ?><label class="sr-only" for="os">Shipping service</label><select class="select" id="os" name="service"><option value="">Any shipping</option><?php foreach ($services as $s): ?><option<?= $req->q('service') === $s ? ' selected' : '' ?>><?= View::e($s) ?></option><?php endforeach; ?></select><?php endif; ?>
    <label class="sr-only" for="ofrom">From</label><input class="input" type="date" id="ofrom" name="from" value="<?= View::e($req->q('from')) ?>">
    <label class="sr-only" for="oto">To</label><input class="input" type="date" id="oto" name="to" value="<?= View::e($req->q('to')) ?>">
    <?php if ($req->q('attention')): ?><input type="hidden" name="attention" value="1"><?php endif; ?>
    <button class="btn">Filter</button>
    <?php if ($filtered): ?><a class="btn btn-ghost" href="/orders">Clear</a><span class="spacer"></span><?= $v->partial('partials/save-view', ['page' => 'orders', 'req' => $req]) ?><?php endif; ?>
  </form>
  <?php if ($orders === []): ?>
    <div class="empty"><div class="glyph"><?= $v->icon('orders') ?></div>
      <?php if ($filtered): ?><h3>No orders match these filters</h3><p>Try widening the dates or clearing a filter.</p><a class="btn" href="/orders">Clear filters</a>
      <?php else: ?><h3>No orders yet</h3><p>Orders appear here automatically once a storefront is connected. Connecting a storefront is set up under Settings.</p><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><?php if ($v->can('orders.fulfil')): ?><th><label class="sr-only" for="pick-all">Select all</label><input type="checkbox" id="pick-all" data-check-all="pick"></th><?php endif; ?><th>Order</th><th>Placed</th><th class="hide-sm">Items</th><th>Status</th><th class="hide-sm">Ship to</th><th class="num">Total</th><th>Next step</th></tr></thead>
    <tbody><?php foreach ($orders as $o): ?><tr>
      <?php if ($v->can('orders.fulfil')): ?><td><input type="checkbox" name="pick" value="<?= (int) $o['id'] ?>" aria-label="Select order <?= View::e($o['order_number']) ?>"></td><?php endif; ?>
      <td class="nowrap"><a class="link" href="/orders/<?= (int) $o['id'] ?>">#<?= View::e($o['order_number']) ?></a><span class="sub"><?= $v->brand($o['brand_id']) ?></span><?php if ($canPii): ?><span class="sub"><?= $v->can('orders.view_customer', (int) $o['brand_id']) ? View::e($o['customer_name'] ?? '—') : 'Customer hidden' ?></span><?php endif; ?><?php if ($o['attention']): ?><span class="sub attn">● <?= View::e(View::ATTENTION[$o['attention']] ?? 'Needs attention') ?></span><?php endif; ?></td>
      <td class="nowrap small"><?= $v->date($o['placed_at'], short: true) ?></td>
      <td class="hide-sm small items-cell"><?php $its = explode("\n", (string) $o['items']); foreach (array_slice($its, 0, 2) as $it): ?><span class="mono nowrap item-line"><?= View::e($it) ?></span><?php endforeach; ?><span class="sub"><?= count($its) > 2 ? '+' . (count($its) - 2) . ' more · ' : '' ?><?= (int) $o['units'] ?> unit<?= (int) $o['units'] === 1 ? '' : 's' ?></span></td>
      <td class="status-stack"><?= $v->badge($o['payment_status']) ?><?= $o['payment_status'] === 'paid' || $o['fulfilment_status'] !== 'unfulfilled' ? $v->badge($o['fulfilment_status']) : '' ?></td>
      <td class="hide-sm small nowrap"><b class="mono"><?= View::e($o['ship_country'] ?? '—') ?></b> · <?= View::e($o['shipped_with'] ?? $o['shipping_service_requested'] ?? '—') ?><?php if ($o['tracking']): ?><span class="sub mono"><?= View::e($o['tracking']) ?></span><?php endif; ?></td>
      <td class="num"><?= $v->money($o['total_minor'], $o['currency']) ?></td>
      <td class="nowrap small"><?= $o['next'] ? View::e(View::ACTIONS[$o['next']] ?? $o['next']) : '<span class="muted">—</span>' ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= $v->partial('partials/pager', compact('total', 'page', 'perPage', 'req')) ?>
  <?php endif; ?>
</div>
