<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\TreasuryMovement;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class ClearDemo extends Command
{
    protected $signature = 'app:clear-demo';

    protected $description = 'Retire les données de démonstration (dépenses et mouvements marqués [démo])';

    public function handle(): int
    {
        $expenses = Expense::where('notes', DemoSeeder::MARKER)->delete();
        $movements = TreasuryMovement::where('notes', DemoSeeder::MARKER)->delete();

        $this->info("Supprimé : {$expenses} dépense(s), {$movements} mouvement(s) de caisse.");
        $this->line('Tes catégories, abonnements, rentrées, caisses et tâches sont intacts.');

        return self::SUCCESS;
    }
}
