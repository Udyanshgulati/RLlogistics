<?php use Commerce\Hub\View; ?>
<div class="page-head">
  <div><h1>Products</h1><p>Central SKUs. Descriptions, photos and prices are edited in each storefront.</p></div>
  <div class="actions"><?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?></div>
</div>
<?php if ($unlinked): ?>
<section class="card mb-16" id="unlinked">
  <div class="card-head"><div><h2>Storefront products not linked to stock</h2><p>These cannot be sold until linked. A SKU match is only a suggestion — confirm each one.</p></div><?= $v->badge('unmapped') ?></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Storefront</th><th>Storefront product</th><th>Storefront SKU</th><th>Suggested match</th><th></th></tr></thead>
    <tbody><?php foreach ($unlinked as $l): $s = $suggestions[$l['id']] ?? null; ?><tr>
      <td><?= $v->brand($l['brand_id']) ?></td>
      <td><?= View::e($l['wc_name'] ?? 'Untitled') ?><span class="sub">#<?= (int) $l['wc_product_id'] ?><?= $l['wc_variation_id'] ? ' · variation #' . (int) $l['wc_variation_id'] : '' ?></span></td>
      <td class="mono"><?= View::e($l['wc_sku'] ?? '—') ?></td>
      <td><?= $s ? '<span class="mono">' . View::e($s['sku']) . '</span>' : '<span class="muted">No exact SKU match</span>' ?></td>
      <td class="text-right"><?php if ($s): ?><form method="post" action="/listings/<?= (int) $l['id'] ?>/map"><?= $v->csrf() ?><input type="hidden" name="item_id" value="<?= (int) $s['item_id'] ?>"><button class="btn btn-sm">Review link</button></form><?php else: ?><span class="muted small">Link from the product page</span><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
<?php if (!$onlyUnlinked): ?>
<div class="card">
  <form class="toolbar" method="get" action="/products"><label class="sr-only" for="pq">Search</label><input class="input grow" id="pq" name="q" value="<?= View::e($q) ?>" placeholder="Search product name or code"><button class="btn">Search</button></form>
  <?php if ($products === []): ?>
    <div class="empty"><div class="glyph"><?= $v->icon('products') ?></div><h3><?= $q ? 'No products match' : 'No products yet' ?></h3><p>Create a product once and generate all six clothing sizes in one step. Each size gets its own SKU and stock record.</p><?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Product</th><th>Code</th><th>Owner</th><th>Category</th><th class="num">SKUs</th><th>Inventory</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($products as $p): ?><tr>
      <td><a class="prod" href="/products/<?= (int) $p['id'] ?>"><span class="thumb"><?= View::e(mb_substr($p['name'], 0, 1)) ?></span><b><?= View::e($p['name']) ?></b></a></td>
      <td class="mono"><?= View::e($p['code']) ?></td><td><?= $v->brand($p['owner_brand_id']) ?></td><td><?= View::e($p['category'] ?? '—') ?></td>
      <td class="num"><?= (int) $p['skus'] ?></td>
      <td><?= $v->badge((int) $p['selling_brands'] > 1 ? 'shared' : ((int) $p['links'] > 0 ? 'exclusive' : 'unmapped')) ?></td>
      <td><?= $v->badge($p['status'] === 'active' ? 'in' : $p['status']) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
