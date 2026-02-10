# DuelDesk - LLM Handoff

Ce fichier est fait pour qu'un autre LLM puisse reprendre le travail rapidement.

## Etape 0 - Objectif + Stack

### Ce qui a ete fait
- Projet "DuelDesk": organiser et suivre des tournois (inspire start.gg).
- Stack: PHP (routing + controllers + views) + JS (UI bracket) + SQL (MariaDB).
- Docker compose: `nginx` (8080) + `php` + `db` (MariaDB 11.x).

### Comment lancer (dev)
- `docker compose up -d --build`
- `docker compose exec php php bin/migrate.php`
- Option demo: `docker compose exec -T php php bin/seed_demo.php`

### Points de repere
- Routes: `public/index.php`
- Controllers: `src/Controllers/`
- Views: `src/Views/`
- CSS/JS: `public/assets/css/app.css`, `public/assets/js/app.js`
- Migrations SQL: `database/migrations/`
- Docker: `docker-compose.yml`, `docker/`

## Etape 1 - UI / DA (sombre, style pro)

### Ce qui a ete fait
- Design system sombre (surfaces translucides, cards, pills, boutons).
- Footer fixe en bas (layout `body` en colonne + espace reserve).
- Police plus "gaming": body `Oxanium`, titres/accents `Silkscreen`.

### Fichiers touches
- `public/assets/css/app.css`
- `src/Views/layout.php`

## Etape 2 - Auth username-only (pas d'email)

### Ce qui a ete fait
- Inscription/connexion basees sur `username` + `password`.
- Premier compte cree devient admin si aucun admin n'existe.
- CSRF tokens + rotation.

### Fichiers touches
- `src/Controllers/AuthController.php`
- `src/Support/Auth.php`, `src/Support/Csrf.php`
- `database/migrations/002_auth.sql`, `database/migrations/003_username_only.sql`

## Etape 3 - Admin (dashboard + roles + games CRUD)

### Ce qui a ete fait
- Dashboard admin: acces uniquement admins.
- Gestion roles users (user/admin).
- Catalogue des jeux:
- Ajout (upload image au format unique).
- Modification (rename + remplacer image optionnel).
- Suppression (DB + suppression du fichier image si dans `/public/uploads/games/`).

### Fichiers touches
- `src/Controllers/AdminController.php`
- `src/Controllers/AdminGameController.php`
- `src/Repositories/UserRepository.php`, `src/Repositories/GameRepository.php`
- `src/Views/admin/index.php`, `src/Views/admin/users.php`, `src/Views/admin/games.php`, `src/Views/admin/game_edit.php`
- `database/migrations/004_games.sql`

## Etape 4 - Tournois (creation + listing + permissions)

### Ce qui a ete fait
- Listing des tournois + creation (admin seulement pour creer).
- Tournoi supporte:
- `format`: `single_elim`, `double_elim` (round robin en placeholder).
- `participant_type`: `solo` ou `team`
- `team_size` si `team` (ex: 2v2, 5v5 CS2).

### Fichiers touches
- `src/Controllers/TournamentController.php`
- `src/Repositories/TournamentRepository.php`
- Migrations: `database/migrations/001_init.sql`, `database/migrations/006_teams.sql`

## Etape 5 - Inscriptions (solo + equipes)

### Ce qui a ete fait
- Solo: un user peut s'inscrire/se desinscrire sur `/tournaments/{id}`.
- Equipes:
- un user cree une equipe (devient capitaine) ou rejoint via `join_code`.
- limite de taille d'equipe respectee (`team_size`).
- quitter une equipe (si vide -> equipe supprimee, si capitaine -> transfert auto au plus ancien).

### Fichiers touches
- `src/Controllers/TournamentSignupController.php`
- `src/Controllers/TournamentTeamController.php`
- `src/Repositories/PlayerRepository.php`, `src/Repositories/TeamRepository.php`, `src/Repositories/TeamMemberRepository.php`
- `src/Views/tournaments/show.php`
- Migration: `database/migrations/006_teams.sql`

## Etape 6 - Brackets (generation + propagation)

### Ce qui a ete fait
- Generation `single_elim` et `double_elim` (cree tous les matchs upfront).
- Double elim:
- winners bracket, losers bracket, grand final.
- propagation winners/losers lors du reporting admin.
- Auto-advance des BYE dans winners uniquement.

### Fichiers touches
- Generation: `src/Services/BracketGenerator.php`
- Reporting + propagation: `src/Controllers/AdminTournamentController.php`
- Persistence: `src/Repositories/MatchRepository.php`

## Etape 7 - Bracket UI (arbre gauche->droite) + modal + page match

### Ce qui a ete fait
- Vue bracket en "tree" horizontal:
- Simple elim: un seul arbre.
- Double elim: 2 arbres (gagnants + perdants) + finale.
- Tous les noeuds existent meme si match TBD.
- Lignes SVG dessinees en JS (pas de recalcul au scroll).
- Hover/focus: highlight des connexions sortantes + match cible.
- Clique un match:
- ouvre une modale (lisible, opaque).
- lien "Ouvrir" vers une page match detaillee.

### Correctifs UI ajoutés
- Page tournoi: sections en onglets (Inscriptions/Bracket/Details), Inscriptions par defaut.
- Lignes winners: endpoint ajuste pour ne pas rentrer sous la card.
- Lignes drop (winners->losers): beaucoup moins visibles hors highlight.
- Plus d'espace vertical entre matchs.
- Tag match (`W1#1`) repositionne pour eviter chevauchement avec scores.

### Fichiers touches
- `src/Views/tournaments/show.php`
- `public/assets/js/app.js`
- `public/assets/css/app.css`
- Route + controller match page: `public/index.php`, `src/Controllers/TournamentController.php`
- View match page: `src/Views/tournaments/match.php`
- Repo detail match: `src/Repositories/MatchRepository.php`

## Etape 8 - Donnees de test (seed demo)

### Ce qui a ete fait
- Script de seed idempotent qui cree:
- jeux: Street Fighter 6 + Counter-Strike 2 (images PNG solides au bon format)
- tournois:
- solo DE (8 joueurs)
- team DE 2v2 (8 equipes)
- CS2 DE 5v5 (4 equipes)
- users de demo avec le password `password123`.

### Commande
- `docker compose exec -T php php bin/seed_demo.php`

### Fichier touche
- `bin/seed_demo.php`

## Etape 9 - Roadmap / reste a faire

### Ce qui a ete fait
- Liste des idees + TODO (MVP -> bonus) dans `TODO.md`.

### Fichier
- `TODO.md`


## Etape 10 - Renommage du dossier

### Ce qui a ete fait
- Le dossier projet a ete renomme de `/home/kali/gcc` vers `/home/kali/DuelDesk`.

### Notes Docker compose
- Si des containers etaient deja lances avant le rename, ils peuvent etre sous le project name `gcc` (ex: `gcc-nginx-1`).
- Pour les gerer depuis n'importe quel dossier:
- `docker compose -p gcc ps`
- `docker compose -p gcc down`
