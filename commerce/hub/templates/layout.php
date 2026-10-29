<?php
/** @var \Commerce\Hub\View $v */
use Commerce\Hub\View;
$u = $app->user;
$flash = $app->takeFlash();
$brands = $app->services->brands();
$current = $nav ?? '';
$attentionCount = 0;
if ($u && $u->can('orders.view', anyBrand: true)) {
    $attentionCount = (int) $app->db->value('SELECT COUNT(*) FROM orders WHERE attention IS NOT NULL');
}
$deadCount = $u && $u->can('integrations.view') ? (int) $app->db->value("SELECT (SELECT COUNT(*) FROM outbox WHERE status = 'dead') + (SELECT COUNT(*) FROM inbox_events WHERE status = 'failed')") : 0;
$demo = $app->db->value("SELECT value FROM settings WHERE name = 'demo_data'") === '1';
$items = [
    ['overview', '/', 'Overview', 'overview.view'],
    ['orders', '/orders', 'Orders', 'orders.view'],
    ['inventory', '/inventory', 'Inventory', 'inventory.view'],
    ['products', '/products', 'Products', 'products.view'],
    ['shipments', '/shipments', 'Shipments', 'orders.view'],
    ['returns', '/returns', 'Returns & Exchanges', 'orders.view'],
    ['transfers', '/transfers', 'Stock Transfers', 'inventory.view'],
    ['reports', '/reports', 'Reports', ['reports.sales', 'reports.operations']],
    ['team', '/team', 'Team & Permissions', 'team.manage'],
    ['settings', '/settings', 'Settings', 'settings.manage'],
];
$initials = $u ? strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($u->name)), 0, 2)))) : '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= View::e(($title ?? 'Operations') . ' · Operations hub') ?></title>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<?php if ($demo): ?><div class="demo-banner"><b>Demonstration data.</b> Products, orders and stock on this server are examples for training and review. They are not real and will be removed before launch.</div><?php endif; ?>
<div class="shell">
  <aside class="side" aria-label="Main navigation">
    <div class="side-brand"><div class="mark">OH</div><div><b>Operations hub</b><span>Milan Icons · Significant Sports · Schermo</span></div></div>
    <nav class="nav">
      <?php foreach ($items as [$key, $href, $label, $perm]):
          $perms = (array) $perm;
          $ok = false;
          foreach ($perms as $p) { $ok = $ok || ($u && ($u->can($p, anyBrand: true))); }
          if (!$ok) continue; ?>
        <a href="<?= $href ?>" class="<?= $current === $key ? 'on' : '' ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= $v->icon($key) ?><?= View::e($label) ?><?php if ($key === 'orders' && $attentionCount > 0): ?><span class="count" title="Orders that need attention"><?= $attentionCount ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot nav">
      <?php if ($u && $u->can('integrations.view')): ?><a href="/health" class="<?= $current === 'health' ? 'on' : '' ?>"><?= $v->icon('health') ?>Integration health<?php if ($deadCount > 0): ?><span class="count"><?= $deadCount ?></span><?php endif; ?></a><?php endif; ?>
      <a href="/help" class="<?= $current === 'help' ? 'on' : '' ?>"><?= $v->icon('help') ?>Help &amp; glossary</a>
    </div>
  </aside>
  <div class="scrim" data-nav-toggle></div>
  <div class="main">
    <header class="top">
      <button class="btn btn-ghost btn-sm menu-btn" type="button" data-nav-toggle aria-label="Open menu"><?= $v->icon('menu') ?></button>
      <form class="search" action="/search" method="get" role="search">
        <?= $v->icon('search') ?>
        <label class="sr-only" for="global-search">Search orders, SKUs and products</label>
        <input id="global-search" name="q" type="search" placeholder="Search order number, SKU or product" value="<?= View::e($req->q('q')) ?>" autocomplete="off">
        <kbd>/</kbd>
      </form>
      <div class="top-right">
        <form method="post" action="/context" class="chip brand-chip">
          <?= $v->csrf() ?>
          <label for="brand-filter"><span class="lbl muted">Brand</span><span class="sr-only">Brand filter</span></label>
          <select id="brand-filter" name="brand" data-autosubmit>
            <option value="">All brands</option>
            <?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>"<?= $app->brandFilter() === (int) $b['id'] ? ' selected' : '' ?>><?= View::e($b['name']) ?></option><?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm">Apply</button></noscript>
        </form>
        <span class="chip wh-chip" title="Current warehouse"><?= $v->icon('warehouse') ?>Delhi warehouse</span>
        <a class="chip bell" href="/#attention" aria-label="<?= $attentionCount + $deadCount ?> items need attention"><?= $v->icon('bell') ?><?php if ($attentionCount + $deadCount > 0): ?><span class="dot"><?= $attentionCount + $deadCount ?></span><?php endif; ?></a>
        <details class="user">
          <summary><span class="avatar" aria-label="Account menu"><?= View::e($initials) ?></span></summary>
          <div class="pop">
            <div class="who"><b><?= View::e($u?->name) ?></b><div class="muted small"><?= View::e($u?->email) ?></div>
              <div class="small muted mt-6"><?= View::e(implode(', ', array_map(fn ($a) => \Commerce\Core\Access\Roles::LABELS[$a['role']] . ($a['brand_id'] ? ' (' . ($brands[$a['brand_id']]['name'] ?? '') . ')' : ''), $u?->assignments() ?? []))) ?></div></div>
            <a href="/help">Help &amp; glossary</a>
            <form method="post" action="/logout"><?= $v->csrf() ?><button type="submit">Sign out</button></form>
          </div>
        </details>
      </div>
    </header>
    <main class="content" id="main">
      <?php if ($flash): ?>
        <div class="flash flash-<?= View::e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
          <span class="msg"><?= View::e($flash['message']) ?></span>
          <?php if (!empty($flash['undo'])): ?><form method="post" action="<?= View::e($flash['undo']['action']) ?>"><?= $v->csrf() ?><button class="btn btn-sm"><?= View::e($flash['undo']['label']) ?></button></form><?php endif; ?>
          <button class="x" type="button" aria-label="Dismiss">×</button>
        </div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<dialog class="drawer" id="drawer" aria-labelledby="drawer-title"><div class="drawer-inner" data-drawer-content><div class="drawer-loading" role="status">Loading…</div></div></dialog>
<dialog class="confirm" id="confirm-dialog">
  <div class="d-body"><h2 data-title>Are you sure?</h2><p data-message></p></div>
  <div class="d-foot"><button class="btn" type="button" data-cancel>Cancel</button><button class="btn btn-primary" type="button" data-ok>Confirm</button></div>
</dialog>
</body>
</html>
