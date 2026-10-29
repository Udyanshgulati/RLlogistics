<?php use Commerce\Hub\View; $pages = max(1, (int) ceil($total / $perPage)); ?>
<div class="pager">
  <span><?= $total === 0 ? 'No results' : (($page - 1) * $perPage + 1) . '–' . min($total, $page * $perPage) . ' of ' . number_format($total) ?></span>
  <?php if ($pages > 1): ?><span class="gap-row">
    <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= $v->url($req->path, $req->query, ['page' => $page - 1]) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= $v->url($req->path, $req->query, ['page' => $page + 1]) ?>">Next</a><?php endif; ?>
  </span><?php endif; ?>
</div>
