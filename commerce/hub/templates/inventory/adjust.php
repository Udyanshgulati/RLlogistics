<?php use Commerce\Hub\View; ?>
<div class="page-head"><div>
  <div class="crumb"><a href="/inventory">Inventory</a> / <a href="/inventory/<?= (int) $item['id'] ?>"><?= View::e($item['sku']) ?></a> / Adjust</div>
  <h1>Adjust stock</h1>
</div></div>
<div class="grid-2">
  <form class="card" id="adjust-form" method="post" action="/inventory/<?= (int) $item['id'] ?>/adjust"
        data-on-hand="<?= $item['on_hand'] ?>" data-reserved="<?= $item['reserved'] ?>" data-damaged="<?= $item['damaged'] ?>" data-quarantine="<?= $item['quarantine'] ?>">
    <?= $v->csrf() ?>
    <div class="card-body stack">
      <dl class="kv">
        <dt>Product</dt><dd><b><?= View::e($item['name']) ?></b></dd>
        <dt>SKU</dt><dd class="mono"><?= View::e($item['sku']) ?></dd>
        <dt>Current physical stock</dt><dd><b><?= $item['on_hand'] ?></b> on hand · <?= $item['available'] ?> available<?= $item['reserved'] ? ' · ' . $item['reserved'] . ' reserved for orders' : '' ?></dd>
      </dl>
      <fieldset class="field">
        <legend class="label">Adjustment type</legend>
        <div class="choice-grid">
          <?php foreach ($types as $t): ?>
            <label class="choice"><input type="radio" name="type" value="<?= $t->value ?>" required><b><?= View::e($t->label()) ?></b><span><?= View::e($t->help()) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (count($types) < 6): ?><p class="help">Your role can record received, damaged and returned stock. Ask an inventory manager for losses and corrections.</p><?php endif; ?>
      </fieldset>
      <div class="field"><label for="quantity">Quantity</label><input class="input input-num" id="quantity" name="quantity" type="number" step="1" required inputmode="numeric"><p class="help">For a stock correction, enter a negative number to remove units.</p></div>
      <div class="field"><label for="reason" id="reason-label">Reason (optional)</label><input class="input" id="reason" name="reason" maxlength="500" placeholder="e.g. Delivery from supplier, torn packaging, miscount"></div>
      <div class="preview" id="preview" hidden aria-live="polite">
        <div><div class="l">On hand</div><div class="v" data-onhand></div></div>
        <div><div class="l">Available to sell</div><div class="v" data-available></div></div>
        <div class="problem" data-problem hidden></div>
      </div>
      <div class="gap-row"><button class="btn btn-primary btn-lg" type="submit">Confirm adjustment</button><a class="btn btn-ghost" href="/inventory/<?= (int) $item['id'] ?>">Cancel</a></div>
      <p class="help">Recorded with your name, the time, the previous and resulting quantities and the reason. You can undo it for 10 minutes.</p>
    </div>
  </form>
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
