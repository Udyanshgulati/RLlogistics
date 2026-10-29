<?php use Commerce\Hub\View; $o = fn ($k, $d = '') => View::e($old[$k] ?? $d); $oldSizes = (array) ($old['sizes'] ?? $sizes); $variants = $old['variants'] ?? 'sizes'; ?>
<div class="page-head"><div><div class="crumb"><a href="/products">Products</a> / New</div><h1>Add product</h1><p>Creates the central SKUs with zero stock. Record stock afterwards with Adjust stock or a CSV import.</p></div></div>
<form class="card" method="post" action="/products">
  <?= $v->csrf() ?>
  <div class="card-body stack">
    <div class="form-grid">
      <div class="field"><label for="brand_id">Owner brand</label>
        <select class="select" id="brand_id" name="brand_id" required><?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>" data-prefix="<?= View::e($prefixes[$b['id']]) ?>"<?= (string) ($old['brand_id'] ?? '') === (string) $b['id'] ? ' selected' : '' ?>><?= View::e($b['name']) ?> (<?= View::e($prefixes[$b['id']]) ?>)</option><?php endforeach; ?></select>
        <p class="help">The brand that owns the physical stock. Other brands can sell it later, but only after someone confirms the shared link.</p></div>
      <div class="field"><label for="code">Product code</label><input class="input mono" id="code" name="code" required value="<?= $o('code') ?>" placeholder="MI-JER-001" pattern="[A-Za-z0-9]{2,8}-[A-Za-z0-9]{2,8}-[0-9]{3,6}">
        <p class="help">Brand prefix, type and number, e.g. MI-JER-001. Sizes are added to the end: MI-JER-001-M.</p></div>
      <div class="field span-2"><label for="name">Product name</label><input class="input" id="name" name="name" required maxlength="200" value="<?= $o('name') ?>"></div>
      <div class="field"><label for="category">Category</label><input class="input" id="category" name="category" maxlength="80" value="<?= $o('category') ?>" placeholder="e.g. Jerseys"></div>
    </div>
    <fieldset class="field">
      <legend class="label">Variations</legend>
      <div class="choice-grid">
        <label class="choice"><input type="radio" name="variants" value="sizes"<?= $variants === 'sizes' ? ' checked' : '' ?>><b>Clothing sizes</b><span>One SKU per size</span></label>
        <label class="choice"><input type="radio" name="variants" value="custom"<?= $variants === 'custom' ? ' checked' : '' ?>><b>Other options</b><span>Colours, lengths, editions…</span></label>
        <label class="choice"><input type="radio" name="variants" value="none"<?= $variants === 'none' ? ' checked' : '' ?>><b>No variations</b><span>A single SKU</span></label>
      </div>
    </fieldset>
    <div class="field" data-variants="sizes"><span class="label">Sizes to create</span>
      <div class="sizes"><?php foreach ($sizes as $s): ?><label class="size"><input type="checkbox" name="sizes[]" value="<?= View::e($s) ?>"<?= in_array($s, $oldSizes, true) ? ' checked' : '' ?>><span><?= View::e($s) ?></span></label><?php endforeach; ?></div>
      <p class="help">Each size gets its own SKU, stock and reorder level.</p></div>
    <div class="field" data-variants="custom" hidden><label for="custom_options">Options</label><input class="input" id="custom_options" name="custom_options" value="<?= $o('custom_options') ?>" placeholder="BLACK, WHITE"><p class="help">Separate with commas. Each becomes a SKU suffix.</p></div>
    <div class="gap-row"><button class="btn btn-primary btn-lg">Create product</button><a class="btn btn-ghost" href="/products">Cancel</a></div>
  </div>
</form>
