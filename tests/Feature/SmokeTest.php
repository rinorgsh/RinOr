<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->create();
    }

    /** Chaque page se rend, avec des données réelles derrière. */
    public function test_toutes_les_pages_se_rendent(): void
    {
        $pages = [
            '/' => 'Dashboard',
            '/depenses' => 'Expenses/Index',
            '/rentrees' => 'Incomes/Index',
            '/abonnements' => 'Subscriptions/Index',
            '/tresorerie' => 'Treasuries/Index',
            '/a-faire' => 'Tasks/Index',
            '/categories' => 'Categories/Index',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    /** Un mois sans écriture ne doit pas casser le dashboard. */
    public function test_le_dashboard_supporte_un_mois_vide(): void
    {
        $this->actingAs($this->user)
            ->get('/?month=1999-01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.income_cents', 0)
                ->where('report.totals.expense_cents', 0));
    }

    /** Un paramètre de mois bricolé retombe sur le mois courant, sans erreur. */
    public function test_un_mois_invalide_retombe_sur_le_mois_courant(): void
    {
        foreach (['n-importe-quoi', '2026-13', '2026-00', '', '99999-01'] as $bad) {
            $this->actingAs($this->user)
                ->get('/?month='.urlencode($bad))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('report.month.iso', now()->format('Y-m')));
        }
    }

    public function test_le_cycle_complet_d_une_depense(): void
    {
        $create = $this->actingAs($this->user)->post('/depenses', [
            'name' => 'Courses',
            'amount' => '82,40',
            'spent_on' => now()->format('Y-m-d'),
        ]);

        // Une virgule décimale doit être refusée côté serveur : le front la
        // convertit avant l'envoi, donc recevoir "82,40" signale un bug.
        $create->assertSessionHasErrors('amount');

        $this->actingAs($this->user)->post('/depenses', [
            'name' => 'Courses',
            'amount' => 82.40,
            'spent_on' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $expense = \App\Models\Expense::where('name', 'Courses')->firstOrFail();
        $this->assertSame(8240, $expense->amount_cents);

        $this->actingAs($this->user)->put("/depenses/{$expense->id}", [
            'name' => 'Courses Colruyt',
            'amount' => 90,
            'spent_on' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertSame(9000, $expense->fresh()->amount_cents);

        $this->actingAs($this->user)->delete("/depenses/{$expense->id}")->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_le_seeder_reproduit_les_chiffres_de_l_excel(): void
    {
        $active = \App\Models\Subscription::active()->get();

        // Charge fixe mensuelle et coût annualisé, tels que vérifiés dans Excel.
        $this->assertSame(24092, $active->sum(fn ($s) => $s->monthly_cents));
        $this->assertSame(289097, $active->sum(fn ($s) => $s->yearly_cents));
        $this->assertSame(259545, (int) \App\Models\Income::sum('amount_cents'));
    }
}
