<?php

namespace Holyc;

enum Token {
    /** Primitive Types */
    // Like void but sizeof 0, ! in Rust as opposed to ()
    case TypeU0;
    case TypeU8;
    case TypeU16;
    case TypeU32;
    case TypeU64;

    case TypeI8;
    case TypeI16;
    case TypeI32;
    case TypeI64;

    case TypeF64; 

    case TypeBool;

    /** Structure */
    case Ident;
    case ParenR;
    case ParenL;
    case BrackR;
    case BrackL;
    case CurlyR;
    case CurlyL;

    case Semicolon;
    case Comma;
    // Also used for variadic
    case Range;

    /** Literals */
    case Integer;
    case Hex;
    case Float;
    case String;
    case Char;
    case True;
    case False;
    case Null;

    // Used for deref and pointer type
    case Pointer;
    // &ident
    case Ref;
    
    /** Assignment */
    case Equals;
    case PlusEquals;
    case MinusEquals;
    case MultiplyEquals;
    case DivideEquals;
    case ModuloEquals;

    /** Operations */
    case Plus;
    case Minus;
    case Multiply;
    case Divide;
    case Modulo;
    // ident->ident
    case FieldDeref;

    case Increment;
    case Decrement;

    case And;
    case Or;
    case ShiftL;
    case ShiftR;
    case BitwiseAnd;
    case BitwiseOr;
    case BitwiseXor;

    /** Conditions */
    case Eq;
    case Ne;
    case Lt;
    case Lte;
    case Gt;
    case Gte;

    /** Statements */
    case For;
    case While;
    case If;
    case Switch;
    case Case;
    case Label;
    case Break;
    // No continue in holyc
    case Clazz;
    case Try;
    case Catch;
    case Throw;

    case HashTag;
    case Include;
    case Exe;
    case Define;


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

            "*" => Self::Pointer,
            "&" => Self::BitwiseAnd,

            "=" => Self::Equals,
            "+=" => Self::PlusEquals,
            "-=" => Self::MinusEquals,
            default => null, 
        };
    }
}
