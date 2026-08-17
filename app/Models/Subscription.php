<?php

namespace App\Models;

use App\Concerns\HasAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasAmount;

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_YEARLY = 'yearly';

    protected $fillable = [
        'name', 'amount', 'amount_cents', 'cycle',
        'category_id', 'next_due_on', 'is_active', 'notes',
    ];

    protected $appends = ['monthly_cents', 'yearly_cents'];

    protected function casts(): array
    {
        return [
            'next_due_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Coût ramené au mois, pour comparer mensuels et annuels. */
    public function getMonthlyCentsAttribute(): int
    {
        return $this->cycle === self::CYCLE_YEARLY
            ? (int) round($this->amount_cents / 12)
            : $this->amount_cents;
    }

    /** Coût ramené à l'année. */
    public function getYearlyCentsAttribute(): int
    {
        return $this->cycle === self::CYCLE_YEARLY
            ? $this->amount_cents
            : $this->amount_cents * 12;
    }
}
