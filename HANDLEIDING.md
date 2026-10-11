# Handleiding — zelf aan juno werken

Praktische gids bij de kernel. Voor het *waarom* achter het ontwerp: zie
[README.md](README.md). Voor het eerste experiment: [docs/v1-gedicht/](docs/v1-gedicht/).

---

## Snelstart

```bash
cd /home/juno

./tick --dry-run         # stub-agent: geen API, geen kosten, hele lus getest
./tick                   # echte tick via ai-studio (prompt juno-code, gpt-5.5)
./tick --verbose         # toon de volledige opdracht die de agent krijgt
./tick --model=gpt-5.5   # ander model dan de standaard uit config.php
./reset                  # terug naar tick 0: staat én werkstuk leeg
./reseal                 # missie opnieuw verzegelen na een wijziging

php spec/run.php         # de testsuite handmatig draaien
php spec/run.php --stil  # alleen de fouten en de totaalregel

bash kernel/tests/run.sh              # 12 tests als juno
sudo bash kernel/tests/run-as-root.sh # 7 tests die juno niet kán draaien

touch var/STOP           # noodstop: de volgende tick doet niets
rm var/STOP              # weer aan
```

Werk je als `lrjohnst`, dan schakelen `./tick`, `./reset` en `./reseal` zelf om naar
`juno` via sudo. Eén keer je wachtwoord per sessie.

---

## Hoe een tick verloopt

```
  1  flock           twee ticks tegelijk kan niet
  2  STOP            noodstop
  3  preflight       leesbaarheid, spec/ dicht, missie-hash, kernel/ dicht
  4  dagcap          max_ticks_per_day
  5  NULMETING       php spec/run.php  →  X van N groen
  6  klaar?          X == N  →  stop, missie behaald
  7  escalatie       3 mislukkingen op rij  →  stop, vraag om Lucas
  8  opdracht        missie + regels + staat + code + falende tests + journal
  9  agent           →  { bestand, inhoud, journal, backlog }
 10  vormcontrole    naam, grootte, begint met <?php      ── afkeuren = retry
 11  WEGSCHRIJVEN    work/<bestand>
 12  HERMETING       php spec/run.php  →  Y van N groen
 13  voortgangsgate  Y > X?   ja → accepteren
                              nee → git checkout work/, retry mét de testuitvoer
 14  vastleggen      state, journal, metrics — altijd, ook bij afkeuring
 15  commit          git add mission/ work/
```

**Het verschil met v1:** daar controleerde de kernel vóór het wegschrijven, dus de
rollback was ceremonieel. Nu schrijft hij eerst en laat de testsuite oordelen. De
rollback is daarmee een echt onderdeel van de lus geworden — en bij een retry krijgt
de agent de werkelijke testuitvoer terug, niet alleen "afgekeurd".

---

## Waar je aan kunt draaien

Alles in `kernel/config.php`. De interessante knoppen:

| | |
|---|---|
| `max_retries` | pogingen per tick. Hoger = meer kans per tick, meer tokens |
| `max_ticks_per_day` | dagcap |
| `max_failures` | na hoeveel mislukte ticks de loop jou erbij haalt |
| `max_fouten_in_prompt` | hoeveel falende tests de agent ziet. Te veel = ruis |
| `max_bestanden_per_tick` | staat op 1 en dat is met opzet (zie hieronder) |
| `bestand_patroon` | welke bestandsnamen in `work/` mogen |
| `journal_window` | hoeveel eerdere ticks in de opdracht gaan |
| `aistudio.model` | het standaardmodel |

**Waarom één bestand per tick.** Twee bestanden maken de oorzaak van een regressie
onvindbaar: de testsuite zegt dan "vier tests kapot" zonder dat je weet welk bestand.
Eén bestand per tick betekent één commit, één meetpunt, één oorzaak.

---

## Rechten — hier gaat het mis

De agent mag niet aan zijn eigen regels kunnen. Dat is afgedwongen met bestandsrechten
en ACL's, en dat heeft vier valkuilen die je allemaal gaat tegenkomen.

**1. Gebruik `setfacl`, nooit `chmod`.** Op een bestand mét ACL past `chmod` de *mask*
aan, niet de groepsrechten. `chmod 640` haalt stilletjes jouw schrijfrecht weg terwijl
`ls` nog iets anders suggereert.

