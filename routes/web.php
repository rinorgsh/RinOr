<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Connexion
|--------------------------------------------------------------------------
| Aucune route d'inscription : le compte se crée avec
| `php artisan app:create-user`.
*/

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store']);
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| L'application
|--------------------------------------------------------------------------
| Tout est derrière `auth` : il n'existe aucune page publique en dehors du
| formulaire de connexion.
*/

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::controller(SubscriptionController::class)
        ->prefix('abonnements')->name('subscriptions.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{subscription}', 'update')->name('update');
            Route::delete('/{subscription}', 'destroy')->name('destroy');
        });

    Route::controller(IncomeController::class)
        ->prefix('rentrees')->name('incomes.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{income}', 'update')->name('update');
            Route::delete('/{income}', 'destroy')->name('destroy');
        });

    Route::controller(ExpenseController::class)
        ->prefix('depenses')->name('expenses.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{expense}', 'update')->name('update');
            Route::delete('/{expense}', 'destroy')->name('destroy');
        });

    Route::controller(TreasuryController::class)
        ->prefix('tresorerie')->name('treasuries.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{treasury}', 'update')->name('update');
            Route::delete('/{treasury}', 'destroy')->name('destroy');

            Route::post('/{treasury}/mouvements', 'storeMovement')->name('movements.store');
            Route::delete('/{treasury}/mouvements/{movement}', 'destroyMovement')
                ->name('movements.destroy');
        });

    Route::controller(TaskController::class)
        ->prefix('a-faire')->name('tasks.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{task}', 'update')->name('update');
            Route::delete('/{task}', 'destroy')->name('destroy');
        });

    Route::controller(CategoryController::class)
        ->prefix('categories')->name('categories.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{category}', 'update')->name('update');
            Route::delete('/{category}', 'destroy')->name('destroy');
        });
});
