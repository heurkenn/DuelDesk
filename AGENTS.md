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
- Auto-advance des BYE:
- winners: uniquement en round 1 (evite d'avancer des matchs "TBD").
- losers: auto-advance quand un slot est "mort" (venant d'un winners BYE) pour eviter de bloquer le bracket.

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
- un compte admin de demo: `dd_admin` / `password123` (role `admin`)

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

## Etape 11 - Lien public (slug) + limites inscriptions

### Ce qui a ete fait
- Route publique partageable: `GET /t/{slug}` (affiche la meme page tournoi).
- Les pages "liste" pointent vers le slug:
- `src/Views/home.php`
- `src/Views/tournaments/index.php`
- Navigation: l'onglet "Tournois" est actif aussi sur `/t/...` (`src/Views/layout.php`).
- Page tournoi:
- boutons "Lien public" + "Copier lien" (JS Clipboard API + fallback prompt).
- affichage etat bracket (genere/en attente).
- Limites inscriptions (solo + team):
- `max_entrants` (cap entrants)
- `signup_closes_at` (date limite)
- lock inscription/retrait si bracket genere (sauf admin)

### Fichiers touches
- Routes: `public/index.php`
- Controller: `src/Controllers/TournamentController.php`
- Repos: `src/Repositories/TournamentRepository.php`, `src/Repositories/TournamentPlayerRepository.php`, `src/Repositories/TournamentTeamRepository.php`
- Signup logic: `src/Controllers/TournamentSignupController.php`, `src/Controllers/TournamentTeamController.php`
- Views: `src/Views/tournaments/show.php`, `src/Views/tournaments/new.php`, `src/Views/admin/tournament.php`
- JS/CSS: `public/assets/js/app.js`, `public/assets/css/app.css`
- Migration: `database/migrations/007_tournament_signup_limits.sql`

## Etape 12 - Scheduling matchs + Best-of par tournoi

### Ce qui a ete fait
- Tournoi:
- champ `best_of_default` (BO par defaut) configurable (creation + admin settings).
- Generation bracket utilise `best_of_default`.
- Matchs:
- admin peut definir/effacer `scheduled_at` par match (datetime-local).
- quand un match est `pending` et qu'on le planifie -> status passe a `scheduled` (et inversement si on efface).
- UI publique: la modale match affiche "Prevu: ..." si `scheduled_at` existe.

### Fichiers touches
- Migration: `database/migrations/008_tournament_best_of.sql`
- Tournoi:
- `src/Controllers/TournamentController.php`, `src/Views/tournaments/new.php`
- `src/Controllers/AdminTournamentController.php`, `src/Views/admin/tournament.php`
- `src/Repositories/TournamentRepository.php`
- Match schedule:
- Route: `public/index.php` (`/admin/tournaments/{id}/matches/{matchId}/schedule`)
- Controller: `src/Controllers/AdminTournamentController.php`
- Repo: `src/Repositories/MatchRepository.php`
- UI: `src/Views/admin/tournament.php`, `src/Views/tournaments/show.php`, `public/assets/js/app.js`, `src/Views/tournaments/match.php`

## Etape 13 - Distinction claire "publique" vs "admin" (UI)

### Ce qui a ete fait
- La route publique `GET /t/{slug}` est en "public view":
- aucun bouton/lien admin n'est affiche (meme si l'utilisateur connecte est admin).
- La route interne `GET /tournaments/{id}` garde les controles admin (ex: lien "Gerer", "Admin bracket", CTA header).

### Fichiers touches
- `src/Controllers/TournamentController.php`
- `src/Views/tournaments/show.php`
- `src/Views/layout.php`
- `TODO.md`

## Etape 14 - Timezone (UTC) + affichage

### Ce qui a ete fait
- Clarification: les dates/heures saisies et affichees sont en `UTC`.
- UI:
- inputs `datetime-local` (creation tournoi + admin settings) affichent un hint "UTC".
- scheduling match: colonne renomme "Horaire (UTC)".
- Pages publiques: affichage tronque a la minute + suffixe `UTC` (fermeture, debut, modal match, page match).

### Fichiers touches
- `src/Views/tournaments/show.php`
- `src/Views/tournaments/new.php`
- `src/Views/admin/tournament.php`
- `src/Views/tournaments/match.php`
- `public/assets/js/app.js`
- `TODO.md`

## Etape 15 - Best-of par match (admin)

### Ce qui a ete fait
- Admin: possibilite de modifier le `best_of` match par match (tant que le match n'est pas `confirmed`).
- UI admin: nouvelle colonne `BO` dans la table des matchs.
- Route: `POST /admin/tournaments/{id}/matches/{matchId}/bestof`.

### Fichiers touches
- `public/index.php`
- `src/Controllers/AdminTournamentController.php`
- `src/Repositories/MatchRepository.php`
- `src/Views/admin/tournament.php`
- `TODO.md`

## Etape 16 - Bracket: arrowheads + drop lines toggle

### Ce qui a ete fait
- Ajout d'arrowheads (SVG markers) sur les arretes winners/losers pour mieux lire le sens (gauche -> droite).
- Drop lines (winners -> losers) cachees par defaut et visibles au hover (highlight).
- Toggle UI "Afficher/Masquer drop lines" dans l'onglet Bracket (double elim).

### Fichiers touches
- `public/assets/js/app.js`
- `public/assets/css/app.css`
- `src/Views/tournaments/show.php`
- `TODO.md`

## Etape 17 - Double elim: GF reset (GF2) + validation BO au reporting

### Ce qui a ete fait
- Double elimination:
- generation cree maintenant 2 matchs `grand`: `GF` (round=1) + `GF2` (round=2).
- Si le gagnant du losers bat le gagnant du winners en `GF`, alors `GF2` est active automatiquement (participants + reset scores).
- Si le gagnant du winners gagne `GF`, alors `GF2` est mise en `void`.
- UI:
- La colonne "Finale" affiche `GF` + `GF2 (Reset)` cote a cote.
- JS: une arrete relie `GF -> GF2`.
- Reporting admin:
- validation des scores par rapport au `best_of` (appliquee quand les scores ressemblent a une serie BO, c-a-d <= BO).

### Fichiers touches
- `src/Services/BracketGenerator.php`
- `src/Controllers/AdminTournamentController.php`
- `src/Repositories/MatchRepository.php`
- `src/Views/tournaments/show.php`
- `src/Views/tournaments/match.php`
- `public/assets/js/app.js`
- `public/assets/css/app.css`
- `TODO.md`

## Etape 18 - Page equipe (publique)

### Ce qui a ete fait
- Nouvelle page equipe: `GET /teams/{id}`.
- Affiche:
- roster (membres + roles + date join)
- join code (visible uniquement pour les membres) + bouton "Copier".
- Page tournoi: noms d'equipes deviennent des liens vers la page equipe.
- JS "Copier": si `data-copy` ne commence pas par `/`, copie le texte brut (utile pour join codes).

### Fichiers touches
- `public/index.php`
- `src/Controllers/TeamController.php`
- `src/Views/teams/show.php`
- `src/Views/tournaments/show.php`
- `public/assets/js/app.js`
- `TODO.md`

## Etape 19 - Round robin (generation + UI)

### Ce qui a ete fait
- DB: ajout du bracket `round_robin` dans `matches.bracket` (migration).
- Generation: `BracketGenerator::generateRoundRobin()` (circle method) cree un calendrier par rounds.
- Admin: generation bracket supporte maintenant `round_robin`.
- UI publique:
- onglet Bracket affiche un "Classement" (W/L + diff) + les rounds avec les matchs cliquables (modal + page match).

### Fichiers touches
- `database/migrations/009_round_robin.sql`
- `src/Services/BracketGenerator.php`
- `src/Controllers/AdminTournamentController.php`
- `src/Views/admin/tournament.php`
- `src/Views/tournaments/show.php`
- `src/Views/tournaments/match.php`
- `public/assets/js/app.js`
- `TODO.md`

## Etape 20 - Equipes: actions capitaine (rename/kick/transfer) + roster lock

### Ce qui a ete fait
- Page equipe: actions disponibles pour capitaine (ou admin):
- renommer l'equipe
- transferer le role capitaine
- kick un membre
- Roster lock:
- kick/transfer bloques si inscriptions fermees ou si le bracket/schedule est genere (sauf admin).

### Fichiers touches
- `public/index.php`
- `src/Controllers/TeamController.php`
- `src/Views/teams/show.php`
- `src/Repositories/TeamRepository.php`
- `src/Repositories/TeamMemberRepository.php`
- `TODO.md`

## Etape 21 - Match reporting (joueurs/capitaines -> reported -> admin confirme/rejette)

### Ce qui a ete fait
- DB: ajout de champs `reported_*` sur `matches` pour stocker un report avant validation admin.
- Route publique (login requis): `POST /tournaments/{id}/matches/{matchId}/report`
- Permissions:
- solo: uniquement les joueurs du match (ou admin)
- team: uniquement les capitaines des equipes du match (ou admin)
- Regles:
- impossible de reporter si match `confirmed` / `void` / incomplet (TBD)
- si le match est deja `reported` par l'autre joueur/capitaine: contre-report possible -> status `disputed`
- Admin:
- page admin tournoi affiche `reported` + "par {username}"
- prefill automatique des inputs avec les valeurs `reported_*`
- bouton "Rejeter" (clear report + retour `pending/scheduled`)
- Dispute flow (counter-report):
- si l'autre joueur/capitaine reporte un score different -> status `disputed`
- admin voit les deux versions (A/B) et peut confirmer A ou B, ou rejeter
- Page match:
- affiche le report (score + winner + qui + quand) quand status `reported`
- affiche les 2 reports quand status `disputed`
- affiche un formulaire "Reporter le score" pour les users eligibles

### Fichiers touches
- Migrations: `database/migrations/010_match_reporting.sql`, `database/migrations/011_match_disputes.sql`
- Routes: `public/index.php`
- Controller: `src/Controllers/MatchReportController.php`, `src/Controllers/AdminTournamentController.php`, `src/Controllers/TournamentController.php`
- Repos: `src/Repositories/MatchRepository.php`, `src/Repositories/TeamMemberRepository.php`
- Views: `src/Views/admin/tournament.php`, `src/Views/tournaments/match.php`
- Seed (admin de demo): `bin/seed_demo.php`
- `TODO.md`

## Etape 22 - Admin: supprimer un tournoi

### Ce qui a ete fait
- Ajout d'un bouton "Supprimer" dans la page admin du tournoi (avec confirmation JS).
- Route admin: `POST /admin/tournaments/{id}/delete`
- Suppression DB: `DELETE FROM tournaments` (CASCADE sur matches / teams / inscriptions via FK).

### Fichiers touches
- Routes: `public/index.php`
- Controller: `src/Controllers/AdminTournamentController.php`
- Repo: `src/Repositories/TournamentRepository.php`
- View: `src/Views/admin/tournament.php`
- `TODO.md`

## Etape 23 - Admin games: resize/crop auto + recherche/tri

### Ce qui a ete fait
- Upload image jeux: PNG (<=6MB), re-encode + crop-fill vers la taille cible (env `GAME_IMAGE_WIDTH`/`GAME_IMAGE_HEIGHT`, default 512x512) via GD.
- Update image: remplace l'image existante + update meta (width/height/mime).
- Admin games: recherche `q` + tri `sort=name|newest`.

### Fichiers touches
- Docker: `docker/php/Dockerfile` (install ext-gd)
- Controller: `src/Controllers/AdminGameController.php`
- Repo: `src/Repositories/GameRepository.php`
- View: `src/Views/admin/games.php`
- Env: `.env.example`
- `TODO.md`

## Etape 24 - Admin tournois: edition config (name/game/format/type/team_size) + reset bracket safe

### Ce qui a ete fait
- Form "Configuration" dans `/admin/tournaments/{id}`.
- Rename + changement de jeu: toujours autorises.
- Format/participant_type/team_size: autorises uniquement si aucun match confirme.
- Si un bracket existe mais aucun match confirme: changement structure -> reset auto (suppression des matchs).
- Protections:
- interdit de changer `participant_type` si des inscriptions existent deja.
- interdit de reduire `team_size` sous la taille max des rosters existants.

### Fichiers touches
- Routes: `public/index.php`
- Controller: `src/Controllers/AdminTournamentController.php`
- Repos: `src/Repositories/TournamentRepository.php`, `src/Repositories/MatchRepository.php`, `src/Repositories/TeamMemberRepository.php`
- View: `src/Views/admin/tournament.php`
- CSS: `public/assets/css/app.css`
- `TODO.md`

## Etape 25 - Audit log

### Ce qui a ete fait
- DB: table `audit_logs` + repo.
- Enregistre les actions principales:
- joueurs/capitaines reportent un score (`match.report`)
- admin confirme (`match.confirm`) / rejette (`match.report.reject`)
- bracket generate/reset
- update config/settings tournoi
- delete tournoi (log global avec `tournament_id` NULL)
- UI: section "Audit" sur la page admin tournoi.

### Fichiers touches
- Migration: `database/migrations/012_audit_logs.sql`
- Repo: `src/Repositories/AuditLogRepository.php`
- Controllers: `src/Controllers/MatchReportController.php`, `src/Controllers/AdminTournamentController.php`
- View: `src/Views/admin/tournament.php`
- `TODO.md`

## Etape 26 - Securite: rate limit login/register

### Ce qui a ete fait
- DB: table `rate_limits`.
- Support: `RateLimit` (key -> hits/reset_at).
- Auth: throttle sur `login` (10 essais / 5min) et `register` (5 essais / 10min) par IP + par username.
- Clear sur succes, hit sur echec.

### Fichiers touches
- Migration: `database/migrations/013_rate_limits.sql`
- Support: `src/Support/RateLimit.php`
- Controller: `src/Controllers/AuthController.php`
- `TODO.md`

## Etape 27 - Pages d'erreur (404/400/403/500)

### Ce qui a ete fait
- Vue `errors/error.php` + `Response` rend maintenant du HTML.
- Exception handler prod rend une page 500 (fallback plain text si render fail).

### Fichiers touches
- View: `src/Views/errors/error.php`
- HTTP: `src/Http/Response.php`
- Bootstrap: `src/Bootstrap.php`
- `TODO.md`

## Etape 28 - Pagination + recherche

### Ce qui a ete fait
- `/tournaments`: recherche `q` + pagination `page` (20/page).
- `/admin/users`: recherche `q` + pagination (40/page).

### Fichiers touches
- Repo: `src/Repositories/TournamentRepository.php`, `src/Repositories/UserRepository.php`
- Controllers: `src/Controllers/TournamentController.php`, `src/Controllers/AdminController.php`
- Views: `src/Views/tournaments/index.php`, `src/Views/admin/users.php`
- `TODO.md`

## Etape 29 - Bracket UX: center on current round + perf CSS

### Ce qui a ete fait
- Onglet Bracket: bouton "Round" -> centre sur le prochain match jouable (non confirme, non TBD).
- Perf CSS: ajout de `contain` sur matrix/grids + `will-change: transform` pour le zoom.

### Fichiers touches
- View: `src/Views/tournaments/show.php`
- JS: `public/assets/js/app.js`
- CSS: `public/assets/css/app.css`
- `TODO.md`

## Etape 30 - Bracket: export SVG + print/PDF

### Ce qui a ete fait
- Onglet Bracket: boutons `SVG` et `PDF`.
- SVG: export via `<foreignObject>` (HTML bracket + SVG lines) dans un fichier `.svg`.
- PDF: active l'onglet bracket + `window.print()` + CSS `@media print` (cache le reste du site).

### Fichiers touches
- View: `src/Views/tournaments/show.php`
- JS: `public/assets/js/app.js`
- CSS: `public/assets/css/app.css`
- `TODO.md`

## Etape 31 - Script dev (docker + db)

### Ce qui a ete fait
- Ajout d'un script unique pour lancer/stopper le stack docker + migrations + seed.
- Commandes: `up`, `down`, `reset`, `ps`, `logs`, `migrate`, `seed`, `sh`, `db`.

### Fichiers touches
- Script: `bin/dev.sh`
- Doc: `README.md`

## Etape 32 - Finale: best-of override + preparation rulesets (CS2)

### Ce qui a ete fait
- Ajout d'un champ optionnel `best_of_final` sur les tournois.
- UI creation tournoi + admin settings: "Best-of (finale)" (defaut = best_of_default).
- Generation bracket: GF/GF2 utilisent `best_of_final` si defini.
- TODO: ajout d'une section "Rulesets (par jeu)" + preparation pick/ban maps CS2 (MAPBAN.GG, map pool, sequence BO3).

### Fichiers touches
- Migration: `database/migrations/014_tournament_best_of_final.sql`
- Controllers: `src/Controllers/TournamentController.php`, `src/Controllers/AdminTournamentController.php`
- Repo: `src/Repositories/TournamentRepository.php`
- Service: `src/Services/BracketGenerator.php`
- Views: `src/Views/tournaments/new.php`, `src/Views/tournaments/show.php`, `src/Views/admin/tournament.php`
- `TODO.md`

## Etape 33 - Discord: liaison compte (OAuth2 identify)

### Ce qui a ete fait
- Page compte: `GET /account` (etat Discord + bouton connecter/deconnecter).
- OAuth2 "identify" Discord:
- `GET /account/discord/connect` -> redirect Discord authorize + state en session.
- `GET /account/discord/callback` -> exchange code -> `/users/@me` -> stocke sur `users`.
- `POST /account/discord/disconnect` -> unlink.
- Champs stockes sur `users`:
- `discord_user_id` (unique)
- `discord_username`, `discord_global_name`, `discord_avatar`
- Integration optionnelle (best-effort via env):
- Auto-role Discord a l'inscription (bot): `DISCORD_BOT_TOKEN`, `DISCORD_GUILD_ID`, `DISCORD_ROLE_ID_PARTICIPANT`
- Annonce webhook lors du `bracket.generate`: `DISCORD_WEBHOOK_URL`

### Fichiers touches
- Routes: `public/index.php`
- Controller: `src/Controllers/AccountController.php`
- View: `src/Views/account/index.php`
- Layout nav: `src/Views/layout.php`
- Repo: `src/Repositories/UserRepository.php`
- Support: `src/Support/Discord.php`
- Signup: `src/Controllers/TournamentSignupController.php`, `src/Controllers/TournamentTeamController.php`
- Admin bracket: `src/Controllers/AdminTournamentController.php`
- Migration: `database/migrations/015_discord_users.sql`
- Env: `.env.example`, `docker-compose.yml`
- `TODO.md`

## Etape 34 - Rulesets: Pick/Ban maps (CS2/Valorant) + blocage reporting

### Ce qui a ete fait
- Tournois: champ `ruleset_json` pour activer un ruleset "map veto".
- Tournois: champ `pickban_start_mode` pour definir comment on decide qui est "Team A" (starter):
- `coin_toss`: pile ou face -> le vainqueur est Team A et commence
- `higher_seed`: le higher seed choisit d'etre Team A ou Team B
- Admin: ajout d'un dashboard `Rulesets` (CRUD) pour creer/modifier/supprimer des rulesets sans editer du JSON:
- `GET /admin/rulesets` (liste + filtre par jeu)
- `GET /admin/rulesets/new` + `POST /admin/rulesets` (creation)
- `GET /admin/rulesets/{id}` + `POST /admin/rulesets/{id}` (edition)
- `POST /admin/rulesets/{id}/delete` (suppression)
- Builder:
- choix du jeu
- pool de maps (key + nom)
- sequences par BO (BO1/BO3/BO5): ordre ban/pick + acteur (`starter`/`other`/`alternate`) ; decider ajoute automatiquement
- Admin tournoi: section "Ruleset (Pick/Ban)" simplifiee:
- selection d'une source (aucun / templates `cs2`/`valorant` / ruleset sauvegarde) sans champ JSON.
- Match: pick/ban obligatoire avant de reporter le score:
- pile ou face (le participant qui lance choisit Pile/Face) -> determine Team A/Team B (slots A/B)
- ou "higher seed" (le higher seed choisit Team A/B)
- sequence ban/pick par BO (BO1/BO3/BO5) + decider auto
- Choix du cote (attaque/defense) obligatoire apres chaque map pick + apres le decider:
- map pick: l'autre slot choisit le cote
- decider: Team B choisit le cote (process CS2)
- verrouillage automatique a la fin
- UI: section Pick/Ban sur la page match + grille de maps + historique.
- UX:
- icone `!` sur les matchs du bracket quand le pick/ban n'est pas encore verrouille
- page match: actions pick/ban/report en AJAX (pas de refresh complet)

### Fichiers touches
- DB: `database/migrations/001_schema.sql`, `database/migrations/002_rulesets.sql`, `database/migrations/003_pickban_sides.sql`, `database/migrations/004_tournament_pickban_start_mode.sql`
- Routes: `public/index.php`
- Controllers: `src/Controllers/PickBanController.php`, `src/Controllers/AdminTournamentController.php`, `src/Controllers/AdminRulesetController.php`, `src/Controllers/TournamentController.php`, `src/Controllers/MatchReportController.php`
- Service: `src/Services/PickBanEngine.php`
- Repos: `src/Repositories/PickBanRepository.php`, `src/Repositories/RulesetRepository.php`, `src/Repositories/TournamentRepository.php`
- Views: `src/Views/admin/tournament.php`, `src/Views/admin/rulesets.php`, `src/Views/admin/ruleset_edit.php`, `src/Views/tournaments/match.php`, `src/Views/tournaments/show.php`
- CSS: `public/assets/css/app.css`
- JS: `public/assets/js/app.js`

## Etape 35 - Infra: squash migrations DB

### Ce qui a ete fait
- Les migrations SQL ont ete "squash" en un schema unique: `database/migrations/001_schema.sql`.
- Les anciennes migrations incrementales (ALTER TABLE, etc.) ont ete supprimees pour reduire le bruit.
- `bin/migrate.php` detecte une DB "legacy" (schema_migrations avec versions manquantes) et demande un reset des volumes.

### Fichiers touches
- Schema: `database/migrations/001_schema.sql`
- Migrate runner: `bin/migrate.php`
- Doc: `README.md`

## Etape 36 - LAN: evenements (mega-tournois)

### Ce qui a ete fait
- Ajout d'un concept "LAN" (evenement) qui regroupe plusieurs tournois.
- DB:
- table `lan_events`
- `tournaments.lan_event_id` (FK `ON DELETE SET NULL`)
- Public:
- `GET /lan` (listing des LAN)
- `GET /lan/{slug}` (page LAN + liste des tournois inclus)
- Admin:
- CRUD LAN: `GET /admin/lan`, `GET /admin/lan/new`, `POST /admin/lan`, `GET /admin/lan/{id}`, `POST /admin/lan/{id}`, `POST /admin/lan/{id}/delete`
- rattacher/detacher un tournoi a un LAN depuis l'admin du LAN
- Creation tournoi: select "LAN (optionnel)" + preselect via `?lan_event_id=...`
- UI:
- navigation "LAN"
- la page tournoi affiche le LAN associe (pill + lien)
- correction UX: les listes (home + `/tournaments`) envoient l'admin vers la vue interne `/tournaments/{id}` (la vue publique `/t/{slug}` reste sans boutons admin)

### Fichiers touches
- Migration: `database/migrations/005_lan_events.sql` (et schema `database/migrations/001_schema.sql`)
- Routes: `public/index.php`
- Controllers: `src/Controllers/LanEventController.php`, `src/Controllers/AdminLanEventController.php`, `src/Controllers/TournamentController.php`, `src/Controllers/AdminTournamentController.php`
- Repos: `src/Repositories/LanEventRepository.php`, `src/Repositories/TournamentRepository.php`
- Views: `src/Views/lan/index.php`, `src/Views/lan/show.php`, `src/Views/admin/lan_events.php`, `src/Views/admin/lan_event_edit.php`, `src/Views/tournaments/new.php`, `src/Views/tournaments/show.php`, `src/Views/admin/tournament.php`, `src/Views/layout.php`, `src/Views/home.php`, `src/Views/tournaments/index.php`
