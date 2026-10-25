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
        Schema::create('source_notes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 120);
            $table->string('kind');

            $table->index(['topic', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_notes');
    }
};
