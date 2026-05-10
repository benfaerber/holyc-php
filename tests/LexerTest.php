<?php

namespace Holyc;

class LexerTest extends TestCase {
    private Lexer $lex;

    protected function setUp(): void {
        $this->lex = new Lexer("");
    }

    /* -------------------- numbers -------------------- */

    public function testIntegerLiteralReturnsInt() {
        $r = $this->lex->lexNumber(collect("100 blah"));
        $this->assertEquals(100, $r->value);
    }

    public function testFloatLiteral() {
        $r = $this->lex->lexNumber(collect("125.24 blah"));
        $this->assertEquals(125.24, $r->value);
    }

    public function testFloatWithLeadingDot() {
        $r = $this->lex->lexNumber(collect(".34 cool"));
        $this->assertEquals(0.34, $r->value);
    }

    public function testHexLiteralUppercase() {
        $r = $this->lex->lexNumber(collect("0xFF rest"));
        $this->assertEquals(255, $r->value);
    }

    public function testHexLiteralLowercase() {
        $r = $this->lex->lexNumber(collect("0x1a more"));
        $this->assertEquals(26, $r->value);
    }

    public function testLoneDotIsNotANumber() {
        $this->assertNull($this->lex->lexNumber(collect(". nope")));
    }

    /* -------------------- strings & chars -------------------- */

    public function testDoubleQuotedString() {
        $r = $this->lex->lexString(collect('"My cool string"  '));
        $this->assertEquals("My cool string", $r->value);
    }

    public function testSingleQuotedStringWithEscapedQuote() {
        $r = $this->lex->lexString(collect("'Joe\\'s string'  "));
        $this->assertEquals("Joe's string", $r->value);
    }

    public function testEscapeSequencesAreTranslated() {
        $r = $this->lex->lexString(collect('"a\\nb\\tc"'));
        $this->assertEquals("a\nb\tc", $r->value);
    }

    public function testStringRejectsNonQuoteStart() {
        $this->assertNull($this->lex->lexString(collect("\$cool")));
    }

    public function testUnterminatedStringThrows() {
        $this->assertThrows(LexError::class,
            fn () => (new Lexer('"never closes'))->lex());
    }

    /* -------------------- words & identifiers -------------------- */

    public function testWordStopsAtPunctuation() {
        $r = $this->lex->lexWord(collect("foo(bar)"));
        $this->assertEquals("foo", $r->value);
    }

    public function testWordAllowsDigitsAfterFirstChar() {
        $r = $this->lex->lexWord(collect("name123;"));
        $this->assertEquals("name123", $r->value);
    }

    public function testWordRejectsNonAlphaStart() {
        $this->assertNull($this->lex->lexWord(collect("\$cool = 20")));
    }

    public function testIdentifierIsAcceptedWhenNotAKeyword() {
        $r = $this->lex->lexIdent(collect("PlaceMass(1)"));
        $this->assertEquals("PlaceMass", $r->value);
    }

    public function testIdentifierIsRejectedWhenKeyword() {
        $this->assertNull($this->lex->lexIdent(collect("if (x)")));
    }

    /* -------------------- keywords -------------------- */

    public function testKeywordTrue() {
        $this->assertEquals(Token::True, $this->lex->lexKeyword(collect("TRUE asdf"))->value);
    }

    public function testKeywordControlFlow() {
        $this->assertEquals(Token::If,     $this->lex->lexKeyword(collect("if (x)"))->value);
        $this->assertEquals(Token::For,    $this->lex->lexKeyword(collect("for (i"))->value);
        $this->assertEquals(Token::While,  $this->lex->lexKeyword(collect("while (1)"))->value);
        $this->assertEquals(Token::Break,  $this->lex->lexKeyword(collect("break;"))->value);
    }

    public function testKeywordType() {
        $this->assertEquals(Token::TypeU64, $this->lex->lexKeyword(collect("U64 x"))->value);
        $this->assertEquals(Token::TypeF64, $this->lex->lexKeyword(collect("F64 d"))->value);
        $this->assertEquals(Token::TypeBool,$this->lex->lexKeyword(collect("Bool b"))->value);
    }

