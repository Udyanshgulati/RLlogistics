<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Support;

/** Minimal PHPUnit-compatible assertions, so the suite runs with no third-party code (bin/test). */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(($msg ? "{$msg}\n" : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    protected function assertTrue(bool $v, string $msg = ''): void
    {
        $this->assertSame(true, $v, $msg);
    }

    protected function assertFalse(bool $v, string $msg = ''): void
    {
        $this->assertSame(false, $v, $msg);
    }

    protected function assertNull(mixed $v, string $msg = ''): void
    {
        $this->assertSame(null, $v, $msg);
    }

    protected function assertCount(int $n, array $a, string $msg = ''): void
    {
        $this->assertSame($n, count($a), $msg);
    }

    protected function assertContains(mixed $needle, array $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionFailed(($msg ? "{$msg}\n" : '') . var_export($needle, true) . ' not found in ' . var_export($haystack, true));
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(($msg ? "{$msg}\n" : '') . "\"{$needle}\" not found in \"{$haystack}\"");
        }
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T
     */
    protected function assertThrows(string $class, callable $fn, ?string $messageContains = null): \Throwable
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new AssertionFailed("Expected {$class}, got " . $e::class . ': ' . $e->getMessage());
            }
            if ($messageContains !== null && !str_contains($e->getMessage(), $messageContains)) {
                throw new AssertionFailed("Exception message \"{$e->getMessage()}\" does not contain \"{$messageContains}\"");
            }
            return $e;
        }
        throw new AssertionFailed("Expected {$class} to be thrown");
    }
}
