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
        Schema::create('dispatch_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('dispatch_id')->constrained();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamp('happened_at');

            $table->index(['dispatch_id', 'happened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_events');
    }
};
