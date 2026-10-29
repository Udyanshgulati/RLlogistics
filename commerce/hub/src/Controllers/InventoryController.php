<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Inventory\InventoryCsv;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

final class InventoryController extends Controller
{
    /** SKUs per page; rows are grouped by product, so a page holds whole size runs in practice. */
    private const PER_PAGE = 200;
    /** Warehouse staff may record only these; the rest need full inventory rights. */
    private const BASIC = [Adjustment::Received, Adjustment::Damaged, Adjustment::ReturnedToInventory];

    public function index(Request $req): Response
    {
        $scope = $this->app->scope('inventory.view');
        $f = ['q' => $req->q('q'), 'category' => $req->q('category'), 'size' => $req->q('size'), 'status' => $req->q('status'), 'sort' => $req->q('sort', 'name'), 'dir' => $req->q('dir', 'asc')];
        $page = max(1, $req->int('page', 1));
        $result = $this->app->services->inventory()->list($f, $scope, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        // Group sizes under their product, keeping the requested sort order of products.
        $groups = [];
        foreach ($result['rows'] as $r) {
            $groups[$r['product_id']] ??= ['product_id' => $r['product_id'], 'name' => $r['name'], 'category' => $r['category'], 'image_url' => $r['image_url'], 'code' => $r['product_code'], 'items' => []];
            $groups[$r['product_id']]['items'][] = $r;
        }
        foreach ($groups as &$g) {
            $sum = fn (string $k) => array_sum(array_column($g['items'], $k));
            $brands = array_values(array_unique(array_merge(...array_column($g['items'], 'listing_brands'))));
            $statuses = array_column($g['items'], 'status');
            $g += ['on_hand' => $sum('on_hand'), 'reserved' => $sum('reserved'), 'available' => $sum('available'), 'incoming' => $sum('incoming'), 'brands' => $brands,
                'status' => in_array('out', $statuses, true) ? 'out' : (in_array('low', $statuses, true) ? 'low' : 'in'),
                'problems' => count(array_filter($statuses, fn ($st) => $st !== 'in')),
                'sharing' => in_array('shared', array_column($g['items'], 'sharing'), true) ? 'shared' : (count($brands) ? 'exclusive' : 'unmapped')];
        }
        unset($g);
        return $this->app->page($req, 'inventory/index', [
            'title' => 'Inventory', 'nav' => 'inventory', 'f' => $f, 'rows' => $result['rows'], 'groups' => array_values($groups), 'total' => $result['total'], 'page' => $page, 'perPage' => self::PER_PAGE,
            'categories' => array_column($this->db->all('SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category'), 'category'),
            'sizes' => $this->app->services->skuPolicy()->sizeRun(),
            'views' => $this->db->all("SELECT name, query FROM saved_views WHERE user_id = ? AND page = 'inventory' ORDER BY name", [$this->app->user->userId]),
        ]);
    }

    public function export(Request $req): Response
    {
        $this->app->user->require('inventory.import', anyBrand: true);
        $scope = $this->app->scope('inventory.view');
        $f = ['q' => $req->q('q'), 'category' => $req->q('category'), 'size' => $req->q('size'), 'status' => $req->q('status'), 'sort' => 'sku'];
        $rows = $this->app->services->inventory()->list($f, $scope, 100000)['rows'];
        if (($ids = $req->q('ids')) !== '') {
            $wanted = array_flip(array_map('intval', explode(',', $ids)));
            $rows = array_values(array_filter($rows, fn ($r) => isset($wanted[(int) $r['id']])));
        }
        $names = array_map(fn ($b) => $b['name'], $this->app->services->brands());
        $this->app->services->audit()->log($this->actor(), 'inventory.export', 'inventory', 'csv', ['rows' => count($rows)], $req->ip);
        return Response::csv('inventory-' . $this->app->clock->now()->format('Y-m-d') . '.csv', InventoryCsv::export($rows, $names));
    }

    public function importForm(Request $req): Response
    {
        $this->app->user->require('inventory.import');
        return $this->app->page($req, 'inventory/import', ['title' => 'Import inventory', 'nav' => 'inventory', 'preview' => null]);
    }

    public function importPreview(Request $req): Response
    {
        $this->app->user->require('inventory.import');
        $file = $req->files['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) && !($this->app->env['TESTING'] ?? false)) {
            throw new LedgerException('Choose a CSV file to upload.');
        }
        if ($file['size'] > 2_000_000) {
            throw new LedgerException('The file is larger than 2 MB. Split it into smaller files.');
        }
        $csv = (string) file_get_contents($file['tmp_name']);
        $preview = $this->app->services->csv()->preview($csv);
        $this->app->session->set('import', ['csv' => $csv, 'name' => basename((string) $file['name']), 'hash' => hash('sha256', $csv)]);
        return $this->app->page($req, 'inventory/import', ['title' => 'Import inventory', 'nav' => 'inventory', 'preview' => $preview, 'fileName' => basename((string) $file['name'])]);
    }

    public function importConfirm(Request $req): Response
    {
        $this->app->user->require('inventory.import');
        $import = $this->app->session->get('import');
        if (!is_array($import) || $req->p('hash') !== $import['hash']) {
            throw new LedgerException('The upload expired. Upload the file again.');
        }
        // Validate again: stock may have changed since the preview.
        $preview = $this->app->services->csv()->preview($import['csv']);
        if ($preview['valid'] === 0) {
            throw new LedgerException('There are no valid rows to import.');
        }
        if ($preview['invalid'] > 0 && $req->p('skip_invalid') !== '1') {
            throw new LedgerException("{$preview['invalid']} rows have problems. Fix the file, or tick “Import the valid rows and skip the rest”.");
        }
        $n = $this->app->services->csv()->apply($preview, $this->actor(), $import['name']);
        $this->app->session->remove('import');
        $this->app->services->audit()->log($this->actor(), 'inventory.import', 'inventory', 'csv', ['file' => $import['name'], 'rows' => $n, 'skipped' => $preview['invalid']], $req->ip);
        $this->app->flash('success', "Imported {$n} rows from {$import['name']}." . ($preview['invalid'] ? " {$preview['invalid']} rows were skipped." : ''));
        return Response::redirect('/inventory');
    }

    public function show(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $this->requireForAny('inventory.view', $this->brandsOfItem($id));
        $item = $this->app->services->inventory()->one($id) ?? throw new HttpError(404, 'This product does not exist.');
        return $this->app->page($req, 'inventory/show', [
            'title' => $item['sku'], 'nav' => 'inventory', 'item' => $item,
            'history' => $this->app->services->inventory()->history($id, 100),
            'listings' => $this->db->all('SELECT l.*, s.name AS store_name, s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.inventory_item_id = ? ORDER BY s.id', [$id]),
            'reservations' => $this->db->all(
                "SELECT r.*, rl.qty, rl.fulfilled_qty, rl.released_qty, o.id AS order_id, o.order_number FROM reservation_lines rl JOIN reservations r ON r.id = rl.reservation_id
                 LEFT JOIN orders o ON o.reservation_id = r.id WHERE rl.inventory_item_id = ? AND r.state IN ('held','confirmed','partially_fulfilled') ORDER BY r.id DESC LIMIT 50",
                [$id],
            ),
            'incomingLines' => $this->db->all("SELECT po.po_number, po.expected_at, l.qty_ordered - l.qty_received - l.qty_missing AS open_qty FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id WHERE l.inventory_item_id = ? AND po.status IN ('ordered','partially_received')", [$id]),
        ]);
    }

    public function adjustForm(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $types = $this->allowedTypes($id);
        $item = $this->app->services->inventory()->one($id) ?? throw new HttpError(404, 'This product does not exist.');
        $vars = ['title' => 'Adjust stock', 'nav' => 'inventory', 'item' => $item, 'types' => $types, 'canCount' => $this->canFull($id)];
        if ($req->q('panel') === '1') {
            // Fragment for the side panel on the inventory list; the full page stays the no-JavaScript route.
            return $this->app->page($req, 'inventory/adjust-panel', $vars + ['bare' => true]);
        }
        return $this->app->page($req, 'inventory/adjust', $vars);
    }

    public function adjust(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $type = Adjustment::tryFrom($req->p('type')) ?? throw new LedgerException('Choose an adjustment type.');
        if (!in_array($type, $this->allowedTypes($id), true)) {
            $this->app->user->require('inventory.adjust');
        }
        $qty = $req->int('quantity', 0, true);
        $before = $this->app->services->ledger()->level($id);
        $after = $this->app->services->ledger()->adjust($id, $type, $qty, $this->actor(), $req->p('reason') ?: null);
        $mid = (int) $this->db->value('SELECT MAX(id) FROM stock_movements WHERE inventory_item_id = ? AND actor = ?', [$id, $this->actor()]);
        $sku = $this->db->value('SELECT sku FROM inventory_items WHERE id = ?', [$id]);
        $this->app->flash('success', "{$type->label()} recorded for {$sku}. On hand {$before->onHand} → {$after->onHand}, available {$before->available()} → {$after->available()}.", ['action' => "/inventory/movements/{$mid}/undo", 'label' => 'Undo']);
        return Response::redirect($req->p('return') === 'list' ? '/inventory' : "/inventory/{$id}");
    }

    /** Bulk: set the same reorder level on the selected SKUs (confirmed in the browser first). */
    public function bulkReorder(Request $req): Response
    {
        $level = $req->int('reorder_level', -1, true);
        $ids = array_values(array_filter(array_map('intval', (array) ($req->post['ids'] ?? []))));
        if ($level < 0 || $ids === []) {
            throw new LedgerException('Select products and enter a reorder level of 0 or more.');
        }
        foreach ($ids as $id) {
            $this->requireForAny('products.manage', $this->brandsOfItem($id));
        }
        $this->db->transaction(function () use ($ids, $level) {
            foreach ($ids as $id) {
                $this->db->run('UPDATE inventory_items SET reorder_level = ?, updated_at = ? WHERE id = ?', [$level, $this->app->clock->now(), $id]);
            }
        });
        $this->app->services->audit()->log($this->actor(), 'item.reorder_level_bulk', 'inventory_item', implode(',', $ids), ['reorder_level' => $level], $req->ip);
        $this->app->flash('success', 'Reorder level set to ' . $level . ' for ' . count($ids) . ' SKU' . (count($ids) === 1 ? '' : 's') . '.');
        return Response::redirect($this->app->back($req));
    }

    public function count(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        if (!$this->canFull($id)) {
            $this->app->user->require('inventory.adjust');
        }
        $counted = $req->int('counted', -1, true);
        $before = $this->app->services->ledger()->level($id);
        $after = $this->app->services->ledger()->stocktake($id, $counted, $this->actor(), $req->p('reason') ?: 'Stocktake');
        $this->app->flash('success', $before->onHand === $after->onHand ? "Count recorded: {$after->onHand} units, no difference." : "Count recorded. On hand {$before->onHand} → {$after->onHand}.");
        return Response::redirect("/inventory/{$id}");
    }

    public function reorder(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $this->requireForAny('products.manage', $this->brandsOfItem($id));
        $level = $req->int('reorder_level', -1, true);
        if ($level < 0) {
            throw new LedgerException('The reorder level must be 0 or more.');
        }
        $this->db->run('UPDATE inventory_items SET reorder_level = ?, updated_at = ? WHERE id = ?', [$level, $this->app->clock->now(), $id]);
        $this->app->services->audit()->log($this->actor(), 'item.reorder_level', 'inventory_item', $id, ['reorder_level' => $level]);
        $this->app->flash('success', "Reorder level set to {$level}.");
        return Response::redirect("/inventory/{$id}");
    }

    public function undo(Request $req, array $p): Response
    {
        $m = $this->db->one('SELECT inventory_item_id FROM stock_movements WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'That change no longer exists.');
        $this->requireForAny('inventory.adjust_basic', $this->brandsOfItem((int) $m['inventory_item_id']));
        $after = $this->app->services->ledger()->reverseAdjustment((int) $p['id'], $this->actor());
        $this->app->flash('success', "Adjustment undone. On hand is {$after->onHand}.");
        return Response::redirect('/inventory/' . $m['inventory_item_id']);
    }

    // ── Incoming stock (purchase orders) ────────────────────────────────────

    public function incoming(Request $req): Response
    {
        $this->app->user->require('purchasing.receive', anyBrand: true);
        $pos = $this->db->all("SELECT po.*, s.name AS supplier FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.status IN ('draft','ordered','partially_received') ORDER BY po.expected_at IS NULL, po.expected_at, po.id");
        $lines = [];
        foreach ($pos as $po) {
            $lines[$po['id']] = $this->db->all('SELECT l.*, i.sku, p.name FROM purchase_order_lines l JOIN inventory_items i ON i.id = l.inventory_item_id JOIN products p ON p.id = i.product_id WHERE l.purchase_order_id = ? ORDER BY i.sku', [$po['id']]);
        }
        return $this->app->page($req, 'inventory/incoming', [
            'title' => 'Incoming stock', 'nav' => 'inventory', 'pos' => $pos, 'lines' => $lines,
            'suppliers' => $this->db->all('SELECT id, name FROM suppliers ORDER BY name'),
        ]);
    }

    public function createPurchaseOrder(Request $req): Response
    {
        $this->app->user->require('purchasing.manage');
        $p = $this->app->services->purchasing();
        $supplier = $req->int('supplier_id', null, true) ?? $p->supplier($req->p('supplier_name'), $req->p('supplier_email'), $req->p('supplier_phone'), $this->actor());
        $lines = [];
        foreach (preg_split('/\R/', $req->p('lines')) as $n => $row) {
            if (trim($row) === '') {
                continue;
            }
            if (!preg_match('/^\s*([A-Za-z0-9-]+)\s*[,;\s]\s*(\d+)\s*$/', $row, $m)) {
                throw new LedgerException('Line ' . ($n + 1) . ': write each item as "SKU, quantity".');
            }
            $item = $this->db->value('SELECT id FROM inventory_items WHERE sku = ?', [strtoupper($m[1])]) ?? throw new LedgerException("Line " . ($n + 1) . ": no product has SKU " . strtoupper($m[1]) . '.');
            $lines[(int) $item] = ($lines[(int) $item] ?? 0) + (int) $m[2];
        }
        $id = $p->create((int) $supplier, $lines, $req->p('expected_at') ?: null, $this->actor());
        $this->app->flash('success', 'Purchase order ' . $this->db->value('SELECT po_number FROM purchase_orders WHERE id = ?', [$id]) . ' created as a draft. Mark it as ordered once it is sent to the supplier.');
        return Response::redirect('/inventory/incoming');
    }

    public function markOrdered(Request $req, array $p): Response
    {
        $this->app->user->require('purchasing.manage');
        $this->app->services->purchasing()->markOrdered((int) $p['id'], $this->actor());
        $this->app->flash('success', 'Marked as ordered. Its units now show as incoming.');
        return Response::redirect('/inventory/incoming');
    }

    public function receiveLine(Request $req, array $p): Response
    {
        $this->app->user->require('purchasing.receive');
        $this->app->services->purchasing()->receive((int) $p['id'], $req->int('good', 0, true), $req->int('damaged', 0, true), $req->int('missing', 0, true), $this->actor());
        $this->app->flash('success', 'Delivery recorded. Good units are now available to sell.');
        return Response::redirect('/inventory/incoming');
    }

    /** @return list<Adjustment> */
    private function allowedTypes(int $itemId): array
    {
        $brands = $this->brandsOfItem($itemId);
        if ($this->canFull($itemId)) {
            return Adjustment::cases();
        }
        $this->requireForAny('inventory.adjust_basic', $brands);
        return self::BASIC;
    }

    private function canFull(int $itemId): bool
    {
        foreach ($this->brandsOfItem($itemId) as $b) {
            if ($this->app->user->can('inventory.adjust', $b)) {
                return true;
            }
        }
        return false;
    }
}