```bash
# fout
sudo chmod 640 kernel/config.php
# goed
sudo setfacl -m u::rw,g::r,o::-,u:lrjohnst:rw kernel/config.php
```

**2. Editors laten het eigendom springen.** `sed -i`, vim en nano schrijven een nieuw
bestand en hernoemen het. Daarmee wordt `root:juno` ineens `lrjohnst:lrjohnst`, en kan
juno zijn eigen kernel niet meer lezen. De preflight vangt dat af met een leesbare
melding, maar herstellen moet je zelf:

```bash
sudo chown root:juno kernel/<bestand>
sudo setfacl -m u::rw,g::r,o::-,u:lrjohnst:rw kernel/<bestand>
```

Bewerk je iets in `kernel/` of `spec/`, controleer daarna `ls -l`.

**3. `test -w` liegt bij ACL's.** `access(2)` kijkt naar de mask en meldt schrijfbaar
terwijl de echte operatie wordt geweigerd. Twee van onze eigen tests stonden daardoor
ten onrechte op rood. Test rechten door te *proberen*:

```bash
# onbruikbaar
sudo -u juno test -w kernel && echo schrijfbaar
# betrouwbaar
sudo -u juno touch kernel/.probeersel 2>/dev/null && echo "FOUT: kan schrijven"
```

**4. Verwijderrecht zit op de map, niet op het bestand.** Juno bezat `mission/` en kon
daarmee het bevroren `MISSION.md` wissen en vervangen — root-eigendom van het bestand
hielp niets. Opgelost met root-eigendom van de map plus een sticky bit. Een sticky bit
helpt níét zolang de agent zelf map-eigenaar is.

```bash
ls -ld mission/      # drwxrwx--T  root juno   ← de T is het sticky bit
```

---

## De missie wijzigen

`mission/MISSION.md` is verzegeld met een SHA-256 in `kernel/MISSION.sha256`. Wijkt die
af, dan weigert de preflight te draaien. Dat is opzet: een missie die ongemerkt meebeweegt
met het werk maakt elke uitkomst per definitie on-mission.

```bash
# wijzig de tekst (als lrjohnst), dan:
./reseal          # toont de diff en vraagt bevestiging
./reseal -y       # zonder vragen
```

`./reseal` weigert te draaien als juno, en dat hoort zo.

---

## Een gate toevoegen

Gates zitten in `kernel/Gates.php`, in drie methoden met elk een eigen moment:

- **`preflight()`** — vóór de tick. Omgeving, rechten, hash. Faalt dit, geen tick.
- **`vorm()`** — op het antwoord van de agent, vóór wegschrijven. Alleen "kan de kernel
  hier iets mee".
- **`voortgang()`** — na de hermeting. De inhoudelijke toets.

Elke gate geeft een array met leesbare meldingen terug. **Die tekst gaat bij een retry
rechtstreeks naar de agent**, dus schrijf hem als instructie, niet als logregel:

```php
// slecht
$fouten[] = 'ERR_INVALID_NAME';
// goed
$fouten[] = "bestandsnaam '$naam' mag niet; toegestaan is kleine letters, "
          . 'cijfers en underscores, met .php erachter';
```

**Duw een controle zo ver mogelijk naar het harde.** De beste gate is deterministisch
(een test, een regex, een set-vergelijking). Een gate die een taalmodel nodig heeft om
te oordelen is traag, duur en te overtuigen. In v1 werkte daarvoor één truc goed: laat
de agent zelf *declareren* wat hij gedaan heeft, dan is de controle een vergelijking in
plaats van een analyse.

---

## Debuggen

```bash
./tick --verbose | less          # wat ziet de agent precies?
./tick --dry-run --mode=geen-voortgang   # forceer een afkeuring
tail -40 mission/JOURNAL.md      # wat dacht de agent, ook bij mislukkingen
cat mission/metrics.csv          # één regel per tick
git log --oneline | head -20     # tick-commits van juno, setup-commits van jou
php spec/run.php | head -20      # welke tests falen en waarom
```

De stub-agent in `kernel/Agent.php` heeft vier modi: `ok`, `vorm-fout`,
`geen-voortgang` en `invalid-json`. De `ok`-modus levert een échte werkende parser op
die 19 van de 40 tests haalt — anders zou je de voortgangsgate niet testen, want die
laat zich niet voor de gek houden met een placeholder.

---

## Valkuilen uit ervaring

