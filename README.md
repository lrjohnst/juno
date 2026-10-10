# juno

Een autonome schrijf-agent. Juno krijgt één opdracht — zijn **missie** — en werkt daar
zelfstandig aan. Elke ronde (*tick*) leest hij waar hij staat, bedenkt zelf wat de volgende
stap is, voert die uit, en legt vast wat hij deed. Daarna gaat hij uit. De volgende tick
begint weer bij nul.

**Rapport met alle bevindingen en gedichten:** <https://lucasjohnston.nl/juno>

## Waarom een gedicht

De eerste missie is klein en controleerbaar: schrijf een bundel van vier strofen, elk over een
alledaagse ervaring, geordend van ochtend naar nacht. Niet omdat het om poëzie gaat, maar omdat
je aan een gedicht met het blote oog ziet wanneer een systeem afdwaalt. Bij een handelsalgoritme
ziet afdwalen eruit als een grafiek die nog steeds stijgt.

Dit project is dus eerst een *harnas* en pas daarna een toepassing.

## Opzet

```
kernel/      de aansturing — dom, deterministisch, en niet schrijfbaar voor de agent
  tick.php       één ronde: lock, preflight, prompt, agent, gates, rollback, commit
  Gates.php      harde controles; elke melding gaat bij een retry terug naar de agent
  State.php      lezen en schrijven van mission/
  Agent.php      stub-agent voor tests, zonder API-aanroep
  AiStudioAgent.php   praat met ai-studio (eigen LLM-gateway)
  CONSTITUTION.md     werkregels voor de agent
  MISSION.sha256      hash van de missie; wijkt die af, dan stopt de loop
  tests/         14 tests, waarvan 5 die de agent per definitie niet kan uitvoeren

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

## Status

Het harnas werkt: 14 tests groen, honderden ronden gedraaid over vier taalmodellen. Wat nog
niet gebouwd is staat in [BACKLOG.md](BACKLOG.md) — met name de zachte controle (een beoordelaar
met een cijfer), de reflectie-ronde die het playbook vult, en een systemd-timer die de loop
echt autonoom maakt.
