<?php

namespace Holyc;

function assertIt(mixed $value, mixed $expected = "NOTHING_EXPECTED", bool $logs = true) {
    if ($logs) {
        $text = $value !== null ? strval($value) : 'null'; 
        print_r($text . "\n");
    } else {
        print_r("Cannot log!\n");
    }
    if ($expected !== 'NOTHING_EXPECTED' && $value !== $expected) {
        if (!$logs) throw new \AssertionError("Assertion Failed! Cannot log"); 
        
        $got = strval($value) . " (" . gettype($value) . ")"; 
        $exp = strval($expected) . " (" . gettype($expected) . ")"; 
        throw new \AssertionError("Assertion Failed! Got: {$got} Expected: {$exp}"); 
    }
}
