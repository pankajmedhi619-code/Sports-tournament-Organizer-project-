<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';
$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    $res = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $res['data'] = $data;
    }
    echo json_encode($res);
    exit;
}

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $tournament = $stmt->fetch();

        if (!$tournament) {
            sendResponse('error', 'Tournament not found', null, 404);
        }

        $teamsStmt = $pdo->prepare("SELECT t.id, t.name, t.sport FROM teams t 
                                    JOIN tournament_teams tt ON t.id = tt.team_id 
                                    WHERE tt.tournament_id = ?");
        $teamsStmt->execute([$_GET['id']]);
        $tournament['teams'] = $teamsStmt->fetchAll();

        sendResponse('success', 'Tournament found', $tournament);
    }

    if (isset($_GET['status'])) {
        $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE status = ? ORDER BY id DESC");
        $stmt->execute([$_GET['status']]);
        sendResponse('success', 'Tournaments list', $stmt->fetchAll());
    }

    $stmt = $pdo->query("SELECT * FROM tournaments ORDER BY id DESC");
    sendResponse('success', 'Tournaments list', $stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (isset($data['action']) && $data['action'] === 'add_team') {
        $tournamentId = $data['tournament_id'] ?? null;
        $teamId = $data['team_id'] ?? null;

        if (!$tournamentId || !$teamId) {
            sendResponse('error', 'Tournament ID and Team ID required', null, 400);
        }

        $tCheck = $pdo->prepare("SELECT id FROM tournaments WHERE id = ?");
        $tCheck->execute([$tournamentId]);
        if (!$tCheck->fetch()) {
            sendResponse('error', 'Tournament not found', null, 404);
        }

        $teamCheck = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
        $teamCheck->execute([$teamId]);
        if (!$teamCheck->fetch()) {
            sendResponse('error', 'Team not found', null, 404);
        }

        $checkLink = $pdo->prepare("SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?");
        $checkLink->execute([$tournamentId, $teamId]);
        if ($checkLink->fetch()) {
            sendResponse('error', 'Team already in tournament', null, 400);
        }

        $stmt = $pdo->prepare("INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?, ?)");
        $stmt->execute([$tournamentId, $teamId]);

        sendResponse('success', 'Team added to tournament', ['tournament_id' => $tournamentId, 'team_id' => $teamId], 201);
    }

    $name = trim($data['name'] ?? '');
    $sport = trim($data['sport'] ?? '');
    $format = $data['format'] ?? 'both';
    $status = $data['status'] ?? 'upcoming';
    $teamIds = $data['team_ids'] ?? [];

    if (empty($name) || empty($sport)) {
        sendResponse('error', 'Name and sport are required', null, 400);
    }

    $allowedFormats = ['knockout', 'round_robin', 'both'];
    if (!in_array($format, $allowedFormats)) {
        sendResponse('error', 'Invalid format. Allowed: knockout, round_robin, both', null, 400);
    }

    $allowedStatus = ['upcoming', 'ongoing', 'completed'];
    if (!in_array($status, $allowedStatus)) {
        sendResponse('error', 'Invalid status. Allowed: upcoming, ongoing, completed', null, 400);
    }

    $check = $pdo->prepare("SELECT id FROM tournaments WHERE LOWER(name) = LOWER(?)");
    $check->execute([$name]);
    if ($check->fetch()) {
        sendResponse('error', 'Tournament name already exists', null, 400);
    }

    $stmt = $pdo->prepare("INSERT INTO tournaments (name, sport, format, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $sport, $format, $status]);
    $tournamentId = $pdo->lastInsertId();

    if (!empty($teamIds) && is_array($teamIds)) {
        $linkStmt = $pdo->prepare("INSERT IGNORE INTO tournament_teams (tournament_id, team_id) VALUES (?, ?)");
        foreach ($teamIds as $tId) {
            $linkStmt->execute([$tournamentId, (int)$tId]);
        }
    }

    sendResponse('success', 'Tournament created', [
        'id' => $tournamentId,
        'name' => $name,
        'sport' => $sport,
        'format' => $format,
        'status' => $status
    ], 201);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $_GET['id'] ?? $data['id'] ?? null;

    if (!$id) {
        sendResponse('error', 'Tournament ID required', null, 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();

    if (!$tournament) {
        sendResponse('error', 'Tournament not found', null, 404);
    }

    $name = trim($data['name'] ?? $tournament['name']);
    $sport = trim($data['sport'] ?? $tournament['sport']);
    $format = $data['format'] ?? $tournament['format'];
    $status = $data['status'] ?? $tournament['status'];

    $updateStmt = $pdo->prepare("UPDATE tournaments SET name = ?, sport = ?, format = ?, status = ? WHERE id = ?");
    $updateStmt->execute([$name, $sport, $format, $status, $id]);

    sendResponse('success', 'Tournament updated', [
        'id' => $id,
        'name' => $name,
        'sport' => $sport,
        'format' => $format,
        'status' => $status
    ]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    }

    if (!$id) {
        sendResponse('error', 'Tournament ID required', null, 400);
    }

    $stmt = $pdo->prepare("DELETE FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse('success', 'Tournament deleted');
}

sendResponse('error', 'Method not allowed', null, 405);
