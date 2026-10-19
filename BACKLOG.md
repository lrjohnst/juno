# Backlog — juno

> App-specifiek backlog. Server-niveau werk staat in `/home/claude/BACKLOG.md`.
> Toegangsregels van de server: `/home/shared/ACCESS.md`.

## Open

| Item | Notities |
|------|----------|
| Ontwerp van de Laravel-juno | Mission, playbook, journal en backlog van de agent komen hierin. Wordt in een aparte sessie uitgewerkt; deze repo is nu nog vanille. |
| API voor `tick.py` | De agent in `/home/juno` houdt alleen een `tick.py` over en haalt zijn werk hier op. Uitwisseling moet via HTTP: de app draait als `lrjsites` en kan niet in `/home/juno` schrijven, en de agent niet hierin. |
| Token voor de agent | Zelfde patroon als de andere agent-geheimen: in `/home/juno`, eigendom van `root`, modus `640`. Juno leest, wijzigt niet. |
| Equivalent voor de missie-verzegeling | In de bestandsversie is `MISSION.sha256` van `root`; dat voorkomt dat de agent zijn eigen opdracht herschrijft. Die bodem moet hier terugkomen, anders verdwijnt hij stil. |
| Geen lange taken in het HTTP-verzoek | Een tick die Claude Code aanroept duurt minuten en loopt vast op de nginx/php-fpm-timeouts. Het werk hoort bij `tick.py`, niet in de request. Patroon: zie `pp-runner.py` bij personal-planner. |

## Afgerond

| Item | Notities |
|------|----------|
| ~~Bootstrap~~ | ~~Vanille Laravel uit `laravel-template`, met `laravel-sso` en `laravel-ui-theme`, zonder het chat-package. Autonome login-app op `.lucasjohnston.nl`.~~ |
