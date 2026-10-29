<?php use Commerce\Hub\View;
$explain = [
    'stock.push' => 'Could not update the stock quantity on a storefront. The storefront may show an old number, but checkouts are still checked against central stock.',
    'notify.customer' => 'A customer email or message could not be sent.',
    'alert.stock' => 'A low-stock alert could not be sent to the team.',
    'store.order_action' => 'A cancellation or payment request could not be sent to the storefront.',
    'store.shipment' => 'The storefront was not told about a dispatch (tracking note / completed status).',
    'store.refund' => 'A refund request could not be completed by the storefront.',
];
$retry = $v->can('integrations.retry'); ?>
<div class="page-head"><div><h1>Integration health</h1><p>Connections to the storefronts and background jobs. Nothing here fails silently.</p></div>
  <?php if ($retry): ?><form method="post" action="/health/reconcile"><?= $v->csrf() ?><button class="btn">Run stock reconciliation</button></form><?php endif; ?></div>

<?php if ($reconcile): $app->session->remove('reconcile'); ?>
  <div class="flash <?= $reconcile['problems'] ? 'flash-error' : 'flash-success' ?>"><span class="msg"><?= $reconcile['problems'] ? count($reconcile['problems']) . ' stock records disagree with their history. Do not edit them; ask an administrator to investigate using the details below.' : 'Reconciliation passed: every stock balance matches its full movement history, and reserved stock matches open orders.' ?></span></div>
  <?php if ($reconcile['problems']): ?><div class="card mb-16"><div class="table-wrap"><table><thead><tr><th>Check</th><th>Item</th><th class="num">Expected</th><th class="num">Recorded</th></tr></thead><tbody><?php foreach ($reconcile['problems'] as $p): ?><tr><td><?= View::e($p['check']) ?></td><td><a class="link" href="/inventory/<?= (int) $p['inventory_item_id'] ?>">#<?= (int) $p['inventory_item_id'] ?></a></td><td class="num"><?= (int) $p['expected'] ?></td><td class="num"><?= (int) $p['actual'] ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="k-label">Background jobs waiting</div><div class="k-value"><?= $pending ?></div><div class="k-hint"><?= $oldestPending ? 'Oldest since ' . $v->date($oldestPending) : 'Queue is empty' ?></div></div>
  <div class="kpi<?= count($dead) ? ' is-bad' : '' ?>"><div class="k-label">Failed jobs</div><div class="k-value"><?= count($dead) ?></div><div class="k-hint">Stopped after repeated attempts</div></div>
  <div class="kpi<?= count($inbox) ? ' is-bad' : '' ?>"><div class="k-label">Storefront updates not processed</div><div class="k-value"><?= count($inbox) ?></div><div class="k-hint">Order events that failed</div></div>
  <a class="kpi<?= $unmapped ? ' is-warn' : '' ?>" href="/products?unlinked=1"><div class="k-label">Products without a stock link</div><div class="k-value"><?= $unmapped ?></div><div class="k-hint">Cannot be sold until linked</div></a>
</div>

<section class="card">
  <div class="card-head"><h2>Storefront connections</h2></div>
  <div class="table-wrap"><table><thead><tr><th>Storefront</th><th>Address</th><th>Status</th><th>Last contact</th></tr></thead><tbody>
    <?php foreach ($stores as $s): ?><tr><td><?= $v->brand($s['brand_id']) ?></td><td class="small"><?= View::e($s['base_url'] ?? 'Not set (awaiting the client)') ?></td><td><?= $v->badge($s['status']) ?></td><td class="small"><?= $s['last_seen_at'] ? $v->date($s['last_seen_at']) : 'Never' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="card-body small muted">Exchange rates last updated: <?= $lastRates ? $v->date($lastRates) : 'never (prices are shown in the store currency until rates are fetched)' ?>.</div>
</section>

<section class="card">
  <div class="card-head"><div><h2>Failed jobs</h2><p>Retried automatically with increasing delays; listed here once they stop.</p></div></div>
  <?php if ($dead === []): ?><div class="all-clear"><span class="tick">✓</span>No failed jobs.</div>
  <?php else: ?><div class="table-wrap"><table><thead><tr><th>What failed</th><th>Last error</th><th class="num">Attempts</th><th>Last try</th><th></th></tr></thead><tbody>
    <?php foreach ($dead as $d): ?><tr><td><?= View::e($explain[$d['topic']] ?? $d['topic']) ?></td><td class="small"><?= View::e($d['last_error']) ?></td><td class="num"><?= (int) $d['attempts'] ?></td><td class="small nowrap"><?= $v->date($d['updated_at']) ?></td>
      <td><?php if ($retry): ?><form method="post" action="/health/outbox/<?= (int) $d['id'] ?>/retry"><?= $v->csrf() ?><button class="btn btn-sm">Retry</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><div><h2>Storefront updates that could not be processed</h2><p>Duplicates are ignored automatically and never appear here.</p></div></div>
  <?php if ($inbox === []): ?><div class="all-clear"><span class="tick">✓</span>Every storefront update was processed.</div>
  <?php else: ?><div class="table-wrap"><table><thead><tr><th>Received</th><th>From</th><th>Problem</th><th class="num">Attempts</th><th></th></tr></thead><tbody>
    <?php foreach ($inbox as $i): ?><tr><td class="small nowrap"><?= $v->date($i['received_at']) ?></td><td class="small"><?= View::e($i['source']) ?></td><td class="small"><?= View::e($i['last_error']) ?></td><td class="num"><?= (int) $i['attempts'] ?></td>
      <td><?php if ($retry): ?><form method="post" action="/health/inbox/<?= (int) $i['id'] ?>/retry"><?= $v->csrf() ?><button class="btn btn-sm">Retry</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<?php if ($exceptions): ?><section class="card"><div class="card-head"><div><h2>Payment and stock exceptions</h2><p>Payments that arrived after their stock hold ended. Each needs a person to refund or reinstate.</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>Store order</th><th>Problem</th><th>When</th></tr></thead><tbody><?php foreach ($exceptions as $e): ?><tr><td><?= View::e($e['order_ref']) ?></td><td class="small"><?= View::e(['paid_after_expiry_no_stock' => 'Paid after the hold expired; stock no longer available', 'paid_after_release' => 'Paid after the order was cancelled', 'expired_store_unreachable' => 'Hold expired while the storefront could not be reached'][$e['exception']] ?? $e['exception']) ?></td><td class="small"><?= $v->date($e['updated_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
