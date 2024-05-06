<?php

namespace Holyc;

/** __toString cannot be implemented on Enums for some reason... */
interface Stringable {
    public function toString(): string;
}
