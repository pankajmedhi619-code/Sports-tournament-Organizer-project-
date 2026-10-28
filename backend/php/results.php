<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

$pdo = getConnection();
$method = $_SERVER["REQUEST_METHOD"];

function sendResponse($status, $message, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);

    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

function getJsonInput()
{
    $input = json_decode(file_get_contents("php://input"), true);
    return is_array($input) ? $input : [];
}

try {
    // GET: Fetch tournament results
    if ($method === "GET") {
        $tournamentId = $_GET["tournament_id"] ?? null;

        if (!$tournamentId || !is_numeric($tournamentId)) {
            sendResponse("error", "Tournament ID is required", null, 400);
        }

        $sql = "
            SELECT
                r.id AS result_id,
                r.match_id,
                m.tournament_id,
                m.group_id,
                m.stage,
                m.match_date,
                m.venue,
                m.status,
                m.team1_id,
                t1.name AS team1_name,
                r.team1_score,
                m.team2_id,
                t2.name AS team2_name,
                r.team2_score,
                r.winner_team_id,
                r.outcome,
                r.recorded_at
            FROM results r
            JOIN matches m ON r.match_id = m.id
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            WHERE m.tournament_id = ?
            ORDER BY m.match_date, m.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$tournamentId]);

        sendResponse("success", "Results fetched successfully", $stmt->fetchAll());
    }

    // POST: Save or update match result
    if ($method === "POST") {
        $data = getJsonInput();

        $matchId = $data["match_id"] ?? null;
        $team1Score = $data["team1_score"] ?? null;
        $team2Score = $data["team2_score"] ?? null;

        if (
            !$matchId ||
            !is_numeric($matchId) ||
            !is_numeric($team1Score) ||
            !is_numeric($team2Score)
        ) {
            sendResponse(
                "error",
                "Match ID and both scores are required",
                null,
                400
            );
        }

        $matchId = (int)$matchId;
        $team1Score = (int)$team1Score;
        $team2Score = (int)$team2Score;

        if ($team1Score < 0 || $team2Score < 0) {
            sendResponse("error", "Scores cannot be negative", null, 400);
        }

        // Determine winner and outcome
        if ($team1Score > $team2Score) {
            $outcome = "team1_win";
        } elseif ($team2Score > $team1Score) {
            $outcome = "team2_win";
        } else {
            $outcome = "draw";
        }

        $pdo->beginTransaction();

        // Fetch the match teams
        $matchStmt = $pdo->prepare("
            SELECT id, team1_id, team2_id
            FROM matches
            WHERE id = ?
            FOR UPDATE
        ");

        $matchStmt->execute([$matchId]);
        $match = $matchStmt->fetch();

        if (!$match) {
            $pdo->rollBack();
            sendResponse("error", "Match not found", null, 404);
        }

        $winnerTeamId = null;

        if ($outcome === "team1_win") {
            $winnerTeamId = (int)$match["team1_id"];
        } elseif ($outcome === "team2_win") {
            $winnerTeamId = (int)$match["team2_id"];
        }

        // Insert result or update the existing result
        $resultStmt = $pdo->prepare("
            INSERT INTO results
                (match_id, team1_score, team2_score, winner_team_id, outcome)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                team1_score = VALUES(team1_score),
                team2_score = VALUES(team2_score),
                winner_team_id = VALUES(winner_team_id),
                outcome = VALUES(outcome),
                recorded_at = CURRENT_TIMESTAMP
        ");

        $resultStmt->execute([
            $matchId,
            $team1Score,
            $team2Score,
            $winnerTeamId,
            $outcome
        ]);

        // Mark match as completed
        $updateMatch = $pdo->prepare("
            UPDATE matches
            SET status = 'completed'
            WHERE id = ?
        ");

        $updateMatch->execute([$matchId]);

        $pdo->commit();

        sendResponse("success", "Result saved successfully", [
            "match_id" => $matchId,
            "team1_score" => $team1Score,
            "team2_score" => $team2Score,
            "winner_team_id" => $winnerTeamId,
            "outcome" => $outcome
        ]);
    }

    sendResponse("error", "Method not allowed", null, 405);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse("error", "Server error: " . $e->getMessage(), null, 500);
}
?>