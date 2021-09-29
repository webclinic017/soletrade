<?php

namespace App\Trade\Indicator;

use App\Models\Signature;
use Illuminate\Support\Collection;

class Fib extends AbstractIndicator
{
    public array $prevFib;
    protected array $config = ['period' => 144];

    public function nearestFib(array $levels, float $price): array
    {
        $minDistance = null;
        foreach ($levels as $level => $levelPrice)
        {
            $distance = abs($price - $levelPrice);
            if (!$minDistance || $distance < $minDistance)
            {
                $minDistance = $distance;
                $fibLevel = $level;
                $fibPrice = $levelPrice;
            }
        }

        return $this->prevFib = [
            'level'    => $fibLevel,
            'price'    => $fibPrice,
            'distance' => $minDistance / $price * 100
        ];
    }

    public function raw(): array
    {
        $raw = [];

        foreach ($this->data as $timestamp => $fibLevels)
        {
            foreach ($fibLevels as $key => $val)
            {
                $raw[$key][$timestamp] = $val;
            }
        }

        return $raw;
    }

    protected function getBindValue(string|int $bind, ?int $timestamp = null): float
    {
        return $this->data[$this->current][$bind];
    }

    protected function getBindable(): array
    {
        return [0, 236, 382, 500, 618, 702, 786, 886, 1000];
    }

    protected function getBindPrice(mixed $bind): float
    {
        return $this->data[$this->current][$bind];
    }

    protected function run(): array
    {
        $fib = [];
        $period = (int)$this->config['period'];
        $highs = [];
        $lows = [];
        $bars = 0;
        foreach ($this->candles as $candle)
        {
            $highs[] = (float)$candle->h;
            $lows[] = (float)$candle->l;

            if ($bars === $period)
            {
                $highest = max($highs);
                $lowest = min($lows);

                $keys = array_keys($highs, $highest);
                $highestPosition = (int)end($keys);

                $keys = array_keys($lows, $lowest);
                $lowestPosition = (int)end($keys);

                if ($lowestPosition < $highestPosition)
                {
                    //upward
                    $fib[] = [
                        0    => $highest,
                        236  => $highest - ($highest - $lowest) * 0.236,
                        382  => $highest - ($highest - $lowest) * 0.382,
                        500  => $highest - ($highest - $lowest) * 0.500,
                        618  => $highest - ($highest - $lowest) * 0.618,
                        702  => $highest - ($highest - $lowest) * 0.702,
                        786  => $highest - ($highest - $lowest) * 0.786,
                        886  => $highest - ($highest - $lowest) * 0.886,
                        1000 => $highest - ($highest - $lowest) * 1.000
                    ];
                }
                else
                {
                    //downward
                    $fib[] = [
                        0    => $lowest,
                        236  => ($highest - $lowest) * 0.236 + $lowest,
                        382  => ($highest - $lowest) * 0.382 + $lowest,
                        500  => ($highest - $lowest) * 0.500 + $lowest,
                        618  => ($highest - $lowest) * 0.618 + $lowest,
                        702  => ($highest - $lowest) * 0.702 + $lowest,
                        786  => ($highest - $lowest) * 0.786 + $lowest,
                        886  => ($highest - $lowest) * 0.886 + $lowest,
                        1000 => ($highest - $lowest) * 1.000 + $lowest
                    ];
                }

                array_shift($highs);
                array_shift($lows);
            }
            else
            {
                $bars++;
            }
        }

        return $fib;
    }

    public function buildSignalName(array $params): string
    {
        return 'FIB-' . $params['side'] . '_' . $params['level'];
    }

    protected function getSavePoints(int|string $bind, Signature $signature): array
    {
        $points = [];

        foreach ($this->data as $timestamp => $fib)
        {
            $points[] = [
                'timestamp' => $timestamp,
                'value'     => $fib[$bind]
            ];
        }

        return $points;
    }
}