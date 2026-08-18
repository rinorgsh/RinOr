<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice',
            'email' => 'alice@test.be',
            'password' => 'un-mot-de-passe-solide',
            'password_confirmation' => 'un-mot-de-passe-solide',
        ], $overrides);
    }

    public function test_la_page_d_inscription_est_publique(): void
    {
        $this->get('/inscription')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_un_compte_est_cree_et_actif_immediatement(): void
    {
        $this->post('/inscription', $this->payload())->assertRedirect('/');

        $user = User::where('email', 'alice@test.be')->firstOrFail();

        // Pas de vérification par e-mail : on est connecté dans la foulée.
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('un-mot-de-passe-solide', $user->password));
    }

    public function test_le_nouveau_compte_recoit_ses_categories_de_depart(): void
    {
        $this->post('/inscription', $this->payload());

        $user = User::where('email', 'alice@test.be')->firstOrFail();

        $this->assertSame(14, Category::forUser($user)->count());
    }

    public function test_le_nouveau_compte_n_herite_d_aucune_donnee_d_un_autre(): void
    {
        $rinor = User::factory()->create();
        Expense::create([
            'user_id' => $rinor->id, 'name' => 'Courses de Rinor',
            'amount' => 100, 'spent_on' => now()->format('Y-m-d'),
        ]);

        $this->post('/inscription', $this->payload());
        $alice = User::where('email', 'alice@test.be')->firstOrFail();

        $this->assertSame(0, Expense::forUser($alice)->count());

        $content = $this->actingAs($alice)->get('/depenses')->getContent();
        $this->assertStringNotContainsString('Courses de Rinor', $content);
    }

    public function test_l_adresse_doit_etre_unique(): void
    {
        User::factory()->create(['email' => 'alice@test.be']);

        $this->post('/inscription', $this->payload())->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'alice@test.be')->count());
    }

    public function test_le_mot_de_passe_fait_au_moins_douze_caracteres(): void
    {
        $this->post('/inscription', $this->payload([
            'password' => 'court',
            'password_confirmation' => 'court',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_les_deux_mots_de_passe_doivent_correspondre(): void
    {
        $this->post('/inscription', $this->payload([
            'password_confirmation' => 'autre-mot-de-passe',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_un_echec_ne_laisse_pas_de_compte_a_moitie_cree(): void
    {
        $this->post('/inscription', $this->payload(['email' => 'pas-une-adresse']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_la_creation_de_comptes_en_masse_est_limitee(): void
    {
        // Sans vérification par e-mail, c'est la seule barrière : 5 par heure.
        // On se déconnecte entre chaque inscription, sinon le middleware
        // `guest` redirige avant même d'atteindre la limitation.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/inscription', $this->payload([
                'email' => "alice{$i}@test.be",
            ]));

            // Vider la session ne suffit pas : le guard garde l'utilisateur
            // résolu en mémoire d'une requête à l'autre dans un même test.
            $this->flushSession();
            $this->app['auth']->forgetGuards();
        }

        $this->post('/inscription', $this->payload(['email' => 'alice6@test.be']))
            ->assertStatus(429);

        $this->assertSame(5, User::count());
    }

    public function test_un_utilisateur_connecte_ne_voit_pas_l_inscription(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/inscription')
            ->assertRedirect();
    }
}
