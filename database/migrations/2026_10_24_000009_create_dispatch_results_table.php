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
        Schema::create('dispatch_results', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('dispatch_id')->unique()->constrained();
            $table->uuid('session_id')->nullable();
            $table->longText('output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('num_turns')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_results');
    }
};
