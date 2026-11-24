<?php

namespace Tests\Unit;

use App\Services\CombinedQtyAllocator;
use PHPUnit\Framework\TestCase;

class CombinedQtyAllocatorTest extends TestCase
{
    public function test_whole_qty_and_largest_remainder(): void
    {
        $allocator=new CombinedQtyAllocator();
        $this->assertEquals([1=>54,2=>36],$allocator->allocate(90,[1=>60,2=>40]));
        $this->assertEquals([1=>34,2=>33,3=>33],$allocator->allocate(100,[3=>1,1=>1,2=>1]));
        $this->assertEquals([1=>61,2=>41],$allocator->allocate(102,[1=>60,2=>40]));
    }
    public function test_zero_negative_and_decimal(): void
    {
        $allocator=new CombinedQtyAllocator();
        $this->assertEquals([1=>0,2=>10,3=>0],$allocator->allocate(10,[1=>-2,2=>4,3=>0]));
        $this->assertEquals([1=>7,2=>0],$allocator->allocate(7,[2=>-5,1=>0]));
        $this->assertEquals([1=>0.3334,2=>0.3334,3=>0.3333],$allocator->allocate(1.0000/3*3+0.0001,[1=>1,2=>1,3=>1]));
        $this->assertEquals([],$allocator->allocate(5,[]));
    }
}
