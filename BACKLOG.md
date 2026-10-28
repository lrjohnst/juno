# Backlog — juno

> App-specifiek backlog. Server-niveau werk staat in `/home/claude/BACKLOG.md`.

## Open

| Item | Notities |
|------|----------|
| Rolprompts | `role_experimentation.md`, `role_doing.md`, `role_reflection.md` zijn nog leeg. |
| Let op: 1 MB per request | Nginx heeft geen `client_max_body_size`, dus een document groter dan ~1 MB komt niet door (413). Nu geen probleem; bij grote documenten de vhost aanpassen. |

## Afgerond

| Item | Notities |
|------|----------|
| ~~Interface: team en inbox~~ | ~~28-10: `/team`, `/team/{agent}`, `/inbox`.~~ |
| ~~Bootstrap~~ | ~~Vanille Laravel uit `laravel-template`, met `laravel-sso` en `laravel-ui-theme`.~~ |
| ~~Settings, ticks en tick-API~~ | ~~22-10: `settings`, `ticks` + `tick_events`, claim en events via de API.~~ |
| ~~Stap 1: tick.log~~ | ~~23-10: `TickLog`, fouten van de API-helper, vangnet in `tick.php`.~~ |
| ~~Stap 2: parent, pauze, rollback~~ | ~~24-10: `parent_id`, `Canceled`, `setStatus()`, `juno_events` + `Juno`, `rollbackTo()`, artisan-commando's.~~ |
| ~~Stap 3: Documents~~ | ~~24-10: `documents` + `document_versions`, API en helpers.~~ |
| ~~Besluiten 24-10 (Lucas)~~ | ~~Voortbouwen op een mislukte tick: ja (head blijft de laatste tick). `claimTick()` hoeft geen `parent_id` terug te geven. Oude ticks krijgen geen parent (de DB wordt gereset). De eerste versie van een document zet Lucas zelf in de DB. Ticks zonder entries tellen mee in het journal-window. Automatische tests en taal/logniveau: nog niet.~~ |
| ~~E-mailhelper~~ | ~~25-10: `POST /api/mail` + `EmailNotification` in de kernel; mail bij een mislukte tick.~~ |
| ~~Stap 4: Journal~~ | ~~24-10: `journal_entries`, `window()`, API en helpers.~~ |
