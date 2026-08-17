<?php

namespace App\Models;

use App\Concerns\HasAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasAmount;

    protected $fillable = ['name', 'amount', 'amount_cents', 'category_id', 'spent_on', 'notes'];

    protected function casts(): array
    {
        return ['spent_on' => 'date:Y-m-d'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('spent_on', $year)->whereMonth('spent_on', $month);
    }
}
