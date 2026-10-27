
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
$pdo = getConnection();

function matchResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['status' => $status, 'message' => $message];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function matchInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

$method = $_SERVER['REQUEST_METHOD'];

/* GET: Fetch matches for a tournament */
if ($method === 'GET') {
    $tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);

    if (!$tournamentId || $tournamentId < 1) {
        matchResponse('error', 'Valid tournament_id is required.', null, 400);
    }

    $sql = "SELECT m.id, m.tournament_id, m.group_id,
                   m.team1_id, m.team2_id,
                   t1.name AS team1_name,
                   t2.name AS team2_name,
                   g.group_name, m.match_date, m.venue,
                   m.stage, m.status,
                   r.team1_score, r.team2_score,
                   r.winner_team_id, r.outcome
            FROM matches m
            JOIN teams t1 ON t1.id = m.team1_id
            JOIN teams t2 ON t2.id = m.team2_id
            LEFT JOIN groups g ON g.id = m.group_id
            LEFT JOIN results r ON r.match_id = m.id
            WHERE m.tournament_id = ?
            ORDER BY (m.match_date IS NULL), m.match_date, m.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tournamentId]);

    matchResponse('success', 'Matches loaded.', $stmt->fetchAll());
}

/* POST: Generate round-robin fixtures or schedule a match */
if ($method === 'POST') {
    $data = matchInput();
    $action = $data['action'] ?? '';

    if ($action === 'generate_round_robin') {
        $tournamentId = filter_var(
            $data['tournament_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$tournamentId || $tournamentId < 1) {
            matchResponse('error', 'Valid tournament_id is required.', null, 400);
        }

        $stmt = $pdo->prepare(
            'SELECT id, format FROM tournaments WHERE id = ?'
        );
        $stmt->execute([$tournamentId]);
        $tournament = $stmt->fetch();

        if (!$tournament) {
            matchResponse('error', 'Tournament not found.', null, 404);
        }

        if (!in_array($tournament['format'], ['round_robin', 'both'], true)) {
            matchResponse(
                'error',
                'Round-robin fixtures are not enabled for this tournament.',
                null,
                400
            );
        }

        $stmt = $pdo->prepare(
            'SELECT id, group_name FROM groups
             WHERE tournament_id = ? ORDER BY id'
        );
        $stmt->execute([$tournamentId]);
        $groups = $stmt->fetchAll();

        if (!$groups) {
            matchResponse(
                'error',
                'No saved groups found. Create and save groups first.',
                null,
                400
            );
        }

        $members = $pdo->prepare(
            'SELECT gm.team_id, t.name
             FROM group_members gm
             JOIN teams t ON t.id = gm.team_id
             WHERE gm.tournament_id = ? AND gm.group_id = ?
             ORDER BY gm.team_id'
        );

        $existing = $pdo->prepare(
            "SELECT team1_id, team2_id FROM matches
             WHERE tournament_id = ? AND group_id = ? AND stage = 'group'"
        );

        $insert = $pdo->prepare(
            "INSERT INTO matches
             (tournament_id, group_id, team1_id, team2_id, stage, status)
             VALUES (?, ?, ?, ?, 'group', 'scheduled')"
        );

        $created = [];
        $skipped = 0;

        try {
            $pdo->beginTransaction();

            foreach ($groups as $group) {
                $members->execute([$tournamentId, $group['id']]);
                $teams = $members->fetchAll();

                if (count($teams) < 2) {
                    continue;
                }

                // Keep existing fixtures to avoid duplicates.
                $existing->execute([$tournamentId, $group['id']]);
                $seen = [];

                foreach ($existing->fetchAll() as $match) {
                    $a = (int)$match['team1_id'];
                    $b = (int)$match['team2_id'];
                    $seen[min($a, $b) . '-' . max($a, $b)] = true;
                }

                // Single round-robin: each pair plays once.
                $count = count($teams);

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $a = (int)$teams[$i]['team_id'];
                        $b = (int)$teams[$j]['team_id'];

                        if ($a === $b) {
                            continue;
                        }

                        $key = min($a, $b) . '-' . max($a, $b);

                        if (isset($seen[$key])) {
                            $skipped++;
                            continue;
                        }

                        $insert->execute([
                            $tournamentId,
                            $group['id'],
                            $a,
                            $b
                        ]);

                        $seen[$key] = true;

                        $created[] = [
                            'group' => $group['group_name'],
                            'team1' => $teams[$i]['name'],
                            'team2' => $teams[$j]['name']
                        ];
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            matchResponse('error', 'Fixture generation failed.', null, 500);
        }

        matchResponse('success', 'Fixtures generated.', [
            'created_count' => count($created),
            'skipped_existing_count' => $skipped,
            'created' => $created
        ]);
    }

    if ($action === 'schedule') {
        $tid = filter_var($data['tournament_id'] ?? null, FILTER_VALIDATE_INT);
        $a = filter_var($data['team1_id'] ?? null, FILTER_VALIDATE_INT);
        $b = filter_var($data['team2_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$tid || !$a || !$b || $a === $b) {
            matchResponse(
                'error',
                'Choose a tournament and two different teams.',
                null,
                400
            );
        }

        $date = trim($data['match_date'] ?? '');
        $venue = trim($data['venue'] ?? '');
        $groupId = !empty($data['group_id'])
            ? (int)$data['group_id']
            : null;

        $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $date);

        if (!$dt || $dt->format('Y-m-d\TH:i') !== $date) {
            matchResponse('error', 'Valid match date and time are required.', null, 400);
        }

        $stmt = $pdo->prepare(
            'SELECT team_id FROM tournament_teams
             WHERE tournament_id = ? AND team_id IN (?, ?)'
        );
        $stmt->execute([$tid, $a, $b]);

        if (count($stmt->fetchAll()) !== 2) {
            matchResponse(
                'error',
                'Both teams must be registered in this tournament.',
                null,
                400
            );
        }

        if ($groupId !== null) {
            $stmt = $pdo->prepare(
                'SELECT id FROM groups WHERE id = ? AND tournament_id = ?'
            );
            $stmt->execute([$groupId, $tid]);

            if (!$stmt->fetch()) {
                matchResponse('error', 'Group does not belong to this tournament.', null, 400);
            }
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM matches
             WHERE tournament_id = ?
               AND LEAST(team1_id, team2_id) = LEAST(?, ?)
               AND GREATEST(team1_id, team2_id) = GREATEST(?, ?)
               AND stage = 'group'"
        );
        $stmt->execute([$tid, $a, $b, $a, $b]);

        if ($stmt->fetch()) {
            matchResponse('error', 'This fixture already exists.', null, 409);
        }

        if ($venue !== '') {
            $stmt = $pdo->prepare(
                "SELECT id FROM matches
                 WHERE match_date = ? AND venue = ?
                   AND status <> 'completed'"
            );
            $stmt->execute([$dt->format('Y-m-d H:i:s'), $venue]);

            if ($stmt->fetch()) {
                matchResponse(
                    'error',
                    'This venue already has a match at that time.',
                    null,
                    409
                );
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO matches
             (tournament_id, group_id, team1_id, team2_id,
              match_date, venue, stage, status)
             VALUES (?, ?, ?, ?, ?, ?, 'group', 'scheduled')"
        );

        $stmt->execute([
            $tid, $groupId, $a, $b,
            $dt->format('Y-m-d H:i:s'), $venue
        ]);

        matchResponse('success', 'Match scheduled.', [
            'id' => (int)$pdo->lastInsertId()
        ], 201);
    }

    matchResponse('error', 'Unknown action.', null, 400);
}

/* PUT: Update match status */
if ($method === 'PUT') {
    $data = matchInput();
    $id = filter_var($_GET['id'] ?? $data['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $data['status'] ?? '';

    if (!$id || !in_array($status, ['scheduled', 'ongoing', 'completed'], true)) {
        matchResponse('error', 'Valid match ID and status are required.', null, 400);
    }

    $stmt = $pdo->prepare('UPDATE matches SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    $check = $pdo->prepare('SELECT id FROM matches WHERE id = ?');
    $check->execute([$id]);

    if (!$check->fetch()) {
        matchResponse('error', 'Match not found.', null, 404);
    }

    matchResponse('success', 'Match status updated.', [
        'id' => $id,
        'status' => $status
    ]);
}

matchResponse('error', 'Method not allowed.', null, 405);
