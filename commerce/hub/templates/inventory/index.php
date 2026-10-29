<?php use Commerce\Hub\View;
$sortLink = function (string $key, string $label) use ($f, $v, $req) {
    $on = $f['sort'] === $key;
    $dir = $on && $f['dir'] === 'asc' ? 'desc' : 'asc';
    return '<a class="' . ($on ? 'sorted' : '') . '" href="' . $v->url('/inventory', $req->query, ['sort' => $key, 'dir' => $dir, 'page' => null]) . '">' . View::e($label) . ($on ? ($f['dir'] === 'asc' ? ' ↑' : ' ↓') : '') . '</a>';
};
$filtered = array_filter([$f['q'], $f['category'], $f['size'], $f['status']]) !== [];
$brandsCell = function (array $brands) use ($v, $app) {
    if ($brands === []) return $v->badge('unmapped');
    if (count($brands) === 1) return $v->brand($brands[0]);
    $names = implode(', ', array_map(fn ($b) => $app->services->brands()[$b]['name'], $brands));
    return '<span class="badge badge-info" title="' . View::e($names) . '">Shared · ' . count($brands) . ' brands</span>';
};
$canBulk = $v->can('inventory.import') || $v->can('products.manage');
?>
<div class="page-head">
  <div><h1>Inventory</h1><p>Central stock for all three storefronts, held at the Delhi warehouse.</p></div>
  <div class="actions">
    <?php if ($v->can('inventory.import')): ?><a class="btn" href="/inventory/import">Import CSV</a><a class="btn" href="<?= $v->url('/inventory/export', $req->query, ['page' => null]) ?>">Export CSV</a><?php endif; ?>
    <?php if ($v->can('purchasing.receive')): ?><a class="btn" href="/inventory/incoming">Incoming stock</a><?php endif; ?>
    <?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?>
  </div>
</div>

<ol class="lifecycle" aria-label="How stock moves">
  <li><b>Received</b><span>Counted in at the warehouse</span></li>
  <li class="is-key"><b>Available</b><span>On hand − reserved − damaged − awaiting inspection</span></li>
  <li><b>Reserved</b><span>Held for a placed order</span></li>
  <li><b>Dispatched</b><span>Left with the carrier</span></li>
</ol>

