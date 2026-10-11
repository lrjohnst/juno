<?php
declare(strict_types=1);

/**
 * De controles. Deterministisch, gratis, niet om te praten.
 *
 * Twee soorten:
 *   - preflight()  draait vóór de tick. Klopt de omgeving?
 *   - vorm()       toetst wat de agent inlevert, nog vóór we iets wegschrijven
 *
 * De belangrijkste controle van v2 staat hier níét, en dat is bewust: of een tick
 * geslaagd is, blijkt pas uit de testsuite nádat het bestand is weggeschreven.
 * Die vergelijking doet tick.php, met TestRunner. Zie voortgang() hieronder voor
 * de regel zelf.
 *
 * Elke melding is geschreven om terug naar de agent te gaan bij een retry.
 * "Afgekeurd" zonder reden leert hem niets.
 */
final class Gates
{
    public function __construct(private array $config) {}

    /**
     * Draait vóór alles. Gaat hier iets mis, dan is er geen tick, geen commit en
     * geen kosten — alleen een leesbare melding.
     *
     * @return string[] fouten; leeg betekent: ga door
     */
    public function preflight(): array
    {
        $problemen = [];

        // Kan juno alles lezen wat hij nodig heeft? Een editor die een bestand
        // herschrijft laat het eigendom springen, en dan valt de tick halverwege om.
        $kernelBestanden = ['config.php', 'State.php', 'Gates.php', 'Agent.php',
                            'AiStudioAgent.php', 'TestRunner.php',
                            'CONSTITUTION.md', 'MISSION.sha256'];
        foreach ($kernelBestanden as $bestand) {
            if (! is_readable($this->config['kernel'] . '/' . $bestand)) {
                $problemen[] = "kernel/$bestand is niet leesbaar "
                             . '(eigendom gesprongen? gebruik setfacl, geen chmod)';
            }
        }

        foreach (['MISSION.md','STATE.json','BACKLOG.md','JOURNAL.md','PLAYBOOK.md','metrics.csv'] as $bestand) {
            if (! is_readable($this->config['mission'] . '/' . $bestand)) {
                $problemen[] = "mission/$bestand is niet leesbaar";
            }
        }

        // De testsuite moet draaibaar zijn, en de agent mag er niet in schrijven.
        if (! is_readable($this->config['spec'] . '/run.php')) {
            $problemen[] = 'spec/run.php is niet leesbaar — zonder testsuite is er geen missie';
        }
        if (is_writable($this->config['spec'])) {
            $problemen[] = 'spec/ is schrijfbaar voor de draaiende gebruiker — '
                         . 'dan kan de agent de meetlat verzetten in plaats van de tests halen';
        }

        // Het anker mag niet bewogen zijn.
        $missiePad   = $this->config['mission'] . '/MISSION.md';
        $verwacht    = trim((string) @file_get_contents($this->config['kernel'] . '/MISSION.sha256'));
        $werkelijk   = is_readable($missiePad) ? hash_file('sha256', $missiePad) : '';
        if ($verwacht === '' || $werkelijk === '') {
            $problemen[] = 'missie-hash kon niet worden vastgesteld';
        } elseif (! hash_equals($verwacht, $werkelijk)) {
            $problemen[] = 'MISSION.md wijkt af van kernel/MISSION.sha256 — het anker is verplaatst. '
                         . 'Loop gestopt. Was dit bewust, werk dan de hash bij met ./reseal.';
        }

        // De agent mag niet in de kernel kunnen schrijven.
        if (is_writable($this->config['kernel'])) {
            $problemen[] = 'kernel/ is schrijfbaar voor de draaiende gebruiker — dat hoort niet';
        }

        return $problemen;
    }

    /**
     * Toetst de vorm van wat de agent inlevert, vóór er iets naar schijf gaat.
     *
     * Dit is opzettelijk mager: de inhoudelijke toets is de testsuite. Hier gaat
     * het alleen om "kan de kernel hier iets mee".
     *
     * @return string[] fouten; leeg betekent: wegschrijven en de tests draaien
     */
    public function vorm(array $antwoord): array
    {
        $fouten = [];

        foreach (['bestand', 'inhoud', 'journal'] as $veld) {
            $leeg = ! isset($antwoord[$veld])
                 || (is_string($antwoord[$veld]) && trim($antwoord[$veld]) === '');
            if ($leeg) {
                $fouten[] = "veld '$veld' ontbreekt of is leeg";
            }
        }
        if ($fouten !== []) {
            return $fouten;   // zonder deze velden heeft de rest geen zin
        }

        $bestand = (string) $antwoord['bestand'];
        if (! preg_match($this->config['bestand_patroon'], $bestand)) {
            $fouten[] = "bestandsnaam '$bestand' mag niet; toegestaan is "
                      . 'kleine letters, cijfers en underscores, met .php erachter';
        }

        $inhoud = (string) $antwoord['inhoud'];
        if (strlen($inhoud) > $this->config['max_bestand_bytes']) {
            $fouten[] = sprintf('bestand is %d bytes; maximaal %d',
                strlen($inhoud), $this->config['max_bestand_bytes']);
        }
        if (! str_contains($inhoud, '<?php')) {
            $fouten[] = 'de inhoud begint niet met <?php — dan laadt de testrunner hem niet';
        }

        return $fouten;
    }

    /**
     * De harde voortgangsregel van v2.
     *
     * Een tick is alleen geslaagd als er méér tests groen staan dan ervoor. Gelijk
     * blijven is géén voortgang en een regressie al helemaal niet. Dit is niet te
     * omzeilen: de agent kan de tests niet aanpassen, alleen de implementatie.
     *
     * @return string[] fouten; leeg betekent: accepteren
     */
    public function voortgang(int $voor, int $na, int $totaal): array
    {
        if ($na > $voor) {
            return [];
        }
        if ($na < $voor) {
            return [sprintf('regressie: er stonden %d van %d tests groen, nu %d. '
                          . 'Je hebt iets gesloopt dat eerder werkte.', $voor, $totaal, $na)];
        }
        return [sprintf('geen voortgang: er stonden %d van %d tests groen, en nog steeds %d. '
                      . 'Een tick slaagt alleen als er een test bij groen komt.',
                        $voor, $totaal, $na)];
    }
}
