<?php

namespace Holyc;

use Error;

// PHP, its 2024. Why are we like this?
function attemptToString(mixed $value): string {
    $text = 'null';
    try {
        $text = strval($value);
    } catch (Error $e) {
        if (in_array('toString', get_class_methods($value))) {
            return $value->toString(); 
        } 
        
        $msg = substr($e, 0, 50);
        $text = "Error: {$msg}";
    }
    return $text; 
}

function assertIt(mixed $value, mixed $expected = "NOTHING_EXPECTED", bool $logs = true) {
    $text = attemptToString($value); 
    print_r($text . "\n"); 
    
    if ($expected !== 'NOTHING_EXPECTED' && $value !== $expected) {
        if (!$logs) throw new \AssertionError("Assertion Failed! Cannot log"); 
        
        $got = strval($value) . " (" . gettype($value) . ")"; 
        $exp = strval($expected) . " (" . gettype($expected) . ")"; 
        throw new \AssertionError("Assertion Failed! Got: {$got} Expected: {$exp}"); 
    }
}
