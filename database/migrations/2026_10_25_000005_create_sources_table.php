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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('tick_id')->nullable()->constrained()->nullOnDelete();
            $table->json('authors');
            $table->string('title', 500);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('doi')->nullable()->unique();
            $table->string('isbn', 20)->nullable()->unique();
            $table->string('url', 1000)->nullable();
            $table->string('url_normalized', 191)->nullable()->unique();
            $table->text('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
