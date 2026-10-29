<?php use Commerce\Hub\View; $canManage = $v->can('products.manage', (int) $product['owner_brand_id']); $canMap = $v->can('mapping.manage'); ?>
<div class="page-head">
  <div><div class="crumb"><a href="/products">Products</a> / <?= View::e($product['code']) ?></div>
    <h1><?= View::e($product['name']) ?></h1>
    <p class="gap-row"><span class="mono"><?= View::e($product['code']) ?></span> · Owned by <?= $v->brand($product['owner_brand_id']) ?> <?= $v->badge($product['status'] === 'active' ? 'in' : $product['status']) ?></p></div>
</div>

<?php if ($confirm && in_array($confirm['item'], array_column($items, 'id'), true)): ?>
<form class="card mb-16" id="confirm" method="post" action="/listings/<?= (int) $confirm['listing'] ?>/map">
  <?= $v->csrf() ?><input type="hidden" name="item_id" value="<?= (int) $confirm['item'] ?>">
  <?php if ($confirm['shared']): ?><input type="hidden" name="confirm_shared" value="1"><?php endif; ?>
  <?php if ($confirm['sku']): ?><input type="hidden" name="confirm_sku" value="1"><?php endif; ?>
  <div class="card-body stack">
    <div class="notice notice-warn"><b><?= $confirm['kind'] === 'shared' ? 'This will share stock between brands.' : 'The SKUs do not match.' ?></b> <?= View::e($confirm['message']) ?></div>
    <p class="muted">Only continue if both listings are the same physical product on the same shelf. Similar names or photos are not enough.</p>
    <div class="gap-row"><button class="btn btn-primary">Yes, link them</button><a class="btn btn-ghost" href="/products/<?= (int) $product['id'] ?>">Cancel</a></div>
  </div>
</form>
<?php endif; ?>

<form class="card" method="post" action="/products/<?= (int) $product['id'] ?>/items">
  <?= $v->csrf() ?>
  <div class="card-head"><div><h2>Sizes and SKUs</h2><p>Weight and dimensions are needed for shipping quotes and customs.</p></div><?php if ($canManage): ?><button class="btn btn-primary btn-sm">Save changes</button><?php endif; ?></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Size</th><th>SKU</th><th>Barcode</th><th class="num">Weight (g)</th><th class="num">L × W × H (mm)</th><th class="num">Reorder level</th><th class="num">On hand</th><th class="num">Available</th><th>Inventory</th><th></th></tr></thead>
    <tbody><?php foreach ($items as $i): $n = 'items[' . (int) $i['id'] . ']'; $ro = $canManage ? '' : ' disabled'; ?><tr>
      <td><b><?= View::e($i['option_label'] ?? '—') ?></b></td>
      <td class="mono nowrap"><a class="link" href="/inventory/<?= (int) $i['id'] ?>"><?= View::e($i['sku']) ?></a></td>
      <td><label class="sr-only" for="bc<?= (int) $i['id'] ?>">Barcode</label><input class="input btn-sm mono" id="bc<?= (int) $i['id'] ?>" name="<?= $n ?>[barcode]" value="<?= View::e($i['barcode'] ?? '') ?>"<?= $ro ?>></td>
      <td class="num"><input class="input input-num btn-sm" name="<?= $n ?>[weight_g]" value="<?= View::e($i['weight_g'] ?? '') ?>" inputmode="numeric" aria-label="Weight in grams"<?= $ro ?>></td>
      <td class="num nowrap"><input class="input input-num btn-sm" name="<?= $n ?>[length_mm]" value="<?= View::e($i['length_mm'] ?? '') ?>" aria-label="Length" inputmode="numeric"<?= $ro ?>> <input class="input input-num btn-sm" name="<?= $n ?>[width_mm]" value="<?= View::e($i['width_mm'] ?? '') ?>" aria-label="Width" inputmode="numeric"<?= $ro ?>> <input class="input input-num btn-sm" name="<?= $n ?>[height_mm]" value="<?= View::e($i['height_mm'] ?? '') ?>" aria-label="Height" inputmode="numeric"<?= $ro ?>></td>
      <td class="num"><input class="input input-num btn-sm" name="<?= $n ?>[reorder_level]" value="<?= (int) $i['reorder_level'] ?>" aria-label="Reorder level" inputmode="numeric"<?= $ro ?>></td>
      <td class="num"><?= $i['on_hand'] ?></td><td class="num"><b><?= $i['available'] ?></b></td>
      <td><?= $v->badge($i['sharing']) ?></td>
      <td><a class="btn btn-sm" href="/inventory/<?= (int) $i['id'] ?>/adjust">Add stock</a></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <div class="card-body form-grid">
    <div class="field"><label for="hs_code">HS code</label><input class="input mono" id="hs_code" name="hs_code" value="<?= View::e($product['hs_code'] ?? '') ?>" placeholder="Confirm with your customs adviser"<?= $canManage ? '' : ' disabled' ?>><p class="help">Needed on customs paperwork for international shipments.</p></div>
    <div class="field"><label for="origin_country">Country of origin</label><input class="input mono" id="origin_country" name="origin_country" value="<?= View::e($product['origin_country'] ?? '') ?>" maxlength="2" placeholder="IN"<?= $canManage ? '' : ' disabled' ?>></div>
    <div class="field"><label for="category">Category</label><input class="input" id="category" name="category" value="<?= View::e($product['category'] ?? '') ?>"<?= $canManage ? '' : ' disabled' ?>></div>
    <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"<?= $canManage ? '' : ' disabled' ?>><?php foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $k => $l): ?><option value="<?= $k ?>"<?= $product['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  </div>
