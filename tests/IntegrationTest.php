<?php

namespace Holyc;

class IntegrationTest extends TestCase {
    private function spanBridgeSource(): string {
        return file_get_contents(__DIR__ . "/../examples/SpanBridge.HC");
    }

    public function testSpanBridgeFileLexesCleanly() {
        $tokens = (new Lexer($this->spanBridgeSource()))->lex();
        $this->assertGreaterThan(400, $tokens->count(),
            "expected the real example to produce a substantial token stream");
    }

    public function testSpanBridgeFileParsesEndToEnd() {
        $ast = parseProgram($this->spanBridgeSource());
        $this->assertEquals(2, $ast->decls->count());
    }

    public function testSpanBridgeFunctionsAreNamedAndTyped() {
        $ast = parseProgram($this->spanBridgeSource());
        $names = array_map(fn ($d) => $d->name, $ast->decls->items);
        $this->assertEquals(['SpanBridge1Init', 'AdjustLoads'], $names);

        foreach ($ast->decls->items as $d) {
            $this->assertInstanceOf(FuncDecl::class, $d);
            $this->assertEquals('U0', $d->returnType->base);
            $this->assertEquals(0, $d->returnType->pointerDepth);
        }
    }

    public function testFactorialProgramShape() {
        $src = "U0 fact(I64 n) { if (n < 2) return 1; return n * fact(n - 1); }";
        $ast = parseProgram($src);
        $this->assertEquals(1, $ast->decls->count());

        $fn = $ast->decls->first();
        $this->assertInstanceOf(FuncDecl::class, $fn);
        $this->assertEquals('fact', $fn->name);
        $this->assertEquals(1, $fn->params->count());
        $this->assertEquals('n', $fn->params->first()->name);
        $this->assertEquals(2, $fn->body->stmts->count());
    }

    public function testLinkedListWalkProgram() {
        $src = <<<'HC'
        class Node { I64 value; Node *next; };
        I64 sum(Node *head) {
            I64 acc = 0;
            while (head != NULL) {
                acc += head->value;
                head = head->next;
            }
            return acc;
        }
        HC;
        $ast = parseProgram($src);
        $this->assertEquals(2, $ast->decls->count());
        $this->assertInstanceOf(ClassDecl::class, $ast->decls->get(0));
        $this->assertInstanceOf(FuncDecl::class, $ast->decls->get(1));
    }
}
