<?php use Commerce\Hub\View; use Commerce\Core\Access\Roles; $brands = $app->services->brands(); ?>
<div class="page-head"><div><h1>Team &amp; permissions</h1><p>Give each person only the access their work needs. Roles can be limited to one brand.</p></div></div>
<?php if ($setupLink): $app->session->remove('setup_link'); ?>
  <div class="notice notice-info mb-16"><b>Set-up link for <?= View::e($setupLink['name']) ?>.</b> Send it to them privately (it is shown only once and expires in 72 hours):<br><span class="mono"><?= View::e($setupLink['link']) ?></span></div>
<?php endif; ?>
<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>People</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Roles</th><th>Last sign-in</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?><tr>
        <td><b><?= View::e($u['name']) ?></b><span class="sub"><?= View::e($u['email']) ?></span><?= $u['status'] === 'disabled' ? $v->badge('cancelled') : '' ?></td>
        <td><form method="post" action="/team/<?= (int) $u['id'] ?>/roles" class="stack" data-confirm="Their access changes from their next page view." data-confirm-title="Change roles for <?= View::e($u['name']) ?>?">
          <?= $v->csrf() ?>
          <?php $have = array_map(fn ($r) => $r['role'] . ':' . ($r['brand_id'] ?? ''), $roles[$u['id']] ?? []); ?>
          <details><summary class="small"><?= View::e(implode(', ', array_map(fn ($r) => Roles::LABELS[$r['role']] . ($r['brand_id'] ? ' · ' . $brands[$r['brand_id']]['name'] : ''), $roles[$u['id']] ?? [])) ?: 'No roles') ?> <span class="link">Change</span></summary>
            <div class="stack mt-12 small"><?php foreach (Roles::LABELS as $role => $label): ?><div class="gap-row"><b class="w-full"><?= View::e($label) ?></b>
              <label class="gap-row"><input type="checkbox" name="roles[]" value="<?= $role ?>:"<?= in_array("{$role}:", $have, true) ? ' checked' : '' ?>> All brands</label>
              <?php if ($role !== 'owner'): foreach ($brands as $b): ?><label class="gap-row"><input type="checkbox" name="roles[]" value="<?= $role ?>:<?= (int) $b['id'] ?>"<?= in_array("{$role}:{$b['id']}", $have, true) ? ' checked' : '' ?>> <?= View::e($b['name']) ?></label><?php endforeach; endif; ?>
            </div><?php endforeach; ?><button class="btn btn-sm">Save roles</button></div>
          </details></form></td>
        <td class="small nowrap"><?= $v->date($u['last_login_at']) ?></td>
        <td class="text-right"><?php if ((int) $u['id'] !== $app->user->userId): ?><form method="post" action="/team/<?= (int) $u['id'] ?>/status" data-confirm="<?= $u['status'] === 'active' ? 'They will be signed out and unable to sign in.' : 'They will be able to sign in again.' ?>" data-confirm-title="<?= $u['status'] === 'active' ? 'Remove access?' : 'Restore access?' ?>"><?= $v->csrf() ?><input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>"><button class="btn btn-sm<?= $u['status'] === 'active' ? ' btn-danger' : '' ?>"><?= $u['status'] === 'active' ? 'Remove access' : 'Restore access' ?></button></form><?php endif; ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <div class="stack">
    <form class="card" method="post" action="/team">
      <?= $v->csrf() ?>
      <div class="card-head"><div><h2>Invite someone</h2><p>They get a one-time link to set their own password.</p></div></div>
      <div class="card-body stack">
        <div class="field"><label for="tn">Name</label><input class="input" id="tn" name="name" required></div>
        <div class="field"><label for="te">Email</label><input class="input" id="te" name="email" type="email" required></div>
        <div class="field"><label for="tr">Role</label><select class="select" id="tr" name="role"><?php foreach (Roles::LABELS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'warehouse' ? ' selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="tb">Brands</label><select class="select" id="tb" name="brand_id"><option value="">All brands</option><?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>"><?= View::e($b['name']) ?> only</option><?php endforeach; ?></select></div>
        <button class="btn btn-primary">Create set-up link</button>
      </div>
    </form>
    <section class="card"><div class="card-head"><h2>What each role can do</h2></div><div class="card-body stack small">
      <?php foreach (Roles::LABELS as $role => $label): ?><details><summary><b><?= View::e($label) ?></b></summary><ul class="mt-6"><?php foreach (Roles::permissionsOf($role) as $perm): ?><li><?= View::e(Roles::PERMISSIONS[$perm]) ?></li><?php endforeach; ?></ul></details><?php endforeach; ?>
    </div></section>
  </div>
</div>
