#!/bin/env php
<?php

namespace Holyc;

require("Collection.php");
require("Lexer.php");
require("LexToken.php");
require("Token.php");
require("Tree.php");

function println(mixed $value) {
    print_r(strval($value) . "\n");
}

$lexer = new Lexer("");
$num = $lexer->lexNumber(Collection::fromString("125.24 "));
println($num);

$num = $lexer->lexNumber(Collection::fromString(".34 "));
println($num);
$num = $lexer->lexNumber(Collection::fromString("1 "));
println($num);

