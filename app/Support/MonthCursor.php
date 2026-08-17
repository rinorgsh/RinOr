<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Le mois affiché, lu depuis `?month=YYYY-MM`. Toute valeur absente ou
 * invalide retombe silencieusement sur le mois courant : une URL bricolée à la
 * main ne doit jamais casser une page.
 */
readonly class MonthCursor
{
    public function __construct(public CarbonImmutable $start)
    {
    }

    public static function fromRequest(Request $request, string $key = 'month'): self
    {
        return self::parse($request->query($key));
    }

    public static function parse(mixed $value): self
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];

            if ($year >= 1970 && $year <= 2999 && $month >= 1 && $month <= 12) {
                return new self(CarbonImmutable::create($year, $month, 1)->startOfMonth());
            }
        }

        return new self(CarbonImmutable::now()->startOfMonth());
    }

    public function year(): int
    {
        return $this->start->year;
    }

    public function month(): int
    {
        return $this->start->month;
    }

    public function toArray(): array
    {
        return [
            'iso' => $this->start->format('Y-m'),
            'label' => $this->start->locale('fr')->isoFormat('MMMM YYYY'),
            'previous' => $this->start->subMonth()->format('Y-m'),
            'next' => $this->start->addMonth()->format('Y-m'),
            'is_current' => $this->start->isSameMonth(CarbonImmutable::now()),
            'today' => CarbonImmutable::now()->format('Y-m-d'),
        ];
    }
}
