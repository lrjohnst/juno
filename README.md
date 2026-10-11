# juno

Een autonome schrijf-agent. Juno krijgt één opdracht — zijn **missie** — en werkt daar
zelfstandig aan. Elke ronde (*tick*) leest hij waar hij staat, bedenkt zelf wat de volgende
stap is, voert die uit, en legt vast wat hij deed. Daarna gaat hij uit. De volgende tick
begint weer bij nul.

**Rapport met alle bevindingen en gedichten:** <https://lucasjohnston.nl/juno>

## De huidige missie: een testsuite groen krijgen

Juno bouwt een interpreter voor een kleine expressietaal in PHP, tot alle 40 tests in `spec/`
groen staan. Die testsuite is **bevroren en root-eigendom** — juno kan hem niet aanpassen.
Dat is de kern: wie de test kan wijzigen, haalt niet de test maar de meetlat omlaag.

Daarmee is de kwaliteitstoets hard. Een tick slaagt alleen als er een test bij groen komt en
geen enkele test die groen stond rood wordt. Geen beoordelaar, geen cijfer op gevoel.

## Hiervoor: de gedichten-agent

De eerste versie schreef gedichten — niet om de poëzie, maar omdat je aan een gedicht met het
blote oog ziet wanneer een systeem afdwaalt. Dat bewees het harnas en leverde onderweg een
reeks experimenten op over het sturen van taalmodellen.

- Rapport met alle 70 bundels: <https://lucasjohnston.nl/juno>
- Archief en ruwe data: [docs/v1-gedicht/](docs/v1-gedicht/)
- Code: `git checkout v1-gedicht`

## Opzet

```
kernel/      de aansturing — dom, deterministisch, en niet schrijfbaar voor de agent
  tick.php       één ronde: lock, preflight, nulmeting, agent, schrijven, hermeting, commit
  Gates.php      preflight, vormcontrole en de harde voortgangsregel
  TestRunner.php draait spec/ en leest de uitslag
  State.php      lezen en schrijven van mission/
  Agent.php      stub-agent voor tests, zonder API-aanroep
  AiStudioAgent.php   praat met ai-studio (eigen LLM-gateway)
  CONSTITUTION.md     werkregels voor de agent
  MISSION.sha256      hash van de missie; wijkt die af, dan stopt de loop
  tests/         19 tests, waarvan 7 die de agent per definitie niet kan uitvoeren

spec/        de bevroren testsuite — root-eigendom. Dit ís de missie
  run.php        de testrunner; meldt OK/FAIL per test en een totaalregel
  tests/         40 testgevallen in zeven groepen, oplopend in moeilijkheid

mission/     de staat — de agent schrijft hier, in een vaste vorm
  MISSION.md     bevroren. Het anker waartegen afdwaling gemeten wordt
  STATE.json     tick-nummer, voortgang, gebruikte onderwerpen, faalteller
  JOURNAL.md     append-only; een mislukking blijft staan, ook na een rollback
  BACKLOG.md     de agent onderhoudt zijn eigen volgende stap
  PLAYBOOK.md    geleerde lessen over de werkwijze
  metrics.csv    één regel per tick — het dashboard
  inbox/         briefjes van buiten, die bij de volgende tick meegaan

work/        het werkstuk. Hier is de agent volledig vrij
var/         runtime: lockfile, noodstop, logs (buiten git)
```

## Gebruik

```bash
./tick                 # één echte ronde via ai-studio
./tick --dry-run       # stub-agent, geen API, geen kosten
./tick --verbose       # toon de volledige prompt
./tick --model=gpt-5.5 # ander model dan de standaard
php spec/run.php       # de testsuite handmatig draaien
./reset                # terug naar tick 0 (alleen de staat, nooit code)
./reseal               # de missie opnieuw verzegelen na een wijziging

bash kernel/tests/run.sh              # 9 tests als de agent
sudo bash kernel/tests/run-as-root.sh # 5 tests die de agent niet kán draaien
touch var/STOP                        # noodstop: volgende tick doet niets
```

## Twee ontwerpregels die de rest verklaren

**De agent kan niet aan zijn eigen regels komen.** `kernel/` is root-eigendom, de missie is
beschermd met een hash, en `mission/` heeft een sticky bit zodat de agent andermans bestanden
niet kan verwijderen. Niet uit voorzichtigheid: wie de controles kan aanpassen, verlaagt bij de
eerste afkeuring gewoon de lat.

**Een mislukking overleeft de rollback.** Wordt werk afgekeurd, dan gaat `work/` terug in de
tijd — maar `JOURNAL.md` en `metrics.csv` groeien alleen aan. Zonder die asymmetrie maakt de
volgende tick exact dezelfde fout, tot in het oneindige.

## Aan de slag

Praktische gids, valkuilen en instructies: **[HANDLEIDING.md](HANDLEIDING.md)**.

## Status

Het harnas werkt: 19 tests groen, honderden ronden gedraaid over vijf taalmodellen. Openstaand
werk in [BACKLOG.md](BACKLOG.md).

Eén bevinding om te weten voordat je begint: **GPT-5.5 haalde de huidige 40 tests in één tick**
— 366 regels met lexer, parser en runtime, meteen groen. Daarmee is er geen loop om te
observeren. De suite moet moeilijker, of het model kleiner. Zie het slot van de handleiding.
