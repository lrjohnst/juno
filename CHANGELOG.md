# Changelog — juno

App-specifieke changelog. Server-niveau werk staat in `/home/claude/CHANGELOG.md`.

---

## 2026-10-24 (VI) — Dispatches: Claude Code-opdrachten van de kernel

- **Tabellen** `dispatches` (`tick_id` nullable voor later, `prompt`, `permission_mode`), `dispatch_events`
  (levenscyclus) en `dispatch_results` (één keer geschreven: `session_id`, `output`, `exit_code`, `cost_usd`,
  `duration_ms`, `num_turns`). Geen statuskolom.
- **Labels** (`DispatchEventLabel`): `Started` → `Finished` | `Failed` | `TimedOut` | `Abandoned`.
- **Model `Dispatch`**: `getStatus()`, `setStatus()`, `isOpen()`, `result()`; `Dispatch::abandonStale()` geeft
  opdrachten op die na `dispatch_timeout` + 5 minuten nog `Started` zijn (de kernel is dan waarschijnlijk
  gecrasht); draait elke minuut in de scheduler. `DispatchResult` is onveranderlijk.
- **API**: `POST /api/ticks/{tick}/dispatches` (→ `{id, timeout_seconds}`, meteen `Started`),
  `POST /api/dispatches/{dispatch}/events` en `POST /api/dispatches/{dispatch}/result` (één keer; daarna 409).
- **Instellingen**: `juno.dispatch_timeout` = 15 (nieuw, met rij in `settings`); `juno.tick_abandon_after`
  van 25 naar **60** (config, `.env` en de rij in `settings`), zodat een tick niet wordt opgegeven terwijl
  Claude nog draait.

**Getest** (testtick, daarna opgeruimd): echte Claude zonder login → `Failed` ("Not logged in"); nep-Claude
→ `Finished` met sessie en kosten, en draaide als juno in de workspace; nep-Claude die bleef hangen, met een
achtergrondkind → `TimedOut` na 1 minuut, zonder weesprocessen; vastgelopen dispatch → `Abandoned`;
dubbel resultaat en dubbel afmelden → 409, verkeerd label en verkeerde permissiemodus → 422.

**Let op, gebeurd tijdens het testen:** de testtick was even de nieuwste tick, waardoor de echte tick van
13:30 (23) hem als parent kreeg. Rechtgezet: tick 23 heeft parent 21, de testtick is weg.

---

## 2026-10-24 (V) — Handmatig een tick draaien en de geschiedenis legen

- **`POST /api/ticks`**: zet nu een tick klaar, los van het interval (voor `kernel/run-tick.php`). Zelfde
  regels als de scheduler; gepauzeerd of een open tick → 409 met de reden.
- **`juno:clear-ticks [--force]`**: leegt `ticks`, `tick_events`, `document_versions` en `journal_entries`
  (ids beginnen weer bij 1). Documenten, settings en `juno_events` blijven. Vraagt om bevestiging en
  weigert als er een tick open staat, tenzij `--force`.
- Besluiten van Lucas verwerkt in `BACKLOG.md`; de e-mailhelper staat op de backlog.

**Getest:** `run-tick.php` → tick 63 (parent 62) `Scheduled → Claimed → Finished`; tijdens pauze en met een
open tick 409 met de juiste reden. `juno:clear-ticks` weigerde bij een open tick en verwijderde niets zonder
bevestiging; het echte legen getest op een tijdelijke SQLite-database (daarna: 0 ticks, documenten en
settings bleven, nieuwe tick kreeg id 1). Testticks 63–64 en de juno-events weggehaald.

---

## 2026-10-24 (IV) — Review stap 1-4 (dispatch 4 van 4)

### Doorgedraaid, met echte acties
Testticks 43–46 via de echte keten (scheduler-methodes, HTTP-API via de kernel-helper, artisan):
drie ticks a → b → c (elk claim, document, journal, `Finished`); rollback zonder pauze geweigerd;
pauze → rollback naar b gaf tick 46 met parent 44; claim tijdens pauze gaf niets; na hervatten claimde
de kernel tick 46. Die zag `state = "b"` (`follows_rollback = true`) en `mission = m3` (`false`), en het
journal bevatte nog "fout in tick c" uit de oude branch. Voorouders van 46: 46 → 44 → 43 → 42 → 35 → 29 → 24.
`tick.log` bleef leeg. Testdata daarna weggehaald.

