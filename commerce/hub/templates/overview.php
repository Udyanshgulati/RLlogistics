<?php use Commerce\Hub\View; use Commerce\Hub\Controllers\OverviewController;
$done = count(array_filter($checklist, fn ($c) => $c[1]));
$rangeHint = ['today' => 'Placed today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Selected dates'][$range]; ?>
<div class="page-head">
  <div>
    <h1>Overview</h1>
    <p><?= $app->brandFilter() ? View::e($app->services->brands()[$app->brandFilter()]['name']) : 'All brands' ?> · Delhi warehouse</p>
  </div>
  <div class="actions">
    <nav class="segmented" aria-label="Date range">
      <?php foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $k => $l): ?><a href="/?range=<?= $k ?>" class="<?= $range === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?>
    </nav>
    <details class="range-pop">
      <summary class="btn btn-sm<?= $range === 'custom' ? ' is-on' : '' ?>">Custom dates</summary>
      <form method="get" action="/" class="pop stack">
        <input type="hidden" name="range" value="custom">
        <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= View::e($req->q('from')) ?>" required></div>
        <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= View::e($req->q('to')) ?>" required></div>
        <button class="btn btn-primary btn-sm">Apply</button>
      </form>
    </details>
  </div>
</div>

<?php if ($showOrders || $showStock): ?>
<div class="kpis kpis-6">
  <?php if ($showOrders): ?>
  <a class="kpi" href="/orders?from=<?= View::e($fromDate) ?>"><div class="k-label">New orders</div><div class="k-value"><?= $kpi['new'] ?></div><div class="k-hint"><?= $rangeHint ?></div></a>
  <a class="kpi<?= $kpi['to_process'] ? ' is-warn' : '' ?>" href="/orders?payment=paid&amp;fulfilment=unfulfilled"><div class="k-label">Orders to process</div><div class="k-value"><?= $kpi['to_process'] ?></div><div class="k-hint">Paid, not yet packed</div></a>
  <a class="kpi" href="/orders?fulfilment=packed"><div class="k-label">Ready to dispatch</div><div class="k-value"><?= $kpi['ready'] ?></div><div class="k-hint">Packed at the warehouse</div></a>
  <a class="kpi" href="/shipments?status=in_transit"><div class="k-label">In transit</div><div class="k-value"><?= $kpi['in_transit'] ?></div><div class="k-hint">With the carrier</div></a>
  <?php endif; ?>
  <?php if ($showStock): ?>
  <a class="kpi<?= $stock['low_stock'] ? ' is-warn' : '' ?>" href="/inventory?status=low"><div class="k-label">Low stock</div><div class="k-value"><?= $stock['low_stock'] ?></div><div class="k-hint">SKUs below reorder level</div></a>
  <a class="kpi<?= $stock['out_of_stock'] ? ' is-bad' : '' ?>" href="/inventory?status=out"><div class="k-label">Out of stock</div><div class="k-value"><?= $stock['out_of_stock'] ?></div><div class="k-hint">SKUs with nothing to sell</div></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($journey !== null):
    // The navy fill and amber marker show that parcels are between the warehouse and the carrier's next scan; never a live position. ?>
<section class="card route-card mb-16" aria-labelledby="route-h">
  <div class="card-head"><div><h2 id="route-h">Shipment journey</h2><p>Parcels by carrier status. Counts update with each scan; this is not live GPS.</p></div><a class="btn btn-sm" href="/shipments">All shipments</a></div>
  <div class="route stops-<?= $journey['problems'] > 0 ? 4 : 3 ?><?= $journey['in_transit'] > 0 ? ' has-transit' : '' ?>" data-route>
    <div class="route-line" aria-hidden="true"><span class="route-fill"></span><?php if ($journey['in_transit'] > 0): ?><span class="route-marker" title="Parcels in transit"></span><?php endif; ?></div>
    <ol class="route-stops">
      <li><a href="/orders?fulfilment=packed"><span class="node is-done"></span><span class="code">DEL</span><b><?= $journey['at_warehouse'] ?></b><span class="lbl">Packed at Delhi warehouse</span></a></li>
      <li><a href="/shipments?status=in_transit"><span class="node<?= $journey['in_transit'] ? ' is-live' : '' ?>"></span><span class="code">TRANSIT</span><b><?= $journey['in_transit'] ?></b><span class="lbl">With the carrier</span></a></li>
      <?php if ($journey['problems'] > 0): ?><li class="is-problem"><a href="/shipments?status=exception"><span class="node is-bad"></span><span class="code">EXCEPTION</span><b><?= $journey['problems'] ?></b><span class="lbl">Delivery problem</span></a></li><?php endif; ?>
      <li><a href="/shipments?status=delivered"><span class="node<?= $journey['delivered'] ? ' is-done' : '' ?>"></span><span class="code">DEST</span><b><?= $journey['delivered'] ?></b><span class="lbl">Delivered in the last 7 days</span></a></li>
    </ol>
  </div>
</section>
<?php endif; ?>

