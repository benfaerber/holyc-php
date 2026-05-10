#!/bin/env php
<?php

namespace Holyc;

require("Stringable.php");
require("Collection.php");
require("Token.php");
require("LexToken.php");
require("Lexer.php");
require("Tree.php");
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
