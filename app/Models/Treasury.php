<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treasury extends Model
{
    protected $fillable = ['name', 'description', 'color', 'target_cents'];

    protected $appends = ['balance_cents'];

    public function movements(): HasMany
    {
        return $this->hasMany(TreasuryMovement::class)->latest('occurred_on')->latest('id');
    }

    /**
     * Solde de la caisse = entrées - sorties. Calculé, jamais stocké, pour
     * qu'il soit impossible de le désynchroniser des mouvements.
     */
    public function getBalanceCentsAttribute(): int
    {
        $in = (int) $this->movements()->where('direction', TreasuryMovement::IN)->sum('amount_cents');
        $out = (int) $this->movements()->where('direction', TreasuryMovement::OUT)->sum('amount_cents');

        return $in - $out;
    }
}
