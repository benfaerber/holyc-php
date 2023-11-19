<?php
include 'collection.php';

class Node {
    public mixed $value;
    public ?Collection $leaves;

    public function __construct(mixed $value, ?Collection $leaves = null) {
        $this->value = $value;
        $this->leaves = $leaves;
    }

    public static function from(mixed $value, ?Collection $leaves = null): Self {
        return new Self($value, $leaves);
    } 
}

function node(mixed $value, ?Collection $leaves = null): Node {
    return Node::from($value, $leaves);
}

function treeTest() {
    $tree = node('global', collect([
        node("prinft"),
        node(
            "if",
            collect([node("1"), node("="), node("2")])
        ),
    ])); 


}
