#!/bin/env php
<?php

namespace Holyc;

require("Stringable.php");
require("Collection.php");
require("Token.php");
require("LexToken.php");
require("Lexer.php");
require("Tree.php");
require("Ast.php");
require("Parser.php");
require("Assert.php");

use function Holyc\collect;
use function Holyc\assertIt;

/* ------------------------------------------------------------------ *
 * Sub-lexer unit tests
 * ------------------------------------------------------------------ */

$lexer = new Lexer("");

// Numbers — int vs float vs hex
assertIt($lexer->lexNumber(collect("125.24 blah blah"))->value, 125.24);
assertIt($lexer->lexNumber(collect("100 blah blah"))->value, 100);
assertIt($lexer->lexNumber(collect(".34 cool"))->value, 0.34);
assertIt($lexer->lexNumber(collect("1 oh yeah"))->value, 1);
assertIt($lexer->lexNumber(collect("0xFF rest"))->value, 255);
assertIt($lexer->lexNumber(collect("0x1a more"))->value, 26);

// Reject lone "."
assertIt($lexer->lexNumber(collect(". nope")), null);

// Strings
assertIt($lexer->lexString(collect("\"My cool string\"  "))->value, "My cool string");
assertIt($lexer->lexString(collect("'Joe\\'s string'  "))->value, "Joe's string");
assertIt($lexer->lexString(collect("'Joe\\'s string\"'  "))->value, "Joe's string\"");
assertIt($lexer->lexString(collect("\$cool")), null);

// Words — must stop at non-ident chars, not just spaces
assertIt($lexer->lexWord(collect("\$cool = 20")), null);   // '$' is not an ident char
assertIt($lexer->lexWord(collect("case asdf"))->value, "case");
assertIt($lexer->lexWord(collect("foo(bar)"))->value, "foo");
assertIt($lexer->lexWord(collect("name123;"))->value, "name123");

// Keywords
assertIt($lexer->lexKeyword(collect("TRUE asdf"))->value, Token::True, logs: false);
assertIt($lexer->lexKeyword(collect("FALSE asdf"))->value, Token::False, logs: false);
assertIt($lexer->lexKeyword(collect("if (x)"))->value, Token::If, logs: false);
assertIt($lexer->lexKeyword(collect("for (i"))->value, Token::For, logs: false);
assertIt($lexer->lexKeyword(collect("U64 x"))->value, Token::TypeU64, logs: false);
assertIt($lexer->lexKeyword(collect("#define X"))->value, Token::Define, logs: false);
assertIt($lexer->lexKeyword(collect("#include \"f\""))->value, Token::Include, logs: false);
assertIt($lexer->lexKeyword(collect("notakeyword"))?->value, null, logs: false);

// Identifiers (non-keywords)
assertIt($lexer->lexIdent(collect("PlaceMass(1)"))->value, "PlaceMass");
assertIt($lexer->lexIdent(collect("if (x)"))?->value, null);   // 'if' is reserved

// Operators — longest match
assertIt($lexer->lexOperator(collect("+= asdf"))->value, Token::PlusEquals, logs: false);
assertIt($lexer->lexOperator(collect("== 1"))->value, Token::Eq, logs: false);
assertIt($lexer->lexOperator(collect("= 1"))->value, Token::Equals, logs: false);
assertIt($lexer->lexOperator(collect("->next"))->value, Token::FieldDeref, logs: false);
assertIt($lexer->lexOperator(collect("...rest"))->value, Token::Range, logs: false);
assertIt($lexer->lexOperator(collect("<<8"))->value, Token::ShiftL, logs: false);
assertIt($lexer->lexOperator(collect("<8"))->value, Token::Lt, logs: false);
assertIt($lexer->lexOperator(collect(",b"))->value, Token::Comma, logs: false);

// Comments
assertIt($lexer->lexComment(collect("// comment\nrest")), 10);
assertIt($lexer->lexComment(collect("/* block */rest")), 11);
assertIt($lexer->lexComment(collect("not a comment")), 0);

/* ------------------------------------------------------------------ *
 * End-to-end lex() tests
 * ------------------------------------------------------------------ */

function tokenSummary(Collection $tokens): string {
    $parts = [];
    foreach ($tokens->items as $t) {
        $parts[] = $t->contents === null
            ? $t->token->value
            : $t->token->value . "(" . var_export($t->contents, true) . ")";
    }
    return implode(' ', $parts);
}

$src = "U64 x = 42;";
$tokens = (new Lexer($src))->lex();
assertIt(tokenSummary($tokens), "U64 Ident('x') = Literal_Integer(42) ;");

$src = "if (x == 0xFF) y += 1.5;";
$tokens = (new Lexer($src))->lex();
assertIt(
    tokenSummary($tokens),
    "if ( Ident('x') == Literal_Integer(255) ) Ident('y') += Literal_Float(1.5) ;"
);

