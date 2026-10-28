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
    // GET: Fetch matches for a tournament
    if ($method === "GET") {
        $tournamentId = $_GET["tournament_id"] ?? null;

        if (!$tournamentId || !is_numeric($tournamentId)) {
            sendResponse("error", "Tournament ID is required", null, 400);
        }

        $sql = "
            SELECT
                m.id,
                m.tournament_id,
                m.group_id,
                g.group_name,
                m.team1_id,
                t1.name AS team1_name,
                m.team2_id,
                t2.name AS team2_name,
                m.match_date,
                m.venue,
                m.stage,
                m.status,
                r.team1_score,
                r.team2_score,
                r.winner_team_id,
                r.outcome
            FROM matches m
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN groups g ON m.group_id = g.id
            LEFT JOIN results r ON m.id = r.match_id
            WHERE m.tournament_id = ?
            ORDER BY m.match_date IS NULL, m.match_date, m.id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$tournamentId]);

        sendResponse("success", "Matches fetched successfully", $stmt->fetchAll());
    }

    // POST: Create or generate matches
    if ($method === "POST") {
        $data = getJsonInput();
        $action = $data["action"] ?? "schedule";

        $tournamentId = $data["tournament_id"] ?? null;

        if (!$tournamentId || !is_numeric($tournamentId)) {
            sendResponse("error", "Tournament ID is required", null, 400);
        }

        $tournamentId = (int)$tournamentId;

        // Generate round-robin matches within each group
        if ($action === "generate_round_robin") {
            $stmt = $pdo->prepare("
                SELECT id, group_id, team_id
                FROM group_members
                WHERE tournament_id = ?
                ORDER BY group_id, team_id
            ");

            $stmt->execute([$tournamentId]);
            $members = $stmt->fetchAll();

            if (count($members) < 2) {
                sendResponse(
                    "error",
                    "At least two group members are required",
                    null,
                    400
                );
            }

            $groupTeams = [];

            foreach ($members as $member) {
                $groupId = (int)$member["group_id"];
                $groupTeams[$groupId][] = (int)$member["team_id"];
            }

            $insert = $pdo->prepare("
                INSERT INTO matches
                    (tournament_id, group_id, team1_id, team2_id, stage, status)
                VALUES (?, ?, ?, ?, 'group', 'scheduled')
            ");

            $created = 0;

            $pdo->beginTransaction();

            foreach ($groupTeams as $groupId => $teamIds) {
                $count = count($teamIds);

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $team1 = $teamIds[$i];
                        $team2 = $teamIds[$j];

                        // Avoid creating the same fixture twice
                        $check = $pdo->prepare("
                            SELECT id
                            FROM matches
                            WHERE tournament_id = ?
                              AND group_id = ?
                              AND (
                                  (team1_id = ? AND team2_id = ?)
                                  OR
                                  (team1_id = ? AND team2_id = ?)
                              )
                            LIMIT 1
                        ");

                        $check->execute([
                            $tournamentId,
                            $groupId,
                            $team1,
                            $team2,
                            $team2,
                            $team1
                        ]);

                        if (!$check->fetch()) {
                            $insert->execute([
                                $tournamentId,
                                $groupId,
                                $team1,
                                $team2
                            ]);

                            $created++;
                        }
                    }
                }
            }

            $pdo->commit();

            sendResponse(
                "success",
                "Round-robin fixtures generated",
                ["created" => $created]
            );
        }

        // Manual match scheduling
        $team1 = $data["team1_id"] ?? null;
        $team2 = $data["team2_id"] ?? null;
        $groupId = $data["group_id"] ?? null;
        $matchDate = $data["match_date"] ?? null;
        $venue = $data["venue"] ?? null;
        $stage = $data["stage"] ?? "group";

        if (
            !$team1 ||
            !$team2 ||
            !is_numeric($team1) ||
            !is_numeric($team2)
        ) {
            sendResponse("error", "Both team IDs are required", null, 400);
        }

        $team1 = (int)$team1;
        $team2 = (int)$team2;

        if ($team1 === $team2) {
            sendResponse("error", "A team cannot play against itself", null, 400);
        }

        // Ensure both teams belong to this tournament
        $teamCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM tournament_teams
            WHERE tournament_id = ?
              AND team_id IN (?, ?)
        ");

        $teamCheck->execute([$tournamentId, $team1, $team2]);

        if ((int)$teamCheck->fetchColumn() !== 2) {
            sendResponse(
                "error",
                "Both teams must be registered in this tournament",
                null,
                400
            );
        }

        // If a group is selected, ensure it belongs to this tournament
        if ($groupId !== null && $groupId !== "") {
            $groupCheck = $pdo->prepare("
                SELECT id
                FROM groups
                WHERE id = ?
                  AND tournament_id = ?
            ");

            $groupCheck->execute([(int)$groupId, $tournamentId]);

            if (!$groupCheck->fetch()) {
                sendResponse("error", "Invalid group for this tournament", null, 400);
            }

            $groupId = (int)$groupId;
        } else {
            $groupId = null;
        }

        $insert = $pdo->prepare("
            INSERT INTO matches
                (tournament_id, group_id, team1_id, team2_id,
                 match_date, venue, stage, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
        ");

        $insert->execute([
            $tournamentId,
            $groupId,
            $team1,
            $team2,
            $matchDate ?: null,
            $venue ?: null,
            $stage
        ]);

        sendResponse(
            "success",
            "Match scheduled successfully",
            ["match_id" => (int)$pdo->lastInsertId()],
            201
        );
    }

    // PUT: Update match status or venue/date
    if ($method === "PUT") {
        $data = getJsonInput();
        $matchId = $_GET["id"] ?? $data["id"] ?? null;

        if (!$matchId || !is_numeric($matchId)) {
            sendResponse("error", "Match ID is required", null, 400);
        }

        $allowedFields = [
            "status",
            "venue",
            "match_date",
            "stage"
        ];

        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            sendResponse("error", "No valid fields to update", null, 400);
        }

        $values[] = (int)$matchId;

        $sql = "
            UPDATE matches
            SET " . implode(", ", $updates) . "
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        sendResponse("success", "Match updated successfully");
    }

    // DELETE: Remove a match
    if ($method === "DELETE") {
        $matchId = $_GET["id"] ?? null;

        if (!$matchId || !is_numeric($matchId)) {
            sendResponse("error", "Match ID is required", null, 400);
        }

        $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
        $stmt->execute([(int)$matchId]);

        sendResponse("success", "Match deleted successfully");
    }

    sendResponse("error", "Method not allowed", null, 405);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse("error", "Server error: " . $e->getMessage(), null, 500);
}
?>