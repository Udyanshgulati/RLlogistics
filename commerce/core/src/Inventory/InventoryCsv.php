<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Core\Db\Database;

/**
 * CSV import and export for the Inventory screen.
 *
 * Import columns: sku (required) and exactly one of
 *   counted_quantity  – a stocktake: sets on hand to the counted number, recording the difference, or
 *   received_quantity – units received: added to on hand.
 * Optional: reorder_level.
 * preview() validates every row and writes nothing. apply() imports all valid rows in one transaction,
 * so a refusal on any row (e.g. a count below reserved units) leaves stock exactly as it was.
 */
final class InventoryCsv
{
    public const MAX_ROWS = 5000;

    public function __construct(private readonly Database $db, private readonly Ledger $ledger)
    {
    }

    public function preview(string $csv): array
    {
        $rows = $this->parse($csv);
        if (isset($rows['error'])) {
            return ['rows' => [], 'valid' => 0, 'invalid' => 0, 'error' => $rows['error']];
        }
        $seen = [];
        $out = [];
        foreach ($rows['rows'] as $line => $r) {
            $sku = SkuPolicy::normalise($r['sku'] ?? '');
            $entry = ['line' => $line, 'sku' => $sku, 'action' => null, 'qty' => null, 'reorder_level' => null, 'current' => null, 'result' => null, 'error' => null, 'item_id' => null];
            $counted = trim($r['counted_quantity'] ?? '');
            $received = trim($r['received_quantity'] ?? '');
            $reorder = trim($r['reorder_level'] ?? '');
            $item = $sku === '' ? null : $this->db->one('SELECT i.id FROM inventory_items i WHERE i.sku = ?', [$sku]);
            $entry['error'] = match (true) {
                $sku === '' => 'SKU is empty.',
                isset($seen[$sku]) => "SKU appears more than once in this file (first on line {$seen[$sku]}).",
                $item === null => 'No product has this SKU. Add the product first.',
                $counted !== '' && $received !== '' => 'Fill in either counted_quantity or received_quantity, not both.',
                $counted === '' && $received === '' && $reorder === '' => 'No quantity or reorder level given.',
                $counted !== '' && !self::isCount($counted) => 'counted_quantity must be a whole number, 0 or more.',
                $received !== '' && (!self::isCount($received) || (int) $received === 0) => 'received_quantity must be a whole number greater than 0.',
                $reorder !== '' && !self::isCount($reorder) => 'reorder_level must be a whole number, 0 or more.',
                default => null,
            };
            $seen[$sku] ??= $line;
            if ($entry['error'] === null) {
                $level = $this->ledger->level((int) $item['id']);
                $entry['item_id'] = (int) $item['id'];
                $entry['current'] = $level->onHand;
                if ($counted !== '') {
                    $entry['action'] = 'count';
                    $entry['qty'] = (int) $counted;
                    $entry['result'] = (int) $counted;
                    $holds = $level->reserved + $level->damaged + $level->quarantine;
                    if ((int) $counted < $holds) {
                        $entry['error'] = "Counted {$counted}, but {$holds} units are reserved or held. Check the count.";
                    }
                } elseif ($received !== '') {
                    $entry['action'] = 'receive';
                    $entry['qty'] = (int) $received;
                    $entry['result'] = $level->onHand + (int) $received;
                }
                $entry['reorder_level'] = $reorder === '' ? null : (int) $reorder;
            }
            $out[] = $entry;
        }
        $invalid = count(array_filter($out, fn ($e) => $e['error'] !== null));
        return ['rows' => $out, 'valid' => count($out) - $invalid, 'invalid' => $invalid, 'error' => null];
    }

    /** @return int rows applied */
    public function apply(array $preview, string $actor, string $fileName): int
    {
        $valid = array_filter($preview['rows'], fn ($e) => $e['error'] === null);
        return $this->db->transaction(function () use ($valid, $actor, $fileName) {
            foreach ($valid as $e) {
                $reason = "CSV import {$fileName}, line {$e['line']}";
                if ($e['action'] === 'count') {
                    $this->ledger->stocktake($e['item_id'], $e['qty'], $actor, $reason);
                } elseif ($e['action'] === 'receive') {
                    $this->ledger->adjust($e['item_id'], Adjustment::Received, $e['qty'], $actor, $reason);
                }
                if ($e['reorder_level'] !== null) {
                    $this->db->run('UPDATE inventory_items SET reorder_level = ? WHERE id = ?', [$e['reorder_level'], $e['item_id']]);
                }
            }
            return count($valid);
        });
    }

    /** @param list<array> $rows from InventoryView::list() */
    public static function export(array $rows, array $brandNames): string
    {
        $h = fopen('php://temp', 'w+');
        fputcsv($h, ['sku', 'product', 'size', 'brands_selling', 'sharing', 'on_hand', 'reserved', 'damaged', 'awaiting_inspection', 'available', 'incoming', 'reorder_level', 'status'], escape: '');
        foreach ($rows as $r) {
            $brands = implode(' | ', array_map(fn ($id) => $brandNames[$id] ?? (string) $id, $r['listing_brands']));
            fputcsv($h, array_map(self::safeCell(...), [
                $r['sku'], $r['name'], $r['option_label'] ?? '', $brands, $r['sharing'], $r['on_hand'], $r['reserved'],
                $r['damaged'], $r['quarantine'], $r['available'], $r['incoming'], $r['reorder_level'], $r['status'],
            ]), escape: '');
        }
        rewind($h);
        return (string) stream_get_contents($h);
    }

    /** Stops spreadsheet apps from running a cell as a formula (CSV injection). */
    public static function safeCell(mixed $v): string
    {
        $s = (string) $v;
        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s) ? "'" . $s : $s;
    }

    private function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv); // Excel BOM
        $h = fopen('php://temp', 'w+');
        fwrite($h, (string) $csv);
        rewind($h);
        $header = fgetcsv($h, escape: '');
        if (!$header) {
            return ['error' => 'The file is empty.'];
        }
        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $header);
        if (!in_array('sku', $header, true)) {
            return ['error' => 'The first row must be a header with a "sku" column.'];
        }
        if (!array_intersect(['counted_quantity', 'received_quantity', 'reorder_level'], $header)) {
            return ['error' => 'Add a counted_quantity, received_quantity or reorder_level column.'];
        }
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h, escape: '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', $cells) === '') {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                return ['error' => 'The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.'];
            }
            $rows[$line] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }
        return ['rows' => $rows];
    }

    private static function isCount(string $v): bool
    {
        return preg_match('/^\d{1,9}$/', $v) === 1;
    }
}