$src = "// comment\nU0 main() { /* hi */ }";
$tokens = (new Lexer($src))->lex();
assertIt(tokenSummary($tokens), "U0 Ident('main') ( ) { }");

$src = "tmpm->flags = 1;";
$tokens = (new Lexer($src))->lex();
assertIt(
    tokenSummary($tokens),
    "Ident('tmpm') -> Ident('flags') = Literal_Integer(1) ;"
);

/* ------------------------------------------------------------------ *
 * Real example file — must lex without error
 * ------------------------------------------------------------------ */

$source = file_get_contents(__DIR__ . "/examples/SpanBridge.HC");
try {
    $tokens = (new Lexer($source))->lex();
    printf("\nSpanBridge.HC: lexed %d tokens\n", $tokens->count());
} catch (LexError $e) {
    printf("\nSpanBridge.HC: LEX ERROR: %s\n", $e->getMessage());
}

/* ------------------------------------------------------------------ *
 * Parser tests
 * ------------------------------------------------------------------ */

function lexParse(string $source): Program {
    $tokens = (new Lexer($source))->lex();
    return (new Parser($tokens))->parse();
}

function parseExpr(string $source): AstNode {
    $tokens = (new Lexer($source))->lex();
    return (new Parser($tokens))->parseExpression();
}

/** Compact S-expression view to make assertions readable. */
function sexpr(AstNode $n): string {
    if ($n instanceof IntLit)    return (string) $n->value;
    if ($n instanceof FloatLit)  return (string) $n->value;
    if ($n instanceof StringLit) return '"' . addcslashes($n->value, "\n\t\r\0\\\"") . '"';
    if ($n instanceof CharLit)   return "'" . addcslashes($n->value, "\n\t\r\0\\'") . "'";
    if ($n instanceof BoolLit)   return $n->value ? 'true' : 'false';
    if ($n instanceof NullLit)   return 'null';
    if ($n instanceof IdentExpr) return $n->name;
    if ($n instanceof BinaryExpr) {
        return "(" . $n->op->value . " " . sexpr($n->left) . " " . sexpr($n->right) . ")";
    }
    if ($n instanceof AssignExpr) {
        return "(" . $n->op->value . " " . sexpr($n->target) . " " . sexpr($n->value) . ")";
    }
    if ($n instanceof UnaryExpr) {
        $tag = $n->prefix ? $n->op->value : ($n->op->value . "_post");
        return "(" . $tag . " " . sexpr($n->operand) . ")";
    }
    if ($n instanceof CallExpr) {
        $args = [];
        foreach ($n->args->items as $a) $args[] = sexpr($a);
        return "(call " . sexpr($n->callee) . (empty($args) ? '' : ' ' . implode(' ', $args)) . ")";
    }
    if ($n instanceof IndexExpr) {
        return "(idx " . sexpr($n->obj) . " " . sexpr($n->index) . ")";
    }
    if ($n instanceof FieldExpr) {
        return "(-> " . sexpr($n->obj) . " " . $n->field . ")";
    }
    if ($n instanceof ExprStmt)   return "(stmt " . sexpr($n->expr) . ")";
    if ($n instanceof ReturnStmt) return "(return" . ($n->value ? " " . sexpr($n->value) : "") . ")";
    if ($n instanceof BreakStmt)  return "(break)";
    if ($n instanceof IfStmt)     return "(if " . sexpr($n->cond) . " " . sexpr($n->then) . ($n->else ? " " . sexpr($n->else) : "") . ")";
    if ($n instanceof WhileStmt)  return "(while " . sexpr($n->cond) . " " . sexpr($n->body) . ")";
    if ($n instanceof ForStmt) {
        $i = $n->init ? sexpr($n->init) : '_';
        $c = $n->cond ? sexpr($n->cond) : '_';
        $s = $n->step ? sexpr($n->step) : '_';
        return "(for $i $c $s " . sexpr($n->body) . ")";
    }
    if ($n instanceof Block) {
        $parts = [];
        foreach ($n->stmts->items as $s) $parts[] = sexpr($s);
        return "(block" . (empty($parts) ? '' : ' ' . implode(' ', $parts)) . ")";
    }
    if ($n instanceof VarDecl) {
        $t = $n->type->base . str_repeat('*', $n->type->pointerDepth);
        $init = $n->init ? " " . sexpr($n->init) : '';
        return "(var $t {$n->name}$init)";
    }
    if ($n instanceof FuncDecl) {
        $t = $n->returnType->base . str_repeat('*', $n->returnType->pointerDepth);
        $ps = [];
        foreach ($n->params->items as $p) {
            $pt = $p->type->base . str_repeat('*', $p->type->pointerDepth);
            $ps[] = $p->name === null ? $pt : "$pt {$p->name}";
        }
        $body = $n->body ? " " . sexpr($n->body) : ';';
        return "(func $t {$n->name} (" . implode(' ', $ps) . ")$body)";
    }
    if ($n instanceof Param) {
        $t = $n->type->base . str_repeat('*', $n->type->pointerDepth);
        return $n->name === null ? $t : "$t {$n->name}";
    }
    if ($n instanceof Program) {
        $parts = [];
        foreach ($n->decls->items as $d) $parts[] = sexpr($d);
        return "(program " . implode(' ', $parts) . ")";
    }
    return "?" . $n->kind() . "?";
}

