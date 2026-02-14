# DuelDesk

Site pro pour organiser et suivre des tournois (style start.gg), en **PHP + JavaScript + SQL**, packagé dans **Docker**.

## Features (etat actuel)

- Tournois: `single_elim`, `double_elim` (GF reset), `round_robin`
- Inscriptions: solo + equipes (teams + join code, actions capitaine)
- Bracket UI: zoom/pan, center round, export SVG/PDF
- Match workflow: report joueurs/capitaines -> validation admin -> disputes + audit log
- Rulesets par jeu + pick/ban maps (CS2 + Valorant) avec blocage reporting tant que pick/ban pas lock
- LAN events: pages `/lan` + `/lan/{slug}` + rattacher/detacher des tournois (tournois LAN caches du listing global)
- Discord:
  - OAuth2 `identify` (liaison compte + avatar)
  - Bot Docker (commandes slash ephemeres `/ping`, `/report`, `/pickban`, `/cancel`) + reminders pick/ban (DM)

## Demarrage (dev)

```bash
docker compose up -d --build
docker compose exec php php bin/migrate.php
```

Si tu avais deja une base de donnees (ancien volume Docker) et que les migrations ont ete "squash", fais un reset:

```bash
bin/dev.sh reset
bin/dev.sh up
```

Ou en 1 commande:

```bash
bin/dev.sh up
```

Seed demo (optionnel):

```bash
bin/dev.sh up --seed
```

Puis ouvre:

- `http://localhost:8080`

Smoke test (dev):

```bash
sudo -E RESET_DB=1 ./test.sh
```

## Auth / Admin

- `/register` : inscription (le **premier** compte cree devient **admin** automatiquement si aucun admin n'existe)
- `/login` : connexion
- `/admin` : dashboard admin
- `/admin/users` : gestion des roles (admin/user)
- `/admin/games` : catalogue des jeux (upload image PNG 512x512 par defaut)

## Services

- `nginx` (port 8080)
- `php-fpm` (app)
- `mariadb` (port 3307 sur la machine, 3306 dans le réseau docker)
- `discord-bot` (bot Discord)

## Config

Variables (voir `.env.example`) :

- `APP_ENV` (`dev` ou `prod`)
- `APP_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- Discord: `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI`
- Bot: `DISCORD_BOT_TOKEN`, `DISCORD_GUILD_ID`, `BOT_API_TOKEN`, `BOT_PUBLIC_URL`

## Prod (VPS)

Voir `prod.md`:
- `docker-compose.prod.yml` (Caddy TLS + pas d'exposition DB/nginx)
- variables `.env` a changer (APP_URL, Discord redirect, BOT_PUBLIC_URL, secrets)
