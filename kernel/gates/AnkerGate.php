<?php
declare(strict_types=1);

/**
 * De missie mag niet ongemerkt meebewegen met het werk.
 *
 * Zonder deze controle is elke uitkomst per definitie on-mission: wie het doel
 * mag bijstellen, haalt het altijd. De hash in kernel/MISSION.sha256 maakt een
 * wijziging een bewuste handeling (./reseal) in plaats van bijvangst.
 */
final class AnkerGate implements Gate
{
    public function __construct(private Config $config) {}

    public function naam(): string { return 'anker'; }
    public function naMeting(): bool { return false; }

    public function keur(Beurt $beurt): array
    {
        $missiePad = $this->config->mission . '/MISSION.md';
        $hashPad   = $this->config->kernel . '/MISSION.sha256';

        if (! is_readable($missiePad)) {
            return [Bevinding::fataal($this->naam(), 'mission/MISSION.md is niet leesbaar')];
        }
        $verwacht = trim((string) @file_get_contents($hashPad));
        if ($verwacht === '') {
            return [Bevinding::fataal($this->naam(),
                'kernel/MISSION.sha256 is leeg of onleesbaar — verzegel de missie met ./reseal')];
        }
        if (! hash_equals($verwacht, hash_file('sha256', $missiePad))) {
            return [Bevinding::fataal($this->naam(),
                'MISSION.md wijkt af van kernel/MISSION.sha256 — het anker is verplaatst. '
                . 'Was dat bewust, werk de hash dan bij met ./reseal.')];
        }
        return [];
    }
}
