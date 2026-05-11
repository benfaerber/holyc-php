<?php

namespace Holyc;

/**
 * End-to-end tests: take a HolyC source string all the way to a real ELF
 * binary on disk, run it, and assert on the exit code. Skipped on systems
 * without nasm + ld.
 */
class EndToEndTest extends TestCase {
    protected function setUp(): void {
        if (!toolchainAvailable()) {
            $this->skip("nasm/ld not available on this system");
        }
    }

    private function exitCode(string $source): int {
        return buildAndRun(compileToAsm($source));
    }

    /* ---------- iteration 1: literal return ---------- */

    public function testReturnsLiteralExitCode() {
        $this->assertEquals(42, $this->exitCode("U0 main() { return 42; }"));
    }

    public function testReturnsZeroByDefault() {
        $this->assertEquals(0, $this->exitCode("U0 main() { return 0; }"));
    }

    /* ---------- iteration 2: arithmetic ---------- */

    public function testArithmeticPrecedence() {
        $this->assertEquals(7, $this->exitCode("U0 main() { return 1 + 2 * 3; }"));
    }

    public function testSubtractionAndDivision() {
        $this->assertEquals(5, $this->exitCode("U0 main() { return (20 - 5) / 3; }"));
    }

    public function testModulo() {
        $this->assertEquals(1, $this->exitCode("U0 main() { return 10 % 3; }"));
    }

    public function testUnaryNegationThenAddition() {
        // exit codes are unsigned bytes, so use a value that ends up positive
        $this->assertEquals(7, $this->exitCode("U0 main() { return -3 + 10; }"));
    }

    /* ---------- iteration 3: locals ---------- */

    public function testLocalVariable() {
        $this->assertEquals(8, $this->exitCode("U0 main() { I64 x = 5; return x + 3; }"));
    }

    public function testReassignment() {
        $this->assertEquals(11, $this->exitCode("U0 main() { I64 x = 5; x = x + 6; return x; }"));
    }

    public function testCompoundAssignment() {
        $this->assertEquals(15, $this->exitCode("U0 main() { I64 x = 10; x += 5; return x; }"));
    }

    public function testSeveralLocals() {
        $this->assertEquals(30, $this->exitCode(
            "U0 main() { I64 a = 5; I64 b = 10; I64 c = 15; return a + b + c; }"
        ));
    }

    /* ---------- iteration 4: control flow ---------- */

    public function testIfTrueBranch() {
        $this->assertEquals(1, $this->exitCode(
            "U0 main() { I64 x = 5; if (x > 3) return 1; return 0; }"
        ));
    }

    public function testIfFalseBranch() {
        $this->assertEquals(0, $this->exitCode(
            "U0 main() { I64 x = 1; if (x > 3) return 1; return 0; }"
        ));
    }

    public function testIfElse() {
        $this->assertEquals(99, $this->exitCode(
            "U0 main() { I64 x = 1; if (x == 0) return 1; else return 99; }"
        ));
    }

    public function testWhileLoopSumsToTen() {
        $this->assertEquals(55, $this->exitCode(
            "U0 main() { I64 i = 0; I64 s = 0; while (i <= 10) { s = s + i; i = i + 1; } return s; }"
        ));
    }

    public function testForLoop() {
        $this->assertEquals(45, $this->exitCode(
            "U0 main() { I64 s = 0; I64 i; for (i = 0; i < 10; i = i + 1) { s = s + i; } return s; }"
        ));
    }

    public function testBreakExitsLoop() {
        $this->assertEquals(5, $this->exitCode(
            "U0 main() { I64 i = 0; while (1) { if (i == 5) break; i = i + 1; } return i; }"
        ));
    }

    public function testShortCircuitAnd() {
        // 0 && (anything) -> 0
        $this->assertEquals(0, $this->exitCode("U0 main() { return 0 && 1; }"));
        $this->assertEquals(1, $this->exitCode("U0 main() { return 1 && 1; }"));
    }

    public function testShortCircuitOr() {
        $this->assertEquals(1, $this->exitCode("U0 main() { return 0 || 1; }"));
        $this->assertEquals(0, $this->exitCode("U0 main() { return 0 || 0; }"));
    }

    /* ---------- iteration 5: function calls ---------- */

    public function testCallSquaresArgument() {
        $src = "I64 sqr(I64 n) { return n * n; } U0 main() { return sqr(7); }";
        $this->assertEquals(49, $this->exitCode($src));
    }

    public function testCallTwoArgs() {
        $src = "I64 add(I64 a, I64 b) { return a + b; } U0 main() { return add(10, 20); }";
        $this->assertEquals(30, $this->exitCode($src));
    }

    public function testRecursiveFactorial() {
        $src = "I64 fact(I64 n) {
                  if (n < 2) return 1;
                  return n * fact(n - 1);
                }
                U0 main() { return fact(5); }";  // 120 fits in exit code
        $this->assertEquals(120, $this->exitCode($src));
    }

    public function testRecursiveFibonacci() {
        $src = "I64 fib(I64 n) {
                  if (n < 2) return n;
                  return fib(n - 1) + fib(n - 2);
                }
                U0 main() { return fib(10); }";  // fib(10) = 55
        $this->assertEquals(55, $this->exitCode($src));
    }

    public function testNestedCallsAndArithmetic() {
        $src = "I64 dbl(I64 n) { return n * 2; }
                I64 inc(I64 n) { return n + 1; }
                U0 main() { return dbl(inc(20)); }";  // (20+1)*2 = 42
        $this->assertEquals(42, $this->exitCode($src));
    }
}
