<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class StrategyTester
{
    use HasConfig;

    protected array $config = [
        'maxCandles' => null,
        'startDate'  => null,
        'endDate'    => null
    ];

    protected Evaluator $evaluator;
    protected Summarizer $summarizer;
    protected array $result = [];

    public function __construct(protected SymbolRepository $symbolRepo, array $config = [])
    {
        $this->mergeConfig($config);
        $this->evaluator = App::make(Evaluator::class);
        $this->summarizer = App::make(Summarizer::class);
    }

    public function run(string $strategyClass, Symbol $symbol): array
    {
        $strategy = $this->setupStrategy($strategyClass, $this->config);

        Log::execTime(static function () use (&$symbol) {

            $updater = $symbol->exchange()->updater();

            if ($symbol->last_update <= (time() - 3600) * 1000)
            {
                $updater->update($symbol);
            }

            $updater->updateByInterval(interval: '1m',
                filter: static fn(Symbol $v) => $v->symbol === $symbol->symbol &&
                    $v->exchange_id === $symbol->exchange_id);

        }, 'CandleUpdater::update()');

        Log::execTime(static function () use (&$symbol, &$strategy, &$result) {
            $result = $strategy->run($symbol);
        }, 'StrategyTester::run()');

        Log::execTime(function () use (&$symbol, &$result) {
            $this->prepareResult($result, $symbol);
        }, 'StrategyTester::prepareResult()');

        return $this->result;
    }

    protected function setupStrategy(string $class, array $config): AbstractStrategy
    {
        if (!is_subclass_of($class, AbstractStrategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        return new $class(config: $config);
    }

    protected function prepareResult(Collection $result, Symbol $symbol): void
    {
        $with = ['entry', 'entry.bindings', 'exit', 'exit.bindings'];
        /**
         * @var TradeSetup[] $trades
         */
        foreach ($result as $id => $trades)
        {
            $this->result['trade_setups'][$id] = $this->pairEvaluateSummarize($trades,
                array_merge($with, [
                    'entry.signals',
                    'entry.signals.bindings',
                    'exit.signals',
                    'exit.signals.bindings'
                ]))->toArray();
        }

        foreach ($symbol->cachedSignals() as $indicator => $signals)
        {
            $this->result['signals'][$indicator] = $this->pairEvaluateSummarize($signals,
                $with)->toArray();
        }
    }

    /**
     * @param TradeSetup[]|Signal[] $trades
     */
    protected function pairEvaluateSummarize(Collection $trades, array $with = []): Collection
    {
        $evaluations = new Collection();
        $lastCandle = $this->symbolRepo->fetchLastCandle($trades->first()->symbol);

        foreach ($trades as $trade)
        {
            if (!isset($entry))
            {
                $entry = $trade;
                continue;
            }

            if ($entry->side !== $trade->side &&
                $entry->timestamp < $lastCandle->t &&
                $trade->timestamp < $lastCandle->t)
            {
                $evaluations[] = $this->evaluator->evaluate($entry, $trade);

                $entry = $trade;
            }
        }

        $evaluations = $this->freshenEvaluations($evaluations, $with);
        $summary = $this->summarizer->summarize($evaluations);

        return new Collection([
            'trades'  => $evaluations,
            'summary' => $summary
        ]);
    }


    /**
     * @param \App\Models\Evaluation[] $evaluations
     *
     * @return \App\Models\Evaluation[]
     */
    protected function freshenEvaluations(Collection $evaluations, array $with = []): Collection
    {
        foreach ($evaluations as $key => $evaluation)
        {
            $evaluations[$key] = $evaluation->fresh($with);
        }

        return $evaluations;
    }
}