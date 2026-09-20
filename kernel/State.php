<?php
declare(strict_types=1);

/**
 * Leest en schrijft de bestanden in mission/.
 *
 * Twee soorten state, met verschillende regels:
 *   - het werkstuk en STATE.json mogen terug in de tijd (rollback)
 *   - JOURNAL.md en metrics.csv groeien alleen maar aan, ook bij mislukking
 * Die asymmetrie is wat voorkomt dat tick 48 dezelfde fout maakt als tick 47.
 */
final class State
{
    public function __construct(private array $cfg) {}

    private function path(string $name): string
    {
        return $this->cfg['mission'] . '/' . $name;
    }

    public function read(): array
    {
        $raw = @file_get_contents($this->path('STATE.json'));
        if ($raw === false) {
            throw new RuntimeException('STATE.json niet leesbaar');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('STATE.json bevat geen geldige JSON');
        }
        return $data + [
            'tick' => 0,
            'status' => 'idle',
            'last_outcome' => null,
            'consecutive_failures' => 0,
            'stanzas' => 0,
            'target_stanzas' => 4,
            'used_archetypes' => [],
            'last_moment_index' => -1,
            'updated_at' => null,
        ];
    }

    public function write(array $state): void
    {
        $state['updated_at'] = date('c');
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->atomicWrite($this->path('STATE.json'), $json . "\n");
    }

    /** Append-only. Overleeft elke rollback. */
    public function journal(int $tick, string $outcome, string $text): void
    {
        $entry = sprintf(
            "\n## Tick %d — %s — %s\n\n%s\n",
            $tick,
            date('Y-m-d H:i'),
            $outcome,
            trim($text)
        );
        file_put_contents($this->path('JOURNAL.md'), $entry, FILE_APPEND | LOCK_EX);
    }

    /** Append-only. Dit bestand is het dashboard. */
    public function metric(array $row): void
    {
        $cols = ['tick','timestamp','outcome','stanzas','words','judge_score','input_tokens','output_tokens','note'];
        $line = [];
        foreach ($cols as $c) {
            $v = (string)($row[$c] ?? '');
            // komma's en nieuwe regels mogen de csv niet breken
            $line[] = str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n")
                ? '"' . str_replace('"', '""', str_replace("\n", ' ', $v)) . '"'
                : $v;
        }
        file_put_contents($this->path('metrics.csv'), implode(',', $line) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** De laatste N journal-entries, voor in de prompt. */
    public function recentJournal(int $n): string
    {
        $raw = @file_get_contents($this->path('JOURNAL.md')) ?: '';
        $parts = preg_split('/^## Tick /m', $raw);
        if ($parts === false || count($parts) <= 1) {
            return '(nog geen eerdere ticks)';
        }
        array_shift($parts); // de kop van het bestand
        $tail = array_slice($parts, -$n);
        return '## Tick ' . implode('## Tick ', $tail);
    }

    public function get(string $name): string
    {
        return @file_get_contents($this->path($name)) ?: '';
    }

    public function put(string $name, string $content): void
    {
        $this->atomicWrite($this->path($name), $content);
    }

    /** Aantal ticks vandaag, uit metrics.csv — voor de dagcap. */
    public function ticksToday(): int
    {
        $raw = @file_get_contents($this->path('metrics.csv')) ?: '';
        $today = date('Y-m-d');
        $n = 0;
        foreach (explode("\n", $raw) as $line) {
            if ($line === '' || str_starts_with($line, 'tick,')) {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            if (isset($cols[1]) && str_starts_with($cols[1], $today)) {
                $n++;
            }
        }
        return $n;
    }

    /** De briefjes die Lucas in de inbox heeft gelegd. */
    public function inbox(): array
    {
        $dir = $this->cfg['mission'] . '/inbox';
        $out = [];
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                $out[basename($f)] = (string)file_get_contents($f);
            }
        }
        return $out;
    }

    /** Verwerkt briefje naar processed/ — anders wordt het elke tick opnieuw uitgevoerd. */
    public function consumeInbox(string $name): void
    {
        $from = $this->cfg['mission'] . '/inbox/' . $name;
        $to   = $this->cfg['mission'] . '/inbox/processed/' . date('Ymd-His') . '-' . $name;
        @rename($from, $to);
    }

    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new RuntimeException("kan niet schrijven naar $tmp");
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("kan $tmp niet hernoemen naar $path");
        }
    }
}
