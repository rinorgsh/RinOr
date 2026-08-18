<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Rinor',
            'email' => 'rinor@example.test',
            'password' => Hash::make('un-mot-de-passe-solide'),
        ]);
    }

    public function test_toutes_les_pages_exigent_une_connexion(): void
    {
        foreach (['/', '/depenses', '/rentrees', '/abonnements', '/tresorerie', '/a-faire', '/categories'] as $url) {
            $this->get($url)->assertRedirect('/connexion');
        }
    }

    public function test_les_ecritures_exigent_une_connexion(): void
    {
        $this->post('/depenses', ['name' => 'X', 'amount' => 10, 'spent_on' => '2026-08-17'])
            ->assertRedirect('/connexion');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_connexion_avec_les_bons_identifiants(): void
    {
        $user = $this->user();

        $this->post('/connexion', [
            'email' => $user->email,
            'password' => 'un-mot-de-passe-solide',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_connexion_refusee_avec_un_mauvais_mot_de_passe(): void
    {
        $user = $this->user();

        $this->post('/connexion', ['email' => $user->email, 'password' => 'faux'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_le_message_d_erreur_ne_revele_pas_si_l_adresse_existe(): void
    {
        $user = $this->user();

        $existant = $this->post('/connexion', ['email' => $user->email, 'password' => 'faux'])
            ->assertSessionHasErrors('email');

        $this->flushSession();
        RateLimiter::clear(strtolower('inconnu@example.test').'|127.0.0.1');

        $inconnu = $this->post('/connexion', ['email' => 'inconnu@example.test', 'password' => 'faux'])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            $existant->getSession()->get('errors')->first('email'),
            $inconnu->getSession()->get('errors')->first('email'),
            'Le message doit être identique, sinon il révèle quels comptes existent.',
        );
    }

    public function test_les_tentatives_sont_limitees(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $user->email, 'password' => 'faux']);
        }

        $response = $this->post('/connexion', [
            'email' => $user->email,
            'password' => 'un-mot-de-passe-solide', // le bon, mais trop tard
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Trop de tentatives',
            $response->getSession()->get('errors')->first('email'),
        );
    }

    public function test_un_utilisateur_connecte_accede_au_dashboard(): void
    {
        $this->actingAs($this->user())->get('/')->assertOk();
    }

    public function test_deconnexion(): void
    {
        $this->actingAs($this->user())->post('/deconnexion')->assertRedirect('/connexion');

        $this->assertGuest();
    }

    public function test_la_page_de_connexion_est_publique(): void
    {
        $this->get('/connexion')->assertOk();
    }
}
