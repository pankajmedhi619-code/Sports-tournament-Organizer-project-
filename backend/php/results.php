
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
$pdo = getConnection();

function resultResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);

    $response = [
        'status' => $status,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function resultInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

$method = $_SERVER['REQUEST_METHOD'];

/* GET: Fetch saved results for one tournament */
if ($method === 'GET') {
    $tournamentId = filter_var(
        $_GET['tournament_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$tournamentId || $tournamentId < 1) {
        resultResponse('error', 'Valid tournament_id is required.', null, 400);
    }

    $sql = "SELECT
                r.id AS result_id,
                r.match_id,
                r.team1_score,
                r.team2_score,
                r.winner_team_id,
                r.outcome,
                r.recorded_at,
                m.tournament_id,
                m.group_id,
                m.match_date,
                m.venue,
                m.stage,
                m.status,
                t1.id AS team1_id,
                t1.name AS team1_name,
                t2.id AS team2_id,
                t2.name AS team2_name
            FROM results r
            JOIN matches m ON m.id = r.match_id
            JOIN teams t1 ON t1.id = m.team1_id
            JOIN teams t2 ON t2.id = m.team2_id
            WHERE m.tournament_id = ?
            ORDER BY r.recorded_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tournamentId]);

    resultResponse('success', 'Tournament results loaded.', $stmt->fetchAll());
}

/* POST: Save or update scores */
if ($method === 'POST') {
    $data = resultInput();

    $matchId = filter_var(
        $data['match_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $score1 = $data['team1_score'] ?? null;
    $score2 = $data['team2_score'] ?? null;

    if (
        !$matchId || $matchId < 1 ||
        filter_var($score1, FILTER_VALIDATE_INT) === false ||
        filter_var($score2, FILTER_VALIDATE_INT) === false ||
        (int)$score1 < 0 ||
        (int)$score2 < 0
    ) {
        resultResponse(
            'error',
            'Match ID and non-negative whole-number scores are required.',
            null,
            400
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id, team1_id, team2_id
         FROM matches WHERE id = ?'
    );
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) {
        resultResponse('error', 'Match not found.', null, 404);
    }

    $score1 = (int)$score1;
    $score2 = (int)$score2;

    if ($score1 > $score2) {
        $winnerId = (int)$match['team1_id'];
        $outcome = 'team1_win';
    } elseif ($score2 > $score1) {
        $winnerId = (int)$match['team2_id'];
        $outcome = 'team2_win';
    } else {
        $winnerId = null;
        $outcome = 'draw';
    }

    try {
        $pdo->beginTransaction();

        // match_id is UNIQUE, so repeated submissions update the result.
        $stmt = $pdo->prepare(
            "INSERT INTO results
                (match_id, team1_score, team2_score, winner_team_id, outcome)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                team1_score = VALUES(team1_score),
                team2_score = VALUES(team2_score),
                winner_team_id = VALUES(winner_team_id),
                outcome = VALUES(outcome),
                recorded_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            $matchId,
            $score1,
            $score2,
            $winnerId,
            $outcome
        ]);

        $stmt = $pdo->prepare(
            "UPDATE matches SET status = 'completed' WHERE id = ?"
        );
        $stmt->execute([$matchId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());
        resultResponse('error', 'Could not save the result.', null, 500);
    }

    resultResponse('success', 'Result saved successfully.', [
        'match_id' => $matchId,
        'team1_score' => $score1,
        'team2_score' => $score2,
        'winner_team_id' => $winnerId,
        'outcome' => $outcome,
        'match_status' => 'completed'
    ], 201);
}

resultResponse('error', 'Method not allowed.', null, 405);
