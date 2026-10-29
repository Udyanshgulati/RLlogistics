<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Inventory;

use Commerce\Core\Tests\Support\LedgerTestCase;

/**
 * Real races: separate OS processes, separate database connections, released from a shared start instant.
 * Each scenario runs several rounds because a single race can pass by luck.
 */
final class ConcurrencyTest extends LedgerTestCase
{
    private const ROUNDS = 15;

    public function testTwoStorefrontsRacingForTheLastUnit(): void
    {
        for ($round = 0; $round < self::ROUNDS; $round++) {
            $item = $this->item(sprintf('MI-TEE-%03d', 100 + $round), 1);
            $results = $this->race([
                ['mode' => 'reserve', 'store' => 1, 'order' => "mi-{$round}", 'key' => "s1:{$round}", 'items' => [$item => 1]], // Milan Icons
                ['mode' => 'reserve', 'store' => 3, 'order' => "sch-{$round}", 'key' => "s3:{$round}", 'items' => [$item => 1]], // Schermo
            ]);
            $this->assertSame(1, self::successes($results), "round {$round}: exactly one order gets the unit");
            $this->assertSame(['insufficient'], array_values(array_column(array_filter($results, fn ($r) => !$r['ok']), 'error')), "round {$round}: the other is told it is sold out");
            $this->assertSame(0, $this->available($item));
            $this->assertSame(1, $this->ledger->level($item)->reserved);
        }
    }

    public function testManyBuyersNeverExceedStock(): void
    {
        $item = $this->item('MI-TEE-200', 10);
        $jobs = [];
        for ($i = 0; $i < 40; $i++) {
            $jobs[] = ['mode' => 'reserve', 'store' => $i % 3 + 1, 'order' => "o{$i}", 'key' => "k{$i}", 'items' => [$item => 1]];
        }
        $results = $this->race($jobs);
        $this->assertSame(10, self::successes($results));
        $this->assertSame(10, $this->ledger->level($item)->reserved);
        $this->assertSame(0, $this->available($item));
    }

    public function testMultiLineOrdersInOppositeOrderDoNotDeadlockOrOversell(): void
    {
        $a = $this->item('MI-TEE-301', 6);
        $b = $this->item('MI-TEE-302', 6);
        $jobs = [];
        for ($i = 0; $i < 20; $i++) {
            // Half the carts list A first, half list B first; the ledger locks in item-id order regardless.
            $items = $i % 2 ? [$a => 1, $b => 1] : [$b => 1, $a => 1];
            $jobs[] = ['mode' => 'reserve', 'store' => $i % 3 + 1, 'order' => "o{$i}", 'key' => "k{$i}", 'items' => $items];
        }
        $results = $this->race($jobs);
        $errors = array_filter(array_column($results, 'error'), fn ($e) => $e !== 'insufficient');
        $this->assertSame([], array_values($errors), 'no deadlock or other errors surfaced');
        $this->assertSame(6, self::successes($results));
        $this->assertSame(0, $this->available($a));
        $this->assertSame(0, $this->available($b));
    }

    public function testSameCheckoutSubmittedConcurrentlyReservesOnce(): void
    {
        $item = $this->item('MI-TEE-400', 5);
        $jobs = array_fill(0, 10, ['mode' => 'reserve', 'store' => 2, 'order' => 'o1', 'key' => 'store:2:order:o1', 'items' => [$item => 2]]);
        $results = $this->race($jobs);
        $this->assertSame(10, self::successes($results), 'every duplicate gets the same answer');
        $this->assertCount(1, array_unique(array_column($results, 'reservationId')));
        $this->assertSame(9, count(array_filter($results, fn ($r) => $r['replayed'] ?? false)));
        $this->assertSame(2, $this->ledger->level($item)->reserved, 'reserved once, not ten times');
    }

    public function testPaymentConfirmationRacingTheExpiryJob(): void
    {
        for ($round = 0; $round < self::ROUNDS; $round++) {
            $item = $this->item(sprintf('MI-TEE-%03d', 500 + $round), 1);
            $r = $this->ledger->reserve(1, "o{$round}", [$item => 1], "k{$round}", 900, 3600);
            $later = $this->clock->now()->modify('+1000 seconds')->format('Y-m-d H:i:s');
            $results = $this->race([
                ['mode' => 'confirm', 'reservation' => $r->reservationId, 'now' => $later],
                ['mode' => 'expire', 'reservation' => $r->reservationId, 'now' => $later],
            ]);
            $this->assertSame(2, self::successes($results), "round {$round}: " . json_encode($results));
            // Whichever wins, a paid order ends up holding its unit and nothing is double counted.
            $this->assertSame('confirmed', $this->ledger->reservation($r->reservationId)['state']);
            $this->assertSame(1, $this->ledger->level($item)->reserved);
        }
    }

    /** Negative control: an unguarded read-then-write does oversell under the same harness. */
    public function testHarnessDetectsOversellInANaiveImplementation(): void
    {
        $oversold = 0;
        for ($round = 0; $round < 5 && $oversold === 0; $round++) {
            $item = $this->item(sprintf('MI-TEE-%03d', 600 + $round), 1);
            $results = $this->race([
                ['mode' => 'naive', 'items' => [$item => 1]],
                ['mode' => 'naive', 'items' => [$item => 1]],
            ]);
            if (self::successes($results) > 1) {
                $oversold++;
            }
            // Undo the naive writes (they bypassed the movement ledger) so tearDown's reconciliation holds.
            $this->db->run('UPDATE stock_levels SET reserved = 0 WHERE inventory_item_id = ?', [$item]);
        }
        $this->assertSame(1, $oversold, 'the naive version sold one unit twice, proving the race is real');
    }

    /** Starts every job in its own PHP process and releases them together. */
    private function race(array $jobs): array
    {
        $start = microtime(true) + 0.6; // time for every process to boot and connect
        $procs = [];
        foreach ($jobs as $job) {
            $job['start'] = $start;
            $cmd = [PHP_BINARY, __DIR__ . '/../workers/reserve.php', json_encode($job)];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[] = [$p, $pipes];
        }
        $out = [];
        foreach ($procs as [$p, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            proc_close($p);
            $decoded = json_decode((string) $stdout, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException("Worker failed: {$stdout} {$stderr}");
            }
            $out[] = $decoded;
        }
        return $out;
    }

    private static function successes(array $results): int
    {
        return count(array_filter($results, fn ($r) => $r['ok']));
    }
}
