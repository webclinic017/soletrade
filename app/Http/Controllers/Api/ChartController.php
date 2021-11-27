<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\HasName;
use App\Trade\Log;
use App\Trade\StrategyTester;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class ChartController extends Controller
{
    public function __construct(protected Request $request, protected SymbolRepository $symbolRepo)
    {

    }

    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $this->getKeyByValue('exchange', $this->mapClassByName(Config::exchanges(), true));
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            $indicators = $this->mapClassByName(Config::indicators(), true);
            return $this->candles(exchange: $exchange,
                symbolName: $symbol,
                interval: $interval,
                indicators: array_map(fn($v) => array_search($v, $indicators), $request->get('indicators', [])),
                strategy: $this->getKeyByValue('strategy', $this->mapClassByName(Config::strategies(), true)),
                range: json_decode($request->get('range'), true),
                limit: $request->get('limit'));
        }

        return [
            'strategies' => $this->mapClassByName(Config::strategies()),
            'exchanges'  => $this->mapClassByName(Config::exchanges()),
            'symbols'    => Config::symbols(),
            'indicators' => $this->mapClassByName(Config::indicators()),
            'intervals'  => $this->symbolRepo->fetchIntervals()
        ];
    }

    protected function getKeyByValue(string $name, array $items): ?string
    {
        if ($param = $this->request->get($name))
        {
            return array_search($param, $items);
        }
        return null;
    }

    /**
     * @param HasName[]|string[] $classes
     */
    protected function mapClassByName(array $classes, bool $assoc = false)
    {
        $mapped = [];
        foreach ($classes as $class)
        {
            if ($assoc)
            {
                $mapped[$class] = $class::name();
            }
            else
            {
                $mapped[] = $class::name();
            }
        }

        return $mapped;
    }

    /**
     * @param AbstractExchange $exchange
     */
    public function candles(string $exchange,
                            string $symbolName,
                            string $interval,
                            array  $indicators,
                            string $strategy = null,
                            ?array $range = null,
                            ?int   $limit = null): array
    {

        $start = $range ? Carbon::parse($range['start'])->getTimestampMs() : null;
        $end = $range ? Carbon::parse($range['end'])->getTimestampMs() : null;

        $symbol = $this->getSymbol($exchange, $symbolName, $interval);

        if (!$symbol)
        {
            $filter = static fn(Symbol $symbol): bool => $symbol->symbol == $symbolName && $symbol->interval == $interval;
            $symbol = $exchange::instance()
                ->updater()
                ->updateByInterval(interval: $interval, filter: $filter)
                ?->first();
        }

        abort_if(!$symbol, 404, "Symbol $symbolName was not found.");

        if ($symbol->last_update <= $end)
        {
            $symbol->exchange()->updater()->update($symbol);
        }

        $candles = $symbol->candles($range ? null : $limit, $start, $end);
        $this->symbolRepo->initIndicators($symbol, $candles, $indicators);

        if ($strategy)
        {
            $tester = new StrategyTester(App::make(SymbolRepository::class), $strategy, [
                'startDate' => $start,
                'endDate'   => $end
            ]);

            $tester->runStrategy($symbol);

            Log::execTimeStart('Evaluating and summarizing trades');
            $summary = $tester->summary();
            $summary['evaluations'] = $summary['evaluations']->map(
                static fn(Evaluation $evaluation): Evaluation => $evaluation->fresh([
//                        'entry.bindings',
//                        'exit.bindings',
//                        'entry.signals.bindings',
//                        'exit.signals.bindings'
                ]));

            Log::execTimeFinish('Evaluating and summarizing trades');

            Log::execTimeStart('Preparing symbol');
            $symbol = $symbol->toArray();
            $symbol['strategy'] = ['trades' => $summary];
            Log::execTimeFinish('Preparing symbol');

            return $symbol;
        }

        return $symbol->toArray();
    }

    /**
     * @param AbstractExchange|string $exchange
     * @param string                  $symbolName
     * @param string                  $interval
     *
     * @return \App\Models\Symbol|null
     */
    protected function getSymbol(AbstractExchange|string $exchange, string $symbolName, string $interval): ?\App\Models\Symbol
    {
        return $this->symbolRepo->fetchSymbol(exchange: $exchange::instance(), symbolName: $symbolName, interval: $interval);
    }

}
