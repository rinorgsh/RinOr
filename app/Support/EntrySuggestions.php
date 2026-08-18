<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Les libellés déjà saisis, avec le dernier montant et la dernière catégorie
 * utilisés pour chacun.
 *
 * C'est le cœur de la saisie rapide : taper « Cour… », choisir « Courses
 * Colruyt », et voir le montant et la catégorie se remplir seuls. Sans ça,
 * noter une dépense demande quatre champs à chaque fois — et une app de compta
 * qu'on trouve pénible est une app qu'on abandonne au bout de trois semaines.
 *
 * Classé par fréquence puis par récence : ce que tu achètes souvent remonte,
 * et à fréquence égale le plus récent gagne.
 */
class EntrySuggestions
{
    public static function for(string $table, string $dateColumn, int $userId, int $limit = 40): array
    {
        // La sous-requête retient l'écriture la plus récente de chaque libellé ;
        // la requête externe y ajoute le nombre d'usages.
        $latest = DB::table($table)
            ->select('name', DB::raw('MAX(id) as last_id'), DB::raw('COUNT(*) as uses'))
            ->where('user_id', $userId)
            ->groupBy('name');

        return DB::table($table.' as e')
            ->joinSub($latest, 'l', fn ($join) => $join->on('e.id', '=', 'l.last_id'))
            ->orderByDesc('l.uses')
            ->orderByDesc('e.'.$dateColumn)
            ->limit($limit)
            ->get(['e.name', 'e.amount_cents', 'e.category_id', 'l.uses'])
            ->map(fn ($row) => [
                'name' => $row->name,
                'amount_cents' => (int) $row->amount_cents,
                'category_id' => $row->category_id,
                'uses' => (int) $row->uses,
            ])->all();
    }
}
