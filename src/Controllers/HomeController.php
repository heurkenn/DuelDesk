<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Repositories\TournamentRepository;
use DuelDesk\View;
use Throwable;

final class HomeController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $recent = [];
        $dbError = null;

        try {
            $repo = new TournamentRepository();
            $recent = array_slice($repo->all(), 0, 5);
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        View::render('home', [
            'title' => 'DuelDesk',
            'recentTournaments' => $recent,
            'dbError' => $dbError,
        ]);
    }
}
