<?php use Commerce\Hub\View; ?>
<div class="page-head"><div>
  <div class="crumb"><a href="/inventory">Inventory</a> / <a href="/inventory/<?= (int) $item['id'] ?>"><?= View::e($item['sku']) ?></a> / Adjust</div>
  <h1>Adjust stock</h1>
</div></div>
<div class="grid-2">
  <div class="card"><div class="card-body"><?= $v->partial('partials/adjust-form', ['item' => $item, 'types' => $types, 'return' => 'item']) ?></div></div>
  <?php if ($canCount): ?>
  <form class="card" method="post" action="/inventory/<?= (int) $item['id'] ?>/count" data-confirm="This sets on hand to the number you counted and records the difference." data-confirm-title="Record count?">
    <?= $v->csrf() ?>
    <div class="card-head"><div><h2>Record a count</h2><p>After a physical count, enter what is on the shelf.</p></div></div>
    <div class="card-body stack">
      <div class="field"><label for="counted">Counted quantity</label><input class="input input-num" id="counted" name="counted" type="number" min="0" required></div>
      <div class="field"><label for="count-reason">Note</label><input class="input" id="count-reason" name="reason" value="Stocktake" maxlength="200"></div>
      <button class="btn">Record count</button>
    </div>
  </form>
  <?php endif; ?>
</div>
