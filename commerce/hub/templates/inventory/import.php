<?php use Commerce\Hub\View; $import = $app->session->get('import'); ?>
<div class="page-head"><div><div class="crumb"><a href="/inventory">Inventory</a> / Import</div><h1>Import inventory</h1><p>Upload a CSV to record counts or deliveries for many SKUs at once.</p></div></div>
<?php if ($preview === null): ?>
<div class="grid-2">
  <form class="card" method="post" action="/inventory/import" enctype="multipart/form-data">
    <?= $v->csrf() ?>
    <div class="card-body stack">
      <div class="field"><label for="file">CSV file</label><input class="input" id="file" name="file" type="file" accept=".csv,text/csv" required><p class="help">Up to 5,000 rows and 2 MB. Nothing changes until you review and confirm.</p></div>
      <button class="btn btn-primary">Upload and review</button>
    </div>
  </form>
  <section class="card"><div class="card-head"><h2>File format</h2></div><div class="card-body stack small">
    <p>First row is the header. Use <span class="mono">sku</span> and one of:</p>
    <p><span class="mono">counted_quantity</span> — a stocktake: on hand becomes this number.<br><span class="mono">received_quantity</span> — units received: added to on hand.</p>
    <p>Optional: <span class="mono">reorder_level</span>.</p>
    <pre class="notice mono">sku,counted_quantity,reorder_level
MI-JER-001-M,25,10
MI-JER-001-L,18,10</pre>
    <p>Tip: <a class="link" href="/inventory/export">export inventory</a> first, edit the numbers, and import it back.</p>
  </div></section>
</div>
<?php else: ?>
<div class="card">
  <div class="card-head"><div><h2>Review <?= View::e($fileName) ?></h2><p><?= $preview['valid'] ?> rows ready<?= $preview['invalid'] ? ', ' . $preview['invalid'] . ' with problems' : '' ?>.</p></div></div>
  <?php if ($preview['error']): ?><div class="card-body"><div class="flash flash-error"><span class="msg"><?= View::e($preview['error']) ?></span></div><a class="btn" href="/inventory/import">Upload another file</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Line</th><th>SKU</th><th>Change</th><th class="num">On hand now</th><th class="num">After import</th><th>Check</th></tr></thead>
    <tbody><?php foreach ($preview['rows'] as $r): ?><tr>
      <td class="num"><?= (int) $r['line'] ?></td><td class="mono"><?= View::e($r['sku']) ?></td>
      <td><?= $r['action'] === 'count' ? 'Count' : ($r['action'] === 'receive' ? 'Received +' . (int) $r['qty'] : ($r['reorder_level'] !== null ? 'Reorder level' : '—')) ?><?= $r['reorder_level'] !== null && $r['action'] ? ' · reorder ' . (int) $r['reorder_level'] : '' ?></td>
      <td class="num"><?= $r['current'] ?? '—' ?></td><td class="num"><b><?= $r['result'] ?? '—' ?></b></td>
      <td><?= $r['error'] === null ? $v->badge('in') : '<span class="attn">' . View::e($r['error']) . '</span>' ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <form class="card-body gap-row" method="post" action="/inventory/import/confirm" data-confirm="<?= $preview['valid'] ?> rows will change stock. Every change is recorded in each product's history." data-confirm-title="Import <?= $preview['valid'] ?> rows?" data-confirm-ok="Import">
    <?= $v->csrf() ?><input type="hidden" name="hash" value="<?= View::e($import['hash'] ?? '') ?>">
    <?php if ($preview['invalid']): ?><label class="gap-row"><input type="checkbox" name="skip_invalid" value="1"> Import the valid rows and skip the rest</label><?php endif; ?>
    <button class="btn btn-primary"<?= $preview['valid'] ? '' : ' disabled' ?>>Import <?= $preview['valid'] ?> rows</button>
    <a class="btn btn-ghost" href="/inventory/import">Upload a different file</a>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
