# Changelog — juno

Autonome agent-loop. Elke tick is één stap richting de missie.

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
