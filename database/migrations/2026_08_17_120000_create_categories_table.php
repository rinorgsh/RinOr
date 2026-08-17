<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 'income'  -> utilisable par les rentrées
            // 'expense' -> utilisable par les dépenses ET les abonnements
            $table->string('type');
            $table->string('color', 7)->default('#898781');
            $table->timestamps();

            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
