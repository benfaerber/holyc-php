<?php

namespace Holyc;

/**
 * Compact S-expression printer for AST nodes — used in test assertions to
 * make tree-shaped expectations readable on a single line.
 */
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
        $args = array_map('Holyc\\sexpr', $n->args->items);
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
    if ($n instanceof IfStmt) {
        $tail = $n->else ? " " . sexpr($n->else) : "";
        return "(if " . sexpr($n->cond) . " " . sexpr($n->then) . $tail . ")";
    }
    if ($n instanceof WhileStmt) {
        return "(while " . sexpr($n->cond) . " " . sexpr($n->body) . ")";
    }
    if ($n instanceof ForStmt) {
        $i = $n->init ? sexpr($n->init) : '_';
        $c = $n->cond ? sexpr($n->cond) : '_';
        $s = $n->step ? sexpr($n->step) : '_';
        return "(for $i $c $s " . sexpr($n->body) . ")";
    }
    if ($n instanceof Block) {
        $parts = array_map('Holyc\\sexpr', $n->stmts->items);
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
    if ($n instanceof ClassDecl) {
        $parts = array_map('Holyc\\sexpr', $n->fields->items);
        return "(class {$n->name}" . (empty($parts) ? '' : ' ' . implode(' ', $parts)) . ")";
    }
    if ($n instanceof Program) {
        $parts = array_map('Holyc\\sexpr', $n->decls->items);
        return "(program " . implode(' ', $parts) . ")";
    }
    return "?" . $n->kind() . "?";
}

/**
 * One-shot helper: lex + parse a source string into a Program.
 */
function parseProgram(string $source): Program {
    $tokens = (new Lexer($source))->lex();
    return (new Parser($tokens))->parse();
}

/**
 * One-shot helper: lex + parse a source string as an expression.
 */
function parseExpression(string $source): AstNode {
    $tokens = (new Lexer($source))->lex();
    return (new Parser($tokens))->parseExpression();
}
