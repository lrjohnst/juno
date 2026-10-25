<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_token_abilities', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('api_token_id')->constrained()->cascadeOnDelete();
            $table->string('ability');

            $table->unique(['api_token_id', 'ability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_token_abilities');
    }
};
