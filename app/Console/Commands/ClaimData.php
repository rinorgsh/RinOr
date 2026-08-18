<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Treasury;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les lignes orphelines (`user_id` null) à un compte.
 *
 * Nécessaire une seule fois, si la migration de cloisonnement a tourné avant
 * qu'un compte existe : sans propriétaire, les données sont invisibles partout.
 *
 * Les catégories demandent un traitement à part. Un compte fraîchement créé
 * possède déjà ses catégories par défaut ; les orphelines portent souvent les
 * mêmes noms, et l'unicité (user_id, name, type) refuserait le rattachement.
 * On fusionne donc : les écritures de l'orpheline sont repointées vers la
 * catégorie existante, puis l'orpheline disparaît. Rien n'est perdu, rien n'est
 * dupliqué.
 */
class ClaimData extends Command
{
    protected $signature = 'app:claim-data {email : Le compte qui récupère les données orphelines}';

    protected $description = 'Attribue les données sans propriétaire à un compte';

    /** Les catégories sont traitées avant, séparément. */
    private const SIMPLE_MODELS = [
        Subscription::class, Income::class, Expense::class,
        Treasury::class, TreasuryMovement::class, Task::class,
    ];

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("Aucun compte pour {$this->argument('email')}.");

            return self::FAILURE;
        }

        $total = 0;

        DB::transaction(function () use ($user, &$total) {
            $total += $this->claimCategories($user);

            foreach (self::SIMPLE_MODELS as $model) {
                $count = $model::withoutGlobalScope('owner')
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);

                if ($count > 0) {
                    $this->line(sprintf('  %-20s %d ligne(s)', class_basename($model), $count));
                }

                $total += $count;
            }
        });

        $this->newLine();
        $this->info($total > 0
            ? "{$total} ligne(s) rattachée(s) à {$user->email}."
            : 'Aucune donnée orpheline.');

        return self::SUCCESS;
    }

    private function claimCategories(User $user): int
    {
        $orphans = Category::withoutGlobalScope('owner')->whereNull('user_id')->get();

        if ($orphans->isEmpty()) {
            return 0;
        }

        // Les catégories déjà possédées, indexées sur (nom, type).
        $existing = Category::forUser($user)->get()
            ->keyBy(fn (Category $c) => $c->name.'|'.$c->type);

        $claimed = 0;
        $merged = 0;

        foreach ($orphans as $orphan) {
            $twin = $existing->get($orphan->name.'|'.$orphan->type);

            if ($twin === null) {
                $orphan->user_id = $user->id;
                $orphan->save();
                $existing->put($orphan->name.'|'.$orphan->type, $orphan);
                $claimed++;

                continue;
            }

            // Doublon : on repointe tout ce qui s'y rattache, puis on supprime.
            foreach ([Subscription::class, Income::class, Expense::class] as $model) {
                $model::withoutGlobalScope('owner')
                    ->where('category_id', $orphan->id)
                    ->update(['category_id' => $twin->id]);
            }

            $orphan->delete();
            $merged++;
        }

        if ($claimed > 0) {
            $this->line(sprintf('  %-20s %d ligne(s)', 'Category', $claimed));
        }

        if ($merged > 0) {
            $this->line(sprintf('  %-20s %d fusionnée(s) avec les catégories existantes', '', $merged));
        }

        return $claimed;
    }
}
