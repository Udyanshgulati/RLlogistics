<div class="drawer-head"><h2 id="drawer-title">Adjust stock</h2><button class="btn btn-ghost btn-sm" type="button" data-drawer-close aria-label="Close">✕</button></div>
<div class="drawer-body"><?= $v->partial('partials/adjust-form', ['item' => $item, 'types' => $types, 'return' => 'list']) ?></div>
