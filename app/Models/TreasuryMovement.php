<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Concerns\HasAmount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryMovement extends Model
{
    use BelongsToUser, HasAmount;

    public const IN = 'in';

    public const OUT = 'out';

    protected $fillable = [
        'user_id', 'treasury_id', 'direction', 'amount', 'amount_cents',
        'label', 'occurred_on', 'notes',
    ];

    protected function casts(): array
    {
        return ['occurred_on' => 'date:Y-m-d'];
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }
}
