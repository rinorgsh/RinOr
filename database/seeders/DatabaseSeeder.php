<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Amorce l'app avec tes vraies données, reprises de l'Excel : catégories,
 * abonnements (montants TTC, c'est ce qui quitte le compte), rentrées déjà
 * encaissées en 2026, caisses et tâches issues des anomalies repérées.
 *
 * Aucune dépense n'est créée ici : ce sont les seules données que l'Excel
 * n'avait pas. Pour voir le dashboard peuplé, lancer en plus :
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Toutes les données appartiennent à un utilisateur. Sans compte existant,
     * il n'y a rien à semer : ces données sont celles de Rinor, pas un jeu
     * générique qu'un nouvel inscrit devrait hériter.
     */
    private int $userId;

    public function run(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->command?->warn(
                'Aucun compte : rien à semer. Crée-le avec `php artisan app:create-user`, puis relance.'
            );

            return;
        }

        $this->userId = $user->id;

        $expense = $this->expenseCategories();
        $income = $this->incomeCategories();

        $this->subscriptions($expense);
        $this->incomes($income);
        $this->invoices();
        $this->treasuries();
        $this->tasks();
    }

    /** @return array<string, int> nom => id */
    private function expenseCategories(): array
    {
        $rows = [
            ['Logement & Charges', '#2a78d6'],
            ['Alimentation', '#1baf7a'],
            ['Restaurant & Sorties', '#eb6834'],
            ['Transport', '#eda100'],
            ['Télécom & Internet', '#4a3aa7'],
            ['Outils & Logiciels', '#e87ba4'],
            ['Infra Client', '#008300'],
            ['Loisirs', '#d55181'],
            ['Divers', '#898781'],
        ];

        return $this->upsert($rows, Category::TYPE_EXPENSE);
    }

    /** @return array<string, int> nom => id */
    private function incomeCategories(): array
    {
        $rows = [
            ['Prestation client', '#1baf7a'],
            ['Abonnement client', '#2a78d6'],
            ['Salaire', '#eda100'],
            ['Autre', '#898781'],
        ];

        return $this->upsert($rows, Category::TYPE_INCOME);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     * @return array<string, int>
     */
    private function upsert(array $rows, string $type): array
    {
        $map = [];

        foreach ($rows as [$name, $color]) {
            $map[$name] = Category::firstOrCreate(
                ['user_id' => $this->userId, 'name' => $name, 'type' => $type],
                ['color' => $color],
            )->id;
        }

        return $map;
    }

    /** @param  array<string, int>  $cat */
    private function subscriptions(array $cat): void
    {
        // [nom, montant TTC, cycle, catégorie, prochaine échéance, notes]
        $rows = [
            // --- Perso ---
            ['Apple iCloud', 1.20, 'monthly', 'Outils & Logiciels', '2026-09-01', null],
            ['Google One / Workspace', 19.99, 'yearly', 'Outils & Logiciels', '2027-03-01', null],
            ['Telenet', 31.00, 'monthly', 'Télécom & Internet', '2026-09-01', 'À renégocier'],
            ['Base', 36.00, 'monthly', 'Télécom & Internet', '2026-09-01', 'À renégocier'],
            ['Spotify', 6.99, 'monthly', 'Loisirs', '2026-09-01', null],

            // --- Outils pro (mutualisés sur tous les clients) ---
            ['Claude Code', 108.90, 'monthly', 'Outils & Logiciels', '2026-09-01', 'Plus gros poste mensuel'],
            ['Laravel Forge', 99.14, 'yearly', 'Outils & Logiciels', '2027-03-01', 'Mutualisé'],
            ['Digital Ocean', 5.50, 'monthly', 'Outils & Logiciels', '2026-09-01', 'Mutualisé'],
            ['Amazon AWS', 3.99, 'monthly', 'Outils & Logiciels', '2026-09-01', 'Mutualisé'],

            // --- Infra client ---
            ['Antika — domaine OVH', 2.41, 'yearly', 'Infra Client', '2027-04-07', null],
            ['Antika — VPS OVH', 5.43, 'monthly', 'Infra Client', '2026-09-20', 'Non refacturé'],
            ['Renowall — domaine OVH', 9.55, 'yearly', 'Infra Client', '2027-03-01', null],
            ['Renowall — site web (VPS)', 46.46, 'yearly', 'Infra Client', '2027-06-19', 'Refacturé à prix coûtant'],
            ['Bella Ischia — domaine OVH', 2.41, 'yearly', 'Infra Client', '2027-04-29', null],
            ['Bella Ischia — VPS OVH', 7.38, 'monthly', 'Infra Client', '2026-09-01', 'Non refacturé'],
            ['Forestoise — domaine OVH', 2.41, 'yearly', 'Infra Client', '2026-10-22', null],
            ['Forestoise — VPS OVH', 9.18, 'monthly', 'Infra Client', '2026-09-22', 'Non refacturé'],
            ['DuoGroep — domaine OVH', 8.46, 'yearly', 'Infra Client', '2027-02-01', null],

            // --- Projet perso ---
            ['PRETIX — domaine', 3.62, 'yearly', 'Outils & Logiciels', '2027-03-01', null],
            ['PRETIX — VPS Hetzner', 9.14, 'monthly', 'Outils & Logiciels', '2026-09-01', null],
        ];

        foreach ($rows as [$name, $amount, $cycle, $category, $due, $notes]) {
            Subscription::firstOrCreate(
                ['user_id' => $this->userId, 'name' => $name],
                [
                    'amount' => $amount,
                    'cycle' => $cycle,
                    'category_id' => $cat[$category] ?? null,
                    'next_due_on' => $due,
                    'is_active' => true,
                    'notes' => $notes,
                ],
            );
        }
    }

    /** @param  array<string, int>  $cat */
    private function incomes(array $cat): void
    {
        // Uniquement l'argent réellement encaissé. La vente DuoGroep encore
        // en attente est une tâche de relance, pas une rentrée.
        $rows = [
            ['SVS RENOV — création site web', 1815.00, 'Prestation client', '2026-01-10'],
            ['ElectroCare — création site web', 484.00, 'Prestation client', '2026-01-15'],
            ['Renowall — app web (annuel)', 249.99, 'Abonnement client', '2026-02-01'],
            ['Renowall — site web (annuel)', 46.46, 'Abonnement client', '2026-06-19'],
        ];

        foreach ($rows as [$name, $amount, $category, $date]) {
            Income::firstOrCreate(
                ['user_id' => $this->userId, 'name' => $name, 'received_on' => $date],
                ['amount' => $amount, 'category_id' => $cat[$category] ?? null],
            );
        }
    }

    /**
     * Les factures déjà connues. DuoGroep est l'angle mort d'origine : 1 815 €
     * envoyés, jamais encaissés, qui n'existaient nulle part dans l'app.
     */
    private function invoices(): void
    {
        $rows = [
            // [numéro, client, prestation, HT, TVA, statut, émise, échéance, payée]
            ['2026-003', 'DuoGroep', 'Création site web', 1500.00, 21, Invoice::SENT, '2026-03-01', '2026-03-31', null],
            ['2026-001', 'SVS RENOV', 'Création site web', 1500.00, 21, Invoice::PAID, '2026-01-01', '2026-01-31', '2026-01-10'],
            ['2026-002', 'ElectroCare', 'Création site web', 400.00, 21, Invoice::PAID, '2026-01-01', '2026-01-31', '2026-01-15'],
        ];

        foreach ($rows as [$number, $client, $label, $amount, $vat, $status, $issued, $due, $paid]) {
            Invoice::firstOrCreate(
                ['user_id' => $this->userId, 'number' => $number],
                [
                    'client' => $client,
                    'label' => $label,
                    'amount' => $amount,
                    'vat_rate' => $vat,
                    'status' => $status,
                    'issued_on' => $issued,
                    'due_on' => $due,
                    'paid_on' => $paid,
                ],
            );
        }
    }

    private function treasuries(): void
    {
        $rows = [
            ['Épargne', 'Argent mis de côté, sans affectation précise.', '#1baf7a', null],
            ['Réserve TVA', 'La TVA encaissée n\'est pas ton argent : mets-la ici dès la facture payée.', '#eb6834', null],
        ];

        foreach ($rows as [$name, $description, $color, $target]) {
            Treasury::firstOrCreate(
                ['user_id' => $this->userId, 'name' => $name],
                ['description' => $description, 'color' => $color, 'target_cents' => $target],
            );
        }
    }

    private function tasks(): void
    {
        // Tâches issues des anomalies repérées dans l'Excel.
        $rows = [
            ['Relancer DuoGroep pour la facture de 1 815 €', 'high', '2026-08-22'],
            ['Définir le prix de vente pour Bee Esthetic', 'high', null],
            ['Trancher ElectroCare : facturer le renouvellement ou couper', 'high', null],
            ['Refacturer les VPS Antika / Forestoise / Bella Ischia (≈ 215 €/an absorbés)', 'normal', null],
            ['Renégocier Telenet + Base (≈ 804 €/an)', 'normal', null],
            ['Vérifier le palier Claude Code par rapport à l\'usage réel', 'low', null],
        ];

        foreach ($rows as $i => [$title, $priority, $due]) {
            Task::firstOrCreate(
                ['user_id' => $this->userId, 'title' => $title],
                [
                    'status' => Task::TODO,
                    'priority' => $priority,
                    'due_on' => $due,
                    'position' => $i,
                ],
            );
        }
    }
}
