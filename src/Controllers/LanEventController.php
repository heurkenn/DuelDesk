<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\LanEventRepository;
use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\Services\LanScoring;
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

        View::render('lan/show', [
            'title' => ((string)($event['name'] ?? 'LAN')) . ' | DuelDesk',
            'event' => $event,
            'tournaments' => $tournaments,
            'scoring' => $scoring,
        ]);
    }
}
