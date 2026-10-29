<?php use Commerce\Hub\View; ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Sign in · Operations hub</title><link rel="stylesheet" href="/assets/app.css"></head>
<body><div class="auth"><div class="auth-card">
  <div class="mark">OH</div>
  <h1>Sign in</h1>
  <p class="muted">Operations hub for Milan Icons, Significant Sports and Schermo.</p>
  <?php if (!empty($error)): ?><div class="flash flash-error mt-18" role="alert"><span class="msg"><?= View::e($error) ?></span></div><?php endif; ?>
  <form method="post" action="/login">
    <?= $v->csrf() ?>
    <input type="hidden" name="next" value="<?= View::e(str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/') ?>">
    <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" autocomplete="username" required value="<?= View::e($email) ?>" autofocus></div>
    <div class="field"><label for="password">Password</label><input class="input" id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
  </form>
  <p class="auth-foot">Forgotten your password? Ask an owner to send you a new set-up link.</p>
</div></div></body></html>
