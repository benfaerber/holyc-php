<?php

namespace Holyc;

class CodeGenTest extends TestCase {
    private function asm(string $source): string {
        return compileToAsm($source);
    }

    /** True if every needle appears in $haystack in the given order. */
    private function containsInOrder(string $haystack, array $needles): bool {
        $pos = 0;
        foreach ($needles as $n) {
            $found = strpos($haystack, $n, $pos);
            if ($found === false) return false;
            $pos = $found + strlen($n);
        }
        return true;
    }

    public function testHeaderHasStartAndExitSyscall() {
        $asm = $this->asm("U0 main() { return 0; }");
        $this->assertTrue($this->containsInOrder($asm, [
            "global _start",
            "_start:",
            "call main",
            "mov rdi, rax",
            "mov rax, 60",
            "syscall",
        ]), "expected _start stub to call main and exit\n$asm");
    }

    public function testFunctionPrologueAndEpilogue() {
        $asm = $this->asm("U0 main() { return 0; }");
        $this->assertTrue($this->containsInOrder($asm, [
            "main:",
            "push rbp",
            "mov rbp, rsp",
            "pop rbp",
            "ret",
        ]));
    }

    public function testIntegerLiteralLoadsIntoRax() {
        $asm = $this->asm("U0 main() { return 42; }");
        $this->assertTrue(str_contains($asm, "mov rax, 42"));
    }

    public function testBinaryAdditionEmitsPushAndAdd() {
        $asm = $this->asm("U0 main() { return 1 + 2; }");
        // Right operand pushed, left into rax, pop rcx, add.
        $this->assertTrue($this->containsInOrder($asm, [
            "mov rax, 2", "push rax", "mov rax, 1", "pop rcx", "add rax, rcx",
        ]));
    }

    public function testDivisionUsesIdiv() {
        $asm = $this->asm("U0 main() { return 10 / 3; }");
        $this->assertTrue($this->containsInOrder($asm, ["cqo", "idiv rcx"]));
    }

    public function testLocalVariableAllocatesStackSlot() {
        $asm = $this->asm("U0 main() { I64 x = 5; return x; }");
        $this->assertTrue(str_contains($asm, "sub rsp, 16"));
        $this->assertTrue(str_contains($asm, "mov [rbp-8], rax"));
        $this->assertTrue(str_contains($asm, "mov rax, [rbp-8]"));
    }

    public function testIfElseEmitsBranches() {
        $asm = $this->asm("U0 main() { if (1) return 1; else return 0; }");
        $this->assertTrue(str_contains($asm, "cmp rax, 0"));
        $this->assertTrue(str_contains($asm, "je "));
        $this->assertTrue(str_contains($asm, "jmp "));
    }

    public function testWhileLoopHasTopAndEndLabels() {
        $asm = $this->asm("U0 main() { I64 i = 0; while (i < 10) { i = i + 1; } return i; }");
        $this->assertTrue((bool) preg_match('/\.Lwhile_\d+:/', $asm));
        $this->assertTrue((bool) preg_match('/\.Lendwhile_\d+:/', $asm));
    }

    public function testParamSpilledToStackSlot() {
        $asm = $this->asm("I64 sqr(I64 n) { return n * n; }");
        // First param goes via rdi, must be spilled into the local slot.
        $this->assertTrue(str_contains($asm, "mov [rbp-8], rdi"));
    }

    public function testCallPassesArgsInSysVRegisters() {
        $asm = $this->asm("I64 f(I64 a, I64 b) { return a + b; } U0 main() { return f(3, 4); }");
        $this->assertTrue(str_contains($asm, "pop rdi"));
        $this->assertTrue(str_contains($asm, "pop rsi"));
        $this->assertTrue(str_contains($asm, "call f"));
    }

    public function testLogicalAndShortCircuits() {
        $asm = $this->asm("U0 main() { return 1 && 0; }");
        // After first operand is zero we jump to the false branch without
        // evaluating the second one — there should be a 'je' to a false label.
        $this->assertTrue((bool) preg_match('/\.Land_false_\d+/', $asm));
    }

    public function testUnknownIdentifierIsCodeGenError() {
        $this->assertThrows(CodeGenError::class,
            fn () => $this->asm("U0 main() { return notDeclared; }"));
    }
}
