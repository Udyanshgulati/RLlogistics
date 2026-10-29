<?php use Commerce\Hub\View; ?>
<div class="page-head"><div><div class="crumb"><a href="/inventory">Inventory</a> / Incoming</div><h1>Incoming stock</h1><p>Purchase orders and deliveries. Units count as available only once they are received.</p></div></div>
<div class="grid-2">
  <div class="stack">
  <?php if ($pos === []): ?>
    <div class="card"><div class="empty"><div class="glyph"><?= $v->icon('shipments') ?></div><h3>No open purchase orders</h3><p>Create one when you order stock from a supplier, so the team can see what is on the way.</p></div></div>
  <?php endif; ?>
  <?php foreach ($pos as $po): ?>
    <section class="card">
      <div class="card-head"><div><h2><?= View::e($po['po_number']) ?> · <?= View::e($po['supplier']) ?></h2><p><?= $po['expected_at'] ? 'Expected ' . $v->date($po['expected_at'], false) : 'No expected date' ?></p></div>
        <span class="gap-row"><?= $v->badge($po['status']) ?><?php if ($po['status'] === 'draft' && $v->can('purchasing.manage')): ?><form method="post" action="/inventory/incoming/<?= (int) $po['id'] ?>/ordered"><?= $v->csrf() ?><button class="btn btn-sm">Mark as ordered</button></form><?php endif; ?></span></div>
      <div class="table-wrap"><table>
        <thead><tr><th>SKU</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Still expected</th><th>Record delivery</th></tr></thead>
        <tbody><?php foreach ($lines[$po['id']] as $l): $open = (int) $l['qty_ordered'] - (int) $l['qty_received'] - (int) $l['qty_missing']; ?><tr>
          <td><span class="mono"><?= View::e($l['sku']) ?></span><span class="sub"><?= View::e($l['name']) ?></span></td>
          <td class="num"><?= (int) $l['qty_ordered'] ?></td>
          <td class="num"><?= (int) $l['qty_received'] ?><?= $l['qty_damaged'] ? ' <span class="sub">' . (int) $l['qty_damaged'] . ' damaged</span>' : '' ?></td>
          <td class="num"><b><?= $open ?></b><?= $l['qty_missing'] ? ' <span class="sub">' . (int) $l['qty_missing'] . ' not coming</span>' : '' ?></td>
          <td><?php if ($open > 0 && $po['status'] !== 'draft'): ?><form class="inline-form" method="post" action="/inventory/incoming/lines/<?= (int) $l['id'] ?>/receive"><?= $v->csrf() ?>
            <label class="sr-only" for="g<?= (int) $l['id'] ?>">Good</label><input class="input input-num btn-sm" id="g<?= (int) $l['id'] ?>" name="good" type="number" min="0" max="<?= $open ?>" placeholder="Good">
            <label class="sr-only" for="d<?= (int) $l['id'] ?>">Damaged</label><input class="input input-num btn-sm" id="d<?= (int) $l['id'] ?>" name="damaged" type="number" min="0" max="<?= $open ?>" placeholder="Damaged">
            <label class="sr-only" for="m<?= (int) $l['id'] ?>">Missing</label><input class="input input-num btn-sm" id="m<?= (int) $l['id'] ?>" name="missing" type="number" min="0" max="<?= $open ?>" placeholder="Missing">
            <button class="btn btn-sm">Receive</button></form><?php elseif ($open > 0): ?><span class="muted small">Mark as ordered first</span><?php else: ?><span class="muted small">Complete</span><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
    </section>
  <?php endforeach; ?>
  </div>
  <?php if ($v->can('purchasing.manage')): ?>
  <form class="card" method="post" action="/inventory/incoming">
    <?= $v->csrf() ?>
    <div class="card-head"><div><h2>New purchase order</h2><p>Saved as a draft until you mark it as ordered.</p></div></div>
    <div class="card-body stack">
      <div class="field"><label for="supplier_id">Supplier</label><select class="select" id="supplier_id" name="supplier_id"><option value="">New supplier…</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="supplier_name">New supplier name</label><input class="input" id="supplier_name" name="supplier_name" maxlength="160"><p class="help">Only needed when adding a supplier.</p></div>
      <div class="field"><label for="lines">Items</label><textarea class="input mono" id="lines" name="lines" rows="5" placeholder="MI-JER-001-M, 20&#10;MI-JER-001-L, 15" required></textarea><p class="help">One SKU and quantity per line.</p></div>
      <div class="field"><label for="expected_at">Expected delivery</label><input class="input" id="expected_at" name="expected_at" type="date"></div>
      <button class="btn btn-primary">Create purchase order</button>
    </div>
  </form>
  <?php endif; ?>
</div>
