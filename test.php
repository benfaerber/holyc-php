#!/bin/env php
<?php

namespace Holyc;

require("Collection.php");
require("Lexer.php");
require("LexToken.php");
require("Token.php");
require("Tree.php");

use function Holyc\collect;

function test(mixed $value, mixed $expected = "NOTHING_EXPECTED", bool $logs = true) {
    if ($logs) {
        $text = $value !== null ? strval($value) : 'null'; 
        print_r($text . "\n");
    } else {
        print_r("Cannot log!");
    }
    if ($expected !== 'NOTHING_EXPECTED' && $value !== $expected) {
        if (!$logs) throw new \AssertionError("Assertion Failed! Cannot log"); 
        
        $got = strval($value) . " (" . gettype($value) . ")"; 
        $exp = strval($expected) . " (" . gettype($expected) . ")"; 
        throw new \AssertionError("Assertion Failed! Got: {$got} Expected: {$exp}"); 
    }
}


$lexer = new Lexer("");
test($lexer->lexNumber(collect("125.24 blah blah"))->value, 125.24);

test($lexer->lexNumber(collect(".34 cool"))->value, 0.34);
test($lexer->lexNumber(collect("1 oh yeah"))->value, 1.0);

test($lexer->lexString(collect("\"My cool string\"  "))->value, "My cool string");
test($lexer->lexString(collect("'Joe\\'s string'  "))->value, "Joe's string");
test($lexer->lexString(collect("'Joe\\'s string\"'  "))->value, "Joe's string\"");
test($lexer->lexString(collect("\$cool")), null);

test($lexer->lexWord(collect("\$cool = 20"))->value, "\$cool");
test($lexer->lexWord(collect("case asdf"))->value, "case");

test($lexer->lexKeyword(collect("TRUE asdf"))->value, Token::True, logs: false);
test($lexer->lexKeyword(collect("+= asdf"))->value, Token::PlusEquals, logs: false);

