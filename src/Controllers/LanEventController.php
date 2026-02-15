<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Repositories\LanPlayerRepository;
use DuelDesk\Repositories\LanTeamMemberRepository;
use DuelDesk\Repositories\LanTeamRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Services\LanEnrollmentService;
use DuelDesk\Services\LanScoring;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\View;

final class LanEventController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $repo = new LanEventRepository();
        $events = $repo->all();

        View::render('lan/index', [
            'title' => 'LAN | DuelDesk',
            'events' => $events,
        ]);
    }

    /** @param array<string, string> $params */
    public function showBySlug(array $params = []): void
    {
        $slug = (string)($params['slug'] ?? '');
        if ($slug === '') {
            Response::notFound();
        }

        $repo = new LanEventRepository();
        $event = $repo->findBySlug($slug);
        if ($event === null) {
            Response::notFound();
        }

        $eId = (int)($event['id'] ?? 0);
        $tRepo = new TournamentRepository();
        $tournaments = $eId > 0 ? $tRepo->listByLanEventId($eId) : [];

        $scoring = LanScoring::compute($event, $tournaments);

        $ptype = (string)($event['participant_type'] ?? 'solo');
        if (!in_array($ptype, ['solo', 'team'], true)) {
            $ptype = 'solo';
        }

        $me = Auth::user();
        $meId = Auth::id();

        $isRegistered = false;
        $myLanTeam = null;
        $myLanTeamMembers = [];
        $teamSizeLimit = null;

        if ($meId !== null && $eId > 0) {
            if ($ptype === 'solo') {
                $isRegistered = (new LanPlayerRepository())->isRegistered($eId, $meId);
            } else {
                $ltRepo = new LanTeamRepository();
                $myLanTeam = $ltRepo->findForUser($eId, $meId);
                if (is_array($myLanTeam)) {
                    $lanTeamId = (int)($myLanTeam['id'] ?? 0);
                    if ($lanTeamId > 0) {
                        $myLanTeamMembers = (new LanTeamMemberRepository())->listMembers($lanTeamId);
                    }
                }
                try {
                    $teamSizeLimit = (new LanEnrollmentService())->effectiveTeamSizeLimit($eId);
                } catch (\Throwable) {
                    $teamSizeLimit = null;
                }
            }
        }

        View::render('lan/show', [
            'title' => ((string)($event['name'] ?? 'LAN')) . ' | DuelDesk',
            'event' => $event,
            'tournaments' => $tournaments,
            'scoring' => $scoring,
            'me' => $me,
            'isRegistered' => $isRegistered,
            'myLanTeam' => $myLanTeam,
            'myLanTeamMembers' => $myLanTeamMembers,
            'teamSizeLimit' => $teamSizeLimit,
            'csrfToken' => Csrf::token(),
        ]);
    }
}
