<?php

namespace App\Trade\Indicator\Helper;

/**
 * @method array prev()
 * @method array current()
 */
trait CanCross
{
    /**
     * Return true if x crosses over y.
     *
     * @param string $x One of the values calculated by the indicator.
     * @param string $y One of the values calculated by the indicator.
     *
     * @return bool
     */
    public function crossOver(string $x, string $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if (empty($prev[$x]) || empty($prev[$y]) || empty($current[$x]) || empty($current[$y]))
        {
            return false;
        }

        return $prev[$x] < $prev[$y] && $current[$x] > $current[$y];
    }

    /**
     * Return true if x crosses under y.
     *
     * @param string $x One of the values calculated by the indicator.
     * @param string $y One of the values calculated by the indicator.
     *
     * @return bool
     */
    public function crossUnder(string $x, string $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if (empty($prev[$x]) || empty($prev[$y]) || empty($current[$x]) || empty($current[$y]))
        {
            return false;
        }

        return $prev[$x] > $prev[$y] && $current[$x] < $current[$y];
    }
}