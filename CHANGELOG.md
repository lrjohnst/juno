# Changelog — juno

App-specifieke changelog. Server-niveau werk staat in `/home/claude/CHANGELOG.md`.

---

## 2026-10-19 — Bootstrap

- Nieuwe app uit `laravel-template` op `juno.lucasjohnston.nl`, draait op de `lrjsites`-pool
  in `/home/lrjsites/juno.lucasjohnston.nl/`.
- **Twee van de drie gedeelde packages**: `lucasjohnston/laravel-sso` en `lrjohnst/laravel-ui-theme`.
  Het chat-package (`lrjohnst/laravel-ai-studio-chat`) is er bewust uit — uit `composer.json`,
  uit `composer.lock` en uit `vendor/`. Er stond geen code in de template die het gebruikte.
- **SSO als autonome login-app** op cookiedomein `.lucasjohnston.nl`, met hetzelfde `SSO_SECRET`
  als de andere sites daar. Je logt dus op elke site van dat domein zelf in en bent dan overal in.
- Gebruiker `lrjohnston@protonmail.com` met `sso_id` `708afd91-…`, gelijk aan de andere sites op
  dit cookiedomein. Zonder gelijke `sso_id` werkt het doorlopen van de sessie niet.
- Database `lrj_juno` met eigen gebruiker `lrj_juno@127.0.0.1`.
- Nginx-vhost + Let's Encrypt. HTTP→HTTPS met **308**, niet 301, zodat een POST z'n methode houdt.
- Verder volledig vanille: geen eigen routes, modellen of views.

Het ontwerp van de agent zelf staat nog open; zie `BACKLOG.md`.
