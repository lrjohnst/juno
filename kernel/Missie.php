<?php
declare(strict_types=1);

/**
 * Hier hangt jouw werk in. De kernel weet niets van wat juno maakt; dit is het
 * enige contract daartussen.
 *
 * Een missie beantwoordt vijf vragen:
 *   1. wat moet de agent inleveren          → verwachteVelden()
 *   2. hoe ver zijn we                      → meet()
 *   3. welke controles gelden               → gates()
 *   4. wat gebeurt er met een goedgekeurd antwoord → verwerk()
 *   5. wat moet de agent te zien krijgen    → context()
 *
 * De kernel regelt de rest: lock, noodstop, dagcap, escalatie, het anker, de
 * retry-lus, de rollback, het journal, de metrics en de commit.
 */
interface Missie
{
    /** Korte naam, voor logregels en commits. */
    public function naam(): string;

    /**
     * Welke velden het antwoord van de agent moet bevatten, naast `journal` en
     * `backlog` die de kernel zelf al verwacht.
     *
     * @return string[]
     */
    public function verwachteVelden(): array;

    /**
     * De huidige stand. Wordt aangeroepen vóór de agent (de nulmeting) en na
     * het wegschrijven (de hermeting).
     *
     * Houd dit goedkoop en bijwerkingsvrij: het draait meerdere keren per tick.
     */
    public function meet(): Meting;

    /**
     * De controles, in volgorde. De kernel voegt zijn eigen gates ervóór toe
     * (anker, rechten, antwoordvorm).
     *
     * @return Gate[]
     */
    public function gates(): array;

    /**
     * Schrijf een goedgekeurd antwoord weg naar het werkstuk.
     *
     * Let op de volgorde in de kernel: dit gebeurt vóór de hermeting en vóór de
     * voortgangsgate. Een afgekeurde tick wordt daarna teruggedraaid met git, dus
     * schrijf alleen binnen `work/`.
     */
    public function verwerk(AgentAntwoord $antwoord): void;

    /**
     * Extra blokken voor de opdracht aan de agent, als kop => inhoud.
     *
     * Hier zet je wat de agent nodig heeft om zijn volgende stap te kiezen: de
     * huidige staat van het werkstuk, een foutrapport, een meetuitslag. Houd het
     * kort — elke zin die niet over de taak gaat, verdunt de zinnen die dat wel
     * doen.
     *
     * @return array<string,string>
     */
    public function context(): array;

    /**
     * De opdrachtregel voor deze tick: wat moet de agent nú doen, en in welke
     * vorm antwoordt hij.
     */
    public function opdracht(): string;
}
