<?php
declare(strict_types=1);

/**
 * De agent is het enige onvoorspelbare onderdeel van dit systeem.
 * De kernel praat met hem via één methode en vertrouwt niets van wat terugkomt.
 */
interface Agent
{
    /**
     * @return array{rauw:string, verbruik:array{input_tokens:int,output_tokens:int}}
     * @throws AgentOnbereikbaar
     */
    public function vraag(string $opdracht): array;

    /** Voor het journal en de logregel. */
    public function naam(): string;
}

/**
 * De agent kon niet bereikt worden — netwerk, leeg tegoed, of een storing aan
 * de kant van de gateway.
 *
 * Dit is uitdrukkelijk iets anders dan afgekeurd werk. Slecht werk hoort de
 * faalteller te verhogen en uiteindelijk te escaleren; een lege provider hoort
 * dat niet te doen. Anders staat de loop stil om een reden waar de agent part
 * noch deel aan heeft.
 */
final class AgentOnbereikbaar extends RuntimeException {}
