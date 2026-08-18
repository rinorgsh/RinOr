<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le tableau de bord doit montrer ce qui est réellement sorti du compte, pas
 * une moyenne. Un annuel ne coûte rien onze mois sur douze.
 */
class DashboardSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Subscription::create(['name' => 'Spotify', 'amount' => 10, 'cycle' => 'monthly']);
        Subscription::create(['name' => 'Netflix', 'amount' => 20, 'cycle' => 'monthly']);

        // 120 € dus chaque mois de mars, et rien les onze autres mois.
        Subscription::create([
            'name' => 'Forge', 'amount' => 120, 'cycle' => 'yearly',
            'next_due_on' => '2027-03-01',
        ]);
    }

    public function test_un_mois_sans_echeance_annuelle_ne_compte_que_les_mensuels(): void
    {
        $this->get('/?month=2026-08')->assertInertia(fn ($page) => $page
            // 10 + 20, surtout pas + 120/12
            ->where('report.totals.fixed_cents', 3000)
            ->where('report.yearly_due_this_month', []));
    }

    public function test_le_mois_de_l_echeance_porte_tout_l_annuel(): void
    {
        $this->get('/?month=2026-03')->assertInertia(fn ($page) => $page
            ->where('report.totals.fixed_cents', 3000 + 12000)
            ->count('report.yearly_due_this_month', 1));
    }

    public function test_le_montant_lisse_reste_disponible_mais_a_part(): void
    {
        // (10 + 20) + 120/12 = 40 €/mois de moyenne.
        $this->get('/?month=2026-08')->assertInertia(fn ($page) => $page
            ->where('report.totals.fixed_smoothed_cents', 4000)
            // Et il n'entre pas dans les sorties.
            ->where('report.totals.outflow_cents', 3000));
    }

    public function test_la_somme_des_douze_mois_egale_le_cout_annuel_reel(): void
    {
        $total = 0;

        foreach (range(1, 12) as $month) {
            $response = $this->get(sprintf('/?month=2026-%02d', $month));
            $total += $response->inertiaProps()['report']['totals']['fixed_cents'];
        }

        // 30 € × 12 + 120 € une fois = 480 €. Rien n'est perdu ni compté deux
        // fois : on a juste cessé d'étaler.
        $this->assertSame(48000, $total);
    }

    public function test_la_tendance_montre_les_pics_au_lieu_de_les_aplatir(): void
    {
        $trend = $this->get('/?month=2026-05')->inertiaProps()['report']['trend'];

        $byMonth = collect($trend)->keyBy('iso');

        // Mars porte l'annuel, avril non : une courbe plate serait un mensonge.
        $this->assertSame(3000 + 12000, $byMonth['2026-03']['outflow_cents']);
        $this->assertSame(3000, $byMonth['2026-04']['outflow_cents']);
    }

    public function test_un_annuel_sans_echeance_est_signale_et_non_reparti(): void
    {
        Subscription::create(['name' => 'Sans date', 'amount' => 60, 'cycle' => 'yearly']);

        $this->get('/?month=2026-08')->assertInertia(fn ($page) => $page
            // Il n'est ajouté à aucun mois...
            ->where('report.totals.fixed_cents', 3000)
            // ...mais on le dit, plutôt que de le faire disparaître en silence.
            ->where('report.unscheduled_yearly_count', 1));
    }

    public function test_un_abonnement_en_pause_ne_compte_dans_aucun_mois(): void
    {
        Subscription::create([
            'name' => 'En pause', 'amount' => 500, 'cycle' => 'monthly', 'is_active' => false,
        ]);

        $this->get('/?month=2026-08')->assertInertia(fn ($page) => $page
            ->where('report.totals.fixed_cents', 3000));
    }
}
