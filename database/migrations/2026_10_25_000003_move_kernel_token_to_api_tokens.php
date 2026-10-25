<?php

use App\Models\ApiToken;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Het bestaande kernel-token (hash uit JUNO_API_TOKEN_HASH) wordt een rij in api_tokens, met alle
     * rechten. Het token zelf in /home/juno/.kernel-token verandert niet.
     */
    public function up(): void
    {
        $hash = env('JUNO_API_TOKEN_HASH');

        if (! $hash) {
            return;
        }

        ApiToken::register('kernel', $hash, array_keys(ApiToken::ABILITIES), 'Overgenomen uit JUNO_API_TOKEN_HASH.');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ApiToken::where('name', 'kernel')->delete();
    }
};
