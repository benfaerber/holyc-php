<?php
namespace Holyc;

use Holyc\Collection;

class Lexer {
    private Collection $tokens; 
    
    public function __construct(string $rawContent) {
        $this->tokens = new Collection([]); 
    }

    public function lexNumber(Collection $content): float {
        $index = 0;
        $built = new Collection([], 'string'); 
        $hasDecimal = false;
        $digits = Collection::fromString('1234567890');
        $decimal = ".";
        while (true) {
            $current = $content->get($index);
            if ($digits->contains($current)) {
                $built->push($current);
            } else if ($current === $decimal && !$hasDecimal) {
                $hasDecimal = true;
                $built->push($current);
            } else {
                break;
            }

            $index++;
        }

        $literal = $built->join();
        return floatval($literal);
    }
}
