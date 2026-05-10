<?php

namespace Holyc;

class ParseError extends \RuntimeException {}

/**
 * Recursive-descent parser for HolyC (Phase 1 subset).
 *
 * Grammar (sketch):
 *   program        := topLevel*
 *   topLevel       := funcDecl | varDecl
 *   funcDecl       := type ident '(' paramList? ')' (block | ';')
 *   paramList      := param (',' param)*
 *   param          := type ident?
 *   varDecl        := type ident ('=' assignment)? ';'
 *   type           := typeKeyword '*'*
 *   block          := '{' stmt* '}'
 *   stmt           := block | ifStmt | whileStmt | forStmt
 *                   | returnStmt | breakStmt | varDecl | exprStmt
 *   exprStmt       := expression ';'
 *   expression     := comma
 *   comma          := assignment (',' assignment)*
 *   assignment     := logicalOr (assignOp assignment)?
 *   logicalOr      := logicalAnd ('||' logicalAnd)*
 *   logicalAnd     := bitOr ('&&' bitOr)*
 *   bitOr          := bitXor ('|' bitXor)*
 *   bitXor         := bitAnd ('^' bitAnd)*
 *   bitAnd         := equality ('&' equality)*
 *   equality       := comparison (('=='|'!=') comparison)*
 *   comparison     := shift (('<'|'<='|'>'|'>=') shift)*
 *   shift          := additive (('<<'|'>>') additive)*
 *   additive       := multiplicative (('+'|'-') multiplicative)*
 *   multiplicative := unary (('*'|'/'|'%') unary)*
 *   unary          := ('+'|'-'|'*'|'&'|'++'|'--') unary | postfix
 *   postfix        := primary ( '(' args? ')' | '[' expr ']'
 *                              | '->' ident | '++' | '--' )*
 *   primary        := intLit | floatLit | strLit | charLit | TRUE | FALSE
 *                   | NULL | ident | '(' expression ')'
 */
class Parser {
    private int $pos = 0;
    private Collection $tokens;

    public function __construct(Collection $tokens) {
        $this->tokens = $tokens;
    }

    /* ------------------------------------------------------------------ *
     * Cursor helpers
     * ------------------------------------------------------------------ */

    private function peek(int $offset = 0): ?LexToken {
        return $this->tokens->get($this->pos + $offset);
    }

    private function eof(): bool {
        return $this->peek() === null;
    }

    private function advance(): LexToken {
        $t = $this->peek();
        if ($t === null) {
            throw new ParseError("Unexpected end of input");
        }
        $this->pos++;
        return $t;
    }

    private function check(Token ...$kinds): bool {
        $t = $this->peek();
        if ($t === null) return false;
        foreach ($kinds as $k) {
            if ($t->token === $k) return true;
        }
        return false;
    }

    private function match(Token ...$kinds): ?LexToken {
        if ($this->check(...$kinds)) {
            return $this->advance();
        }
        return null;
    }

    private function expect(Token $kind, string $what = ''): LexToken {
        $t = $this->peek();
        if ($t === null || $t->token !== $kind) {
            $got = $t === null ? 'EOF' : $t->token->value;
            $msg = "Expected {$kind->value}" . ($what !== '' ? " ($what)" : '') . ", got {$got} at token #{$this->pos}";
            throw new ParseError($msg);
        }
        return $this->advance();
    }

    private static function isTypeToken(Token $t): bool {
        return match ($t) {
            Token::TypeU0, Token::TypeU8, Token::TypeU16, Token::TypeU32, Token::TypeU64,
            Token::TypeI8, Token::TypeI16, Token::TypeI32, Token::TypeI64,
            Token::TypeF64, Token::TypeBool => true,
            default => false,
        };
    }

    /* ------------------------------------------------------------------ *
     * Top-level
     * ------------------------------------------------------------------ */

    public function parse(): Program {
        $decls = new Collection([], AstNode::class);
        while (!$this->eof()) {
            $decls->push($this->parseTopLevel());
        }
        return new Program($decls);
    }

    private function parseTopLevel(): AstNode {
        $type = $this->parseType();
        $name = $this->expect(Token::Ident, 'declaration name')->contents;

        if ($this->check(Token::ParenL)) {
            return $this->finishFuncDecl($type, $name);
        }
        return $this->finishVarDecl($type, $name);
    }

    private function finishFuncDecl(TypeRef $returnType, string $name): FuncDecl {
        $this->expect(Token::ParenL);
        $params = new Collection([], Param::class);
        if (!$this->check(Token::ParenR)) {
            $params->push($this->parseParam());
            while ($this->match(Token::Comma)) {
                $params->push($this->parseParam());
            }
        }
        $this->expect(Token::ParenR);

        if ($this->match(Token::Semicolon)) {
            return new FuncDecl($returnType, $name, $params, null);
        }
        $body = $this->parseBlock();
        return new FuncDecl($returnType, $name, $params, $body);
    }

    private function parseParam(): Param {
        $type = $this->parseType();
        $name = null;
        if ($this->check(Token::Ident)) {
            $name = $this->advance()->contents;
        }
        return new Param($type, $name);
    }

