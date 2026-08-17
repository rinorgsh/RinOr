<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connexion mono-utilisateur. Il n'y a volontairement aucune route
 * d'inscription : le compte se crée en ligne de commande
 * (`php artisan app:create-user`), donc une URL publique n'expose jamais de
 * formulaire d'inscription.
 */
class LoginController extends Controller
{
    /** Nombre d'échecs tolérés avant blocage temporaire. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            // Message identique quel que soit le champ fautif : on ne révèle
            // pas si l'adresse existe.
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // Empêche la fixation de session : nouvel identifiant après connexion.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Trop de tentatives. Réessaie dans {$seconds} secondes.",
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        );
    }
}
