<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. tick_id is null bij een bericht van Lucas (via de interface). Geen
     * gelezen-status: nieuw is wat binnenkwam sinds de vorige tick van de ontvanger.
     */
    public function up(): void
    {
        Schema::create('agent_emails', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('tick_id')->nullable()->constrained();
            $table->foreignId('from_agent_id')->constrained('agents');
            $table->foreignId('to_agent_id')->constrained('agents');
            $table->text('body');

            $table->index(['to_agent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_emails');
    }
};