### Tegen de afspraken
- Elke `Schema::create` begint met `id()` en `timestamps()`: **klopt** (7 tabellen).
- camelCase: **klopt**, geen snake_case-methodes meer in handler of kernel.
- Labels met hoofdletter, events-tabellen in plaats van statuskolommen, geen rechtensysteem, geen
  Claimed-only-regel voor schrijven: **klopt**.

### Afwijkingen van het ontwerp
| # | Afwijking | Bewust? |
|---|---|---|
| 1 | `GET /api/ticks/{tick}/documents` zonder `names` geeft alle documenten; `Api::getDocuments()` heeft `$names` optioneel. | Bewust: een uitbreiding, het ontwerp blijft gewoon werken. |
| 2 | Het claim-antwoord bevat `parent_id`, maar `Api::claimTick()` geeft alleen het id terug. Wie `parent_id` nodig heeft, gebruikt `$api->post('/ticks/claim')`. | Bewust: zo blijft `tick.php` werken; of `claimTick()` een array moet worden is aan Lucas. |
| 3 | Een geweigerde rollback gooit `InvalidTickTransition`, ook als de reden de pauze is (geen tick-overgang). | Per ongeluk-achtig, maar onschuldig: de melding is duidelijk. Niet aangepast. |
| 4 | `Tick::head()` is de laatste tick, ook als die `Failed`, `Abandoned` of `Canceled` is. Een volgende tick bouwt dus voort op een mislukte tick, inclusief de documentversies die die nog schreef. | Precies volgens het ontwerp, maar dat gevolg is niet besproken: open vraag. |
| 5 | Ticks 1–24 hebben geen parent (van vóór `parent_id`). | Bekend uit stap 2: open vraag. |
| 6 | Geen automatische tests; `tests/` bevat alleen de voorbeeldtests van Laravel. Alles is met de hand doorgetest. | Niet in het ontwerp; groter werk, niet aangeraakt. |
| 7 | `.env` heeft `APP_LOCALE=en` (`.env.example` zegt `nl`), dus validatiefouten zijn Engels ("The entries field is required."). `LOG_LEVEL=debug` in productie. | Uit de bootstrap, niet uit dit ontwerp. Niet aangepast. |

### Meteen opgelost
- `config/juno.php`: commentaar noemde nog `/home/juno/tick.php` en `/home/juno/.juno-token`; nu
  `kernel/tick.php` en `.kernel-token`.
- `BACKLOG.md`: de tabel "Afgerond" bijgewerkt met stap 1–4.

---

## 2026-10-24 (III) — Stap 4: Journal (dispatch 3 van 4)

- **Instelling `juno.journal_window`** (`JUNO_JOURNAL_WINDOW`, standaard 10) in `config/juno.php`, `.env`,
  `.env.example` en als rij in `settings` (migration), net als de andere twee.
- **Tabel `journal_entries`** (`tick_id`, `content`). Model `JournalEntry`; `Tick->journalEntries()`.
- **`JournalEntry::window()`**: de entries van de chronologisch laatste `journal_window` ticks (op id,
  over alle branches heen, dus niet langs de parent-route), op volgorde van tick en daarbinnen van entry.
- **API**: `GET /api/journal` → `{journal_window, entries: [{id, tick_id, content, created_at}]}`;
  `POST /api/ticks/{tick}/journal` met `{entries: [{content}, …]}` → 201 `{ids}`, in één transactie.
- **Kernel**: `Api::getJournal()` en `Api::addJournalEntries(int $tickId, array $entries)`.

**Getest** met testticks 36–41 (36→37→38→39, en 40 met parent 37 als rollback, 41 na 40) en een tijdelijk
window van 3: het journal gaf de entries van 39, 40 en 41, dus ook de fout uit tick 39 van de oude branch.
Twee entries in één aanroep; lege lijst, ontbrekende en lege inhoud → 422; onbekende tick → 404, alle met
tick-id in `tick.log`. Testdata, testregels en het tijdelijke window (terug naar 10) zijn opgeruimd.