</form>

<?php if ($canManage): ?>
<form class="card inline-form card-body" method="post" action="/products/<?= (int) $product['id'] ?>/options">
  <?= $v->csrf() ?><label class="label" for="option">Add a size or option</label><input class="input btn-sm" id="option" name="option" placeholder="e.g. XS" required maxlength="20"><button class="btn btn-sm">Add</button>
</form>
<?php endif; ?>

<section class="card">
  <div class="card-head"><div><h2>Storefront links</h2><p>Each storefront product draws its stock from one SKU above.</p></div></div>
  <?php if ($listings === []): ?><div class="card-body"><p class="muted">No storefront sells this product yet.</p></div>
  <?php else: ?><div class="table-wrap"><table>
    <thead><tr><th>Storefront</th><th>SKU</th><th>Storefront product</th><th>Linked</th><th></th></tr></thead>
    <tbody><?php foreach ($listings as $l): ?><tr>
      <td><?= $v->brand($l['brand_id']) ?></td><td class="mono"><?= View::e($l['sku']) ?></td>
      <td><?= View::e($l['wc_name'] ?? '') ?><span class="sub">#<?= (int) $l['wc_product_id'] ?><?= $l['wc_variation_id'] ? ' · variation #' . (int) $l['wc_variation_id'] : '' ?></span></td>
      <td class="small"><?= $v->date($l['mapped_at']) ?></td>
      <td class="text-right"><?php if ($canMap): ?><form class="inline-form" method="post" action="/listings/<?= (int) $l['id'] ?>/unmap" data-confirm="The storefront will show this product as out of stock until it is linked again." data-confirm-title="Unlink from stock?" data-confirm-ok="Unlink"><?= $v->csrf() ?><label class="sr-only" for="ur<?= (int) $l['id'] ?>">Reason</label><input class="input btn-sm" id="ur<?= (int) $l['id'] ?>" name="reason" placeholder="Reason" required><button class="btn btn-sm btn-danger">Unlink</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
  <?php if ($canMap): ?>
  <form class="card-body form-grid" method="post" action="/listings">
    <?= $v->csrf() ?>
    <div class="field span-2"><span class="label">Link a storefront product</span><p class="help">Normally storefront products appear automatically once the store is connected. You can also add one by its WooCommerce ID.</p></div>
    <div class="field"><label for="ls">Storefront</label><select class="select" id="ls" name="store_id"><?php foreach ($stores as $s): ?><option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="li">SKU</label><select class="select" id="li" name="item_id"><?php foreach ($items as $i): ?><option value="<?= (int) $i['id'] ?>"><?= View::e($i['sku']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="lp">WooCommerce product ID</label><input class="input" id="lp" name="wc_product_id" inputmode="numeric" required></div>
    <div class="field"><label for="lv">Variation ID</label><input class="input" id="lv" name="wc_variation_id" inputmode="numeric" placeholder="Leave empty for a simple product"></div>
    <div class="field"><label for="lk">Storefront SKU</label><input class="input mono" id="lk" name="wc_sku"></div>
    <div class="field"><label for="ln">Storefront product name</label><input class="input" id="ln" name="wc_name"></div>
    <div class="span-2"><button class="btn">Link</button></div>
  </form>
  <?php endif; ?>
</section>
