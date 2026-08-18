<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        Subscription::create(['name' => 'Spotify', 'amount' => 6.99, 'cycle' => 'monthly']);
        Subscription::create(['name' => 'Claude', 'amount' => 108.90, 'cycle' => 'monthly']);
        Subscription::create(['name' => 'Forge', 'amount' => 99.14, 'cycle' => 'yearly']);
        Subscription::create(['name' => 'Domaine', 'amount' => 9.55, 'cycle' => 'yearly']);
    }

    private function summary(): array
    {
        return $this->get('/abonnements')
            ->inertiaProps()['summary'];
    }

    public function test_chaque_mois_ne_compte_que_les_mensuels(): void
    {
        // 6,99 + 108,90 — surtout PAS un douzième des annuels.
        $this->assertSame(11589, $this->summary()['monthly_cents']);
        $this->assertSame(2, $this->summary()['monthly_count']);
    }

    public function test_une_fois_par_an_ne_compte_que_les_annuels(): void
    {
        // 99,14 + 9,55
        $this->assertSame(10869, $this->summary()['yearly_cents']);
        $this->assertSame(2, $this->summary()['yearly_count']);
    }

    public function test_le_total_annuel_est_mensuels_fois_douze_plus_annuels(): void
    {
        $s = $this->summary();

        $this->assertSame(
            $s['monthly_cents'] * 12 + $s['yearly_cents'],
            $s['total_yearly_cents'],
        );
        $this->assertSame(11589 * 12 + 10869, $s['total_yearly_cents']); // 149 937
    }

    public function test_le_montant_lisse_est_le_total_divise_par_douze(): void
    {
        $s = $this->summary();

        $this->assertSame(
            (int) round($s['total_yearly_cents'] / 12),
            $s['smoothed_monthly_cents'],
        );

        // Et il diffère bien de ce qui est réellement prélevé chaque mois :
        // c'est toute la raison de les afficher séparément.
        $this->assertNotSame($s['monthly_cents'], $s['smoothed_monthly_cents']);
    }

    public function test_un_abonnement_en_pause_ne_compte_dans_aucun_total(): void
    {
        Subscription::create([
            'name' => 'En pause',
            'amount' => 500,
            'cycle' => 'monthly',
            'is_active' => false,
        ]);

        $s = $this->summary();

        $this->assertSame(11589, $s['monthly_cents']);
        $this->assertSame(1, $s['inactive_count']);
        $this->assertSame(4, $s['active_count']);
    }
}
