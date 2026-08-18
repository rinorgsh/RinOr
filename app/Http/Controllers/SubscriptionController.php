<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        // Actifs d'abord, puis du plus lourd au plus léger en coût mensualisé
        // (sinon un annuel de 100 € passerait devant un mensuel de 90 €).
        $subscriptions = Subscription::with('category')
            ->orderByDesc('is_active')
            ->orderByRaw("CASE cycle WHEN 'yearly' THEN amount_cents / 12.0 ELSE amount_cents END DESC")
            ->get();

        $active = $subscriptions->where('is_active', true);

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions->map(fn (Subscription $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'amount_cents' => $s->amount_cents,
                'cycle' => $s->cycle,
                'monthly_cents' => $s->monthly_cents,
                'yearly_cents' => $s->yearly_cents,
                'next_due_on' => $s->next_due_on?->format('Y-m-d'),
                'is_active' => $s->is_active,
                'notes' => $s->notes,
                'category' => $s->category ? [
                    'id' => $s->category->id,
                    'name' => $s->category->name,
                    'color' => $s->category->color,
                ] : null,
            ])->values(),
            'categories' => Category::expense()->orderBy('name')->get(['id', 'name', 'color']),
            'summary' => $this->summary($active, $subscriptions->count()),
        ]);
    }

    /**
     * Trois chiffres distincts, parce qu'ils répondent à trois questions
     * différentes et qu'on les confond facilement :
     *
     *  - `monthly_*`  ce qui part du compte CHAQUE mois (mensuels seulement).
     *  - `yearly_*`   ce qui part UNE FOIS par an (annuels seulement).
     *  - `total_*`    le coût réel sur douze mois : mensuels × 12 + annuels.
     *
     * `smoothed_monthly_cents` est le total annuel divisé par 12 : utile pour
     * provisionner, mais ce n'est PAS un montant réellement prélevé. Il est
     * nommé et étiqueté séparément pour qu'on ne le prenne pas pour tel.
     *
     * @param  \Illuminate\Support\Collection<int, Subscription>  $active
     */
    private function summary(Collection $active, int $totalCount): array
    {
        $monthly = $active->where('cycle', Subscription::CYCLE_MONTHLY);
        $yearly = $active->where('cycle', Subscription::CYCLE_YEARLY);

        $monthlyCents = (int) $monthly->sum('amount_cents');
        $yearlyCents = (int) $yearly->sum('amount_cents');
        $totalCents = $monthlyCents * 12 + $yearlyCents;

        return [
            'monthly_cents' => $monthlyCents,
            'monthly_count' => $monthly->count(),
            'monthly_over_year_cents' => $monthlyCents * 12,

            'yearly_cents' => $yearlyCents,
            'yearly_count' => $yearly->count(),

            'total_yearly_cents' => $totalCents,
            'smoothed_monthly_cents' => (int) round($totalCents / 12),

            'active_count' => $active->count(),
            'inactive_count' => $totalCount - $active->count(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        Subscription::create($this->validated($request));

        return back()->with('flash', 'Abonnement ajouté.');
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($this->validated($request));

        return back()->with('flash', 'Abonnement mis à jour.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('flash', 'Abonnement supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cycle' => ['required', Rule::in([Subscription::CYCLE_MONTHLY, Subscription::CYCLE_YEARLY])],
            // `exists` interroge la table sans le scope global : sans ce
            // `where`, on pourrait rattacher une écriture à la catégorie d'un
            // autre utilisateur en devinant son id.
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $request->user()->id),
            ],
            'next_due_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