**`grep -q` in een pipe doodt je tick.** `./tick | grep -q "foo"` laat grep afsluiten bij
de eerste match, wat SIGPIPE naar tick.php stuurt. Die stopt dan halverwege en schrijft
zijn journal nooit. Onze eigen testsuite had hierdoor een test die faalde terwijl de
kernel klopte. Vang de uitvoer eerst op:

```bash
uitvoer=$(./tick --dry-run)
printf '%s' "$uitvoer" | grep -q "afgekeurd"
```

**`set -o pipefail` plus exit 2.** De kernel gebruikt exit 2 voor "bewust overgeslagen"
(lock, STOP, dagcap, missie behaald). Met `pipefail` leest een pipe dat als mislukking,
ook als je grep wél iets vond. Daarom staat er `set -u` in de testscripts en geen
`pipefail`.

**`git reset --hard` in een reset-script gooit code weg.** De eerste versie van
`reset.sh` deed dat, en wiste daarmee de `./tick`-shortcut die net was aangemaakt. Een
staat-reset hoort geen code te kunnen slopen: `reset.sh` herstelt nu alleen de
staatsbestanden en leegt `work/`.

**Git weigert voor `lrjohnst` in juno's repo.** "dubious ownership", want `.git` is van
juno. Gebruik `-c safe.directory=/home/juno`:

```bash
git -C /home/juno -c safe.directory=/home/juno log --oneline
```

**`git checkout -- mission/MISSION.md` faalt als juno** met *unable to unlink*. Dat is
geen bug maar de sticky-bit-bescherming. Herstel in-place (overschrijven, niet
vervangen) of als root.

---

## Wat we over prompts hebben geleerd

Uit 70 bundels in v1, zie het [rapport](https://lucasjohnston.nl/juno):

**Je missietekst stuurt meer dan je bedoelt.** Drie voorbeelden die als illustratie
bedoeld waren, bepaalden aantoonbaar waar het model over schreef — en niet letterlijk:
het model interpoleerde in de ruimte tússen de voorbeelden. Als je iets niet wilt zien
terugkomen, noem het dan ook niet als voorbeeld.

**Een instructie die tegen een modelvoorkeur in gaat, wordt niet gevolgd — alleen duur.**
Een omgekeerde tijdsvolgorde kostte 7 van de 10 bundels. De gate hield netjes tegen maar
kon niets afdwingen. Als een gate structureel afgaat, is dat vaker een
instructieprobleem dan een modelprobleem.

**Overbodige uitleg in de prompt kost tokens, niet kwaliteit.** Twintig bundels om en om:
geen aantoonbaar verschil. Haal het eruit omdat het ruis en geld scheelt, niet omdat de
output beter wordt.

**Meet afwisselend, niet in blokken.** Een eerste meting gaf 4-tegen-1 en leek een effect.
Om en om draaien maakte het 2-tegen-2. Dat was ruis.

**Groter is niet vanzelf beter.** Bij Mistral gold: groter model, mínder variatie — Large
opende tien van de tien keer met exact dezelfde woorden. GPT-5.5 doorbrak dat. Voor
variatie tussen runs was het kleinste model het beste.

---

## Openstaande ontwerpvraag

**De eerste code-missie is te makkelijk.** GPT-5.5 haalde alle 40 tests in één tick:
366 regels met een lexer, parser en runtime, meteen groen. Daarmee is er geen loop om te
observeren — en de loop is het hele punt.

Drie manieren om dat op te lossen, met verschillende gevolgen:

1. **Moeilijkere suite.** Meer tests en meer taal: strings, lijsten, voorwaardes, loops,
   foutmeldingen bij ongeldige invoer. Dan past de code op een gegeven moment niet meer
   in één prompt, en loop je tegen de echte grens aan — daar heb je tool-use nodig (de
   agent die zelf bestanden leest) in plaats van de hele codebase meesturen.
2. **Kleiner model.** `gpt-5.4-mini` of `gpt-4.1-mini`. Dan zie je de loop wel werken,
   maar je meet vooral modelzwakte.
3. **Meerdere bestanden verplichten.** Een suite die een architectuur afdwingt (lexer,
   parser en runtime elk in een eigen bestand) maakt één tick per bestand noodzakelijk.

Optie 1 is de interessantste, omdat je dan de grens vindt waar deze opzet — kernel leest
en schrijft, agent levert alleen inhoud — niet meer volstaat. Dat is precies het punt
waarop echte tool-use nodig wordt.
