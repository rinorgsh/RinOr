<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cloisonnement par utilisateur.
 *
 * `treasury_movements` reçoit aussi un `user_id` alors qu'il pourrait l'hériter
 * de sa caisse : c'est une dénormalisation assumée. Une requête directe sur les
 * mouvements — dans un futur export, un rapport, une commande — ne doit jamais
 * pouvoir échapper au cloisonnement parce que quelqu'un a oublié de passer par
 * la relation. Sur des données financières d'autrui, la redondance est moins
 * chère qu'une fuite.
 */
return new class extends Migration
{
    /** L'ordre suit les dépendances : les propriétaires avant les dépendants. */
    private const TABLES = [
        'categories',
        'subscriptions',
        'incomes',
        'expenses',
        'treasuries',
        'treasury_movements',
        'tasks',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('user_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
            });
        }

        // Reprise des données existantes : s'il y a déjà un compte, tout lui
        // appartient. Sinon les lignes restent orphelines et invisibles — d'où
        // la commande `app:claim-data`.
        $owner = DB::table('users')->orderBy('id')->value('id');

        if ($owner !== null) {
            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $owner]);
            }
        }

        // L'unicité d'une catégorie devient relative à son propriétaire :
        // deux utilisateurs peuvent tous deux avoir « Alimentation ».
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique('categories_name_type_unique');
            $t->unique(['user_id', 'name', 'type']);
        });

        foreach (['incomes' => 'received_on', 'expenses' => 'spent_on'] as $table => $column) {
            Schema::table($table, fn (Blueprint $t) => $t->index(['user_id', $column]));
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'name', 'type']);
            $t->unique(['name', 'type']);
        });

        foreach (['incomes' => 'received_on', 'expenses' => 'spent_on'] as $table => $column) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex([$table.'_user_id_'.$column.'_index']));
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('user_id');
            });
        }
    }
};
