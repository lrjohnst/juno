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
    public function __construct(private array $config) {}

    private function path(string $filename): string
    {
        return $this->config['mission'] . '/' . $filename;
    }

    public function read(): array
    {
        $raw_json = @file_get_contents($this->path('STATE.json'));
        if ($raw_json === false) {
            throw new RuntimeException('STATE.json niet leesbaar');
        }
        $decoded_state = json_decode($raw_json, true);
        if (!is_array($decoded_state)) {
            throw new RuntimeException('STATE.json bevat geen geldige JSON');
        }
        return $decoded_state + [
            'tick' => 0,
            'status' => 'idle',
            'last_outcome' => null,
            'consecutive_failures' => 0,
            'groen' => 0,
            'totaal' => 0,
            'bestanden' => [],
            'updated_at' => null,
        ];
    }

    public function write(array $new_state): void
    {
        $new_state['updated_at'] = date('c');
        $pretty_json = json_encode(
            $new_state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->atomicWrite($this->path('STATE.json'), $pretty_json . "\n");
    }

    /** Append-only. Overleeft elke rollback. */
    public function journal(int $tick_number, string $outcome, string $entry_text): void
    {
        $journal_entry = sprintf(
            "\n## Tick %d — %s — %s\n\n%s\n",
            $tick_number,
            date('Y-m-d H:i'),
            $outcome,
            trim($entry_text)
        );
        file_put_contents($this->path('JOURNAL.md'), $journal_entry, FILE_APPEND | LOCK_EX);
    }

    /** Append-only. Dit bestand is het dashboard. */
    public function metric(array $metric_row): void
    {
        $columns = ['tick','timestamp','outcome','groen','totaal','input_tokens','output_tokens','note'];
        $escaped_values = [];
        foreach ($columns as $column_name) {
            $value = (string)($metric_row[$column_name] ?? '');
            // komma's en nieuwe regels mogen de csv niet breken
            $needs_quoting = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n");
            $escaped_values[] = $needs_quoting
                ? '"' . str_replace('"', '""', str_replace("\n", ' ', $value)) . '"'
                : $value;
        }
        file_put_contents($this->path('metrics.csv'), implode(',', $escaped_values) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** De laatste N journal-entries, voor in de prompt. */
    public function recentJournal(int $entry_count): string
    {
        $raw_journal = @file_get_contents($this->path('JOURNAL.md')) ?: '';
        $all_entries = preg_split('/^## Tick /m', $raw_journal);
        if ($all_entries === false || count($all_entries) <= 1) {
            return '(nog geen eerdere ticks)';
        }
        array_shift($all_entries); // de kop van het bestand
        $recent_entries = array_slice($all_entries, -$entry_count);
        return '## Tick ' . implode('## Tick ', $recent_entries);
    }

    public function get(string $filename): string
    {
        return @file_get_contents($this->path($filename)) ?: '';
    }

    public function put(string $filename, string $contents): void
    {
        $this->atomicWrite($this->path($filename), $contents);
    }

    /** Aantal ticks vandaag, uit metrics.csv — voor de dagcap. */
    public function ticksToday(): int
    {
        $raw_csv    = @file_get_contents($this->path('metrics.csv')) ?: '';
        $today      = date('Y-m-d');
        $tick_count = 0;
        foreach (explode("\n", $raw_csv) as $csv_line) {
            if ($csv_line === '' || str_starts_with($csv_line, 'tick,')) {
                continue;
            }
            $line_values = str_getcsv($csv_line, ',', '"', '\\');
            if (isset($line_values[1]) && str_starts_with($line_values[1], $today)) {
                $tick_count++;
            }
        }
        return $tick_count;
    }

    /** De briefjes die Lucas in de inbox heeft gelegd. */
    public function inbox(): array
    {
        $inbox_dir = $this->config['mission'] . '/inbox';
        $messages  = [];
        foreach (glob($inbox_dir . '/*') ?: [] as $message_path) {
            if (is_file($message_path)) {
                $messages[basename($message_path)] = (string)file_get_contents($message_path);
            }
        }
        return $messages;
    }

    /** Verwerkt briefje naar processed/ — anders wordt het elke tick opnieuw uitgevoerd. */
    public function consumeInbox(string $filename): void
    {
        $source_path  = $this->config['mission'] . '/inbox/' . $filename;
        $archive_path = $this->config['mission'] . '/inbox/processed/' . date('Ymd-His') . '-' . $filename;
        @rename($source_path, $archive_path);
    }

    private function atomicWrite(string $target_path, string $contents): void
    {
        $temp_path = $target_path . '.tmp';
        if (file_put_contents($temp_path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("kan niet schrijven naar $temp_path");
        }
        if (!rename($temp_path, $target_path)) {
            @unlink($temp_path);
            throw new RuntimeException("kan $temp_path niet hernoemen naar $target_path");
        }
    }
}
