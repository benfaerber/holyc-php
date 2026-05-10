<?php

namespace Holyc;

class CollectionTest extends TestCase {
    public function testFromArray() {
        $c = collect([1, 2, 3]);
        $this->assertEquals(3, $c->count());
        $this->assertEquals(1, $c->first());
        $this->assertEquals(3, $c->last());
    }

    public function testFromString() {
        $c = collect("abc");
        $this->assertEquals(3, $c->count());
        $this->assertEquals('a', $c->get(0));
        $this->assertEquals('c', $c->get(2));
    }

    public function testGetOutOfBoundsReturnsNull() {
        $c = collect([1, 2]);
        $this->assertNull($c->get(99));
        $this->assertNull($c->get(2));
    }

    public function testMap() {
        $r = collect([1, 2, 3])->map(fn ($x) => $x * 2);
        $this->assertEquals([2, 4, 6], $r->items);
    }

    public function testFilter() {
        $r = collect([1, 2, 3, 4])->filter(fn ($x) => $x % 2 === 0);
        $this->assertEquals([2, 4], $r->items);
    }

    public function testReduce() {
        $sum = collect([1, 2, 3, 4])->reduce(fn ($acc, $x) => $acc + $x, 0);
        $this->assertEquals(10, $sum);
    }

    public function testEvery() {
        $this->assertTrue(collect([2, 4, 6])->every(fn ($x) => $x % 2 === 0));
        $this->assertFalse(collect([2, 3, 6])->every(fn ($x) => $x % 2 === 0));
    }

    public function testSome() {
        $this->assertTrue(collect([1, 2, 3])->some(fn ($x) => $x === 2));
        $this->assertFalse(collect([1, 2, 3])->some(fn ($x) => $x === 99));
    }

    public function testContains() {
        $c = collect([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(99));
    }

    public function testJoin() {
        $this->assertEquals("a,b,c", collect(['a', 'b', 'c'])->join(','));
        $this->assertEquals("abc", collect(['a', 'b', 'c'])->join());
    }

    public function testSlice() {
        $r = collect([1, 2, 3, 4, 5])->slice(1, 3);
        $this->assertEquals([2, 3, 4], $r->items);
    }

    public function testSort() {
        // Regression: sort() previously crashed because it used `clone`
        // on the underlying array.
        $r = collect([3, 1, 2])->sort();
        $this->assertEquals([1, 2, 3], $r->items);
    }

    public function testTypeChecking() {
        new Collection([1, 2, 3], 'integer'); // should not throw
        $this->assertThrows(\TypeError::class,
            fn () => new Collection([1, "fail", 3], 'integer'));
    }

    public function testIterationVisitsEveryElement() {
        // Regression: valid() previously had an off-by-one that skipped
        // the last element during foreach.
        $c = collect([10, 20, 30]);
        $seen = [];
        foreach ($c as $v) $seen[] = $v;
        $this->assertEquals([10, 20, 30], $seen);
    }

    public function testRangeWithStartAndEnd() {
        $this->assertEquals([1, 2, 3, 4, 5], Collection::range(1, 5)->items);
    }

    public function testPushReturnsSelfForChaining() {
        $c = collect([1])->push(2)->push(3);
        $this->assertEquals([1, 2, 3], $c->items);
    }

    public function testPrepend() {
        $c = collect([1, 2])->prepend(0);
        $this->assertEquals([0, 1, 2], $c->items);
    }
}
