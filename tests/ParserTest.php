<?php

namespace Holyc;

class ParserTest extends TestCase {

    /* ------------------------------------------------------------------ *
     * Expressions — precedence & associativity
     * ------------------------------------------------------------------ */

    public function testMultiplicativeBindsTighterThanAdditive() {
        $this->assertEquals("(+ 1 (* 2 3))", sexpr(parseExpression("1 + 2 * 3")));
    }

    public function testParenthesesOverrideDefaultPrecedence() {
        $this->assertEquals("(* (+ 1 2) 3)", sexpr(parseExpression("(1 + 2) * 3")));
    }

    public function testAdditiveIsLeftAssociative() {
        $this->assertEquals("(+ (+ 1 2) 3)", sexpr(parseExpression("1 + 2 + 3")));
    }

    public function testAssignmentIsRightAssociative() {
        $this->assertEquals("(= a (= b 1))", sexpr(parseExpression("a = b = 1")));
    }

    public function testCompoundAssignmentMixesWithMultiplication() {
        $this->assertEquals("(+= a (* b 2))", sexpr(parseExpression("a += b * 2")));
    }

    public function testLogicalAndOverEquality() {
        $this->assertEquals("(&& (== a b) (< c d))", sexpr(parseExpression("a == b && c < d")));
    }

    public function testBitwiseAndIsTighterThanBitwiseOr() {
        $this->assertEquals("(| a (Bitwise_& b c))", sexpr(parseExpression("a | b & c")));
    }

    public function testShiftIsLooserThanAdditive() {
        $this->assertEquals("(<< x (+ 2 1))", sexpr(parseExpression("x << 2 + 1")));
    }

    /* ------------------------------------------------------------------ *
     * Unary, postfix, calls, fields
     * ------------------------------------------------------------------ */

    public function testUnaryPlusAndMinus() {
        $this->assertEquals("(+ (- x) (+ y))", sexpr(parseExpression("-x + +y")));
    }

    public function testDereferenceAndAddressOf() {
        $this->assertEquals("(= (* p) (Bitwise_& x))", sexpr(parseExpression("*p = &x")));
    }

    public function testPrefixIncrement() {
        $this->assertEquals("(++ i)", sexpr(parseExpression("++i")));
    }

    public function testPostfixIncrement() {
        $this->assertEquals("(++_post i)", sexpr(parseExpression("i++")));
    }

    public function testFieldDerefChain() {
        $this->assertEquals("(-> (-> a b) c)", sexpr(parseExpression("a->b->c")));
    }

    public function testIndexExpression() {
        $this->assertEquals("(idx a (+ i 1))", sexpr(parseExpression("a[i+1]")));
    }

    public function testCallExpression() {
        $this->assertEquals("(call f 1 2 3)", sexpr(parseExpression("f(1, 2, 3)")));
    }

    public function testCallChainedWithFieldAndIndex() {
        $this->assertEquals("(idx (-> (call f) g) 0)", sexpr(parseExpression("f()->g[0]")));
    }

    public function testCommaOperatorIsLeftAssociative() {
        $this->assertEquals("(, (, a b) c)", sexpr(parseExpression("a, b, c")));
    }

    /* ------------------------------------------------------------------ *
     * Phase 2 operators
     * ------------------------------------------------------------------ */

    public function testBitOrAssign()  { $this->assertEquals("(|= x MASK)",      sexpr(parseExpression("x |= MASK"))); }
    public function testBitAndAssign() { $this->assertEquals("(Bitwise_&= x y)", sexpr(parseExpression("x &= y"))); }
    public function testBitXorAssign() { $this->assertEquals("(^= x y)",         sexpr(parseExpression("x ^= y"))); }
    public function testShiftLAssign() { $this->assertEquals("(<<= x 4)",        sexpr(parseExpression("x <<= 4"))); }
    public function testShiftRAssign() { $this->assertEquals("(>>= x 4)",        sexpr(parseExpression("x >>= 4"))); }

    public function testLogicalNot() {
        $this->assertEquals("(! done)",      sexpr(parseExpression("!done")));
        $this->assertEquals("(&& (! a) b)",  sexpr(parseExpression("!a && b")));
    }

    /* ------------------------------------------------------------------ *
     * Statements & top-level
     * ------------------------------------------------------------------ */

    public function testFunctionWithSimpleBody() {
        $this->assertEquals(
            "(program (func U0 main () (block (stmt (= x 1)))))",
            sexpr(parseProgram("U0 main() { x = 1; }"))
        );
    }

