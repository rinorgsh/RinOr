<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Treasury;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Toutes les agrégations du dashboard pour un mois donné.
 *
 * Les abonnements ne génèrent pas d'écritures de dépense : ils sont comptés à
 * part comme « charge fixe », ramenés au mois (un annuel compte pour 1/12).
 * Le total des sorties du mois = dépenses saisies + charge fixe.
 */
class MonthlyReport
{
    public function __construct(private readonly CarbonImmutable $month)
    {
    }

    public static function for(?int $year = null, ?int $month = null): self
    {
        $now = CarbonImmutable::now();

        return new self(
            CarbonImmutable::create($year ?? $now->year, $month ?? $now->month, 1)->startOfMonth()
        );
    }

    public function toArray(): array
    {
        $incomeCents = $this->incomeCents();
        $expenseCents = $this->expenseCents();
        $fixedCents = $this->subscriptionsDueCents($this->month);
        $outflowCents = $expenseCents + $fixedCents;

        return [
            'month' => [
                'year' => $this->month->year,
                'month' => $this->month->month,
                'iso' => $this->month->format('Y-m'),
                'label' => $this->month->locale('fr')->isoFormat('MMMM YYYY'),
                'previous' => $this->month->subMonth()->format('Y-m'),
                'next' => $this->month->addMonth()->format('Y-m'),
                'is_current' => $this->month->isSameMonth(CarbonImmutable::now()),
            ],
            'totals' => [
                'income_cents' => $incomeCents,
                'expense_cents' => $expenseCents,
                'fixed_cents' => $fixedCents,
                'outflow_cents' => $outflowCents,
                'net_cents' => $incomeCents - $outflowCents,
                // Référence, jamais comptée dans les sorties : c'est le total
                // annuel divisé par 12, pas un prélèvement.
                'fixed_smoothed_cents' => $this->smoothedFixedCents(),
                'savings_rate' => $incomeCents > 0
                    ? round((($incomeCents - $outflowCents) / $incomeCents) * 100, 1)
                    : null,
                'treasury_cents' => $this->treasuryCents(),
            ],
            'income_by_category' => $this->byCategory(Income::class, 'received_on'),
            'expense_by_category' => $this->byCategory(Expense::class, 'spent_on'),
            'top_income_sources' => $this->topEntries(Income::class, 'received_on'),
            'top_expenses' => $this->topEntries(Expense::class, 'spent_on'),
            'trend' => $this->trend(),
            'yearly_due_this_month' => $this->yearlyDueThisMonth(),
            'unscheduled_yearly_count' => $this->unscheduledYearlyCount(),
            'receivables' => $this->receivables(),
            'tasks' => $this->taskSummary(),
            'upcoming_subscriptions' => $this->upcomingSubscriptions(),
        ];
    }

    private function incomeCents(): int
    {
        return (int) Income::inMonth($this->month->year, $this->month->month)->sum('amount_cents');
    }

    private function expenseCents(): int
    {
        return (int) Expense::inMonth($this->month->year, $this->month->month)->sum('amount_cents');
    }

    /**
     * Les abonnements réellement prélevés sur le mois donné.
     *
     * Un mensuel tombe tous les mois. Un annuel ne tombe que le mois de son
     * échéance : en août, aucun annuel n'est dû, donc rien ne doit être compté
     * pour eux. Lisser (annuel / 12) donnait un chiffre qui n'apparaît sur
     * aucun relevé bancaire — et surtout, il masquait les mois lourds : chez
     * Rinor, mars encaisse 132 € d'annuels d'un coup.
     *
     * Un annuel sans date d'échéance ne peut être placé nulle part : il est
     * exclu et signalé à part plutôt que réparti arbitrairement.
     */
    private function subscriptionsDueCents(CarbonImmutable $month): int
    {
        return Subscription::active()->get()->sum(function (Subscription $s) use ($month) {
            if ($s->cycle === Subscription::CYCLE_MONTHLY) {
                return $s->amount_cents;
            }

            return $s->next_due_on?->month === $month->month ? $s->amount_cents : 0;
        });
    }

    /** Le total annuel ramené au mois : utile pour provisionner, jamais prélevé tel quel. */
    private function smoothedFixedCents(): int
    {
        return Subscription::active()->get()->sum(fn (Subscription $s) => $s->monthly_cents);
    }

