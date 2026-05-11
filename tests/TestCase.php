<?php

namespace Holyc;

/**
 * Marker exception thrown by TestCase::skip() to signal that a test should
 * be reported as skipped rather than passed or failed.
 */
class SkipTest extends \Exception {}

/**
 * Tiny assertion-based test base class. No packages — written from scratch.
 *
 * Subclass and define methods named `test...()`. The runner discovers them
 * via `get_class_methods` and invokes each one in turn. `setUp()` / `tearDown()`
 * are called before/after each test if defined.
 *
 * Each assertion records its caller's file:line via `debug_backtrace` so
 * failure messages point at the actual line in the test file, not at this
 * base class.
 */
abstract class TestCase {
    private int $assertions = 0;

    /**
     * Run every `test*` method on this suite. If `$filter` is given, only
     * methods whose `Suite::method` name or humanised label contains it
     * (case-insensitive) are executed. Returns a list of result rows:
     *   ['name', 'method', 'status' => 'pass'|'fail', 'error', 'where']
     * plus the count of skipped tests.
     */
    public function run(?string $filter = null): array {
        $methods = array_values(array_filter(
            get_class_methods($this),
            fn ($m) => str_starts_with($m, 'test')
        ));

        $cls = $this->name();
        $results = [];
        $skipped = 0;
        foreach ($methods as $m) {
            $human = $this->humanize($m);
            if ($filter !== null) {
                $hay = "$cls::$m\n$human";
                if (stripos($hay, $filter) === false) {
                    $skipped++;
                    continue;
                }
            }

            $row = [
                'name'   => $human,
                'method' => $m,
                'status' => 'pass',
                'error'  => null,
                'where'  => null,
            ];
            try {
                if (method_exists($this, 'setUp')) $this->setUp();
                $this->$m();
                if (method_exists($this, 'tearDown')) $this->tearDown();
            } catch (SkipTest $e) {
                $row['status'] = 'skip';
                $row['error']  = $e->getMessage();
            } catch (\Throwable $e) {
                $row['status'] = 'fail';
                $row['error']  = $e->getMessage();
                $row['where']  = $this->locate($e);
            }
            $results[] = $row;
        }
        return ['results' => $results, 'skipped' => $skipped];
    }

    protected function skip(string $reason): never {
        throw new SkipTest($reason);
    }

    public function name(): string {
        $cls = static::class;
        $pos = strrpos($cls, '\\');
        return $pos === false ? $cls : substr($cls, $pos + 1);
    }

    public function assertionCount(): int {
        return $this->assertions;
    }

    /**
     * Find the first frame inside the actual test file (not this base class).
     */
    private function locate(\Throwable $e): string {
        foreach ($e->getTrace() as $frame) {
            if (!isset($frame['file'])) continue;
            if (str_ends_with($frame['file'], 'TestCase.php')) continue;
            return basename($frame['file']) . ':' . ($frame['line'] ?? '?');
        }
        return basename($e->getFile()) . ':' . $e->getLine();
    }

    private function humanize(string $name): string {
        $name = preg_replace('/^test/', '', $name);
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $name);
        return strtolower($name);
    }

    /* ------------------------------------------------------------------ *
     * Assertions
     * ------------------------------------------------------------------ */

    protected function assertEquals($expected, $actual, string $msg = ''): void {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail("expected: " . $this->display($expected)
                      . "\nactual:   " . $this->display($actual), $msg);
        }
    }

    protected function assertNull($actual, string $msg = ''): void {
        $this->assertEquals(null, $actual, $msg);
    }

    protected function assertNotNull($actual, string $msg = ''): void {
        $this->assertions++;
        if ($actual === null) {
            $this->fail("expected non-null, got null", $msg);
        }
    }

    protected function assertTrue($actual, string $msg = ''): void {
        $this->assertEquals(true, $actual, $msg);
    }

    protected function assertFalse($actual, string $msg = ''): void {
        $this->assertEquals(false, $actual, $msg);
    }

    protected function assertCount(int $expected, $countable, string $msg = ''): void {
        $this->assertions++;
        $count = $countable instanceof Collection
            ? $countable->count()
            : (is_countable($countable) ? count($countable) : -1);
        if ($count !== $expected) {
            $this->fail("expected count $expected, got $count", $msg);
        }
    }

    protected function assertGreaterThan($limit, $actual, string $msg = ''): void {
        $this->assertions++;
        if (!($actual > $limit)) {
            $this->fail("expected > $limit, got $actual", $msg);
        }
    }

    protected function assertInstanceOf(string $class, $actual, string $msg = ''): void {
        $this->assertions++;
        if (!($actual instanceof $class)) {
            $got = is_object($actual) ? get_class($actual) : gettype($actual);
            $this->fail("expected instance of $class, got $got", $msg);
        }
    }

    /**
     * Returns the caught throwable on success so the caller can make further
     * assertions about it (e.g. inspect its message).
     */
    protected function assertThrows(string $class, callable $fn, string $msg = ''): \Throwable {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!is_a($e, $class)) {
                $got = get_class($e);
                $this->fail("expected $class to be thrown, got $got: " . $e->getMessage(), $msg);
            }
            return $e;
        }
        $this->fail("expected $class to be thrown, no exception thrown", $msg);
    }

    private function fail(string $reason, string $userMsg): never {
        $prefix = $userMsg !== '' ? "$userMsg\n        " : '';
        throw new \AssertionError($prefix . $reason);
    }

    private function display($v): string {
        if (is_string($v))   return '"' . addcslashes($v, "\n\t\r\0\\\"") . '"';
        if (is_null($v))     return 'null';
        if (is_bool($v))     return $v ? 'true' : 'false';
        if (is_int($v) || is_float($v)) return (string) $v;
        if ($v instanceof \UnitEnum) return $v::class . '::' . $v->name;
        if (is_object($v) && method_exists($v, '__toString')) return (string) $v;
        if (is_array($v))    return 'array(' . count($v) . ')';
        return var_export($v, true);
    }
}
