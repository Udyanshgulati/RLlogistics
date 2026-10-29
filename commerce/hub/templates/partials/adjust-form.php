<?php use Commerce\Hub\View; /* Shared by the full Adjust page and the side panel. $return: where to go after saving. */ ?>
<form class="adjust" id="adjust-form" method="post" action="/inventory/<?= (int) $item['id'] ?>/adjust"
      data-on-hand="<?= $item['on_hand'] ?>" data-reserved="<?= $item['reserved'] ?>" data-damaged="<?= $item['damaged'] ?>" data-quarantine="<?= $item['quarantine'] ?>">
  <?= $v->csrf() ?>
  <input type="hidden" name="return" value="<?= View::e($return ?? 'item') ?>">
  <div class="adjust-head">
    <div><b><?= View::e($item['name']) ?></b><span class="sub mono"><?= View::e($item['sku']) ?><?= $item['option_label'] ? ' · Size ' . View::e($item['option_label']) : '' ?></span></div>
  </div>
  <div class="levels levels-3">
    <div><div class="l">On hand</div><div class="v"><?= $item['on_hand'] ?></div></div>
    <div><div class="l">Reserved</div><div class="v"><?= $item['reserved'] ?></div></div>
    <div class="is-key"><div class="l">Available</div><div class="v"><?= $item['available'] ?></div></div>
  </div>
  <fieldset class="field">
    <legend class="label">What happened?</legend>
    <div class="choice-list">
      <?php foreach ($types as $t): ?>
        <label class="choice"><input type="radio" name="type" value="<?= $t->value ?>" required><b><?= View::e($t->label()) ?></b><span><?= View::e($t->help()) ?></span></label>
      <?php endforeach; ?>
    </div>
    <?php if (count($types) < 6): ?><p class="help">Your role records received, damaged and returned stock. Losses and corrections are recorded by an inventory manager.</p><?php endif; ?>
  </fieldset>
  <div class="field"><label for="quantity">Quantity</label><input class="input input-num" id="quantity" name="quantity" type="number" step="1" required inputmode="numeric"><p class="help">For a correction, a negative number removes units.</p></div>
  <div class="field"><label for="reason" id="reason-label">Reason (optional)</label><input class="input" id="reason" name="reason" maxlength="500" placeholder="e.g. Supplier delivery, torn packaging, miscount"></div>
  <div class="preview" id="preview" hidden aria-live="polite">
    <div><div class="l">On hand</div><div class="v" data-onhand></div></div>
    <div><div class="l">Available to sell</div><div class="v" data-available></div></div>
    <div class="problem" data-problem hidden></div>
  </div>
  <div class="adjust-foot">
    <button class="btn btn-primary btn-lg" type="submit">Confirm adjustment</button>
    <?php if (($return ?? '') === 'list'): ?><button class="btn btn-ghost" type="button" data-drawer-close>Cancel</button><?php else: ?><a class="btn btn-ghost" href="/inventory/<?= (int) $item['id'] ?>">Cancel</a><?php endif; ?>
  </div>
  <p class="help">Recorded in the stock ledger with your name, the time, the change and the reason. You can undo it for 10 minutes.</p>
</form>
