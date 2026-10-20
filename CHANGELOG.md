# Changelog — juno

App-specifieke changelog. Server-niveau werk staat in `/home/claude/CHANGELOG.md`.

---

## 2026-10-19 — Bootstrap

- Nieuwe app uit `laravel-template` op `juno.lucasjohnston.nl`, draait op de `lrjsites`-pool
  in `/home/lrjsites/juno.lucasjohnston.nl/`.
- Packages: `lucasjohnston/laravel-sso` en `lrjohnst/laravel-ui-theme`, zonder het chat-package.
- SSO als autonome login-app op cookiedomein `.lucasjohnston.nl`.
- Database `lrj_juno` met eigen gebruiker `lrj_juno@127.0.0.1`.
- Nginx-vhost + Let's Encrypt, HTTP→HTTPS met 308.
- Verder vanille: geen eigen routes, modellen of views.
