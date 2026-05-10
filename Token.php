<?php

namespace Holyc;

use Holyc\Stringable;

enum Token: string implements Stringable {
    /* Primitive Types */
    // Like void but sizeof 0, ! in Rust as opposed to ()
    case TypeU0 = 'U0';
    case TypeU8 = 'U8';
    case TypeU16 = 'U16';
    case TypeU32 = 'U32';
    case TypeU64 = 'U64';

    case TypeI8 = 'I8';
    case TypeI16 = 'I16';
    case TypeI32 = 'I32';
    case TypeI64 = 'I64';

    case TypeF64 = 'F64'; 

    case TypeBool = 'Bool';

    /** Structure */
    case Ident = 'Ident';
    case ParenR = ')';
    case ParenL = '(';
    case BrackR = ']';
    case BrackL = '[';
    case CurlyR = '}';
    case CurlyL = '{';

    case Semicolon = ';';
    case Comma = ',';
    // Also used for variadic
    case Range = 'Literal_Range';

    /* Literals */
    case Integer = 'Literal_Integer';
    case Hex = 'Literal_Hex';
    case Float = 'Literal_Float';
    case String = 'Literal_String';
    case Char = 'Literal_Char';
    case True = 'TRUE';
    case False = 'FALSE';
    case Null = 'NULL';

    // Used for deref and pointer type
    case Pointer = 'P*';
    // &ident
    case Ref = '&';
    
    /* Assignment */
    case Equals = '=';
    case PlusEquals = '+=';
    case MinusEquals = '-=';
    case MultiplyEquals = '*=';
    case DivideEquals = '/=';
    case ModuloEquals = '%=';

    /* Operations */
    case Plus = '+';
    case Minus = '-';
    case Multiply = '*';
    case Divide = '/';
    case Modulo = '%';
    // ident->ident
    case FieldDeref = '->';

    case Increment = '++';
    case Decrement = '--';

    case And = '&&';
    case Or = '||';
    case ShiftL = '<<';
    case ShiftR = '>>';
    case BitwiseAnd = 'Bitwise_&';
    case BitwiseOr = '|';
    case BitwiseXor = '^';

    /* Conditions */
    case Eq = '==';
    case Ne = '!=';
    case Lt = '<';
    case Lte = '<=';
    case Gt = '>';
    case Gte = '>=';

    /* Statements */
    case For = 'for';
    case While = 'while';
    case If = 'if';
    case Switch = 'switch';
    case Case = 'case';
    case Label = 'label';
    case Break = 'break';
    // No continue in holyc
    case Clazz = 'class';
    case Try = 'try';
    case Catch = 'catch';
    case Throw = 'throw';

    case Include = '#include';
    case Exe = '#exe';
    case Define = '#define';

    public function toString(): string {
        return $this->value;
    }

    /**
     * Word-keyword table (alphabetic identifiers that are reserved).
     * Operators/punctuation are handled by operatorTable().
     */
    public static function fromKeyword(string $keyword): ?Self {
        return match ($keyword) {
            "U0" => Self::TypeU0,
            "U8" => Self::TypeU8,
            "U16" => Self::TypeU16,
            "U32" => Self::TypeU32,
            "U64" => Self::TypeU64,

            "I8" => Self::TypeI8,
            "I16" => Self::TypeI16,
            "I32" => Self::TypeI32,
            "I64" => Self::TypeI64,

            "F64" => Self::TypeF64,
            "Bool" => Self::TypeBool,

            "TRUE" => Self::True,
            "FALSE" => Self::False,
            "NULL" => Self::Null,

            "for" => Self::For,
            "while" => Self::While,
            "if" => Self::If,
            "switch" => Self::Switch,
            "case" => Self::Case,
            "label" => Self::Label,
            "break" => Self::Break,
            "class" => Self::Clazz,
            "try" => Self::Try,
            "catch" => Self::Catch,
            "throw" => Self::Throw,

            "#include" => Self::Include,
            "#exe" => Self::Exe,
            "#define" => Self::Define,

            default => null,
        };
    }

    /**
     * Operator/punctuation table. Order matters: longest match wins,
     * so multi-char operators must come before their single-char prefixes.
     */
    public static function operatorTable(): array {
        return [
            // 3-char
            ["...", Self::Range],

            // 2-char
            ["==", Self::Eq],
            ["!=", Self::Ne],
            ["<=", Self::Lte],
            [">=", Self::Gte],
            ["<<", Self::ShiftL],
            [">>", Self::ShiftR],
            ["&&", Self::And],
            ["||", Self::Or],
            ["++", Self::Increment],
            ["--", Self::Decrement],
            ["+=", Self::PlusEquals],
            ["-=", Self::MinusEquals],
            ["*=", Self::MultiplyEquals],
            ["/=", Self::DivideEquals],
            ["%=", Self::ModuloEquals],
            ["->", Self::FieldDeref],

            // 1-char
            ["(", Self::ParenL],
            [")", Self::ParenR],
            ["[", Self::BrackL],
            ["]", Self::BrackR],
            ["{", Self::CurlyL],
            ["}", Self::CurlyR],
            [";", Self::Semicolon],
            [",", Self::Comma],
            ["=", Self::Equals],
            ["+", Self::Plus],
            ["-", Self::Minus],
            ["*", Self::Multiply],
            ["/", Self::Divide],
            ["%", Self::Modulo],
            ["<", Self::Lt],
            [">", Self::Gt],
            ["&", Self::BitwiseAnd],
            ["|", Self::BitwiseOr],
            ["^", Self::BitwiseXor],
        ];
    }

    public static function fromOperator(string $op): ?Self {
        foreach (Self::operatorTable() as [$text, $token]) {
            if ($text === $op) return $token;
        }
        return null;
    }
}
