<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Nullable: de ticks van vóór de rollen hebben er geen.
     */
    public function up(): void
    {
        Schema::table('ticks', function (Blueprint $table) {
            $table->string('role')->nullable()->after('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticks', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
