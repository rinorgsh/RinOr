<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Subscription;
use App\Models\Treasury;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_montants_sont_stockes_en_centimes_entiers(): void
    {
        $expense = Expense::create([
            'name' => 'Courses',
            'amount' => 82.40,
            'spent_on' => '2026-08-17',
        ]);

        $this->assertSame(8240, $expense->amount_cents);
        $this->assertSame(82.40, $expense->amount);
    }

    public function test_additionner_des_centimes_ne_derive_pas(): void
    {
        // 0,10 + 0,20 vaut 0,30000000000000004 en flottant. En centimes, non.
        foreach ([0.10, 0.20] as $amount) {
            Expense::create(['name' => 'x', 'amount' => $amount, 'spent_on' => '2026-08-17']);
        }

        $this->assertSame(30, (int) Expense::sum('amount_cents'));
    }

    public function test_un_abonnement_annuel_est_ramene_au_mois(): void
    {
        $yearly = Subscription::create([
            'name' => 'Forge',
            'amount' => 99.14,
            'cycle' => 'yearly',
        ]);

        $this->assertSame(9914, $yearly->yearly_cents);
        $this->assertSame(826, $yearly->monthly_cents); // 9914 / 12 arrondi
    }

    public function test_un_abonnement_mensuel_est_ramene_a_l_annee(): void
    {
        $monthly = Subscription::create([
            'name' => 'Spotify',
            'amount' => 6.99,
            'cycle' => 'monthly',
        ]);

        $this->assertSame(699, $monthly->monthly_cents);
        $this->assertSame(8388, $monthly->yearly_cents);
    }

    public function test_le_solde_d_une_caisse_est_calcule_depuis_ses_mouvements(): void
    {
        $caisse = Treasury::create(['name' => 'Épargne', 'color' => '#1baf7a']);

        $caisse->movements()->createMany([
            ['direction' => TreasuryMovement::IN, 'amount' => 500, 'label' => 'Prestation', 'occurred_on' => '2026-06-01'],
            ['direction' => TreasuryMovement::IN, 'amount' => 300, 'label' => 'Virement', 'occurred_on' => '2026-07-01'],
            ['direction' => TreasuryMovement::OUT, 'amount' => 219, 'label' => 'Écran', 'occurred_on' => '2026-08-01'],
        ]);

        $this->assertSame(58100, $caisse->fresh()->balance_cents);
    }

    public function test_on_ne_peut_pas_retirer_plus_que_le_solde(): void
    {
        $user = User::factory()->create();
        $caisse = Treasury::create(['name' => 'Épargne', 'color' => '#1baf7a']);

        $caisse->movements()->create([
            'direction' => TreasuryMovement::IN,
            'amount' => 100,
            'label' => 'Dépôt',
            'occurred_on' => '2026-08-01',
        ]);

        $this->actingAs($user)
            ->post("/tresorerie/{$caisse->id}/mouvements", [
                'direction' => 'out',
                'amount' => 150,
                'label' => 'Trop gros',
                'occurred_on' => '2026-08-17',
            ])
            ->assertSessionHasErrors('amount');

        // Le mouvement refusé ne doit rien avoir laissé derrière lui.
        $this->assertSame(10000, $caisse->fresh()->balance_cents);
        $this->assertDatabaseCount('treasury_movements', 1);
    }

    public function test_supprimer_une_categorie_conserve_les_ecritures(): void
    {
        $user = User::factory()->create();

        $category = \App\Models\Category::create([
            'name' => 'Alimentation',
            'type' => 'expense',
            'color' => '#1baf7a',
        ]);

        $expense = Expense::create([
            'name' => 'Courses',
            'amount' => 50,
            'category_id' => $category->id,
            'spent_on' => '2026-08-17',
        ]);

        $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect();

        // L'écriture survit, elle perd juste sa catégorie.
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'category_id' => null]);
    }
}
