# DuelDesk

Site pro pour organiser et suivre des tournois (style start.gg), en **PHP + JavaScript + SQL**, packagé dans **Docker**.

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

## Config

Variables (voir `.env.example`) :

- `APP_ENV` (`dev` ou `prod`)
- `APP_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

## Roadmap courte

- Joueurs + inscriptions
- Generation du bracket (SE/DE/RR)
- Reporting / validation des scores
- Vue publique partageable par lien
- Integration Discord (check-in, roles)
