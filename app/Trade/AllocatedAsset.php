<?php

namespace App\Trade;

use App\Trade\Evaluation\Position;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;

final class AllocatedAsset
{
    protected float $amount;

    public function __construct(public readonly Balance $balance,
                                public readonly Asset $asset,
                                float  $amount)
    {
        $this->allocate($amount);
    }

    public function allocate(float $amount): void
    {
        $this->balance->update();

        if ($this->asset->available() < $amount)
        {
            throw new \LogicException('Allocated asset amount exceeds available amount.');
        }

        $this->amount = $amount;
    }

    public function getRealSize(float $proportionalSize): float
    {
        $this->assertGreaterThanZero($proportionalSize);
        $this->assertLessThanMaxPositionSize($proportionalSize);

        return $this->amount * $proportionalSize / Position::MAX_SIZE;
    }

    private function assertGreaterThanZero(float $value): void
    {
        if ($value <= 0)
        {
            throw new \LogicException('Argument $value must be greater than zero.');
        }
    }

    private function assertLessThanMaxPositionSize(float $proportionalSize): void
    {
        if ($proportionalSize > Position::MAX_SIZE)
        {
            throw new \LogicException('Argument $proportionalSize exceeds the maximum position size.');
        }
    }

    public function getProportionalSize(float $realSize): float
    {
        $this->assertGreaterThanZero($realSize);
        $this->assertLessThanAllocation($realSize);

        return $realSize / $this->amount * Position::MAX_SIZE;
    }

    private function assertLessThanAllocation(float $size): void
    {
        if ($size > $this->amount)
        {
            throw new \LogicException('Argument $size exceeds the allocated asset amount.');
        }
    }

    public function amount(): float
    {
        return $this->amount;
    }
}