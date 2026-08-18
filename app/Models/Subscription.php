<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Concerns\HasAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToUser, HasAmount;

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_YEARLY = 'yearly';

    protected $fillable = [
        'user_id', 'name', 'amount', 'amount_cents', 'cycle',
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

    /**
     * La prochaine échéance réelle, recalculée à la lecture.
     *
     * `next_due_on` est une **ancre**, pas une valeur vivante : rien ne la fait
     * avancer en base. Sans ce calcul, tous les abonnements mensuels passeraient
     * « en retard » le mois suivant et y resteraient pour toujours — il faudrait
     * corriger vingt dates à la main chaque mois.
     *
     * Calculer à la lecture plutôt que via une tâche planifiée : aucun cron à
     * faire tourner, et le résultat est juste même si le serveur est resté
     * éteint six mois.
     *
     * `addMonthsNoOverflow` évite le piège du 31 : une échéance au 31 janvier
     * tombe au 28 février, pas au 3 mars.
     */
    public function nextDueDate(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        if ($this->next_due_on === null) {
            return null;
        }

        $from ??= CarbonImmutable::now()->startOfDay();
        $date = CarbonImmutable::parse($this->next_due_on)->startOfDay();

        if (! $date->isBefore($from)) {
            return $date;
        }

        // Saut direct, puis ajustement : une ancre vieille de trois ans ne doit
        // pas coûter trente-six itérations.
        $date = $this->cycle === self::CYCLE_MONTHLY
            ? $date->addMonthsNoOverflow((int) $date->diffInMonths($from))
            : $date->addYearsNoOverflow((int) $date->diffInYears($from));

        while ($date->isBefore($from)) {
            $date = $this->cycle === self::CYCLE_MONTHLY
                ? $date->addMonthNoOverflow()
                : $date->addYearNoOverflow();
        }

        return $date;
    }

    /**
     * Le mois anniversaire d'un annuel. Invariant par construction : ajouter des
     * années ne change pas le mois, donc l'ancre suffit.
     */
    public function dueMonth(): ?int
    {
        return $this->next_due_on?->month;
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
