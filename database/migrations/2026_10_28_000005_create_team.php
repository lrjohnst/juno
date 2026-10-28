<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEAM = [
        ['Onderzoeker', 'Agent', 'Abstraction', 'role_abstraction.md', 'Werkt het theoretisch kader uit: wat er aan kennis bestaat, wat empirisch sterk onderbouwd is en wat nog hypothetisch. Verankert het handelen van het team in nieuwe, relevante en verifieerbare kennis.'],
        ['Experimenteerder', 'Agent', 'Experimentation', 'role_experimentation.md', 'Vertaalt theorie naar een hypothese en een experimentontwerp, en bouwt de code: strategie, backtest, aanpassingen aan het handelsscript.'],
        ['Doener', 'Agent', 'Doing', 'role_doing.md', 'Voert het experiment uit en neemt waar: backtests draaien, kijken of de handelsfaciliteit gezond draait, ruwe resultaten verzamelen. Verandert zelf niets aan de strategie.'],
        ['Reflecteerder', 'Agent', 'Reflection', 'role_reflection.md', 'Evalueert met harde maatstaven (tegen random) en zachte. Rapporteert, en beheert de roadmap.'],
        ['Lucas', 'Human', null, null, 'Eigenaar van Juno. Leest niet alles wat hij krijgt: schrijf hem alleen als je hem echt nodig hebt.'],
    ];

    /**
     * Run the migrations. Het team, met per agent een rolprompt (een document dat een rollback niet volgt)
     * en de documenten die de kernel klaarzet.
     */
    public function up(): void
    {
        $now = now();
        $documentId = function (string $name, bool $followsRollback) use ($now): int {
            return DB::table('documents')->where('name', $name)->value('id')
                ?? DB::table('documents')->insertGetId(['name' => $name, 'follows_rollback' => $followsRollback, 'created_at' => $now, 'updated_at' => $now]);
        };

        $shared = array_filter([
            $documentId('facilities.md', false),
            DB::table('documents')->where('name', 'mission.md')->value('id'),
            DB::table('documents')->where('name', 'general_backlog.md')->value('id'),
        ]);
        $extra = ['Abstraction' => array_filter([DB::table('documents')->where('name', 'theoretical_framework_backlog.md')->value('id')])];

        foreach (self::TEAM as [$name, $kind, $role, $roleDocument, $summary]) {
            $agentId = DB::table('agents')->insertGetId([
                'name' => $name,
                'kind' => $kind,
                'role' => $role,
                'role_document_id' => $roleDocument ? $documentId($roleDocument, false) : null,
                'summary' => $summary,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($kind !== 'Agent') {
                continue;
            }

            foreach ([...$shared, ...($extra[$role] ?? [])] as $id) {
                DB::table('agent_documents')->insert(['agent_id' => $agentId, 'document_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /**
     * Reverse the migrations. De documenten blijven staan.
     */
    public function down(): void
    {
        DB::table('agent_documents')->delete();
        DB::table('agents')->delete();
    }
};