<div class="card" data-filter-card>
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
  <?php if ($canBulk && $groups): ?>
  <div class="bulkbar" data-bulkbar hidden>
    <span><b data-bulk-count>0</b> selected</span>
    <?php if ($v->can('inventory.import')): ?><a class="btn btn-sm" href="#" data-bulk-export>Export selected</a><?php endif; ?>
    <?php if ($v->can('products.manage')): ?><form method="post" action="/inventory/bulk-reorder" class="inline-form" data-bulk-form data-confirm="This sets the same reorder level on every selected SKU. Each change is recorded." data-confirm-title="Change reorder levels?">
      <?= $v->csrf() ?><label class="sr-only" for="bulk-level">Reorder level</label><input class="input input-num btn-sm" id="bulk-level" name="reorder_level" type="number" min="0" required placeholder="Level"><button class="btn btn-sm">Set reorder level</button></form><?php endif; ?>
    <button class="btn btn-sm btn-ghost" type="button" data-bulk-clear>Clear selection</button>
  </div>
  <?php endif; ?>
  <?php if ($groups === []): ?>
    <div class="empty">
      <div class="glyph"><?= $v->icon('inventory') ?></div>
      <?php if ($filtered): ?><h3>No products match these filters</h3><p>Try a different search, or clear the filters.</p><a class="btn" href="/inventory">Clear filters</a>
      <?php else: ?><h3>No products yet</h3><p>Add your first product and its sizes. Each size gets its own SKU and stock record.</p><?php if ($v->can('products.manage')): ?><a class="btn btn-primary" href="/products/new">Add product</a><?php endif; ?><?php endif; ?>
    </div>
  <?php else: ?>
  <div class="table-wrap"><table class="inv-table">
    <thead><tr>
      <?php if ($canBulk): ?><th class="col-check"><label class="sr-only" for="sel-all">Select all</label><input type="checkbox" id="sel-all" data-check-all="sku"></th><?php endif; ?>
      <th><?= $sortLink('name', 'Product') ?></th><th><?= $sortLink('sku', 'SKU') ?></th><th>Brand</th><th>Size</th>
      <th class="num" title="Physical units at the warehouse">On hand</th><th class="num" title="Held for open orders">Reserved</th>
      <th class="num"><?= $sortLink('available', 'Available') ?></th><th class="num hide-sm" title="On purchase orders, not yet received">Incoming</th><th class="num hide-sm">Reorder</th><th>Status</th><th><span class="sr-only">Actions</span></th>
    </tr></thead>
    <?php foreach ($groups as $gi => $g): $multi = count($g['items']) > 1; $open = $multi && ($f['size'] !== '' || $f['q'] !== '' || $g['problems'] > 0 && $f['status'] !== ''); ?>
    <tbody class="group<?= $open ? ' is-open' : '' ?>" data-group>
      <?php if ($multi): ?>
      <tr class="parent">
        <?php if ($canBulk): ?><td class="col-check"><input type="checkbox" data-group-check aria-label="Select all sizes of <?= View::e($g['name']) ?>"></td><?php endif; ?>
        <td><button class="expander" type="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="g<?= $gi ?>" data-expand><span class="chev" aria-hidden="true">›</span><span class="prod"><?php if ($g['image_url']): ?><img class="thumb" src="<?= View::e($g['image_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb"><?= View::e(mb_substr($g['name'], 0, 1)) ?></span><?php endif; ?><span><b><?= View::e($g['name']) ?></b><span class="sub"><?= View::e($g['category'] ?? '') ?></span></span></span></button></td>
        <td class="mono nowrap muted"><?= View::e($g['code']) ?></td>
        <td><?= $brandsCell($g['brands']) ?></td>
        <td class="small muted nowrap"><?= count($g['items']) ?> sizes</td>
        <td class="num"><?= $g['on_hand'] ?></td><td class="num<?= $g['reserved'] ? '' : ' qty-zero' ?>"><?= $g['reserved'] ?></td><td class="num qty-strong"><?= $g['available'] ?></td>
        <td class="num hide-sm<?= $g['incoming'] ? '' : ' qty-zero' ?>"><?= $g['incoming'] ?></td><td class="hide-sm"></td>
        <td class="nowrap"><?= $g['problems'] ? $v->badge($g['status']) . '<span class="sub">' . $g['problems'] . ' of ' . count($g['items']) . ' sizes</span>' : $v->badge('in') ?></td>
        <td></td>
      </tr>
      <?php endif; ?>
      <?php foreach ($g['items'] as $r): ?>
      <tr class="<?= $multi ? 'child' : 'single' ?>"<?= $multi ? ' id="g' . $gi . '"' : '' ?>>
        <?php if ($canBulk): ?><td class="col-check"><input type="checkbox" name="sku" value="<?= (int) $r['id'] ?>" aria-label="Select <?= View::e($r['sku']) ?>"></td><?php endif; ?>
        <td><?php if ($multi): ?><a class="child-name" href="/inventory/<?= (int) $r['id'] ?>">Size <?= View::e($r['option_label']) ?></a><?php else: ?><a class="prod" href="/inventory/<?= (int) $r['id'] ?>"><?php if ($r['image_url']): ?><img class="thumb" src="<?= View::e($r['image_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb"><?= View::e(mb_substr($r['name'], 0, 1)) ?></span><?php endif; ?><span><b><?= View::e($r['name']) ?></b><span class="sub"><?= View::e($r['category'] ?? '') ?></span></span></a><?php endif; ?></td>
        <td class="mono nowrap"><a class="link-plain" href="/inventory/<?= (int) $r['id'] ?>"><?= View::e($r['sku']) ?></a></td>
        <td><?= $multi ? '' : $brandsCell($r['listing_brands']) ?></td>
        <td><?= View::e($r['option_label'] ?? '—') ?></td>
        <td class="num"><?= $r['on_hand'] ?></td>
        <td class="num<?= $r['reserved'] ? '' : ' qty-zero' ?>"><?= $r['reserved'] ?></td>
        <td class="num qty-strong"><?= $r['available'] ?></td>
        <td class="num hide-sm<?= $r['incoming'] ? '' : ' qty-zero' ?>"><?= $r['incoming'] ?></td>
        <td class="num hide-sm"><?= $r['reorder_level'] ?></td>
        <td><?= $v->badge($r['status']) ?></td>
        <td class="text-right nowrap"><a class="btn btn-sm" href="/inventory/<?= (int) $r['id'] ?>/adjust" data-drawer="/inventory/<?= (int) $r['id'] ?>/adjust?panel=1">Adjust</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
  </table></div>
  <?= $v->partial('partials/pager', ['total' => $total, 'page' => $page, 'perPage' => $perPage, 'req' => $req]) ?>
  <?php endif; ?>
</div>
