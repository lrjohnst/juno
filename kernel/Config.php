<?php
declare(strict_types=1);

/**
 * Getypte configuratie. Eén plek voor alle knoppen, en een typefout in een
 * sleutel wordt een fout in plaats van een stille null.
 */
final readonly class Config
{
    public function __construct(
        public string $root,
        public string $kernel,
        public string $mission,
        public string $work,
        public string $var,
        public string $phpBinary = '/usr/bin/php',
        public int $maxRetries = 2,
        public int $maxTicksPerDag = 60,
        public int $maxMislukkingen = 3,
        public int $timeoutSeconden = 180,
        public int $journalVenster = 3,
        public string $aistudioUrl = '',
        public string $aistudioPrompt = '',
        public string $aistudioModel = '',
    ) {}

    public static function standaard(string $root = '/home/juno'): self
    {
        $overrides = [];
        if (is_readable($root . '/kernel/config.local.php')) {
            $overrides = (array) require $root . '/kernel/config.local.php';
        }

        return new self(
            root:            $root,
            kernel:          $root . '/kernel',
            mission:         $root . '/mission',
            work:            $root . '/work',
            var:             $root . '/var',
            phpBinary:       $overrides['php_binary']        ?? '/usr/bin/php',
            maxRetries:      $overrides['max_retries']       ?? 2,
            maxTicksPerDag:  $overrides['max_ticks_per_dag']  ?? 60,
            maxMislukkingen: $overrides['max_mislukkingen']  ?? 3,
            timeoutSeconden: $overrides['timeout_seconden']  ?? 180,
            journalVenster:  $overrides['journal_venster']   ?? 3,
            aistudioUrl:     $overrides['aistudio_url']      ?? 'https://ai-studio.phitech.cloud/api/chat',
            aistudioPrompt:  $overrides['aistudio_prompt']   ?? '',
            aistudioModel:   $overrides['aistudio_model']    ?? '',
        );
    }

    public function pad(string ...$delen): string
    {
        return $this->root . '/' . implode('/', $delen);
    }
}
