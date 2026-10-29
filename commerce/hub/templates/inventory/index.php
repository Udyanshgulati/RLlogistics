<?php use Commerce\Hub\View;
$sortLink = function (string $key, string $label) use ($f, $v, $req) {
    $on = $f['sort'] === $key;
    $dir = $on && $f['dir'] === 'asc' ? 'desc' : 'asc';
    return '<a class="' . ($on ? 'sorted' : '') . '" href="' . $v->url('/inventory', $req->query, ['sort' => $key, 'dir' => $dir, 'page' => null]) . '">' . View::e($label) . ($on ? ($f['dir'] === 'asc' ? ' ↑' : ' ↓') : '') . '</a>';
};
$filtered = array_filter([$f['q'], $f['category'], $f['size'], $f['status']]) !== [];
?>
<div class="page-head">
  <div><h1>Inventory</h1><p>Central stock for all three storefronts, held at the Delhi warehouse.</p></div>
  <div class="actions">
    <?php if ($v->can('inventory.import')): ?><a class="btn" href="/inventory/import">Import inventory</a><?php endif; ?>
    <?php if ($v->can('inventory.import')): ?><a class="btn" href="<?= $v->url('/inventory/export', $req->query, ['page' => null]) ?>">Export inventory</a><?php endif; ?>
    <?php if ($v->can('purchasing.receive')): ?><a class="btn" href="/inventory/incoming">Incoming stock</a><?php endif; ?>
    <?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="presets">
    <a class="preset<?= !$filtered ? ' on' : '' ?>" href="/inventory">All</a>
    <a class="preset<?= $f['status'] === 'low' ? ' on' : '' ?>" href="/inventory?status=low">Low stock</a>
    <a class="preset<?= $f['status'] === 'out' ? ' on' : '' ?>" href="/inventory?status=out">Out of stock</a>
    <?php foreach ($views as $sv): ?><a class="preset" href="/inventory?<?= View::e($sv['query']) ?>"><?= View::e($sv['name']) ?></a><?php endforeach; ?>
  </div>
  <form class="toolbar" method="get" action="/inventory">
    <label class="sr-only" for="inv-q">Search</label>
    <input class="input grow" id="inv-q" name="q" value="<?= View::e($f['q']) ?>" placeholder="Search product name, SKU or barcode">
    <label class="sr-only" for="inv-cat">Category</label>
    <select class="select" id="inv-cat" name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option<?= $f['category'] === $c ? ' selected' : '' ?>><?= View::e($c) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="inv-size">Size</label>
    <select class="select" id="inv-size" name="size"><option value="">All sizes</option><?php foreach ($sizes as $s): ?><option<?= $f['size'] === $s ? ' selected' : '' ?>><?= View::e($s) ?></option><?php endforeach; ?></select>
    <label class="sr-only" for="inv-status">Stock status</label>
    <select class="select" id="inv-status" name="status"><option value="">Any status</option><?php foreach (['in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <input type="hidden" name="sort" value="<?= View::e($f['sort']) ?>"><input type="hidden" name="dir" value="<?= View::e($f['dir']) ?>">
    <button class="btn">Filter</button>
    <?php if ($filtered): ?><a class="btn btn-ghost" href="/inventory">Clear</a><span class="spacer"></span><?= $v->partial('partials/save-view', ['page' => 'inventory', 'req' => $req]) ?><?php endif; ?>
  </form>
  <?php if ($rows === []): ?>
    <div class="empty">
      <div class="glyph"><?= $v->icon('inventory') ?></div>
      <?php if ($filtered): ?><h3>No products match these filters</h3><p>Try a different search, or clear the filters.</p><a class="btn" href="/inventory">Clear filters</a>
      <?php else: ?><h3>No products yet</h3><p>Add your first product and its sizes. Each size gets its own SKU and stock record.</p><?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?><?php endif; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr>
      <th><?= $sortLink('name', 'Product') ?></th><th><?= $sortLink('sku', 'SKU') ?></th><th>Brand availability</th><th>Size</th>
      <th class="num" title="Physical units at the warehouse">On hand</th><th class="num" title="Held for open orders">Reserved</th>
      <th class="num"><?= $sortLink('available', 'Available') ?></th><th class="num hide-sm" title="On purchase orders, not yet received">Incoming</th><th class="num hide-sm">Reorder level</th><th>Status</th><th><span class="sr-only">Actions</span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a class="prod" href="/inventory/<?= (int) $r['id'] ?>"><?php if ($r['image_url']): ?><img class="thumb" src="<?= View::e($r['image_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb"><?= View::e(mb_substr($r['name'], 0, 1)) ?></span><?php endif; ?><span><b><?= View::e($r['name']) ?></b><span class="sub"><?= View::e($r['category'] ?? '') ?></span></span></a></td>
        <td class="mono nowrap"><?= View::e($r['sku']) ?></td>
        <td><?php if ($r['listing_brands'] === []): ?><?= $v->badge('unmapped') ?><?php else: ?><span class="gap-row"><?php foreach ($r['listing_brands'] as $b): ?><?= $v->brand($b) ?><?php endforeach; ?></span><?php endif; ?></td>
        <td><?= View::e($r['option_label'] ?? '—') ?></td>
        <td class="num"><?= $r['on_hand'] ?></td>
        <td class="num<?= $r['reserved'] ? '' : ' qty-zero' ?>"><?= $r['reserved'] ?></td>
        <td class="num qty-strong"><?= $r['available'] ?></td>
        <td class="num hide-sm<?= $r['incoming'] ? '' : ' qty-zero' ?>"><?= $r['incoming'] ?></td>
        <td class="num hide-sm"><?= $r['reorder_level'] ?></td>
        <td><?= $v->badge($r['status']) ?></td>
        <td class="text-right nowrap"><a class="btn btn-sm" href="/inventory/<?= (int) $r['id'] ?>/adjust">Adjust stock</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= $v->partial('partials/pager', compact('total', 'page', 'perPage', 'req')) ?>
  <?php endif; ?>
</div>
