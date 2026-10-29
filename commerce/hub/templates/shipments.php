<?php use Commerce\Hub\View; ?>
<div class="page-head"><div><h1>Shipments</h1><p>Parcels sent from the Delhi warehouse.</p></div></div>
<div class="card">
  <form class="toolbar" method="get" action="/shipments">
    <label class="sr-only" for="ss">Status</label><select class="select" id="ss" name="status"><option value="">Any status</option><?php foreach (['draft', 'label_created', 'dispatched', 'in_transit', 'exception', 'delivered'] as $s): ?><option value="<?= $s ?>"<?= $req->q('status') === $s ? ' selected' : '' ?>><?= View::STATUS[$s][0] ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="sc">Carrier</label><select class="select" id="sc" name="carrier"><option value="">Any carrier</option><?php foreach ($carriers as $c): ?><option<?= $req->q('carrier') === $c ? ' selected' : '' ?>><?= View::e($c) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="sco">Country</label><input class="input" id="sco" name="country" value="<?= View::e($req->q('country')) ?>" placeholder="Country (e.g. GB)" maxlength="2">
    <button class="btn">Filter</button>
  </form>
  <?php if ($rows === []): ?><div class="empty"><div class="glyph"><?= $v->icon('shipments') ?></div><h3>No shipments</h3><p>Shipments are created from an order once it is packed.</p></div>
  <?php else: ?><div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Brand</th><th>Destination</th><th>Carrier</th><th>Service</th><th>Status</th><th>Tracking</th><th>Label</th><th class="num">Shipping cost</th><th>Dispatched</th><th>Estimated delivery</th></tr></thead>
    <tbody><?php foreach ($rows as $s): ?><tr>
      <td><a class="link" href="/orders/<?= (int) $s['order_id'] ?>">#<?= View::e($s['order_number']) ?></a></td><td><?= $v->brand($s['brand_id']) ?></td><td><?= View::e($s['ship_country'] ?? '—') ?></td>
      <td><?= View::e($s['carrier']) ?></td><td class="small"><?= View::e($s['service']) ?></td><td><?= $v->badge($s['status']) ?></td>
      <td class="mono small"><?= View::e($s['tracking_number'] ?? '—') ?></td><td class="small"><?= $s['label_ref'] ? 'Created' : '<span class="muted">From carrier portal</span>' ?></td>
      <td class="num"><?= $s['cost_minor'] !== null ? $v->money($s['cost_minor'], $s['cost_currency']) : '<span class="muted">—</span>' ?></td>
      <td class="small nowrap"><?= $v->date($s['dispatched_at']) ?></td>
      <td class="small nowrap"><?= $s['estimated_delivery'] ? $v->date($s['estimated_delivery'], false) . ' <span class="muted">(' . View::e($s['estimate_source']) . ')</span>' : '<span class="muted">Not provided</span>' ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
