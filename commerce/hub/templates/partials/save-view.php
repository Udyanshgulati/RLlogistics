<?php use Commerce\Hub\View; ?>
<form method="post" action="/views" class="inline-form">
  <?= $v->csrf() ?>
  <input type="hidden" name="page" value="<?= View::e($page) ?>">
  <input type="hidden" name="query" value="<?= View::e(http_build_query($req->query)) ?>">
  <label class="sr-only" for="view-name">Name for this view</label>
  <input class="input btn-sm" id="view-name" name="name" placeholder="Save these filters as…" maxlength="60" required>
  <button class="btn btn-sm">Save view</button>
</form>
