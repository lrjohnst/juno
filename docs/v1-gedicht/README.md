# v1 — de gedichten-agent (archief)

Het eerste harnas-experiment, afgerond op 2026-09-25. Terug te halen met:

```bash
git checkout v1-gedicht
```

Volledig rapport met alle 70 bundels: <https://lucasjohnston.nl/juno>

## De missie

> Schrijf, strofe voor strofe, een bundel van vier strofen. Elke strofe beschrijft één
> alledaagse maar archetypische menselijke ervaring, zo helder mogelijk, zodat de lezer
> die ervaring herkent en even opnieuw voelt. Geordend van ochtend naar nacht.

Eén strofe per tick. Vier ticks per bundel.

## Waarom een gedicht en niet meteen code

Omdat je aan een gedicht met het blote oog ziet wanneer een systeem afdwaalt. Een
tradingbot die afdwaalt levert een grafiek op die nog steeds stijgt. Deze versie moest
de machinerie bewijzen, niet de toepassing.

## Wat het bewees

**Het harnas werkt.** 14 tests groen, 410 ticks, geen enkele keer twee ticks tegelijk,
rollback deed wat hij moest doen, en de agent kon nooit bij zijn eigen regels.

**De vier gates hielden.** Elke strofe moest een eigen onderwerp hebben en een moment
van de dag dat niet terugliep. Dat werkte dankzij één truc: de agent *declareert* zelf
welk moment en welk onderwerp hij koos, waarna de controle een set-vergelijking is in
plaats van taalanalyse. Declareren in plaats van detecteren.

## Wat het níét bewees

**Zelfverbetering.** `PLAYBOOK.md` bleef 410 ticks lang leeg. De reflectie-tick is
nooit gebouwd, de critic ook niet. Het systeem leverde werk af maar leerde nooit iets
over zijn eigen werkwijze. Dat is de belangrijkste openstaande vraag.

## De vier experimenten

| | vraag | uitkomst |
|---|---|---|
| `ab-test.sh` | maakt overbodige uitleg in de missie verschil? | nee — H0 niet verworpen (2/10 tegen 2/10) |
| `2x2-test.sh` | sturen de voorbeelden in de missie de output? | **ja, sterk** — lift in 5/5 bundels met voorbeelden B op Medium, 0/5 elders |
| `omgekeerd-test.sh` | zit de somberte aan het tijdstip of aan de positie? | aan het tijdstip — maar 7/10 bundels liep vast omdat het model met de ochtend bleef beginnen |
| `large-test.sh` + `gpt-test.sh` | helpt een groter model? | bij Mistral: groter = eentoniger. GPT-5.5 doorbreekt dat |

De scripts staan hier en zijn herhaalbaar, mits je ze tegen een `v1-gedicht`-checkout
draait: ze verwachten `moments` in de config en de gedicht-gates in `Gates.php`.

## De drie belangrijkste lessen voor v2

**1. Je missietekst stuurt meer dan je bedoelt.** De drie voorbeelden waren als
illustratie bedoeld en bepaalden aantoonbaar waar het model over schreef. En niet
letterlijk: er stond geen lift in de voorbeelden, maar wel een volle wachtkamer, een
bus en niet-bij-iets-kunnen. Het model interpoleert in de ruimte ertussen.

**2. Een instructie die tegen een modelvoorkeur in gaat, wordt niet gevolgd — alleen
duur.** De omgekeerde volgorde kostte 7 van de 10 bundels. De gate hield netjes tegen
maar kon niets afdwingen.

**3. Meet afwisselend, niet in blokken.** Een eerste meting gaf 4-tegen-1 en leek een
effect. Zodra de twee varianten om en om draaiden in plaats van na elkaar, was het
2-tegen-2. Dat was ruis.

## Bestanden hier

- `resultaten*.csv` — de ruwe metingen per experiment
- `bundels.json` / `stats.json` — alle 70 bundels geparseerd, plus de afgeleide cijfers
  (dit voedt het rapport op lucasjohnston.nl)
- `MISSION-*.md` / `CONSTITUTION-*.md` — de varianten die tegen elkaar zijn gedraaid
- `*-test.sh` — de experimentscripts
