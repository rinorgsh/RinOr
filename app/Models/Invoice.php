<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Concerns\HasAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use BelongsToUser, HasAmount;

    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::DRAFT, self::SENT, self::PAID, self::CANCELLED];

    protected $fillable = [
        'user_id', 'number', 'client', 'label', 'amount', 'amount_cents',
        'vat_rate', 'status', 'issued_on', 'due_on', 'paid_on', 'notes',
    ];

    protected $appends = ['vat_cents', 'total_cents', 'is_overdue', 'days_late'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
            'due_on' => 'date:Y-m-d',
            'paid_on' => 'date:Y-m-d',
            'vat_rate' => 'integer',
        ];
    }

    /** La rentrée créée à l'encaissement. */
    public function income(): HasOne
    {
        return $this->hasOne(Income::class);
    }

    public function getVatCentsAttribute(): int
    {
        return (int) round($this->amount_cents * $this->vat_rate / 100);
    }

    /** Ce que le client doit réellement virer. */
    public function getTotalCentsAttribute(): int
    {
        return $this->amount_cents + $this->vat_cents;
    }

    /** En retard = envoyée, échéance dépassée, toujours pas payée. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::SENT
            && $this->due_on !== null
            && $this->due_on->isBefore(CarbonImmutable::now()->startOfDay());
    }

    public function getDaysLateAttribute(): int
    {
        return $this->is_overdue
            ? (int) $this->due_on->diffInDays(CarbonImmutable::now()->startOfDay())
            : 0;
    }

    /** Ce qui reste à encaisser : envoyées, ni payées ni annulées. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::DRAFT, self::SENT]);
    }

    /**
     * Marque la facture payée et crée la rentrée correspondante.
     *
     * Les deux vont ensemble : sans ça, il faudrait ressaisir le montant dans
     * Rentrées, et cette double saisie est exactement ce qu'on finit par ne
     * plus faire.
     */
    public function markPaid(string $paidOn, ?int $categoryId = null): void
    {
        DB::transaction(function () use ($paidOn, $categoryId) {
            $this->update(['status' => self::PAID, 'paid_on' => $paidOn]);

            $this->income()->updateOrCreate(
                ['invoice_id' => $this->id],
                [
                    'user_id' => $this->user_id,
                    'name' => $this->client.' — '.$this->label,
                    'amount_cents' => $this->total_cents,
                    'category_id' => $categoryId,
                    'received_on' => $paidOn,
                ],
            );
        });
    }

    /** Rouvre la facture et retire la rentrée qu'elle avait créée. */
    public function markUnpaid(): void
    {
        DB::transaction(function () {
            $this->income?->delete();
            $this->update(['status' => self::SENT, 'paid_on' => null]);
        });
    }
}
