<?php
declare(strict_types=1);

/**
 * Het geheugen van juno: de bestanden in mission/.
 *
 * Twee soorten staat, met verschillende regels:
 *   - STATE.json en het werkstuk mogen terug in de tijd (rollback)
 *   - JOURNAL.md en metrics.csv groeien alleen aan, ook bij mislukking
 *
 * Die asymmetrie is wat voorkomt dat tick 48 dezelfde fout maakt als tick 47.
 * Haal hem niet weg om de boel op te ruimen.
 */
final class State
{
    public function __construct(private Config $config) {}

    private function pad(string $bestand): string
    {
        return $this->config->mission . '/' . $bestand;
    }

    public function lees(): array
    {
        $rauw = @file_get_contents($this->pad('STATE.json'));
        if ($rauw === false) {
            throw new RuntimeException('STATE.json niet leesbaar');
        }
        $data = json_decode($rauw, true);
        if (! is_array($data)) {
            throw new RuntimeException('STATE.json bevat geen geldige JSON');
        }
        return $data + [
            'tick' => 0,
            'status' => 'idle',
            'laatste_uitkomst' => null,
            'mislukkingen_op_rij' => 0,
            'stand' => null,
            'bijgewerkt' => null,
        ];
    }

    public function schrijf(array $staat): void
    {
        $staat['bijgewerkt'] = date('c');
        $this->atomisch(
            $this->pad('STATE.json'),
            json_encode($staat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /** Append-only. Overleeft elke rollback. */
    public function journaal(int $tick, Uitkomst $uitkomst, string $tekst): void
    {
        $entry = sprintf("\n## Tick %d — %s — %s\n\n%s\n",
            $tick, date('Y-m-d H:i'), $uitkomst->value, trim($tekst));
        file_put_contents($this->pad('JOURNAL.md'), $entry, FILE_APPEND | LOCK_EX);
    }

    /** Append-only. Dit bestand is het dashboard. */
    public function metriek(array $rij): void
    {
        $pad = $this->pad('metrics.csv');
        $kolommen = $this->kolommen($pad, array_keys($rij));

        $waarden = [];
        foreach ($kolommen as $kolom) {
            $waarde = (string) ($rij[$kolom] ?? '');
            $quoten = str_contains($waarde, ',') || str_contains($waarde, '"') || str_contains($waarde, "\n");
            $waarden[] = $quoten
                ? '"' . str_replace('"', '""', str_replace("\n", ' ', $waarde)) . '"'
                : $waarde;
        }
        file_put_contents($pad, implode(',', $waarden) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * De kopregel bepaalt de kolommen. Bestaat het bestand nog niet, dan
     * schrijven we de kop uit de eerste rij — zo mag een missie zelf bepalen wat
     * hij meet zonder dat de kernel die kolommen hoeft te kennen.
     */
    private function kolommen(string $pad, array $uitRij): array
    {
        $eerste = @file($pad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0] ?? null;
        if ($eerste !== null) {
            return array_map('trim', explode(',', $eerste));
        }
        file_put_contents($pad, implode(',', $uitRij) . "\n", LOCK_EX);
        return $uitRij;
    }

    /** De laatste N journal-entries, voor de opdracht. */
    public function recenteJournaal(int $aantal): string
    {
        $rauw = @file_get_contents($this->pad('JOURNAL.md')) ?: '';
        $entries = preg_split('/^## Tick /m', $rauw);
        if ($entries === false || count($entries) <= 1) {
            return '(nog geen eerdere ticks)';
        }
        array_shift($entries);
        return '## Tick ' . implode('## Tick ', array_slice($entries, -$aantal));
    }

    public function get(string $bestand): string
    {
        return @file_get_contents($this->pad($bestand)) ?: '';
    }

    public function put(string $bestand, string $inhoud): void
    {
        $this->atomisch($this->pad($bestand), $inhoud);
    }

    /** Aantal ticks vandaag, uit metrics.csv — voor de dagcap. */
    public function ticksVandaag(): int
    {
        $rauw = @file_get_contents($this->pad('metrics.csv')) ?: '';
        $vandaag = date('Y-m-d');
        $aantal = 0;
        foreach (explode("\n", $rauw) as $regel) {
            if ($regel === '' || str_starts_with($regel, 'tick,')) {
                continue;
            }
            $kolommen = str_getcsv($regel, ',', '"', '\\');
            if (isset($kolommen[1]) && str_starts_with((string) $kolommen[1], $vandaag)) {
                $aantal++;
            }
        }
        return $aantal;
    }

    /** Briefjes die van buiten zijn neergelegd. */
    public function inbox(): array
    {
        $berichten = [];
        foreach (glob($this->config->mission . '/inbox/*') ?: [] as $pad) {
            if (is_file($pad)) {
                $berichten[basename($pad)] = (string) file_get_contents($pad);
            }
        }
        return $berichten;
    }

    /** Verwerkt briefje wegzetten — anders gaat het elke tick opnieuw mee. */
    public function inboxVerwerkt(string $bestand): void
    {
        @rename(
            $this->config->mission . '/inbox/' . $bestand,
            $this->config->mission . '/inbox/processed/' . date('Ymd-His') . '-' . $bestand
        );
    }

    private function atomisch(string $pad, string $inhoud): void
    {
        $tijdelijk = $pad . '.tmp';
        if (file_put_contents($tijdelijk, $inhoud, LOCK_EX) === false) {
            throw new RuntimeException("kan niet schrijven naar $tijdelijk");
        }
        if (! rename($tijdelijk, $pad)) {
            @unlink($tijdelijk);
            throw new RuntimeException("kan $tijdelijk niet hernoemen naar $pad");
        }
    }
}