// Expressions — precedence
assertIt(sexpr(parseExpr("1 + 2 * 3")),       "(+ 1 (* 2 3))");
assertIt(sexpr(parseExpr("(1 + 2) * 3")),     "(* (+ 1 2) 3)");
assertIt(sexpr(parseExpr("1 + 2 + 3")),       "(+ (+ 1 2) 3)");                  // left-assoc
assertIt(sexpr(parseExpr("a = b = 1")),       "(= a (= b 1))");                  // right-assoc
assertIt(sexpr(parseExpr("a += b * 2")),      "(+= a (* b 2))");
assertIt(sexpr(parseExpr("a == b && c < d")), "(&& (== a b) (< c d))");
assertIt(sexpr(parseExpr("a | b & c")),       "(| a (Bitwise_& b c))");
assertIt(sexpr(parseExpr("x << 2 + 1")),      "(<< x (+ 2 1))");
assertIt(sexpr(parseExpr("-x + +y")),         "(+ (- x) (+ y))");
assertIt(sexpr(parseExpr("*p = &x")),         "(= (* p) (Bitwise_& x))");
assertIt(sexpr(parseExpr("++i")),             "(++ i)");
assertIt(sexpr(parseExpr("i++")),             "(++_post i)");
assertIt(sexpr(parseExpr("a->b->c")),         "(-> (-> a b) c)");
assertIt(sexpr(parseExpr("a[i+1]")),          "(idx a (+ i 1))");
assertIt(sexpr(parseExpr("f(1, 2, 3)")),      "(call f 1 2 3)");
assertIt(sexpr(parseExpr("f()->g[0]")),       "(idx (-> (call f) g) 0)");
assertIt(sexpr(parseExpr("a, b, c")),         "(, (, a b) c)");                   // comma op

// Statements
assertIt(sexpr(lexParse("U0 main() { x = 1; }")),
    "(program (func U0 main () (block (stmt (= x 1)))))");

assertIt(sexpr(lexParse("U64 g = 42;")),
    "(program (var U64 g 42))");

assertIt(sexpr(lexParse("U0 f(I64 n, U8 *buf) { }")),
    "(program (func U0 f (I64 n U8* buf) (block)))");

assertIt(sexpr(lexParse("U0 f() { I64 i; for (i=0; i<10; i++) { x += i; } }")),
    "(program (func U0 f () (block (var I64 i) (for (= i 0) (< i 10) (++_post i) (block (stmt (+= x i)))))))");

assertIt(sexpr(lexParse("U0 f() { if (a < b) { x = 1; } else { x = 2; } }")),
    "(program (func U0 f () (block (if (< a b) (block (stmt (= x 1))) (block (stmt (= x 2)))))))");

assertIt(sexpr(lexParse("U0 f() { while (running) { x++; if (x > 10) break; } }")),
    "(program (func U0 f () (block (while running (block (stmt (++_post x)) (if (> x 10) (break)))))))");

// Forward declaration
assertIt(sexpr(lexParse("I64 sqr(I64 x);")),
    "(program (func I64 sqr (I64 x);))");

// HolyC-style print: comma operator carries the args
assertIt(sexpr(parseExpr("\"hello %d\\n\", count")),
    "(, \"hello %d\\n\" count)");

// Pointer in return type
assertIt(sexpr(lexParse("U8* alloc() { return NULL; }")),
    "(program (func U8* alloc () (block (stmt (return null)))))");

/* ------------------------------------------------------------------ *
 * End-to-end on a small synthetic HolyC program
 * ------------------------------------------------------------------ */

$prog = <<<HC
U0 fact(I64 n) {
  if (n < 2) return 1;
  return n * fact(n - 1);
}
HC;

$ast = lexParse($prog);
printf("\nfact() AST:\n%s", $ast->dump());

// Try the real example. Phase 1 is expected to fail somewhere because of
// user-defined types (`MyMass *tmpm;`) and multi-name var decls.
echo "\nAttempting to parse SpanBridge.HC...\n";
try {
    $bridge = lexParse(file_get_contents(__DIR__ . "/examples/SpanBridge.HC"));
    printf("Parsed %d top-level decls.\n", $bridge->decls->count());
} catch (ParseError $e) {
    printf("Expected Phase 1 limit: %s\n", $e->getMessage());
}