    public function testKeywordPreprocessor() {
        $this->assertEquals(Token::Define,  $this->lex->lexKeyword(collect("#define X"))->value);
        $this->assertEquals(Token::Include, $this->lex->lexKeyword(collect('#include "f"'))->value);
    }

    /* -------------------- operators (longest match) -------------------- */

    public function testCompoundAssignmentOperators() {
        $this->assertEquals(Token::PlusEquals,    $this->lex->lexOperator(collect("+= asdf"))->value);
        $this->assertEquals(Token::MinusEquals,   $this->lex->lexOperator(collect("-= 1"))->value);
        $this->assertEquals(Token::ShiftLEquals,  $this->lex->lexOperator(collect("<<= 4"))->value);
        $this->assertEquals(Token::ShiftREquals,  $this->lex->lexOperator(collect(">>= 4"))->value);
        $this->assertEquals(Token::BitOrEquals,   $this->lex->lexOperator(collect("|= 1"))->value);
    }

    public function testOperatorComparison() {
        $this->assertEquals(Token::Eq,     $this->lex->lexOperator(collect("== 1"))->value);
        $this->assertEquals(Token::Equals, $this->lex->lexOperator(collect("= 1"))->value);
        $this->assertEquals(Token::Lt,     $this->lex->lexOperator(collect("<8"))->value);
        $this->assertEquals(Token::ShiftL, $this->lex->lexOperator(collect("<<8"))->value);
    }

    public function testOperatorPunctuation() {
        $this->assertEquals(Token::FieldDeref, $this->lex->lexOperator(collect("->next"))->value);
        $this->assertEquals(Token::Range,      $this->lex->lexOperator(collect("...rest"))->value);
        $this->assertEquals(Token::Comma,      $this->lex->lexOperator(collect(",b"))->value);
    }

    /* -------------------- comments -------------------- */

    public function testLineCommentSkipsToNewline() {
        $this->assertEquals(10, $this->lex->lexComment(collect("// comment\nrest")));
    }

    public function testBlockCommentSkipsToCloser() {
        $this->assertEquals(11, $this->lex->lexComment(collect("/* block */rest")));
    }

    public function testNotACommentReturnsZero() {
        $this->assertEquals(0, $this->lex->lexComment(collect("not a comment")));
    }

    /* -------------------- driver / end-to-end -------------------- */

    private function summarise(Collection $tokens): string {
        $parts = [];
        foreach ($tokens->items as $t) {
            $parts[] = $t->contents === null
                ? $t->token->value
                : $t->token->value . "(" . var_export($t->contents, true) . ")";
        }
        return implode(' ', $parts);
    }

    public function testDriverSimpleVarDecl() {
        $tokens = (new Lexer("U64 x = 42;"))->lex();
        $this->assertEquals("U64 Ident('x') = Literal_Integer(42) ;", $this->summarise($tokens));
    }

    public function testDriverHandlesArrowAndCompoundAssign() {
        $tokens = (new Lexer("tmpm->flags |= 1;"))->lex();
        $this->assertEquals(
            "Ident('tmpm') -> Ident('flags') |= Literal_Integer(1) ;",
            $this->summarise($tokens)
        );
    }

    public function testDriverSkipsCommentsAndPreprocessor() {
        $tokens = (new Lexer("// hi\n#define X 5\nU0 m() {}"))->lex();
        $this->assertEquals("U0 Ident('m') ( ) { }", $this->summarise($tokens));
    }

    public function testDriverHandlesNonAsciiIdentifiers() {
        // HolyC uses ã as a built-in identifier (pi). Bytes >= 0x80 should
        // be accepted as identifier-alpha.
        // Tokens: F64 | r | = | ã | ;
        $tokens = (new Lexer("F64 r = ã;"))->lex();
        $this->assertEquals(5, $tokens->count());
        $this->assertEquals(Token::Ident, $tokens->get(3)->token);
    }
}
