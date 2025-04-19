<?php

namespace Holyc;

class Str implements Stringable {
    public function __construct(private string $value)
    {
        
    }

    public function toString(): string {
        return $this->value;
    }

    public function __toString() {
        return $this->toString();
    }
}
