<?php
declare(strict_types=1);

/**
 * De stand van de missie, als één getal.
 *
 * Elke missie moet kunnen zeggen hoe ver hij is, anders kan de kernel niet
 * beoordelen of een tick iets heeft opgeleverd. `maximum` mag null zijn voor een
 * missie zonder vast eindpunt; dan is er geen eindtoestand en stopt de loop pas
 * op jouw noodstop of op de dagcap.
 */
final readonly class Meting
{
    public function __construct(
        public float $waarde,
        public ?float $maximum = null,
        public string $eenheid = '',
        public array $details = [],   // vrij veld voor de missie; gaat mee in de prompt
    ) {}

    public function isVooruitgangOp(self $eerder): bool
    {
        return $this->waarde > $eerder->waarde;
    }

    public function isAchteruitgangOp(self $eerder): bool
    {
        return $this->waarde < $eerder->waarde;
    }

    public function isKlaar(): bool
    {
        return $this->maximum !== null && $this->waarde >= $this->maximum;
    }

    public function __toString(): string
    {
        $getal = $this->waarde == (int) $this->waarde
            ? (string) (int) $this->waarde
            : number_format($this->waarde, 2, ',', '');
        $van = $this->maximum !== null
            ? '/' . ($this->maximum == (int) $this->maximum ? (int) $this->maximum : $this->maximum)
            : '';
        return trim("$getal$van {$this->eenheid}");
    }
}
