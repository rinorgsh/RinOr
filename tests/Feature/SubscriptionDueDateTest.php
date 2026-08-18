<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `next_due_on` est une ancre que rien ne fait avancer en base. La prochaine
 * échéance est donc recalculée à la lecture — sinon tout passe « en retard »
 * le mois suivant et n'en ressort jamais.
 */
class SubscriptionDueDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function sub(string $cycle, string $anchor): Subscription
    {
        return Subscription::create([
            'name' => 'Test', 'amount' => 10, 'cycle' => $cycle, 'next_due_on' => $anchor,
        ]);
    }

    public function test_une_echeance_future_est_laissee_telle_quelle(): void
    {
        $s = $this->sub('monthly', '2026-09-01');

        $this->assertSame(
            '2026-09-01',
            $s->nextDueDate(CarbonImmutable::create(2026, 8, 18))->format('Y-m-d'),
        );
    }

    public function test_un_mensuel_depasse_avance_au_mois_suivant(): void
    {
        $s = $this->sub('monthly', '2026-09-01');

        // Le bug d'origine : au 15/09 il affichait « il y a 14 jours ».
        $this->assertSame(
            '2026-10-01',
            $s->nextDueDate(CarbonImmutable::create(2026, 9, 15))->format('Y-m-d'),
        );
    }

    public function test_le_jour_meme_de_l_echeance_compte_encore(): void
    {
        $s = $this->sub('monthly', '2026-09-01');

        $this->assertSame(
            '2026-09-01',
            $s->nextDueDate(CarbonImmutable::create(2026, 9, 1))->format('Y-m-d'),
        );
    }

    public function test_une_ancre_tres_ancienne_rattrape_en_une_fois(): void
    {
        $s = $this->sub('monthly', '2023-03-10');

        $this->assertSame(
            '2026-09-10',
            $s->nextDueDate(CarbonImmutable::create(2026, 8, 18))->format('Y-m-d'),
        );
    }

    public function test_un_annuel_avance_d_un_an_et_garde_son_mois(): void
    {
        $s = $this->sub('yearly', '2026-03-01');

        $next = $s->nextDueDate(CarbonImmutable::create(2026, 8, 18));

        $this->assertSame('2027-03-01', $next->format('Y-m-d'));
        // Le mois anniversaire ne bouge jamais : c'est lui qui décide dans quel
        // mois l'annuel pèse.
        $this->assertSame(3, $s->dueMonth());
    }

    public function test_le_31_ne_deborde_pas_sur_le_mois_suivant(): void
    {
        $s = $this->sub('monthly', '2026-01-31');

        // 31 janvier + 1 mois = 28 février, pas 3 mars.
        $this->assertSame(
            '2026-02-28',
            $s->nextDueDate(CarbonImmutable::create(2026, 2, 1))->format('Y-m-d'),
        );
    }

    public function test_un_abonnement_sans_echeance_reste_sans_echeance(): void
    {
        $this->assertNull($this->sub('monthly', '2026-09-01')->forceFill(['next_due_on' => null])->nextDueDate());
    }

    public function test_le_dashboard_n_affiche_jamais_d_echeance_passee(): void
    {
        $this->sub('monthly', '2020-01-15');
        $this->sub('yearly', '2019-06-20');

        $upcoming = $this->get('/')->inertiaProps()['report']['upcoming_subscriptions'];

        $this->assertCount(2, $upcoming);

        foreach ($upcoming as $item) {
            $this->assertGreaterThanOrEqual(
                0,
                $item['days_left'],
                "L'échéance {$item['next_due_on']} est dans le passé.",
            );
        }
    }

    public function test_les_prochains_preleves_sont_tries_sur_la_date_reelle(): void
    {
        // Ancre très ancienne mais échéance réelle proche.
        $this->sub('monthly', '2020-08-25');
        // Ancre future mais lointaine.
        $this->sub('yearly', '2027-01-10');

        $upcoming = $this->get('/')->inertiaProps()['report']['upcoming_subscriptions'];

        $dates = array_column($upcoming, 'next_due_on');
        $sorted = $dates;
        sort($sorted);

        $this->assertSame($sorted, $dates, 'Le tri doit suivre la date réelle, pas l\'ancre.');
    }
}
