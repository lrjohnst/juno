# Backlog — juno

App-specifiek. Server-werk staat in `/home/claude/BACKLOG.md`.

## Open

### Eerst: de missie moet moeilijker
1. **De testsuite uitbreiden.** GPT-5.5 haalde de huidige 40 tests in één tick, dus er is
   geen loop om te observeren. Uitbreiden met strings, lijsten, voorwaardes, loops en
   foutmeldingen bij ongeldige invoer — tot de code niet meer in één prompt past. Dat is
   precies de grens waar echte tool-use nodig wordt.
2. Alternatief of aanvullend: een suite die een architectuur afdwingt (lexer, parser en
   runtime elk in een eigen bestand), zodat één tick per bestand noodzakelijk is.

### Dan: de loop echt autonoom maken
3. systemd service + timer (geen cron: geeft sandboxing en journalctl).
4. systemd-hardening: `ProtectSystem=strict`, `ProtectHome=tmpfs`, `BindPaths=/home/juno`,
   `PrivateTmp=yes`, `NoNewPrivileges=yes`.

### De recursie — nooit getest
5. **Reflectie-tick** elke 10 ticks die `PLAYBOOK.md` vult, met een cap van 20 regels.
   Na 410 ticks in v1 is dat bestand nog leeg: het systeem heeft nog nooit iets over zijn
   eigen werkwijze geleerd. Dit is de belangrijkste openstaande vraag van het hele project.
6. Meetbaar maken: daalt de afkeurratio nadat het playbook gevuld raakt? Bij een moeilijke
   suite met regelmatige afkeuringen is dat een echte meting.
7. Inbox-verwerking in de tick (sorteren naar backlog, playbook of journal).

### Uitwijk bij provider-problemen
8. **Fallback-keten.** ai-studio geeft al `alternatives` mee in zijn foutenvelop, maar juno
   gebruikt dat niet. Nu valt de loop stil bij een leeg tegoed; met een fallback schuift hij
   door naar het volgende toegestane model. Relevant nu Mistral wegvalt.

### Overig
9. Klein leesdashboard (Laravel) dat `metrics.csv` en `JOURNAL.md` toont — alleen lezen.
10. Tool-use: de agent die zelf bestanden leest en tests draait, in plaats van de hele
    codebase in de prompt. Pas nodig als punt 1 tegen de promptgrens aanloopt.

## Afgerond

- Fase 1: skelet, bevroren missie, kernel, testsuite (2026-09-20).
- Fase 2: AiStudioAgent, client + prompt in ai-studio, eerste echte bundel (2026-09-23).
- v1 afgerond: 70 bundels, vier experimenten, rapport op lucasjohnston.nl (2026-09-25).
- v2 vertrekpunt: bevroren testsuite in `spec/`, harde voortgangsgate, TestRunner,
  19 kernel-tests groen (2026-10-11).
