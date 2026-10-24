<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
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
        $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $team = $stmt->fetch();
        if (!$team) {
            sendResponse('error', 'Team not found', null, 404);
        }
        sendResponse('success', 'Team found', $team);
    }

    if (isset($_GET['tournament_id'])) {
        $stmt = $pdo->prepare("SELECT t.* FROM teams t 
                               JOIN tournament_teams tt ON t.id = tt.team_id 
                               WHERE tt.tournament_id = ?");
        $stmt->execute([$_GET['tournament_id']]);
        sendResponse('success', 'Teams list', $stmt->fetchAll());
    }

    $stmt = $pdo->query("SELECT * FROM teams ORDER BY id ASC");
    sendResponse('success', 'Teams list', $stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $name = trim($data['name'] ?? '');
    $sport = trim($data['sport'] ?? '');
    $tournamentId = $data['tournament_id'] ?? null;

    if (empty($name) || empty($sport)) {
        sendResponse('error', 'Name and sport are required', null, 400);
    }

    $check = $pdo->prepare("SELECT id FROM teams WHERE name = ?");
    $check->execute([$name]);
    if ($check->fetch()) {
        sendResponse('error', 'Team name already exists', null, 400);
    }

    $stmt = $pdo->prepare("INSERT INTO teams (name, sport) VALUES (?, ?)");
    $stmt->execute([$name, $sport]);
    $teamId = $pdo->lastInsertId();

    if ($tournamentId) {
        $link = $pdo->prepare("INSERT INTO tournament_teams (tournament_id, team_id) VALUES (?, ?)");
        $link->execute([$tournamentId, $teamId]);
    }

    sendResponse('success', 'Team added', ['id' => $teamId, 'name' => $name, 'sport' => $sport], 201);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    }

    if (!$id) {
        sendResponse('error', 'Team ID required', null, 400);
    }

    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse('success', 'Team deleted');
}

sendResponse('error', 'Method not allowed', null, 405);