---

## 2026-10-24 (II) — Stap 3: Documents (dispatch 2 van 4)

- **Tabellen** `documents` (`name` uniek, `follows_rollback`) en `document_versions` (`document_id`,
  `tick_id` verplicht, `content` longText).
- **`Document->getVersion(Tick)` / `getContent(Tick)`**: bij `follows_rollback = true` de nieuwste versie
  van een tick uit `$tick->getAncestors()`, anders de nieuwste versie overall. Geen versie: `null`.
- **`Document->setContent($content, Tick)`**: gelijk aan de stand bij die tick → geen nieuwe versie (de
  bestaande komt terug); naam op `.json` → eerst `json_validate()`, anders `InvalidDocumentContent`.
- **`DocumentVersion` is onveranderlijk**: een update gooit een `LogicException`.
- **API**: `GET /api/ticks/{tick}/documents?names[]=…` (zonder `names`: alle documenten) geeft per naam
  `{content, version_id, tick_id}` of `null`; onbekende naam → 404. `POST /api/ticks/{tick}/documents/{name}`
  met `{content}` → 201 (nieuwe versie) of 200 (`created: false`); ongeldige JSON → 422; onbekend → 404.
- **Kernel**: `Api::getDocuments(int $tickId, array $names = [])` en `Api::setDocument(int $tickId, string $name, string $content)`.

**Getest:** het rollback-voorbeeld uit de chat met testticks 30–34 (route 33→32→31→30, en 34 met parent
31): tick 34 zag `"b"`, tick 33 `"d"`; het document met `follows_rollback = false` zag bij tick 34 de
nieuwste versie `d`. Gelijke inhoud gaf geen nieuwe versie; ongeldige JSON en een update van een versie
werden geweigerd; een nieuwe versie bij tick 34 veranderde niets voor tick 33. Via de kernel-helper over
HTTP: lezen per tick, alle documenten, nieuw/gelijk/lege inhoud, 422 en twee keer 404 (die drie fouten
kwamen met `[tick 34]` in `tick.log`). Testdata en de testregels in `tick.log` zijn weggehaald.

---

## 2026-10-24 — Stap 2: parent, Canceled, pauze en rollback (dispatch 1 van 4)

- **`ticks.parent_id`** (nullable FK naar `ticks`, geen `branch`-kolom). `Tick::head()` is de laatste tick
  op id; `Tick::schedule()` geeft die als parent mee. `getParent()` en `getAncestors()` (de tick zelf, dan
  zijn parent, enzovoort). Het claim-antwoord bevat nu `{id, parent_id}`.
- **Label `Canceled`**, alleen na `Scheduled`.
- **`set_status()` heet nu `setStatus()`**, overal.
- **Pauze**: tabel `juno_events` (`label`, `description`, `happened_at`), enum `JunoEventLabel` (`Paused`,
  `Resumed`), model `JunoEvent` en klasse `App\Juno` met `isPaused()`, `pause()` en `resume()`. Pauzeren
  terwijl Juno al gepauzeerd is (of hervatten terwijl hij loopt) geeft `InvalidJunoTransition`.
  Zolang Juno gepauzeerd is, doen `Tick::schedule()` en `Tick::claimNext()` niets.
- **`Tick::rollbackTo($target, $description)`**: alleen als Juno gepauzeerd is, er geen tick open staat en
  het doel `Finished` is. Zet direct een tick klaar met het doel als parent ("Rollback naar tick N: …");
  die start pas na hervatten.
- **Artisan**: `juno:pause {description}`, `juno:resume {description}`, `juno:cancel {tick} {description}`,
  `juno:abandon {tick} {description}`, `juno:rollback {tick} {description}`.

**Getest met echte acties:** schedule met head als parent en claim met `parent_id`; rollback zonder pauze,
met een open tick en naar een `Canceled` tick geweigerd; dubbel pauzeren en hervatten geweigerd;
`schedule()` en claim tijdens pauze leveren niets op; cancel (en dubbel cancel geweigerd); rollback naar
tick 24 gaf tick 27 met parent 24, zodat tick 24 twee kinderen had (25 en 27); die werd pas na hervatten
geclaimd; abandon van een geclaimde tick. `tick.log` bleef leeg. Testticks 25–28 en de juno-events zijn
daarna weggehaald.

