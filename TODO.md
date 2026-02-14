# TODO - DuelDesk

Ce fichier liste ce qu'il reste a faire (MVP -> features) + des idees a ajouter.

## MVP (prioritaire)

- [x] Vue publique partageable par lien (slug)
- [x] Distinction claire "publique" vs "admin" (pas de boutons admin visibles cote public)
- [x] Limites inscriptions:
- [x] `max_entrants` (cap)
- [x] date/heure de fermeture des inscriptions
- [x] blocage inscription/retrait quand le bracket est genere (ou bien workflow "void")
- [x] Page tournoi:
- [x] afficher l'etat "bracket genere" / "en attente"
- [x] bouton "copier le lien" (share)
- [x] Gestion des matchs:
- [x] statut `scheduled_at` (planification admin)
- [x] timezone (clarifier UTC vs local, affichage)
- [x] definir `best_of` (par tournoi)
- [x] best_of par match (admin) (optionnel)

## Brackets (fonctionnel + visuel)

- [x] Round robin (backend + UI)
- [x] Double elimination:
- [x] grand final "reset" (GF2) si le gagnant du losers bat le gagnant du winners
- [ ] option "petite finale" (3e place)
- [ ] Ameliorer les arretes SVG:
- [x] arrowheads (sens de lecture)
- [ ] routage plus lisible du winners -> losers (eviter les croisements)
- [x] toggle "masquer les drop lines" (ou afficher uniquement au hover)
- [x] Zoom/pan du bracket (utile sur mobile + gros tournois)
- [x] Boutons "fit to view" / "center on current round"
- [x] Export image/PDF du bracket

## Match workflow (reporting)

- [x] Permettre aux joueurs/capitaines de reporter un score (etat `reported`)
- [x] Validation admin (prefill + confirmer/rejeter) (etat `confirmed`)
- [x] Dispute flow: permettre un "counter-report" + statut `disputed` + resolution admin
- [x] Regles BO:
- [x] verifier qu'un score est coherent (ex: BO3 -> 2 max wins)
- [x] gerer les `BYE` proprement dans tous les cas (SE/DE)
- [x] Historique des actions (audit log): qui a confirme quoi, quand

## Rulesets (par jeu)

- [x] Finale: permettre un best-of different (ex: BO5 en finale) configurable a la creation + editable admin
- [x] Pick/Ban maps (framework):
- [x] Admin: CRUD rulesets + builder (pool + sequences) (sans JSON)
- [x] Templates: CS2 + Valorant
- [x] stocker un ruleset par tournoi + snapshot par match
- [x] UI pick/ban sur la page match (ban/pick/decider) + historique
- [x] Choix du cote (attaque/defense) apres chaque map pick + apres le decider
- [x] Blocage reporting tant que le pick/ban n'est pas verrouille
- [x] UX: icone `!` sur les matchs du bracket si pick/ban requis + polling (pas besoin de refresh)
- [x] Parametre tournoi: `pickban_start_mode` (coin_toss / higher_seed)
- [ ] liaison optionnelle a un outil externe (ex: MAPBAN.GG) via URL + stockage du lien dans le match

### CS2 (premier ruleset a implementer)

- [x] Template CS2: map pool (7 maps)
- [x] DUST 2
- [x] ANCIENT
- [x] ANUBIS
- [x] INFERNO
- [x] MIRAGE
- [x] NUKE
- [x] TRAIN
- [x] BO1 pick/ban: A remove 2, B remove 3, A remove 1, decider
- [x] BO3 pick/ban: BAN - BAN - PICK - PICK - BAN - BAN + decider
- [x] Side choice: apres chaque pick, l'autre choisit le cote; decider = Team B choisit
- [ ] Finals: selection des maps via MAPBAN.GG (optionnel)

## LAN (mega-tournois)

- [x] Evenements LAN: pages publiques `/lan` + `/lan/{slug}`
- [x] Admin LAN: CRUD + rattacher/detacher des tournois

## Infra (dev)

- [x] Migrations DB "squash": `database/migrations/001_schema.sql` + runner `bin/migrate.php` (detecte DB legacy)

## Equipes

- [x] Page equipe (publique) avec:
- [x] membres + roles (capitaine/member)
- [x] bouton "copier join code"
- [x] Capacites capitaine:
- [x] rename equipe
- [x] kick/uninvite membre
- [x] transfer captain
- [x] lock roster a partir d'un certain moment (ex: bracket genere)
- [ ] Optional: remplacements (sub) / roster "lineup"

## Admin / Roles

- [ ] Roles plus fins:
- [ ] `super_admin` (site)
- [ ] `organizer` (par tournoi)
- [ ] `moderator` (par tournoi)
- [ ] Tournois:
- [x] edit name / game / format / participant_type / team_size (si aucun match confirme)
- [x] supprimer un tournoi (avec confirmation)
- [ ] Gestion games:
- [x] resize/crop auto des images (forcer le format unique)
- [x] recherche + tri

## Performance / UX

- [ ] Tournoi page: optimiser le rendu du bracket pour gros volumes:
- [ ] limiter box-shadows couteux dans la grille
- [x] `contain`/`content-visibility` sur les grosses zones
- [x] eviter toute logique JS au scroll
- [ ] Skeleton loading / placeholders
- [ ] Pagination / recherche sur:
- [x] liste des tournois
- [x] admin users
- [ ] admin matches
- [ ] Accessibilite:
- [ ] navigation clavier bracket (focus + highlight)
- [ ] contrast checks (WCAG) sur les pills/lines

## Securite

- [x] Rate limit login / register
- [ ] Politique mot de passe (min, blacklist)
- [x] Cookies session: `Secure`, `HttpOnly`, `SameSite`
- [ ] Upload images:
- [x] verifier mime + taille max
- [x] re-encoder (png/webp) pour eviter les payloads bizarres
- [ ] stocker hors webroot + servir via nginx
- [x] Pages d'erreur propres (404/500) en prod

## Discord (idee start.gg-like)

- [x] Lier un compte a Discord (OAuth2)
- [x] Stocker `discord_user_id` sur `users` (plutot que `players`)
- [ ] Bot/Integration:
- [x] auto-role sur inscription (env bot + `discord_user_id` user)
- [ ] check-in via bouton/commande
- [x] annonces: webhook "bracket genere" (env `DISCORD_WEBHOOK_URL`)
- [ ] annonces: ping "next match" / debut tournoi
- [x] importer pseudo/avatars (optionnel)

## DevOps / Deploiement VPS

- [x] docker compose "prod" (nginx + php + db) + env vars propres
- [x] HTTPS (Caddy/Traefik) + reverse proxy
- [ ] Backups automatiques DB + restore procedure
- [ ] Healthchecks + restart policies
- [ ] Logs centralises (nginx/php)

## Tests / Qualite

- [ ] Tests unitaires:
- [ ] generation bracket (SE/DE)
- [ ] propagation winners/losers
- [ ] contraintes teams (team_size)
- [x] Smoke tests HTTP (routes principales)
- [ ] Linting/formatting PHP (phpcs/phpstan) + CI

## Idees bonus (plus tard)

- [ ] Profils (avatar, bio, liens)
- [ ] Classements / ELO / saisons
- [ ] Paiement/fees (Stripe) + verification inscription
- [ ] Streams:
- [ ] liens Twitch/YouTube par match
- [ ] overlay "match card" pour OBS
- [ ] API JSON (read-only) pour integrations
- [ ] Multi-stage:
- [ ] pools -> bracket final
- [ ] swiss -> top cut
- [ ] Notifications email (optionnel, si on ajoute email un jour)