    /** Les annuels qui tombent ce mois-ci : ils expliquent les pics. */
    private function yearlyDueThisMonth(): array
    {
        return Subscription::active()
            ->where('cycle', Subscription::CYCLE_YEARLY)
            ->whereNotNull('next_due_on')
            ->get()
            ->filter(fn (Subscription $s) => $s->next_due_on->month === $this->month->month)
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'amount_cents' => $s->amount_cents,
            ])->values()->all();
    }

    /** Annuels sans échéance : impossibles à placer dans un mois. */
    private function unscheduledYearlyCount(): int
    {
        return Subscription::active()
            ->where('cycle', Subscription::CYCLE_YEARLY)
            ->whereNull('next_due_on')
            ->count();
    }

    private function treasuryCents(): int
    {
        return Treasury::all()->sum(fn (Treasury $t) => $t->balance_cents);
    }

    /**
     * Somme du mois par catégorie, décroissante. Les écritures sans catégorie
     * sont regroupées sous « Sans catégorie » plutôt que masquées.
     *
     * @param  class-string<Income|Expense>  $model
     */
    private function byCategory(string $model, string $dateColumn): array
    {
        $table = (new $model)->getTable();

        $rows = $model::query()
            ->leftJoin('categories', 'categories.id', '=', $table.'.category_id')
            ->whereYear($table.'.'.$dateColumn, $this->month->year)
            ->whereMonth($table.'.'.$dateColumn, $this->month->month)
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total_cents')
            ->get([
                'categories.name as name',
                'categories.color as color',
                DB::raw('SUM(amount_cents) as total_cents'),
            ]);

        $total = (int) $rows->sum('total_cents');

        return $rows->map(fn ($row) => [
            'name' => $row->name ?? 'Sans catégorie',
            'color' => $row->color,
            'total_cents' => (int) $row->total_cents,
            'share' => $total > 0 ? round(((int) $row->total_cents / $total) * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * Les plus grosses écritures individuelles du mois, agrégées par libellé
     * (deux courses « Colruyt » comptent comme une seule ligne).
     *
     * @param  class-string<Income|Expense>  $model
     */
    private function topEntries(string $model, string $dateColumn, int $limit = 6): array
    {
        return $model::query()
            ->whereYear($dateColumn, $this->month->year)
            ->whereMonth($dateColumn, $this->month->month)
            ->groupBy('name')
            ->orderByDesc('total_cents')
            ->limit($limit)
            ->get(['name', DB::raw('SUM(amount_cents) as total_cents'), DB::raw('COUNT(*) as entries')])
            ->map(fn ($row) => [
                'name' => $row->name,
                'total_cents' => (int) $row->total_cents,
                'entries' => (int) $row->entries,
            ])->all();
    }

    /** Rentrées vs sorties sur les 6 mois glissants qui finissent au mois affiché. */
    private function trend(): array
    {
        return collect(range(5, 0))->map(function (int $back) {
            $m = $this->month->subMonths($back);

            $income = (int) Income::inMonth($m->year, $m->month)->sum('amount_cents');
            $expense = (int) Expense::inMonth($m->year, $m->month)->sum('amount_cents');

            // Recalculé pour CHAQUE mois : la courbe doit montrer les pics
            // d'échéances annuelles, pas les aplatir en appliquant partout la
            // valeur du mois affiché.
            $subs = $this->subscriptionsDueCents($m);

            return [
                'iso' => $m->format('Y-m'),
                'label' => $m->locale('fr')->isoFormat('MMM'),
                'income_cents' => $income,
                'outflow_cents' => $expense + $subs,
            ];
        })->all();
    }

    /**
     * L'argent facturé mais pas encore reçu. Il n'apparaît dans aucun total du
     * mois — c'est justement le point : une facture impayée est invisible tant
     * qu'on ne la regarde pas en face.
     */
    private function receivables(): array
    {
        $open = Invoice::outstanding()->get();
        $overdue = $open->filter(fn (Invoice $i) => $i->is_overdue);

        return [
            'outstanding_cents' => $open->sum(fn (Invoice $i) => $i->total_cents),
            'outstanding_count' => $open->count(),
            'overdue_cents' => $overdue->sum(fn (Invoice $i) => $i->total_cents),
            'overdue_count' => $overdue->count(),
            'worst' => $overdue->sortByDesc('days_late')->take(3)->map(fn (Invoice $i) => [
                'id' => $i->id,
                'client' => $i->client,
                'total_cents' => $i->total_cents,
                'days_late' => $i->days_late,
            ])->values()->all(),
        ];
    }

    private function taskSummary(): array
    {
        $tasks = Task::all();

        return [
            'todo' => $tasks->where('status', Task::TODO)->count(),
            'doing' => $tasks->where('status', Task::DOING)->count(),
            'done' => $tasks->where('status', Task::DONE)->count(),
            'overdue' => $tasks->filter(fn (Task $t) => $t->is_overdue)->count(),
            'next' => $tasks
                ->where('status', '!=', Task::DONE)
                ->sortBy(fn (Task $t) => $t->due_on?->timestamp ?? PHP_INT_MAX)
                ->take(4)
                ->map(fn (Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'due_on' => $t->due_on?->format('Y-m-d'),
                    'is_overdue' => $t->is_overdue,
                ])->values()->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function upcomingSubscriptions(): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return Subscription::active()
            ->whereNotNull('next_due_on')
            ->orderBy('next_due_on')
            ->limit(5)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'amount_cents' => $s->amount_cents,
                'cycle' => $s->cycle,
                'next_due_on' => $s->next_due_on->format('Y-m-d'),
                'days_left' => $today->diffInDays($s->next_due_on->toImmutable()->startOfDay(), false),
            ])->all();
    }
}