    private function finishVarDecl(TypeRef $type, string $name): VarDecl {
        $init = null;
        if ($this->match(Token::Equals)) {
            $init = $this->parseAssignment();
        }
        $this->expect(Token::Semicolon, 'after variable declaration');
        return new VarDecl($type, $name, $init);
    }

    private function parseType(): TypeRef {
        $t = $this->peek();
        if ($t === null || !self::isTypeToken($t->token)) {
            $got = $t === null ? 'EOF' : $t->token->value;
            throw new ParseError("Expected type keyword, got {$got} at token #{$this->pos}");
        }
        $this->advance();
        $base = $t->token->value;
        $depth = 0;
        while ($this->match(Token::Multiply) || $this->match(Token::Pointer)) {
            $depth++;
        }
        return new TypeRef($base, $depth);
    }

    /* ------------------------------------------------------------------ *
     * Statements
     * ------------------------------------------------------------------ */

    private function parseBlock(): Block {
        $this->expect(Token::CurlyL, 'block start');
        $stmts = new Collection([], AstNode::class);
        while (!$this->check(Token::CurlyR) && !$this->eof()) {
            $stmts->push($this->parseStmt());
        }
        $this->expect(Token::CurlyR, 'block end');
        return new Block($stmts);
    }

    private function parseStmt(): AstNode {
        $t = $this->peek();
        if ($t === null) {
            throw new ParseError("Unexpected end of input in statement");
        }

        if ($t->token === Token::CurlyL)  return $this->parseBlock();
        if ($t->token === Token::If)      return $this->parseIfStmt();
        if ($t->token === Token::While)   return $this->parseWhileStmt();
        if ($t->token === Token::For)     return $this->parseForStmt();
        if ($t->token === Token::Break)   return $this->parseBreakStmt();
        if (self::isTypeToken($t->token)) {
            // Variable declaration
            $type = $this->parseType();
            $name = $this->expect(Token::Ident, 'variable name')->contents;
            return $this->finishVarDecl($type, $name);
        }

        return $this->parseExprStmt();
    }

    private function parseIfStmt(): IfStmt {
        $this->expect(Token::If);
        $this->expect(Token::ParenL);
        $cond = $this->parseExpression();
        $this->expect(Token::ParenR);
        $then = $this->parseStmt();
        $else = null;
        // HolyC uses `else` like C; we treat a bare ident "else" as the keyword
        // since the lexer sees it as Ident (no keyword entry yet). Add a tiny
        // contextual hook here.
        if ($this->isContextualKeyword('else')) {
            $this->advance();
            $else = $this->parseStmt();
        }
        return new IfStmt($cond, $then, $else);
    }

    private function isContextualKeyword(string $word): bool {
        $t = $this->peek();
        return $t !== null && $t->token === Token::Ident && $t->contents === $word;
    }

    private function parseWhileStmt(): WhileStmt {
        $this->expect(Token::While);
        $this->expect(Token::ParenL);
        $cond = $this->parseExpression();
        $this->expect(Token::ParenR);
        $body = $this->parseStmt();
        return new WhileStmt($cond, $body);
    }

    private function parseForStmt(): ForStmt {
        $this->expect(Token::For);
        $this->expect(Token::ParenL);

        $init = null;
        if (!$this->check(Token::Semicolon)) {
            $t = $this->peek();
            if ($t !== null && self::isTypeToken($t->token)) {
                $type = $this->parseType();
                $name = $this->expect(Token::Ident, 'for-init variable name')->contents;
                $vinit = null;
                if ($this->match(Token::Equals)) {
                    $vinit = $this->parseAssignment();
                }
                $init = new VarDecl($type, $name, $vinit);
            } else {
                $init = $this->parseExpression();
            }
        }
        $this->expect(Token::Semicolon, 'after for-init');

        $cond = null;
        if (!$this->check(Token::Semicolon)) {
            $cond = $this->parseExpression();
        }
        $this->expect(Token::Semicolon, 'after for-cond');

        $step = null;
        if (!$this->check(Token::ParenR)) {
            $step = $this->parseExpression();
        }
        $this->expect(Token::ParenR);

        $body = $this->parseStmt();
        return new ForStmt($init, $cond, $step, $body);
    }

    private function parseBreakStmt(): BreakStmt {
        $this->expect(Token::Break);
        $this->expect(Token::Semicolon);
        return new BreakStmt();
    }

    private function parseExprStmt(): ExprStmt {
        // HolyC has no explicit `return` token in our enum yet; treat
        // contextual `return` ident as a return statement.
        if ($this->isContextualKeyword('return')) {
            $this->advance();
            $value = null;
            if (!$this->check(Token::Semicolon)) {
                $value = $this->parseExpression();
            }
            $this->expect(Token::Semicolon);
            return new ExprStmt(new ReturnStmt($value));
        }

        $expr = $this->parseExpression();
        $this->expect(Token::Semicolon, 'after expression statement');
        return new ExprStmt($expr);
    }

    /* ------------------------------------------------------------------ *
     * Expressions — precedence climbing
     * ------------------------------------------------------------------ */

