<?php

namespace App\Support;

use App\Models\Category;
use App\Models\User;

/**
 * Catégories de départ d'un nouveau compte.
 *
 * Volontairement génériques : un nouvel inscrit ne doit hériter d'aucune donnée
 * d'un autre utilisateur. Les teintes viennent des huit slots catégoriels
 * validés (bandes de luminosité, plancher de chroma, séparation daltonienne).
 */
class DefaultCategories
{
    private const EXPENSE = [
        ['Logement & Charges', '#2a78d6'],
        ['Alimentation', '#1baf7a'],
        ['Restaurant & Sorties', '#eb6834'],
        ['Transport', '#eda100'],
        ['Télécom & Internet', '#4a3aa7'],
        ['Outils & Logiciels', '#e87ba4'],
        ['Santé', '#e34948'],
        ['Loisirs', '#d55181'],
        ['Divers', '#898781'],
    ];

    private const INCOME = [
        ['Salaire', '#eda100'],
        ['Prestation client', '#1baf7a'],
        ['Abonnement client', '#2a78d6'],
        ['Remboursement', '#4a3aa7'],
        ['Autre', '#898781'],
    ];

    public static function createFor(User $user): int
    {
        $created = 0;

        foreach ([Category::TYPE_EXPENSE => self::EXPENSE, Category::TYPE_INCOME => self::INCOME] as $type => $rows) {
            foreach ($rows as [$name, $color]) {
                $category = Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $name, 'type' => $type],
                    ['color' => $color],
                );

                if ($category->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }
}
