<?php
namespace Holyc;

use Holyc\Collection;
use function Holyc\collect;

class Consumed {
    public function __construct(
        public mixed $value,
        public int $length 
    ) {
        //
    }

    public function __toString() {
        return strval($this->value);
    }
}

class Lexer {
    private Collection $tokens; 
    
    public function __construct(string $rawContent) {
        $this->tokens = new Collection([]); 
    }

    public function lexNumber(Collection $content): ?Consumed {
        $index = 0;
        $built = collect([], 'string'); 
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
        $number = floatval($built->join());
        return new Consumed($number, $built->count());  
    }

    public function lexString(Collection $content): ?Consumed {
        $first = $content->get(0);
        if ($first !== '"' && $first !== "'") return null; 
        $isSingle = $first === "'"; 
        $index = 1;
        $escaped = false;
        $built = new Collection([], 'string');
        while (true) {
            $current = $content->get($index);
            if ($current === "\\") {
                $escaped = true;
                $index++;
                continue;
            }

            $char = $isSingle ? "'" : '"';
            if ($current === $char && !$escaped) {
                break;
            }
            $built->push($current); 
            
            if ($escaped) {
                $escaped = false;
            }
            $index++;
        }

        $text = $built->join();
        return new Consumed($text, $built->count() + 2);
    }

    public function lexWord(Collection $content): ?Consumed {
        $built = collect();
        $index = 0; 
        while (true) {
            $curr = $content->get($index);
            if ($curr === ' ') {
                break;
            }
            $built->push($curr);
            $index++;
        }
       return new Consumed($built->join(''), $built->count()); 
    }

    public function lexKeyword(Collection $content): ?Consumed {
        $word = $this->lexWord($content);
        if (!$word) return null;
        $token = Token::fromKeyword($word);
        if (!$token) {
            return null;
        }
        return new Consumed($token, strlen($word));
    }
}
