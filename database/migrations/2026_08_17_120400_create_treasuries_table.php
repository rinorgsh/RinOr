<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasuries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#898781');
            $table->unsignedBigInteger('target_cents')->nullable(); // objectif optionnel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasuries');
    }
};
