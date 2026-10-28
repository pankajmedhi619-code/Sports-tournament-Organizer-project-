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

// Fetch groups
if ($method === 'GET') {
    // get group by id with its teams
    if (isset($_GET['id'])) {
        $groupId = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT g.*, t.name AS tournament_name FROM groups g 
                               JOIN tournaments t ON g.tournament_id = t.id 
                               WHERE g.id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group) {
            sendResponse('error', 'Group not found', null, 404);
        }

        $membersStmt = $pdo->prepare("SELECT tm.id, tm.name, tm.sport 
                                      FROM group_members gm 
                                      JOIN teams tm ON gm.team_id = tm.id 
                                      WHERE gm.group_id = ? 
                                      ORDER BY tm.id ASC");
        $membersStmt->execute([$groupId]);
        $group['teams'] = $membersStmt->fetchAll();

        sendResponse('success', 'Group found', $group);
    }

    // get all groups for a tournament
    if (isset($_GET['tournament_id'])) {
        $tournamentId = (int)$_GET['tournament_id'];

        $tCheck = $pdo->prepare("SELECT id, name FROM tournaments WHERE id = ?");
        $tCheck->execute([$tournamentId]);
        $tournament = $tCheck->fetch();

        if (!$tournament) {
            sendResponse('error', 'Tournament not found', null, 404);
        }

        $stmt = $pdo->prepare("SELECT id, tournament_id, group_name FROM groups WHERE tournament_id = ? ORDER BY id ASC");
        $stmt->execute([$tournamentId]);
        $groups = $stmt->fetchAll();

        $memberStmt = $pdo->prepare("SELECT tm.id, tm.name, tm.sport 
                                     FROM group_members gm 
                                     JOIN teams tm ON gm.team_id = tm.id 
                                     WHERE gm.group_id = ? AND gm.tournament_id = ? 
                                     ORDER BY tm.id ASC");

        foreach ($groups as &$group) {
            $memberStmt->execute([$group['id'], $tournamentId]);
            $group['teams'] = $memberStmt->fetchAll();
        }
        unset($group);

        sendResponse('success', 'Groups list', [
            'tournament' => $tournament,
            'groups' => $groups
        ]);
    }

    // all groups list
    $stmt = $pdo->query("SELECT g.*, t.name AS tournament_name FROM groups g 
                         JOIN tournaments t ON g.tournament_id = t.id 
                         ORDER BY g.id DESC");
    $allGroups = $stmt->fetchAll();

    $memberStmt = $pdo->prepare("SELECT tm.id, tm.name, tm.sport 
                                 FROM group_members gm 
                                 JOIN teams tm ON gm.team_id = tm.id 
                                 WHERE gm.group_id = ? 
                                 ORDER BY tm.id ASC");
    foreach ($allGroups as &$group) {
        $memberStmt->execute([$group['id']]);
        $group['teams'] = $memberStmt->fetchAll();
    }
    unset($group);

    sendResponse('success', 'Groups list', $allGroups);
}

// Save groups (from DSU algorithm or manual)
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // add a single team to an existing group
    if (isset($data['action']) && $data['action'] === 'add_member') {
        $tournamentId = $data['tournament_id'] ?? null;
        $groupId = $data['group_id'] ?? null;
        $teamId = $data['team_id'] ?? null;

        if (!$tournamentId || !$groupId || !$teamId) {
            sendResponse('error', 'tournament_id, group_id, and team_id are required', null, 400);
        }

        $gCheck = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND tournament_id = ?");
        $gCheck->execute([$groupId, $tournamentId]);
        if (!$gCheck->fetch()) {
            sendResponse('error', 'Group not found in specified tournament', null, 404);
        }

        // check if team is registered in this tournament
        $ttCheck = $pdo->prepare("SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?");
        $ttCheck->execute([$tournamentId, $teamId]);
        if (!$ttCheck->fetch()) {
            sendResponse('error', 'Team is not registered in this tournament', null, 400);
        }

        // check if already assigned to a group
        $existingCheck = $pdo->prepare("SELECT group_id FROM group_members WHERE tournament_id = ? AND team_id = ?");
        $existingCheck->execute([$tournamentId, $teamId]);
        if ($existingCheck->fetch()) {
            sendResponse('error', 'Team is already assigned to a group in this tournament', null, 400);
        }

        $insertStmt = $pdo->prepare("INSERT INTO group_members (tournament_id, group_id, team_id) VALUES (?, ?, ?)");
        $insertStmt->execute([$tournamentId, $groupId, $teamId]);

        sendResponse('success', 'Team added to group', [
            'tournament_id' => $tournamentId,
            'group_id' => $groupId,
            'team_id' => $teamId
        ], 201);
    }

    // batch save groups generated from frontend DSU
    if (isset($data['groups']) && is_array($data['groups'])) {
        $tournamentId = $data['tournament_id'] ?? null;
        $replaceExisting = $data['replace'] ?? true;

        if (!$tournamentId) {
            sendResponse('error', 'tournament_id is required', null, 400);
        }

        // check tournament exists
        $tStmt = $pdo->prepare("SELECT id, name FROM tournaments WHERE id = ?");
        $tStmt->execute([$tournamentId]);
        $tournament = $tStmt->fetch();
        if (!$tournament) {
            sendResponse('error', 'Tournament not found', null, 404);
        }

        // get all teams registered for this tournament
        $ttStmt = $pdo->prepare("SELECT team_id FROM tournament_teams WHERE tournament_id = ?");
        $ttStmt->execute([$tournamentId]);
        $registeredTeamIds = array_column($ttStmt->fetchAll(), 'team_id');
        $registeredLookup = array_flip($registeredTeamIds);

        // validate teams before saving
        $assignedTeams = [];
        foreach ($data['groups'] as $gIndex => $grp) {
            $groupName = trim($grp['name'] ?? $grp['group_name'] ?? ('Group ' . ($gIndex + 1)));
            $teamIds = $grp['team_ids'] ?? $grp['teamIds'] ?? [];

            if (!is_array($teamIds)) {
                sendResponse('error', "Teams in '{$groupName}' must be an array of team IDs", null, 400);
            }

            foreach ($teamIds as $tid) {
                if (!isset($registeredLookup[$tid])) {
                    sendResponse('error', "Team ID {$tid} is not registered in tournament {$tournamentId}", null, 400);
                }
                if (isset($assignedTeams[$tid])) {
                    sendResponse('error', "Team ID {$tid} cannot be assigned to multiple groups", null, 400);
                }
                $assignedTeams[$tid] = true;
            }
        }

        try {
            $pdo->beginTransaction();

            // clear old groups if replacing
            if ($replaceExisting) {
                $delGroups = $pdo->prepare("DELETE FROM groups WHERE tournament_id = ?");
                $delGroups->execute([$tournamentId]);
            }

            $insertGroup = $pdo->prepare("INSERT INTO groups (tournament_id, group_name) VALUES (?, ?)");
            $insertMember = $pdo->prepare("INSERT INTO group_members (tournament_id, group_id, team_id) VALUES (?, ?, ?)");

            $savedGroups = [];

            foreach ($data['groups'] as $gIndex => $grp) {
                $groupName = trim($grp['name'] ?? $grp['group_name'] ?? ('Group ' . ($gIndex + 1)));
                $teamIds = $grp['team_ids'] ?? $grp['teamIds'] ?? [];

                $insertGroup->execute([$tournamentId, $groupName]);
                $newGroupId = $pdo->lastInsertId();

                foreach ($teamIds as $tid) {
                    $insertMember->execute([$tournamentId, $newGroupId, (int)$tid]);
                }

                $savedGroups[] = [
                    'id' => (int)$newGroupId,
                    'tournament_id' => (int)$tournamentId,
                    'group_name' => $groupName,
                    'team_ids' => array_map('intval', $teamIds)
                ];
            }

            $pdo->commit();

            sendResponse('success', 'Groups saved successfully', [
                'tournament_id' => (int)$tournamentId,
                'groups' => $savedGroups
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse('error', 'Failed to save groups: ' . $e->getMessage(), null, 500);
        }
    }

    // single group creation
    $tournamentId = $data['tournament_id'] ?? null;
    $groupName = trim($data['group_name'] ?? $data['name'] ?? '');
    $teamIds = $data['team_ids'] ?? $data['teamIds'] ?? [];

    if (!$tournamentId || empty($groupName)) {
        sendResponse('error', 'tournament_id and group_name are required', null, 400);
    }

    $tStmt = $pdo->prepare("SELECT id FROM tournaments WHERE id = ?");
    $tStmt->execute([$tournamentId]);
    if (!$tStmt->fetch()) {
        sendResponse('error', 'Tournament not found', null, 404);
    }

    $dupCheck = $pdo->prepare("SELECT id FROM groups WHERE tournament_id = ? AND LOWER(group_name) = LOWER(?)");
    $dupCheck->execute([$tournamentId, $groupName]);
    if ($dupCheck->fetch()) {
        sendResponse('error', "A group with name '{$groupName}' already exists in this tournament", null, 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO groups (tournament_id, group_name) VALUES (?, ?)");
        $stmt->execute([$tournamentId, $groupName]);
        $groupId = $pdo->lastInsertId();

        if (!empty($teamIds) && is_array($teamIds)) {
            $insertMember = $pdo->prepare("INSERT INTO group_members (tournament_id, group_id, team_id) VALUES (?, ?, ?)");
            foreach ($teamIds as $tid) {
                $insertMember->execute([$tournamentId, $groupId, (int)$tid]);
            }
        }

        $pdo->commit();

        sendResponse('success', 'Group created', [
            'id' => (int)$groupId,
            'tournament_id' => (int)$tournamentId,
            'group_name' => $groupName,
            'team_ids' => array_map('intval', $teamIds)
        ], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse('error', 'Failed to create group: ' . $e->getMessage(), null, 500);
    }
}

// update group name
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $_GET['id'] ?? $data['id'] ?? null;
    $groupName = trim($data['group_name'] ?? $data['name'] ?? '');

    if (!$id || empty($groupName)) {
        sendResponse('error', 'Group ID and group_name required', null, 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$id]);
    $group = $stmt->fetch();

    if (!$group) {
        sendResponse('error', 'Group not found', null, 404);
    }

    // avoid duplicate name in same tournament
    $check = $pdo->prepare("SELECT id FROM groups WHERE tournament_id = ? AND LOWER(group_name) = LOWER(?) AND id <> ?");
    $check->execute([$group['tournament_id'], $groupName, $id]);
    if ($check->fetch()) {
        sendResponse('error', 'Group name already exists in this tournament', null, 400);
    }

    $update = $pdo->prepare("UPDATE groups SET group_name = ? WHERE id = ?");
    $update->execute([$groupName, $id]);

    sendResponse('success', 'Group updated', [
        'id' => (int)$id,
        'tournament_id' => (int)$group['tournament_id'],
        'group_name' => $groupName
    ]);
}

// delete group or remove team
if ($method === 'DELETE') {
    // remove a single team from group
    if (isset($_GET['action']) && $_GET['action'] === 'remove_member') {
        $groupId = $_GET['group_id'] ?? null;
        $teamId = $_GET['team_id'] ?? null;

        if (!$groupId || !$teamId) {
            sendResponse('error', 'group_id and team_id required', null, 400);
        }

        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND team_id = ?");
        $stmt->execute([$groupId, $teamId]);

        sendResponse('success', 'Team removed from group');
    }

    // clear all groups of tournament
    if (isset($_GET['tournament_id'])) {
        $tournamentId = (int)$_GET['tournament_id'];
        $stmt = $pdo->prepare("DELETE FROM groups WHERE tournament_id = ?");
        $stmt->execute([$tournamentId]);

        sendResponse('success', 'All groups cleared for tournament');
    }

    // delete one group
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    }

    if (!$id) {
        sendResponse('error', 'Group ID or tournament_id required', null, 400);
    }

    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse('success', 'Group deleted');
}

sendResponse('error', 'Method not allowed', null, 405);
