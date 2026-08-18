<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Treasury;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'étanchéité entre comptes. Chaque test décrit une manière concrète dont les
 * finances d'un utilisateur pourraient fuiter chez un autre.
 */
class IsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['email' => 'alice@test.be']);
        $this->bob = User::factory()->create(['email' => 'bob@test.be']);
    }

    /** Crée un jeu complet appartenant à $user. */
    private function dataFor(User $user): array
    {
        $category = Category::create([
            'user_id' => $user->id, 'name' => 'Alimentation',
            'type' => 'expense', 'color' => '#1baf7a',
        ]);

        return [
            'category' => $category,
            'expense' => Expense::create([
                'user_id' => $user->id, 'name' => "Courses de {$user->email}",
                'amount' => 100, 'category_id' => $category->id, 'spent_on' => now()->format('Y-m-d'),
            ]),
            'income' => Income::create([
                'user_id' => $user->id, 'name' => "Salaire de {$user->email}",
                'amount' => 2000, 'received_on' => now()->format('Y-m-d'),
            ]),
            'subscription' => Subscription::create([
                'user_id' => $user->id, 'name' => "Netflix de {$user->email}",
                'amount' => 15, 'cycle' => 'monthly',
            ]),
            'treasury' => $treasury = Treasury::create([
                'user_id' => $user->id, 'name' => "Épargne de {$user->email}", 'color' => '#1baf7a',
            ]),
            'movement' => TreasuryMovement::create([
                'user_id' => $user->id, 'treasury_id' => $treasury->id,
                'direction' => 'in', 'amount' => 500,
                'label' => 'Secret', 'occurred_on' => now()->format('Y-m-d'),
            ]),
            'task' => Task::create([
                'user_id' => $user->id, 'title' => "Tâche de {$user->email}",
            ]),
        ];
    }

    public function test_les_listes_ne_montrent_que_ses_propres_donnees(): void
    {
        $this->dataFor($this->alice);
        $this->dataFor($this->bob);

        $pages = [
            '/depenses' => 'alice@test.be',
            '/rentrees' => 'alice@test.be',
            '/abonnements' => 'alice@test.be',
            '/tresorerie' => 'alice@test.be',
            '/a-faire' => 'alice@test.be',
        ];

        foreach ($pages as $url => $needle) {
            $content = $this->actingAs($this->bob)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                $needle,
                $content,
                "La page {$url} laisse fuir des données d'Alice chez Bob.",
            );
        }
    }

    public function test_on_ne_peut_pas_lire_ni_modifier_l_enregistrement_d_un_autre(): void
    {
        $alice = $this->dataFor($this->alice);

        // La liaison implicite de route passe par le scope global : l'id
        // d'Alice n'existe tout simplement pas pour Bob.
        $routes = [
            ['put', "/depenses/{$alice['expense']->id}"],
            ['delete', "/depenses/{$alice['expense']->id}"],
            ['put', "/rentrees/{$alice['income']->id}"],
            ['delete', "/rentrees/{$alice['income']->id}"],
            ['put', "/abonnements/{$alice['subscription']->id}"],
            ['delete', "/abonnements/{$alice['subscription']->id}"],
            ['put', "/tresorerie/{$alice['treasury']->id}"],
            ['delete', "/tresorerie/{$alice['treasury']->id}"],
            ['put', "/a-faire/{$alice['task']->id}"],
            ['delete', "/a-faire/{$alice['task']->id}"],
            ['put', "/categories/{$alice['category']->id}"],
            ['delete', "/categories/{$alice['category']->id}"],
            ['post', "/tresorerie/{$alice['treasury']->id}/mouvements"],
            ['delete', "/tresorerie/{$alice['treasury']->id}/mouvements/{$alice['movement']->id}"],
        ];

        foreach ($routes as [$verb, $url]) {
            $this->actingAs($this->bob)
                ->{$verb}($url, ['name' => 'pirate', 'amount' => 1])
                ->assertNotFound("{$verb} {$url} devrait être introuvable pour Bob.");
        }

        // Et rien n'a bougé.
        $this->assertDatabaseHas('expenses', ['id' => $alice['expense']->id, 'name' => "Courses de alice@test.be"]);
        $this->assertDatabaseHas('treasury_movements', ['id' => $alice['movement']->id, 'label' => 'Secret']);
    }

    public function test_on_ne_peut_pas_rattacher_une_ecriture_a_la_categorie_d_un_autre(): void
    {
        $alice = $this->dataFor($this->alice);

        // `exists:categories,id` seul aurait laissé passer.
        $this->actingAs($this->bob)
            ->post('/depenses', [
                'name' => 'Tentative',
                'amount' => 50,
                'category_id' => $alice['category']->id,
                'spent_on' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('expenses', ['name' => 'Tentative']);
    }

    public function test_deux_comptes_peuvent_avoir_une_categorie_du_meme_nom(): void
    {
        Category::create([
            'user_id' => $this->alice->id, 'name' => 'Alimentation',
            'type' => 'expense', 'color' => '#1baf7a',
        ]);

        $this->actingAs($this->bob)
            ->post('/categories', ['name' => 'Alimentation', 'type' => 'expense', 'color' => '#eb6834'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Category::forUser($this->bob)->where('name', 'Alimentation')->count());
    }

    public function test_le_dashboard_ne_compte_que_ses_propres_montants(): void
    {
        $this->dataFor($this->alice);   // 100 € de dépense, 2 000 € de rentrée
        $this->dataFor($this->bob);

        $this->actingAs($this->bob)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('report.totals.income_cents', 200000)   // et non 400000
                ->where('report.totals.expense_cents', 10000)   // et non 20000
                ->where('report.totals.treasury_cents', 50000)  // et non 100000
                ->where('report.totals.fixed_cents', 1500));    // et non 3000
    }

    public function test_une_ecriture_creee_appartient_a_son_auteur(): void
    {
        $this->actingAs($this->bob)->post('/depenses', [
            'name' => 'Sandwich',
            'amount' => 8,
            'spent_on' => now()->format('Y-m-d'),
        ])->assertRedirect();

        // Le user_id n'est jamais fourni par le formulaire : il est imposé.
        $this->assertDatabaseHas('expenses', [
            'name' => 'Sandwich',
            'user_id' => $this->bob->id,
        ]);
    }

    public function test_le_user_id_envoye_par_le_formulaire_est_ignore(): void
    {
        $this->actingAs($this->bob)->post('/depenses', [
            'name' => 'Cadeau empoisonné',
            'amount' => 8,
            'spent_on' => now()->format('Y-m-d'),
            'user_id' => $this->alice->id,   // tentative d'écrire chez Alice
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'name' => 'Cadeau empoisonné',
            'user_id' => $this->bob->id,
        ]);
        $this->assertSame(0, Expense::forUser($this->alice)->where('name', 'Cadeau empoisonné')->count());
    }

    public function test_supprimer_un_compte_emporte_ses_donnees_et_pas_celles_des_autres(): void
    {
        $this->dataFor($this->alice);
        $this->dataFor($this->bob);

        $this->alice->delete();

        $this->assertSame(0, Expense::forUser($this->alice)->count());
        $this->assertSame(1, Expense::forUser($this->bob)->count());
        $this->assertSame(1, TreasuryMovement::forUser($this->bob)->count());
    }
}
