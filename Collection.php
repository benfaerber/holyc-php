<?php

namespace Holyc;

use \TypeError;

class Collection implements \JsonSerializable, \Iterator {
    public array $items;
    private int $position = 0;
    private string $type;

    public function __construct(array $items = [], string $type = 'mixed') {
        $this->type = $type;
        $this->items = array_values($items);
        $this->position = 0;

        if (!$this->allMatch()) {
            throw new TypeError("Expected type {$type}!");
        }
    }

    public static function from(array $items, string $type = 'mixed'): Self {
        $cleaned = array_values($items); 
        return new Self($cleaned, $type);
    }

    public static function fromString(string $value): Self {
        return new Self(str_split($value), 'string');
    }

    public static function range(int $start, ?int $end = null) {
        if (!$end) return Collection::from(range(0, $start));
        return Collection::from(range($start, $end));
    }

    public function allMatch(): bool {
        return $this->every(
            fn ($item) => $this->matchesType($item, $this->type)
        ); 
    }

    public function matchesType($item, string $desiredType) {
        if ($desiredType === 'mixed') return true;
        $primitives = new Self(["boolean", "integer", "float", "string", "array", "null"]);
        $itemType = gettype($item);
        if ($primitives->contains($itemType)) {
            return $itemType === $desiredType;
        }
        
        return is_a($item, $desiredType);
    }

    public function get(int $index) {
        if ($index > $this->count() - 1) {
            return null;
        }

        return $this->items[$index];
    }

    public function push(mixed $item): Self {
        array_push($this->items, $item);
        return $this; 
    }

    public function prepend(mixed $item): Self {
        $this->items = [$item, ...$this->items];
        return $this;
    }

    public function count(): int {
        return count($this->items);
    }

    public function last(): mixed {
        return $this->get($this->count() - 1);
    }

    public function first(): mixed {
        return $this->get(0);
    }

    public function sort(): Self {
        $copy = clone $this->items;
        sort($copy);
        return Collection::from($copy, $this->type);
    }

    public function contains(mixed $desired): bool {
        foreach ($this->items as $item) {
            if ($item == $desired) {
                return true;
            }
        }
        return false;
    }

    public function map($predicate): Self {
        return Collection::from(array_map($predicate, $this->items));
    }

    public function filter($predicate): Self {
        $finished = array_values(array_filter($this->items, $predicate));
        return Collection::from($finished, $this->type);
    }
    
    public function some($predicate): bool {
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return true;
            }
        }
        return false;
    }

    public function every($predicate): bool {
        foreach ($this->items as $item) {
            if (!$predicate($item)) {
                return false;
            }
        }
        return true;
    }

    public function reduce($predicate, $start) {
        foreach ($this->items as $item) {
            $start = $predicate($start, $item);
        }
        return $start;
    }

    public function tap($predicate): Self {
        foreach ($this->items as $item) {
            $predicate($item);
        }
        return $this;
    }

    public function join(string $sep = ''): string {
        return implode($sep, $this->items);
    }

    /** Serialization */
    public function __toString(): string {
        return json_encode($this->items, JSON_PRETTY_PRINT);
    }

    public function jsonSerialize(): mixed {
        return json_encode($this->items); 
    }

    /** Iterator Implementation */
    public function rewind(): void {
        $this->position = 0;
    }

    public function current() {
        return $this->items[$this->position];
    }

    public function next(): void {
        $this->position++;
    }

    public function valid(): bool {
        return $this->position < count($this->items) - 1;
    }

    public function key(): int {
        return $this->position;
    }
}

function collect(array|string $items = [], string $type = 'mixed') {
    return is_string($items) 
        ? Collection::fromString($items) 
        : Collection::from($items, $type);
}

function collectionTests() {
    $myDogs = new Collection(["tim", "bob", "frank"], 'string');
    echo $myDogs;

    $myNumbers = new Collection([1, 2, 3], 'integer');
    echo $myNumbers;

    $myClasses = new Collection([new Collection([]), new Collection([])], "Collection");
    echo $myClasses;

    try {
        $failure = new Collection([1, "fail", 3], 'int');
        echo $failure;
    } catch (TypeError $error) {
        echo "Had expected type error!";
    }

    $numbers = Collection::range(10)
        ->map(fn ($t) => $t + 1)
        ->filter(fn ($t) => $t % 2 === 0)
        ->tap(fn ($t) => printf("%d\n", $t))
        ->reduce(fn ($acc, $curr) => $acc + $curr, 0);

    echo "\n\n";
    echo $numbers;
} 
