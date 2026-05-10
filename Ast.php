<?php

namespace Holyc;

/**
 * Base class for all AST nodes. Provides a generic recursive `dump()` that
 * pretty-prints the tree using public properties via reflection-free
 * `get_object_vars`.
 */
abstract class AstNode {
    public function kind(): string {
        $cls = static::class;
        $pos = strrpos($cls, '\\');
        return $pos === false ? $cls : substr($cls, $pos + 1);
    }

    public function dump(int $indent = 0): string {
        $pad = str_repeat('  ', $indent);
        $out = $pad . $this->kind();

        $props = get_object_vars($this);
        // Inline trivial scalar props on the header line for compactness.
        $inline = [];
        $nested = [];
        foreach ($props as $name => $val) {
            if ($val instanceof Token) {
                $inline[] = "$name=" . $val->value;
            } else if (is_scalar($val) || $val === null) {
                $inline[] = "$name=" . var_export($val, true);
            } else {
                $nested[$name] = $val;
            }
        }
        if (!empty($inline)) {
            $out .= ' ' . implode(' ', $inline);
        }
        $out .= "\n";

        foreach ($nested as $name => $val) {
            $out .= $pad . "  $name:";
            if ($val instanceof AstNode) {
                $out .= "\n" . $val->dump($indent + 2);
            } else if ($val instanceof Collection) {
                if ($val->count() === 0) {
                    $out .= " []\n";
                } else {
                    $out .= "\n";
                    foreach ($val->items as $item) {
                        if ($item instanceof AstNode) {
                            $out .= $item->dump($indent + 2);
                        } else {
                            $out .= str_repeat('  ', $indent + 2) . var_export($item, true) . "\n";
                        }
                    }
                }
            } else {
                $out .= ' ' . var_export($val, true) . "\n";
            }
        }
        return $out;
    }

    public function __toString(): string {
        return $this->dump();
    }
}

/* ------------------------------------------------------------------ *
 * Top-level
 * ------------------------------------------------------------------ */

class Program extends AstNode {
    public function __construct(public Collection $decls) {}
}

class TypeRef extends AstNode {
    public function __construct(public string $base, public int $pointerDepth = 0) {}
}

class Param extends AstNode {
    public function __construct(public TypeRef $type, public ?string $name) {}
}

class FuncDecl extends AstNode {
    public function __construct(
        public TypeRef $returnType,
        public string $name,
        public Collection $params, // Collection<Param>
        public ?Block $body         // null = forward declaration
    ) {}
}

class VarDecl extends AstNode {
    public function __construct(
        public TypeRef $type,
        public string $name,
        public ?AstNode $init
    ) {}
}

class ClassDecl extends AstNode {
    public function __construct(
        public string $name,
        public Collection $fields // Collection<VarDecl>
    ) {}
}

/* ------------------------------------------------------------------ *
 * Statements
 * ------------------------------------------------------------------ */

class Block extends AstNode {
    public function __construct(public Collection $stmts) {}
}

class IfStmt extends AstNode {
    public function __construct(
        public AstNode $cond,
        public AstNode $then,
        public ?AstNode $else
    ) {}
}

class WhileStmt extends AstNode {
    public function __construct(public AstNode $cond, public AstNode $body) {}
}

class ForStmt extends AstNode {
    public function __construct(
        public ?AstNode $init,
        public ?AstNode $cond,
        public ?AstNode $step,
        public AstNode $body
    ) {}
}

class ReturnStmt extends AstNode {
    public function __construct(public ?AstNode $value) {}
}

class BreakStmt extends AstNode {}

class ExprStmt extends AstNode {
    public function __construct(public AstNode $expr) {}
}

/* ------------------------------------------------------------------ *
 * Expressions
 * ------------------------------------------------------------------ */

class BinaryExpr extends AstNode {
    public function __construct(
        public Token $op,
        public AstNode $left,
        public AstNode $right
    ) {}
}

class AssignExpr extends AstNode {
    public function __construct(
        public Token $op,
        public AstNode $target,
        public AstNode $value
    ) {}
}

class UnaryExpr extends AstNode {
    public function __construct(
        public Token $op,
        public AstNode $operand,
        public bool $prefix = true
    ) {}
}

class CallExpr extends AstNode {
    public function __construct(
        public AstNode $callee,
        public Collection $args // Collection<AstNode>
    ) {}
}

class IndexExpr extends AstNode {
    public function __construct(public AstNode $obj, public AstNode $index) {}
}

class FieldExpr extends AstNode {
    /** $arrow=true for `->`, false for `.` (Phase 2) */
    public function __construct(
        public AstNode $obj,
        public string $field,
        public bool $arrow = true
    ) {}
}

class IdentExpr extends AstNode {
    public function __construct(public string $name) {}
}

class IntLit extends AstNode {
    public function __construct(public int $value) {}
}

class FloatLit extends AstNode {
    public function __construct(public float $value) {}
}

class StringLit extends AstNode {
    public function __construct(public string $value) {}
}

class CharLit extends AstNode {
    public function __construct(public string $value) {}
}

class BoolLit extends AstNode {
    public function __construct(public bool $value) {}
}

class NullLit extends AstNode {}
