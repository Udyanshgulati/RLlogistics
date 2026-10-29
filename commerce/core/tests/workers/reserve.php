<?php

declare(strict_types=1);

// Child process for the concurrency tests. Waits for a shared start instant, then performs one operation
// on its own database connection and prints a JSON result. Never run against a non-test database (TestDb enforces this).

require __DIR__ . '/../../src/autoload.php';

use Commerce\Core\Inventory\InsufficientStock;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Support\FixedClock;
use Commerce\Core\Tests\Support\TestDb;

$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$db = TestDb::connect();
$ledger = new Ledger($db, new FixedClock($job['now'] ?? '2026-11-01 10:00:00'));
$items = [];
foreach ($job['items'] ?? [] as $id => $q) {
    $items[(int) $id] = (int) $q;
}

while (microtime(true) < $job['start']) {
    // spin: every worker leaves the barrier within microseconds of the others
}

try {
    $result = match ($job['mode']) {
        'reserve' => (array) $ledger->reserve($job['store'], $job['order'], $items, $job['key'], 900, 86400),
        'confirm' => (array) $ledger->confirm($job['reservation'], 'pay_' . getmypid(), 'test'),
        'expire' => ['expired' => $ledger->expire($job['reservation'], 'test')],
        'naive' => naiveReserve($db, array_key_first($items), reset($items)),
    };
    echo json_encode($result + ['ok' => true]);
} catch (InsufficientStock $e) {
    echo json_encode(['ok' => false, 'error' => 'insufficient']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()]);
}

/** Negative control: read-check-write without a guard. Shows the harness can detect a lost update. */
function naiveReserve(Commerce\Core\Db\Database $db, int $item, int $q): array
{
    $row = $db->one('SELECT on_hand, reserved, damaged, quarantine FROM stock_levels WHERE inventory_item_id = ?', [$item]);
    $available = $row['on_hand'] - $row['reserved'] - $row['damaged'] - $row['quarantine'];
    if ($available < $q) {
        throw new InsufficientStock([$item => ['requested' => $q, 'available' => $available]]);
    }
    usleep(50_000); // widen the window between read and write
    $db->run('UPDATE stock_levels SET reserved = ? WHERE inventory_item_id = ?', [$row['reserved'] + $q, $item]);
    return ['state' => 'held'];
}
