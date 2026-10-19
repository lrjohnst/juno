# Laravel Template

Startpunt voor nieuwe Laravel-apps op srv6.phitech.cloud. Inclusief SSO-authenticatie en AI Studio chat-integratie.

## Nieuwe app aanmaken

1. Kopieer deze repo naar de nieuwe locatie:
   ```bash
   cp -a /home/lrjohnst/repositories/laravel-template /var/www/mijn-app.lucasjohnston.nl
   cd /var/www/mijn-app.lucasjohnston.nl
   rm -rf .git
   git init
   ```

2. Pas `.env.example` aan en maak `.env`:
   ```bash
   cp .env.example .env
   # Pas APP_NAME, APP_URL, DB_*, SSO_*, AI_STUDIO_* aan
   php artisan key:generate
   ```

3. Database aanmaken:
   ```sql
   CREATE DATABASE mijn_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'mijn_app'@'127.0.0.1' IDENTIFIED BY '<wachtwoord>';
   GRANT ALL PRIVILEGES ON mijn_app.* TO 'mijn_app'@'127.0.0.1';
   ```

4. Installeren:
   ```bash
   composer install
   php artisan migrate
   npm install
   npm run build
   ```

5. Nginx + SSL:
   ```bash
   # Maak vhost in /etc/nginx/sites-available/
   # Symlink naar sites-enabled
   # certbot --nginx -d mijn-app.lucasjohnston.nl
   ```

## Meegeleverde packages

| Package | Doel |
|---------|------|
| `lucasjohnston/laravel-sso` | Cross-subdomain SSO via personal-planner |
| `lrjohnst/laravel-ai-studio-chat` | AI chat via ai-studio.phitech.cloud |

## Structuur

- `routes/web.php` — minimale route met SSO-middleware
- `config/ai-studio-chat.php` — chat-configuratie (mode, slug, middleware, actions)
- `resources/views/layouts/app.blade.php` — basis layout met nav en flash messages
- `resources/views/home.blade.php` — welkomstpagina
