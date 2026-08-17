<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    public const TODO = 'todo';

    public const DOING = 'doing';

    public const DONE = 'done';

    public const STATUSES = [self::TODO, self::DOING, self::DONE];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = ['title', 'status', 'priority', 'due_on', 'notes', 'position', 'completed_at'];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== self::DONE
            && $this->due_on !== null
            && $this->due_on->isPast()
            && ! $this->due_on->isToday();
    }
}
