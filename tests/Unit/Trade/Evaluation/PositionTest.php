<?php

namespace Tests\Unit\Trade\Evaluation;

use App\Trade\Evaluation\Position;
use App\Trade\Evaluation\Price;
use PHPUnit\Framework\TestCase;

class PositionTest extends TestCase
{
    public function test_close(): void
    {
        $pos = $this->getPosition(true, $size = 1, 1, 4);
        $this->assertEquals(-10, $pos->roi(0.9));
        $this->assertEquals($this->calcRelativeRoi(-10, 1), $pos->relativeRoi(0.9));
        $pos->close(time());
        $this->assertEquals(300, $pos->exitRoi());
        $this->assertEquals($this->calcRelativeRoi(300, $size), $pos->relativeExitRoi());
    }

    protected function getPosition(bool $isBuy, float $size, float $entry, float $exit = 0, float $stop = 0): Position
    {
        return new Position($isBuy,
            $size,
            time(),
            new Price($entry),
            new Price($exit),
            new Price($stop)
        );
    }

    public function test_recalculating_closed_position_roi_should_throw_exception(): void
    {
        $pos = $this->getPosition(true, 1, 1, 2, 0.5);
        $pos->stop(time());
        $this->expectExceptionMessage('ROI for a closed position can not be recalculated');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $pos->roi(0.9);
    }

    public function test_stop(): void
    {
        $pos = $this->getPosition(true, $size = 1, 1, 2, 0.5);
        $this->assertEquals(-10, $pos->roi(0.9));
        $this->assertEquals($this->calcRelativeRoi(-10, $size), $pos->relativeRoi(0.9));
        $pos->stop(time());
        $this->assertEquals(-50, $pos->exitRoi());
        $this->assertEquals(-0.5, $pos->relativeExitRoi());
    }

    public function test_partial_profit_decrease_buy_roi(): void
    {
        $pos = $this->getPosition(true, $size = 50, 1, 1.5, 0.5);
        $this->assertEquals(0, $pos->roi(1));
        $this->assertEquals(100, $pos->roi(2));
        $this->assertEquals(200, $pos->roi(3));
        $this->assertEquals(-50, $pos->roi(0.5));
        $this->assertEquals(-100, $pos->roi(0));
        $this->assertEquals(-200, $pos->roi(-1));

        $pos->stop(time());
        $this->assertEquals(-50, $pos->exitRoi());
        $this->assertEquals($this->calcRelativeRoi(-50, $size), $pos->relativeExitRoi());

        $pos = $this->getPosition(true, $size = 1, 1, 1.5);
        $pos->decreaseSize(0.5, 2);
        $this->assertEquals(100, $pos->roi(2));
        $this->assertEquals(75, $pos->roi(1.5));

        $pos->close(time());
        $this->assertEquals(75, $pos->exitRoi());
        $this->assertEquals($this->calcRelativeRoi(75, $size), $pos->relativeExitRoi());
    }

    protected function calcRelativeRoi(float $roi, float $size): float
    {
        return $roi * $size / Position::MAX_SIZE;
    }

    public function test_roi_is_equal_after_decrease_size(): void
    {
        $pos = $this->getPosition(true, 100, 1);
        $before = $pos->roi(0.5);
        $pos->decreaseSize(50, 0.5);
        $after = $pos->roi(0.5);
        $this->assertEquals($before, $after);
    }

    public function test_partial_loss_decrease_buy_roi(): void
    {
        $pos = $this->getPosition(true, $size = 100, 1, 3, 0.5);
        $this->assertEquals(0, $pos->roi(1));
        $this->assertEquals(100, $pos->roi(2));
        $this->assertEquals(200, $pos->roi(3));
        $this->assertEquals(-50, $pos->roi(0.5));
        $this->assertEquals(-100, $pos->roi(0));
        $this->assertEquals(-200, $pos->roi(-1));

        $pos->stop(time());
        $this->assertEquals(-50, $pos->exitRoi());
        $this->assertEquals($this->calcRelativeRoi(-50, $size), $pos->relativeExitRoi());

        $pos = $this->getPosition(true, $size = 100, 1, 2);
        $pos->decreaseSize(50, 0.5);
        $this->assertEquals(-25, $pos->roi(1));
        $this->assertEquals(0, $pos->roi(1.5));
        $this->assertEquals(25, $pos->roi(2));

        $pos->close(time());
        $this->assertEquals(25, $pos->exitRoi());
        $this->assertEquals($this->calcRelativeRoi(25, $size), $pos->relativeExitRoi());
    }

