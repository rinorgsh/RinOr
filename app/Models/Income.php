<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Concerns\HasAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use BelongsToUser, HasAmount;

    protected $fillable = ['user_id', 'name', 'amount', 'amount_cents', 'category_id', 'invoice_id', 'received_on', 'notes'];

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        static::deleted(function (Income $income) {
            if ($income->invoice_id === null) {
                return;
            }

            // Supprimer l'encaissement rouvre la facture. Elle n'est pas
            // supprimée : le client te doit toujours cet argent, seul le
            // paiement est annulé. Sans ça la facture resterait « payée » sans
            // aucune rentrée derrière — invisible dans les totaux comme dans
            // les créances.
            Invoice::withoutGlobalScope('owner')
                ->whereKey($income->invoice_id)
                ->update([
                    'status' => Invoice::SENT,
                    'paid_on' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Renseignée si la rentrée vient de l'encaissement d'une facture. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('received_on', $year)->whereMonth('received_on', $month);
    }
}
