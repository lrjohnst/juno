<?php
declare(strict_types=1);

/**
 * Optioneel aanvullend contract: een missie die met de stub-agent getest kan
 * worden, zonder tokens uit te geven.
 *
 * Los van Missie gehouden omdat het alleen voor tests is. Implementeer je dit
 * niet, dan werkt --stub niet voor jouw missie — en dan kun je de lus alleen
 * met echte aanroepen testen, wat geld kost en langzamer is. Doe het dus.
 */
interface MetStub
{
    /**
     * Antwoorden per modus. `null` als waarde geeft onleesbare JSON terug, zodat
     * je ook die tak kunt toetsen.
     *
     * @return array<string,array<string,mixed>|null>
     */
    public function stubAntwoorden(): array;
}
