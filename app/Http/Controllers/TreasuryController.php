<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\TreasuryMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TreasuryController extends Controller
{
    public function index(): Response
    {
        $treasuries = Treasury::with('movements')->orderBy('id')->get();

        return Inertia::render('Treasuries/Index', [
            'treasuries' => $treasuries->map(fn (Treasury $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'color' => $t->color,
                'target_cents' => $t->target_cents,
                'balance_cents' => $t->balance_cents,
                'movements' => $t->movements->map(fn (TreasuryMovement $m) => [
                    'id' => $m->id,
                    'direction' => $m->direction,
                    'amount_cents' => $m->amount_cents,
                    'label' => $m->label,
                    'occurred_on' => $m->occurred_on->format('Y-m-d'),
                    'notes' => $m->notes,
                ])->values(),
            ])->values(),
            'total_cents' => $treasuries->sum(fn (Treasury $t) => $t->balance_cents),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Treasury::create($this->validatedTreasury($request));

        return back()->with('flash', 'Caisse créée.');
    }

    public function update(Request $request, Treasury $treasury): RedirectResponse
    {
        $treasury->update($this->validatedTreasury($request));

        return back()->with('flash', 'Caisse mise à jour.');
    }

    public function destroy(Treasury $treasury): RedirectResponse
    {
        // Les mouvements partent avec la caisse (cascadeOnDelete).
        $treasury->delete();

        return back()->with('flash', 'Caisse supprimée avec son historique.');
    }

    public function storeMovement(Request $request, Treasury $treasury): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'label' => ['required', 'string', 'max:160'],
            'occurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'label.required' => 'Indique la provenance (entrée) ou le motif (sortie).',
        ]);

        // Une sortie ne peut pas vider la caisse au-delà de son solde : la
        // vérification et l'écriture sont dans la même transaction pour qu'une
        // double soumission ne puisse pas passer entre les deux.
        DB::transaction(function () use ($treasury, $data) {
            if ($data['direction'] === TreasuryMovement::OUT) {
                $cents = (int) round((float) $data['amount'] * 100);
                $balance = $treasury->balance_cents;

                if ($cents > $balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Solde insuffisant : la caisse contient '
                            .number_format($balance / 100, 2, ',', ' ').' €.',
                    ]);
                }
            }

            $treasury->movements()->create($data);
        });

        return back()->with('flash', $data['direction'] === TreasuryMovement::IN
            ? 'Argent ajouté à la caisse.'
            : 'Retrait enregistré.');
    }

    public function destroyMovement(Treasury $treasury, TreasuryMovement $movement): RedirectResponse
    {
        abort_unless($movement->treasury_id === $treasury->id, 404);

        $movement->delete();

        return back()->with('flash', 'Mouvement supprimé.');
    }

    private function validatedTreasury(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'target' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $data['target_cents'] = isset($data['target']) && $data['target'] !== null
            ? (int) round((float) $data['target'] * 100)
            : null;

        unset($data['target']);

        return $data;
    }
}
