<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // Message de confirmation affiché en toast après une écriture.
            'flash' => fn () => $request->session()->get('flash'),

            'auth' => [
                'user' => $request->user()
                    ? ['name' => $request->user()->name, 'email' => $request->user()->email]
                    : null,
            ],

            // Les URLs sont partagées ici plutôt que codées en dur dans les
            // composants Vue : Ziggy n'est pas installé.
            'nav' => [
                'dashboard' => route('dashboard'),
                'subscriptions' => route('subscriptions.index'),
                'incomes' => route('incomes.index'),
                'invoices' => route('invoices.index'),
                'expenses' => route('expenses.index'),
                'treasuries' => route('treasuries.index'),
                'tasks' => route('tasks.index'),
                'categories' => route('categories.index'),
                'logout' => route('logout'),
            ],
        ];
    }
}
