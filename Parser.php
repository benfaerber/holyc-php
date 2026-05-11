<?php

namespace Holyc;

class ParseError extends \RuntimeException {}

/**
 * Recursive-descent parser for HolyC (Phase 2).
 *
 * New in Phase 2:
 *   - User-defined types accepted in any type position (no registry needed).
 *   - `<ident> <*>* <ident>` followed by '=' ';' ',' '(' '[' is recognised as a
 *     declaration start (resolves the C-style typedef ambiguity heuristically).
 *   - Multi-name var decls with per-declarator pointers and initializers.
 *   - `class Name { fields };` declarations.
 *   - Top-level statements (e.g. bare `DocClear;`).
 *
 * Grammar (Phase 2 sketch):
 *   program        := topLevel*
 *   topLevel       := classDecl | funcDecl | varDeclList | exprStmt
 *   classDecl      := 'class' Ident '{' varDeclList* '}' ';'
 *   funcDecl       := type ident '(' paramList? ')' (block | ';')
 *   varDeclList    := baseType declarator (',' declarator)* ';'
 *   declarator     := '*'* ident ('=' assignment)?
 *   baseType       := typeKeyword | Ident
 *   type           := baseType '*'*
 *   ... (statements / expressions unchanged)
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
            $extra = '';
            if ($t !== null && $t->contents !== null) {
                $extra = " (=" . var_export($t->contents, true) . ")";
            }
            $msg = "Expected {$kind->value}" . ($what !== '' ? " ($what)" : '') . ", got {$got}{$extra} at token #{$this->pos}";
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

    /**
     * Heuristic: are we looking at the start of a declaration?
     *
     *   - A built-in type keyword always starts a decl.
     *   - An ident followed by zero-or-more `*` and another ident, followed
     *     by one of `= ; , ( [`, is a decl. This catches user-defined types
     *     without needing a typedef table. The well-known false positive
     *     `a * b;` (multiplication-as-statement) is treated as a decl, which
     *     is virtually always what is meant in HolyC.
     */
    private function looksLikeDeclStart(): bool {
        $t = $this->peek();
        if ($t === null) return false;
        if (self::isTypeToken($t->token)) return true;
        if ($t->token !== Token::Ident) return false;

        $i = 1;
        while (($p = $this->peek($i)) !== null && $p->token === Token::Multiply) {
            $i++;
        }
        $name = $this->peek($i);
        if ($name === null || $name->token !== Token::Ident) return false;

        $after = $this->peek($i + 1);
        if ($after === null) return false;
        return match ($after->token) {
            Token::Equals,
            Token::Semicolon,
            Token::Comma,
            Token::ParenL,
            Token::BrackL => true,
            default => false,
        };
    }

    /* ------------------------------------------------------------------ *
     * Top-level
     * ------------------------------------------------------------------ */

    public function parse(): Program {
        $decls = new Collection([], AstNode::class);
        while (!$this->eof()) {
            $this->parseTopLevelInto($decls);
        }
        return new Program($decls);
    }

    private function parseTopLevelInto(Collection $out): void {
        if ($this->check(Token::Clazz)) {
            $out->push($this->parseClassDecl());
            return;
        }

        if (!$this->looksLikeDeclStart()) {
            // HolyC allows bare statements at top level (e.g. "Hello\n";).
            $out->push($this->parseStmt());
            return;
        }

        // Decide func-decl vs var-decl by reading: baseType '*'* ident ('(' | rest)
        $base = $this->parseBaseType();
        $depth = $this->parsePointers();
        $name = $this->expect(Token::Ident, 'declaration name')->contents;

        if ($this->check(Token::ParenL)) {
            // It's a function: '*'s after baseType belong to the return type.
            $rtype = new TypeRef($base, $depth);
            $out->push($this->finishFuncDecl($rtype, $name));
            return;
        }

        // It's a var-decl list (possibly multiple names).
        $out->push($this->finishDeclarator($base, $depth, $name));
        while ($this->match(Token::Comma)) {
            $d = $this->parsePointers();
            $n = $this->expect(Token::Ident, 'declarator name')->contents;
            $out->push($this->finishDeclarator($base, $d, $n));
        }
        $this->expect(Token::Semicolon, 'after variable declaration');
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
        $base = $this->parseBaseType();
        $depth = $this->parsePointers();
        $name = null;
        if ($this->check(Token::Ident)) {
            $name = $this->advance()->contents;
        }
        return new Param(new TypeRef($base, $depth), $name);
    }

    private function finishDeclarator(string $base, int $depth, string $name): VarDecl {
        $init = null;
        if ($this->match(Token::Equals)) {
            $init = $this->parseAssignment();
        }
        return new VarDecl(new TypeRef($base, $depth), $name, $init);
    }

    private function parseClassDecl(): ClassDecl {
        $this->expect(Token::Clazz);
        $name = $this->expect(Token::Ident, 'class name')->contents;
        $this->expect(Token::CurlyL, 'class body start');
        $fields = new Collection([], AstNode::class);
        while (!$this->check(Token::CurlyR) && !$this->eof()) {
            $base = $this->parseBaseType();
            $depth = $this->parsePointers();
            $fname = $this->expect(Token::Ident, 'field name')->contents;
            $fields->push($this->finishDeclarator($base, $depth, $fname));
            while ($this->match(Token::Comma)) {
                $d = $this->parsePointers();
                $n = $this->expect(Token::Ident, 'field name')->contents;
                $fields->push($this->finishDeclarator($base, $d, $n));
            }
            $this->expect(Token::Semicolon, 'after class field');
        }
        $this->expect(Token::CurlyR, 'class body end');
        $this->expect(Token::Semicolon, 'after class declaration');
        return new ClassDecl($name, $fields);
    }

    /* ------------------------------------------------------------------ *
     * Types
     * ------------------------------------------------------------------ */

    private function parseBaseType(): string {
        $t = $this->peek();
        if ($t === null) {
            throw new ParseError("Expected type, got EOF at token #{$this->pos}");
        }
        if (self::isTypeToken($t->token)) {
            $this->advance();
            return $t->token->value;
        }
        if ($t->token === Token::Ident) {
            $this->advance();
            return (string) $t->contents;
        }
        throw new ParseError("Expected type keyword or identifier, got {$t->token->value} at token #{$this->pos}");
    }

    private function parsePointers(): int {
        $depth = 0;
        while ($this->match(Token::Multiply) || $this->match(Token::Pointer)) {
            $depth++;
        }
        return $depth;
    }

    /* ------------------------------------------------------------------ *
     * Statements
     * ------------------------------------------------------------------ */

    private function parseBlock(): Block {
        $this->expect(Token::CurlyL, 'block start');
        $stmts = new Collection([], AstNode::class);
        while (!$this->check(Token::CurlyR) && !$this->eof()) {
            $this->parseStmtInto($stmts);
        }
        $this->expect(Token::CurlyR, 'block end');
        return new Block($stmts);
    }

    /**
     * Parse a statement OR a (possibly multi-name) declaration into the
     * given collection. Used by block bodies and top-level.
     */
    private function parseStmtInto(Collection $out): void {
        if ($this->looksLikeDeclStart()) {
            $base = $this->parseBaseType();
            $depth = $this->parsePointers();
            $name = $this->expect(Token::Ident, 'variable name')->contents;
            $out->push($this->finishDeclarator($base, $depth, $name));
            while ($this->match(Token::Comma)) {
                $d = $this->parsePointers();
                $n = $this->expect(Token::Ident, 'declarator name')->contents;
                $out->push($this->finishDeclarator($base, $d, $n));
            }
            $this->expect(Token::Semicolon, 'after variable declaration');
            return;
        }
        $out->push($this->parseStmt());
    }

    /**
     * Parse a single statement (no decls). Used by if/while/for bodies where
     * a declaration is not allowed without an explicit block.
     */
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
        if ($t->token === Token::Return)  return $this->parseReturnStmt();

        return $this->parseExprStmt();
    }

    private function parseReturnStmt(): ReturnStmt {
        $this->expect(Token::Return);
        $value = null;
        if (!$this->check(Token::Semicolon)) {
            $value = $this->parseExpression();
        }
        $this->expect(Token::Semicolon, 'after return');
        return new ReturnStmt($value);
    }

    private function parseIfStmt(): IfStmt {
        $this->expect(Token::If);
        $this->expect(Token::ParenL);
        $cond = $this->parseExpression();
        $this->expect(Token::ParenR);
        $then = $this->parseStmt();
        $else = null;
        if ($this->match(Token::Else)) {
            $else = $this->parseStmt();
        }
        return new IfStmt($cond, $then, $else);
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
            if ($this->looksLikeDeclStart()) {
                $base = $this->parseBaseType();
                $depth = $this->parsePointers();
                $name = $this->expect(Token::Ident, 'for-init variable name')->contents;
                $init = $this->finishDeclarator($base, $depth, $name);
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

    private function parseExprStmt(): AstNode {
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
            Token::BitOrEquals,
            Token::BitAndEquals,
            Token::BitXorEquals,
            Token::ShiftLEquals,
            Token::ShiftREquals,
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
            Token::Not,         // logical not
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
