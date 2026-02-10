# TODO - DuelDesk

Ce fichier liste ce qu'il reste a faire (MVP -> features) + des idees a ajouter.

## MVP (prioritaire)

- [x] Vue publique partageable par lien (slug)
- [x] Distinction claire "publique" vs "admin" (pas de boutons admin visibles cote public)
- [ ] Limites inscriptions:
- [x] `max_entrants` (cap)
- [x] date/heure de fermeture des inscriptions
- [x] blocage inscription/retrait quand le bracket est genere (ou bien workflow "void")
- [ ] Page tournoi:
- [x] afficher l'etat "bracket genere" / "en attente"
- [x] bouton "copier le lien" (share)
- [ ] Gestion des matchs:
- [x] statut `scheduled_at` (planification admin)
- [x] timezone (clarifier UTC vs local, affichage)
- [x] definir `best_of` (par tournoi)
- [x] best_of par match (admin) (optionnel)

## Brackets (fonctionnel + visuel)

- [x] Round robin (backend + UI)
- [ ] Double elimination:
- [x] grand final "reset" (GF2) si le gagnant du losers bat le gagnant du winners
- [ ] option "petite finale" (3e place)
- [ ] Ameliorer les arretes SVG:
- [x] arrowheads (sens de lecture)
- [ ] routage plus lisible du winners -> losers (eviter les croisements)
- [x] toggle "masquer les drop lines" (ou afficher uniquement au hover)
- [ ] Zoom/pan du bracket (utile sur mobile + gros tournois)
- [ ] Boutons "fit to view" / "center on current round"
- [ ] Export image/PDF du bracket

## Match workflow (reporting)

- [x] Permettre aux joueurs/capitaines de reporter un score (etat `reported`)
- [x] Validation admin (prefill + confirmer/rejeter) (etat `confirmed`)
- [ ] Dispute flow: permettre un "counter-report" + statut `disputed` + resolution admin
- [ ] Regles BO:
- [x] verifier qu'un score est coherent (ex: BO3 -> 2 max wins)
- [ ] gerer les `BYE` proprement dans tous les cas (SE/DE)
- [ ] Historique des actions (audit log): qui a confirme quoi, quand

## Equipes

- [x] Page equipe (publique) avec:
- [x] membres + roles (capitaine/member)
- [x] bouton "copier join code"
- [ ] Capacites capitaine:
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
- [ ] edit name / game / format / participant_type / team_size (si aucun match confirme)
- [ ] supprimer un tournoi (avec confirmation)
- [ ] Gestion games:
- [ ] resize/crop auto des images (forcer le format unique)
- [ ] recherche + tri

## Performance / UX

- [ ] Tournoi page: optimiser le rendu du bracket pour gros volumes:
- [ ] limiter box-shadows couteux dans la grille
- [ ] `contain`/`content-visibility` sur les grosses zones
- [ ] eviter toute logique JS au scroll
- [ ] Skeleton loading / placeholders
- [ ] Pagination / recherche sur:
- [ ] liste des tournois
- [ ] admin users
- [ ] admin matches
- [ ] Accessibilite:
- [ ] navigation clavier bracket (focus + highlight)
- [ ] contrast checks (WCAG) sur les pills/lines

## Securite

- [ ] Rate limit login / register
- [ ] Politique mot de passe (min, blacklist)
- [ ] Cookies session: `Secure`, `HttpOnly`, `SameSite`
- [ ] Upload images:
- [ ] verifier mime + taille max
- [ ] re-encoder (png/webp) pour eviter les payloads bizarres
- [ ] stocker hors webroot + servir via nginx
- [ ] Pages d'erreur propres (404/500) en prod

## Discord (idee start.gg-like)

- [ ] Lier un compte a Discord (OAuth2)
- [ ] Stocker `discord_user_id` sur `users` (plutot que `players`)
- [ ] Bot/Integration:
- [ ] auto-role sur inscription
- [ ] check-in via bouton/commande
- [ ] annonces (debut tournoi, next match ping)
- [ ] importer pseudo/avatars (optionnel)

## DevOps / Deploiement VPS

- [ ] docker compose "prod" (nginx + php + db) + env vars propres
- [ ] HTTPS (Caddy/Traefik) + reverse proxy
- [ ] Backups automatiques DB + restore procedure
- [ ] Healthchecks + restart policies
- [ ] Logs centralises (nginx/php)

## Tests / Qualite

- [ ] Tests unitaires:
- [ ] generation bracket (SE/DE)
- [ ] propagation winners/losers
- [ ] contraintes teams (team_size)
- [ ] Smoke tests HTTP (routes principales)
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
