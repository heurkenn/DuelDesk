<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\MatchRepository;
use DuelDesk\Repositories\PlayerRepository;
use DuelDesk\Repositories\TournamentPlayerRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Discord;
use DuelDesk\Support\Flash;

final class TournamentSignupController
{
    /** @param array<string, string> $params */
    public function signup(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $lanEventId = (int)($t['lan_event_id'] ?? 0);
        if ($lanEventId > 0) {
            $le = (new LanEventRepository())->findById($lanEventId);
            $lanSlug = is_array($le) ? (string)($le['slug'] ?? '') : '';
            Flash::set('error', "Ce tournoi fait partie d'un LAN: inscris-toi au LAN.");
            Response::redirect($lanSlug !== '' ? ('/lan/' . $lanSlug) : '/lan');
        }

        if (($t['participant_type'] ?? 'solo') === 'team') {
            Flash::set('error', 'Tournoi en equipe: cree ou rejoins une equipe.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $me = Auth::user();
        if (!Auth::isAdmin()) {
            $discordUserId = is_array($me) ? trim((string)($me['discord_user_id'] ?? '')) : '';
            if ($discordUserId === '') {
                Flash::set('error', 'Connexion Discord requise: lie ton compte avant de t\'inscrire a un tournoi.');
                Response::redirect('/account');
            }
        }

        $status = (string)($t['status'] ?? 'draft');
        $isOpen = in_array($status, ['published', 'running'], true);

        if (!$isOpen && !Auth::isAdmin()) {
            Flash::set('error', 'Inscriptions fermees.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $mRepo = new MatchRepository();
        if (!Auth::isAdmin() && $mRepo->countForTournament($tournamentId) > 0) {
            Flash::set('error', 'Inscriptions verrouillees (bracket deja genere).');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $signupClosesAt = $t['signup_closes_at'] ?? null;
        if (!Auth::isAdmin() && is_string($signupClosesAt) && $signupClosesAt !== '') {
            $ts = strtotime($signupClosesAt);
            if ($ts !== false && $ts <= time()) {
                Flash::set('error', 'Inscriptions fermees (date limite depassee).');
                Response::redirect('/tournaments/' . $tournamentId);
            }
        }

        $maxEntrants = $t['max_entrants'] ?? null;
        if (!Auth::isAdmin() && $maxEntrants !== null) {
            $max = (int)$maxEntrants;
            if ($max > 0) {
                $tpRepo = new TournamentPlayerRepository();
                if ($tpRepo->countForTournament($tournamentId) >= $max) {
                    Flash::set('error', 'Tournoi complet.');
                    Response::redirect('/tournaments/' . $tournamentId);
                }
            }
        }

        $meId = Auth::id();
        if ($meId === null || $me === null) {
            Response::badRequest('Not authenticated');
        }

        $handle = (string)($me['username'] ?? 'player');

        $pRepo = new PlayerRepository();
        $playerId = $pRepo->ensureForUser($meId, $handle);

        $tpRepo = new TournamentPlayerRepository();
        $tpRepo->add($tournamentId, $playerId);

        try {
            $discordUserId = trim((string)($me['discord_user_id'] ?? ''));
            if ($discordUserId !== '') {
                Discord::tryAutoRoleOnSignup($discordUserId);
            }
        } catch (\Throwable) {
            // Discord integration is best-effort.
        }

        Flash::set('success', 'Inscription enregistree.');
        Response::redirect('/tournaments/' . $tournamentId);
    }

    /** @param array<string, string> $params */
    public function withdraw(array $params = []): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $tournamentId = (int)($params['id'] ?? 0);
        if ($tournamentId <= 0) {
            Response::notFound();
        }

        $tRepo = new TournamentRepository();
        $t = $tRepo->findById($tournamentId);
        if ($t === null) {
            Response::notFound();
        }

        $lanEventId = (int)($t['lan_event_id'] ?? 0);
        if ($lanEventId > 0) {
            $le = (new LanEventRepository())->findById($lanEventId);
            $lanSlug = is_array($le) ? (string)($le['slug'] ?? '') : '';
            Flash::set('error', "Ce tournoi fait partie d'un LAN: gerer l'inscription depuis le LAN.");
            Response::redirect($lanSlug !== '' ? ('/lan/' . $lanSlug) : '/lan');
        }

        if (($t['participant_type'] ?? 'solo') === 'team') {
            Flash::set('error', 'Tournoi en equipe: quitte ton equipe.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $status = (string)($t['status'] ?? 'draft');
        if ($status === 'completed' && !Auth::isAdmin()) {
            Flash::set('error', 'Tournoi termine.');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $mRepo = new MatchRepository();
        if (!Auth::isAdmin() && $mRepo->countForTournament($tournamentId) > 0) {
            Flash::set('error', 'Retrait verrouille (bracket deja genere).');
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $signupClosesAt = $t['signup_closes_at'] ?? null;
        if (!Auth::isAdmin() && is_string($signupClosesAt) && $signupClosesAt !== '') {
            $ts = strtotime($signupClosesAt);
            if ($ts !== false && $ts <= time()) {
                Flash::set('error', 'Retrait bloque (inscriptions fermees).');
                Response::redirect('/tournaments/' . $tournamentId);
            }
        }

        $meId = Auth::id();
        if ($meId === null) {
            Response::badRequest('Not authenticated');
        }

        $pRepo = new PlayerRepository();
        $p = $pRepo->findByUserId($meId);
        if ($p === null) {
            Flash::set('error', "Tu n'es pas inscrit.");
            Response::redirect('/tournaments/' . $tournamentId);
        }

        $tpRepo = new TournamentPlayerRepository();
        $tpRepo->remove($tournamentId, (int)$p['id']);

        Flash::set('success', 'Inscription annulee.');
        Response::redirect('/tournaments/' . $tournamentId);
    }
}
