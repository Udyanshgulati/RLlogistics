<?php

declare(strict_types=1);

// Demonstration data for staging and training. Everything is labelled "Demo"; customers use example.test.
// It goes through the real ledger, order sync and fulfilment code, so the screens show genuine behaviour.
// Loaded by `bin/hub demo` (refused when APP_ENV=production). Expects $db, $clock, $env from bin/hub.

use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Inventory\InsufficientStock;
use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Hub\App;
use Commerce\Hub\Http\ArraySession;

if ((int) $db->value('SELECT COUNT(*) FROM products') > 0) {
    fwrite(STDERR, "Products already exist. Demo data is only loaded into an empty database.\n");
    exit(2);
}
$app = new App($db, $clock, new ArraySession(), $env);
$s = $app->services;
$catalog = $s->catalog();
$ledger = $s->ledger();
$actor = 'system:demo';
mt_srand(20261101);

$db->run("INSERT INTO settings (name, value, updated_by, updated_at) VALUES ('demo_data', '1', ?, ?) ON DUPLICATE KEY UPDATE value = '1'", [$actor, $clock->now()]);

// Products: [brand, code, name, category, sizes?, opening per size, reorder]
$catalogue = [
    [1, 'MI-JER-001', 'Demo match jersey', 'Jerseys', true, [6, 14, 22, 18, 9, 3], 8],
    [1, 'MI-CAP-001', 'Demo team cap', 'Accessories', false, [40], 10],
    [1, 'MI-TEE-001', 'Demo black supporter tee', 'T-shirts', true, [10, 24, 30, 20, 8, 0], 6],
    [2, 'SS-TRK-001', 'Demo training top', 'Training', true, [12, 16, 4, 11, 6, 2], 5],
    [2, 'SS-BAG-001', 'Demo kit bag', 'Accessories', false, [7], 8],
    [3, 'SCH-TEE-001', 'Demo heavyweight tee', 'T-shirts', true, [15, 20, 18, 12, 5, 1], 10],
    [3, 'SCH-HOD-001', 'Demo hoodie', 'Outerwear', true, [4, 9, 0, 6, 3, 2], 4],
];
$items = [];
foreach ($catalogue as [$brand, $code, $name, $cat, $sized, $opening, $reorder]) {
    $ids = $catalog->createProduct($brand, $code, $name, $cat, $sized ? SkuPolicy::APPAREL_SIZES : [], $actor, $sized ? 'apparel_size' : 'none');
    $db->run("UPDATE products SET status = 'active', hs_code = NULL WHERE code = ?", [$code]);
    foreach (array_values($ids) as $i => $itemId) {
        if ($opening[$i] > 0) {
            $ledger->adjust($itemId, Adjustment::Received, $opening[$i], $actor, 'Demo opening stock');
        }
        $db->run('UPDATE inventory_items SET reorder_level = ?, weight_g = ? WHERE id = ?', [$reorder, $sized ? 320 : 150, $itemId]);
        $items[$code][] = $itemId;
    }
}

// Storefront listings. The black tee is deliberately shared by all three brands (one stock record).
$wc = 1000;
$listing = [];
foreach ($catalogue as [$brand, $code]) {
    foreach ($items[$code] as $i => $itemId) {
        $sku = $db->value('SELECT sku FROM inventory_items WHERE id = ?', [$itemId]);
        $stores = $code === 'MI-TEE-001' ? [1, 2, 3] : [$brand];
        foreach ($stores as $store) {
            $wc++;
            $id = $catalog->recordListing($store, $wc, 0, $sku, "Demo listing {$sku}");
            $catalog->mapListing($id, $itemId, $actor, confirmShared: true);
            $listing[$store][$sku] = $wc;
        }
    }
}
// One storefront product nobody has linked yet, to show the "not linked" flow.
$catalog->recordListing(3, 9001, 0, 'SCH-CAP-001', 'Demo cap (not linked yet)');

