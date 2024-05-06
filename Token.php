<?php

namespace Holyc;

use Holyc\Stringable;

enum Token: string implements Stringable {
    /** Primitive Types */
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

    /** Literals */
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
    
    /** Assignment */
    case Equals = '=';
    case PlusEquals = '+=';
    case MinusEquals = '-=';
    case MultiplyEquals = '*=';
    case DivideEquals = '/=';
    case ModuloEquals = '%=';

    /** Operations */
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

    /** Conditions */
    case Eq = '==';
    case Ne = '!=';
    case Lt = '<';
    case Lte = '<=';
    case Gt = '>';
    case Gte = '>=';

    /** Statements */
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
            
            "(" => Self::ParenL,       
            ")" => Self::ParenR,       
            "[" => Self::BrackL,       
            "]" => Self::BrackR,       
            "{" => Self::CurlyL,       
            "}" => Self::CurlyR,       
            ";" => Self::Semicolon,       
            "," => Self::Semicolon,
            "..." => Self::Range,
            "TRUE" => Self::True,
            "FALSE" => Self::False,
            "NULL" => Self::Null,
            "#define" => Self::Define,
            "->" => Self::FieldDeref,

            "*" => Self::Pointer,
            "&" => Self::BitwiseAnd,

            "=" => Self::Equals,
            "+=" => Self::PlusEquals,
            "-=" => Self::MinusEquals,
            default => null, 
        };
    }
}
