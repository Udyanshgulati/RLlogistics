<?php use Commerce\Hub\View; ?>
<?php if ($app->user === null): ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= View::e($title) ?></title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="auth"><div class="auth-card"><?php endif; ?>
<div class="card"><div class="empty">
  <div class="glyph"><?= $v->icon('help') ?></div>
  <h3><?= View::e($title) ?></h3>
  <p><?= View::e($message) ?></p>
  <a class="btn" href="/">Go to overview</a>
</div></div>
<?php if ($app->user === null): ?></div></div></body></html><?php endif; ?>
