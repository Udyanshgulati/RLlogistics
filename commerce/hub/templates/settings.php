<?php use Commerce\Hub\View; $addr = json_decode((string) ($warehouse['address_json'] ?? ''), true) ?: []; ?>
<div class="page-head"><div><h1>Settings</h1><p>Storefronts, the warehouse and market rules. Owner only.</p></div></div>
<section class="card">
  <div class="card-head"><div><h2>Storefronts</h2><p>API keys and webhook secrets live in the server's environment file, never here. This table only shows whether each one is present.</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>Storefront</th><th>Address</th><th>Status</th><th>REST key</th><th>Checkout secret</th><th>Webhook secret</th><th>Last contact</th><th></th></tr></thead><tbody>
    <?php foreach ($stores as $s): $sec = $secrets[$s['code']]; ?><tr>
      <td><?= $v->brand($s['brand_id']) ?></td>
      <td colspan="1"><form id="st<?= (int) $s['id'] ?>" method="post" action="/settings/stores/<?= (int) $s['id'] ?>" class="inline-form"><?= $v->csrf() ?><label class="sr-only" for="su<?= (int) $s['id'] ?>">Address</label><input class="input btn-sm" id="su<?= (int) $s['id'] ?>" name="base_url" value="<?= View::e($s['base_url'] ?? '') ?>" placeholder="https://… (awaiting client)"></form></td>
      <td><select class="select btn-sm" name="status" form="st<?= (int) $s['id'] ?>" aria-label="Status"><?php foreach (['not_connected' => 'Not connected', 'connected' => 'Connected', 'paused' => 'Paused'] as $k => $l): ?><option value="<?= $k ?>"<?= $s['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></td>
      <td><?= $v->badge($sec['rest'] ? 'connected' : 'not_connected') ?></td><td><?= $v->badge($sec['hub'] ? 'connected' : 'not_connected') ?></td><td><?= $v->badge($sec['webhook'] ? 'connected' : 'not_connected') ?></td>
      <td class="small"><?= $v->date($s['last_seen_at']) ?></td>
      <td><button class="btn btn-sm" form="st<?= (int) $s['id'] ?>">Save</button></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<div class="grid-half">
  <form class="card" method="post" action="/settings/warehouse">
    <?= $v->csrf() ?>
    <div class="card-head"><div><h2>Delhi warehouse</h2><p>Needed before carriers can quote or book pickups.</p></div><?= $v->badge($addr ? 'connected' : 'not_connected') ?></div>
    <div class="card-body form-grid">
      <div class="field span-2"><label for="w1">Street address</label><input class="input" id="w1" name="line1" value="<?= View::e($addr['line1'] ?? '') ?>" required></div>
      <div class="field span-2"><label for="w2">Address line 2</label><input class="input" id="w2" name="line2" value="<?= View::e($addr['line2'] ?? '') ?>"></div>
      <div class="field"><label for="wc">City</label><input class="input" id="wc" name="city" value="<?= View::e($addr['city'] ?? 'Delhi') ?>"></div>
      <div class="field"><label for="ws">State</label><input class="input" id="ws" name="state" value="<?= View::e($addr['state'] ?? '') ?>"></div>
      <div class="field"><label for="wp">PIN code</label><input class="input" id="wp" name="postcode" value="<?= View::e($addr['postcode'] ?? '') ?>" pattern="\d{6}" inputmode="numeric" required></div>
      <div class="field"><label for="wh">Pickup hours</label><input class="input" id="wh" name="pickup_hours" value="<?= View::e($addr['pickup_hours'] ?? '') ?>" placeholder="e.g. Mon–Sat 10:00–18:00"></div>
      <div class="field"><label for="wn">Contact name</label><input class="input" id="wn" name="contact_name" value="<?= View::e($addr['contact_name'] ?? '') ?>"></div>
      <div class="field"><label for="wt">Contact phone</label><input class="input" id="wt" name="contact_phone" value="<?= View::e($addr['contact_phone'] ?? '') ?>"></div>
      <div class="span-2"><button class="btn btn-primary">Save warehouse</button></div>
    </div>
  </form>
  <section class="card">
    <div class="card-head"><div><h2>Markets, currencies and payments</h2><p>Read-only here. Changed in the market configuration file and reviewed before deploy.</p></div></div>
    <div class="card-body stack">
      <?php if ($marketError): ?><div class="flash flash-error"><span class="msg"><?= View::e($marketError) ?></span></div>
      <?php else: ?>
        <?php if (str_starts_with((string) ($markets['_status'] ?? ''), 'PLACEHOLDER')): ?><div class="notice notice-warn">The market rules are placeholders awaiting the client's tax adviser and the payment providers' confirmation. No currency is chargeable until a gateway is verified.</div><?php endif; ?>
        <div class="table-wrap"><table><thead><tr><th>Market</th><th>Shows prices in</th><th>Can charge in</th><th>Duties</th></tr></thead><tbody>
          <?php foreach ($markets['markets'] as $code => $m): ?><tr><td><b><?= View::e($m['name']) ?></b><span class="sub"><?= is_array($m['countries']) ? count($m['countries']) . ' countries' : 'All other countries' ?></span></td><td><?= View::e($m['display_currency']) ?></td><td class="small"><?= View::e(implode(', ', $m['checkout_currencies'])) ?></td><td class="small"><?= View::e(strtoupper($m['duties']['mode'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <div class="table-wrap"><table><thead><tr><th>Payment provider</th><th>Enabled</th><th>Verified currencies</th></tr></thead><tbody>
          <?php foreach ($markets['gateways'] as $id => $g): ?><tr><td><?= View::e($g['label'] ?? $id) ?></td><td><?= $v->badge(!empty($g['enabled']) ? 'connected' : 'not_connected') ?></td><td class="small"><?= View::e(implode(', ', $g['verified_currencies']) ?: 'None verified yet') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p class="muted small">Carrier integrations: none connected yet. Each needs an account and a sandbox test before it can quote rates or book shipments.</p>
      <?php endif; ?>
    </div>
  </section>
</div>