    public function test_partial_profit_decrease_sell_roi(): void
    {
        $pos = $this->getPosition(false, 100, 1);
        $this->assertEquals(0, $pos->roi(1));
        $this->assertEquals(100, $pos->roi(0));
        $this->assertEquals(200, $pos->roi(-1));
        $this->assertEquals(-50, $pos->roi(1.5));
        $this->assertEquals(-100, $pos->roi(2));
        $this->assertEquals(-200, $pos->roi(3));

        $pos = $this->getPosition(false, 100, 1);
        $pos->decreaseSize(50, 0);
        $this->assertEquals(100, $pos->roi(0));
        $this->assertEquals(75, $pos->roi(0.5));
        $this->assertEquals(150, $pos->roi(-1));
    }

    public function test_partial_loss_decrease_sell_roi(): void
    {
        $pos = $this->getPosition(false, 100, 1);
        $this->assertEquals(0, $pos->roi(1));
        $this->assertEquals(-100, $pos->roi(2));
        $this->assertEquals(-200, $pos->roi(3));
        $this->assertEquals(50, $pos->roi(0.5));
        $this->assertEquals(100, $pos->roi(0));
        $this->assertEquals(200, $pos->roi(-1));

        $pos = $this->getPosition(false, 100, 1);
        $pos->decreaseSize(50, 2);
        $this->assertEquals(-100, $pos->roi(2));
        $this->assertEquals(-50, $pos->roi(1));
        $this->assertEquals(0, $pos->roi(0));
    }

    public function test_get_invalid_price(): void
    {
        $pos = $this->getPosition(true, 1, 1);

        $this->expectError();
        /** @noinspection PhpExpressionResultUnusedInspection */
        $pos->price('invalid');
    }

    public function test_reducing_below_used_size_should_throw_exception(): void
    {
        $pos = $this->getPosition(true, 1, 1);

        $this->expectExceptionMessage('Reduce size can not be greater than used size.');
        $pos->decreaseSize(2, 1);
    }

    public function test_get_asset_amount(): void
    {
        $pos = $this->getPosition(true, 1, 1);
        $this->assertEquals(1, $pos->getAssetAmount());

        $pos->increaseSize(1, 0.5);
        $this->assertEquals(3, $pos->getAssetAmount());

        $pos->increaseSize(1, 0.1);
        $this->assertEquals(13, $pos->getAssetAmount());

        $this->expectExceptionMessage('Position is open but no asset left.');
        $pos->decreaseSize(3, 1);
    }

    public function test_relative_buy_roi(): void
    {
        $pos = $this->getPosition(true, 50, 50);
        $this->assertEquals(10, $pos->roi(55));
        $this->assertEquals(5, $pos->relativeRoi(55));
        $this->assertEquals(-20, $pos->roi(40));
        $this->assertEquals(-10, $pos->relativeRoi(40));
    }

    public function test_relative_sell_roi(): void
    {
        $pos = $this->getPosition(false, 50, 50);
        $this->assertEquals(-10, $pos->roi(55));
        $this->assertEquals(-5, $pos->relativeRoi(55));
        $this->assertEquals(20, $pos->roi(40));
        $this->assertEquals(10, $pos->relativeRoi(40));
    }

    public function test_get_used_size(): void
    {
        $pos = $this->getPosition(true, 2, 1);
        $this->assertEquals(2, $pos->getUsedSize());

        $pos->decreaseSize(1, 1);
        $this->assertEquals(1, $pos->getUsedSize());

        $pos->increaseSize(5, 5);
        $this->assertEquals(6, $pos->getUsedSize());
    }

    public function test_get_break_even_price(): void
    {
        $pos = $this->getPosition(true, 2, 1);
        $this->assertEquals(1, $pos->getBreakEvenPrice());

        $pos->increaseSize(1, 0.5);
        $this->assertEquals(0.75, $pos->getBreakEvenPrice());
    }

    public function test_buy_roi(): void
    {
        $pos = $this->getPosition(true, 10, 10);
        $this->assertEquals(0, $pos->roi(10));

        $pos->increaseSize(10, 5);
        $this->assertEquals(-25, $pos->roi(5));

        $pos->increaseSize(10, 20);
        $this->assertEquals(133.33333333333, $pos->roi(20));

        $this->assertEquals(0, $pos->roi($pos->getBreakEvenPrice()));
    }

    public function test_increasing_above_max_size_should_throw_exception(): void
    {
        $pos = $this->getPosition(true, 1, 1);
        $this->expectExceptionMessage('The requested size is bigger than the remaining size.');
        $pos->increaseSize(Position::MAX_SIZE + 1, 1);
    }

    public function test_sell_roi(): void
    {
        $pos = $this->getPosition(false, 10, 10);
        $this->assertEquals(0, $pos->roi(10));

        $pos->increaseSize(10, 5);
        $this->assertEquals(25, $pos->roi(5));

        $pos->increaseSize(10, 2.5);
        $this->assertEquals(41.666666666667, $pos->roi(2.5));

        $this->assertEquals(0, $pos->roi($pos->getBreakEvenPrice()));
    }
}