<?php use Commerce\Hub\View; use Commerce\Hub\Controllers\AuthController; ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Set your password · Operations hub</title><link rel="stylesheet" href="/assets/app.css"></head>
<body><div class="auth"><div class="auth-card">
  <div class="mark">OH</div>
  <h1>Set your password</h1>
  <?php if ($user): ?><p class="muted">Welcome, <?= View::e($user['name']) ?>. Choose a password of at least <?= AuthController::MIN_PASSWORD ?> characters.</p><?php endif; ?>
  <?php if (!empty($error)): ?><div class="flash flash-error mt-18" role="alert"><span class="msg"><?= View::e($error) ?></span></div><?php endif; ?>
  <?php if ($user): ?>
  <form method="post" action="/setup">
    <?= $v->csrf() ?>
    <input type="hidden" name="token" value="<?= View::e($token) ?>">
    <div class="field"><label for="password">New password</label><input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="<?= AuthController::MIN_PASSWORD ?>" required></div>
    <div class="field"><label for="password_confirm">Repeat password</label><input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required></div>
    <button class="btn btn-primary btn-lg" type="submit">Save and sign in</button>
  </form>
  <?php endif; ?>
</div></div></body></html>
