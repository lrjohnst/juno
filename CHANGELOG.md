# Changelog — juno

App-specifieke changelog. Server-niveau werk staat in `/home/claude/CHANGELOG.md`.

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
- `/etc/cron.d/juno-tick` (root): elke minuut, na `sleep 30`, `/home/juno/kernel/tick.php` als juno.

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