---

## 2026-10-23 — 401 met tekst

De token-middleware geeft bij een 401 nu "Ongeldig of ontbrekend token." mee, zodat `tick.log` van de
kernel zegt wat er misging in plaats van een lege melding.

---

## 2026-10-22 — Settings, ticks en de tick-API

**Settings**
- `config/juno.php`: `tick_interval` (30 min, `JUNO_TICK_INTERVAL`) en `tick_abandon_after`
  (25 min, `JUNO_TICK_ABANDON_AFTER`). Daarnaast `api_token_hash` (`JUNO_API_TOKEN_HASH`); dat is
  een geheim, dus geen setting.
- Tabel `settings` (`key` = volledige config-sleutel, `value` als JSON). `AppServiceProvider::boot()`
  laadt alle rijen bij elke bootstrap in `config()`; de database gaat boven `config/` en `.env`.

**Ticks: geen statuskolom, maar events**
- `ticks` bevat alleen `id` en timestamps. De levenscyclus staat in `tick_events` (`tick_id`, `label`,
  `description`, `happened_at`). `happened_at` is wanneer het gebeurde; dat kan van `created_at` afwijken.
- Labels (enum `TickEventLabel`): `Scheduled` → `Claimed` → `Finished` | `Failed` | `Abandoned`.
- `Tick->getStatus()` geeft het laatste event (op `happened_at`, dan `id`). `Tick->set_status([...])`
  bewaakt de volgorde en weigert een event dat vóór de huidige status zou liggen.
  `Tick->isOpen()` zegt of de status `Scheduled` of `Claimed` is.

**Twee crons naast elkaar**
- Scheduler (`lrjsites`, elke minuut): eerst krijgen geclaimde ticks zonder afsluiting na
  `tick_abandon_after` minuten het event `Abandoned`. Daarna komt eens per `tick_interval` minuten
  (vanaf de Unix-epoch) een tick met `Scheduled`, maar alleen als er geen tick open staat.
- `/etc/cron.d/juno-tick` (root): elke minuut, na `sleep 30`, `/home/juno/kernel/tick.php` als `lrjohnst`
  (de kernel), met `kernel/Api.php` als client. Geen logbestand.

**API** (`routes/api.php`, Bearer-token, alleen de SHA-256 staat in `.env`)
- `POST /api/ticks/claim`: `200 {id}` of `204`. Met `lockForUpdate` in een transactie.
- `POST /api/ticks/{tick}/events`: `{label, description, happened_at}`. Alleen `Finished` of `Failed`;
  een verkeerde volgorde geeft `409`.
- HTTP in plaats van een console command: juno mag `.env` niet lezen en geen sudo naar `lrjsites` krijgen.

**Getest**: 401 zonder of met fout token; `204` als er niets klaarstaat; geen tweede tick zolang er
één open staat; de stub haalt een tick op en sluit af met `Finished`; dubbel afsluiten geeft `409`;
`Claimed` posten geeft `422`; opgeven na 25 minuten werkt. Testticks zijn daarna weggehaald.

`settings` opnieuw aangemaakt met de nieuwe kolomvolgorde (`migrate:refresh` op alleen de twee
settings-migraties; de rijen zijn 30 en 25).

Afspraak: elke nieuwe migration begint met `$table->id();` en `$table->timestamps();`.

---

## 2026-10-19 — Bootstrap

- Nieuwe app uit `laravel-template` op `juno.lucasjohnston.nl`, draait op de `lrjsites`-pool
  in `/home/lrjsites/juno.lucasjohnston.nl/`.
- Packages: `lucasjohnston/laravel-sso` en `lrjohnst/laravel-ui-theme`, zonder het chat-package.
- SSO als autonome login-app op cookiedomein `.lucasjohnston.nl`.
- Database `lrj_juno` met eigen gebruiker `lrj_juno@127.0.0.1`.
- Nginx-vhost + Let's Encrypt, HTTP→HTTPS met 308.
- Verder vanille: geen eigen routes, modellen of views.
