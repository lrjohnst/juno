<?php
declare(strict_types=1);

/**
 * Eén constatering van een gate.
 *
 * De melding gaat bij een retry rechtstreeks naar de agent, dus schrijf hem als
 * instructie en niet als logregel. `fataal` is geen luxe: een vormfout is het
 * opnieuw proberen waard, een verplaatst anker niet.
 */
final readonly class Bevinding
{
    public function __construct(
        public string $gate,
        public string $melding,
        public bool $fataal = false,
    ) {}

    public static function fataal(string $gate, string $melding): self
    {
        return new self($gate, $melding, true);
    }

    /** @param Bevinding[] $bevindingen */
    public static function bevatFatale(array $bevindingen): bool
    {
        foreach ($bevindingen as $b) {
            if ($b->fataal) {
                return true;
            }
        }
        return false;
    }

    /** @param Bevinding[] $bevindingen */
    public static function alsLijst(array $bevindingen): string
    {
        return implode("\n", array_map(fn(self $b): string => '- ' . $b->melding, $bevindingen));
    }

    /** @param Bevinding[] $bevindingen */
    public static function alsRegel(array $bevindingen): string
    {
        return implode('; ', array_map(fn(self $b): string => $b->melding, $bevindingen));
    }
}
