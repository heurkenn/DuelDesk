<?php

declare(strict_types=1);

require __DIR__ . '/../src/Bootstrap.php';

use DuelDesk\Controllers\HomeController;
use DuelDesk\Controllers\AuthController;
use DuelDesk\Controllers\AdminController;
use DuelDesk\Controllers\AdminGameController;
use DuelDesk\Controllers\AdminTournamentController;
use DuelDesk\Controllers\MatchReportController;
use DuelDesk\Controllers\TournamentController;
use DuelDesk\Controllers\TournamentSignupController;
use DuelDesk\Controllers\TournamentTeamController;
use DuelDesk\Controllers\TeamController;
use DuelDesk\Http\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

$router->get('/register', [AuthController::class, 'register']);
$router->post('/register', [AuthController::class, 'registerPost']);
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'loginPost']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users/{id:\\d+}/role', [AdminController::class, 'updateRole']);
$router->get('/admin/games', [AdminGameController::class, 'index']);
$router->post('/admin/games', [AdminGameController::class, 'create']);
$router->get('/admin/games/{id:\\d+}', [AdminGameController::class, 'edit']);
$router->post('/admin/games/{id:\\d+}', [AdminGameController::class, 'update']);
$router->post('/admin/games/{id:\\d+}/delete', [AdminGameController::class, 'delete']);
$router->get('/admin/tournaments/{id:\\d+}', [AdminTournamentController::class, 'show']);
$router->post('/admin/tournaments/{id:\\d+}/settings', [AdminTournamentController::class, 'updateSettings']);
$router->post('/admin/tournaments/{id:\\d+}/bracket/generate', [AdminTournamentController::class, 'generateBracket']);
$router->post('/admin/tournaments/{id:\\d+}/bracket/reset', [AdminTournamentController::class, 'resetBracket']);
$router->post('/admin/tournaments/{id:\\d+}/matches/{matchId:\\d+}/report', [AdminTournamentController::class, 'reportMatch']);
$router->post('/admin/tournaments/{id:\\d+}/matches/{matchId:\\d+}/report/reject', [AdminTournamentController::class, 'rejectReport']);
$router->post('/admin/tournaments/{id:\\d+}/matches/{matchId:\\d+}/schedule', [AdminTournamentController::class, 'scheduleMatch']);
$router->post('/admin/tournaments/{id:\\d+}/matches/{matchId:\\d+}/bestof', [AdminTournamentController::class, 'setBestOf']);
$router->post('/admin/tournaments/{id:\\d+}/players/{playerId:\\d+}/seed', [AdminTournamentController::class, 'setSeed']);
$router->post('/admin/tournaments/{id:\\d+}/players/{playerId:\\d+}/checkin', [AdminTournamentController::class, 'setCheckin']);
$router->post('/admin/tournaments/{id:\\d+}/players/{playerId:\\d+}/remove', [AdminTournamentController::class, 'removePlayer']);
$router->post('/admin/tournaments/{id:\\d+}/teams/{teamId:\\d+}/seed', [AdminTournamentController::class, 'setTeamSeed']);
$router->post('/admin/tournaments/{id:\\d+}/teams/{teamId:\\d+}/checkin', [AdminTournamentController::class, 'setTeamCheckin']);
$router->post('/admin/tournaments/{id:\\d+}/teams/{teamId:\\d+}/remove', [AdminTournamentController::class, 'removeTeam']);

$router->get('/tournaments', [TournamentController::class, 'index']);
$router->get('/tournaments/new', [TournamentController::class, 'new']);
$router->post('/tournaments', [TournamentController::class, 'create']);
$router->get('/tournaments/{id:\\d+}/matches/{matchId:\\d+}', [TournamentController::class, 'match']);
$router->post('/tournaments/{id:\\d+}/matches/{matchId:\\d+}/report', [MatchReportController::class, 'report']);
$router->get('/tournaments/{id:\\d+}', [TournamentController::class, 'show']);
$router->get('/t/{slug:[a-z0-9-]+}', [TournamentController::class, 'showBySlug']);
$router->post('/tournaments/{id:\\d+}/signup', [TournamentSignupController::class, 'signup']);
$router->post('/tournaments/{id:\\d+}/withdraw', [TournamentSignupController::class, 'withdraw']);
$router->post('/tournaments/{id:\\d+}/teams/create', [TournamentTeamController::class, 'create']);
$router->post('/tournaments/{id:\\d+}/teams/join', [TournamentTeamController::class, 'join']);
$router->post('/tournaments/{id:\\d+}/teams/{teamId:\\d+}/leave', [TournamentTeamController::class, 'leave']);
$router->get('/teams/{id:\\d+}', [TeamController::class, 'show']);
$router->post('/teams/{id:\\d+}/rename', [TeamController::class, 'rename']);
$router->post('/teams/{id:\\d+}/members/{userId:\\d+}/kick', [TeamController::class, 'kick']);
$router->post('/teams/{id:\\d+}/members/{userId:\\d+}/captain', [TeamController::class, 'setCaptain']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
