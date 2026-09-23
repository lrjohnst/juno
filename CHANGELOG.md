# Changelog — juno

Autonome agent-loop. Elke tick is één stap richting de missie.

---

## 2026-09-23

### Fase 2 — koppeling met ai-studio (af)

- `kernel/AiStudioAgent.php`: `POST /api/chat` met Bearer-auth, timeout, en géén
  `conversation_id` — elke tick is een nieuw gesprek, want het geheugen zit in bestanden.
- ai-studio: client `juno` (id 8) en prompt `juno-tick` (id 25) met `response_schema`.
  Dat schema dwingt de JSON-vorm af via native structured output, dus die gate hoeft
  niet meer in onze eigen code. Systeemprompt en schema staan in ai-studio's database
  en liggen daarmee buiten het bereik van de agent.
- `--model=` vlag om per tick van model te wisselen.
- Leesbare 422-melding: Laravel-validatiefouten hebben `{message, errors}` in plaats van
  de `error`-envelop, en kwamen daardoor binnen als een nietszeggend "http_422".

**Ontwerppunt dat de moeite van het onthouden waard is:** een onbereikbare provider is
iets anders dan een afgekeurde strofe. `AgentUnavailable` slaat de tick over zonder het
tick-nummer of `consecutive_failures` te verhogen. Zonder dat onderscheid escaleert de
loop na drie mislukte pogingen op een leeg tegoed — en staat juno stil om een reden die
niets met zijn werk te maken heeft.

**Eerste echte bundel** (4 strofen, via `mistral-small-latest`): alle vier de gates groen,
vier verschillende archetypen, netjes van ochtend naar nacht. Circa 7.700 input- en
990 output-tokens voor de hele bundel.

**Anthropic-tegoed is nog steeds leeg** — de API meldt letterlijk "Your credit balance is
too low". Er is één Anthropic-provider (#1) in ai-studio; die key heeft geen saldo.
`mistral-small-latest` is als uitwijk aan de juno-client gekoppeld.

---

## 2026-09-20

### Fase 1 — skelet en lus (af)

- Mappenstructuur: `kernel/` (root-eigendom, juno leest en voert uit), `mission/` (state),
  `work/` (het artifact), `var/` (runtime, buiten git).
- Missie vastgesteld en bevroren: vier strofen, elk een eigen alledaags archetype,
  geordend van ochtend naar nacht. Drie sloten — tekst, bestandsrechten, SHA-256.
- `kernel/tick.php`: lock, kill switch, preflight, dagcap, prompt-assemblage, retry,
  gates, rollback, journal, metrics, commit.
- `kernel/Gates.php`: zeven harde gates. De agent declareert `moment` en `archetype`
  zelf, zodat de missie-eisen deterministisch toetsbaar zijn in plaats van via taalanalyse.
- `kernel/Agent.php`: `DryRunAgent` draait de volledige lus zonder API-aanroep.
- Testsuite: `tests/run.sh` (9 tests als juno) en `tests/run-as-root.sh` (5 tests die juno
  per definitie niet kan uitvoeren). Alle 14 groen.

**Gevonden tijdens het bouwen:**
- Verwijderrecht zit in Unix op de directory, niet op het bestand. juno bezat `mission/`
  en kon het bevroren anker dus wissen en vervangen. Opgelost met root-eigendom van de map
  plus sticky bit.
- `chmod` verlaagt op een bestand mét ACL de mask, niet de groepsrechten — waardoor
  schrijfrecht stilletjes wegvalt. In dit project dus `setfacl`, nooit `chmod`.
- Editors die een bestand herschrijven (`sed -i`, vim, nano) laten het eigendom springen.
  De preflight vangt dat nu af met een leesbare melding.
- `test -w` is onbruikbaar bij ACL's: `access(2)` kijkt naar de mask en meldt schrijfbaar
  terwijl de echte operatie wordt geweigerd. Rechten test je door te proberen.
