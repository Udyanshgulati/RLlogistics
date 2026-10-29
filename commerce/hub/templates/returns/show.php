<?php use Commerce\Hub\View; use Commerce\Core\Market\Currency; $canProcess = $v->can('returns.process', $brand); ?>
<div class="page-head"><div><div class="crumb"><a href="/returns">Returns</a> / <?= View::e($r['rma_number']) ?></div>
  <h1><?= View::e($r['rma_number']) ?> · <?= $r['kind'] === 'exchange' ? 'Exchange' : 'Return' ?></h1>
  <p class="gap-row">Order <a class="link" href="/orders/<?= (int) $r['order_id'] ?>">#<?= View::e($r['order_number']) ?></a> · <?= View::e($r['reason']) ?> <?= $v->badge($r['status']) ?></p></div></div>
<div class="grid-2">
  <section class="card">
    <div class="card-head"><div><h2>Items</h2><p>1. Record what arrived. 2. Inspect each item. Only approved items return to available stock.</p></div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Item</th><th class="num">Expected</th><th>Received so far</th><th>Inspection so far</th></tr></thead>
      <tbody><?php foreach ($lines as $l): ?><tr>
        <td><?= View::e($l['name']) ?><span class="sub mono"><?= View::e($l['sku']) ?><?= $l['option_label'] ? ' · ' . View::e($l['option_label']) : '' ?></span></td>
        <td class="num"><?= (int) $l['qty'] ?></td>
        <td><?php if ($canProcess && (int) $l['qty_received'] < (int) $l['qty']): ?><form class="inline-form" method="post" action="/returns/lines/<?= (int) $l['id'] ?>/receive"><?= $v->csrf() ?><label class="sr-only" for="rc<?= (int) $l['id'] ?>">Received so far</label><input class="input input-num btn-sm" id="rc<?= (int) $l['id'] ?>" name="received" type="number" min="<?= (int) $l['qty_received'] ?>" max="<?= (int) $l['qty'] ?>" value="<?= (int) $l['qty'] ?>"><button class="btn btn-sm">Record receipt</button></form><span class="sub"><?= (int) $l['qty_received'] ?> received</span><?php else: ?><b><?= (int) $l['qty_received'] ?></b> received<?php endif; ?></td>
        <td><?php $pending = (int) $l['qty_received'] - (int) $l['qty_sellable'] - (int) $l['qty_damaged']; ?>
          <?php if ($canProcess && $pending > 0): ?><form class="inline-form" method="post" action="/returns/lines/<?= (int) $l['id'] ?>/inspect" data-confirm="Approved units go back on sale on every storefront that sells this SKU. Damaged units are held aside." data-confirm-title="Record inspection?"><?= $v->csrf() ?>
            <label class="sr-only" for="is<?= (int) $l['id'] ?>">Sellable</label><input class="input input-num btn-sm" id="is<?= (int) $l['id'] ?>" name="sellable" type="number" min="<?= (int) $l['qty_sellable'] ?>" value="<?= (int) $l['qty_sellable'] + $pending ?>" title="Approved for resale">
            <label class="sr-only" for="id<?= (int) $l['id'] ?>">Damaged</label><input class="input input-num btn-sm" id="id<?= (int) $l['id'] ?>" name="damaged" type="number" min="<?= (int) $l['qty_damaged'] ?>" value="<?= (int) $l['qty_damaged'] ?>" title="Damaged">
            <button class="btn btn-sm">Save</button></form><span class="sub">Approved · damaged (running totals)</span>
          <?php else: ?><?= (int) $l['qty_sellable'] ?> approved for resale · <?= (int) $l['qty_damaged'] ?> damaged<?= $pending > 0 ? ' · ' . $pending . ' awaiting inspection' : '' ?><?php endif; ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2>Refund</h2><p>Made by the storefront through the original payment provider.</p></div><?= $v->badge($r['refund_status']) ?></div>
    <div class="card-body stack">
      <?php if (in_array($r['refund_status'], ['not_requested', 'failed'], true) && $v->can('refunds.request', $brand)): ?>
        <?php if ($r['refund_status'] === 'failed'): ?><div class="flash flash-error"><span class="msg">The last refund attempt failed. Check the order timeline for the reason.</span></div><?php endif; ?>
        <form class="stack" method="post" action="/returns/<?= (int) $r['id'] ?>/refund" data-confirm="The storefront will refund this amount to the customer's original payment method. This cannot be undone from the hub." data-confirm-title="Request refund?" data-confirm-ok="Request refund">
          <?= $v->csrf() ?>
          <div class="field"><label for="amount">Amount (<?= View::e($r['currency']) ?>)</label><input class="input input-num" id="amount" name="amount" inputmode="decimal" required value="<?= View::e(Currency::fromMinor(min($suggested, (int) $r['total_minor'] - (int) $r['refunded_minor']), $r['currency'])) ?>"><p class="help">Suggested: the price of the returned items. Up to <?= $v->money((int) $r['total_minor'] - (int) $r['refunded_minor'], $r['currency']) ?> can still be refunded on this order.</p></div>
          <button class="btn btn-primary">Request refund</button>
        </form>
      <?php elseif ($r['refund_status'] === 'requested'): ?><p>Sent to the storefront. It will show as refunded once the payment provider confirms.</p>
      <?php elseif ($r['refund_status'] === 'succeeded'): ?><p>Refund confirmed by the payment provider<?= $r['refund_ref'] ? ' (reference ' . View::e($r['refund_ref']) . ')' : '' ?>.</p>
      <?php else: ?><p class="muted">Your role cannot request refunds. Finance can.</p><?php endif; ?>
    </div>
  </section>
</div>
