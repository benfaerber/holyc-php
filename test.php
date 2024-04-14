#!/bin/env php
<?php

namespace Holyc;

require("Collection.php");
require("Lexer.php");
require("LexToken.php");
require("Token.php");
require("Tree.php");
require("Assert.php");

use function Holyc\collect;
use function Holyc\assertIt;


$lexer = new Lexer("");
assertIt($lexer->lexNumber(collect("125.24 blah blah"))->value, 125.24);

assertIt($lexer->lexNumber(collect(".34 cool"))->value, 0.34);
assertIt($lexer->lexNumber(collect("1 oh yeah"))->value, 1.0);

assertIt($lexer->lexString(collect("\"My cool string\"  "))->value, "My cool string");
assertIt($lexer->lexString(collect("'Joe\\'s string'  "))->value, "Joe's string");
assertIt($lexer->lexString(collect("'Joe\\'s string\"'  "))->value, "Joe's string\"");
assertIt($lexer->lexString(collect("\$cool")), null);

assertIt($lexer->lexWord(collect("\$cool = 20"))->value, "\$cool");
assertIt($lexer->lexWord(collect("case asdf"))->value, "case");

assertIt($lexer->lexKeyword(collect("TRUE asdf"))->value, Token::True, logs: false);
assertIt($lexer->lexKeyword(collect("+= asdf"))->value, Token::PlusEquals, logs: false);

