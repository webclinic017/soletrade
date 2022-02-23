<?php

namespace Trade\Evaluation;

use App\Models\Candle;
use App\Trade\Evaluation\Position;
use App\Models\Signature;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Evaluation\TradeLoop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TradeLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_to_exit_with_config_param_close_on_exit(): void
    {
        $symbol = Symbol::factory()->create();
        $time = time();

        /** @var Collection<Candle> $candles */
        $candles = Candle::factory()
            ->for($symbol)
            ->fillBetween($time - 86400, $time, 3600)
            ->priceLowerThan(2)
            ->priceHigherThan(1)
            ->create();

        $candles[3]->update([
            'h' => 2,
            'l' => 2,
            'c' => 2,
            'o' => 2,
        ]);

        $tradeFactory = TradeSetup::factory()
            ->for($symbol)
            ->for(Signature::factory()->create());

        /** @var TradeSetup $entry */
        $entry = $tradeFactory->create([
            'side'       => 'SELL',
            'price'      => 2,
            'timestamp'  => $candles[2]->t,
            'price_date' => $candles[3]->t - 1000
        ]);

        $popped = $candles->pop();
        /** @var TradeSetup $exit */
        $exit = $tradeFactory->create([
            'side'       => 'BUY',
            'price'      => 1,
            'timestamp'  => $candles->last()->t,
            'price_date' => $popped->t - 1000
        ]);

        $candles->last()->update([
            'h' => 1,
            'l' => 1,
            'c' => 1,
            'o' => 1,
        ]);

        $loop = new TradeLoop($entry, $entry->symbol, ['closeOnExit' => true]);
        $loop->runToExit($exit);

        $status = $loop->status();
        $position = $status->getPosition();

        $this->assertInstanceOf(Position::class, $position);

        $exitTime = $position->exitTime();
        $this->assertNotNull($exitTime);
        //account for evaluation interval?
        $this->assertEquals($exit->price_date, $exitTime);
    }

    public function test_run_with_config_param_timeout(): void
    {
        $symbol = Symbol::factory()->create();
        $time = time();

        /** @var Candle[] $candles */
        $candles = Candle::factory()
            ->for($symbol)
            ->fillBetween($time - 86400, $time, 3600)
            ->priceLowerThan(2)
            ->create();

        $candles[3]->update([
            'h' => 2,
            'l' => 2,
            'c' => 2,
            'o' => 2,
        ]);

        /** @var TradeSetup $entry */
        $entry = TradeSetup::factory()
            ->for($symbol)
            ->for(Signature::factory()->create())
            ->create([
                'side'       => 'SELL',
                'price'      => 2,
                'timestamp'  => $candles[2]->t,
                'price_date' => $candles[3]->t - 1000
            ]);

        $loop = new TradeLoop($entry, $entry->symbol, ['timeout' => 60 * 3]);
        $loop->run();

        $status = $loop->status();
        $position = $status->getPosition();

        $this->assertInstanceOf(Position::class, $position);

        $entryTime = $position->entryTime();
        $exitTime = $position->exitTime();

        $this->assertNotNull($exitTime);
        //account for evaluation interval?
        $this->assertEquals($entryTime + (3600 * 3 * 1000), $exitTime);
    }
}