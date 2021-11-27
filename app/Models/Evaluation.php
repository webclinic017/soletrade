<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int            id
 * @property string         type
 * @property int            entry_id
 * @property TradeSetup     entry
 * @property TradeSetup     exit
 * @property int            exit_id
 * @property float          relative_roi
 * @property float          highest_roi
 * @property float          lowest_roi
 * @property float          lowest_to_highest_roi
 * @property float          used_size
 * @property float          entry_price
 * @property float          exit_price
 * @property float          close_price //rename to cancel_price
 * @property float          stop_price
 * @property float          highest_price
 * @property float          lowest_price
 * @property float          lowest_price_to_highest_exit
 * @property float          highest_price_to_lowest_exit
 * @property float          highest_entry_price
 * @property float          lowest_entry_price
 * @property bool           is_entry_price_valid
 * @property bool           is_ambiguous
 * @property bool           is_stopped
 * @property bool           is_closed
 * @property int            entry_timestamp
 * @property int            exit_timestamp
 * @property array          risk_reward_history
 * @property array          log
 * @property \Carbon\Carbon created_at
 * @property \Carbon\Carbon updated_at
 */
class Evaluation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $with = ['entry', 'exit'];
    protected array $unique = ['type', 'entry_id', 'exit_id'];
    protected $casts = [
        'risk_reward_history' => 'array',
        'log'                 => 'array'
    ];

    public function entry(): MorphTo
    {
        return $this->morphTo('entry', 'type');
    }

    public function exit(): MorphTo
    {
        return $this->morphTo('exit', 'type');
    }
}
