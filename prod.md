# Prod Deployment (VPS)

Ce fichier explique comment deployer DuelDesk en production (nom de domaine, TLS, Discord OAuth, bot).

## Architecture recommandee

- `caddy` (public) : termine TLS + HSTS, reverse-proxy vers
- `nginx` (interne) : sert `/public` + PHP via `php-fpm`
- `php` (interne) : app
- `db` (interne) : MariaDB (non exposee)
- `discord-bot` (interne) : bot + commandes slash + polling pick/ban

## Fichiers ajoutes

- `docker-compose.prod.yml` : override compose prod (Caddy + pas de ports DB/nginx)
- `docker/caddy/Caddyfile` : config Caddy TLS -> nginx

## Variables a regler (.env)

Copie `.env.example` vers `.env` sur le VPS puis modifie:

### App

- `APP_ENV=prod`
- `APP_URL=https://TON-DOMAINE` (ex: `https://dueldesk.example.com`)

### DB

- `DB_HOST=db`
- `DB_PORT=3306`
- `DB_NAME=dueldesk`
- `DB_USER=dueldesk`
- `DB_PASSWORD=...` (mot de passe fort)
- `DB_ROOT_PASSWORD=...` (mot de passe fort)

Important:
- en prod, ne publie pas le port DB (c'est fait via `docker-compose.prod.yml`).

### Caddy / Domaine

- `APP_DOMAIN=TON-DOMAINE` (ex: `dueldesk.example.com`)

### Discord OAuth (connexion compte)

Dans le portail Discord Developer:
- OAuth2 -> Redirects: ajoute EXACTEMENT:
  - `https://TON-DOMAINE/account/discord/callback`

Dans `.env`:
- `DISCORD_CLIENT_ID=...`
- `DISCORD_CLIENT_SECRET=...`
- `DISCORD_REDIRECT_URI=https://TON-DOMAINE/account/discord/callback`

### Discord Bot

Dans `.env`:
- `DISCORD_BOT_TOKEN=...`
- `DISCORD_GUILD_ID=...`
- `DISCORD_ROLE_ID_PARTICIPANT=...` (optionnel si auto-role)

URLs bot:
- `BOT_INTERNAL_URL=http://nginx` (reste comme ca en prod; c'est l'URL interne docker)
- `BOT_PUBLIC_URL=https://TON-DOMAINE` (IMPORTANT: pour les liens envoyes par le bot)

### Bot API Token (obligatoire)

- `BOT_API_TOKEN=...` (secret aleatoire, long, non devinable)

Ce token est utilise par:
- le service `discord-bot` pour appeler `GET/POST /api/bot/...`
- le service `php` pour valider l'Authorization Bearer

### Discord webhook (optionnel)

- `DISCORD_WEBHOOK_URL=...`

## Lancer en prod

Sur le VPS:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose exec php php bin/migrate.php
```

Ou via le helper:

```bash
bin/prod.sh up
```

Optionnel demo:

```bash
docker compose exec -T php php bin/seed_demo.php
```

## Ports / Firewall

- Ouvrir: `80/tcp`, `443/tcp`
- Fermer: DB (`3306`) et tout le reste (ne pas exposer)

## Discord: commandes slash

Le bot deploye les commandes slash **dans le serveur** `DISCORD_GUILD_ID`.
Si tu changes `DISCORD_GUILD_ID` ou le token, redemarre le service:

```bash
docker compose up -d --build discord-bot
```

## Checklist securite prod

- HTTPS OK + HSTS actif (Caddyfile le met).
- `APP_ENV=prod`
- `BOT_API_TOKEN` secret fort
- DB non exposee
- Uploads hors webroot: les fichiers sont stockes dans `storage/uploads` et servis via `/uploads/...` par nginx.
- Sauvegardes DB (a automatiser)
- CSP: `script-src 'self'` (pas d'inline scripts). Il reste `style-src 'unsafe-inline'` tant qu'on utilise des `style=""` inline.
