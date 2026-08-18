<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les factures : l'argent qu'on te doit.
 *
 * Distinct des rentrées, qui n'enregistrent que l'argent reçu. C'est
 * précisément l'angle mort : 1 815 € dormaient chez DuoGroep sans exister
 * nulle part dans l'app, sinon comme une tâche.
 *
 * Le montant est stocké HT, avec son taux de TVA : c'est ainsi qu'une facture
 * s'écrit, et ça permet de savoir plus tard quelle part n'est pas à toi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('number')->nullable();
            $table->string('client');
            $table->string('label');

            $table->unsignedBigInteger('amount_cents');        // HT
            $table->unsignedTinyInteger('vat_rate')->default(21);

            $table->string('status')->default('sent');         // draft|sent|paid|cancelled
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('paid_on')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_on']);
        });

        // Une facture encaissée crée sa rentrée : marquer « payée » ne doit pas
        // obliger à ressaisir le montant ailleurs. Le lien permet aussi de
        // retirer la rentrée si on annule le paiement.
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('category_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', fn (Blueprint $t) => $t->dropConstrainedForeignId('invoice_id'));
        Schema::dropIfExists('invoices');
    }
};
