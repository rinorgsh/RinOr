<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('received_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
