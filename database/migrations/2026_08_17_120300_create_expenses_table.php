<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
