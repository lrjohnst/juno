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
        Schema::create('juno_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamp('happened_at');

            $table->index(['label', 'happened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juno_events');
    }
};
