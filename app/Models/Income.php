<?php

namespace App\Models;

use App\Concerns\HasAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasAmount;

    protected $fillable = ['name', 'amount', 'amount_cents', 'category_id', 'received_on', 'notes'];

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('received_on', $year)->whereMonth('received_on', $month);
    }
}