    public function parseExpression(): AstNode {
        return $this->parseComma();
    }

    private function parseComma(): AstNode {
        $left = $this->parseAssignment();
        while ($op = $this->match(Token::Comma)) {
            $right = $this->parseAssignment();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseAssignment(): AstNode {
        $left = $this->parseLogicalOr();
        if ($op = $this->match(
            Token::Equals,
            Token::PlusEquals,
            Token::MinusEquals,
            Token::MultiplyEquals,
            Token::DivideEquals,
            Token::ModuloEquals,
        )) {
            $right = $this->parseAssignment(); // right-associative
            return new AssignExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseLogicalOr(): AstNode {
        $left = $this->parseLogicalAnd();
        while ($op = $this->match(Token::Or)) {
            $right = $this->parseLogicalAnd();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseLogicalAnd(): AstNode {
        $left = $this->parseBitOr();
        while ($op = $this->match(Token::And)) {
            $right = $this->parseBitOr();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseBitOr(): AstNode {
        $left = $this->parseBitXor();
        while ($op = $this->match(Token::BitwiseOr)) {
            $right = $this->parseBitXor();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseBitXor(): AstNode {
        $left = $this->parseBitAnd();
        while ($op = $this->match(Token::BitwiseXor)) {
            $right = $this->parseBitAnd();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseBitAnd(): AstNode {
        $left = $this->parseEquality();
        while ($op = $this->match(Token::BitwiseAnd)) {
            $right = $this->parseEquality();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseEquality(): AstNode {
        $left = $this->parseComparison();
        while ($op = $this->match(Token::Eq, Token::Ne)) {
            $right = $this->parseComparison();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseComparison(): AstNode {
        $left = $this->parseShift();
        while ($op = $this->match(Token::Lt, Token::Lte, Token::Gt, Token::Gte)) {
            $right = $this->parseShift();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseShift(): AstNode {
        $left = $this->parseAdditive();
        while ($op = $this->match(Token::ShiftL, Token::ShiftR)) {
            $right = $this->parseAdditive();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseAdditive(): AstNode {
        $left = $this->parseMultiplicative();
        while ($op = $this->match(Token::Plus, Token::Minus)) {
            $right = $this->parseMultiplicative();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseMultiplicative(): AstNode {
        $left = $this->parseUnary();
        while ($op = $this->match(Token::Multiply, Token::Divide, Token::Modulo)) {
            $right = $this->parseUnary();
            $left = new BinaryExpr($op->token, $left, $right);
        }
        return $left;
    }

    private function parseUnary(): AstNode {
        if ($op = $this->match(
            Token::Plus,
            Token::Minus,
            Token::Multiply,    // dereference
            Token::BitwiseAnd,  // address-of
            Token::Increment,
            Token::Decrement,
        )) {
            $operand = $this->parseUnary();
            return new UnaryExpr($op->token, $operand, prefix: true);
        }
        return $this->parsePostfix();
    }

    private function parsePostfix(): AstNode {
        $expr = $this->parsePrimary();
        while (true) {
            if ($this->match(Token::ParenL)) {
                $args = new Collection([], AstNode::class);
                if (!$this->check(Token::ParenR)) {
                    $args->push($this->parseAssignment());
                    while ($this->match(Token::Comma)) {
                        $args->push($this->parseAssignment());
                    }
                }
                $this->expect(Token::ParenR);
                $expr = new CallExpr($expr, $args);
            } else if ($this->match(Token::BrackL)) {
                $idx = $this->parseExpression();
                $this->expect(Token::BrackR);
                $expr = new IndexExpr($expr, $idx);
            } else if ($this->match(Token::FieldDeref)) {
                $field = $this->expect(Token::Ident, 'field name')->contents;
                $expr = new FieldExpr($expr, $field, arrow: true);
            } else if ($op = $this->match(Token::Increment, Token::Decrement)) {
                $expr = new UnaryExpr($op->token, $expr, prefix: false);
            } else {
                break;
            }
        }
        return $expr;
    }

    private function parsePrimary(): AstNode {
        $t = $this->peek();
        if ($t === null) {
            throw new ParseError("Unexpected end of input in expression");
        }

        switch ($t->token) {
            case Token::Integer:
                $this->advance();
                return new IntLit((int) $t->contents);
            case Token::Float:
                $this->advance();
                return new FloatLit((float) $t->contents);
            case Token::String:
                $this->advance();
                return new StringLit((string) $t->contents);
            case Token::Char:
                $this->advance();
                return new CharLit((string) $t->contents);
            case Token::True:
                $this->advance();
                return new BoolLit(true);
            case Token::False:
                $this->advance();
                return new BoolLit(false);
            case Token::Null:
                $this->advance();
                return new NullLit();
            case Token::Ident:
                $this->advance();
                return new IdentExpr((string) $t->contents);
            case Token::ParenL:
                $this->advance();
                $expr = $this->parseExpression();
                $this->expect(Token::ParenR, 'after parenthesised expression');
                return $expr;
            default:
                throw new ParseError("Unexpected token {$t->token->value} at #{$this->pos} (in primary)");
        }
    }
}
