<?php use Commerce\Hub\View; ?>
<div class="page-head"><div><h1>Returns &amp; exchanges</h1><p>Start a return from the order page. Items are inspected before they go back on sale.</p></div></div>
<div class="card">
  <div class="presets"><?php foreach (['' => 'All', 'requested' => 'Awaiting items', 'received' => 'Awaiting inspection', 'inspected' => 'Inspected', 'closed' => 'Closed'] as $k => $l): ?><a class="preset<?= $status === $k ? ' on' : '' ?>" href="/returns<?= $k ? '?status=' . $k : '' ?>"><?= $l ?></a><?php endforeach; ?></div>
  <div class="mt-12"></div>
  <?php if ($rows === []): ?><div class="empty"><div class="glyph"><?= $v->icon('returns') ?></div><h3>No returns</h3><p>Open a return from a shipped order. You will be guided through receiving and inspecting the items.</p></div>
  <?php else: ?><div class="table-wrap"><table>
    <thead><tr><th>Return</th><th>Order</th><th>Brand</th><th>Type</th><th class="num">Units</th><th>Reason</th><th>Status</th><th>Refund</th><th>Opened</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><a class="link" href="/returns/<?= (int) $r['id'] ?>"><?= View::e($r['rma_number']) ?></a></td><td><a class="link" href="/orders/<?= (int) $r['order_id'] ?>">#<?= View::e($r['order_number']) ?></a></td>
      <td><?= $v->brand($r['brand_id']) ?></td><td><?= $r['kind'] === 'exchange' ? 'Exchange' : 'Return' ?></td><td class="num"><?= (int) $r['units'] ?></td><td class="small"><?= View::e($r['reason']) ?></td>
      <td><?= $v->badge($r['status']) ?></td><td><?= $v->badge($r['refund_status']) ?></td><td class="small nowrap"><?= $v->date($r['created_at'], false) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
