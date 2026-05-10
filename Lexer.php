<?php
namespace Holyc;

use Holyc\Collection;
use function Holyc\collect;
use function Holyc\token;
use function Holyc\tokenWith;

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

class LexError extends \RuntimeException {}

class Lexer {
    private Collection $tokens;
    private Collection $source;

    public function __construct(string $rawContent) {
        $this->tokens = new Collection([], LexToken::class);
        $this->source = Collection::fromString($rawContent);
    }

    /* ------------------------------------------------------------------ *
     * Character-class predicates
     * ------------------------------------------------------------------ */

    private static function isDigit(?string $c): bool {
        return $c !== null && $c >= '0' && $c <= '9';
    }

    private static function isHexDigit(?string $c): bool {
        if ($c === null) return false;
        return self::isDigit($c)
            || ($c >= 'a' && $c <= 'f')
            || ($c >= 'A' && $c <= 'F');
    }

    private static function isAlpha(?string $c): bool {
        if ($c === null || $c === '') return false;
        // HolyC allows special chars (e.g. ã for pi) in identifiers; treat any
        // non-ASCII byte (>= 0x80) as identifier-alpha so UTF-8 sequences pass.
        if (ord($c) >= 0x80) return true;
        return ($c >= 'a' && $c <= 'z')
            || ($c >= 'A' && $c <= 'Z')
            || $c === '_';
    }

    private static function isIdentChar(?string $c): bool {
        return self::isAlpha($c) || self::isDigit($c);
    }

    private static function isWhitespace(?string $c): bool {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r";
    }

    /* ------------------------------------------------------------------ *
     * Sub-lexers — each takes a Collection<string> starting at the cursor
     * and returns a Consumed { value, length } or null if it doesn't match.
     * ------------------------------------------------------------------ */

    public function lexNumber(Collection $content): ?Consumed {
        // 0x... hex literal
        if ($content->get(0) === '0' && ($content->get(1) === 'x' || $content->get(1) === 'X')) {
            return $this->lexHex($content);
        }

        $index = 0;
        $built = collect([], 'string');
        $hasDecimal = false;
        $first = $content->get(0);

        // Allow a leading "." for things like ".34"
        if ($first === '.' && self::isDigit($content->get(1))) {
            $built->push('.');
            $hasDecimal = true;
            $index = 1;
        }

        while (true) {
            $current = $content->get($index);
            if (self::isDigit($current)) {
                $built->push($current);
            } else if ($current === '.' && !$hasDecimal && self::isDigit($content->get($index + 1))) {
                $hasDecimal = true;
                $built->push($current);
            } else {
                break;
            }
            $index++;
        }

        if ($built->count() === 0) return null;
        $text = $built->join();

        // Reject lone "."
        if ($text === '.') return null;

        if ($hasDecimal) {
            return new Consumed(floatval($text), $built->count());
        }
        return new Consumed(intval($text), $built->count());
    }

    public function lexHex(Collection $content): ?Consumed {
        if ($content->get(0) !== '0') return null;
        $p = $content->get(1);
        if ($p !== 'x' && $p !== 'X') return null;
        $index = 2;
        $built = collect([], 'string');
        while (self::isHexDigit($content->get($index))) {
            $built->push($content->get($index));
            $index++;
        }
        if ($built->count() === 0) return null;
        $value = hexdec($built->join());
        return new Consumed($value, $built->count() + 2);
    }

    public function lexString(Collection $content): ?Consumed {
        $first = $content->get(0);
        if ($first !== '"' && $first !== "'") return null;
        $isSingle = $first === "'";
        $index = 1;
        $built = new Collection([], 'string');
        $char = $isSingle ? "'" : '"';

        while (true) {
            $current = $content->get($index);
            if ($current === null) {
                throw new LexError("Unterminated string literal");
            }
            if ($current === "\\") {
                $next = $content->get($index + 1);
                if ($next === null) {
                    throw new LexError("Unterminated escape in string literal");
                }
                $built->push(match ($next) {
                    'n'  => "\n",
                    't'  => "\t",
                    'r'  => "\r",
                    '0'  => "\0",
                    '\\' => "\\",
                    "'"  => "'",
                    '"'  => '"',
                    default => $next, // unknown escape: keep char verbatim
                });
                $index += 2;
                continue;
            }
            if ($current === $char) {
                break;
            }
            $built->push($current);
            $index++;
        }

        $text = $built->join();
        // Length of source consumed: index + 1 (closing quote)
        return new Consumed($text, $index + 1);
    }

    public function lexChar(Collection $content): ?Consumed {
        // HolyC char literals are written with single quotes; we treat them
        // identically to strings at the lex stage (both routed via lexString),
        // but expose this for callers that want the int code-point form.
        $s = $this->lexString($content);
        if (!$s) return null;
        return $s;
    }

    /**
     * Read a "word" — a maximal run of identifier characters [A-Za-z0-9_].
     * Stops on any delimiter (whitespace, punctuation, EOF).
     */
    public function lexWord(Collection $content): ?Consumed {
        $built = collect([], 'string');
        $index = 0;
        while (true) {
            $curr = $content->get($index);
            if ($curr === null) break;
            if (!self::isIdentChar($curr)) break;
            $built->push($curr);
            $index++;
        }
        if ($built->count() === 0) return null;
        return new Consumed($built->join(''), $built->count());
    }

