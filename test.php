#!/bin/env php
<?php

namespace Holyc;

require("Collection.php");
require("Lexer.php");
require("LexToken.php");
require("Token.php");
require("Tree.php");

function test(mixed $value, mixed $expected = "NONE_EXPECTED") {
    $text = $value !== null ? strval($value) : 'null'; 
    print_r($text . "\n");
    if ($expected !== 'NONE_EXPECTED' && $value !== $expected) {
        $got = strval($value) . " (" . gettype($value) . ")"; 
        $exp = strval($expected) . " (" . gettype($expected) . ")"; 
        throw new \AssertionError("Assertion Failed! Got: {$got} Expected: {$exp}"); 
    }
}

function c(string $value) {
    return Collection::fromString($value);
}

$lexer = new Lexer("");
test($lexer->lexNumber(c("125.24 ")), 125.24);

test($lexer->lexNumber(c(".34 ")), 0.34);
test($lexer->lexNumber(c("1 ")), 1.0);

test($lexer->lexString(c("\"My cool string\"  "), "\"My cool string\""));
test($lexer->lexString(c("'Joe\\'s string'  ")), "'Joe's string'");
test($lexer->lexString(c("'Joe\\'s string\"'  ")), "'Joe's string\"'");
test($lexer->lexString(c("\$cool")), null);
