<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Income;
use App\Support\MonthCursor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(Request $request): Response
    {
        $cursor = MonthCursor::fromRequest($request);

        $entries = Income::with(['category', 'invoice:id,client,label,number'])
            ->whereYear('received_on', $cursor->year())
            ->whereMonth('received_on', $cursor->month())
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Incomes/Index', [
            'entries' => $entries->map(fn (Income $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'amount_cents' => $i->amount_cents,
                'received_on' => $i->received_on->format('Y-m-d'),
                'notes' => $i->notes,
                // Renseignée si la rentrée vient d'un encaissement : la
                // supprimer rouvrira la facture, il faut le dire avant.
                'invoice' => $i->invoice ? [
                    'id' => $i->invoice->id,
                    'client' => $i->invoice->client,
                    'label' => $i->invoice->label,
                ] : null,
                'category' => $i->category
                    ? ['id' => $i->category->id, 'name' => $i->category->name, 'color' => $i->category->color]
                    : null,
            ])->values(),
            'categories' => Category::income()->orderBy('name')->get(['id', 'name', 'color']),
            'month' => $cursor->toArray(),
            'total_cents' => (int) $entries->sum('amount_cents'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Income::create($this->validated($request));

        return back()->with('flash', 'Rentrée enregistrée.');
    }

    public function update(Request $request, Income $income): RedirectResponse
    {
        $income->update($this->validated($request));

        return back()->with('flash', 'Rentrée mise à jour.');
    }

    public function destroy(Income $income): RedirectResponse
    {
        $income->delete();

        return back()->with('flash', 'Rentrée supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            // `exists` interroge la table sans le scope global : sans ce
            // `where`, on pourrait rattacher une écriture à la catégorie d'un
            // autre utilisateur en devinant son id.
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $request->user()->id),
            ],
            'received_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }


}
