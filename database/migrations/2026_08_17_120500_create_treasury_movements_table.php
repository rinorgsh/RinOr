<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treasury_id')->constrained()->cascadeOnDelete();
            $table->string('direction');        // 'in' | 'out'
            $table->unsignedBigInteger('amount_cents');
            // 'in'  -> provenance de l'argent
            // 'out' -> motif de la sortie
            $table->string('label');
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['treasury_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_movements');
    }
};
