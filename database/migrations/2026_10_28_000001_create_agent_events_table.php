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
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('agent_id')->constrained();
            $table->foreignId('tick_id')->nullable()->constrained();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamp('happened_at');

            $table->index(['agent_id', 'label', 'happened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
