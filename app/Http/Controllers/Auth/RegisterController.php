<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription ouverte, compte actif immédiatement — pas de vérification par
 * e-mail.
 *
 * Ce choix a une conséquence : l'adresse n'est jamais prouvée. Un compte peut
 * donc être créé avec l'adresse de quelqu'un d'autre, et une future
 * réinitialisation de mot de passe par e-mail serait à cadrer. La limitation
 * de débit sur la route est la seule barrière contre la création en masse.
 */
class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            // Même exigence qu'en ligne de commande : 12 caractères. Sur une app
            // qui héberge des finances, le mot de passe est toute la barrière.
            'password' => ['required', 'confirmed', Password::min(12)],
        ], [
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        // Compte et catégories de départ dans la même transaction : pas de
        // compte à moitié créé si la seconde étape échoue.
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            DefaultCategories::createFor($user);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('flash', 'Bienvenue. Tes catégories de départ sont prêtes.');
    }
}
