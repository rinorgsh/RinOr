<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function invoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client' => 'DuoGroep',
            'label' => 'Création site web',
            'amount' => 1500,
            'vat_rate' => 21,
            'status' => Invoice::SENT,
            'issued_on' => '2026-03-01',
            'due_on' => '2026-03-31',
        ], $overrides));
    }

    public function test_le_total_est_le_ht_plus_la_tva(): void
    {
        $invoice = $this->invoice();

        $this->assertSame(150000, $invoice->amount_cents);
        $this->assertSame(31500, $invoice->vat_cents);
        $this->assertSame(181500, $invoice->total_cents);
    }

    public function test_une_facture_echue_et_non_payee_est_en_retard(): void
    {
        $late = $this->invoice(['due_on' => now()->subDays(10)->format('Y-m-d')]);
        $this->assertTrue($late->is_overdue);
        $this->assertSame(10, $late->days_late);

        $future = $this->invoice(['due_on' => now()->addDays(10)->format('Y-m-d')]);
        $this->assertFalse($future->is_overdue);

        // Une facture payée n'est jamais en retard, même échue.
        $paid = $this->invoice([
            'due_on' => now()->subDays(10)->format('Y-m-d'),
            'status' => Invoice::PAID,
        ]);
        $this->assertFalse($paid->is_overdue);
    }

    public function test_encaisser_cree_la_rentree_du_meme_montant(): void
    {
        $invoice = $this->invoice();
        $category = Category::create([
            'user_id' => $this->user->id, 'name' => 'Prestation client',
            'type' => 'income', 'color' => '#1baf7a',
        ]);

        $this->post("/factures/{$invoice->id}/encaisser", [
            'paid_on' => '2026-04-15',
            'category_id' => $category->id,
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('2026-04-15', $invoice->paid_on->format('Y-m-d'));

        // La rentrée est créée automatiquement : pas de double saisie.
        $income = Income::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(181500, $income->amount_cents);   // TTC, pas HT
        $this->assertSame($category->id, $income->category_id);
        $this->assertSame($this->user->id, $income->user_id);
    }

    public function test_rouvrir_retire_la_rentree(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);

        $this->assertSame(1, Income::where('invoice_id', $invoice->id)->count());

        $this->post("/factures/{$invoice->id}/rouvrir")->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::SENT, $invoice->status);
        $this->assertNull($invoice->paid_on);
        // Sinon la rentrée resterait et gonflerait les totaux du mois.
        $this->assertSame(0, Income::where('invoice_id', $invoice->id)->count());
    }

    public function test_encaisser_deux_fois_ne_duplique_pas_la_rentree(): void
    {
        $invoice = $this->invoice();

        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-20']);

        $this->assertSame(1, Income::where('invoice_id', $invoice->id)->count());
        $this->assertSame('2026-04-20', Income::where('invoice_id', $invoice->id)->first()->received_on->format('Y-m-d'));
    }

    public function test_supprimer_une_facture_payee_retire_sa_rentree(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);

        $this->delete("/factures/{$invoice->id}")->assertRedirect();

        $this->assertDatabaseCount('invoices', 0);
        // Une rentrée orpheline continuerait de compter dans les totaux.
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_l_echeance_ne_peut_pas_preceder_l_emission(): void
    {
        $this->post('/factures', [
            'client' => 'X', 'label' => 'Y', 'amount' => 100, 'vat_rate' => 21,
            'status' => Invoice::SENT, 'issued_on' => '2026-03-10', 'due_on' => '2026-03-01',
        ])->assertSessionHasErrors('due_on');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_le_dashboard_annonce_ce_qu_on_te_doit(): void
    {
        $this->invoice(['due_on' => now()->subDays(140)->format('Y-m-d')]);
        $this->invoice(['client' => 'Autre', 'amount' => 500, 'due_on' => now()->addDays(10)->format('Y-m-d')]);
        $this->invoice(['client' => 'Payée', 'amount' => 9999, 'status' => Invoice::PAID]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('report.receivables.outstanding_cents', 181500 + 60500)
            ->where('report.receivables.outstanding_count', 2)
            ->where('report.receivables.overdue_count', 1)
            ->where('report.receivables.overdue_cents', 181500));
    }

    /* ================= Cohérence facture <-> rentrée ================= */

    public function test_supprimer_la_rentree_rouvre_la_facture_sans_la_supprimer(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);
        $income = Income::where('invoice_id', $invoice->id)->firstOrFail();

        $this->delete("/rentrees/{$income->id}")->assertRedirect();

        $invoice->refresh();

        // La facture survit : le client doit toujours cet argent.
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(Invoice::SENT, $invoice->status);
        $this->assertNull($invoice->paid_on);
        $this->assertSame(0, Income::count());
    }

    public function test_apres_suppression_de_la_rentree_la_facture_recompte_dans_les_creances(): void
    {
        $invoice = $this->invoice(['due_on' => now()->addDays(10)->format('Y-m-d')]);
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => now()->format('Y-m-d')]);

        // Encaissée : elle sort des créances.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('report.receivables.outstanding_cents', 0));

        $this->delete('/rentrees/'.Income::where('invoice_id', $invoice->id)->first()->id);

        // Rouverte : l'argent redevient visible comme dû. C'est tout l'enjeu —
        // sinon il disparaissait des totaux ET des créances.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('report.receivables.outstanding_cents', 181500)
            ->where('report.receivables.outstanding_count', 1));
    }

    public function test_supprimer_une_rentree_ordinaire_ne_touche_a_aucune_facture(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);

        $manuelle = Income::create([
            'name' => 'Remboursement', 'amount' => 50, 'received_on' => '2026-04-20',
        ]);

        $this->delete("/rentrees/{$manuelle->id}")->assertRedirect();

        // La facture encaissée reste payée.
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
        $this->assertSame(1, Income::count());
    }

    public function test_rouvrir_depuis_la_facture_ne_double_pas_la_reouverture(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);

        $this->post("/factures/{$invoice->id}/rouvrir")->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::SENT, $invoice->status);
        $this->assertNull($invoice->paid_on);
        $this->assertSame(0, Income::count());
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_supprimer_la_facture_emporte_sa_rentree_sans_la_ressusciter(): void
    {
        $invoice = $this->invoice();
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);

        $this->delete("/factures/{$invoice->id}")->assertRedirect();

        // Les deux disparaissent, et la suppression de la rentrée n'a pas
        // recréé une facture « à encaisser » au passage.
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_le_cycle_complet_encaisser_supprimer_reencaisser(): void
    {
        $invoice = $this->invoice();

        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15']);
        $this->delete('/rentrees/'.Income::where('invoice_id', $invoice->id)->first()->id);
        $this->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-05-02']);

        $invoice->refresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('2026-05-02', $invoice->paid_on->format('Y-m-d'));
        $this->assertSame(1, Income::where('invoice_id', $invoice->id)->count());
        $this->assertSame(181500, Income::where('invoice_id', $invoice->id)->first()->amount_cents);
    }

    public function test_une_facture_n_est_pas_visible_par_un_autre_compte(): void
    {
        $invoice = $this->invoice();
        $bob = User::factory()->create();

        $this->actingAs($bob)->get('/factures')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invoices', []));

        $this->actingAs($bob)->post("/factures/{$invoice->id}/encaisser", ['paid_on' => '2026-04-15'])
            ->assertNotFound();

        $this->assertSame(Invoice::SENT, $invoice->fresh()->status);
    }
}
