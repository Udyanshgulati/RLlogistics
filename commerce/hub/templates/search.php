<?php use Commerce\Hub\View; ?>
<div class="page-head"><div><h1>Search</h1><p><?= $q === '' ? 'Search by order number, SKU or product name.' : 'Results for “' . View::e($q) . '”' ?></p></div></div>
<?php if (mb_strlen($q) < 2): ?>
  <div class="card"><div class="empty"><h3>Type at least two characters</h3><p>Order numbers, SKUs (for example MI-JER-001-M) and product names all work.</p></div></div>
<?php elseif ($orders === [] && $items === []): ?>
  <div class="card"><div class="empty"><h3>Nothing found</h3><p>Check the spelling, or search for part of the SKU.</p></div></div>
<?php else: ?>
  <?php if ($orders): ?><div class="card"><div class="card-head"><h2>Orders</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Brand</th><th>Placed</th><th>Payment</th><th>Fulfilment</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><a class="link" href="/orders/<?= (int) $o['id'] ?>">#<?= View::e($o['order_number']) ?></a></td><td><?= $v->brand($o['brand_id']) ?></td><td><?= $v->date($o['placed_at']) ?></td><td><?= $v->badge($o['payment_status']) ?></td><td><?= $v->badge($o['fulfilment_status']) ?></td><td class="num"><?= $v->money($o['total_minor'], $o['currency']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div><?php endif; ?>
  <?php if ($items): ?><div class="card"><div class="card-head"><h2>Products</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Product</th><th>SKU</th><th class="num">Available</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><a class="link" href="/inventory/<?= (int) $i['id'] ?>"><?= View::e($i['name']) ?></a></td><td class="mono"><?= View::e($i['sku']) ?></td><td class="num"><?= $i['available'] ?></td><td><?= $v->badge($i['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div><?php endif; ?>
<?php endif; ?>
