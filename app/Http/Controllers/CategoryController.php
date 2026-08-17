<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount(['incomes', 'expenses', 'subscriptions'])
            ->withSum('incomes as incomes_total_cents', 'amount_cents')
            ->withSum('expenses as expenses_total_cents', 'amount_cents')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'color' => $c->color,
                'usage_count' => $c->incomes_count + $c->expenses_count + $c->subscriptions_count,
                'total_cents' => (int) ($c->type === Category::TYPE_INCOME
                    ? $c->incomes_total_cents
                    : $c->expenses_total_cents),
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return back()->with('flash', 'Catégorie créée.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('flash', 'Catégorie mise à jour.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Les écritures liées sont conservées, leur catégorie repasse à null.
        $category->delete();

        return back()->with('flash', 'Catégorie supprimée. Les écritures liées sont conservées.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('categories', 'name')
                    ->where(fn ($q) => $q->where('type', $request->input('type')))
                    ->ignore($category?->id),
            ],
            'type' => ['required', Rule::in([Category::TYPE_INCOME, Category::TYPE_EXPENSE])],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'name.unique' => 'Une catégorie de ce type porte déjà ce nom.',
        ]);
    }
}