// Orders through the real sync path.
$countries = ['GB' => 'London', 'US' => 'New York', 'AE' => 'Dubai', 'DE' => 'Berlin', 'IN' => 'Delhi', 'AU' => 'Sydney', 'FR' => 'Paris', 'SG' => 'Singapore'];
$sync = $s->sync();
$ful = $s->fulfilment();
$orderNo = 2400;
$made = [];
for ($n = 0; $n < 26; $n++) {
    $store = [1, 1, 2, 3, 3, 1][$n % 6];
    $skus = array_keys($listing[$store]);
    $pick = array_rand(array_flip($skus), mt_rand(1, 2));
    $pick = (array) $pick;
    $country = array_keys($countries)[$n % count($countries)];
    $placed = $clock->now()->modify('-' . (int) (($n * 37) % (29 * 24)) . ' hours');
    $lines = [];
    $items4 = [];
    $total = 0;
    foreach ($pick as $i => $sku) {
        $qty = mt_rand(1, 2);
        $price = str_starts_with($sku, 'SCH') ? 2499 : (str_contains($sku, 'JER') ? 3999 : 1899);
        $lines[] = ['id' => $i + 1, 'product_id' => $listing[$store][$sku], 'variation_id' => 0, 'sku' => $sku, 'name' => 'Demo ' . strtolower(explode('-', $sku)[1]) . ' ' . $sku, 'quantity' => $qty, 'subtotal' => (string) ($price * $qty), 'total' => (string) ($price * $qty)];
        $items4[(int) $db->value("SELECT inventory_item_id FROM listings WHERE store_id = ? AND wc_product_id = ?", [$store, $listing[$store][$sku]])] = $qty;
        $total += $price * $qty;
    }
    $orderNo++;
    try {
        $ledger->reserve($store, (string) $orderNo, $items4, "store:{$store}:order:{$orderNo}", 2700, 172800);
    } catch (InsufficientStock) {
        continue;
    }
    $status = $n % 9 === 4 ? 'pending' : ($n % 11 === 7 ? 'failed' : 'processing');
    $snap = [
        'id' => $orderNo, 'number' => (string) $orderNo, 'status' => $status, 'currency' => 'INR',
        'date_created_gmt' => $placed->format('Y-m-d\TH:i:s'), 'date_modified_gmt' => $placed->modify('+4 minutes')->format('Y-m-d\TH:i:s'),
        'total' => (string) ($total + 1500), 'shipping_total' => '1500', 'discount_total' => '0', 'total_tax' => '0',
        'payment_method' => 'demo', 'transaction_id' => $status === 'processing' ? 'demo_' . $orderNo : '',
        'billing' => ['first_name' => 'Demo', 'last_name' => 'Customer ' . ($n + 1), 'email' => 'customer' . ($n + 1) . '@example.test', 'country' => $country],
        'shipping' => ['first_name' => 'Demo', 'last_name' => 'Customer ' . ($n + 1), 'address_1' => ($n + 10) . ' Example Street', 'city' => $countries[$country], 'postcode' => '00000', 'country' => $country],
        'shipping_lines' => [['method_title' => 'Express (demo)']], 'line_items' => $lines, 'refunds' => [],
    ];
    $sync->ingest($store, "demo-{$orderNo}", $snap);
    $made[] = [(int) $db->value('SELECT id FROM orders WHERE store_id = ? AND wc_order_id = ?', [$store, $orderNo]), $status, $n];
}

// Move paid orders along so every stage of the workflow is visible.
foreach ($made as [$id, $status, $n]) {
    if ($status !== 'processing') {
        continue;
    }
    $stage = $n % 7;
    if ($stage >= 1) $ful->startProcessing($id, $actor);
    if ($stage >= 2) $ful->markPicked($id, $actor);
    if ($stage >= 3) $ful->markPacked($id, $actor);
    if ($stage >= 4) {
        $ship = $ful->createShipment($id, 'Demo carrier', 'Express (demo)', 'DEMO' . str_pad((string) $id, 8, '0', STR_PAD_LEFT), null, $actor);
        if ($stage >= 5) {
            $ful->dispatch($ship, $actor);
            $at = $clock->now()->modify('-1 day');
            $ful->recordTracking($ship, "demo-{$ship}-1", 'in_transit', 'Departed Delhi hub (demo event)', 'Delhi', $at);
            if ($stage === 6) {
                $ful->recordTracking($ship, "demo-{$ship}-2", 'delivered', 'Delivered (demo event)', null, $clock->now()->modify('-2 hours'));
            }
        }
    }
}
// One delivery problem and one return awaiting inspection.
$exc = $db->value("SELECT id FROM shipments WHERE status = 'dispatched' ORDER BY id LIMIT 1");
if ($exc) {
    $ful->recordTracking((int) $exc, "demo-{$exc}-x", 'exception', 'Recipient not available (demo event)', null, $clock->now()->modify('-3 hours'));
}
$delivered = $db->value("SELECT o.id FROM orders o WHERE o.fulfilment_status = 'delivered' ORDER BY o.id LIMIT 1");
if ($delivered) {
    $line = $db->one('SELECT id, qty_shipped FROM order_lines WHERE order_id = ? LIMIT 1', [$delivered]);
    $rid = $s->returns()->open((int) $delivered, [(int) $line['id'] => 1], 'Demo: wrong size', 'exchange', $actor);
    $rl = (int) $db->value('SELECT id FROM return_lines WHERE return_id = ?', [$rid]);
    $s->returns()->receive($rl, 1, $actor);
}
// A purchase order on the way.
$po = $s->purchasing()->create($s->purchasing()->supplier('Demo supplier', null, null, $actor), [$items['SCH-HOD-001'][2] => 12, $items['SS-TRK-001'][5] => 10], $clock->now()->modify('+6 days')->format('Y-m-d'), $actor);
$s->purchasing()->markOrdered($po, $actor);

// Demo team, one per role, with a shared password printed once.
$password = $env['DEMO_PASSWORD'] ?? bin2hex(random_bytes(6));
foreach (['owner' => [null, 'Demo Owner'], 'ops_manager' => [null, 'Demo Operations'], 'warehouse' => [null, 'Demo Warehouse'], 'brand_manager' => [3, 'Demo Schermo Manager'], 'finance' => [null, 'Demo Finance'], 'support' => [null, 'Demo Support']] as $role => [$brand, $name]) {
    $uid = $db->insert('INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)', [str_replace('_', '-', $role) . '@example.test', $name, password_hash($password, Commerce\Hub\Controllers\AuthController::algo()), $clock->now()]);
    $db->run('INSERT INTO user_roles (user_id, role, brand_id) VALUES (?, ?, ?)', [$uid, $role, $brand]);
}
echo "Demo data loaded: " . $db->value('SELECT COUNT(*) FROM inventory_items') . " SKUs, " . $db->value('SELECT COUNT(*) FROM orders') . " orders.\n";
echo "Demo sign-ins: owner@example.test, ops-manager@, warehouse@, brand-manager@ (Schermo only), finance@, support@example.test\nPassword: {$password}\n";
