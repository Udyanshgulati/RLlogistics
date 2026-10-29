<?php use Commerce\Hub\View; ?>
<div class="page-head"><div><div class="crumb no-print"><a href="/orders">Orders</a> / Pick list</div><h1>Pick list</h1><p><?= $count ?> order<?= $count === 1 ? '' : 's' ?> · printed <?= $v->date($app->clock->now()->format('Y-m-d H:i:s')) ?> · Delhi warehouse</p></div>
  <div class="actions no-print"><a class="btn" href="/orders?fulfilment=processing">Back to orders</a><button class="btn btn-primary" type="button" data-print>Print</button></div></div>
<div class="card">
  <?php if ($rows === []): ?><div class="empty"><h3>Nothing to pick</h3><p>Orders appear here after “Start processing”. Select orders on the Orders page to print a list for just those.</p></div>
  <?php else: ?><div class="table-wrap"><table>
    <thead><tr><th>✓</th><th>SKU</th><th>Product</th><th>Size</th><th class="num">Qty</th><th>Order</th><th>Brand</th><th>Location</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr><td>☐</td><td class="mono"><b><?= View::e($r['sku']) ?></b></td><td><?= View::e($r['name']) ?></td><td><?= View::e($r['option_label'] ?? '—') ?></td><td class="num"><b><?= (int) $r['qty'] ?></b></td><td>#<?= View::e($r['order_number']) ?></td><td><?= View::e($r['brand']) ?></td><td class="muted">Not configured</td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
