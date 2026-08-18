<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Cloisonne un modèle par utilisateur.
 *
 * Deux garanties, et il faut les deux :
 *
 *  1. Un scope global filtre TOUTE requête sur l'utilisateur connecté. C'est
 *     une liste blanche : on n'ajoute pas `where('user_id', ...)` dans chaque
 *     contrôleur, parce qu'il suffit de l'oublier une fois pour exposer les
 *     finances de quelqu'un d'autre.
 *
 *  2. `user_id` est rempli automatiquement à la création. Un contrôleur ne peut
 *     donc pas créer une ligne orpheline, ni en attribuer une à autrui.
 *
 * Hors requête HTTP (seeders, commandes, tests), il n'y a pas d'utilisateur
 * connecté : le scope ne s'applique pas. C'est volontaire et sans danger, parce
 * que toutes les routes web sont derrière `auth` — une requête HTTP a donc
 * toujours un utilisateur. Pour cibler un utilisateur en console, on passe par
 * `forUser()`.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            if (Auth::hasUser() || Auth::check()) {
                $query->where($query->getModel()->getTable().'.user_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if ($model->user_id !== null) {
                return;
            }

            if (! Auth::check()) {
                throw new RuntimeException(
                    static::class.' créé sans utilisateur. En console, renseigne '
                    .'explicitement user_id (ou utilise Model::forUser($id)).'
                );
            }

            $model->user_id = Auth::id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Requête cadrée sur un utilisateur précis, indépendamment de la session.
     * Réservé à la console et aux tests.
     */
    public static function forUser(User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return static::withoutGlobalScope('owner')->where('user_id', $id);
    }
}
