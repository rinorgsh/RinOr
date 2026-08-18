<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        // Les plus urgentes d'abord : en retard, puis par échéance.
        $invoices = Invoice::with('income:id,invoice_id')
            ->orderByRaw("CASE status WHEN 'sent' THEN 0 WHEN 'draft' THEN 1 WHEN 'paid' THEN 2 ELSE 3 END")
            ->orderBy('due_on')
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'client' => $i->client,
                'label' => $i->label,
                'amount_cents' => $i->amount_cents,
                'vat_rate' => $i->vat_rate,
                'vat_cents' => $i->vat_cents,
                'total_cents' => $i->total_cents,
                'status' => $i->status,
                'issued_on' => $i->issued_on->format('Y-m-d'),
                'due_on' => $i->due_on->format('Y-m-d'),
                'paid_on' => $i->paid_on?->format('Y-m-d'),
                'is_overdue' => $i->is_overdue,
                'days_late' => $i->days_late,
                'notes' => $i->notes,
            ])->values(),
            'summary' => $this->summary($invoices),
            'clients' => $invoices->pluck('client')->unique()->sort()->values(),
            'incomeCategories' => Category::income()->orderBy('name')->get(['id', 'name', 'color']),
            'today' => CarbonImmutable::now()->format('Y-m-d'),
            'defaultDueOn' => CarbonImmutable::now()->addDays(30)->format('Y-m-d'),
        ]);
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function summary(Collection $invoices): array
    {
        $outstanding = $invoices->whereIn('status', [Invoice::DRAFT, Invoice::SENT]);
        $overdue = $invoices->filter(fn (Invoice $i) => $i->is_overdue);
        $paidThisYear = $invoices
            ->where('status', Invoice::PAID)
            ->filter(fn (Invoice $i) => $i->paid_on?->year === CarbonImmutable::now()->year);

        return [
            // Le chiffre qui manquait : ce qu'on te doit, tout de suite.
            'outstanding_cents' => $outstanding->sum(fn (Invoice $i) => $i->total_cents),
            'outstanding_count' => $outstanding->count(),

            'overdue_cents' => $overdue->sum(fn (Invoice $i) => $i->total_cents),
            'overdue_count' => $overdue->count(),
            'worst_days_late' => (int) ($overdue->max('days_late') ?? 0),

            'paid_year_cents' => $paidThisYear->sum(fn (Invoice $i) => $i->total_cents),
            'paid_year_count' => $paidThisYear->count(),

            // La TVA encaissée n'est pas à toi : la rappeler évite de la dépenser.
            'vat_collected_cents' => $paidThisYear->sum(fn (Invoice $i) => $i->vat_cents),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        Invoice::create($this->validated($request));

        return back()->with('flash', 'Facture enregistrée.');
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->update($this->validated($request));

        return back()->with('flash', 'Facture mise à jour.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        // La rentrée liée part avec (nullOnDelete la détacherait sinon en
        // laissant une rentrée orpheline qui gonflerait les totaux).
        $invoice->income?->delete();
        $invoice->delete();

        return back()->with('flash', 'Facture supprimée.');
    }

    /** Encaissement : marque payée et crée la rentrée du même montant. */
    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $request->user()->id)
                    ->where('type', Category::TYPE_INCOME),
            ],
        ]);

        $invoice->markPaid($data['paid_on'], $data['category_id'] ?? null);

        return back()->with('flash', 'Facture encaissée. La rentrée a été créée.');
    }

    public function unpay(Invoice $invoice): RedirectResponse
    {
        $invoice->markUnpaid();

        return back()->with('flash', 'Facture rouverte. La rentrée a été retirée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'number' => ['nullable', 'string', 'max:40'],
            'client' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in([Invoice::DRAFT, Invoice::SENT, Invoice::CANCELLED])],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'due_on.after_or_equal' => "L'échéance ne peut pas précéder la date d'émission.",
        ]);
    }
}
