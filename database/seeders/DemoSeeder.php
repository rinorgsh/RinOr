<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Treasury;
use App\Models\User;
use App\Models\TreasuryMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Dépenses et mouvements de caisse fictifs, uniquement pour voir le dashboard
 * peuplé. Rien ici n'est une vraie donnée.
 *
 *   php artisan db:seed --class=DemoSeeder    # remplir
 *   php artisan app:clear-demo                # tout retirer
 */
class DemoSeeder extends Seeder
{
    public const MARKER = '[démo]';

    public function run(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->command?->warn('Aucun compte : rien à semer.');

            return;
        }

        $month = CarbonImmutable::now()->startOfMonth();
        $categories = Category::forUser($user)->where('type', Category::TYPE_EXPENSE)
            ->pluck('id', 'name');

        // [jour du mois, libellé, montant, catégorie]
        $rows = [
            [2, 'Loyer', 780.00, 'Logement & Charges'],
            [3, 'Courses Colruyt', 82.40, 'Alimentation'],
            [5, 'Essence', 65.00, 'Transport'],
            [7, 'Resto', 54.00, 'Restaurant & Sorties'],
            [9, 'Courses Delhaize', 61.20, 'Alimentation'],
            [11, 'Écran 27"', 219.00, 'Divers'],
            [12, 'Abonnement STIB', 49.00, 'Transport'],
            [14, 'Courses Lidl', 44.75, 'Alimentation'],
            [15, 'Cinéma', 24.00, 'Loisirs'],
            [16, 'Pharmacie', 31.50, 'Divers'],
            [16, 'Brunch', 38.00, 'Restaurant & Sorties'],
        ];

        foreach ($rows as [$day, $name, $amount, $category]) {
            $date = $month->addDays($day - 1);

            if ($date->isFuture()) {
                continue;
            }

            Expense::create([
                'user_id' => $user->id,
                'name' => $name,
                'amount' => $amount,
                'category_id' => $categories[$category] ?? null,
                'spent_on' => $date->format('Y-m-d'),
                'notes' => self::MARKER,
            ]);
        }

        // Le mois précédent, pour que la courbe de tendance ait de quoi tracer.
        foreach ([1, 2, 3, 4, 5] as $back) {
            $past = $month->subMonths($back);

            Expense::create([
                'user_id' => $user->id,
                'name' => 'Dépenses du mois',
                'amount' => 900 + ($back * 63),
                'category_id' => $categories['Divers'] ?? null,
                'spent_on' => $past->addDays(14)->format('Y-m-d'),
                'notes' => self::MARKER,
            ]);
        }

        $this->treasuryMovements($month, $user->id);
    }

    private function treasuryMovements(CarbonImmutable $month, int $userId): void
    {
        $epargne = Treasury::forUser($userId)->where('name', 'Épargne')->first();
        $tva = Treasury::forUser($userId)->where('name', 'Réserve TVA')->first();

        if ($epargne) {
            $epargne->movements()->createMany([
                [
                    'user_id' => $userId,
                    'direction' => TreasuryMovement::IN,
                    'amount' => 500.00,
                    'label' => 'Prestation SVS RENOV',
                    'occurred_on' => $month->subMonths(2)->format('Y-m-d'),
                    'notes' => self::MARKER,
                ],
                [
                    'user_id' => $userId,
                    'direction' => TreasuryMovement::IN,
                    'amount' => 300.00,
                    'label' => 'Virement mensuel',
                    'occurred_on' => $month->format('Y-m-d'),
                    'notes' => self::MARKER,
                ],
                [
                    'user_id' => $userId,
                    'direction' => TreasuryMovement::OUT,
                    'amount' => 219.00,
                    'label' => 'Achat écran 27"',
                    'occurred_on' => $month->addDays(10)->format('Y-m-d'),
                    'notes' => self::MARKER,
                ],
            ]);
        }

        if ($tva) {
            $tva->movements()->create([
                'user_id' => $userId,
                'direction' => TreasuryMovement::IN,
                'amount' => 315.00,
                'label' => 'TVA sur facture SVS RENOV',
                'occurred_on' => $month->subMonths(2)->format('Y-m-d'),
                'notes' => self::MARKER,
            ]);
        }
    }
}
