<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Les montants sont stockés en centimes entiers (`amount_cents`) pour éviter
 * toute dérive de virgule flottante sur de l'argent. Ce trait expose un
 * attribut virtuel `amount` en euros, utilisable en lecture comme en écriture.
 */
trait HasAmount
{
    public function initializeHasAmount(): void
    {
        $this->appends = array_unique([...$this->appends, 'amount']);
    }

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->amount_cents / 100,
            set: fn (float|int|string $value): array => [
                'amount_cents' => (int) round((float) $value * 100),
            ],
        );
    }
}
