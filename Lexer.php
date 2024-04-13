<?php
namespace Holyc;

use Holyc\Collection;

class Lexer {
    private Collection $tokens; 
    
    public function __construct(string $rawContent) {
        $this->tokens = new Collection([]); 
    }

    public function lexNumber(Collection $content): ?float {
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
    
        if ($built->count() === 0) return null;
        $literal = $built->join();
        return floatval($literal);
    }

    public function lexString(Collection $content): ?string {
        $first = $content->get(0);
        if ($first !== '"' && $first !== "'") return null; 
        $isSingle = $first === "'"; 
        $index = 1;
        $escaped = false;
        $built = new Collection([$first], 'string');
        while (true) {
            $current = $content->get($index);
            if ($current === "\\") {
                $escaped = true;
                $index++;
                continue;
            }

            $char = $isSingle ? "'" : '"';
            $built->push($current); 
            if ($current === $char && !$escaped) {
                break;
            }

            if ($escaped) {
                $escaped = false;
            }
            $index++;
        }

        return $built->join();
    }
}