    /**
     * Match a reserved word keyword. Returns Consumed<Token> or null.
     * Identifier-shaped (alpha-leading) words only.
     */
    public function lexKeyword(Collection $content): ?Consumed {
        // Preprocessor directives start with '#'
        if ($content->get(0) === '#') {
            $rest = $content->slice(1);
            $word = $this->lexWord($rest);
            if (!$word) return null;
            $token = Token::fromKeyword('#' . $word->value);
            if (!$token) return null;
            return new Consumed($token, $word->length + 1);
        }

        if (!self::isAlpha($content->get(0))) return null;
        $word = $this->lexWord($content);
        if (!$word) return null;
        $token = Token::fromKeyword($word->value);
        if (!$token) return null;
        return new Consumed($token, $word->length);
    }

    /**
     * Match an identifier (any word that is not a reserved keyword).
     * Returns Consumed<string> with the identifier text.
     */
    public function lexIdent(Collection $content): ?Consumed {
        if (!self::isAlpha($content->get(0))) return null;
        $word = $this->lexWord($content);
        if (!$word) return null;
        if (Token::fromKeyword($word->value) !== null) return null;
        return $word;
    }

    /**
     * Longest-match operator/punctuation lex. Returns Consumed<Token>.
     */
    public function lexOperator(Collection $content): ?Consumed {
        foreach (Token::operatorTable() as [$text, $token]) {
            if ($this->startsWith($content, $text)) {
                return new Consumed($token, strlen($text));
            }
        }
        return null;
    }

    /**
     * Skip C/C++ style comments. Returns the number of characters consumed,
     * or 0 if the cursor is not on a comment.
     */
    public function lexComment(Collection $content): int {
        if ($content->get(0) !== '/') return 0;
        $second = $content->get(1);

        // // line comment
        if ($second === '/') {
            $i = 2;
            while ($content->get($i) !== null && $content->get($i) !== "\n") {
                $i++;
            }
            return $i;
        }

        // /* block comment */
        if ($second === '*') {
            $i = 2;
            while (true) {
                $c = $content->get($i);
                if ($c === null) {
                    throw new LexError("Unterminated block comment");
                }
                if ($c === '*' && $content->get($i + 1) === '/') {
                    return $i + 2;
                }
                $i++;
            }
        }

        return 0;
    }

    private function startsWith(Collection $content, string $needle): bool {
        $len = strlen($needle);
        for ($i = 0; $i < $len; $i++) {
            if ($content->get($i) !== $needle[$i]) return false;
        }
        return true;
    }

    /* ------------------------------------------------------------------ *
     * Top-level driver
     * ------------------------------------------------------------------ */

    public function lex(): Collection {
        $cursor = 0;
        $tokens = new Collection([], LexToken::class);
        $n = $this->source->count();

        while ($cursor < $n) {
            $rest = $this->source->slice($cursor);
            $head = $rest->get(0);

            // Whitespace
            if (self::isWhitespace($head)) {
                $cursor++;
                continue;
            }

            // Preprocessor directives — Phase 1: skip the whole line.
            // (No newline tokens are emitted, so we can't otherwise terminate them.)
            if ($head === '#') {
                while ($cursor < $n && $this->source->get($cursor) !== "\n") {
                    $cursor++;
                }
                continue;
            }

            // Comments
            $skipped = $this->lexComment($rest);
            if ($skipped > 0) {
                $cursor += $skipped;
                continue;
            }

            // Numbers (incl. 0x hex). Also handle leading '.' followed by digit.
            if (self::isDigit($head) || ($head === '.' && self::isDigit($rest->get(1)))) {
                $num = $this->lexNumber($rest);
                if ($num) {
                    $isFloat = is_float($num->value);
                    $tokens->push(tokenWith($isFloat ? Token::Float : Token::Integer, $num->value));
                    $cursor += $num->length;
                    continue;
                }
            }

            // Strings / chars
            if ($head === '"') {
                $s = $this->lexString($rest);
                $tokens->push(tokenWith(Token::String, $s->value));
                $cursor += $s->length;
                continue;
            }
            if ($head === "'") {
                $s = $this->lexString($rest);
                $tokens->push(tokenWith(Token::Char, $s->value));
                $cursor += $s->length;
                continue;
            }

            // Keywords (alpha-leading or '#'-leading directives)
            if (self::isAlpha($head) || $head === '#') {
                $kw = $this->lexKeyword($rest);
                if ($kw) {
                    $tokens->push(token($kw->value));
                    $cursor += $kw->length;
                    continue;
                }
                $id = $this->lexIdent($rest);
                if ($id) {
                    $tokens->push(tokenWith(Token::Ident, $id->value));
                    $cursor += $id->length;
                    continue;
                }
                // '#' with no recognised directive: fall through to error
            }

            // Operators / punctuation
            $op = $this->lexOperator($rest);
            if ($op) {
                $tokens->push(token($op->value));
                $cursor += $op->length;
                continue;
            }

            // Nothing matched
            $context = $rest->slice(0, 16)->join();
            throw new LexError("Unexpected character at offset {$cursor}: " . var_export($head, true) . " near \"{$context}\"");
        }

        $this->tokens = $tokens;
        return $tokens;
    }

    public function tokens(): Collection {
        return $this->tokens;
    }
}