    public function testGlobalVariableDeclaration() {
        $this->assertEquals(
            "(program (var U64 g 42))",
            sexpr(parseProgram("U64 g = 42;"))
        );
    }

    public function testFunctionWithTypedAndPointerParams() {
        $this->assertEquals(
            "(program (func U0 f (I64 n U8* buf) (block)))",
            sexpr(parseProgram("U0 f(I64 n, U8 *buf) { }"))
        );
    }

    public function testForLoop() {
        $this->assertEquals(
            "(program (func U0 f () (block (var I64 i) (for (= i 0) (< i 10) (++_post i) (block (stmt (+= x i)))))))",
            sexpr(parseProgram("U0 f() { I64 i; for (i=0; i<10; i++) { x += i; } }"))
        );
    }

    public function testIfElse() {
        $this->assertEquals(
            "(program (func U0 f () (block (if (< a b) (block (stmt (= x 1))) (block (stmt (= x 2)))))))",
            sexpr(parseProgram("U0 f() { if (a < b) { x = 1; } else { x = 2; } }"))
        );
    }

    public function testWhileLoopWithBreak() {
        $this->assertEquals(
            "(program (func U0 f () (block (while running (block (stmt (++_post x)) (if (> x 10) (break)))))))",
            sexpr(parseProgram("U0 f() { while (running) { x++; if (x > 10) break; } }"))
        );
    }

    public function testForwardDeclaration() {
        $this->assertEquals(
            "(program (func I64 sqr (I64 x);))",
            sexpr(parseProgram("I64 sqr(I64 x);"))
        );
    }

    public function testHolyCPrintViaCommaOperator() {
        $this->assertEquals(
            '(, "hello %d\n" count)',
            sexpr(parseExpression('"hello %d\n", count'))
        );
    }

    public function testPointerReturnType() {
        $this->assertEquals(
            "(program (func U8* alloc () (block (return null))))",
            sexpr(parseProgram("U8* alloc() { return NULL; }"))
        );
    }

    /* ------------------------------------------------------------------ *
     * Phase 2 — user types, multi-name decls, classes, top-level stmts
     * ------------------------------------------------------------------ */

    public function testUserDefinedTypeInParamsAndLocals() {
        $this->assertEquals(
            "(program (func U0 init (CMathODE* o) (block (var MyMass* m) (stmt (= m (-> m next))))))",
            sexpr(parseProgram("U0 init(CMathODE *o) { MyMass *m; m = m->next; }"))
        );
    }

    public function testMultiNameVarDeclWithMixedPointersAndInits() {
        $this->assertEquals(
            "(program (func U0 f () (block (var F64 d) (var F64 tt 10) (var F64* p))))",
            sexpr(parseProgram("U0 f() { F64 d, tt = 10.0, *p; }"))
        );
    }

    public function testUserTypePointerWithInitializer() {
        $this->assertEquals(
            "(program (func U0 f (CMathODE* o) (block (var MyMass* m (-> o next)))))",
            sexpr(parseProgram("U0 f(CMathODE *o) { MyMass *m = o->next; }"))
        );
    }

    public function testSimpleClassDecl() {
        $this->assertEquals(
            "(program (class Point (var I64 x) (var I64 y)))",
            sexpr(parseProgram("class Point { I64 x; I64 y; };"))
        );
    }

    public function testClassWithSelfReferentialPointers() {
        $this->assertEquals(
            "(program (class Node (var I64 value) (var Node* next) (var Node* prev)))",
            sexpr(parseProgram("class Node { I64 value; Node *next, *prev; };"))
        );
    }

    public function testTopLevelStatement() {
        $this->assertEquals(
            "(program (stmt DocClear))",
            sexpr(parseProgram("DocClear;"))
        );
    }

    public function testTopLevelHolyCPrint() {
        $this->assertEquals(
            '(program (stmt (, "hello %d\n" count)))',
            sexpr(parseProgram('"hello %d\n", count;'))
        );
    }

    public function testHeuristicDoesNotMisfireOnPlainAssignment() {
        $this->assertEquals(
            "(program (stmt (= x (+ a b))))",
            sexpr(parseProgram("x = a + b;"))
        );
    }

    /* ------------------------------------------------------------------ *
     * Error reporting
     * ------------------------------------------------------------------ */

    public function testParseErrorOnMissingSemicolon() {
        $this->assertThrows(ParseError::class,
            fn () => parseProgram("U0 f() { x = 1 }"));
    }

    public function testParseErrorOnUnclosedBlock() {
        $this->assertThrows(ParseError::class,
            fn () => parseProgram("U0 f() { x = 1;"));
    }
}
