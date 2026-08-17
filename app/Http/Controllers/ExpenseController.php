<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Support\MonthCursor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $cursor = MonthCursor::fromRequest($request);

        $entries = Expense::with('category')
            ->whereYear('spent_on', $cursor->year())
            ->whereMonth('spent_on', $cursor->month())
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Expenses/Index', [
            'entries' => $entries->map(fn (Expense $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'amount_cents' => $e->amount_cents,
                'spent_on' => $e->spent_on->format('Y-m-d'),
                'notes' => $e->notes,
                'category' => $e->category
                    ? ['id' => $e->category->id, 'name' => $e->category->name, 'color' => $e->category->color]
                    : null,
            ])->values(),
            'categories' => Category::expense()->orderBy('name')->get(['id', 'name', 'color']),
            'month' => $cursor->toArray(),
            'total_cents' => (int) $entries->sum('amount_cents'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Expense::create($this->validated($request));

        return back()->with('flash', 'Dépense enregistrée.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validated($request));

        return back()->with('flash', 'Dépense mise à jour.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('flash', 'Dépense supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'spent_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }


}
