<?php use Commerce\Hub\View;
$exp = fn (string $r) => $v->url('/reports/export', ['report' => $r, 'from' => $fromLabel, 'to' => $toLabel]);
$canSales = $v->can('reports.sales'); $canOps = $v->can('reports.operations'); ?>
<div class="page-head"><div><h1>Reports</h1><p><?= View::e($fromLabel) ?> to <?= View::e($toLabel) ?> · amounts are never added across currencies</p></div>
  <form class="inline-form" method="get" action="/reports"><label class="sr-only" for="rf">From</label><input class="input btn-sm" type="date" id="rf" name="from" value="<?= View::e($fromLabel) ?>"><label class="sr-only" for="rt">To</label><input class="input btn-sm" type="date" id="rt" name="to" value="<?= View::e($toLabel) ?>"><button class="btn btn-sm">Apply</button></form></div>

<?php if ($canSales): ?>
<section class="card">
  <div class="card-head"><div><h2>Sales by brand</h2><p>Gross order value = totals of paid orders including shipping and tax. Net sales = gross − refunds.</p></div><?php if ($sales): ?><a class="btn btn-sm" href="<?= $exp('sales') ?>">Export CSV</a><?php endif; ?></div>
  <?php if ($sales === []): ?><div class="empty"><h3>No paid orders in these dates</h3></div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Brand</th><th>Currency</th><th class="num">Orders</th><th class="num">Gross order value</th><th class="num">Discounts</th><th class="num">Refunds</th><th class="num">Net sales</th><th class="num">Tax</th><th class="num">Shipping</th></tr></thead>
  <tbody><?php foreach ($sales as $s): ?><tr><td><?= View::e($s['brand']) ?></td><td><?= View::e($s['currency']) ?></td><td class="num"><?= $s['orders'] ?></td><td class="num"><?= $v->money($s['gross'], $s['currency']) ?></td><td class="num"><?= $v->money($s['discounts'], $s['currency']) ?></td><td class="num"><?= $v->money($s['refunds'], $s['currency']) ?></td><td class="num"><b><?= $v->money($s['net'], $s['currency']) ?></b></td><td class="num"><?= $v->money($s['tax'], $s['currency']) ?></td><td class="num"><?= $v->money($s['shipping'], $s['currency']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<div class="grid-half">
  <section class="card"><div class="card-head"><h2>Orders over time</h2><?php if ($daily): ?><a class="btn btn-sm" href="<?= $exp('daily') ?>">Export CSV</a><?php endif; ?></div>
    <?php if ($daily === []): ?><div class="card-body muted">No orders in these dates.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Day</th><th class="num">Paid orders</th></tr></thead><tbody><?php foreach ($daily as $d): ?><tr><td><?= View::e($d['day']) ?></td><td class="num"><?= (int) $d['orders'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
  <section class="card"><div class="card-head"><div><h2>By destination country</h2><p>Shipping country on the order (not the shopper's detected location).</p></div><?php if ($countries): ?><a class="btn btn-sm" href="<?= $exp('countries') ?>">Export CSV</a><?php endif; ?></div>
    <?php if ($countries === []): ?><div class="card-body muted">No orders in these dates.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Country</th><th>Currency</th><th class="num">Orders</th><th class="num">Gross</th></tr></thead><tbody><?php foreach ($countries as $c): ?><tr><td><?= View::e($c['country']) ?></td><td><?= View::e($c['currency']) ?></td><td class="num"><?= (int) $c['orders'] ?></td><td class="num"><?= $v->money($c['gross'], $c['currency']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<?php endif; ?>

<?php if ($canOps): ?>
<div class="grid-half">
  <section class="card"><div class="card-head"><h2>Inventory now</h2><a class="btn btn-sm" href="<?= $exp('inventory') ?>">Export CSV</a></div><div class="card-body"><dl class="kv">
    <?php foreach ($inventory[0] as $k => $val): ?><dt><?= View::e(ucfirst(str_replace('_', ' ', $k))) ?></dt><dd class="num text-right"><?= number_format((int) $val) ?></dd><?php endforeach; ?></dl></div></section>
  <section class="card"><div class="card-head"><h2>Fulfilment</h2><a class="btn btn-sm" href="<?= $exp('fulfilment') ?>">Export CSV</a></div><div class="card-body"><dl class="kv">
    <dt>Awaiting processing</dt><dd class="num text-right"><?= (int) $fulfilment[0]['awaiting_processing'] ?></dd>
    <dt>Awaiting dispatch</dt><dd class="num text-right"><?= (int) $fulfilment[0]['awaiting_dispatch'] ?></dd>
    <dt>In transit</dt><dd class="num text-right"><?= (int) $fulfilment[0]['in_transit'] ?></dd>
    <dt>Average time to dispatch</dt><dd class="num text-right"><?= $fulfilment[0]['avg_hours_to_dispatch'] ? View::e($fulfilment[0]['avg_hours_to_dispatch']) . ' hours' : '—' ?></dd>
  </dl><p class="muted small mt-12">Delayed shipments are reported only once a carrier integration provides reliable delivery dates.</p></div></section>
  <section class="card"><div class="card-head"><h2>Stock adjustments</h2><?php if ($adjustments): ?><a class="btn btn-sm" href="<?= $exp('adjustments') ?>">Export CSV</a><?php endif; ?></div>
    <?php if ($adjustments === []): ?><div class="card-body muted">No adjustments in these dates.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Type</th><th class="num">Changes</th><th class="num">On hand</th><th class="num">Damaged</th></tr></thead><tbody><?php foreach ($adjustments as $a): ?><tr><td><?= View::e(\Commerce\Hub\Controllers\OverviewController::movementLabel($a['type'])) ?></td><td class="num"><?= (int) $a['movements'] ?></td><td class="num"><?= sprintf('%+d', $a['on_hand_change']) ?></td><td class="num"><?= sprintf('%+d', $a['damaged_change']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
  <section class="card"><div class="card-head"><h2>Returns</h2><?php if ($returns): ?><a class="btn btn-sm" href="<?= $exp('returns') ?>">Export CSV</a><?php endif; ?></div>
    <?php if ($returns === []): ?><div class="card-body muted">No returns in these dates.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Brand</th><th class="num">Returns</th><th class="num">Units</th><th class="num">Damaged</th></tr></thead><tbody><?php foreach ($returns as $r): ?><tr><td><?= View::e($r['brand']) ?></td><td class="num"><?= (int) $r['returns'] ?></td><td class="num"><?= (int) $r['units'] ?></td><td class="num"><?= (int) $r['damaged_units'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<?php endif; ?>