<div class="grid-2">
  <div class="stack">
    <section class="card" id="attention" aria-labelledby="att-h">
      <div class="card-head"><div><h2 id="att-h">Needs attention</h2><p>Each item links to where it is fixed.</p></div></div>
      <?php if ($attention === []): ?>
        <div class="all-clear"><span class="tick">✓</span><span>Nothing needs attention right now.</span></div>
      <?php else: ?>
        <ul class="attention">
          <?php foreach ($attention as [$n, $msg, $href, $label, $tone]): ?>
            <li class="tone-<?= $tone ?>"><span class="n"><?= $n ?></span><span class="msg"><?= View::e($msg) ?></span><a class="btn btn-sm" href="<?= View::e($href) ?>"><?= View::e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php if ($showOrders): ?>
    <section class="card" aria-labelledby="ro-h">
      <div class="card-head"><h2 id="ro-h">Recent orders</h2><a class="btn btn-sm btn-ghost" href="/orders">View all</a></div>
      <?php if ($recentOrders === []): ?><div class="empty"><h3>No orders yet</h3><p>Orders appear here as soon as a connected storefront sends them.</p></div>
      <?php else: ?><div class="table-wrap"><table class="table-compact"><thead><tr><th>Order</th><th>Brand</th><th>Destination</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>
        <?php foreach ($recentOrders as $o): ?><tr>
          <td class="nowrap"><a class="link" href="/orders/<?= (int) $o['id'] ?>">#<?= View::e($o['order_number']) ?></a><?php if ($o['attention']): ?> <span class="attn-dot" title="Needs attention"></span><?php endif; ?></td>
          <td><?= $v->brand($o['brand_id']) ?></td>
          <td class="mono small"><?= View::e($o['ship_country'] ?? '—') ?></td>
          <td><?= $v->badge($o['payment_status'] === 'paid' ? $o['fulfilment_status'] : $o['payment_status']) ?></td>
          <td class="num"><?= $v->money($o['total_minor'], $o['currency']) ?></td>
        </tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($health !== null): ?>
    <section class="card" aria-labelledby="ih-h">
      <div class="card-head"><h2 id="ih-h">Integration health</h2><a class="btn btn-sm btn-ghost" href="/health">Details</a></div>
      <ul class="feed health-list">
        <?php foreach ($health['stores'] as $st): ?><li><span class="w-full"><?= $v->brand($st['brand_id']) ?><span class="sub small muted"><?= $st['last_seen_at'] ? 'Last contact ' . $v->date($st['last_seen_at']) : 'No contact yet' ?></span></span><?= $v->badge($st['status']) ?></li><?php endforeach; ?>
        <li><span class="w-full">Background jobs<span class="sub small muted"><?= $health['waiting'] ?> waiting</span></span><?= $health['failed'] ? '<span class="badge badge-bad">' . $health['failed'] . ' failed</span>' : '<span class="badge badge-ok">No failures</span>' ?></li>
      </ul>
    </section>
    <?php endif; ?>
    <?php if ($flow !== null): ?>
    <section class="card" aria-labelledby="if-h">
      <div class="card-head"><div><h2 id="if-h">Inventory flow</h2><p>How stock moves through the warehouse.</p></div></div>
      <ol class="flow">
        <li><span class="flow-n"><?= number_format($flow['received']) ?></span><span class="flow-l">Received</span><span class="flow-s">last 7 days</span></li>
        <li class="is-key"><span class="flow-n"><?= number_format($flow['available']) ?></span><span class="flow-l">Available</span><span class="flow-s">to sell now</span></li>
        <li><span class="flow-n"><?= number_format($flow['reserved']) ?></span><span class="flow-l">Reserved</span><span class="flow-s">for open orders</span></li>
        <li><span class="flow-n"><?= number_format($flow['dispatched']) ?></span><span class="flow-l">Dispatched</span><span class="flow-s">last 7 days</span></li>
      </ol>
    </section>
    <section class="card" aria-labelledby="sm-h">
      <div class="card-head"><h2 id="sm-h">Recent stock movements</h2><a class="btn btn-sm btn-ghost" href="/inventory">Inventory</a></div>
      <?php if ($movements === []): ?><div class="card-body muted">No stock recorded yet.</div>
      <?php else: ?><ul class="feed moves">
        <?php foreach ($movements as $m): $d = (int) $m['d_on_hand'] ?: -(int) $m['d_reserved'] ?: (int) $m['d_damaged'] ?: (int) $m['d_quarantine']; ?>
          <li><span class="w-full"><a class="mono small link" href="/inventory/<?= (int) $m['item_id'] ?>"><?= View::e($m['sku']) ?></a><span class="sub small muted"><?= View::e(OverviewController::movementLabel($m['type'])) ?> · <span class="when"><?= $v->date($m['created_at']) ?></span></span></span><span class="num delta <?= $d >= 0 ? 'up' : 'down' ?>"><?= sprintf('%+d', $d) ?></span></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($done < count($checklist)): ?>
    <section class="card" aria-labelledby="chk-h">
      <div class="card-head"><div><h2 id="chk-h">Getting set up</h2><p><?= $done ?> of <?= count($checklist) ?> done</p></div></div>
      <div class="card-body"><progress class="w-full" max="<?= count($checklist) ?>" value="<?= $done ?>"><?= $done ?> of <?= count($checklist) ?></progress></div>
      <ul class="checklist">
        <?php foreach ($checklist as [$label, $ok, $href]): ?><li class="<?= $ok ? 'done' : '' ?>"><span class="box"><?= $ok ? '✓' : '' ?></span><a href="<?= View::e($href) ?>"><?= View::e($label) ?></a></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>
</div>
