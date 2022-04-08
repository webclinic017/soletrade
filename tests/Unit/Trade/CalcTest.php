<?php

namespace Trade;

use App\Trade\Calc;
use PHPUnit\Framework\TestCase;

class CalcTest extends TestCase
{
    public function test_duration(): void
    {
        $this->assertEquals(60, Calc::duration('1m'));
        $this->assertEquals(60 * 5, Calc::duration('5m'));
        $this->assertEquals(60 ** 2, Calc::duration('1h'));
        $this->assertEquals(60 ** 2 * 4, Calc::duration('4h'));
        $this->assertEquals(60 ** 2 * 24, Calc::duration('1d'));
        $this->assertEquals(60 ** 2 * 24 * 7, Calc::duration('1w'));
        $this->assertEquals(60 ** 2 * 24 * 30, Calc::duration('1M'));
    }

    public function test_as_ms(): void
    {
        $time = time();
        $this->assertEquals($time * 1000, Calc::asMs($time));
        $this->assertEquals($time * 1000, Calc::asMs($time * 1000));
        $this->expectException(\LogicException::class);
        Calc::asMs($time * 10);
    }

    public function test_elapsed_time(): void
    {
        $time = time();
        $this->assertEquals("0:0:0:59", Calc::elapsedTime($time - 59));
        $this->assertEquals("0:0:1:0", Calc::elapsedTime($time - 60));
        $this->assertEquals("0:0:1:1", Calc::elapsedTime($time - 61));
        $this->assertEquals("0:0:1:59", Calc::elapsedTime($time - 119));
        $this->assertEquals("0:0:2:0", Calc::elapsedTime($time - 120));
        $this->assertEquals("0:0:2:1", Calc::elapsedTime($time - 121));
        $this->assertEquals("0:0:2:59", Calc::elapsedTime($time - 179));
        $this->assertEquals("0:23:59:0", Calc::elapsedTime($time - 23 * 60 * 60 - 59 * 60));
        $this->assertEquals("0:1:0:0", Calc::elapsedTime($time - 60 * 60));
        $this->assertEquals("1:0:0:0", Calc::elapsedTime($time - 86400));
        $this->assertEquals("60:0:0:0", Calc::elapsedTime($time - 86400 * 60));
        $this->assertEquals("60:1:1:1", Calc::elapsedTime($time - 86400 * 60 - 3600 - 60 - 1));
        $this->assertEquals("60:23:59:59", Calc::elapsedTime($time - 86400 * 60 - 23 * 60 ** 2 - 59 * 60 - 59));
        $this->assertEquals("61:0:0:0", Calc::elapsedTime($time - 86400 * 60 - 23 * 60 ** 2 - 59 * 60 - 60));
        $this->assertEquals("101:23:59:59", Calc::elapsedTime($time - 86400 * 101 - 23 * 60 ** 2 - 59 * 60 - 59));
        $this->assertEquals("102:0:0:0", Calc::elapsedTime($time - 86400 * 101 - 23 * 60 ** 2 - 59 * 60 - 60));
    }
}
