<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Support\EntrySuggestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->category = Category::create([
            'name' => 'Alimentation', 'type' => 'expense', 'color' => '#1baf7a',
        ]);
    }

    private function expense(string $name, float $amount, string $date, ?int $categoryId = null): Expense
    {
        return Expense::create([
            'name' => $name, 'amount' => $amount,
            'category_id' => $categoryId, 'spent_on' => $date,
        ]);
    }

    public function test_la_suggestion_porte_le_dernier_montant_et_la_derniere_categorie(): void
    {
        $this->expense('Courses Colruyt', 60, '2026-08-01', $this->category->id);
        $this->expense('Courses Colruyt', 82.40, '2026-08-10', $this->category->id);

        $s = collect(EntrySuggestions::for('expenses', 'spent_on', $this->user->id))
            ->firstWhere('name', 'Courses Colruyt');

        // Le dernier montant, pas le premier ni une moyenne.
        $this->assertSame(8240, $s['amount_cents']);
        $this->assertSame($this->category->id, $s['category_id']);
        $this->assertSame(2, $s['uses']);
    }

    public function test_les_suggestions_sont_classees_par_frequence(): void
    {
        $this->expense('Rare', 10, '2026-08-15');
        foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $d) {
            $this->expense('Fréquent', 20, $d);
        }

        $names = array_column(EntrySuggestions::for('expenses', 'spent_on', $this->user->id), 'name');

        $this->assertSame('Fréquent', $names[0]);
    }

    public function test_chaque_libelle_n_apparait_qu_une_fois(): void
    {
        foreach (range(1, 5) as $i) {
            $this->expense('Café', 3, sprintf('2026-08-%02d', $i));
        }

        $names = array_column(EntrySuggestions::for('expenses', 'spent_on', $this->user->id), 'name');

        $this->assertSame(['Café'], $names);
    }

    public function test_les_suggestions_ne_traversent_pas_les_comptes(): void
    {
        $this->expense('Secret de Rinor', 100, '2026-08-01');

        $bob = User::factory()->create();
        $names = array_column(EntrySuggestions::for('expenses', 'spent_on', $bob->id), 'name');

        $this->assertSame([], $names);
    }

    public function test_la_page_depenses_expose_les_suggestions(): void
    {
        $this->expense('Courses Colruyt', 82.40, '2026-08-10', $this->category->id);

        $this->get('/depenses')->assertInertia(fn ($page) => $page
            ->has('suggestions', 1)
            ->where('suggestions.0.name', 'Courses Colruyt')
            ->where('suggestions.0.amount_cents', 8240));
    }

    public function test_la_page_rentrees_expose_ses_propres_suggestions(): void
    {
        $this->expense('Une dépense', 50, '2026-08-01');

        // Les rentrées ne doivent pas proposer des libellés de dépenses.
        $this->get('/rentrees')->assertInertia(fn ($page) => $page->has('suggestions', 0));
    }
}
